<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('alumni');
$user = getUser();

$pageTitle = __('alumni_document_request');
$crumb = __('alumni_documents_crumb');
$message = '';
$defaultType = basename($_SERVER['PHP_SELF']) === 'certificate-request.php' ? 'certificate' : 'transcript';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestType    = input_enum($_POST, 'request_type', ['transcript', 'certificate'], $defaultType);
    $purpose        = trim($_POST['purpose'] ?? '');
    $deliveryMethod = input_enum($_POST, 'delivery_method', ['pickup', 'email_pdf', 'postal_mail'], '');
    $note = trim($_POST['note'] ?? '');

    if ($requestType === '' || $purpose === '' || $deliveryMethod === '') {
        $message = __('alumni_fill_request');
    } else {
        $stmt = $pdo->prepare("INSERT INTO document_requests (requester_user_id, requester_type, request_type, purpose, delivery_method, note, status, requested_at) VALUES (?, 'alumni', ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([(int)$user['id'], $requestType, $purpose, $deliveryMethod, $note ?: null]);
        $newRequestId = (int)$pdo->lastInsertId();
        logAudit($pdo, (int)$user['id'], 'DOCUMENT_REQUEST.SUBMIT', 'document_requests', $newRequestId, 'Alumni request type: ' . $requestType . ' delivery: ' . $deliveryMethod);
        header('Location: dashboard.php');
        exit;
    }
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('alumni_services') ?></div>
            <div class="hero-title"><?= $defaultType === 'certificate' ? __('certificate_request') : __('transcript_request') ?></div>
            <div class="hero-desc"><?= __('alumni_request_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
                <input type="hidden" name="request_type" value="<?= htmlspecialchars($defaultType) ?>">

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('purpose_label') ?></label>
                    <input type="text" name="purpose" required placeholder="<?= __('alumni_purpose_placeholder') ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('delivery_method_label') ?></label>
                    <select name="delivery_method" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        <option value=""><?= __('select_delivery') ?></option>
                        <option value="pickup"><?= __('pickup') ?></option>
                        <option value="email_pdf"><?= __('email_pdf') ?></option>
                        <option value="postal_mail"><?= __('postal_mail') ?></option>
                    </select>
                </div>

                <div style="margin-bottom:18px;">
                    <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('note') ?></label>
                    <textarea name="note" rows="4" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></textarea>
                </div>

                <button type="submit" class="btn"><?= __('submit_request') ?></button>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
