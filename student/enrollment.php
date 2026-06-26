<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('student');
$user = getUser();

$pageTitle = __('course_registration');
$crumb = __('course_registration');

$message = '';
$messageType = '';

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$currentSem = $pdo->query("SELECT * FROM semesters WHERE is_current = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$currentSem) {
    $currentSem = $pdo->query("SELECT * FROM semesters ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

$regOpen = false;
if ($currentSem) {
    $today = date('Y-m-d');
    $regOpen = ($today >= ($currentSem['registration_start'] ?? '') && $today <= ($currentSem['registration_end'] ?? ''));
}

$maxCredits = 22;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $studentId > 0) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'enroll' && $regOpen) {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        if ($sectionId > 0) {
            try {
                $pdo->beginTransaction();
                $secStmt = $pdo->prepare("SELECT sections.*, courses.course_id AS cid, courses.credits, courses.course_code FROM sections JOIN courses ON courses.id = sections.course_id WHERE sections.id = ? AND sections.status = 'active' FOR UPDATE");
                $secStmt->execute([$sectionId]);
                $sec = $secStmt->fetch(PDO::FETCH_ASSOC);

                if (!$sec) throw new Exception(__('section_unavailable'));
                $remaining = (int)$sec['capacity'] - (int)$sec['enrolled_count'];
                if ($remaining <= 0) throw new Exception(__('section_full'));

                $dupStmt = $pdo->prepare("SELECT id, status FROM enrollments WHERE student_id = ? AND section_id = ? LIMIT 1");
                $dupStmt->execute([$studentId, $sectionId]);
                $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
                if ($dup && $dup['status'] === 'enrolled') throw new Exception(__('already_registered'));

                $schedStmt = $pdo->prepare("SELECT day_of_week, start_time, end_time FROM section_schedules WHERE section_id = ?");
                $schedStmt->execute([$sectionId]);
                $newScheds = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

                $conflictStmt = $pdo->prepare("SELECT ss.day_of_week, ss.start_time, ss.end_time, c.course_code FROM enrollments e JOIN sections s ON s.id = e.section_id JOIN section_schedules ss ON ss.section_id = s.id JOIN courses c ON c.id = s.course_id WHERE e.student_id = ? AND e.status = 'enrolled'");
                $conflictStmt->execute([$studentId]);
                $existingScheds = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($newScheds as $ns) {
                    foreach ($existingScheds as $es) {
                        if ($ns['day_of_week'] === $es['day_of_week'] && $ns['start_time'] < $es['end_time'] && $ns['end_time'] > $es['start_time']) {
                            throw new Exception(__('schedule_conflict_with', ['course' => $es['course_code'] . ' (' . $es['day_of_week'] . ' ' . substr($es['start_time'],0,5) . '-' . substr($es['end_time'],0,5) . ')']));
                        }
                    }
                }

                $credStmt = $pdo->prepare("SELECT COALESCE(SUM(c.credits),0) FROM enrollments e JOIN sections s ON s.id = e.section_id JOIN courses c ON c.id = s.course_id WHERE e.student_id = ? AND e.status = 'enrolled'");
                $credStmt->execute([$studentId]);
                $currentCredits = (int)$credStmt->fetchColumn();
                if ($currentCredits + (int)$sec['credits'] > $maxCredits) {
                    throw new Exception(__('credit_limit_exceeded', ['max' => $maxCredits]));
                }

                if ($dup) {
                    $pdo->prepare("UPDATE enrollments SET status = 'enrolled', enrolled_at = NOW(), dropped_at = NULL WHERE id = ?")->execute([(int)$dup['id']]);
                } else {
                    $pdo->prepare("INSERT INTO enrollments (student_id, section_id, semester_id, status, enrolled_at) VALUES (?, ?, ?, 'enrolled', NOW())")->execute([$studentId, $sectionId, (int)$currentSem['id']]);
                }

                $pdo->prepare("UPDATE sections SET enrolled_count = enrolled_count + 1 WHERE id = ?")->execute([$sectionId]);
                $pdo->commit();
                $message = __('success_registered', ['course' => $sec['course_code']]);
                $messageType = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    if ($action === 'drop') {
        $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
        if ($enrollmentId > 0) {
            try {
                $pdo->beginTransaction();
                $enStmt = $pdo->prepare("SELECT * FROM enrollments WHERE id = ? AND student_id = ? AND status = 'enrolled' FOR UPDATE");
                $enStmt->execute([$enrollmentId, $studentId]);
                $en = $enStmt->fetch(PDO::FETCH_ASSOC);
                if (!$en) throw new Exception(__('enrollment_not_found'));

                $pdo->prepare("UPDATE enrollments SET status = 'dropped', dropped_at = NOW() WHERE id = ?")->execute([$enrollmentId]);
                $pdo->prepare("UPDATE sections SET enrolled_count = GREATEST(enrolled_count - 1, 0) WHERE id = ?")->execute([(int)$en['section_id']]);
                $pdo->commit();
                $message = __('success_withdrawn');
                $messageType = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Batch-load schedules for all section IDs → map[section_id => [rows]]
function batchLoadSchedules(PDO $pdo, array $sectionIds): array
{
    $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));
    if (!$sectionIds) return [];
    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM section_schedules WHERE section_id IN ($placeholders) ORDER BY section_id, FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
    $stmt->execute($sectionIds);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int)$row['section_id']][] = $row;
    }
    return $map;
}

// Batch-load first instructor name for all section IDs → map[section_id => 'Name']
function batchLoadInstructors(PDO $pdo, array $sectionIds): array
{
    $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));
    if (!$sectionIds) return [];
    $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
    $stmt = $pdo->prepare("SELECT si.section_id, s.first_name, s.last_name FROM section_instructors si JOIN staff s ON s.id = si.staff_id WHERE si.section_id IN ($placeholders) ORDER BY si.section_id, si.id ASC");
    $stmt->execute($sectionIds);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int)$row['section_id'];
        if (!isset($map[$sid])) {
            $map[$sid] = trim($row['first_name'] . ' ' . $row['last_name']);
        }
    }
    return $map;
}

