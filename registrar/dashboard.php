<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('registrar_home');
$crumb = __('office_of_registrar');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $petitionId = (int)($_POST['petition_id'] ?? 0);
    $action = $_POST['petition_action'] ?? '';
    $note = trim($_POST['reviewer_note'] ?? '');

    if ($petitionId > 0 && in_array($action, ['approved', 'denied'])) {
        $pdo->prepare("UPDATE registrar_petitions SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reviewer_note = ? WHERE id = ? AND status = 'pending'")
            ->execute([$action, (int)$user['id'], $note, $petitionId]);
        logAudit($pdo, (int)$user['id'], $action === 'approved' ? 'PETITION.APPROVED' : 'PETITION.DENIED', 'registrar_petitions', $petitionId);
        header('Location: dashboard.php');
        exit;
    }
}

$currentSemester = $pdo->query("SELECT semester_name FROM semesters WHERE is_current = 1 LIMIT 1")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeSections = (int)$pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();

$avgGpa = $pdo->query("SELECT ROUND(AVG(cumulative_gpa), 2) FROM students WHERE cumulative_gpa > 0")->fetchColumn();
$avgGpa = $avgGpa ?: '—';

$releasedGrades = (int)$pdo->query("SELECT COUNT(*) FROM final_grades WHERE status IN ('released','locked')")->fetchColumn();
$totalGrades = (int)$pdo->query("SELECT COUNT(*) FROM final_grades")->fetchColumn();
$gradesPct = $totalGrades > 0 ? round($releasedGrades / $totalGrades * 100) : 0;
$pendingGradesPct = 100 - $gradesPct;

$pendingGrades = (int)$pdo->query("SELECT COUNT(*) FROM final_grades WHERE status = 'submitted'")->fetchColumn();
$pendingDocs = (int)$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('pending','processing')")->fetchColumn();

