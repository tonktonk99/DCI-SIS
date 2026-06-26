<?php
session_start();

require '../config/database.php';
require '../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$passwordInput = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$loginOk = false;
$needsRehash = false;

if ($user) {
    $storedPassword = (string)$user['password'];

    // Modern password_hash format
    if (password_get_info($storedPassword)['algo'] !== 0) {
        if (password_verify($passwordInput, $storedPassword)) {
            $loginOk = true;

            if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $needsRehash = true;
            }
        }
    }

    // Legacy MD5 fallback for old prototype accounts
    if (!$loginOk && md5($passwordInput) === $storedPassword) {
        $loginOk = true;
        $needsRehash = true;
    }
}

if ($user && $loginOk) {
    if ($needsRehash) {
        $newHash = password_hash($passwordInput, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([
            $newHash,
            (int)$user['id']
        ]);

        $user['password'] = $newHash;
    }

    $_SESSION['user'] = $user;

    logAudit(
        $pdo,
        (int)$user['id'],
        'AUTH.LOGIN_SUCCESS',
        'users',
        (int)$user['id'],
        'User logged in successfully: ' . $user['username']
    );

    if ($user['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($user['role'] === 'registrar') {
        header('Location: ../registrar/dashboard.php');
    } elseif ($user['role'] === 'student') {
        header('Location: ../student/dashboard.php');
    } elseif ($user['role'] === 'professor') {
        header('Location: ../professor/dashboard.php');
    } elseif ($user['role'] === 'alumni') {
        header('Location: ../alumni/dashboard.php');
    } else {
        header('Location: ../index.php');
    }

    exit;
}

logAudit(
    $pdo,
    $user ? (int)$user['id'] : null,
    'AUTH.LOGIN_FAILED',
    'users',
    $user ? (int)$user['id'] : null,
    'Failed login attempt for username: ' . $username
);

header('Location: ../login.php?error=1');
exit;