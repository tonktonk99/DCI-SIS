<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'admin') {
    die('Access denied');
}

$pageTitle = __('admin_home');
$crumb = __('administration');

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$activeSections = (int)$pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();
$currentSemester = $pdo->query("SELECT semester_name FROM semesters WHERE is_current = 1 LIMIT 1")->fetchColumn();

$usersByRole = $pdo->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);

$enrollByProg = $pdo->query("
    SELECT p.program_code, p.program_name_th, COUNT(s.id) AS cnt
    FROM students s
    JOIN programs p ON p.id = s.program_id
    GROUP BY p.id, p.program_code, p.program_name_th
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$progColors = ['#1c3a6e','#2d5f4a','#c89028','#8b4a6b','#5a4f3a','#a04a14','#1a4a7a','#3a2f20'];

$gpaRanges = [
    ['label' => '3.50–4.00', 'min' => 3.50, 'max' => 4.01, 'color' => '#2d5f4a'],
    ['label' => '3.00–3.49', 'min' => 3.00, 'max' => 3.50, 'color' => '#1c3a6e'],
    ['label' => '2.50–2.99', 'min' => 2.50, 'max' => 3.00, 'color' => '#c89028'],
    ['label' => '2.00–2.49', 'min' => 2.00, 'max' => 2.50, 'color' => '#5a4f3a'],
    ['label' => __('below') . ' 2.00', 'min' => 0, 'max' => 2.00, 'color' => '#A51C30'],
];
$gpaDist = [];
foreach ($gpaRanges as $r) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE cumulative_gpa >= ? AND cumulative_gpa < ? AND cumulative_gpa > 0");
    $stmt->execute([$r['min'], $r['max']]);
    $gpaDist[] = array_merge($r, ['cnt' => (int)$stmt->fetchColumn()]);
}
$gpaMax = 1;
foreach ($gpaDist as $g) { if ($g['cnt'] > $gpaMax) $gpaMax = $g['cnt']; }