$enrollByProg = $pdo->query("
    SELECT p.program_name_th, COUNT(s.id) AS cnt
    FROM students s
    JOIN programs p ON p.id = s.program_id
    GROUP BY p.id, p.program_name_th
    ORDER BY cnt DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$progColors = ['#1c3a6e','#2d5f4a','#c89028','#5a4f3a','#1a4a7a','#3a2f20'];

$pendingPetitions = $pdo->query("
    SELECT rp.*, s.student_code, s.first_name, s.last_name
    FROM registrar_petitions rp
    LEFT JOIN students s ON s.id = rp.student_id
    WHERE rp.status = 'pending'
    ORDER BY rp.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$petitionTypeKeys = [
    'late_withdrawal' => 'late_withdrawal',
    'credit_overload' => 'credit_overload',
    'program_transfer' => 'program_transfer',
    'grade_change' => 'grade_amendment',
    'leave_of_absence' => 'leave_of_absence',
];

$auditLog = $pdo->query("
    SELECT al.*, u.username
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$recentSections = $pdo->query("
    SELECT sections.*, semesters.semester_name, courses.course_code, courses.course_name_th
    FROM sections
    JOIN semesters ON semesters.id = sections.semester_id
    JOIN courses ON courses.id = sections.course_id
    ORDER BY sections.id DESC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('office_of_registrar') ?></div>
            <div class="hero-title"><?= __('registrar_dashboard') ?></div>
            <div class="hero-desc"><?= __('current_term') ?>: <?= htmlspecialchars($currentSemester ?: __('not_configured')) ?></div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('enrolled_students') ?></div>
                    <div class="kpi-value"><?= number_format($totalStudents) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('active_sections') ?></div>
                    <div class="kpi-value"><?= number_format($activeSections) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('average_gpa') ?></div>
                    <div class="kpi-value"><?= htmlspecialchars($avgGpa) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('grades_released') ?></div>
                    <div class="kpi-value"><?= $gradesPct ?>%
                        <?php if ($pendingGradesPct > 0): ?>
                            <span style="font-size:12px;color:var(--gold);">(<?= $pendingGradesPct ?>% <?= __('pending_pct') ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($pendingPetitions) > 0): ?>
        <h3 class="section-title"><?= __('pending_actions') ?> <span style="font-size:14px;color:var(--crimson);font-weight:700;">(<?= count($pendingPetitions) ?>)</span></h3>
        <div class="list-card" style="margin-bottom:24px;">
            <?php foreach ($pendingPetitions as $pet):
                $typeKey = $petitionTypeKeys[$pet['petition_type']] ?? $pet['petition_type'];
                $typeLabel = __($typeKey);
                $badgeColor = match($pet['petition_type']) {
                    'late_withdrawal' => 'badge-gold',
                    'credit_overload' => 'badge-blue',
                    'program_transfer' => 'badge-green',
                    'grade_change' => 'badge-red',
                    default => 'badge-blue',
                };
            ?>
                <div class="list-item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:250px;">
                        <span class="badge <?= $badgeColor ?>"><?= htmlspecialchars($typeLabel) ?></span>
                        <div style="margin-top:8px;font-weight:600;font-size:14px;"><?= htmlspecialchars($pet['title']) ?></div>
                        <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                            <?= htmlspecialchars($pet['student_code'] ?? '') ?> · <?= htmlspecialchars(trim(($pet['first_name'] ?? '') . ' ' . ($pet['last_name'] ?? ''))) ?>
                            · <?= htmlspecialchars($pet['created_at']) ?>
                        </div>
                        <?php if ($pet['details']): ?>
                            <div style="font-size:12px;color:#5a4f3a;margin-top:6px;line-height:1.6;"><?= htmlspecialchars($pet['details']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0;">
                        <form method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="petition_id" value="<?= (int)$pet['id'] ?>">
                            <input type="hidden" name="petition_action" value="approved">
                            <input type="hidden" name="reviewer_note" value="">
                            <button type="submit" class="btn" style="padding:6px 14px;font-size:12px;background:var(--green);" onclick="return confirm('<?= __('confirm_approve') ?>')"><?= __('approve') ?></button>
                        </form>
                        <form method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="petition_id" value="<?= (int)$pet['id'] ?>">
                            <input type="hidden" name="petition_action" value="denied">
                            <input type="hidden" name="reviewer_note" value="">
                            <button type="submit" class="btn btn-light" style="padding:6px 14px;font-size:12px;color:var(--crimson);" onclick="return confirm('<?= __('confirm_deny') ?>')"><?= __('deny') ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('enrollment_by_program') ?></h3>
                <div class="card">
                    <?php if (count($enrollByProg) === 0): ?>
                        <div style="color:var(--muted);"><?= __('no_enrollment_data') ?></div>
                    <?php endif; ?>
                    <?php
                    $maxCount = 1;
                    foreach ($enrollByProg as $ep) { if ((int)$ep['cnt'] > $maxCount) $maxCount = (int)$ep['cnt']; }
                    ?>
                    <?php foreach ($enrollByProg as $i => $ep):
                        $pct = round((int)$ep['cnt'] / $maxCount * 100);
                        $color = $progColors[$i % count($progColors)];
                    ?>
                        <div style="margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                                <span><?= htmlspecialchars($ep['program_name_th']) ?></span>
                                <span class="mono" style="font-weight:600;"><?= number_format((int)$ep['cnt']) ?></span>
                            </div>
                            <div style="background:#ece4d2;height:8px;border-radius:4px;overflow:hidden;">
                                <div style="background:<?= $color ?>;height:100%;width:<?= $pct ?>%;border-radius:4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('recent_sections') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr><th><?= __('term') ?></th><th><?= __('course') ?></th><th><?= __('sec') ?></th><th><?= __('enrollment') ?></th><th><?= __('status') ?></th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentSections) === 0): ?>
                                <tr><td colspan="5"><?= __('no_sections') ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentSections as $sec): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sec['semester_name']) ?></td>
                                    <td>
                                        <span class="mono"><?= htmlspecialchars($sec['course_code']) ?></span>
                                        <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($sec['course_name_th']) ?></div>
                                    </td>
                                    <td class="mono"><?= htmlspecialchars($sec['section_number']) ?></td>
                                    <td class="mono"><?= (int)$sec['enrolled_count'] ?> / <?= (int)$sec['capacity'] ?></td>
                                    <td><span class="badge badge-green"><?= htmlspecialchars($sec['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('recent_activity') ?></h3>
                <div class="list-card">
                    <?php if (count($auditLog) === 0): ?>
                        <div class="list-item" style="color:var(--muted);"><?= __('no_recent_activity') ?></div>
                    <?php endif; ?>
                    <?php foreach ($auditLog as $log): ?>
                        <div class="list-item" style="font-size:12px;">
                            <div style="display:flex;justify-content:space-between;gap:8px;">
                                <span class="mono" style="color:var(--muted);font-size:11px;min-width:60px;"><?= htmlspecialchars(substr($log['created_at'] ?? '', 11, 5)) ?></span>
                                <span style="flex:1;">
                                    <strong><?= htmlspecialchars($log['username'] ?? 'system') ?></strong>
                                    · <?= htmlspecialchars($log['action'] ?? '') ?>
                                    <?php if ($log['entity_type']): ?>
                                        <span style="color:var(--muted);">→ <?= htmlspecialchars($log['entity_type']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('quick_actions') ?></h3>
                <div class="card">
                    <p><a class="btn" href="<?= APP_BASE ?>/registrar/grades.php" style="width:100%;text-align:center;display:block;"><?= __('grade_submission_review') ?></a></p>
                    <p><a class="btn btn-light" href="<?= APP_BASE ?>/registrar/document-requests.php" style="width:100%;text-align:center;display:block;"><?= __('document_services') ?></a></p>
                    <p><a class="btn btn-light" href="<?= APP_BASE ?>/registrar/sections.php" style="width:100%;text-align:center;display:block;"><?= __('manage_sections') ?></a></p>
                    <p><a class="btn btn-light" href="<?= APP_BASE ?>/registrar/students.php" style="width:100%;text-align:center;display:block;"><?= __('student_records') ?></a></p>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('pending_items') ?></h3>
                <div class="card">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;">
                        <tr>
                            <td style="padding:8px 0;color:var(--muted);"><?= __('petitions_awaiting') ?></td>
                            <td style="padding:8px 0;text-align:right;font-weight:700;color:<?= count($pendingPetitions) > 0 ? 'var(--crimson)' : 'var(--green)' ?>;" class="mono"><?= count($pendingPetitions) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:var(--muted);"><?= __('grade_submissions_pending') ?></td>
                            <td style="padding:8px 0;text-align:right;font-weight:700;" class="mono"><?= number_format($pendingGrades) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:var(--muted);"><?= __('document_requests_pending') ?></td>
                            <td style="padding:8px 0;text-align:right;font-weight:700;" class="mono"><?= number_format($pendingDocs) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
