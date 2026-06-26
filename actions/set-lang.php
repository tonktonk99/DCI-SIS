<?php
session_start();

$lang = $_GET['lang'] ?? 'th';
if (!in_array($lang, ['th', 'en'])) {
    $lang = 'th';
}

$_SESSION['lang'] = $lang;

$redirect = $_GET['redirect'] ?? '/dci-sis/';
if (strpos($redirect, '/dci-sis/') !== 0) {
    $redirect = '/dci-sis/';
}

header('Location: ' . $redirect);
exit;
