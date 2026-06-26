<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'student') {
    die('Access denied');
}

$pageTitle = __('student_exams');
$crumb = __('student_exams');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$exams = [];
if ($studentId > 0) {
    $examStmt = $pdo->prepare("SELECT exams.*, sections.section_number, courses.course_code, courses.course_name_th, courses.credits, semesters.semester_name, exam_scores.score FROM enrollments JOIN sections ON sections.id = enrollments.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = enrollments.semester_id JOIN exams ON exams.section_id = sections.id LEFT JOIN exam_scores ON exam_scores.exam_id = exams.id AND exam_scores.student_id = enrollments.student_id WHERE enrollments.student_id = ? AND enrollments.status = 'enrolled' AND exams.status IN ('published', 'completed') ORDER BY exams.exam_date ASC, exams.start_time ASC");
    $examStmt->execute([$studentId]);
    $exams = $examStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = __('no_student_profile');
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('examinations') ?></div>
            <div class="hero-title"><?= __('student_exams') ?></div>
            <div class="hero-desc"><?= __('exam_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('course') ?></th>
                        <th><?= __('type') ?></th>
                        <th><?= __('time') ?></th>
                        <th><?= __('exam_room') ?></th>
                        <th><?= __('score') ?></th>
                        <th><?= __('status_col') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($exams) === 0): ?>
                        <tr><td colspan="7"><?= __('no_exams_published') ?></td></tr>
                    <?php endif; ?>

                    <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td class="mono"><?= htmlspecialchars($exam['exam_date']) ?></td>
                            <td>
                                <span class="mono"><?= htmlspecialchars($exam['course_code']) ?></span>
                                <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($exam['course_name_th']) ?> · Sec <?= htmlspecialchars($exam['section_number']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($exam['exam_type']) ?></td>
                            <td class="mono"><?= htmlspecialchars(substr($exam['start_time'] ?? '', 0, 5)) ?> - <?= htmlspecialchars(substr($exam['end_time'] ?? '', 0, 5)) ?></td>
                            <td><?= htmlspecialchars($exam['room_name'] ?? '-') ?></td>
                            <td class="mono">
                                <?php if ($exam['score'] !== null): ?>
                                    <?= number_format((float)$exam['score'], 2) ?> / <?= number_format((float)$exam['max_score'], 2) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($exam['status'] === 'completed'): ?>
                                    <span class="badge badge-green"><?= __('completed') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-blue"><?= __('published') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
