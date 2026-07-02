<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('alumni');
$user = getUser();

$pageTitle = __('alumni_profile');
$crumb = __('alumni_profile');

require_once '../includes/IdentityRepository.php';
$student = (new IdentityRepository($pdo))->resolveStudentForUser((int)$user['id']);
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

        <?php if ($student): ?>
            <div class="grid-2">
                <div>
                    <h3 class="section-title" style="margin-top:24px;"><?= __('academic_record') ?></h3>
                    <div class="card">
                        <p><?= __('student_id_label') ?>: <span class="mono"><?= htmlspecialchars($student['student_code'] ?? '-') ?></span></p>
                        <p><?= __('student_name_label') ?>: <?= htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?: '-' ?></p>
                        <p><?= __('program_label') ?>: <?= htmlspecialchars(($student['program_code'] ?? '') !== '' ? $student['program_code'] . ' - ' . ($student['program_name_th'] ?? '') : '-') ?></p>
                        <?php if (!empty($student['year_level'])): ?>
                            <p><?= __('year_level_label') ?>: <?= htmlspecialchars((string)(int)$student['year_level']) ?></p>
                        <?php endif; ?>
                        <p>
                            <?= __('status') ?>:
                            <?php $studyStatus = $student['study_status'] ?? '-'; ?>
                            <span class="badge <?= in_array($studyStatus, ['graduated', 'alumni'], true) ? 'badge-green' : 'badge-blue' ?>"><?= htmlspecialchars($studyStatus) ?></span>
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="section-title" style="margin-top:24px;"><?= __('alumni_quick_links') ?></h3>
                    <div class="card">
                        <p><a class="btn" href="<?= APP_BASE ?>/alumni/transcript_request.php"><?= __('transcript_request') ?></a></p>
                        <p><a class="btn btn-light" href="<?= APP_BASE ?>/alumni/certificate_request.php"><?= __('certificate_request') ?></a></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="margin-top:24px;border-left:4px solid #c89028;"><?= __('no_student_profile') ?></div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
