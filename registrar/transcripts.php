<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('manage_transcripts');
$crumb = __('office_of_registrar') . ' / ' . __('manage_transcripts');

$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where  = [];
$params = [];

if ($search !== '') {
    // Escape LIKE special chars to prevent wildcard injection
    $safe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $where[]  = '(students.student_code LIKE ? OR students.first_name LIKE ? OR students.last_name LIKE ?)';
    $params[] = $safe . '%';        // student_code: starts-with, can use uq_students_student_code
    $params[] = '%' . $safe . '%';  // first_name: contains (no btree index on name cols)
    $params[] = '%' . $safe . '%';  // last_name: contains
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count query: students only — no heavy joins needed
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM students $whereSql");
$cntStmt->execute($params);
$totalStudents = (int)$cntStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($totalStudents / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

// LIMIT/OFFSET are int-cast, safe to interpolate
$listSql = "
    SELECT
        students.id,
        students.student_code,
        students.first_name,
        students.last_name,
        students.study_status,
        programs.program_code,
        programs.program_name_th,
        COUNT(final_grades.id) AS released_grades,
        COALESCE(SUM(courses.credits), 0) AS released_credits
    FROM students
    LEFT JOIN programs ON programs.id = students.program_id
    LEFT JOIN final_grades ON final_grades.student_id = students.id AND final_grades.status IN ('released', 'locked')
    LEFT JOIN sections ON sections.id = final_grades.section_id
    LEFT JOIN courses ON courses.id = sections.course_id
    $whereSql
    GROUP BY students.id
    ORDER BY students.student_code ASC
    LIMIT $perPage OFFSET $offset
";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$students = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$_pgQs = $search !== '' ? '&' . http_build_query(['search' => $search]) : '';
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

        <form method="GET" action="transcripts.php" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:20px;">
            <div style="flex:1;min-width:240px;">
                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('search') ?></label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars(__('search_student_placeholder')) ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
            </div>
            <button type="submit" class="btn"><?= __('filter') ?></button>
            <a class="btn btn-light" href="transcripts.php"><?= __('reset') ?></a>
        </form>

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
            <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e8e0cc;flex-wrap:wrap;gap:8px;">
                <div style="font-size:13px;color:#8a7c5e;">
                    <?= number_format($totalStudents) ?> <?= __('results') ?> &nbsp;&middot;&nbsp; <?= $page ?> / <?= $totalPages ?>
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-light" style="padding:5px 10px;font-size:12px;" href="<?= htmlspecialchars('transcripts.php?page=' . ($page - 1) . $_pgQs) ?>">&laquo; <?= __('prev_page') ?></a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <a class="btn <?= $p === $page ? '' : 'btn-light' ?>" style="padding:5px 10px;font-size:12px;" href="<?= htmlspecialchars('transcripts.php?page=' . $p . $_pgQs) ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-light" style="padding:5px 10px;font-size:12px;" href="<?= htmlspecialchars('transcripts.php?page=' . ($page + 1) . $_pgQs) ?>"><?= __('next_page') ?> &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
