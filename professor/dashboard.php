<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'professor') {
    die('Access denied');
}

$pageTitle = __('faculty_home');
$crumb = __('teaching_workspace');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
$staffId = $staff ? (int)$staff['id'] : 0;

$sectionCount = 0;
$studentCount = 0;
$examCount = 0;
$submittedGrades = 0;
$sections = [];

if ($staffId > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM section_instructors WHERE staff_id = ?");
    $stmt->execute([$staffId]);
    $sectionCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT enrollments.student_id) FROM section_instructors JOIN enrollments ON enrollments.section_id = section_instructors.section_id AND enrollments.status = 'enrolled' WHERE section_instructors.staff_id = ?");
    $stmt->execute([$staffId]);
    $studentCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM section_instructors JOIN exams ON exams.section_id = section_instructors.section_id WHERE section_instructors.staff_id = ?");
    $stmt->execute([$staffId]);
    $examCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM section_instructors JOIN final_grades ON final_grades.section_id = section_instructors.section_id WHERE section_instructors.staff_id = ? AND final_grades.status = 'submitted'");
    $stmt->execute([$staffId]);
    $submittedGrades = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT sections.*, semesters.semester_name, courses.course_code, courses.course_name_th FROM section_instructors JOIN sections ON sections.id = section_instructors.section_id JOIN semesters ON semesters.id = sections.semester_id JOIN courses ON courses.id = sections.course_id WHERE section_instructors.staff_id = ? ORDER BY sections.id DESC LIMIT 8");
    $stmt->execute([$staffId]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = __('no_professor_profile');
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('teaching_workspace') ?></div>
            <div class="hero-title"><?= htmlspecialchars($staff ? ($staff['first_name'] . ' ' . $staff['last_name']) : ($user['username'] ?? 'Professor')) ?></div>
            <div class="hero-desc"><?= __('prof_dashboard_desc') ?></div>
            <div class="kpi-row">
                <div class="kpi"><div class="kpi-label"><?= __('sections_count') ?></div><div class="kpi-value"><?= number_format($sectionCount) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('students') ?></div><div class="kpi-value"><?= number_format($studentCount) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('exams_count') ?></div><div class="kpi-value"><?= number_format($examCount) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('submitted_grades') ?></div><div class="kpi-value"><?= number_format($submittedGrades) ?></div></div>
            </div>
        </div>

        <?php if ($message): ?><div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('my_sections') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead><tr><th><?= __('term') ?></th><th><?= __('course') ?></th><th><?= __('section') ?></th><th><?= __('students') ?></th><th><?= __('status') ?></th></tr></thead>
                        <tbody>
                            <?php if (count($sections) === 0): ?><tr><td colspan="5"><?= __('no_sections') ?></td></tr><?php endif; ?>
                            <?php foreach ($sections as $section): ?>
                                <tr>
                                    <td><?= htmlspecialchars($section['semester_name']) ?></td>
                                    <td><span class="mono"><?= htmlspecialchars($section['course_code']) ?></span><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($section['course_name_th']) ?></div></td>
                                    <td class="mono"><?= htmlspecialchars($section['section_number']) ?></td>
                                    <td class="mono"><?= (int)$section['enrolled_count'] ?> / <?= (int)$section['capacity'] ?></td>
                                    <td><span class="badge badge-blue"><?= htmlspecialchars($section['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('quick_actions') ?></h3>
                <div class="card">
                    <p><a class="btn" href="/dci-sis/professor/gradebook.php"><?= __('open_gradebook') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/professor/exams.php"><?= __('enter_exam_scores') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/professor/students.php"><?= __('view_student_roster') ?></a></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
