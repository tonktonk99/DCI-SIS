<?php
require 'includes/auth.php';

checkLogin();

$role = getUser()['role'] ?? '';

if ($role === 'admin') {
    redirect_to(APP_BASE . '/admin/dashboard.php');
} elseif ($role === 'registrar') {
    redirect_to(APP_BASE . '/registrar/dashboard.php');
} elseif ($role === 'professor') {
    redirect_to(APP_BASE . '/professor/dashboard.php');
} elseif ($role === 'student') {
    redirect_to(APP_BASE . '/student/dashboard.php');
} elseif ($role === 'alumni') {
    redirect_to(APP_BASE . '/alumni/dashboard.php');
} else {
    redirect_to(APP_BASE . '/logout.php');
}
