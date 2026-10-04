<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

$cfgFile = APP_ROOT . '/config/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    exit('Konfigurasi belum ada: salin config/config.example.php menjadi config/config.php lalu isi data database.');
}
$GLOBALS['CONFIG'] = require $cfgFile;

date_default_timezone_set(cfg('timezone', 'Asia/Jakarta'));
error_reporting(E_ALL);
ini_set('display_errors', cfg('debug', false) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

function cfg(string $key, $default = null)
{
    return $GLOBALS['CONFIG'][$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $pdo = new PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') return true;
    return strpos($_SERVER['HTTP_CF_VISITOR'] ?? '', 'https') !== false;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('BGSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

start_session();
