<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('admin');
$user = getUser();

$pageTitle = __('audit_trail');
$crumb = __('administration') . ' / ' . __('audit_trail');

$actionFilter = trim($_GET['action'] ?? '');
$userFilter = (int)($_GET['user_id'] ?? 0);

$where = [];
$params = [];

if ($actionFilter !== '') {
    $where[] = 'audit_logs.action LIKE ?';
    $params[] = '%' . $actionFilter . '%';
}

if ($userFilter > 0) {
    $where[] = 'audit_logs.user_id = ?';
    $params[] = $userFilter;
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        audit_logs.*,
        users.username
    FROM audit_logs
    LEFT JOIN users ON users.id = audit_logs.user_id
    $whereSql
    ORDER BY audit_logs.id DESC
    LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->query("SELECT action, COUNT(*) AS total FROM audit_logs GROUP BY action ORDER BY total DESC LIMIT 10");
$topActions = $countStmt->fetchAll(PDO::FETCH_ASSOC);

$totalLogs = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$todayLogs = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('system_administration') ?></div>
            <div class="hero-title"><?= __('audit_logs_title') ?></div>
            <div class="hero-desc"><?= __('audit_logs_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('total_logs') ?></div>
                    <div class="kpi-value"><?= number_format((int)$totalLogs) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('today') ?></div>
                    <div class="kpi-value"><?= number_format((int)$todayLogs) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('showing') ?></div>
                    <div class="kpi-value"><?= number_format(count($logs)) ?></div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('audit_log_entries') ?></h3>
                <div class="card">
                    <form method="GET" action="audit-logs.php" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
                        <div style="flex:1;min-width:200px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('action_label') ?></label>
                            <input
                                type="text"
                                name="action"
                                value="<?= htmlspecialchars($actionFilter) ?>"
                                placeholder="<?= __('action_placeholder') ?>"
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                        </div>

                        <div style="flex:1;min-width:200px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('user_label') ?></label>
                            <select name="user_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value="0"><?= __('all_users') ?></option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $userFilter ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn"><?= __('filter') ?></button>
                        <a class="btn btn-light" href="audit-logs.php"><?= __('reset') ?></a>
                    </form>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?= __('time') ?></th>
                                <th><?= __('user_label') ?></th>
                                <th><?= __('action_label') ?></th>
                                <th><?= __('entity') ?></th>
                                <th><?= __('details') ?></th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) === 0): ?>
                                <tr><td colspan="7"><?= __('no_audit_logs') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="mono"><?= (int)$log['id'] ?></td>
                                    <td class="mono"><?= htmlspecialchars($log['created_at'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($log['username'] ?? '-') ?></td>
                                    <td><span class="badge badge-blue"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td>
                                        <?= htmlspecialchars($log['entity_type'] ?? '-') ?>
                                        <?php if (!empty($log['entity_id'])): ?>
                                            <div class="mono" style="font-size:11px;color:#8a7c5e;">ID <?= (int)$log['entity_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:360px;white-space:normal;">
                                        <?= htmlspecialchars($log['details'] ?? '-') ?>
                                    </td>
                                    <td class="mono"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('top_actions') ?></h3>
                <div class="list-card">
                    <?php if (count($topActions) === 0): ?>
                        <div class="list-item"><?= __('no_action_data') ?></div>
                    <?php endif; ?>

                    <?php foreach ($topActions as $action): ?>
                        <div class="list-item">
                            <div style="display:flex;justify-content:space-between;gap:14px;align-items:center;">
                                <div>
                                    <div class="mono" style="color:#1c3a6e;font-weight:600;">
                                        <?= htmlspecialchars($action['action']) ?>
                                    </div>
                                    <div style="font-size:12px;color:#8a7c5e;"><?= __('action_count') ?></div>
                                </div>
                                <div class="serif" style="font-size:24px;font-weight:700;">
                                    <?= number_format((int)$action['total']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="section-title" style="margin-top:28px;"><?= __('note') ?></h3>
                <div class="card">
                    <p style="margin-top:0;color:#5a4f3a;font-size:13px;">
                        <?= __('audit_note') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
