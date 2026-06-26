<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'student') {
    die('Access denied');
}

$pageTitle = __('transcript_title');
$crumb = __('transcript_title');
$message = '';

$stmt = $pdo->prepare("SELECT students.*, programs.program_code, programs.program_name_th, programs.program_name_en FROM students LEFT JOIN programs ON programs.id = students.program_id WHERE students.user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

$studentId = $student ? (int)$student['id'] : 0;
$grades = [];

if ($studentId > 0) {
    $gradeStmt = $pdo->prepare("SELECT final_grades.*, courses.course_code, courses.course_name_th, courses.course_name_en, courses.credits, sections.section_number, semesters.id AS semester_id, semesters.semester_name FROM final_grades JOIN sections ON sections.id = final_grades.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE final_grades.student_id = ? AND final_grades.status IN ('released', 'locked') ORDER BY semesters.id ASC, courses.course_code ASC");
    $gradeStmt->execute([$studentId]);
    $grades = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $message = __('no_student_profile');
}

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

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_record') ?></div>
            <div class="hero-title"><?= __('transcript_title') ?></div>
            <div class="hero-desc"><?= __('transcript_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('credits') ?></div>
                    <div class="kpi-value"><?= (int)$totalCredits ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label">GPA</div>
                    <div class="kpi-value"><?= number_format($cumulativeGpa, 2) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('status') ?></div>
                    <div class="kpi-value"><?= htmlspecialchars($student['study_status'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card" style="background:#fbf8f2;">
            <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:22px;border-bottom:1px solid #d9cfb8;padding-bottom:18px;">
                <div>
                    <div class="serif" style="font-size:28px;font-weight:700;"><?= __('dci_name_en') ?></div>
                    <div style="font-size:13px;color:#5a4f3a;"><?= __('dci_name_th') ?></div>
                    <div class="mono" style="font-size:11px;color:#8a7c5e;margin-top:8px;"><?= __('unofficial_transcript') ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:12px;color:#8a7c5e;"><?= __('issue_date') ?></div>
                    <div class="mono"><?= htmlspecialchars($issueDate) ?></div>
                </div>
            </div>

            <?php if ($student): ?>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
                    <div>
                        <div class="kpi-label"><?= __('student_id_label') ?></div>
                        <div class="mono"><?= htmlspecialchars($student['student_code']) ?></div>
                    </div>
                    <div>
                        <div class="kpi-label"><?= __('student_name_label') ?></div>
                        <div><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                    </div>
                    <div>
                        <div class="kpi-label"><?= __('program_label') ?></div>
                        <div><?= htmlspecialchars($student['program_name_th'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="kpi-label"><?= __('admission_year') ?></div>
                        <div class="mono"><?= htmlspecialchars($student['admission_year'] ?? '-') ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($semesterGroups) === 0): ?>
                <div class="card"><?= __('no_transcript_data') ?></div>
            <?php endif; ?>

            <?php foreach ($semesterGroups as $group): ?>
                <?php $semesterGpa = $group['credits'] > 0 ? $group['points'] / $group['credits'] : 0; ?>
                <div style="margin-bottom:26px;">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;">
                        <h3 class="section-title" style="margin:0;"><?= htmlspecialchars($group['semester_name']) ?></h3>
                        <div class="mono" style="font-size:12px;color:#5a4f3a;">
                            <?= __('credits') ?> <?= (int)$group['credits'] ?> · GPA <?= number_format($semesterGpa, 2) ?>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('course_code') ?></th>
                                <th><?= __('course_title') ?></th>
                                <th><?= __('section') ?></th>
                                <th><?= __('credits') ?></th>
                                <th><?= __('score') ?></th>
                                <th><?= __('grade') ?></th>
                                <th><?= __('point') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['items'] as $item): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($item['course_code']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($item['course_name_th']) ?>
                                        <?php if (!empty($item['course_name_en'])): ?>
                                            <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($item['course_name_en']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mono"><?= htmlspecialchars($item['section_number']) ?></td>
                                    <td class="mono"><?= (int)$item['credits'] ?></td>
                                    <td class="mono"><?= number_format((float)$item['raw_score'], 2) ?></td>
                                    <td class="serif" style="font-size:20px;font-weight:700;color:#1c3a6e;"><?= htmlspecialchars($item['letter_grade']) ?></td>
                                    <td class="mono"><?= number_format((float)$item['grade_point'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:flex-end;border-top:1px solid #d9cfb8;padding-top:18px;">
                <button onclick="window.print()" class="btn"><?= __('print_transcript') ?></button>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
