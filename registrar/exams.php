<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('examination_schedule');
$crumb = __('office_of_registrar') . ' / ' . __('examination_schedule');
$message = '';
$currentUserId = (int)$user['id'];

$sectionStmt = $pdo->query("SELECT sections.id, sections.section_number, sections.room_name, semesters.semester_name, courses.course_code, courses.course_name_th FROM sections JOIN semesters ON semesters.id = sections.semester_id JOIN courses ON courses.id = sections.course_id WHERE sections.status = 'active' ORDER BY semesters.id DESC, courses.course_code ASC, sections.section_number ASC");
$sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $section_id = (int)($_POST['section_id'] ?? 0);
    $exam_type  = input_enum($_POST, 'exam_type', ['midterm', 'final', 'quiz', 'oral', 'other'], '');
    $exam_title = trim($_POST['exam_title'] ?? '');
    $exam_date  = input_date($_POST, 'exam_date');
    $start_time = $_POST['start_time'] ?? null;
    $end_time   = $_POST['end_time'] ?? null;
    $room_name  = trim($_POST['room_name'] ?? '');
    $max_score  = (float)($_POST['max_score'] ?? 100);
    $status     = input_enum($_POST, 'status', ['scheduled', 'published'], 'scheduled');

    if ($section_id <= 0 || $exam_type === '' || $exam_title === '' || !$exam_date) {
        $message = __('fill_exam_fields');
    } else {
        $stmt = $pdo->prepare("INSERT INTO exams (section_id, exam_type, exam_title, exam_date, start_time, end_time, room_name, max_score, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $section_id,
            $exam_type,
            $exam_title,
            $exam_date,
            $start_time ?: null,
            $end_time ?: null,
            $room_name ?: null,
            $max_score,
            $status
        ]);
        $newExamId = (int)$pdo->lastInsertId();
        logAudit($pdo, $currentUserId, 'EXAM.CREATE', 'exams', $newExamId, 'Created exam: ' . $exam_title . ' type: ' . $exam_type . ' section: ' . $section_id);

        header('Location: exams.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM exams WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();

        if ($current) {
            $newStatus = $current === 'published' ? 'scheduled' : 'published';
            $update = $pdo->prepare("UPDATE exams SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $id]);
            logAudit($pdo, $currentUserId, 'EXAM.TOGGLE_STATUS', 'exams', $id, 'Status changed from ' . $current . ' to ' . $newStatus);
        }
    }
    header('Location: exams.php');
    exit;
}

$examStmt = $pdo->query("SELECT exams.*, sections.section_number, semesters.semester_name, courses.course_code, courses.course_name_th FROM exams JOIN sections ON sections.id = exams.section_id JOIN semesters ON semesters.id = sections.semester_id JOIN courses ON courses.id = sections.course_id ORDER BY exams.exam_date DESC, exams.start_time ASC");
$exams = $examStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('exam_office') ?></div>
            <div class="hero-title"><?= __('manage_exams') ?></div>
            <div class="hero-desc"><?= __('manage_exams_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('exam_list') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('course') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('time') ?></th>
                                <th><?= __('exam_room') ?></th>
                                <th><?= __('max_score') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('manage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($exams) === 0): ?>
                                <tr><td colspan="8"><?= __('no_exam_records') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($exam['exam_date']) ?></td>
                                    <td>
                                        <span class="mono"><?= htmlspecialchars($exam['course_code']) ?></span>
                                        <div style="font-size:12px;color:#8a7c5e;">
                                            <?= htmlspecialchars($exam['course_name_th']) ?> · <?= __('sec') ?> <?= htmlspecialchars($exam['section_number']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($exam['exam_type']) ?></td>
                                    <td class="mono"><?= htmlspecialchars(substr($exam['start_time'] ?? '', 0, 5)) ?> - <?= htmlspecialchars(substr($exam['end_time'] ?? '', 0, 5)) ?></td>
                                    <td><?= htmlspecialchars($exam['room_name'] ?? '-') ?></td>
                                    <td class="mono"><?= number_format((float)$exam['max_score'], 2) ?></td>
                                    <td>
                                        <?php if ($exam['status'] === 'published'): ?>
                                            <span class="badge badge-green"><?= __('published') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-blue"><?= htmlspecialchars($exam['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$exam['id'] ?>"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('publish_schedule') ?></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('add_exam') ?></h3>
                <div class="card">
                    <form method="POST" action="exams.php">
                        <?= csrf_field() ?>
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('section') ?></label>
                            <select name="section_id" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value=""><?= __('select_section') ?></option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= (int)$section['id'] ?>">
                                        <?= htmlspecialchars($section['semester_name']) ?> · <?= htmlspecialchars($section['course_code']) ?> <?= __('sec') ?> <?= htmlspecialchars($section['section_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('exam_type_label') ?></label>
                                <select name="exam_type" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                    <option value="midterm"><?= __('exam_midterm') ?></option>
                                    <option value="final"><?= __('exam_final') ?></option>
                                    <option value="quiz"><?= __('exam_quiz') ?></option>
                                    <option value="oral"><?= __('exam_oral') ?></option>
                                    <option value="other"><?= __('other_type') ?></option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('max_score') ?></label>
                                <input type="number" step="0.01" name="max_score" value="100" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('exam_title_label') ?></label>
                            <input type="text" name="exam_title" placeholder="<?= __('placeholder_exam_title') ?>" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('exam_date') ?></label>
                                <input type="date" name="exam_date" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
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
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('exam_room') ?></label>
                            <input type="text" name="room_name" placeholder="<?= __('placeholder_exam_room') ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('status') ?></label>
                            <select name="status" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value="scheduled"><?= __('status_scheduled') ?></option>
                                <option value="published"><?= __('published') ?></option>
                                <option value="completed"><?= __('completed') ?></option>
                                <option value="cancelled"><?= __('status_cancelled') ?></option>
                            </select>
                        </div>

                        <button type="submit" class="btn"><?= __('save_exam') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
