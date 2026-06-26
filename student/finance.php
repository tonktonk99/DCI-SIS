<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'student') {
    die('Access denied');
}

$pageTitle = __('student_financial');
$crumb = __('student_financial');

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$invoices = [];
$payments = [];
$holds = [];
$totalOwed = 0;
$totalPaid = 0;

if ($studentId > 0) {
    $stmt = $pdo->prepare("SELECT si.*, s.semester_name FROM student_invoices si LEFT JOIN semesters s ON s.id = si.semester_id WHERE si.student_id = ? ORDER BY si.due_date ASC");
    $stmt->execute([$studentId]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invoices as $inv) {
        $totalOwed += (float)$inv['amount'];
        $totalPaid += (float)$inv['paid_amount'];
    }

    $stmt = $pdo->prepare("SELECT sp.*, si.description AS invoice_desc FROM student_payments sp LEFT JOIN student_invoices si ON si.id = sp.invoice_id WHERE sp.student_id = ? ORDER BY sp.paid_at DESC");
    $stmt->execute([$studentId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM student_holds WHERE student_id = ? AND is_active = 1 ORDER BY placed_at DESC");
    $stmt->execute([$studentId]);
    $holds = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$balance = $totalOwed - $totalPaid;
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('student_financial') ?></div>
            <div class="hero-title"><?= __('financial_account') ?></div>
            <div class="hero-desc"><?= htmlspecialchars($student ? $student['first_name'] . ' ' . $student['last_name'] : 'Student') ?></div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('total_charges') ?></div>
                    <div class="kpi-value"><?= number_format($totalOwed, 2) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('payments_applied') ?></div>
                    <div class="kpi-value" style="color:#5a8a3f;"><?= number_format($totalPaid, 2) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('outstanding_balance') ?></div>
                    <div class="kpi-value" style="color:<?= $balance > 0 ? '#A51C30' : '#5a8a3f' ?>;"><?= number_format($balance, 2) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('active_holds') ?></div>
                    <div class="kpi-value" style="color:<?= count($holds) > 0 ? '#A51C30' : '#5a8a3f' ?>;"><?= count($holds) ?></div>
                </div>
            </div>
        </div>

        <?php if (count($holds) > 0): ?>
        <h3 class="section-title"><?= __('active_holds') ?></h3>
        <div class="list-card">
            <?php foreach ($holds as $hold): ?>
                <div class="list-item" style="border-left:4px solid #A51C30;">
                    <span class="badge badge-red"><?= htmlspecialchars($hold['hold_type']) ?></span>
                    <div style="margin-top:8px;font-weight:600;"><?= htmlspecialchars($hold['reason']) ?></div>
                    <div style="font-size:12px;color:#8a7c5e;"><?= __('placed_by') ?>: <?= htmlspecialchars($hold['placed_by']) ?> · <?= htmlspecialchars($hold['placed_at']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('charges_invoices') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('description') ?></th>
                                <th><?= __('term') ?></th>
                                <th style="text-align:right;"><?= __('amount') ?></th>
                                <th style="text-align:right;"><?= __('paid') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('due_date') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($invoices) === 0): ?>
                                <tr><td colspan="6"><?= __('no_charges') ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($invoices as $inv):
                                $statusClass = match($inv['status']) {
                                    'paid' => 'badge-green',
                                    'partial' => 'badge-gold',
                                    default => 'badge-red',
                                };
                                $statusLabel = match($inv['status']) {
                                    'paid' => __('status_paid'),
                                    'partial' => __('status_partial'),
                                    default => __('status_unpaid'),
                                };
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($inv['description']) ?></strong>
                                        <div style="font-size:11px;color:#8a7c5e;"><?= htmlspecialchars($inv['invoice_type']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($inv['semester_name'] ?? '-') ?></td>
                                    <td class="mono" style="text-align:right;">฿ <?= number_format((float)$inv['amount'], 2) ?></td>
                                    <td class="mono" style="text-align:right;">฿ <?= number_format((float)$inv['paid_amount'], 2) ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td class="mono"><?= htmlspecialchars($inv['due_date'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('payment_history') ?></h3>
                <div class="list-card">
                    <?php if (count($payments) === 0): ?>
                        <div class="list-item"><?= __('no_payment_history') ?></div>
                    <?php endif; ?>
                    <?php foreach ($payments as $pay): ?>
                        <div class="list-item">
                            <span class="badge badge-green"><?= htmlspecialchars($pay['payment_method']) ?></span>
                            <div style="margin-top:8px;">
                                <strong class="mono" style="font-size:18px;color:#5a8a3f;">฿ <?= number_format((float)$pay['amount'], 2) ?></strong>
                            </div>
                            <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($pay['invoice_desc'] ?? '-') ?></div>
                            <div style="font-size:12px;color:#8a7c5e;">
                                Ref: <?= htmlspecialchars($pay['reference_no'] ?? '-') ?> · <?= htmlspecialchars($pay['paid_at']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($balance > 0): ?>
                <h3 class="section-title" style="margin-top:28px;"><?= __('make_payment') ?></h3>
                <div class="card">
                    <div style="text-align:center;padding:20px 0;">
                        <div style="font-size:14px;color:#8a7c5e;margin-bottom:8px;"><?= __('outstanding_balance') ?></div>
                        <div class="serif" style="font-size:36px;font-weight:700;color:#A51C30;">฿ <?= number_format($balance, 2) ?></div>
                        <div style="margin-top:18px;">
                            <a class="btn" href="#" onclick="alert('<?= __('payment_online_dev') ?>'); return false;"><?= __('pay_now') ?></a>
                        </div>
                        <div style="font-size:11px;color:#8a7c5e;margin-top:12px;">
                            <?= __('payment_note') ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