function sectionScheduleText(array $scheds): string {
    $parts = [];
    foreach ($scheds as $s) {
        $dayMap = ['Monday'=>'จ.','Tuesday'=>'อ.','Wednesday'=>'พ.','Thursday'=>'พฤ.','Friday'=>'ศ.','Saturday'=>'ส.','Sunday'=>'อา.'];
        $d = $dayMap[$s['day_of_week']] ?? $s['day_of_week'];
        $parts[] = $d . ' ' . substr($s['start_time'],0,5) . '-' . substr($s['end_time'],0,5);
    }
    return implode(', ', $parts);
}

$searchQ = trim($_GET['q'] ?? '');
$filterProg = trim($_GET['prog'] ?? '');

$catalogSql = "SELECT sections.*, courses.course_code, courses.course_name_th, courses.credits, courses.description AS course_desc, courses.program_id, programs.program_name_th AS dept_name FROM sections JOIN courses ON courses.id = sections.course_id LEFT JOIN programs ON programs.id = courses.program_id WHERE sections.status = 'active'";
$params = [];

if ($currentSem) {
    $catalogSql .= " AND sections.semester_id = ?";
    $params[] = (int)$currentSem['id'];
}
if ($searchQ !== '') {
    $catalogSql .= " AND (courses.course_code LIKE ? OR courses.course_name_th LIKE ? OR courses.course_name_en LIKE ?)";
    $like = '%' . $searchQ . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filterProg !== '') {
    $catalogSql .= " AND courses.program_id = ?";
    $params[] = (int)$filterProg;
}

$catalogSql .= " ORDER BY courses.course_code ASC, sections.section_number ASC";
$catStmt = $pdo->prepare($catalogSql);
$catStmt->execute($params);
$catalog = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$cart = [];
$cartCredits = 0;
if ($studentId > 0) {
    $cartStmt = $pdo->prepare("SELECT e.*, s.section_number, c.course_code, c.course_name_th, c.credits FROM enrollments e JOIN sections s ON s.id = e.section_id JOIN courses c ON c.id = s.course_id WHERE e.student_id = ? AND e.status = 'enrolled' ORDER BY c.course_code");
    $cartStmt->execute([$studentId]);
    $cart = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cart as $c) $cartCredits += (int)$c['credits'];
}

$enrolledSectionIds = array_column($cart, 'section_id');
$programs = $pdo->query("SELECT id, program_code, program_name_th FROM programs ORDER BY program_code")->fetchAll(PDO::FETCH_ASSOC);
$tuitionPerCredit = 1500;
$estimatedTuition = $cartCredits * $tuitionPerCredit;

