<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('manage_transcripts');
$crumb = __('office_of_registrar') . ' / ' . __('manage_transcripts');

$stmt = $pdo->query("SELECT students.*, programs.program_code, programs.program_name_th, COUNT(final_grades.id) AS released_grades, COALESCE(SUM(courses.credits), 0) AS released_credits FROM students LEFT JOIN programs ON programs.id = students.program_id LEFT JOIN final_grades ON final_grades.student_id = students.id AND final_grades.status IN ('released', 'locked') LEFT JOIN sections ON sections.id = final_grades.section_id LEFT JOIN courses ON courses.id = sections.course_id GROUP BY students.id ORDER BY students.student_code ASC");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('registrar_documents') ?></div>
            <div class="hero-title"><?= __('manage_transcripts') ?></div>
            <div class="hero-desc"><?= __('manage_transcripts_desc') ?></div>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('student_code_label') ?></th>
                        <th><?= __('name_label') ?></th>
                        <th><?= __('program_col') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('released_grades') ?></th>
                        <th><?= __('released_credits') ?></th>
                        <th><?= __('print_label') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) === 0): ?>
                        <tr><td colspan="7"><?= __('no_student_records') ?></td></tr>
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
                            <td class="mono"><?= (int)$student['released_grades'] ?></td>
                            <td class="mono"><?= (int)$student['released_credits'] ?></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <a class="btn btn-light" style="padding:6px 10px;font-size:11px;" target="_blank" href="certificate-print.php?student_id=<?= (int)$student['id'] ?>"><?= __('certificate_label') ?></a>
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
