<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('professor');
$user = getUser();

$pageTitle = __('my_courses');
$crumb = __('my_courses');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
$staffId = $staff ? (int)$staff['id'] : 0;

$sections = [];
if ($staffId > 0) {
    $sectionStmt = $pdo->prepare("SELECT sections.*, courses.course_code, courses.course_name_th, courses.course_name_en, courses.credits, semesters.semester_name FROM section_instructors JOIN sections ON sections.id = section_instructors.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE section_instructors.staff_id = ? ORDER BY semesters.id DESC, courses.course_code ASC, sections.section_number ASC");
    $sectionStmt->execute([$staffId]);
    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = __('no_professor_profile');
}

function professorCourseSchedule(PDO $pdo, int $sectionId): string
{
    $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time, room_name FROM section_schedules WHERE section_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$sectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return '-';
    }

    $start = substr($row['start_time'], 0, 5);
    $end = substr($row['end_time'], 0, 5);
    $room = $row['room_name'] ? ' · ' . $row['room_name'] : '';

    return $row['day_of_week'] . ' ' . $start . '-' . $end . $room;
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('teaching_workspace') ?></div>
            <div class="hero-title"><?= __('courses_teaching') ?></div>
            <div class="hero-desc"><?= __('prof_courses_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi"><div class="kpi-label"><?= __('sections_count') ?></div><div class="kpi-value"><?= count($sections) ?></div></div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('term') ?></th>
                        <th><?= __('course') ?></th>
                        <th><?= __('section') ?></th>
                        <th><?= __('schedule_col') ?></th>
                        <th><?= __('students') ?></th>
                        <th><?= __('status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sections) === 0): ?>
                        <tr><td colspan="6"><?= __('no_teaching_sections') ?></td></tr>
                    <?php endif; ?>

                    <?php foreach ($sections as $section): ?>
                        <tr>
                            <td><?= htmlspecialchars($section['semester_name']) ?></td>
                            <td>
                                <span class="mono"><?= htmlspecialchars($section['course_code']) ?></span>
                                <div style="font-size:12px;color:#8a7c5e;">
                                    <?= htmlspecialchars($section['course_name_th']) ?> · <?= (int)$section['credits'] ?> <?= __('credits') ?>
                                </div>
                            </td>
                            <td class="mono"><?= htmlspecialchars($section['section_number']) ?></td>
                            <td><?= htmlspecialchars(professorCourseSchedule($pdo, (int)$section['id'])) ?></td>
                            <td class="mono"><?= (int)$section['enrolled_count'] ?> / <?= (int)$section['capacity'] ?></td>
                            <td>
                                <?php if ($section['status'] === 'active'): ?>
                                    <span class="badge badge-green"><?= __('active') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-blue"><?= htmlspecialchars($section['status']) ?></span>
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
