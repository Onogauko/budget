<?php
declare(strict_types=1);

/*
 * Pembaca & penulis XLSX ringan (hanya butuh ekstensi zip + xmlreader bawaan PHP),
 * sehingga tidak perlu folder vendor/composer di shared hosting.
 */

function xlsx_col_index(string $letters): int
{
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n - 1;
}

function xlsx_col_letter(int $idx): string
{
    $s = '';
    $idx++;
    while ($idx > 0) {
        $m = ($idx - 1) % 26;
        $s = chr(65 + $m) . $s;
        $idx = intdiv($idx - $m - 1, 26);
    }
    return $s;
}

/** Atribut tanpa memedulikan prefiks namespace (mendukung OOXML transitional & strict). */
function xlsx_attr(DOMElement $el, string $local): string
{
    foreach ($el->attributes as $a) {
        if ($a->localName === $local) return $a->value;
    }
    return '';
}

/**
 * Baca semua sheet. Hasil: ['date1904'=>bool, 'sheets'=>[nama => [row(1-based) => [col(0-based) => value]]]]
 */
function xlsx_read(string $path, int $maxRows = 20000): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('File Excel tidak bisa dibuka (rusak atau bukan .xlsx).');

    $wbXml = $zip->getFromName('xl/workbook.xml');
    $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXml === false || $relXml === false) throw new RuntimeException('Struktur file .xlsx tidak dikenali.');

    $rels = [];
    $dom = new DOMDocument();
    $dom->loadXML($relXml, LIBXML_NONET);
    foreach ($dom->getElementsByTagNameNS('*', 'Relationship') as $rel) {
        $target = $rel->getAttribute('Target');
        $target = $target[0] === '/' ? ltrim($target, '/') : 'xl/' . $target;
        $rels[$rel->getAttribute('Id')] = $target;
    }

    $dom = new DOMDocument();
    $dom->loadXML($wbXml, LIBXML_NONET);
    $date1904 = false;
    foreach ($dom->getElementsByTagNameNS('*', 'workbookPr') as $wp) {
        $v = $wp->getAttribute('date1904');
        $date1904 = ($v === '1' || $v === 'true');
    }
    $sheetFiles = [];
    foreach ($dom->getElementsByTagNameNS('*', 'sheet') as $sh) {
        $rid = xlsx_attr($sh, 'id');
        if (isset($rels[$rid])) $sheetFiles[$sh->getAttribute('name')] = $rels[$rid];
    }

    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $r = new XMLReader();
        $r->XML($ssXml, null, LIBXML_NONET);
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
                $node = $r->expand();
                $text = '';
                foreach ($node->getElementsByTagNameNS('*', 't') as $t) {
                    if ($t->parentNode && $t->parentNode->localName === 'rPh') continue;
                    $text .= $t->textContent;
                }
                $shared[] = $text;
            }
        }
        $r->close();
    }

    $sheets = [];
    foreach ($sheetFiles as $name => $file) {
        $xml = $zip->getFromName($file);
        if ($xml === false) continue;
        $rows = [];
        $r = new XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_PARSEHUGE);
        $rowNum = 0;
        $colAuto = 0;
        while ($r->read()) {
            if ($r->nodeType !== XMLReader::ELEMENT) continue;
            if ($r->localName === 'row') {
                $attr = $r->getAttribute('r');
                $rowNum = $attr ? (int)$attr : $rowNum + 1;
                if ($rowNum > $maxRows) break;
                $colAuto = 0;
                continue;
            }
            if ($r->localName !== 'c') continue;
            $c = $r->expand();
            $ref = $c->getAttribute('r');
            if ($ref && preg_match('/^([A-Z]+)(\d+)$/i', $ref, $m)) {
                $col = xlsx_col_index($m[1]);
                $rowNum = (int)$m[2];
            } else {
                $col = $colAuto;
            }
            $colAuto = $col + 1;
            $type = $c->getAttribute('t');
            $val = null;
            if ($type === 'inlineStr') {
                $val = '';
                foreach ($c->getElementsByTagNameNS('*', 't') as $t) $val .= $t->textContent;
            } else {
                foreach ($c->childNodes as $ch) {
                    if ($ch->localName === 'v') { $val = $ch->textContent; break; }
                }
                if ($val !== null) {
                    if ($type === 's') $val = $shared[(int)$val] ?? '';
                    elseif ($type === 'b') $val = $val === '1';
                    elseif ($type === '' || $type === 'n') $val = is_numeric($val) ? $val + 0 : $val;
                }
            }
            if ($val !== null && $val !== '') $rows[$rowNum][$col] = $val;
        }
        $r->close();
        ksort($rows);
        $sheets[$name] = $rows;
    }
    $zip->close();
    return ['date1904' => $date1904, 'sheets' => $sheets];
}

