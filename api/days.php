<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

api_user();
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (!valid_date($from) || !valid_date($to) || $from > $to) json_err('Rentang tanggal tidak valid.');
if ((strtotime($to) - strtotime($from)) / 86400 > 400) json_err('Rentang maksimal 400 hari.');

json_out(['ok' => true, 'from' => $from, 'to' => $to, 'days' => (object)budget_days($from, $to)]);