// Pre-load all schedules and instructors in 2 queries instead of N+N+M per page
$allSectionIds = array_merge(
    array_map('intval', array_column($catalog, 'id')),
    array_map('intval', array_column($cart, 'section_id'))
);
$scheduleMap   = batchLoadSchedules($pdo, $allSectionIds);
$instructorMap = batchLoadInstructors($pdo, array_map('intval', array_column($catalog, 'id')));
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('course_registration') ?></div>
            <div class="hero-title"><?= __('course_registration') ?></div>
            <div class="hero-desc">
                <?php if ($currentSem): ?>
                    <?php if ($regOpen): ?>
                        <span class="badge badge-green" style="vertical-align:middle;"><?= __('registration_open') ?></span>
                    <?php else: ?>
                        <span class="badge badge-red" style="vertical-align:middle;"><?= __('registration_closed') ?></span>
                    <?php endif; ?>
                    <?= __('registration_period') ?> — <?= htmlspecialchars($currentSem['semester_name']) ?>
                    <?php if ($currentSem['registration_start'] && $currentSem['registration_end']): ?>
                        · <?= __('add_drop_deadline') ?>: <?= htmlspecialchars($currentSem['registration_end']) ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?= __('no_current_term') ?>
                <?php endif; ?>
            </div>

            <div class="kpi-row">
                <div class="kpi">
                    <div class="kpi-label"><?= __('selected_courses') ?></div>
                    <div class="kpi-value"><?= count($cart) ?></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('total_credit_hours') ?></div>
                    <div class="kpi-value"><?= $cartCredits ?> <span style="font-size:14px;color:var(--muted);">/ <?= $maxCredits ?></span></div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?= __('estimated_tuition') ?></div>
                    <div class="kpi-value">฿ <?= number_format($estimatedTuition) ?></div>
                </div>
            </div>

            <div style="margin-top:14px;background:#e8e0d0;height:8px;border-radius:4px;overflow:hidden;">
                <div style="background:<?= $cartCredits > $maxCredits ? 'var(--crimson)' : 'var(--green)' ?>;height:100%;width:<?= min(100, round($cartCredits / $maxCredits * 100)) ?>%;transition:width 0.3s;"></div>
            </div>
            <div style="font-size:11px;color:var(--muted);margin-top:4px;"><?= __('credits_selected', ['current' => $cartCredits, 'max' => $maxCredits]) ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid <?= $messageType === 'success' ? 'var(--green)' : 'var(--crimson)' ?>;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('course_catalog') ?></h3>

                <div class="card" style="padding:14px 16px;margin-bottom:16px;">
                    <form method="GET" action="enrollment.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <input type="text" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="<?= __('search_course_placeholder') ?>" style="flex:1;min-width:200px;padding:8px 12px;border:1px solid var(--line);background:#fff;font-size:13px;border-radius:2px;">
                        <select name="prog" style="padding:8px 12px;border:1px solid var(--line);background:#fff;font-size:13px;border-radius:2px;">
                            <option value=""><?= __('all_departments') ?></option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filterProg == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['program_name_th']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn" style="padding:8px 16px;"><?= __('search') ?></button>
                        <?php if ($searchQ !== '' || $filterProg !== ''): ?>
                            <a href="enrollment.php" class="btn btn-light" style="padding:8px 16px;"><?= __('clear_filters') ?></a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (count($catalog) === 0): ?>
                    <div class="card" style="text-align:center;color:var(--muted);padding:40px;"><?= __('no_courses_match') ?></div>
                <?php endif; ?>

                <?php foreach ($catalog as $sec):
                    $secId = (int)$sec['id'];
                    $isEnrolled = in_array($secId, $enrolledSectionIds);
                    $remaining = (int)$sec['capacity'] - (int)$sec['enrolled_count'];
                    $isFull = $remaining <= 0;
                    $scheds = $scheduleMap[$secId] ?? [];
                    $schedText = sectionScheduleText($scheds);
                    $instructor = $instructorMap[$secId] ?? '-';

                    $hasConflict = false;
                    $conflictWith = '';
                    if (!$isEnrolled) {
                        foreach ($scheds as $ns) {
                            foreach ($cart as $ce) {
                                $ceScheds = $scheduleMap[(int)$ce['section_id']] ?? [];
                                foreach ($ceScheds as $es) {
                                    if ($ns['day_of_week'] === $es['day_of_week'] && $ns['start_time'] < $es['end_time'] && $ns['end_time'] > $es['start_time']) {
                                        $hasConflict = true;
                                        $conflictWith = $ce['course_code'];
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                ?>
                    <div class="card" style="padding:18px 20px;margin-bottom:12px;<?= $hasConflict ? 'border-left:4px solid #c89028;' : '' ?><?= $isEnrolled ? 'border-left:4px solid var(--green);' : '' ?>">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
                            <div style="flex:1;">
                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <span class="mono" style="font-size:14px;font-weight:700;color:var(--crimson);"><?= htmlspecialchars($sec['course_code']) ?></span>
                                    <span class="badge" style="background:#f0e9d8;color:#5a4f3a;"><?= (int)$sec['credits'] ?> <?= __('credits') ?></span>
                                    <?php if ($sec['dept_name']): ?>
                                        <span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($sec['dept_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="serif" style="font-size:20px;font-weight:600;margin-top:6px;"><?= htmlspecialchars($sec['course_name_th']) ?></div>
                                <?php if ($sec['course_desc']): ?>
                                    <div style="font-size:12px;color:#5a4f3a;margin-top:6px;line-height:1.6;"><?= htmlspecialchars(mb_strimwidth($sec['course_desc'], 0, 150, '…')) ?></div>
                                <?php endif; ?>
                                <div style="font-size:12px;color:var(--muted);margin-top:8px;">
                                    <?= htmlspecialchars($schedText ?: '-') ?> · Sec <?= htmlspecialchars($sec['section_number']) ?> · <?= htmlspecialchars($instructor) ?>
                                    <?php if ($scheds && $scheds[0]['room_name']): ?>
                                        · <?= htmlspecialchars($scheds[0]['room_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasConflict): ?>
                                    <div style="font-size:12px;color:#c89028;margin-top:6px;font-weight:600;"><?= __('schedule_conflict_with', ['course' => $conflictWith]) ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;min-width:100px;">
                                <div style="font-size:12px;margin-bottom:8px;">
                                    <span class="mono" style="color:<?= $isFull ? 'var(--crimson)' : ($remaining <= 5 ? '#c89028' : 'var(--green)') ?>;font-weight:600;"><?= $remaining ?></span>
                                    <span style="color:var(--muted);font-size:11px;"><?= __('seats_available') ?></span>
                                </div>
                                <?php if ($isEnrolled): ?>
                                    <span class="badge badge-green"><?= __('registered_check') ?></span>
                                <?php elseif ($isFull): ?>
                                    <button class="btn btn-light" style="padding:6px 14px;font-size:12px;" disabled><?= __('join_waitlist') ?></button>
                                <?php elseif (!$regOpen): ?>
                                    <span class="badge badge-red" style="font-size:11px;"><?= __('registration_closed') ?></span>
                                <?php elseif ($hasConflict): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="enroll">
                                        <input type="hidden" name="section_id" value="<?= $secId ?>">
                                        <button type="submit" class="btn" style="padding:6px 14px;font-size:12px;background:#c89028;" onclick="return confirm('<?= __('confirm_conflict', ['course' => $conflictWith]) ?>')"><?= __('add_course') ?></button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="enroll">
                                        <input type="hidden" name="section_id" value="<?= $secId ?>">
                                        <button type="submit" class="btn" style="padding:6px 14px;font-size:12px;"><?= __('add_course') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <h3 class="section-title"><?= __('registered_courses_count') ?> <span style="font-size:14px;color:var(--muted);">(<?= count($cart) ?>)</span></h3>

                <div class="list-card">
                    <?php if (count($cart) === 0): ?>
                        <div class="list-item" style="text-align:center;color:var(--muted);padding:30px;">
                            <?= __('no_courses_registered') ?><br>
                            <span style="font-size:12px;"><?= __('browse_catalog') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($cart as $item):
                        $itemScheds = $scheduleMap[(int)$item['section_id']] ?? [];
                        $itemSchedText = sectionScheduleText($itemScheds);
                    ?>
                        <div class="list-item">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                                <div>
                                    <div class="mono" style="font-size:13px;font-weight:700;color:var(--crimson);"><?= htmlspecialchars($item['course_code']) ?></div>
                                    <div class="serif" style="font-size:17px;font-weight:600;margin-top:2px;"><?= htmlspecialchars($item['course_name_th']) ?></div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                                        <?= htmlspecialchars($itemSchedText) ?> · <?= (int)$item['credits'] ?> <?= __('credits') ?>
                                    </div>
                                </div>
                                <?php if ($regOpen): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="drop">
                                        <input type="hidden" name="enrollment_id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn btn-light" style="padding:4px 10px;font-size:11px;color:var(--crimson);" onclick="return confirm('<?= __('confirm_withdraw', ['course' => $item['course_code']]) ?>')">✕</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($cart) > 0): ?>
                    <div class="card" style="margin-top:16px;">
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <tr>
                                <td style="padding:6px 0;color:var(--muted);"><?= __('total_credit_hours') ?></td>
                                <td style="padding:6px 0;text-align:right;font-weight:700;" class="mono"><?= $cartCredits ?></td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:var(--muted);"><?= __('estimated_tuition') ?></td>
                                <td style="padding:6px 0;text-align:right;font-weight:700;" class="mono">฿ <?= number_format($estimatedTuition) ?></td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin-top:14px;font-size:11px;color:var(--muted);text-align:center;"><?= __('advisor_approval') ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
