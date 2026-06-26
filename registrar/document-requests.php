<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('manage_document_requests');
$crumb = __('office_of_registrar') . ' / ' . __('document_services');
$message = '';
$currentUserId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $statusMap = [
        'process' => 'processing',
        'complete' => 'completed',
        'reject' => 'rejected',
        'cancel' => 'cancelled',
    ];

    $auditMap = [
        'process' => 'DOCUMENT.PROCESS',
        'complete' => 'DOCUMENT.COMPLETE',
        'reject' => 'DOCUMENT.REJECT',
        'cancel' => 'DOCUMENT.CANCEL',
    ];

    if ($requestId > 0 && isset($statusMap[$action])) {
        $stmt = $pdo->prepare("UPDATE document_requests SET status = ?, processed_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$statusMap[$action], $currentUserId, $requestId]);

        logAudit($pdo, $currentUserId, $auditMap[$action], 'document_requests', $requestId, 'Document request status changed to ' . $statusMap[$action]);

        header('Location: document-requests.php');
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'processing', 'completed', 'rejected', 'cancelled', 'all'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'pending';
}

$where = '';
$params = [];
if ($statusFilter !== 'all') {
    $where = 'WHERE document_requests.status = ?';
    $params[] = $statusFilter;
}

$sql = "
    SELECT document_requests.*, users.username, students.student_code, students.first_name, students.last_name
    FROM document_requests
    LEFT JOIN users ON users.id = document_requests.requester_user_id
    LEFT JOIN students ON students.id = document_requests.student_id
    $where
    ORDER BY document_requests.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM document_requests GROUP BY status");
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
            <div class="hero-kicker"><?= __('registrar_services') ?></div>
            <div class="hero-title"><?= __('manage_document_requests') ?></div>
            <div class="hero-desc"><?= __('manage_document_requests_desc') ?></div>
            <div class="kpi-row">
                <div class="kpi"><div class="kpi-label"><?= __('pending') ?></div><div class="kpi-value"><?= (int)($statusCounts['pending'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('processing') ?></div><div class="kpi-value"><?= (int)($statusCounts['processing'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('completed') ?></div><div class="kpi-value"><?= (int)($statusCounts['completed'] ?? 0) ?></div></div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
                <h3 class="section-title" style="margin:0;"><?= __('request_list') ?></h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-light" href="document-requests.php?status=pending"><?= __('pending') ?></a>
                    <a class="btn btn-light" href="document-requests.php?status=processing"><?= __('processing') ?></a>
                    <a class="btn btn-light" href="document-requests.php?status=completed"><?= __('completed') ?></a>
                    <a class="btn btn-light" href="document-requests.php?status=all"><?= __('all') ?></a>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr><th><?= __('request_date') ?></th><th><?= __('requester') ?></th><th><?= __('request_type') ?></th><th><?= __('purpose') ?></th><th><?= __('delivery_method') ?></th><th><?= __('status') ?></th><th><?= __('manage') ?></th></tr>
                </thead>
                <tbody>
                    <?php if (count($requests) === 0): ?>
                        <tr><td colspan="7"><?= __('no_requests_in_status') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td class="mono"><?= htmlspecialchars($request['requested_at'] ?? '-') ?></td>
                            <td><span class="mono"><?= htmlspecialchars($request['student_code'] ?? '-') ?></span><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars(trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')) ?: ($request['username'] ?? '-')) ?></div></td>
                            <td><?= htmlspecialchars($request['request_type']) ?></td>
                            <td><?= htmlspecialchars($request['purpose']) ?></td>
                            <td><?= htmlspecialchars($request['delivery_method']) ?></td>
                            <td>
                                <?php if ($request['status'] === 'completed'): ?>
                                    <span class="badge badge-green"><?= __('completed') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-blue"><?= htmlspecialchars($request['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <form method="POST" action="document-requests.php" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="action" value="process"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('process_action') ?></button></form>
                                    <form method="POST" action="document-requests.php" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="action" value="complete"><button type="submit" class="btn" style="padding:6px 10px;font-size:11px;"><?= __('complete_action') ?></button></form>
                                    <form method="POST" action="document-requests.php" style="margin:0;"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="action" value="reject"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('reject_action') ?></button></form>
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
