<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('student');
$user = getUser();

$pageTitle = __('document_requests_title');
$crumb = __('document_requests_title');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

if (!$student) {
    $message = __('no_student_profile');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId > 0) {
    $requestType    = input_enum($_POST, 'request_type', ['transcript', 'certificate', 'student_status', 'graduation_certificate', 'other'], '');
    $purpose        = trim($_POST['purpose'] ?? '');
    $deliveryMethod = input_enum($_POST, 'delivery_method', ['pickup', 'email_pdf', 'postal_mail'], '');
    $note = trim($_POST['note'] ?? '');

    if ($requestType === '' || $purpose === '' || $deliveryMethod === '') {
        $message = __('fill_required_fields');
    } else {
        $stmt = $pdo->prepare("INSERT INTO document_requests (requester_user_id, student_id, requester_type, request_type, purpose, delivery_method, note, status, requested_at) VALUES (?, ?, 'student', ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([
            (int)$user['id'],
            $studentId,
            $requestType,
            $purpose,
            $deliveryMethod,
            $note ?: null
        ]);
        $newRequestId = (int)$pdo->lastInsertId();
        logAudit($pdo, (int)$user['id'], 'DOCUMENT_REQUEST.SUBMIT', 'document_requests', $newRequestId, 'Request type: ' . $requestType . ' delivery: ' . $deliveryMethod);
        header('Location: requests.php');
        exit;
    }
}

$requests = [];
if ($studentId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM document_requests WHERE student_id = ? ORDER BY id DESC");
    $stmt->execute([$studentId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('student_services') ?></div>
            <div class="hero-title"><?= __('document_requests_title') ?></div>
            <div class="hero-desc"><?= __('requests_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('my_requests') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('request_date') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('purpose') ?></th>
                                <th><?= __('delivery_method') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('update') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($requests) === 0): ?>
                                <tr><td colspan="6"><?= __('no_document_requests') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($request['requested_at'] ?? '-') ?></td>
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
                                    <td class="mono"><?= htmlspecialchars($request['updated_at'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('create_new_request') ?></h3>
                <div class="card">
                    <form method="POST" action="requests.php">
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('document_type') ?></label>
                            <select name="request_type" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value=""><?= __('select_type') ?></option>
                                <option value="transcript"><?= __('transcript_type') ?></option>
                                <option value="certificate"><?= __('certificate_type') ?></option>
                                <option value="student_status"><?= __('student_status_letter') ?></option>
                                <option value="graduation_certificate"><?= __('graduation_certificate') ?></option>
                                <option value="other"><?= __('other_type') ?></option>
                            </select>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('purpose_label') ?></label>
                            <input type="text" name="purpose" required placeholder="<?= __('purpose_placeholder') ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
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
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
