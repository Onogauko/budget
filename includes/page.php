<?php
declare(strict_types=1);

function page_head(string $title): void
{
    $v = APP_VERSION;
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta name="theme-color" content="#5b21b6">'
        . '<title>' . h($title) . '</title>'
        . '<link rel="manifest" href="manifest.webmanifest">'
        . '<link rel="icon" href="icons/icon.svg" type="image/svg+xml">'
        . '<link rel="apple-touch-icon" href="icons/icon-192.png">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="css/app.css?v=' . $v . '">'
        . '</head>';
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}
