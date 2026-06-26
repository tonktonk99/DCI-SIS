<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('student');
$user = getUser();

$pageTitle = __('student_grades');
$crumb = __('student_grades');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$grades = [];
if ($studentId > 0) {
    $gradeStmt = $pdo->prepare("SELECT final_grades.*, courses.course_code, courses.course_name_th, courses.course_name_en, courses.credits, sections.section_number, semesters.semester_name FROM final_grades JOIN sections ON sections.id = final_grades.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE final_grades.student_id = ? AND final_grades.status IN ('released', 'locked') ORDER BY semesters.id DESC, courses.course_code ASC");
    $gradeStmt->execute([$studentId]);
    $grades = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = __('no_student_profile');
}

$totalCredits = 0;
$totalPoints = 0;
foreach ($grades as $grade) {
    $credits = (int)$grade['credits'];
    $totalCredits += $credits;
    $totalPoints += $credits * (float)$grade['grade_point'];
}
$gpa = $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_record') ?></div>
            <div class="hero-title"><?= __('student_grades') ?></div>
            <div class="hero-desc"><?= __('grades_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('released_count') ?></div>
                    <div class="kpi-value"><?= count($grades) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('total_credits') ?></div>
                    <div class="kpi-value"><?= (int)$totalCredits ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('gpa_from_released') ?></div>
                    <div class="kpi-value"><?= number_format($gpa, 2) ?></div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('semester') ?></th>
                        <th><?= __('course') ?></th>
                        <th><?= __('section') ?></th>
                        <th><?= __('credits_col') ?></th>
                        <th><?= __('score') ?></th>
                        <th><?= __('grade') ?></th>
                        <th><?= __('status_col') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($grades) === 0): ?>
                        <tr><td colspan="7"><?= __('no_grades_published') ?></td></tr>
                    <?php endif; ?>

                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td><?= htmlspecialchars($grade['semester_name']) ?></td>
                            <td>
                                <span class="mono"><?= htmlspecialchars($grade['course_code']) ?></span>
                                <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($grade['course_name_th']) ?></div>
                            </td>
                            <td class="mono"><?= htmlspecialchars($grade['section_number']) ?></td>
                            <td class="mono"><?= (int)$grade['credits'] ?></td>
                            <td class="mono"><?= number_format((float)$grade['raw_score'], 2) ?></td>
                            <td class="serif" style="font-size:22px;font-weight:700;color:#1c3a6e;">
                                <?= htmlspecialchars($grade['letter_grade'] ?? '-') ?>
                                <div class="mono" style="font-size:11px;color:#8a7c5e;"><?= number_format((float)$grade['grade_point'], 2) ?></div>
                            </td>
                            <td><span class="badge badge-green"><?= htmlspecialchars($grade['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
