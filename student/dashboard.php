<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('student');
$user = getUser();

$pageTitle = __('academic_home');
$crumb = __('academic_home');

$stmt = $pdo->prepare("SELECT students.*, programs.program_code, programs.program_name_th FROM students LEFT JOIN programs ON programs.id = students.program_id WHERE students.user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$enrolledCount = 0;
$totalCredits = 0;
$todayClasses = [];
$upcomingExams = [];
$pendingRequests = 0;
$latestGrades = [];

if ($studentId > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'enrolled'");
    $stmt->execute([$studentId]);
    $enrolledCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(courses.credits), 0) FROM enrollments JOIN sections ON sections.id = enrollments.section_id JOIN courses ON courses.id = sections.course_id WHERE enrollments.student_id = ? AND enrollments.status = 'enrolled'");
    $stmt->execute([$studentId]);
    $totalCredits = (int)$stmt->fetchColumn();

    $dayName = date('l');
    $stmt = $pdo->prepare("SELECT courses.course_code, courses.course_name_th, sections.section_number, section_schedules.start_time, section_schedules.end_time, section_schedules.room_name FROM enrollments JOIN sections ON sections.id = enrollments.section_id JOIN courses ON courses.id = sections.course_id JOIN section_schedules ON section_schedules.section_id = sections.id WHERE enrollments.student_id = ? AND enrollments.status = 'enrolled' AND section_schedules.day_of_week = ? ORDER BY section_schedules.start_time ASC LIMIT 5");
    $stmt->execute([$studentId, $dayName]);
    $todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT exams.exam_title, exams.exam_type, exams.exam_date, exams.start_time, exams.room_name, courses.course_code, courses.course_name_th FROM enrollments JOIN sections ON sections.id = enrollments.section_id JOIN courses ON courses.id = sections.course_id JOIN exams ON exams.section_id = sections.id WHERE enrollments.student_id = ? AND enrollments.status = 'enrolled' AND exams.status IN ('published','completed') AND exams.exam_date >= CURDATE() ORDER BY exams.exam_date ASC, exams.start_time ASC LIMIT 5");
    $stmt->execute([$studentId]);
    $upcomingExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE student_id = ? AND status IN ('pending','processing')");
    $stmt->execute([$studentId]);
    $pendingRequests = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT final_grades.*, courses.course_code, courses.course_name_th, sections.section_number, semesters.semester_name FROM final_grades JOIN sections ON sections.id = final_grades.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE final_grades.student_id = ? AND final_grades.status IN ('released','locked') ORDER BY final_grades.id DESC LIMIT 5");
    $stmt->execute([$studentId]);
    $latestGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_home') ?></div>
            <div class="hero-title"><?= htmlspecialchars($student ? ($student['first_name'] . ' ' . $student['last_name']) : ($user['username'] ?? 'Student')) ?></div>
            <div class="hero-desc">
                <?= $student ? htmlspecialchars(($student['program_code'] ?? '-') . ' · ' . ($student['program_name_th'] ?? '-')) : '' ?>
            </div>

            <div class="kpi-row">
                <div class="kpi"><div class="kpi-label"><?= __('registered_courses') ?></div><div class="kpi-value"><?= number_format($enrolledCount) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('credit_hours') ?></div><div class="kpi-value"><?= number_format($totalCredits) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('cumulative_gpa') ?></div><div class="kpi-value"><?= number_format((float)($student['cumulative_gpa'] ?? 0), 2) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('pending_requests') ?></div><div class="kpi-value"><?= number_format($pendingRequests) ?></div></div>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('todays_schedule') ?></h3>
                <div class="list-card">
                    <?php if (count($todayClasses) === 0): ?>
                        <div class="list-item"><?= __('no_classes_today') ?></div>
                    <?php endif; ?>
                    <?php foreach ($todayClasses as $class): ?>
                        <div class="list-item">
                            <strong class="mono"><?= htmlspecialchars(substr($class['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($class['end_time'], 0, 5)) ?></strong>
                            <div class="serif" style="font-size:18px;font-weight:600;"><?= htmlspecialchars($class['course_code']) ?> · <?= htmlspecialchars($class['course_name_th']) ?></div>
                            <div style="font-size:12px;color:#5a4f3a;">Sec <?= htmlspecialchars($class['section_number']) ?> · <?= htmlspecialchars($class['room_name'] ?? '-') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:28px;"><?= __('recent_academic_record') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead><tr><th><?= __('course') ?></th><th><?= __('term') ?></th><th><?= __('score') ?></th><th><?= __('grade') ?></th></tr></thead>
                        <tbody>
                            <?php if (count($latestGrades) === 0): ?>
                                <tr><td colspan="4"><?= __('no_grades_released') ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($latestGrades as $grade): ?>
                                <tr>
                                    <td><span class="mono"><?= htmlspecialchars($grade['course_code']) ?></span><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($grade['course_name_th']) ?></div></td>
                                    <td><?= htmlspecialchars($grade['semester_name']) ?></td>
                                    <td class="mono"><?= number_format((float)$grade['raw_score'], 2) ?></td>
                                    <td class="serif" style="font-size:20px;font-weight:700;color:#1c3a6e;"><?= htmlspecialchars($grade['letter_grade']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('upcoming_examinations') ?></h3>
                <div class="list-card">
                    <?php if (count($upcomingExams) === 0): ?>
                        <div class="list-item"><?= __('no_upcoming_exams') ?></div>
                    <?php endif; ?>
                    <?php foreach ($upcomingExams as $exam): ?>
                        <div class="list-item">
                            <span class="badge badge-blue"><?= htmlspecialchars($exam['exam_type']) ?></span>
                            <div style="margin-top:8px;font-weight:600;"><?= htmlspecialchars($exam['exam_title']) ?></div>
                            <div style="font-size:12px;color:#5a4f3a;"><?= htmlspecialchars($exam['course_code']) ?> · <?= htmlspecialchars($exam['exam_date']) ?> <?= htmlspecialchars(substr($exam['start_time'] ?? '', 0, 5)) ?> · <?= htmlspecialchars($exam['room_name'] ?? '-') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:28px;"><?= __('quick_actions') ?></h3>
                <div class="card">
                    <p><a class="btn" href="/dci-sis/student/enrollment.php"><?= __('course_registration') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/student/transcript.php"><?= __('view_transcript') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/student/requests.php"><?= __('request_documents') ?></a></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
