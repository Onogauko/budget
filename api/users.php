<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$me = api_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db()->query('SELECT id, username, name, role, active, must_change_pw, created_at, last_login_at FROM bg_users ORDER BY active DESC, name')->fetchAll();
    foreach ($rows as &$r) {
        foreach (['id', 'active', 'must_change_pw'] as $k) $r[$k] = (int)$r[$k];
    }
    json_out(['ok' => true, 'users' => $rows]);
}

require_method('POST');
check_csrf();
$in = input_json();
$action = $in['action'] ?? '';
$pdo = db();

function temp_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
    return $s . random_int(10, 99);
}

if ($action === 'create') {
    $username = strtolower(trim((string)($in['username'] ?? '')));
    $name = trim((string)($in['name'] ?? ''));
    $role = ($in['role'] ?? 'viewer') === 'admin' ? 'admin' : 'viewer';
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) json_err('Username 3-50 karakter (huruf kecil, angka, titik, minus, garis bawah).');
    if ($name === '') json_err('Nama wajib diisi.');
    $st = $pdo->prepare('SELECT COUNT(*) FROM bg_users WHERE username = ?');
    $st->execute([$username]);
    if ((int)$st->fetchColumn()) json_err('Username sudah dipakai.');
    $pw = temp_password();
    $pdo->prepare('INSERT INTO bg_users (username, name, password_hash, role, active, must_change_pw, created_at) VALUES (?, ?, ?, ?, 1, 1, ?)')
        ->execute([$username, mb_substr($name, 0, 100), password_hash($pw, PASSWORD_DEFAULT), $role, now_str()]);
    json_out(['ok' => true, 'temp_password' => $pw]);
}

$id = (int)($in['id'] ?? 0);
$target = find_user($id);
if (!$target) json_err('Pengguna tidak ditemukan.', 404);

if ($action === 'reset') {
    $pw = temp_password();
    $pdo->prepare('UPDATE bg_users SET password_hash = ?, must_change_pw = 1 WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
    $pdo->prepare('DELETE FROM bg_auth_tokens WHERE user_id = ?')->execute([$id]);
    json_out(['ok' => true, 'temp_password' => $pw]);
}

if ($action === 'update') {
    if ($id === (int)$me['id'] && (($in['role'] ?? $target['role']) !== 'admin' || isset($in['active']) && !(int)$in['active'])) {
        json_err('Anda tidak bisa menurunkan peran atau menonaktifkan akun sendiri.');
    }
    $name = trim((string)($in['name'] ?? $target['name']));
    $role = ($in['role'] ?? $target['role']) === 'admin' ? 'admin' : 'viewer';
    $active = isset($in['active']) ? ((int)$in['active'] ? 1 : 0) : (int)$target['active'];
    if ($name === '') json_err('Nama wajib diisi.');
    $pdo->prepare('UPDATE bg_users SET name = ?, role = ?, active = ? WHERE id = ?')->execute([mb_substr($name, 0, 100), $role, $active, $id]);
    if (!$active) $pdo->prepare('DELETE FROM bg_auth_tokens WHERE user_id = ?')->execute([$id]);
    json_out(['ok' => true]);
}

json_err('Aksi tidak dikenal.');
