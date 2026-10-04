<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/xlsx.php';

api_admin();
$ym = $_GET['ym'] ?? date('Y-m');
if (!valid_ym($ym)) json_err('Bulan tidak valid.');

$divs = divisions_all(true);
$n = count($divs);
$lastCol = xlsx_col_letter(3 + $n);
$names = [null, null, null, null];
foreach ($divs as $d) $names[] = ['v' => $d['name'], 's' => 'head'];
$head = [['v' => 'Date', 's' => 'head'], ['v' => 'Day', 's' => 'head'], ['v' => 'Note', 's' => 'head'], ['v' => 'total', 's' => 'head']];
foreach ($divs as $d) $head[] = ['v' => $d['code'], 's' => 'head'];
$rows = [$names, $head];
$days = (int)date('t', strtotime($ym . '-01'));
for ($i = 1; $i <= $days; $i++) {
    $date = sprintf('%s-%02d', $ym, $i);
    $rn = $i + 2;
    $rows[] = [['v' => $date, 's' => 'date'], hari_id($date), null, ['f' => "SUM(E{$rn}:{$lastCol}{$rn})", 's' => 'bnum']];
}
xlsx_send([['name' => 'DATA', 'rows' => $rows, 'freeze' => 'E3', 'widths' => array_merge([12, 10, 18, 16], array_fill(0, $n, 14))]],
    'Template_Budget_' . $ym . '.xlsx');
