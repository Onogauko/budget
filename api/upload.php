<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/budget_import.php';

require_method('POST');
$user = api_admin();
check_csrf();

$tmpDir = APP_ROOT . '/storage/tmp';
$upDir = APP_ROOT . '/storage/uploads';
$action = $_GET['action'] ?? '';

// Bersihkan pratinjau yang ditinggalkan lebih dari 1 hari
foreach (glob($tmpDir . '/*') ?: [] as $f) {
    if (is_file($f) && basename($f)[0] !== '.' && filemtime($f) < time() - 86400) @unlink($f);
}

function token_ok(string $t): bool { return (bool)preg_match('/^[a-f0-9]{32}$/', $t); }

if ($action === 'preview') {
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
        $msg = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'File terlalu besar.' : 'File belum dipilih atau gagal terkirim.';
        json_err($msg);
    }
    if ($f['size'] > cfg('max_upload_mb', 10) * 1024 * 1024) json_err('File terlalu besar (maks ' . cfg('max_upload_mb', 10) . ' MB).');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        json_err($ext === 'xls' ? 'Format .xls lama belum didukung. Simpan ulang sebagai .xlsx (File > Save As > Excel Workbook).' : 'Format file harus .xlsx atau .csv.');
    }
    $token = bin2hex(random_bytes(16));
    $stored = $tmpDir . '/' . $token . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $stored)) json_err('Gagal menyimpan file sementara.', 500);

    try {
        $res = analyze_budget_file($stored, $ext);
    } catch (Throwable $e) {
        @unlink($stored);
        json_err($e instanceof RuntimeException ? $e->getMessage() : 'File tidak bisa dibaca: ' . $e->getMessage());
    }
    file_put_contents($tmpDir . '/' . $token . '.json', json_encode([
        'ext' => $ext, 'name' => mb_substr(basename($f['name']), 0, 200), 'user' => (int)$user['id'], 'result' => $res,
    ]));
    unset($res['days']);
    json_out(['ok' => true, 'token' => $token, 'file' => $f['name']] + $res);
}

if ($action === 'commit' || $action === 'cancel') {
    $in = input_json();
    $token = (string)($in['token'] ?? '');
    if (!token_ok($token)) json_err('Token pratinjau tidak valid.');
    $metaFile = $tmpDir . '/' . $token . '.json';
    if (!is_file($metaFile)) json_err('Pratinjau sudah kedaluwarsa. Silakan upload ulang.');
    $meta = json_decode(file_get_contents($metaFile), true);
    $src = $tmpDir . '/' . $token . '.' . $meta['ext'];

    if ($action === 'cancel') {
        @unlink($metaFile); @unlink($src);
        json_out(['ok' => true]);
    }

    $res = $meta['result'];
    if (!empty($res['errors'])) json_err('File masih memiliki error, perbaiki dulu lalu upload ulang.');

    $storedName = date('Ymd_His') . '_' . $token . '.' . $meta['ext'];
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $insDiv = $pdo->prepare('INSERT INTO bg_divisions (code, name, sort_order, active) VALUES (?, ?, ?, 1)');
        $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM bg_divisions')->fetchColumn();
        $chk = $pdo->prepare('SELECT COUNT(*) FROM bg_divisions WHERE code = ?');
        foreach ($res['new_divisions'] as $code => $name) {
            $chk->execute([(int)$code]);
            if ((int)$chk->fetchColumn() === 0) $insDiv->execute([(int)$code, $name, ++$maxSort]);
        }

        $pdo->prepare('INSERT INTO bg_uploads (user_id, original_name, stored_name, date_from, date_to, days, total, replaced_days, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$user['id'], $meta['name'], $storedName, $res['date_from'], $res['date_to'], $res['days_count'], $res['total'], $res['replaced_days'], now_str()]);
        $uploadId = (int)$pdo->lastInsertId();

        $delB = $pdo->prepare('DELETE FROM bg_budget WHERE budget_date = ?');
        $delD = $pdo->prepare('DELETE FROM bg_days WHERE budget_date = ?');
        $insD = $pdo->prepare('INSERT INTO bg_days (budget_date, note, upload_id) VALUES (?, ?, ?)');
        foreach ($res['days'] as $date => $d) {
            $delB->execute([$date]);
            $delD->execute([$date]);
            $insD->execute([$date, $d['note'], $uploadId]);
            $vals = [];
            $params = [];
            foreach ($d['amounts'] as $code => $amt) {
                $vals[] = '(?, ?, ?)';
                array_push($params, $date, (int)$code, (int)$amt);
            }
            $pdo->prepare('INSERT INTO bg_budget (budget_date, div_code, amount) VALUES ' . implode(',', $vals))->execute($params);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Upload commit gagal: ' . $e->getMessage());
        json_err('Gagal menyimpan ke database. Data lama tidak berubah.', 500);
    }
    @rename($src, $upDir . '/' . $storedName);
    @unlink($metaFile);
    json_out(['ok' => true, 'upload_id' => $uploadId, 'days' => $res['days_count'], 'date_from' => $res['date_from'], 'date_to' => $res['date_to']]);
}

json_err('Aksi tidak dikenal.');
