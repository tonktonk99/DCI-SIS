<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'student') {
    die('Access denied');
}

$pageTitle = __('student_courses');
$crumb = __('student_courses');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;
$courses = [];

function myCourseInstructor(PDO $pdo, int $sectionId): string
{
    $stmt = $pdo->prepare("SELECT staff.first_name, staff.last_name FROM section_instructors JOIN staff ON staff.id = section_instructors.staff_id WHERE section_instructors.section_id = ? ORDER BY section_instructors.id ASC LIMIT 1");
    $stmt->execute([$sectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? trim($row['first_name'] . ' ' . $row['last_name']) : '-';
}

function myCourseSchedule(PDO $pdo, int $sectionId): string
{
    $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time, room_name FROM section_schedules WHERE section_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$sectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return '-';
    $start = substr($row['start_time'], 0, 5);
    $end = substr($row['end_time'], 0, 5);
    $room = $row['room_name'] ? ' · ' . $row['room_name'] : '';
    return $row['day_of_week'] . ' ' . $start . '-' . $end . $room;
}

if ($studentId > 0) {
    $courseStmt = $pdo->prepare("SELECT enrollments.*, sections.section_number, sections.room_name, courses.course_code, courses.course_name_th, courses.course_name_en, courses.credits, semesters.semester_name FROM enrollments JOIN sections ON sections.id = enrollments.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = enrollments.semester_id WHERE enrollments.student_id = ? AND enrollments.status = 'enrolled' ORDER BY semesters.id DESC, courses.course_code ASC");
    $courseStmt->execute([$studentId]);
    $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = __('no_student_profile');
}

$totalCredits = 0;
foreach ($courses as $course) {
    $totalCredits += (int)$course['credits'];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('student_courses') ?></div>
            <div class="hero-title"><?= __('my_courses') ?></div>
            <div class="hero-desc"><?= __('courses_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('registered_courses') ?></div>
                    <div class="kpi-value"><?= count($courses) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('total_credits') ?></div>
                    <div class="kpi-value"><?= (int)$totalCredits ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('status') ?></div>
                    <div class="kpi-value"><?= __('status_active') ?></div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;">
                <h3 class="section-title" style="margin:0;"><?= __('course_list') ?></h3>
                <a href="/dci-sis/student/enrollment.php" class="btn"><?= __('go_to_enrollment') ?></a>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('semester') ?></th>
                        <th><?= __('course_code_name') ?></th>
                        <th><?= __('section') ?></th>
                        <th><?= __('instructor') ?></th>
                        <th><?= __('schedule_col') ?></th>
                        <th><?= __('credits_col') ?></th>
                        <th><?= __('status_col') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($courses) === 0): ?>
                        <tr><td colspan="7"><?= __('no_enrolled_courses') ?></td></tr>
                    <?php endif; ?>

                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?= htmlspecialchars($course['semester_name']) ?></td>
                            <td>
                                <span class="mono"><?= htmlspecialchars($course['course_code']) ?></span>
                                <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($course['course_name_th']) ?></div>
                                <?php if (!empty($course['course_name_en'])): ?>
                                    <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($course['course_name_en']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= htmlspecialchars($course['section_number']) ?></td>
                            <td><?= htmlspecialchars(myCourseInstructor($pdo, (int)$course['section_id'])) ?></td>
                            <td><?= htmlspecialchars(myCourseSchedule($pdo, (int)$course['section_id'])) ?></td>
                            <td class="mono"><?= (int)$course['credits'] ?></td>
                            <td><span class="badge badge-green"><?= __('enrolled') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
