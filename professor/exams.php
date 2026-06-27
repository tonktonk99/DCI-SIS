<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('professor');
$user = getUser();

$pageTitle = __('exam_scores');
$crumb = __('exam_scores');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    $message = __('no_professor_profile');
}

$staffId = $staff ? (int)$staff['id'] : 0;
$selectedExamId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);

$exams = [];
if ($staffId > 0) {
    $examStmt = $pdo->prepare("SELECT exams.*, sections.section_number, courses.course_code, courses.course_name_th, semesters.semester_name FROM exams JOIN sections ON sections.id = exams.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id JOIN section_instructors ON section_instructors.section_id = sections.id WHERE section_instructors.staff_id = ? ORDER BY exams.exam_date DESC, exams.start_time ASC");
    $examStmt->execute([$staffId]);
    $exams = $examStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_scores' && $selectedExamId > 0 && $staffId > 0) {
    $scores = $_POST['scores'] ?? [];

    try {
        $pdo->beginTransaction();

        $verifyStmt = $pdo->prepare("SELECT exams.id FROM exams JOIN sections ON sections.id = exams.section_id JOIN section_instructors ON section_instructors.section_id = sections.id WHERE exams.id = ? AND section_instructors.staff_id = ? LIMIT 1");
        $verifyStmt->execute([$selectedExamId, $staffId]);
        $allowed = $verifyStmt->fetchColumn();

        if (!$allowed) {
            throw new Exception(__('no_permission_exam'));
        }

        foreach ($scores as $studentId => $scoreValue) {
            $studentId = (int)$studentId;
            $scoreValue = trim((string)$scoreValue);

            if ($studentId <= 0 || $scoreValue === '') {
                continue;
            }

            $score = (float)$scoreValue;

            $existingStmt = $pdo->prepare("SELECT id FROM exam_scores WHERE exam_id = ? AND student_id = ? LIMIT 1");
            $existingStmt->execute([$selectedExamId, $studentId]);
            $existingId = $existingStmt->fetchColumn();

            if ($existingId) {
                $updateStmt = $pdo->prepare("UPDATE exam_scores SET score = ?, graded_at = NOW() WHERE id = ?");
                $updateStmt->execute([$score, (int)$existingId]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO exam_scores (exam_id, student_id, score, graded_at) VALUES (?, ?, ?, NOW())");
                $insertStmt->execute([$selectedExamId, $studentId, $score]);
            }
        }

        $pdo->commit();
        logAudit($pdo, (int)$user['id'], 'EXAM.SAVE_SCORES', 'exams', $selectedExamId, 'Saved exam scores for exam: ' . $selectedExamId);
        header('Location: exams.php?exam_id=' . $selectedExamId);
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[exams] save_scores: ' . $e->getMessage());
        $message = __('unexpected_error');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $e->getMessage();
    }
}

$selectedExam = null;
$roster = [];

if ($selectedExamId > 0 && $staffId > 0) {
    $selectedStmt = $pdo->prepare("SELECT exams.*, sections.section_number, courses.course_code, courses.course_name_th, semesters.semester_name FROM exams JOIN sections ON sections.id = exams.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id JOIN section_instructors ON section_instructors.section_id = sections.id WHERE exams.id = ? AND section_instructors.staff_id = ? LIMIT 1");
    $selectedStmt->execute([$selectedExamId, $staffId]);
    $selectedExam = $selectedStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedExam) {
        $rosterStmt = $pdo->prepare("SELECT students.id AS student_id, students.student_code, students.first_name, students.last_name, exam_scores.score FROM enrollments JOIN students ON students.id = enrollments.student_id LEFT JOIN exam_scores ON exam_scores.exam_id = ? AND exam_scores.student_id = students.id WHERE enrollments.section_id = ? AND enrollments.status = 'enrolled' ORDER BY students.student_code ASC");
        $rosterStmt->execute([$selectedExamId, (int)$selectedExam['section_id']]);
        $roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('teaching_workspace') ?></div>
            <div class="hero-title"><?= __('enter_exam_scores') ?></div>
            <div class="hero-desc"><?= __('prof_exams_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('my_exams') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('date') ?></th>
                                <th><?= __('course') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('max_score') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('select_action') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($exams) === 0): ?>
                                <tr><td colspan="6"><?= __('no_exams_assigned') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($exam['exam_date']) ?></td>
                                    <td>
                                        <span class="mono"><?= htmlspecialchars($exam['course_code']) ?></span>
                                        <div style="font-size:12px;color:#8a7c5e;">
                                            <?= htmlspecialchars($exam['course_name_th']) ?> · Sec <?= htmlspecialchars($exam['section_number']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($exam['exam_type']) ?></td>
                                    <td class="mono"><?= number_format((float)$exam['max_score'], 2) ?></td>
                                    <td>
                                        <?php if ($exam['status'] === 'completed'): ?>
                                            <span class="badge badge-green"><?= __('completed') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-blue"><?= htmlspecialchars($exam['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-light" style="padding:6px 10px;font-size:11px;" href="exams.php?exam_id=<?= (int)$exam['id'] ?>"><?= __('open_action') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('exam_scores') ?></h3>
                <div class="card">
                    <?php if (!$selectedExam): ?>
                        <p><?= __('select_exam_prompt') ?></p>
                    <?php else: ?>
                        <div style="margin-bottom:14px;">
                            <div class="mono" style="color:#1c3a6e;font-weight:600;">
                                <?= htmlspecialchars($selectedExam['course_code']) ?> · Sec <?= htmlspecialchars($selectedExam['section_number']) ?>
                            </div>
                            <div class="serif" style="font-size:20px;font-weight:600;">
                                <?= htmlspecialchars($selectedExam['exam_title']) ?>
                            </div>
                            <div style="font-size:12px;color:#5a4f3a;">
                                <?= htmlspecialchars($selectedExam['exam_date']) ?> · Max <?= number_format((float)$selectedExam['max_score'], 2) ?>
                            </div>
                        </div>

                        <form method="POST" action="exams.php">
                            <input type="hidden" name="action" value="save_scores">
                            <input type="hidden" name="exam_id" value="<?= (int)$selectedExam['id'] ?>">

                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= __('code') ?></th>
                                        <th><?= __('students') ?></th>
                                        <th><?= __('score') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($roster) === 0): ?>
                                        <tr><td colspan="3"><?= __('no_students_in_section') ?></td></tr>
                                    <?php endif; ?>

                                    <?php foreach ($roster as $student): ?>
                                        <tr>
                                            <td class="mono"><?= htmlspecialchars($student['student_code']) ?></td>
                                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="<?= (float)$selectedExam['max_score'] ?>"
                                                    name="scores[<?= (int)$student['student_id'] ?>]"
                                                    value="<?= $student['score'] !== null ? htmlspecialchars($student['score']) : '' ?>"
                                                    style="width:100%;padding:8px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                                                >
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <button type="submit" class="btn" style="margin-top:14px;"><?= __('save_exam_scores') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
