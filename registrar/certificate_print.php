<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$studentId = (int)($_GET['student_id'] ?? 0);

if ($studentId <= 0) {
    die('Missing student_id');
}

$stmt = $pdo->prepare("SELECT students.*, programs.program_code, programs.program_name_th, programs.program_name_en FROM students LEFT JOIN programs ON programs.id = students.program_id WHERE students.id = ? LIMIT 1");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student not found');
}

$issueDate = date('Y-m-d');
$studentName = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
$studentCode = htmlspecialchars($student['student_code']);
?>
<!doctype html>
<html lang="<?= currentLang() ?>">
<head>
    <meta charset="utf-8">
    <title><?= __('certificate_label') ?> - <?= $studentCode ?></title>
    <style>
        body { font-family: Arial, Tahoma, sans-serif; color:#1f1f1f; margin:44px; line-height:1.65; }
        .header { text-align:center; border-bottom:2px solid #222; padding-bottom:18px; margin-bottom:36px; }
        .school { font-size:26px; font-weight:700; }
        .sub { font-size:13px; color:#555; }
        .title { text-align:center; font-size:24px; font-weight:700; margin:34px 0; letter-spacing:1px; }
        .content { font-size:16px; max-width:760px; margin:0 auto; }
        .line { margin-bottom:16px; }
        .footer { margin-top:70px; display:flex; justify-content:flex-end; }
        .signature { width:280px; text-align:center; padding-top:54px; border-top:1px solid #222; font-size:13px; }
        .print-actions { position:fixed; top:16px; right:16px; }
        .btn { background:#1c3a6e; color:#fff; border:0; padding:10px 14px; cursor:pointer; }
        @media print { .print-actions { display:none; } body { margin:20mm; } }
    </style>
</head>
<body>
    <div class="print-actions"><button class="btn" onclick="window.print()"><?= __('print_btn') ?></button></div>

    <div class="header">
        <div class="school"><?= __('cert_school_name_en') ?></div>
        <div class="sub"><?= __('cert_school_name') ?></div>
    </div>

    <div class="title"><?= __('cert_title') ?></div>

    <div class="content">
        <div class="line"><?= __('cert_issue_date') ?>: <strong><?= htmlspecialchars($issueDate) ?></strong></div>

        <p><?= __('cert_body', ['name' => $student['first_name'] . ' ' . $student['last_name'], 'code' => $student['student_code']]) ?></p>

        <p>
            <?= __('cert_program') ?>: <strong><?= htmlspecialchars($student['program_name_en'] ?: ($student['program_name_th'] ?? '-')) ?></strong>
            <?php if (!empty($student['program_code'])): ?>
                (<strong><?= htmlspecialchars($student['program_code']) ?></strong>)
            <?php endif; ?>
        </p>

        <p>
            <?= __('cert_status') ?>: <strong><?= htmlspecialchars($student['study_status'] ?? '-') ?></strong>.
            <?= __('cert_admission') ?>: <strong><?= htmlspecialchars($student['admission_year'] ?? '-') ?></strong>.
        </p>

        <p><?= __('cert_footer') ?></p>
    </div>

    <div class="footer">
        <div class="signature"><?= __('cert_registrar') ?><br><?= __('cert_school_name') ?></div>
    </div>
</body>
</html>
