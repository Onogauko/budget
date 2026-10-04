<?php
declare(strict_types=1);

require_once __DIR__ . '/xlsx.php';

/** Ubah nilai sel menjadi tanggal Y-m-d, atau null jika tidak valid. */
function parse_budget_date($v, bool $date1904 = false): ?string
{
    if ($v === null || $v === '') return null;
    if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^\d{5}(\.\d+)?$/', $v))) {
        $serial = (float)$v;
        if ($serial < 20000 || $serial > 80000) return null;
        $days = (int)floor($serial) - ($date1904 ? -1462 : 0) - 25569;
        return gmdate('Y-m-d', $days * 86400);
    }
    $s = trim((string)$v);
    $s = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?$/', '', $s);
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'j M Y', 'j F Y', 'd M Y', 'd F Y', 'Y/m/d'];
    foreach ($formats as $f) {
        $dt = DateTime::createFromFormat('!' . $f, $s);
        $err = DateTime::getLastErrors();
        if ($dt && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
            $y = (int)$dt->format('Y');
            if ($y >= 2000 && $y <= 2100) return $dt->format('Y-m-d');
        }
    }
    return null;
}

/** Ubah nilai sel menjadi angka bulat Rupiah; null jika kosong; false jika bukan angka. */
function parse_amount($v)
{
    if ($v === null || $v === '') return null;
    if (is_int($v) || is_float($v)) return (int)round($v);
    $s = trim((string)$v);
    $s = preg_replace('/^Rp\.?\s*/i', '', $s);
    $s = str_replace([' ', "\u{00A0}"], '', $s);
    if ($s === '' || $s === '-') return null;
    $neg = false;
    if (preg_match('/^\((.*)\)$/', $s, $m)) { $neg = true; $s = $m[1]; }
    if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {          // 1.234.567,89
        $s = str_replace(['.', ','], ['', '.'], $s);
    } elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {    // 1,234,567.89
        $s = str_replace(',', '', $s);
    } else {
        $s = str_replace(',', '.', $s);
    }
    if (!is_numeric($s)) return false;
    $n = (int)round((float)$s);
    return $neg ? -$n : $n;
}

function norm_label($v): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string)$v)));
}

/**
 * Analisa file budget. Mengembalikan pratinjau + data siap simpan.
 * Format: satu baris per tanggal, kolom per divisi (kode divisi di baris header, nama di baris atasnya),
 * seperti sheet DATA pada MASTER_SHARE_BUDGET.xlsx. Kolom Day/Note/Total dikenali otomatis.
 */
