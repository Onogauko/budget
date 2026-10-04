<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

require_method('POST');
api_admin();
check_csrf();
$in = input_json();
$title = trim((string)($in['store_title'] ?? ''));
if ($title === '' || mb_strlen($title) > 100) json_err('Nama toko wajib diisi (maks 100 karakter).');
set_setting('store_title', $title);
json_out(['ok' => true]);
