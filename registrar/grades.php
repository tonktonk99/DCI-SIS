<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('grade_submission_review');
$crumb = __('office_of_registrar') . ' / ' . __('grade_submission_review');
$message = '';
$currentUserId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $finalGradeId = (int)($_POST['final_grade_id'] ?? 0);

    if ($finalGradeId <= 0) {
        $message = __('grade_not_found');
    } elseif ($action === 'release') {
        $stmt = $pdo->prepare("UPDATE final_grades SET status = 'released', released_at = NOW() WHERE id = ? AND status IN ('submitted', 'returned')");
        $stmt->execute([$finalGradeId]);

        logAudit($pdo, $currentUserId, 'GRADE.RELEASE', 'final_grades', $finalGradeId, 'Registrar released final grade ID ' . $finalGradeId);

        header('Location: grades.php');
        exit;
    } elseif ($action === 'return') {
        $stmt = $pdo->prepare("UPDATE final_grades SET status = 'returned' WHERE id = ? AND status = 'submitted'");
        $stmt->execute([$finalGradeId]);

        logAudit($pdo, $currentUserId, 'GRADE.RETURN', 'final_grades', $finalGradeId, 'Registrar returned final grade ID ' . $finalGradeId);

        header('Location: grades.php');
        exit;
    } elseif ($action === 'lock') {
        $stmt = $pdo->prepare("UPDATE final_grades SET status = 'locked' WHERE id = ? AND status = 'released'");
        $stmt->execute([$finalGradeId]);

        logAudit($pdo, $currentUserId, 'GRADE.LOCK', 'final_grades', $finalGradeId, 'Registrar locked final grade ID ' . $finalGradeId);

        header('Location: grades.php');
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'submitted';
$allowedStatuses = ['submitted', 'returned', 'released', 'locked', 'all'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'submitted';
}

$where = '';
$params = [];
if ($statusFilter !== 'all') {
    $where = "WHERE final_grades.status = ?";
    $params[] = $statusFilter;
}

$sql = "
    SELECT
        final_grades.*,
        students.student_code,
        students.first_name,
        students.last_name,
        courses.course_code,
        courses.course_name_th,
        sections.section_number,
        semesters.semester_name
    FROM final_grades
    JOIN students ON students.id = final_grades.student_id
    JOIN sections ON sections.id = final_grades.section_id
    JOIN courses ON courses.id = sections.course_id
    JOIN semesters ON semesters.id = sections.semester_id
    $where
    ORDER BY final_grades.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM final_grades GROUP BY status");
$statusCounts = [];
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['status']] = (int)$row['total'];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('registrar_workflow') ?></div>
            <div class="hero-title"><?= __('review_release_grades') ?></div>
            <div class="hero-desc"><?= __('review_release_grades_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi"><div class="kpi-label"><?= __('grade_submitted') ?></div><div class="kpi-value"><?= (int)($statusCounts['submitted'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('grade_returned') ?></div><div class="kpi-value"><?= (int)($statusCounts['returned'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('grade_released') ?></div><div class="kpi-value"><?= (int)($statusCounts['released'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('grade_locked') ?></div><div class="kpi-value"><?= (int)($statusCounts['locked'] ?? 0) ?></div></div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
                <h3 class="section-title" style="margin:0;"><?= __('grade_list') ?></h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-light" href="grades.php?status=submitted"><?= __('grade_submitted') ?></a>
                    <a class="btn btn-light" href="grades.php?status=returned"><?= __('grade_returned') ?></a>
                    <a class="btn btn-light" href="grades.php?status=released"><?= __('grade_released') ?></a>
                    <a class="btn btn-light" href="grades.php?status=locked"><?= __('grade_locked') ?></a>
                    <a class="btn btn-light" href="grades.php?status=all"><?= __('all') ?></a>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('students') ?></th>
                        <th><?= __('course') ?></th>
                        <th><?= __('semester') ?></th>
                        <th><?= __('score') ?></th>
                        <th><?= __('grade') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('grade_submitted') ?></th>
                        <th><?= __('grade_released') ?></th>
                        <th><?= __('action') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($grades) === 0): ?>
                        <tr><td colspan="9"><?= __('no_grades_in_status') ?></td></tr>
                    <?php endif; ?>

                    <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td><span class="mono"><?= htmlspecialchars($grade['student_code']) ?></span><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']) ?></div></td>
                            <td><span class="mono"><?= htmlspecialchars($grade['course_code']) ?></span><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($grade['course_name_th']) ?> · <?= __('sec') ?> <?= htmlspecialchars($grade['section_number']) ?></div></td>
                            <td><?= htmlspecialchars($grade['semester_name']) ?></td>
                            <td class="mono"><?= number_format((float)$grade['raw_score'], 2) ?></td>
                            <td class="serif" style="font-size:20px;font-weight:700;color:#1c3a6e;"><?= htmlspecialchars($grade['letter_grade'] ?? '-') ?><div class="mono" style="font-size:11px;color:#8a7c5e;"><?= number_format((float)$grade['grade_point'], 2) ?></div></td>
                            <td>
                                <?php if ($grade['status'] === 'released' || $grade['status'] === 'locked'): ?>
                                    <span class="badge badge-green"><?= htmlspecialchars($grade['status']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-blue"><?= htmlspecialchars($grade['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= htmlspecialchars($grade['submitted_at'] ?? '-') ?></td>
                            <td class="mono"><?= htmlspecialchars($grade['released_at'] ?? '-') ?></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php if ($grade['status'] === 'submitted' || $grade['status'] === 'returned'): ?>
                                        <form method="POST" action="grades.php" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="action" value="release"><input type="hidden" name="final_grade_id" value="<?= (int)$grade['id'] ?>"><button class="btn" style="padding:6px 10px;font-size:11px;" type="submit"><?= __('release') ?></button></form>
                                    <?php endif; ?>
                                    <?php if ($grade['status'] === 'submitted'): ?>
                                        <form method="POST" action="grades.php" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="final_grade_id" value="<?= (int)$grade['id'] ?>"><button class="btn btn-light" style="padding:6px 10px;font-size:11px;" type="submit"><?= __('return_grade') ?></button></form>
                                    <?php endif; ?>
                                    <?php if ($grade['status'] === 'released'): ?>
                                        <form method="POST" action="grades.php" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="action" value="lock"><input type="hidden" name="final_grade_id" value="<?= (int)$grade['id'] ?>"><button class="btn btn-light" style="padding:6px 10px;font-size:11px;" type="submit"><?= __('lock') ?></button></form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
