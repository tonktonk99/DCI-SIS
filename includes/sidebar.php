<?php
$user = getUser();
$currentRole = $user['role'] ?? 'guest';
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

if (!function_exists('navActive')) {
    function navActive(string $path): string
    {
        global $currentPath;
        return strpos($currentPath, $path) !== false ? ' active' : '';
    }
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="brand">
            <img src="/dci-sis/assets/images/logo.png" class="brand-logo" alt="DCI Logo">
            <div>
                <div class="brand-title">DCI Academic<br>Portal</div>
                <div class="brand-subtitle">ศูนย์พุทธศาสตร์ศึกษา DCI</div>
            </div>
        </div>
    </div>

    <nav class="nav">

        <?php if ($currentRole === 'student'): ?>

            <div class="nav-section"><?= __('academics') ?></div>
            <a class="nav-link<?= navActive('/student/dashboard.php') ?>" href="/dci-sis/student/dashboard.php"><span class="nav-icon">◆</span> <?= __('academic_home') ?></a>
            <a class="nav-link<?= navActive('/student/enrollment.php') ?>" href="/dci-sis/student/enrollment.php"><span class="nav-icon">❋</span> <?= __('course_registration') ?></a>
            <a class="nav-link<?= navActive('/student/courses.php') ?>" href="/dci-sis/student/courses.php"><span class="nav-icon">☰</span> <?= __('my_courses') ?></a>
            <a class="nav-link<?= navActive('/student/grades.php') ?>" href="/dci-sis/student/grades.php"><span class="nav-icon">✎</span> <?= __('academic_record') ?></a>
            <a class="nav-link<?= navActive('/student/transcript.php') ?>" href="/dci-sis/student/transcript.php"><span class="nav-icon">⊞</span> <?= __('academic_transcript') ?></a>
            <a class="nav-link<?= navActive('/student/schedule.php') ?>" href="/dci-sis/student/schedule.php"><span class="nav-icon">▦</span> <?= __('class_schedule') ?></a>
            <a class="nav-link<?= navActive('/student/exams.php') ?>" href="/dci-sis/student/exams.php"><span class="nav-icon">⊡</span> <?= __('examinations') ?></a>

            <div class="nav-section"><?= __('services') ?></div>
            <a class="nav-link<?= navActive('/student/finance.php') ?>" href="/dci-sis/student/finance.php"><span class="nav-icon">฿</span> <?= __('student_financial') ?></a>
            <a class="nav-link<?= navActive('/student/requests.php') ?>" href="/dci-sis/student/requests.php"><span class="nav-icon">§</span> <?= __('document_services') ?></a>

        <?php endif; ?>

        <?php if ($currentRole === 'professor'): ?>

            <div class="nav-section"><?= __('teaching') ?></div>
            <a class="nav-link<?= navActive('/professor/dashboard.php') ?>" href="/dci-sis/professor/dashboard.php"><span class="nav-icon">◆</span> <?= __('faculty_home') ?></a>
            <a class="nav-link<?= navActive('/professor/courses.php') ?>" href="/dci-sis/professor/courses.php"><span class="nav-icon">☰</span> <?= __('my_courses') ?></a>
            <a class="nav-link<?= navActive('/professor/students.php') ?>" href="/dci-sis/professor/students.php"><span class="nav-icon">⊞</span> <?= __('course_roster') ?></a>
            <a class="nav-link<?= navActive('/professor/exams.php') ?>" href="/dci-sis/professor/exams.php"><span class="nav-icon">⊡</span> <?= __('examinations') ?></a>

            <div class="nav-section"><?= __('grading') ?></div>
            <a class="nav-link<?= navActive('/professor/gradebook.php') ?>" href="/dci-sis/professor/gradebook.php"><span class="nav-icon">✎</span> <?= __('gradebook') ?></a>

        <?php endif; ?>

        <?php if ($currentRole === 'registrar'): ?>

            <div class="nav-section"><?= __('academic_records') ?></div>
            <a class="nav-link<?= navActive('/registrar/dashboard.php') ?>" href="/dci-sis/registrar/dashboard.php"><span class="nav-icon">◆</span> <?= __('registrar_home') ?></a>
            <a class="nav-link<?= navActive('/registrar/students.php') ?>" href="/dci-sis/registrar/students.php"><span class="nav-icon">⊞</span> <?= __('student_records') ?></a>
            <a class="nav-link<?= navActive('/registrar/professors.php') ?>" href="/dci-sis/registrar/professors.php"><span class="nav-icon">✦</span> <?= __('faculty_directory') ?></a>
            <a class="nav-link<?= navActive('/registrar/grades.php') ?>" href="/dci-sis/registrar/grades.php"><span class="nav-icon">✎</span> <?= __('grade_submission_review') ?></a>
            <a class="nav-link<?= navActive('/registrar/transcripts.php') ?>" href="/dci-sis/registrar/transcripts.php"><span class="nav-icon">⊡</span> <?= __('academic_transcripts') ?></a>
            <a class="nav-link<?= navActive('/registrar/document-requests.php') ?>" href="/dci-sis/registrar/document-requests.php"><span class="nav-icon">§</span> <?= __('document_services') ?></a>

            <div class="nav-section"><?= __('curriculum') ?></div>
            <a class="nav-link<?= navActive('/registrar/programs.php') ?>" href="/dci-sis/registrar/programs.php"><span class="nav-icon">❋</span> <?= __('degree_programs') ?></a>
            <a class="nav-link<?= navActive('/registrar/courses.php') ?>" href="/dci-sis/registrar/courses.php"><span class="nav-icon">☰</span> <?= __('course_catalog') ?></a>
            <a class="nav-link<?= navActive('/registrar/sections.php') ?>" href="/dci-sis/registrar/sections.php"><span class="nav-icon">▦</span> <?= __('class_sections') ?></a>
            <a class="nav-link<?= navActive('/registrar/exams.php') ?>" href="/dci-sis/registrar/exams.php"><span class="nav-icon">⊡</span> <?= __('examination_schedule') ?></a>

            <div class="nav-section"><?= __('administration') ?></div>
            <a class="nav-link<?= navActive('/registrar/academic-years.php') ?>" href="/dci-sis/registrar/academic-years.php"><span class="nav-icon">⚐</span> <?= __('academic_calendar') ?></a>
            <a class="nav-link<?= navActive('/registrar/semesters.php') ?>" href="/dci-sis/registrar/semesters.php"><span class="nav-icon">◇</span> <?= __('terms_semesters') ?></a>

        <?php endif; ?>

        <?php if ($currentRole === 'admin'): ?>

            <div class="nav-section"><?= __('administration') ?></div>
            <a class="nav-link<?= navActive('/admin/dashboard.php') ?>" href="/dci-sis/admin/dashboard.php"><span class="nav-icon">◆</span> <?= __('admin_home') ?></a>
            <a class="nav-link<?= navActive('/admin/users.php') ?>" href="/dci-sis/admin/users.php"><span class="nav-icon">⊞</span> <?= __('user_accounts') ?></a>
            <a class="nav-link<?= navActive('/admin/roles.php') ?>" href="/dci-sis/admin/roles.php"><span class="nav-icon">⚐</span> <?= __('roles_permissions') ?></a>

            <div class="nav-section"><?= __('system') ?></div>
            <a class="nav-link<?= navActive('/admin/settings.php') ?>" href="/dci-sis/admin/settings.php"><span class="nav-icon">◇</span> <?= __('system_configuration') ?></a>
            <a class="nav-link<?= navActive('/admin/audit-logs.php') ?>" href="/dci-sis/admin/audit-logs.php"><span class="nav-icon">✎</span> <?= __('audit_trail') ?></a>

        <?php endif; ?>

        <?php if ($currentRole === 'alumni'): ?>

            <div class="nav-section"><?= __('alumni_services') ?></div>
            <a class="nav-link<?= navActive('/alumni/dashboard.php') ?>" href="/dci-sis/alumni/dashboard.php"><span class="nav-icon">◆</span> <?= __('alumni_home') ?></a>
            <a class="nav-link<?= navActive('/alumni/profile.php') ?>" href="/dci-sis/alumni/profile.php"><span class="nav-icon">⊞</span> <?= __('alumni_profile') ?></a>

            <div class="nav-section"><?= __('document_requests') ?></div>
            <a class="nav-link<?= navActive('/alumni/transcript_request.php') ?>" href="/dci-sis/alumni/transcript_request.php"><span class="nav-icon">⊡</span> <?= __('transcript_request') ?></a>
            <a class="nav-link<?= navActive('/alumni/certificate_request.php') ?>" href="/dci-sis/alumni/certificate_request.php"><span class="nav-icon">§</span> <?= __('certificate_request') ?></a>

        <?php endif; ?>

        <div class="nav-divider"></div>
        <a class="nav-link" href="/dci-sis/logout.php"><span class="nav-icon">↗</span> <?= __('sign_out') ?></a>
    </nav>

    <div class="sidebar-user">
        <div class="avatar">
            <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
        </div>
        <div>
            <div style="font-size:12.5px;font-weight:600;">
                <?= htmlspecialchars($user['username'] ?? 'Guest') ?>
            </div>
            <div style="font-size:10.5px;color:#a89980;text-transform:capitalize;">
                <?= htmlspecialchars($currentRole) ?>
            </div>
        </div>
    </div>
</aside>
