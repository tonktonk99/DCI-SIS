<?php
require '../includes/auth.php';
require '../config/database.php';

checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'professor') {
    die('Access denied');
}

$pageTitle = __('course_roster');
$crumb = __('course_roster');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);
$staffId = $staff ? (int)$staff['id'] : 0;

$selectedSectionId = (int)($_GET['section_id'] ?? 0);
$sections = [];
$students = [];

if ($staffId > 0) {
    $sectionStmt = $pdo->prepare("SELECT sections.*, courses.course_code, courses.course_name_th, semesters.semester_name FROM section_instructors JOIN sections ON sections.id = section_instructors.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE section_instructors.staff_id = ? ORDER BY semesters.id DESC, courses.course_code ASC");
    $sectionStmt->execute([$staffId]);
    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($selectedSectionId === 0 && count($sections) > 0) {
        $selectedSectionId = (int)$sections[0]['id'];
    }

    if ($selectedSectionId > 0) {
        $verify = $pdo->prepare("SELECT id FROM section_instructors WHERE section_id = ? AND staff_id = ? LIMIT 1");
        $verify->execute([$selectedSectionId, $staffId]);
        if ($verify->fetchColumn()) {
            $studentStmt = $pdo->prepare("SELECT students.*, programs.program_code, programs.program_name_th FROM enrollments JOIN students ON students.id = enrollments.student_id LEFT JOIN programs ON programs.id = students.program_id WHERE enrollments.section_id = ? AND enrollments.status = 'enrolled' ORDER BY students.student_code ASC");
            $studentStmt->execute([$selectedSectionId]);
            $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $message = __('no_professor_profile');
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('teaching_workspace') ?></div>
            <div class="hero-title"><?= __('course_roster') ?></div>
            <div class="hero-desc"><?= __('prof_students_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="GET" action="students.php" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
                <div style="flex:1;min-width:260px;">
                    <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('select_section') ?></label>
                    <select name="section_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= (int)$section['id'] ?>" <?= (int)$section['id'] === $selectedSectionId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($section['semester_name']) ?> · <?= htmlspecialchars($section['course_code']) ?> Sec <?= htmlspecialchars($section['section_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn"><?= __('view_roster') ?></button>
            </form>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('student_id_label') ?></th>
                        <th><?= __('name') ?></th>
                        <th><?= __('program_label') ?></th>
                        <th><?= __('status') ?></th>
                        <th>GPA</th>
                        <th><?= __('credits') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) === 0): ?>
                        <tr><td colspan="6"><?= __('no_students_in_section') ?></td></tr>
                    <?php endif; ?>

                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="mono"><?= htmlspecialchars($student['student_code']) ?></td>
                            <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td>
                                <span class="mono"><?= htmlspecialchars($student['program_code'] ?? '-') ?></span>
                                <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($student['program_name_th'] ?? '-') ?></div>
                            </td>
                            <td><span class="badge badge-blue"><?= htmlspecialchars($student['study_status']) ?></span></td>
                            <td class="mono"><?= number_format((float)$student['cumulative_gpa'], 2) ?></td>
                            <td class="mono"><?= (int)$student['total_credits_earned'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
