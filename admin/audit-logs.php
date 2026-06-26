<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('admin');
$user = getUser();

$pageTitle = __('audit_trail');
$crumb = __('administration') . ' / ' . __('audit_trail');

$actionFilter = trim($_GET['action'] ?? '');
$userFilter   = (int)($_GET['user_id'] ?? 0);
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$where  = [];
$params = [];

if ($actionFilter !== '') {
    $where[]  = 'audit_logs.action LIKE ?';
    $params[] = '%' . $actionFilter . '%';
}
if ($userFilter > 0) {
    $where[]  = 'audit_logs.user_id = ?';
    $params[] = $userFilter;
}
if ($dateFrom !== '') {
    $where[]  = 'audit_logs.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[]  = 'audit_logs.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count filtered rows (no JOIN needed — all filter cols are on audit_logs)
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereSql");
$cntStmt->execute($params);
$filteredCount = (int)$cntStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($filteredCount / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

// List query: LIMIT/OFFSET are int-cast, safe to interpolate
$sql = "
    SELECT audit_logs.*, users.username
    FROM audit_logs
    LEFT JOIN users ON users.id = audit_logs.user_id
    $whereSql
    ORDER BY audit_logs.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userStmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Limit top-actions summary to last 30 days — avoids full-table GROUP BY at scale
$topActionsStmt = $pdo->query("SELECT action, COUNT(*) AS total FROM audit_logs WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY action ORDER BY total DESC LIMIT 10");
$topActions = $topActionsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
// Range predicate lets idx_al_created_at work (DATE() wrapping prevents index use)
$todayLogs = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY")->fetchColumn();

// Pagination link params (preserves active filters across pages)
$_pgParams = [];
if ($actionFilter !== '') $_pgParams['action']    = $actionFilter;
if ($userFilter > 0)      $_pgParams['user_id']   = $userFilter;
if ($dateFrom !== '')     $_pgParams['date_from']  = $dateFrom;
if ($dateTo !== '')       $_pgParams['date_to']    = $dateTo;
$_pgQs = $_pgParams ? '&' . http_build_query($_pgParams) : '';
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
                    <div class="kpi-value"><?= number_format($filteredCount) ?></div>
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

                        <div style="flex:1;min-width:160px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('date_from') ?></label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>
                        <div style="flex:1;min-width:160px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('date_to') ?></label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
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
                    <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e8e0cc;flex-wrap:wrap;gap:8px;">
                        <div style="font-size:13px;color:#8a7c5e;">
                            <?= number_format($filteredCount) ?> <?= __('results') ?> &nbsp;&middot;&nbsp; <?= $page ?> / <?= $totalPages ?>
                        </div>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-light" style="padding:5px 10px;font-size:12px;" href="<?= htmlspecialchars('audit-logs.php?page=' . ($page - 1) . $_pgQs) ?>">&laquo; <?= __('prev_page') ?></a>
                            <?php endif; ?>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <a class="btn <?= $p === $page ? '' : 'btn-light' ?>" style="padding:5px 10px;font-size:12px;" href="<?= htmlspecialchars('audit-logs.php?page=' . $p . $_pgQs) ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-light" style="padding:5px 10px;font-size:12px;" href="<?= htmlspecialchars('audit-logs.php?page=' . ($page + 1) . $_pgQs) ?>"><?= __('next_page') ?> &raquo;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
