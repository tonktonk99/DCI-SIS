<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}

if (!isset($crumb)) {
    $crumb = 'DCI Academic Portal';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> - DCI Academic Portal</title>
    <link rel="stylesheet" href="/dci-sis/assets/css/app.css">
</head>
<body>
<div class="app">