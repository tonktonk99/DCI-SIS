<?php
// Copy this file to database.php and configure via environment variables.
// For local dev without env vars, the fallback defaults below are used.
// Production: set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS as env vars.
$host      = getenv('DB_HOST') ?: '127.0.0.1';
$port      = getenv('DB_PORT') ?: '3306';
$dbname    = getenv('DB_NAME') ?: 'dci_sis';
$username  = getenv('DB_USER') ?: 'dci_app';
$dbPassEnv = getenv('DB_PASS');
$password  = $dbPassEnv !== false ? $dbPassEnv : '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[dci-sis] DB connection failed: ' . $e->getMessage());
    $msg = (defined('APP_DEBUG') && APP_DEBUG)
        ? 'Database connection error: ' . htmlspecialchars($e->getMessage())
        : 'Database connection error. Please contact the administrator.';
    die($msg);
}