$auditLog = $pdo->query("
    SELECT al.*, u.username, u.role AS user_role
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

$recentStudents = $pdo->query("
    SELECT s.student_code, s.first_name, s.last_name, p.program_code, s.created_at
    FROM students s
    LEFT JOIN programs p ON p.id = s.program_id
    ORDER BY s.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('administration') ?></div>
            <div class="hero-title"><?= __('system_overview') ?></div>
            <div class="hero-desc">
                <?= __('welcome') ?>, <?= htmlspecialchars($user['username']) ?>
                · <?= __('current_term') ?>: <?= htmlspecialchars($currentSemester ?: __('not_configured')) ?>
            </div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('user_accounts') ?></div>
                    <div class="kpi-value"><?= number_format($totalUsers) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('student_records') ?></div>
                    <div class="kpi-value"><?= number_format($totalStudents) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('faculty_members') ?></div>
                    <div class="kpi-value"><?= number_format($totalStaff) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('course_catalog') ?></div>
                    <div class="kpi-value"><?= number_format($totalCourses) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('active_sections') ?></div>
                    <div class="kpi-value"><?= number_format($activeSections) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('system_status') ?></div>
                    <div class="kpi-value" style="color:var(--green);"><?= __('operational') ?></div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('enrollment_by_program') ?></h3>
                <div class="card">
                    <?php if (count($enrollByProg) === 0): ?>
                        <div style="color:var(--muted);padding:20px 0;text-align:center;"><?= __('no_enrollment_data') ?></div>
                    <?php else: ?>
                        <?php
                        $epMax = 1;
                        foreach ($enrollByProg as $ep) { if ((int)$ep['cnt'] > $epMax) $epMax = (int)$ep['cnt']; }
                        $epTotal = 0;
                        foreach ($enrollByProg as $ep) $epTotal += (int)$ep['cnt'];
                        ?>
                        <div style="display:flex;gap:20px;align-items:center;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--line-soft);">
                            <div style="text-align:center;">
                                <div class="serif" style="font-size:36px;font-weight:700;"><?= number_format($epTotal) ?></div>
                                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.1em;"><?= __('total_students') ?></div>
                            </div>
                            <div style="flex:1;display:flex;height:14px;border-radius:7px;overflow:hidden;">
                                <?php foreach ($enrollByProg as $i => $ep):
                                    $pct = round((int)$ep['cnt'] / $epTotal * 100, 1);
                                    $color = $progColors[$i % count($progColors)];
                                ?>
                                    <div style="width:<?= $pct ?>%;background:<?= $color ?>;height:100%;" title="<?= htmlspecialchars($ep['program_name_th']) ?>: <?= $ep['cnt'] ?> (<?= $pct ?>%)"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php foreach ($enrollByProg as $i => $ep):
                            $pct = round((int)$ep['cnt'] / $epMax * 100);
                            $sharePct = round((int)$ep['cnt'] / $epTotal * 100);
                            $color = $progColors[$i % count($progColors)];
                        ?>
                            <div style="margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                                    <span>
                                        <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= $color ?>;margin-right:6px;vertical-align:middle;"></span>
                                        <?= htmlspecialchars($ep['program_name_th']) ?>
                                        <span style="color:var(--muted);font-size:11px;margin-left:4px;"><?= $sharePct ?>%</span>
                                    </span>
                                    <span class="mono" style="font-weight:600;"><?= number_format((int)$ep['cnt']) ?></span>
                                </div>
                                <div style="background:#ece4d2;height:6px;border-radius:3px;overflow:hidden;">
                                    <div style="background:<?= $color ?>;height:100%;width:<?= $pct ?>%;border-radius:3px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('gpa_distribution') ?></h3>
                <div class="card">
                    <?php foreach ($gpaDist as $g): ?>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                            <span class="mono" style="min-width:72px;font-size:12px;color:var(--muted);"><?= $g['label'] ?></span>
                            <div style="flex:1;background:#ece4d2;height:8px;border-radius:4px;overflow:hidden;">
                                <div style="background:<?= $g['color'] ?>;height:100%;width:<?= $gpaMax > 0 ? round($g['cnt'] / $gpaMax * 100) : 0 ?>%;border-radius:4px;"></div>
                            </div>
                            <span class="mono" style="min-width:30px;text-align:right;font-size:12px;font-weight:600;"><?= $g['cnt'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('users_by_role') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead><tr><th><?= __('role') ?></th><th style="text-align:right;"><?= __('count') ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($usersByRole as $ur): ?>
                                <tr>
                                    <td style="text-transform:capitalize;"><?= htmlspecialchars($ur['role']) ?></td>
                                    <td class="mono" style="text-align:right;font-weight:600;"><?= number_format((int)$ur['cnt']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('audit_trail') ?></h3>
                <div class="list-card">
                    <?php if (count($auditLog) === 0): ?>
                        <div class="list-item" style="color:var(--muted);"><?= __('no_activity_recorded') ?></div>
                    <?php endif; ?>
                    <?php foreach ($auditLog as $log): ?>
                        <div class="list-item" style="font-size:12px;">
                            <div style="display:flex;gap:10px;align-items:flex-start;">
                                <span class="mono" style="color:var(--muted);font-size:10px;min-width:44px;padding-top:2px;">
                                    <?= htmlspecialchars(substr($log['created_at'] ?? '', 11, 5)) ?>
                                </span>
                                <div style="flex:1;">
                                    <strong><?= htmlspecialchars($log['username'] ?? 'system') ?></strong>
                                    <span style="color:var(--muted);">(<?= htmlspecialchars($log['user_role'] ?? '—') ?>)</span>
                                    <div style="margin-top:2px;"><?= htmlspecialchars($log['action'] ?? '') ?>
                                        <?php if ($log['entity_type']): ?>
                                            → <span style="color:var(--crimson);"><?= htmlspecialchars($log['entity_type']) ?></span>
                                            <?php if ($log['entity_id']): ?>#<?= (int)$log['entity_id'] ?><?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($auditLog) > 0): ?>
                        <div class="list-item" style="text-align:center;">
                            <a href="/dci-sis/admin/audit-logs.php" style="font-size:12px;color:var(--crimson);text-decoration:none;font-weight:600;"><?= __('view_full_audit') ?></a>
                        </div>
                    <?php endif; ?>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('recent_students') ?></h3>
                <div class="list-card">
                    <?php if (count($recentStudents) === 0): ?>
                        <div class="list-item" style="color:var(--muted);"><?= __('no_student_records') ?></div>
                    <?php endif; ?>
                    <?php foreach ($recentStudents as $rs): ?>
                        <div class="list-item">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-weight:600;"><?= htmlspecialchars(trim($rs['first_name'] . ' ' . $rs['last_name'])) ?></div>
                                    <div style="font-size:11px;color:var(--muted);">
                                        <span class="mono"><?= htmlspecialchars($rs['student_code'] ?? '') ?></span>
                                        · <?= htmlspecialchars($rs['program_code'] ?? '—') ?>
                                    </div>
                                </div>
                                <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars(substr($rs['created_at'] ?? '', 0, 10)) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:24px;"><?= __('quick_actions') ?></h3>
                <div class="card">
                    <p><a class="btn" href="/dci-sis/admin/users.php" style="width:100%;text-align:center;display:block;"><?= __('manage_users') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/admin/roles.php" style="width:100%;text-align:center;display:block;"><?= __('roles_permissions') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/admin/settings.php" style="width:100%;text-align:center;display:block;"><?= __('system_configuration') ?></a></p>
                    <p><a class="btn btn-light" href="/dci-sis/admin/audit-logs.php" style="width:100%;text-align:center;display:block;"><?= __('full_audit_trail') ?></a></p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
