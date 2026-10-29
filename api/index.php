<?php

// Forward Vercel requests to normal Laravel public/index.php
// Pastikan seluruh direktori writable di /tmp tersedia
$baseTmp = '/tmp/laravel';

$dirs = [
    $baseTmp.'/storage/framework/views',
    $baseTmp.'/storage/framework/cache/data',
    $baseTmp.'/storage/framework/sessions',
    $baseTmp.'/storage/logs',
    $baseTmp.'/bootstrap/cache',
];

foreach ($dirs as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Fallback APP_KEY jika belum diset di Vercel Dashboard Environment Variables
if (! getenv('APP_KEY') && ! isset($_ENV['APP_KEY'])) {
    $fallbackKey = 'base64:Fu7MJURdjGTqhmnQ+fbYnMftealdGnIkDX7lr1+HG+c=';
    putenv("APP_KEY={$fallbackKey}");
    $_ENV['APP_KEY'] = $fallbackKey;
    $_SERVER['APP_KEY'] = $fallbackKey;
}

if (! getenv('APP_STORAGE')) {
    putenv("APP_STORAGE={$baseTmp}/storage");
    $_ENV['APP_STORAGE'] = "{$baseTmp}/storage";
    $_SERVER['APP_STORAGE'] = "{$baseTmp}/storage";
}

// Arahkan cache internal Laravel ke /tmp agar tidak error Read-only file system
putenv("APP_CONFIG_CACHE={$baseTmp}/bootstrap/cache/config.php");
putenv("APP_EVENTS_CACHE={$baseTmp}/bootstrap/cache/events.php");
putenv("APP_PACKAGES_CACHE={$baseTmp}/bootstrap/cache/packages.php");
putenv("APP_ROUTES_CACHE={$baseTmp}/bootstrap/cache/routes.php");
putenv("APP_SERVICES_CACHE={$baseTmp}/bootstrap/cache/services.php");
putenv("VIEW_COMPILED_PATH={$baseTmp}/storage/framework/views");
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=cookie');
putenv('LOG_CHANNEL=stderr');

$_ENV['APP_CONFIG_CACHE'] = "{$baseTmp}/bootstrap/cache/config.php";
$_ENV['APP_EVENTS_CACHE'] = "{$baseTmp}/bootstrap/cache/events.php";
$_ENV['APP_PACKAGES_CACHE'] = "{$baseTmp}/bootstrap/cache/packages.php";
$_ENV['APP_ROUTES_CACHE'] = "{$baseTmp}/bootstrap/cache/routes.php";
$_ENV['APP_SERVICES_CACHE'] = "{$baseTmp}/bootstrap/cache/services.php";
$_ENV['VIEW_COMPILED_PATH'] = "{$baseTmp}/storage/framework/views";
$_ENV['CACHE_STORE'] = 'array';
$_ENV['SESSION_DRIVER'] = 'cookie';
$_ENV['LOG_CHANNEL'] = 'stderr';

// Normalisasi SCRIPT_NAME agar Symfony tidak memotong prefix /api dari REQUEST_URI di Vercel Serverless
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