/** Baca CSV (deteksi pemisah ; , tab) ke format baris yang sama dengan xlsx_read. */
function csv_read(string $path, int $maxRows = 20000): array
{
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('File CSV tidak bisa dibaca.');
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $first = strtok($raw, "\n") ?: '';
    $delims = [';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t")];
    arsort($delims);
    $delim = array_key_first($delims);

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $rows = [];
    $n = 0;
    while (($line = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        $n++;
        if ($n > $maxRows) break;
        foreach ($line as $i => $v) {
            $v = trim((string)$v);
            if ($v !== '') $rows[$n][$i] = $v;
        }
    }
    fclose($fh);
    return ['date1904' => false, 'sheets' => ['CSV' => $rows]];
}

/* ------------------------------------------------------------------ */
/* Penulis XLSX                                                        */
/* ------------------------------------------------------------------ */

/** Gaya sel: 0 normal, 1 tebal, 2 angka, 3 angka tebal, 4 tanggal, 5 persen, 6 judul, 7 header (tebal + latar), 8 header angka */
function xlsx_style_id(string $s): int
{
    return ['' => 0, 'b' => 1, 'num' => 2, 'bnum' => 3, 'date' => 4, 'pct' => 5, 'title' => 6, 'head' => 7, 'headnum' => 8][$s] ?? 0;
}

/**
 * $sheets = [['name'=>'DATA','rows'=>[[cell,...],...],'widths'=>[w,...],'freeze'=>'E3','merges'=>['A1:D1']], ...]
 * cell: null | string | int | float | ['v'=>..., 's'=>'num|date|b|...', 'f'=>'SUM(A1:A2)']
 * Untuk 's'=>'date', 'v' berupa 'Y-m-d'.
 */
function xlsx_write(string $path, array $sheets): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Gagal membuat file Excel.');
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');

    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wbSheets = '';
    $wbRels = '';
    foreach ($sheets as $i => $sh) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wbSheets .= '<sheet name="' . $esc(mb_substr($sh['name'], 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
    }
    $ct .= '</Types>';
    $styleRel = count($sheets) + 1;
    $wbRels .= '<Relationship Id="rId' . $styleRel . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $wbSheets . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
        . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEDE9FE"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="9">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="3" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="10" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="3" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');

    foreach ($sheets as $i => $sh) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        if (!empty($sh['freeze']) && preg_match('/^([A-Z]+)(\d+)$/', $sh['freeze'], $m)) {
            $xs = xlsx_col_index($m[1]);
            $ys = (int)$m[2] - 1;
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane'
                . ($xs ? ' xSplit="' . $xs . '"' : '') . ($ys ? ' ySplit="' . $ys . '"' : '')
                . ' topLeftCell="' . $sh['freeze'] . '" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>';
        }
        if (!empty($sh['widths'])) {
            $xml .= '<cols>';
            foreach ($sh['widths'] as $ci => $w) $xml .= '<col min="' . ($ci + 1) . '" max="' . ($ci + 1) . '" width="' . $w . '" customWidth="1"/>';
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ($sh['rows'] as $ri => $row) {
            $rn = $ri + 1;
            $xml .= '<row r="' . $rn . '">';
            foreach ($row as $ci => $cell) {
                if ($cell === null || $cell === '') continue;
                $ref = xlsx_col_letter($ci) . $rn;
                $style = 0;
                $f = null;
                $v = $cell;
                if (is_array($cell)) {
                    $style = xlsx_style_id($cell['s'] ?? '');
                    $f = $cell['f'] ?? null;
                    $v = $cell['v'] ?? null;
                    if (($cell['s'] ?? '') === 'date' && is_string($v)) {
                        $v = (int)round((strtotime($v . ' UTC') / 86400) + 25569);
                    }
                }
                $sAttr = $style ? ' s="' . $style . '"' : '';
                if ($f !== null) {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . '><f>' . $esc($f) . '</f></c>';
                } elseif (is_int($v) || is_float($v)) {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . '><v>' . $v . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '"' . $sAttr . ' t="inlineStr"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if (!empty($sh['merges'])) {
            $xml .= '<mergeCells count="' . count($sh['merges']) . '">';
            foreach ($sh['merges'] as $mg) $xml .= '<mergeCell ref="' . $mg . '"/>';
            $xml .= '</mergeCells>';
        }
        $xml .= '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
    }
    $zip->close();
}

function xlsx_send(array $sheets, string $filename): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'bgx');
    xlsx_write($tmp, $sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
