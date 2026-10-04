<?php
// Salin file ini menjadi config/config.php lalu isi sesuai database di cPanel hostdata.id.
return [
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=NAMA_DATABASE;charset=utf8mb4',
        'user' => 'USER_DATABASE',
        'pass' => 'PASSWORD_DATABASE',
    ],
    'timezone'    => 'Asia/Jakarta',
    'debug'       => false,
    // Batas ukuran file upload (MB)
    'max_upload_mb' => 10,
];
