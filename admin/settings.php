<?php
require '../includes/auth.php';
checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'admin') {
    die('Access denied');
}

$pageTitle = __('system_configuration');
$crumb = __('administration') . ' / ' . __('system_configuration');
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('system_administration') ?></div>
            <div class="hero-title"><?= __('system_settings') ?></div>
            <div class="hero-desc"><?= __('settings_desc') ?></div>
        </div>

        <div class="card">
            <h3 class="section-title"><?= __('settings_placeholder_title') ?></h3>
            <p><?= __('settings_placeholder_text') ?></p>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
