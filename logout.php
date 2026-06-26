<?php
require 'config/session.php';
require 'config/database.php';
require 'includes/audit.php';

$user = $_SESSION['user'] ?? null;

if ($user) {
    logAudit(
        $pdo,
        (int)$user['id'],
        'AUTH.LOGOUT',
        'users',
        (int)$user['id'],
        'User logged out: ' . ($user['username'] ?? '?')
    );
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
header('Location: ' . APP_BASE . '/login.php');
exit;
