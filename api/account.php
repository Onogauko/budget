<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

require_method('POST');
$u = api_user(true);
check_csrf();
$in = input_json();
$old = (string)($in['old_password'] ?? '');
$new = (string)($in['new_password'] ?? '');

$st = db()->prepare('SELECT password_hash FROM bg_users WHERE id = ?');
$st->execute([$u['id']]);
if (!password_verify($old, (string)$st->fetchColumn())) json_err('Password lama salah.');
if ($p = password_problem($new)) json_err($p);
if ($old === $new) json_err('Password baru harus berbeda dari password lama.');

db()->prepare('UPDATE bg_users SET password_hash = ?, must_change_pw = 0 WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
json_out(['ok' => true]);
