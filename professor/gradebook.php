<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('professor');
$user = getUser();

$pageTitle = __('gradebook');
$crumb = __('gradebook');
$message = '';

$stmt = $pdo->prepare("SELECT * FROM staff WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    $message = __('no_professor_profile');
}

$staffId = $staff ? (int)$staff['id'] : 0;
$selectedSectionId = (int)($_GET['section_id'] ?? $_POST['section_id'] ?? 0);

function calculateLetterGrade(float $score): array
{
    if ($score >= 90) return ['A', 4.00];
    if ($score >= 85) return ['B+', 3.50];
    if ($score >= 80) return ['B', 3.00];
    if ($score >= 75) return ['C+', 2.50];
    if ($score >= 70) return ['C', 2.00];
    if ($score >= 65) return ['D+', 1.50];
    if ($score >= 60) return ['D', 1.00];
    return ['F', 0.00];
}

function loadSections(PDO $pdo, int $staffId): array
{
    $stmt = $pdo->prepare("SELECT sections.*, courses.course_code, courses.course_name_th, courses.course_name_en, semesters.semester_name FROM section_instructors JOIN sections ON sections.id = section_instructors.section_id JOIN courses ON courses.id = sections.course_id JOIN semesters ON semesters.id = sections.semester_id WHERE section_instructors.staff_id = ? ORDER BY semesters.id DESC, courses.course_code ASC, sections.section_number ASC");
    $stmt->execute([$staffId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function verifySectionOwner(PDO $pdo, int $sectionId, int $staffId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM section_instructors WHERE section_id = ? AND staff_id = ? LIMIT 1");
    $stmt->execute([$sectionId, $staffId]);
    return (bool)$stmt->fetchColumn();
}

function loadGradeItems(PDO $pdo, int $sectionId): array
{
    $stmt = $pdo->prepare("SELECT * FROM grade_items WHERE section_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$sectionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadRoster(PDO $pdo, int $sectionId): array
{
    $stmt = $pdo->prepare("SELECT enrollments.id AS enrollment_id, students.id AS student_id, students.student_code, students.first_name, students.last_name FROM enrollments JOIN students ON students.id = enrollments.student_id WHERE enrollments.section_id = ? AND enrollments.status = 'enrolled' ORDER BY students.student_code ASC");
    $stmt->execute([$sectionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadScores(PDO $pdo, int $sectionId): array
{
    $stmt = $pdo->prepare("SELECT grade_scores.grade_item_id, grade_scores.student_id, grade_scores.score FROM grade_scores JOIN grade_items ON grade_items.id = grade_scores.grade_item_id WHERE grade_items.section_id = ?");
    $stmt->execute([$sectionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scores = [];
    foreach ($rows as $row) {
        $scores[(int)$row['student_id']][(int)$row['grade_item_id']] = $row['score'];
    }
    return $scores;
}

function weightedTotal(array $gradeItems, array $studentScores): float
{
    $total = 0;
    foreach ($gradeItems as $item) {
        $itemId = (int)$item['id'];
        $maxScore = (float)$item['max_score'];
        $weight = (float)$item['weight'];
        $score = isset($studentScores[$itemId]) && $studentScores[$itemId] !== null ? (float)$studentScores[$itemId] : 0;

        if ($maxScore > 0) {
            $total += ($score / $maxScore) * $weight;
        }
    }
    return round($total, 2);
}

$sections = $staffId > 0 ? loadSections($pdo, $staffId) : [];

if ($selectedSectionId === 0 && count($sections) > 0) {
    $selectedSectionId = (int)$sections[0]['id'];
}

$canUseSelectedSection = $selectedSectionId > 0 && $staffId > 0 && verifySectionOwner($pdo, $selectedSectionId, $staffId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item') {
    if (!$canUseSelectedSection) {
        $message = __('no_permission_section');
    } else {
        $name = trim($_POST['name'] ?? '');
        $weight = (float)($_POST['weight'] ?? 0);
        $maxScore = (float)($_POST['max_score'] ?? 100);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || $weight <= 0 || $maxScore <= 0) {
            $message = __('fill_grade_item_fields');
        } else {
            $stmt = $pdo->prepare("INSERT INTO grade_items (section_id, name, weight, max_score, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$selectedSectionId, $name, $weight, $maxScore, $sortOrder]);
            header('Location: gradebook.php?section_id=' . $selectedSectionId);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_scores') {
    if (!$canUseSelectedSection) {
        $message = __('no_permission_section');
    } else {
        $scoresInput = $_POST['scores'] ?? [];

        // Validate item ownership: load all valid grade_item IDs for this section once
        $sectionItems = loadGradeItems($pdo, $selectedSectionId);
        $validItemIds = array_flip(array_column($sectionItems, 'id'));

        // Pre-load existing score rows in 1 query (replaces N×M per-cell SELECT)
        $existingMap = [];
        if ($validItemIds) {
            $ph = implode(',', array_fill(0, count($validItemIds), '?'));
            $es = $pdo->prepare("SELECT id, grade_item_id, student_id FROM grade_scores WHERE grade_item_id IN ($ph)");
            $es->execute(array_keys($validItemIds));
            foreach ($es->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existingMap[(int)$row['student_id']][(int)$row['grade_item_id']] = (int)$row['id'];
            }
        }

        $stmtUpdate = $pdo->prepare("UPDATE grade_scores SET score = ?, updated_at = NOW() WHERE id = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO grade_scores (grade_item_id, student_id, score, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");

        try {
            $pdo->beginTransaction();

            foreach ($scoresInput as $studentId => $items) {
                $studentId = (int)$studentId;
                foreach ($items as $gradeItemId => $scoreValue) {
                    $gradeItemId = (int)$gradeItemId;
                    $scoreValue = trim((string)$scoreValue);

                    if ($studentId <= 0 || $gradeItemId <= 0 || $scoreValue === '') {
                        continue;
                    }
                    if (!isset($validItemIds[$gradeItemId])) {
                        continue;
                    }

                    $score = (float)$scoreValue;

                    if (isset($existingMap[$studentId][$gradeItemId])) {
                        $stmtUpdate->execute([$score, $existingMap[$studentId][$gradeItemId]]);
                    } else {
                        $stmtInsert->execute([$gradeItemId, $studentId, $score]);
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        header('Location: gradebook.php?section_id=' . $selectedSectionId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_final') {
    if (!$canUseSelectedSection) {
        $message = __('no_permission_section');
    } else {
        $gradeItems = loadGradeItems($pdo, $selectedSectionId);
        $roster = loadRoster($pdo, $selectedSectionId);
        $scores = loadScores($pdo, $selectedSectionId);

        // Pre-load existing final_grade rows in 1 query (replaces N per-student SELECT)
        $existingFinals = [];
        if ($roster) {
            $enrollmentIds = array_map('intval', array_column($roster, 'enrollment_id'));
            $ph = implode(',', array_fill(0, count($enrollmentIds), '?'));
            $ef = $pdo->prepare("SELECT id, enrollment_id FROM final_grades WHERE enrollment_id IN ($ph)");
            $ef->execute($enrollmentIds);
            foreach ($ef->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existingFinals[(int)$row['enrollment_id']] = (int)$row['id'];
            }
        }

        $stmtUpdate = $pdo->prepare("UPDATE final_grades SET raw_score = ?, letter_grade = ?, grade_point = ?, status = 'submitted', submitted_at = NOW() WHERE id = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO final_grades (enrollment_id, student_id, section_id, raw_score, letter_grade, grade_point, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, 'submitted', NOW())");

        try {
            $pdo->beginTransaction();

            foreach ($roster as $studentRow) {
                $studentId = (int)$studentRow['student_id'];
                $enrollmentId = (int)$studentRow['enrollment_id'];
                $studentScores = $scores[$studentId] ?? [];
                $total = weightedTotal($gradeItems, $studentScores);
                [$letter, $point] = calculateLetterGrade($total);

                if (isset($existingFinals[$enrollmentId])) {
                    $stmtUpdate->execute([$total, $letter, $point, $existingFinals[$enrollmentId]]);
                } else {
                    $stmtInsert->execute([$enrollmentId, $studentId, $selectedSectionId, $total, $letter, $point]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        header('Location: gradebook.php?section_id=' . $selectedSectionId);
        exit;
    }
}

$selectedSection = null;
foreach ($sections as $section) {
    if ((int)$section['id'] === $selectedSectionId) {
        $selectedSection = $section;
        break;
    }
}

$gradeItems = $canUseSelectedSection ? loadGradeItems($pdo, $selectedSectionId) : [];
$roster = $canUseSelectedSection ? loadRoster($pdo, $selectedSectionId) : [];
$scores = $canUseSelectedSection ? loadScores($pdo, $selectedSectionId) : [];

$totalWeight = 0;
foreach ($gradeItems as $item) {
    $totalWeight += (float)$item['weight'];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('prof_gradebook') ?></div>
            <div class="hero-title"><?= __('gradebook') ?></div>
            <div class="hero-desc"><?= __('gradebook_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="GET" action="gradebook.php" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
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
                <button type="submit" class="btn"><?= __('open_gradebook') ?></button>
            </form>
        </div>

        <?php if ($selectedSection && $canUseSelectedSection): ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;">
                    <div>
                        <div class="mono" style="color:#1c3a6e;font-weight:600;">
                            <?= htmlspecialchars($selectedSection['course_code']) ?> · <?= __('section') ?> <?= htmlspecialchars($selectedSection['section_number']) ?>
                        </div>
                        <div class="serif" style="font-size:24px;font-weight:600;">
                            <?= htmlspecialchars($selectedSection['course_name_th']) ?>
                        </div>
                        <div style="font-size:12px;color:#5a4f3a;">
                            <?= htmlspecialchars($selectedSection['semester_name']) ?> · <?= __('students') ?> <?= count($roster) ?> <?= __('students_persons') ?>
                        </div>
                    </div>
                    <div>
                        <?php if (abs($totalWeight - 100) < 0.01): ?>
                            <span class="badge badge-green"><?= __('weight_total_100') ?></span>
                        <?php else: ?>
                            <span class="badge badge-blue"><?= __('weight_total') ?> <?= number_format($totalWeight, 2) ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <h3 class="section-title"><?= __('score_table') ?></h3>
                    <div class="card" style="overflow-x:auto;">
                        <form method="POST" action="gradebook.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_scores">
                            <input type="hidden" name="section_id" value="<?= (int)$selectedSectionId ?>">

                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= __('code') ?></th>
                                        <th><?= __('students') ?></th>
                                        <?php foreach ($gradeItems as $item): ?>
                                            <th>
                                                <?= htmlspecialchars($item['name']) ?>
                                                <div class="mono" style="font-size:10px;color:#8a7c5e;">/<?= number_format((float)$item['max_score'], 2) ?> · <?= number_format((float)$item['weight'], 2) ?>%</div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th><?= __('total') ?></th>
                                        <th><?= __('grade') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($roster) === 0): ?>
                                        <tr><td colspan="<?= count($gradeItems) + 4 ?>"><?= __('no_students_in_section') ?></td></tr>
                                    <?php endif; ?>

                                    <?php foreach ($roster as $studentRow): ?>
                                        <?php
                                        $studentId = (int)$studentRow['student_id'];
                                        $studentScores = $scores[$studentId] ?? [];
                                        $total = weightedTotal($gradeItems, $studentScores);
                                        [$letter, $point] = calculateLetterGrade($total);
                                        ?>
                                        <tr>
                                            <td class="mono"><?= htmlspecialchars($studentRow['student_code']) ?></td>
                                            <td><?= htmlspecialchars($studentRow['first_name'] . ' ' . $studentRow['last_name']) ?></td>
                                            <?php foreach ($gradeItems as $item): ?>
                                                <?php $itemId = (int)$item['id']; ?>
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="<?= (float)$item['max_score'] ?>"
                                                        name="scores[<?= $studentId ?>][<?= $itemId ?>]"
                                                        value="<?= isset($studentScores[$itemId]) ? htmlspecialchars($studentScores[$itemId]) : '' ?>"
                                                        style="width:90px;padding:8px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                                                    >
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="mono"><?= number_format($total, 2) ?></td>
                                            <td class="serif" style="font-size:20px;font-weight:700;color:#1c3a6e;"><?= htmlspecialchars($letter) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <button type="submit" class="btn" style="margin-top:14px;"><?= __('save_scores') ?></button>
                        </form>
                    </div>
                </div>

                <div>
                    <h3 class="section-title"><?= __('add_grade_item') ?></h3>
                    <div class="card">
                        <form method="POST" action="gradebook.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_item">
                            <input type="hidden" name="section_id" value="<?= (int)$selectedSectionId ?>">

                            <div style="margin-bottom:14px;">
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('item_name') ?></label>
                                <input type="text" name="name" placeholder="<?= __('item_name_placeholder') ?>" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                                <div>
                                    <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('weight_pct') ?></label>
                                    <input type="number" step="0.01" name="weight" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                </div>
                                <div>
                                    <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('max_score') ?></label>
                                    <input type="number" step="0.01" name="max_score" value="100" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                </div>
                            </div>

                            <div style="margin-bottom:18px;">
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('sort_order') ?></label>
                                <input type="number" name="sort_order" value="0" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                            </div>

                            <button type="submit" class="btn"><?= __('add_grade_item') ?></button>
                        </form>
                    </div>

                    <h3 class="section-title" style="margin-top:28px;"><?= __('submit_final_grades') ?></h3>
                    <div class="card">
                        <p style="margin-top:0;color:#5a4f3a;font-size:13px;">
                            <?= __('submit_final_desc') ?>
                        </p>
                        <form method="POST" action="gradebook.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="submit_final">
                            <input type="hidden" name="section_id" value="<?= (int)$selectedSectionId ?>">
                            <button type="submit" class="btn"><?= __('submit_final_grades') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card"><?= __('no_section_access') ?></div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
