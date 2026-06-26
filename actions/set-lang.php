<?php
require '../config/session.php';

$lang = $_GET['lang'] ?? 'th';
if (!in_array($lang, ['th', 'en'])) {
    $lang = 'th';
}

$_SESSION['lang'] = $lang;

$redirect = $_GET['redirect'] ?? APP_BASE . '/';
if (strpos($redirect, APP_BASE . '/') !== 0) {
    $redirect = APP_BASE . '/';
}

header('Location: ' . $redirect);
exit;
