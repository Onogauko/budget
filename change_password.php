<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/page.php';

$u = current_user();
if (!$u) redirect('login.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = (string)($_POST['old_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password2'] ?? '');
    $st = db()->prepare('SELECT password_hash FROM bg_users WHERE id = ?');
    $st->execute([$u['id']]);
    if (!hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) $error = 'Sesi kedaluwarsa, silakan coba lagi.';
    elseif (!password_verify($old, (string)$st->fetchColumn())) $error = 'Password lama salah.';
    elseif ($new !== $new2) $error = 'Konfirmasi password tidak sama.';
    elseif ($old === $new) $error = 'Password baru harus berbeda dari password lama.';
    elseif ($p = password_problem($new)) $error = $p;
    else {
        db()->prepare('UPDATE bg_users SET password_hash = ?, must_change_pw = 0 WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        redirect('./');
    }
}
page_head('Ganti Password • Budget Harian');
?>
<body class="auth">
<main class="auth-card">
  <div class="auth-brand">
    <img src="icons/icon.svg" alt="" width="56" height="56">
    <div>
      <h1>Ganti Password</h1>
      <p>Halo <?= h($u['name']) ?><?= (int)$u['must_change_pw'] ? ', buat password baru sebelum melanjutkan.' : '' ?></p>
    </div>
  </div>
  <?php if ($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label class="field"><span>Password lama</span><input type="password" name="old_password" autocomplete="current-password" required></label>
    <label class="field"><span>Password baru</span><input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
    <label class="field"><span>Ulangi password baru</span><input type="password" name="new_password2" autocomplete="new-password" minlength="8" required></label>
    <p class="hint">Minimal 8 karakter, berisi huruf dan angka.</p>
    <button class="btn btn-primary btn-block" type="submit">Simpan Password</button>
  </form>
  <form method="post" action="logout.php" class="auth-foot">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <button class="btn btn-ghost btn-block" type="submit">Keluar</button>
  </form>
</main>
</body></html>
