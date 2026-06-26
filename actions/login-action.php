<?php
require '../config/session.php';
require '../config/database.php';
require '../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_BASE . '/login.php');
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
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([$newHash, (int)$user['id']]);
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
        'name'     => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
                       ?: $user['username'],
    ];
    $_SESSION['last_activity'] = time();

    logAudit(
        $pdo,
        (int)$user['id'],
        'AUTH.LOGIN_SUCCESS',
        'users',
        (int)$user['id'],
        'User logged in successfully: ' . $user['username']
    );

    $role = $user['role'];
    if ($role === 'admin') {
        header('Location: ' . APP_BASE . '/admin/dashboard.php');
    } elseif ($role === 'registrar') {
        header('Location: ' . APP_BASE . '/registrar/dashboard.php');
    } elseif ($role === 'student') {
        header('Location: ' . APP_BASE . '/student/dashboard.php');
    } elseif ($role === 'professor') {
        header('Location: ' . APP_BASE . '/professor/dashboard.php');
    } elseif ($role === 'alumni') {
        header('Location: ' . APP_BASE . '/alumni/dashboard.php');
    } else {
        header('Location: ' . APP_BASE . '/index.php');
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

header('Location: ' . APP_BASE . '/login.php?error=1');
exit;
