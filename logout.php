<?php
session_start();

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
        'User logged out: ' . $user['username']
    );
}

session_destroy();
header('Location: login.php');
exit;
