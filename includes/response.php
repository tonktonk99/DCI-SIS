<?php
function redirect_to(string $path, int $statusCode = 302): never
{
    // Block off-site URLs to prevent open redirect
    if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
        $path = APP_BASE . '/';
    }
    http_response_code($statusCode);
    header('Location: ' . $path);
    exit;
}

function redirect_back(string $fallback): never
{
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
        redirect_to($ref);
    }
    redirect_to($fallback);
}

function abort_403(string $message = 'Access Denied'): never
{
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 Forbidden</title>'
        . '<style>body{font-family:Arial,sans-serif;padding:60px;color:#1e1e1e;}</style></head>'
        . '<body><h1 style="color:#a51c30;">403 Forbidden</h1>'
        . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><a href="' . htmlspecialchars(APP_BASE, ENT_QUOTES, 'UTF-8') . '/login.php">Return to login</a></p>'
        . '</body></html>';
    exit;
}

function abort_404(string $message = 'Record Not Found'): never
{
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 Not Found</title>'
        . '<style>body{font-family:Arial,sans-serif;padding:60px;color:#1e1e1e;}</style></head>'
        . '<body><h1 style="color:#1c3a6e;">404 Not Found</h1>'
        . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</body></html>';
    exit;
}

function abort_400(string $message = 'Bad Request'): never
{
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>400 Bad Request</title>'
        . '<style>body{font-family:Arial,sans-serif;padding:60px;color:#1e1e1e;}</style></head>'
        . '<body><h1>400 Bad Request</h1>'
        . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</body></html>';
    exit;
}
