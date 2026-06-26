<?php
define('APP_BASE', '/dci-sis');
define('SESSION_IDLE_TTL', 7200);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;

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
