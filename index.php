<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/page.php';

$u = current_user();
if (!$u) redirect('login.php');
if ((int)$u['must_change_pw']) redirect('change_password.php');

header('Cache-Control: no-cache, must-revalidate');
page_head('Budget Harian');
?>
<body>
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<div id="app" class="app">
  <header class="topbar">
    <div class="topbar-title">
      <img src="icons/icon.svg" alt="" width="32" height="32">
      <div>
        <strong>Budget Harian</strong>
        <small id="storeTitle">&nbsp;</small>
      </div>
    </div>
    <nav class="sidenav" id="sideNav"></nav>
    <button class="avatar" id="btnAccount" aria-label="Akun"><?= h(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></button>
  </header>

  <main class="content">
    <section class="view" id="view-harian" data-view="harian"></section>
    <section class="view" id="view-bulanan" data-view="bulanan" hidden></section>
    <section class="view" id="view-upload" data-view="upload" hidden></section>
    <section class="view" id="view-admin" data-view="admin" hidden></section>
  </main>

  <nav class="tabbar" id="tabBar"></nav>
</div>
<div id="sheetRoot"></div>
<div id="toastRoot" class="toasts" aria-live="polite"></div>
<form id="logoutForm" method="post" action="logout.php" hidden><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"></form>
<script type="module" src="js/app.js?v=<?= APP_VERSION ?>"></script>
</body></html>
