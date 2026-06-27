<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('class_sections');
$crumb = __('office_of_registrar') . ' / ' . __('academic_setup');
$message = '';

$semesterStmt = $pdo->query("SELECT * FROM semesters ORDER BY id DESC");
$semesters = $semesterStmt->fetchAll(PDO::FETCH_ASSOC);

$courseStmt = $pdo->query("SELECT * FROM courses WHERE status = 'active' ORDER BY course_code ASC");
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

$staffStmt = $pdo->query("SELECT * FROM staff WHERE status = 'active' ORDER BY first_name ASC, last_name ASC");
$staffList = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $semester_id = (int)($_POST['semester_id'] ?? 0);
    $course_id = (int)($_POST['course_id'] ?? 0);
    $section_number = trim($_POST['section_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $room_name = trim($_POST['room_name'] ?? '');
    $status = input_enum($_POST, 'status', ['active', 'inactive'], 'active');
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $day_of_week = trim($_POST['day_of_week'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $schedule_room = trim($_POST['schedule_room'] ?? '');

    if ($semester_id <= 0 || $course_id <= 0 || $section_number === '') {
        $message = __('fill_section_fields');
    } else {
        $check = $pdo->prepare("SELECT id FROM sections WHERE semester_id = ? AND course_id = ? AND section_number = ? LIMIT 1");
        $check->execute([$semester_id, $course_id, $section_number]);

        if ($check->fetchColumn()) {
            $message = __('duplicate_section');
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO sections (semester_id, course_id, section_number, capacity, enrolled_count, room_name, status) VALUES (?, ?, ?, ?, 0, ?, ?)");
                $stmt->execute([
                    $semester_id,
                    $course_id,
                    $section_number,
                    $capacity,
                    $room_name ?: null,
                    $status
                ]);
                $sectionId = (int)$pdo->lastInsertId();

                if ($staff_id > 0) {
                    $instructorStmt = $pdo->prepare("INSERT INTO section_instructors (section_id, staff_id) VALUES (?, ?)");
                    $instructorStmt->execute([$sectionId, $staff_id]);
                }

                if ($day_of_week !== '' && $start_time !== '' && $end_time !== '') {
                    $scheduleStmt = $pdo->prepare("INSERT INTO section_schedules (section_id, day_of_week, start_time, end_time, room_name) VALUES (?, ?, ?, ?, ?)");
                    $scheduleStmt->execute([
                        $sectionId,
                        $day_of_week,
                        $start_time,
                        $end_time,
                        $schedule_room ?: ($room_name ?: null)
                    ]);
                }

                $pdo->commit();
                header('Location: sections.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = __('save_failed_duplicate_section');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM sections WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus) {
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            $update = $pdo->prepare("UPDATE sections SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $id]);
        }
    }
    header('Location: sections.php');
    exit;
}

$stmt = $pdo->query("SELECT sections.*, semesters.semester_name, courses.course_code, courses.course_name_th FROM sections JOIN semesters ON semesters.id = sections.semester_id JOIN courses ON courses.id = sections.course_id ORDER BY sections.id DESC");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

function sectionInstructorName(PDO $pdo, int $sectionId): string
{
    $stmt = $pdo->prepare("SELECT staff.first_name, staff.last_name FROM section_instructors JOIN staff ON staff.id = section_instructors.staff_id WHERE section_instructors.section_id = ? ORDER BY section_instructors.id ASC LIMIT 1");
    $stmt->execute([$sectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? trim($row['first_name'] . ' ' . $row['last_name']) : '-';
}

function sectionScheduleText(PDO $pdo, int $sectionId): string
{
    $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time, room_name FROM section_schedules WHERE section_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$sectionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '-';
    }
    return $row['day_of_week'] . ' ' . substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5) . ($row['room_name'] ? ' · ' . $row['room_name'] : '');
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_setup') ?></div>
            <div class="hero-title"><?= __('manage_sections_page') ?></div>
            <div class="hero-desc"><?= __('manage_sections_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('section_list') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('semester') ?></th>
                                <th><?= __('course') ?></th>
                                <th><?= __('section') ?></th>
                                <th><?= __('instructor') ?></th>
                                <th><?= __('schedule_col') ?></th>
                                <th><?= __('seats') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('manage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($sections) === 0): ?>
                                <tr><td colspan="8"><?= __('no_section_records') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($sections as $section): ?>
                                <tr>
                                    <td><?= htmlspecialchars($section['semester_name']) ?></td>
                                    <td>
                                        <span class="mono"><?= htmlspecialchars($section['course_code']) ?></span>
                                        <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($section['course_name_th']) ?></div>
                                    </td>
                                    <td class="mono"><?= htmlspecialchars($section['section_number']) ?></td>
                                    <td><?= htmlspecialchars(sectionInstructorName($pdo, (int)$section['id'])) ?></td>
                                    <td><?= htmlspecialchars(sectionScheduleText($pdo, (int)$section['id'])) ?></td>
                                    <td class="mono"><?= (int)$section['enrolled_count'] ?> / <?= (int)$section['capacity'] ?></td>
                                    <td>
                                        <?php if ($section['status'] === 'active'): ?>
                                            <span class="badge badge-green"><?= __('active') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-blue"><?= htmlspecialchars($section['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$section['id'] ?>"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('toggle') ?></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('add_section') ?></h3>
                <div class="card">
                    <form method="POST" action="sections.php">
                        <?= csrf_field() ?>
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('semester') ?></label>
                            <select name="semester_id" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value=""><?= __('select_semester') ?></option>
                                <?php foreach ($semesters as $semester): ?>
                                    <option value="<?= (int)$semester['id'] ?>"><?= htmlspecialchars($semester['semester_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('course') ?></label>
                            <select name="course_id" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value=""><?= __('select_course') ?></option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= (int)$course['id'] ?>"><?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name_th']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('section') ?></label>
                                <input type="text" name="section_number" placeholder="<?= __('placeholder_section') ?>" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('capacity') ?></label>
                                <input type="number" name="capacity" value="30" min="0" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('main_room') ?></label>
                            <input type="text" name="room_name" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('instructor') ?></label>
                            <select name="staff_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value=""><?= __('not_specified') ?></option>
                                <?php foreach ($staffList as $staff): ?>
                                    <option value="<?= (int)$staff['id'] ?>"><?= htmlspecialchars($staff['staff_code']) ?> - <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('day') ?></label>
                                <select name="day_of_week" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                    <option value=""><?= __('not_specified') ?></option>
                                    <option value="Monday"><?= __('Monday') ?></option>
                                    <option value="Tuesday"><?= __('Tuesday') ?></option>
                                    <option value="Wednesday"><?= __('Wednesday') ?></option>
                                    <option value="Thursday"><?= __('Thursday') ?></option>
                                    <option value="Friday"><?= __('Friday') ?></option>
                                    <option value="Saturday"><?= __('Saturday') ?></option>
                                    <option value="Sunday"><?= __('Sunday') ?></option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('start_time') ?></label>
                                <input type="time" name="start_time" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('end_time') ?></label>
                                <input type="time" name="end_time" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('schedule_room') ?></label>
                            <input type="text" name="schedule_room" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('status') ?></label>
                            <select name="status" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value="active"><?= __('active') ?></option>
                                <option value="inactive"><?= __('inactive') ?></option>
                            </select>
                        </div>

                        <button type="submit" class="btn"><?= __('save_section') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
