<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''))) {
    logout_user();
}
header('Location: login.php');
exit;
