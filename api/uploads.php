<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

api_admin();

if (isset($_GET['download'])) {
    $st = db()->prepare('SELECT original_name, stored_name FROM bg_uploads WHERE id = ?');
    $st->execute([(int)$_GET['download']]);
    $row = $st->fetch();
    $path = $row ? APP_ROOT . '/storage/uploads/' . basename($row['stored_name']) : '';
    if (!$row || !is_file($path)) json_err('File arsip tidak ditemukan.', 404);
    $name = preg_replace('/[^\w.\- ]+/u', '_', $row['original_name']);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$rows = db()->query('SELECT u.id, u.original_name, u.stored_name, u.date_from, u.date_to, u.days, u.total, u.replaced_days, u.created_at, us.name AS user_name
    FROM bg_uploads u LEFT JOIN bg_users us ON us.id = u.user_id ORDER BY u.id DESC LIMIT 100')->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['days'] = (int)$r['days'];
    $r['total'] = (int)$r['total'];
    $r['replaced_days'] = (int)$r['replaced_days'];
    $r['date_from'] = substr((string)$r['date_from'], 0, 10);
    $r['date_to'] = substr((string)$r['date_to'], 0, 10);
    $r['has_file'] = is_file(APP_ROOT . '/storage/uploads/' . basename($r['stored_name']));
    unset($r['stored_name']);
}
json_out(['ok' => true, 'uploads' => $rows]);
