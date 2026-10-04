<?php
declare(strict_types=1);

function now_str(): string
{
    return date('Y-m-d H:i:s');
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $message, int $code = 400, array $extra = []): void
{
    json_out(['ok' => false, 'error' => $message] + $extra, $code);
}

function input_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) json_err('Metode tidak diizinkan', 405);
}

function valid_date(?string $s): bool
{
    if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return checkdate($m, $d, $y);
}

function valid_ym(?string $s): bool
{
    return (bool)($s && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $s));
}

function setting(string $key, ?string $default = null): ?string
{
    $st = db()->prepare('SELECT v FROM bg_settings WHERE k = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

function set_setting(string $key, string $value): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM bg_settings WHERE k = ?')->execute([$key]);
    $pdo->prepare('INSERT INTO bg_settings (k, v) VALUES (?, ?)')->execute([$key, $value]);
}

function divisions_all(bool $activeOnly = false): array
{
    $sql = 'SELECT code, name, sort_order, active FROM bg_divisions'
        . ($activeOnly ? ' WHERE active = 1' : '')
        . ' ORDER BY sort_order, code';
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['code'] = (int)$r['code'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['active'] = (int)$r['active'];
    }
    return $rows;
}

const HARI_ID = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN_ID = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function hari_id(string $ymd): string
{
    return HARI_ID[(int)date('w', strtotime($ymd))];
}

function bulan_label(string $ym): string
{
    [$y, $m] = explode('-', $ym);
    return BULAN_ID[(int)$m] . ' ' . $y;
}

/** Ambil data budget per tanggal: [date => ['t'=>total,'n'=>note,'d'=>[code=>amount]]] */
function budget_days(string $from, string $to): array
{
    $st = db()->prepare('SELECT budget_date, div_code, amount FROM bg_budget WHERE budget_date BETWEEN ? AND ? ORDER BY budget_date');
    $st->execute([$from, $to]);
    $days = [];
    foreach ($st as $r) {
        $d = substr((string)$r['budget_date'], 0, 10);
        if (!isset($days[$d])) $days[$d] = ['t' => 0, 'n' => null, 'd' => []];
        $amt = (int)$r['amount'];
        $days[$d]['d'][(int)$r['div_code']] = $amt;
        $days[$d]['t'] += $amt;
    }
    $st = db()->prepare('SELECT budget_date, note FROM bg_days WHERE budget_date BETWEEN ? AND ? AND note IS NOT NULL AND note <> \'\'');
    $st->execute([$from, $to]);
    foreach ($st as $r) {
        $d = substr((string)$r['budget_date'], 0, 10);
        if (!isset($days[$d])) $days[$d] = ['t' => 0, 'n' => null, 'd' => []];
        $days[$d]['n'] = $r['note'];
    }
    ksort($days);
    return $days;
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
