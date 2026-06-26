<?php
session_start();

require_once __DIR__ . '/lang.php';

function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: ../login.php');
        exit;
    }
}

function getUser() {
    return $_SESSION['user'] ?? null;
}