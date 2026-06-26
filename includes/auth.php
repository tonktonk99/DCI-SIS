<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/lang.php';

function checkLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . APP_BASE . '/login.php');
        exit;
    }
    _checkIdleTimeout();
}

function _checkIdleTimeout(): void
{
    $last = $_SESSION['last_activity'] ?? null;
    if ($last !== null && (time() - (int)$last) > SESSION_IDLE_TTL) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: ' . APP_BASE . '/login.php?reason=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireRole(string ...$roles): void
{
    checkLogin();
    $user = getUser();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        header('Location: ' . APP_BASE . '/login.php?reason=forbidden');
        exit;
    }
}

function getUser(): ?array
{
    return $_SESSION['user'] ?? null;
}
