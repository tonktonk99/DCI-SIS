<?php
require '../includes/auth.php';
requireRole('alumni');
$user = getUser();

$pageTitle = __('alumni_dashboard');
$crumb = __('alumni_home');
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('alumni_portal') ?></div>
            <div class="hero-title"><?= __('alumni_dashboard') ?></div>
            <div class="hero-desc"><?= __('alumni_dashboard_desc') ?></div>
        </div>
        <div class="grid-2">
            <div class="card">
                <h3 class="section-title"><?= __('alumni_services') ?></h3>
                <p><?= __('alumni_services_placeholder_desc') ?></p>
            </div>
            <div class="card">
                <h3 class="section-title"><?= __('alumni_quick_links') ?></h3>
                <p><a class="btn" href="/dci-sis/alumni/transcript-request.php"><?= __('transcript_request') ?></a></p>
                <p><a class="btn btn-light" href="/dci-sis/alumni/certificate-request.php"><?= __('certificate_request') ?></a></p>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
