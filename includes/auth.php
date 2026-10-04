<?php
declare(strict_types=1);

const REMEMBER_COOKIE = 'bg_remember';
const REMEMBER_DAYS = 30;

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        json_err('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.', 419);
    }
}

function find_user(int $id): ?array
{
    $st = db()->prepare('SELECT id, username, name, role, active, must_change_pw FROM bg_users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    return $u ?: null;
}

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) return $cached;

    $user = null;
    if (!empty($_SESSION['uid'])) {
        $user = find_user((int)$_SESSION['uid']);
        if ($user && !(int)$user['active']) $user = null;
        if (!$user) unset($_SESSION['uid']);
    }
    if (!$user && !empty($_COOKIE[REMEMBER_COOKIE])) {
        $user = login_from_cookie((string)$_COOKIE[REMEMBER_COOKIE]);
    }
    $cached = $user;
    return $user;
}

function login_from_cookie(string $cookie): ?array
{
    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) return null;
    [$selector, $validator] = $parts;
    $st = db()->prepare('SELECT * FROM bg_auth_tokens WHERE selector = ? AND expires_at > ?');
    $st->execute([$selector, now_str()]);
    $tok = $st->fetch();
    if (!$tok || !hash_equals($tok['token_hash'], hash('sha256', $validator))) {
        clear_remember_cookie();
        return null;
    }
    $user = find_user((int)$tok['user_id']);
    if (!$user || !(int)$user['active']) return null;

    db()->prepare('DELETE FROM bg_auth_tokens WHERE id = ?')->execute([$tok['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    issue_remember_token((int)$user['id']);
    return $user;
}

function issue_remember_token(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_DAYS * 86400;
    db()->prepare('INSERT INTO bg_auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]);
    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires' => $expires, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

function login_user(array $user, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['id'];
    db()->prepare('UPDATE bg_users SET last_login_at = ? WHERE id = ?')->execute([now_str(), $user['id']]);
    db()->prepare('DELETE FROM bg_auth_tokens WHERE expires_at < ?')->execute([now_str()]);
    if ($remember) issue_remember_token((int)$user['id']);
}

function logout_user(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $selector = explode(':', (string)$_COOKIE[REMEMBER_COOKIE])[0];
        db()->prepare('DELETE FROM bg_auth_tokens WHERE selector = ?')->execute([$selector]);
        clear_remember_cookie();
    }
    $_SESSION = [];
    session_destroy();
}

function login_blocked(string $username): bool
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $st = db()->prepare('SELECT COUNT(*) FROM bg_login_attempts WHERE ip = ? AND attempted_at > ?');
    $st->execute([client_ip(), $since]);
    if ((int)$st->fetchColumn() >= 15) return true;
    $st = db()->prepare('SELECT COUNT(*) FROM bg_login_attempts WHERE ip = ? AND username = ? AND attempted_at > ?');
    $st->execute([client_ip(), $username, $since]);
    return (int)$st->fetchColumn() >= 5;
}

function record_failed_login(string $username): void
{
    db()->prepare('INSERT INTO bg_login_attempts (ip, username, attempted_at) VALUES (?, ?, ?)')
        ->execute([client_ip(), substr($username, 0, 50), now_str()]);
    db()->prepare('DELETE FROM bg_login_attempts WHERE attempted_at < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

/** Untuk API: wajib login. $allowMustChange untuk endpoint ganti password. */
function api_user(bool $allowMustChange = false): array
{
    $u = current_user();
    if (!$u) json_err('Silakan login terlebih dahulu.', 401);
    if (!$allowMustChange && (int)$u['must_change_pw']) json_err('Anda wajib mengganti password terlebih dahulu.', 403, ['code' => 'must_change']);
    return $u;
}

function api_admin(): array
{
    $u = api_user();
    if ($u['role'] !== 'admin') json_err('Hanya admin yang boleh melakukan ini.', 403);
    return $u;
}

function password_problem(string $pw): ?string
{
    if (strlen($pw) < 8) return 'Password minimal 8 karakter.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Password harus berisi huruf dan angka.';
    return null;
}
