<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('student');
$user = getUser();

$stmt = $pdo->prepare("SELECT students.*, programs.program_code, programs.program_name_th, programs.program_name_en FROM students LEFT JOIN programs ON programs.id = students.program_id WHERE students.user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die('Student profile not found');
}

$studentId = (int)$student['id'];

$gradeStmt = $pdo->prepare("SELECT final_grades.*, courses.course_code, courses.course_name_th, courses.course_name_en, courses.credits, sections.section_number, semesters.id AS semester_id, semesters.semester_name FROM final_grades JOIN sections ON sections.id = final_grades.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE final_grades.student_id = ? AND final_grades.status IN ('released', 'locked') ORDER BY semesters.id ASC, courses.course_code ASC");
$gradeStmt->execute([$studentId]);
$grades = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);

$semesterGroups = [];
$totalCredits = 0;
$totalPoints = 0;

foreach ($grades as $grade) {
    $semesterId = (int)$grade['semester_id'];
    if (!isset($semesterGroups[$semesterId])) {
        $semesterGroups[$semesterId] = [
            'semester_name' => $grade['semester_name'],
            'items' => [],
            'credits' => 0,
            'points' => 0,
        ];
    }

    $credits = (int)$grade['credits'];
    $points = $credits * (float)$grade['grade_point'];

    $semesterGroups[$semesterId]['items'][] = $grade;
    $semesterGroups[$semesterId]['credits'] += $credits;
    $semesterGroups[$semesterId]['points'] += $points;
    $totalCredits += $credits;
    $totalPoints += $points;
}

$cumulativeGpa = $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
$issueDate = date('Y-m-d');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>Transcript - <?= htmlspecialchars($student['student_code']) ?></title>
    <style>
        body { font-family: Arial, Tahoma, sans-serif; color:#1f1f1f; margin:36px; }
        .header { border-bottom:2px solid #222; padding-bottom:16px; margin-bottom:20px; display:flex; justify-content:space-between; gap:20px; }
        .school { font-size:24px; font-weight:700; }
        .sub { font-size:12px; color:#555; margin-top:4px; }
        .title { text-align:center; font-size:22px; font-weight:700; margin:22px 0; letter-spacing:1px; }
        .info-grid { display:grid; grid-template-columns: 1fr 1fr; gap:8px 24px; margin-bottom:24px; font-size:13px; }
        table { width:100%; border-collapse:collapse; margin-bottom:18px; }
        th, td { border:1px solid #777; padding:7px 8px; font-size:12px; vertical-align:top; }
        th { background:#f0f0f0; text-align:left; }
        .semester { font-size:15px; font-weight:700; margin-top:18px; margin-bottom:6px; }
        .summary { text-align:right; font-size:13px; margin-top:16px; }
        .footer { margin-top:40px; display:flex; justify-content:space-between; gap:40px; font-size:12px; }
        .signature { width:260px; text-align:center; padding-top:48px; border-top:1px solid #222; }
        .print-actions { position:fixed; top:16px; right:16px; }
        .btn { background:#1c3a6e; color:#fff; border:0; padding:10px 14px; cursor:pointer; }
        @media print { .print-actions { display:none; } body { margin:20mm; } }
    </style>
</head>
<body>
    <div class="print-actions"><button class="btn" onclick="window.print()">Print</button></div>

    <div class="header">
        <div>
            <div class="school">DCI Center for Buddhist Studies</div>
            <div class="sub">ศูนย์พุทธศาสตร์ศึกษา DCI</div>
        </div>
        <div style="text-align:right;">
            <div><strong>Unofficial Transcript</strong></div>
            <div class="sub">Issue Date: <?= htmlspecialchars($issueDate) ?></div>
        </div>
    </div>

    <div class="title">ACADEMIC TRANSCRIPT</div>

    <div class="info-grid">
        <div><strong>Student ID:</strong> <?= htmlspecialchars($student['student_code']) ?></div>
        <div><strong>Admission Year:</strong> <?= htmlspecialchars($student['admission_year'] ?? '-') ?></div>
        <div><strong>Student Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
        <div><strong>Status:</strong> <?= htmlspecialchars($student['study_status'] ?? '-') ?></div>
        <div><strong>Program:</strong> <?= htmlspecialchars($student['program_name_th'] ?? '-') ?></div>
        <div><strong>Program Code:</strong> <?= htmlspecialchars($student['program_code'] ?? '-') ?></div>
    </div>

    <?php if (count($semesterGroups) === 0): ?>
        <p>No released academic records found.</p>
    <?php endif; ?>

    <?php foreach ($semesterGroups as $group): ?>
        <?php $semesterGpa = $group['credits'] > 0 ? $group['points'] / $group['credits'] : 0; ?>
        <div class="semester"><?= htmlspecialchars($group['semester_name']) ?> | Credits <?= (int)$group['credits'] ?> | GPA <?= number_format($semesterGpa, 2) ?></div>
        <table>
            <thead>
                <tr>
                    <th style="width:90px;">Course Code</th>
                    <th>Course Title</th>
                    <th style="width:60px;">Section</th>
                    <th style="width:60px;">Credits</th>
                    <th style="width:70px;">Score</th>
                    <th style="width:60px;">Grade</th>
                    <th style="width:70px;">Point</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($group['items'] as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['course_code']) ?></td>
                        <td><?= htmlspecialchars($item['course_name_th']) ?><?php if (!empty($item['course_name_en'])): ?><br><span style="color:#555;"><?= htmlspecialchars($item['course_name_en']) ?></span><?php endif; ?></td>
                        <td><?= htmlspecialchars($item['section_number']) ?></td>
                        <td><?= (int)$item['credits'] ?></td>
                        <td><?= number_format((float)$item['raw_score'], 2) ?></td>
                        <td><strong><?= htmlspecialchars($item['letter_grade']) ?></strong></td>
                        <td><?= number_format((float)$item['grade_point'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <div class="summary">
        <strong>Total Credits:</strong> <?= (int)$totalCredits ?> &nbsp;&nbsp;
        <strong>Cumulative GPA:</strong> <?= number_format($cumulativeGpa, 2) ?>
    </div>

    <div class="footer">
        <div>This document is generated from DCI Academic Portal.</div>
        <div class="signature">Registrar</div>
    </div>
</body>
</html>
