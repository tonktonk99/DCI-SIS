<?php
require '../includes/auth.php';
requireRole('alumni');
$user = getUser();

$pageTitle = __('alumni_profile');
$crumb = __('alumni_profile');
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('alumni_portal') ?></div>
            <div class="hero-title"><?= __('alumni_profile_title') ?></div>
            <div class="hero-desc"><?= __('alumni_profile_desc') ?></div>
        </div>
        <div class="card">
            <h3 class="section-title"><?= __('alumni_account') ?></h3>
            <p><?= __('alumni_username_label') ?> <?= htmlspecialchars($user['username'] ?? '-') ?></p>
            <p><?= __('alumni_role_label') ?> <?= htmlspecialchars($user['role'] ?? '-') ?></p>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
