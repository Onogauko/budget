<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/page.php';

if (current_user()) redirect('./');

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
        $error = 'Sesi kedaluwarsa, silakan coba lagi.';
    } elseif (login_blocked($username)) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.';
    } else {
        $st = db()->prepare('SELECT * FROM bg_users WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();
        if ($u && (int)$u['active'] && password_verify($password, $u['password_hash'])) {
            login_user($u, !empty($_POST['remember']));
            redirect((int)$u['must_change_pw'] ? 'change_password.php' : './');
        }
        record_failed_login($username);
        $error = $u && !(int)$u['active'] ? 'Akun dinonaktifkan. Hubungi admin.' : 'Username atau password salah.';
    }
}
$title = setting('store_title', 'Daily Budget');
page_head('Masuk • Budget Harian');
?>
<body class="auth">
<main class="auth-card">
  <div class="auth-brand">
    <img src="icons/icon.svg" alt="" width="56" height="56">
    <div>
      <h1>Budget Harian</h1>
      <p><?= h($title) ?></p>
    </div>
  </div>
  <?php if ($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label class="field"><span>Username</span>
      <input name="username" value="<?= h($username) ?>" autocomplete="username" autocapitalize="none" required autofocus></label>
    <label class="field"><span>Password</span>
      <input type="password" name="password" autocomplete="current-password" required></label>
    <label class="check"><input type="checkbox" name="remember" value="1" checked> Ingat saya 30 hari</label>
    <button class="btn btn-primary btn-block" type="submit">Masuk</button>
  </form>
</main>
</body></html>
