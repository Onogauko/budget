<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/xlsx.php';

api_user();
$ym = $_GET['ym'] ?? '';
$format = $_GET['format'] ?? 'xlsx';
if (!valid_ym($ym)) json_err('Bulan tidak valid.');
if (!in_array($format, ['xlsx', 'csv'], true)) json_err('Format tidak dikenal.');

$from = $ym . '-01';
$to = date('Y-m-t', strtotime($from));
$days = budget_days($from, $to);
$divs = divisions_all(true);
// sertakan divisi nonaktif yang tetap punya angka di bulan ini
$codes = array_column($divs, 'code');
foreach (divisions_all() as $d) {
    if (in_array($d['code'], $codes, true)) continue;
    foreach ($days as $x) if (isset($x['d'][$d['code']])) { $divs[] = $d; $codes[] = $d['code']; break; }
}
$title = setting('store_title', 'Daily Budget');
$fname = 'Budget_' . preg_replace('/[^A-Za-z0-9]+/', '_', $title) . '_' . $ym;

$divTotal = [];
$grand = 0;
foreach ($days as $d) {
    foreach ($d['d'] as $c => $a) $divTotal[$c] = ($divTotal[$c] ?? 0) + $a;
    $grand += $d['t'];
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $head = ['Tanggal', 'Hari', 'Catatan', 'Total'];
    foreach ($divs as $d) $head[] = $d['code'] . ' ' . $d['name'];
    fputcsv($out, $head, ';', '"', '');
    foreach ($days as $date => $d) {
        $row = [$date, hari_id($date), $d['n'] ?? '', $d['t']];
        foreach ($divs as $dv) $row[] = $d['d'][$dv['code']] ?? '';
        fputcsv($out, $row, ';', '"', '');
    }
    $row = ['TOTAL', '', '', $grand];
    foreach ($divs as $dv) $row[] = $divTotal[$dv['code']] ?? 0;
    fputcsv($out, $row, ';', '"', '');
    fclose($out);
    exit;
}

// XLSX: sheet DATA dengan susunan sama seperti master (bisa di-upload ulang) + sheet ringkasan per divisi
$nDiv = count($divs);
$lastCol = xlsx_col_letter(3 + $nDiv);
$rows = [];
$rows[] = [['v' => 'Daily Budget ' . $title, 's' => 'title']];
$rows[] = ['Periode: ' . bulan_label($ym) . '  •  Diunduh: ' . date('d/m/Y H:i')];
$rows[] = [];
$names = ['', '', '', ''];
foreach ($divs as $d) $names[] = ['v' => $d['name'], 's' => 'head'];
$rows[] = $names;
$head = [['v' => 'Date', 's' => 'head'], ['v' => 'Day', 's' => 'head'], ['v' => 'Note', 's' => 'head'], ['v' => 'total', 's' => 'head']];
foreach ($divs as $d) $head[] = ['v' => $d['code'], 's' => 'head'];
$rows[] = $head;
foreach ($days as $date => $d) {
    $r = [['v' => $date, 's' => 'date'], hari_id($date), $d['n'] ?? '', ['v' => $d['t'], 's' => 'bnum']];
    foreach ($divs as $dv) $r[] = isset($d['d'][$dv['code']]) ? ['v' => $d['d'][$dv['code']], 's' => 'num'] : null;
    $rows[] = $r;
}
$tot = [['v' => 'TOTAL', 's' => 'b'], '', '', ['v' => $grand, 's' => 'bnum']];
foreach ($divs as $dv) $tot[] = ['v' => $divTotal[$dv['code']] ?? 0, 's' => 'bnum'];
$rows[] = $tot;

$sum = [];
$sum[] = [['v' => 'Ringkasan per Divisi - ' . bulan_label($ym), 's' => 'title']];
$sum[] = [$title];
$sum[] = [];
$sum[] = [['v' => 'Div', 's' => 'head'], ['v' => 'Nama', 's' => 'head'], ['v' => 'Budget', 's' => 'head'], ['v' => '%', 's' => 'head']];
foreach ($divs as $dv) {
    $t = $divTotal[$dv['code']] ?? 0;
    $sum[] = [$dv['code'], $dv['name'], ['v' => $t, 's' => 'num'], ['v' => $grand ? $t / $grand : 0, 's' => 'pct']];
}
$sum[] = [['v' => 'TOTAL', 's' => 'b'], '', ['v' => $grand, 's' => 'bnum'], ['v' => $grand ? 1 : 0, 's' => 'pct']];

xlsx_send([
    ['name' => 'DATA', 'rows' => $rows, 'freeze' => 'E6', 'widths' => array_merge([12, 10, 18, 16], array_fill(0, $nDiv, 14))],
    ['name' => 'Per Divisi', 'rows' => $sum, 'widths' => [8, 20, 18, 9]],
], $fname . '.xlsx');
