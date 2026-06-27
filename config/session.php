<?php
define('APP_ENV',   getenv('APP_ENV') ?: 'local');
define('APP_DEBUG', APP_ENV !== 'production');
define('APP_BASE',  '/dci-sis');
define('SESSION_IDLE_TTL', 7200);

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Bangkok');

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors',     '1');

// Security headers — sent before any HTML output on every request
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.name',            'dci_sess');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string)SESSION_IDLE_TTL);
    session_start();
}