function analyze_budget_file(string $path, string $ext): array
{
    $book = $ext === 'csv' ? csv_read($path) : xlsx_read($path);
    $date1904 = $book['date1904'];

    $known = [];
    $byName = [];
    foreach (divisions_all() as $d) {
        $known[$d['code']] = $d['name'];
        $byName[norm_label($d['name'])] = $d['code'];
    }

    // Pilih sheet: utamakan bernama DATA, lalu sheet pertama yang punya header "Date/Tanggal".
    $candidates = $book['sheets'];
    uksort($candidates, fn($a, $b) => (strcasecmp($b, 'DATA') === 0) <=> (strcasecmp($a, 'DATA') === 0));
    $sheetName = null; $rows = null; $headerRow = null; $dateCol = null;
    foreach ($candidates as $name => $srows) {
        foreach ($srows as $rn => $cells) {
            if ($rn > 30) break;
            foreach ($cells as $ci => $v) {
                if (in_array(norm_label($v), ['date', 'tanggal', 'tgl'], true)) {
                    $sheetName = $name; $rows = $srows; $headerRow = $rn; $dateCol = $ci;
                    break 3;
                }
            }
        }
    }
    if ($rows === null) {
        throw new RuntimeException('Header tidak ditemukan. Pastikan ada kolom "Date" atau "Tanggal" (lihat template).');
    }

    $header = $rows[$headerRow];
    $nameRow = $rows[$headerRow - 1] ?? [];
    $errors = [];
    $warnings = [];
    $cols = [];          // ci => code
    $newDivs = [];       // code => name
    $noteCol = null; $totalCol = null;
    $maxCol = max(array_merge(array_keys($header), array_keys($nameRow), [0]));

    for ($ci = 0; $ci <= $maxCol; $ci++) {
        if ($ci === $dateCol) continue;
        $h = $header[$ci] ?? null;
        $label = norm_label($h);
        if (in_array($label, ['day', 'hari'], true)) continue;
        if (in_array($label, ['note', 'notes', 'catatan', 'keterangan', 'ket'], true)) { $noteCol = $ci; continue; }
        if (in_array($label, ['total', 'store budget', 'total budget', 'jumlah'], true)) { $totalCol = $ci; continue; }
        if ($h === null && !isset($nameRow[$ci])) continue;

        $code = null; $name = trim((string)($nameRow[$ci] ?? ''));
        if (is_int($h) || is_float($h) || (is_string($h) && preg_match('/^\d+$/', trim($h)))) {
            $code = (int)$h;
        } elseif (is_string($h) && preg_match('/^(\d{1,4})\s*[-–.:]?\s+(.+)$/u', trim($h), $m)) {
            $code = (int)$m[1];
            $name = $name ?: trim($m[2]);
        } elseif ($h !== null && isset($byName[$label])) {
            $code = $byName[$label];
        } elseif ($h === null && $name !== '' && isset($byName[norm_label($name)])) {
            $code = $byName[norm_label($name)];
        }

        if ($code === null) {
            $warnings[] = 'Kolom ' . xlsx_col_letter($ci) . ' ("' . ($h ?? $name) . '") tidak dikenali sebagai divisi, dilewati.';
            continue;
        }
        if (in_array($code, $cols, true)) {
            $errors[] = 'Kode divisi ' . $code . ' muncul lebih dari sekali di header.';
            continue;
        }
        $cols[$ci] = $code;
        if (!isset($known[$code])) {
            $newDivs[$code] = $name !== '' ? mb_substr($name, 0, 100) : 'Divisi ' . $code;
        } elseif ($name !== '' && norm_label($name) !== norm_label($known[$code])) {
            $warnings[] = 'Divisi ' . $code . ' bernama "' . $name . '" di file, tetapi "' . $known[$code] . '" di aplikasi (nama di aplikasi dipakai).';
        }
    }
    if (!$cols) throw new RuntimeException('Tidak ada kolom divisi yang dikenali. Header harus berisi kode divisi (16, 21, ...) seperti template.');
    foreach ($newDivs as $code => $name) $warnings[] = 'Divisi baru akan ditambahkan: ' . $code . ' - ' . $name . '.';

    $days = [];
    $mismatch = 0;
    foreach ($rows as $rn => $cells) {
        if ($rn <= $headerRow) continue;
        $rawDate = $cells[$dateCol] ?? null;
        $hasValues = false;
        foreach ($cols as $ci => $code) if (isset($cells[$ci])) { $hasValues = true; break; }
        if ($rawDate === null && !$hasValues) continue;
        if (in_array(norm_label($rawDate), ['total', 'grand total', 'jumlah', 'subtotal', 'sub total'], true)) continue;

        $date = parse_budget_date($rawDate, $date1904);
        if ($date === null) {
            if ($hasValues) $errors[] = 'Baris ' . $rn . ': tanggal "' . $rawDate . '" tidak valid.';
            continue;
        }
        if (isset($days[$date])) {
            $errors[] = 'Tanggal ' . $date . ' muncul lebih dari sekali (baris ' . $days[$date]['row'] . ' dan ' . $rn . ').';
            continue;
        }
        $amounts = [];
        foreach ($cols as $ci => $code) {
            $a = parse_amount($cells[$ci] ?? null);
            if ($a === false) {
                $errors[] = 'Baris ' . $rn . ' kolom ' . xlsx_col_letter($ci) . ': nilai "' . $cells[$ci] . '" bukan angka.';
                continue;
            }
            if ($a === null) continue;
            if ($a < 0) $warnings[] = 'Baris ' . $rn . ' divisi ' . $code . ': nilai negatif.';
            $amounts[$code] = $a;
        }
        if (!$amounts) continue; // tanggal tanpa angka budget dilewati
        $total = array_sum($amounts);
        if ($totalCol !== null && isset($cells[$totalCol])) {
            $ft = parse_amount($cells[$totalCol]);
            if (is_int($ft) && abs($ft - $total) > 1000) {
                $mismatch++;
                if ($mismatch <= 5) $warnings[] = 'Tanggal ' . $date . ': kolom total (' . number_format($ft, 0, ',', '.') . ') berbeda dengan jumlah divisi (' . number_format($total, 0, ',', '.') . ').';
            }
        }
        $note = $noteCol !== null && isset($cells[$noteCol]) ? mb_substr(trim((string)$cells[$noteCol]), 0, 255) : null;
        $days[$date] = ['row' => $rn, 'note' => $note, 'amounts' => $amounts, 'total' => $total];
    }
    if ($mismatch > 5) $warnings[] = '... dan ' . ($mismatch - 5) . ' tanggal lain dengan selisih total.';
    if (!$days) $errors[] = 'Tidak ada baris tanggal berisi angka budget.';
    ksort($days);

    $divTotals = [];
    foreach ($days as $d) foreach ($d['amounts'] as $code => $a) $divTotals[$code] = ($divTotals[$code] ?? 0) + $a;

    $existing = 0;
    if ($days) {
        $dates = array_keys($days);
        $st = db()->prepare('SELECT budget_date FROM bg_budget WHERE budget_date BETWEEN ? AND ? GROUP BY budget_date');
        $st->execute([$dates[0], end($dates)]);
        $set = array_flip($dates);
        foreach ($st as $r) if (isset($set[substr((string)$r['budget_date'], 0, 10)])) $existing++;
    }

    $divList = [];
    foreach ($cols as $code) {
        $divList[] = ['code' => $code, 'name' => $known[$code] ?? $newDivs[$code], 'total' => $divTotals[$code] ?? 0, 'new' => isset($newDivs[$code])];
    }

    return [
        'sheet' => $sheetName,
        'date_from' => $days ? array_key_first($days) : null,
        'date_to' => $days ? array_key_last($days) : null,
        'days_count' => count($days),
        'total' => array_sum(array_column($days, 'total')),
        'replaced_days' => $existing,
        'divisions' => $divList,
        'new_divisions' => $newDivs,
        'day_totals' => array_map(fn($d) => ['t' => $d['total'], 'n' => $d['note']], $days),
        'days' => $days,
        'errors' => array_slice($errors, 0, 30),
        'warnings' => array_slice($warnings, 0, 30),
    ];
}
