<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

api_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_out(['ok' => true, 'divisions' => divisions_all()]);
}

require_method('POST');
check_csrf();
$in = input_json();
$action = $in['action'] ?? '';
$pdo = db();

if ($action === 'save') {
    $list = $in['divisions'] ?? [];
    if (!is_array($list)) json_err('Data tidak valid.');
    $up = $pdo->prepare('UPDATE bg_divisions SET name = ?, sort_order = ?, active = ? WHERE code = ?');
    $pdo->beginTransaction();
    foreach ($list as $i => $d) {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') { $pdo->rollBack(); json_err('Nama divisi tidak boleh kosong.'); }
        $up->execute([mb_substr($name, 0, 100), $i + 1, !empty($d['active']) ? 1 : 0, (int)$d['code']]);
    }
    $pdo->commit();
    json_out(['ok' => true]);
}

if ($action === 'create') {
    $code = (int)($in['code'] ?? 0);
    $name = trim((string)($in['name'] ?? ''));
    if ($code <= 0 || $code > 9999) json_err('Kode divisi harus angka 1-9999.');
    if ($name === '') json_err('Nama divisi wajib diisi.');
    $st = $pdo->prepare('SELECT COUNT(*) FROM bg_divisions WHERE code = ?');
    $st->execute([$code]);
    if ((int)$st->fetchColumn()) json_err('Kode divisi sudah ada.');
    $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM bg_divisions')->fetchColumn();
    $pdo->prepare('INSERT INTO bg_divisions (code, name, sort_order, active) VALUES (?, ?, ?, 1)')->execute([$code, mb_substr($name, 0, 100), $max + 1]);
    json_out(['ok' => true]);
}

if ($action === 'delete') {
    $code = (int)($in['code'] ?? 0);
    $st = $pdo->prepare('SELECT COUNT(*) FROM bg_budget WHERE div_code = ?');
    $st->execute([$code]);
    if ((int)$st->fetchColumn()) json_err('Divisi ini sudah punya data budget. Nonaktifkan saja.');
    $pdo->prepare('DELETE FROM bg_divisions WHERE code = ?')->execute([$code]);
    json_out(['ok' => true]);
}

json_err('Aksi tidak dikenal.');
