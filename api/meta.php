<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$u = api_user();
$range = db()->query('SELECT MIN(budget_date) AS mn, MAX(budget_date) AS mx FROM bg_budget')->fetch();
json_out([
    'ok' => true,
    'user' => ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['name'], 'role' => $u['role']],
    'settings' => ['store_title' => setting('store_title', 'Daily Budget')],
    'divisions' => divisions_all(true),
    'today' => date('Y-m-d'),
    'range' => ['min' => $range['mn'] ? substr((string)$range['mn'], 0, 10) : null, 'max' => $range['mx'] ? substr((string)$range['mx'], 0, 10) : null],
    'version' => APP_VERSION,
]);
