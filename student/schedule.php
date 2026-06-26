<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('student');
$user = getUser();

$pageTitle = __('class_schedule');
$crumb = __('class_schedule');

$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$studentId = $student ? (int)$student['id'] : 0;

$schedules = [];
if ($studentId > 0) {
    $stmt = $pdo->prepare("
        SELECT sections.id AS section_id, sections.section_number,
               courses.course_code, courses.course_name_th, courses.credits,
               semesters.semester_name,
               ss.day_of_week, ss.start_time, ss.end_time, ss.room_name
        FROM enrollments
        JOIN sections ON sections.id = enrollments.section_id
        JOIN courses ON courses.id = sections.course_id
        JOIN semesters ON semesters.id = enrollments.semester_id
        LEFT JOIN section_schedules ss ON ss.section_id = sections.id
        WHERE enrollments.student_id = ? AND enrollments.status = 'enrolled'
        ORDER BY ss.start_time ASC
    ");
    $stmt->execute([$studentId]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInstructor(PDO $pdo, int $sectionId): string {
    $s = $pdo->prepare("SELECT staff.first_name, staff.last_name FROM section_instructors JOIN staff ON staff.id = section_instructors.staff_id WHERE section_instructors.section_id = ? LIMIT 1");
    $s->execute([$sectionId]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ? trim($r['first_name'] . ' ' . $r['last_name']) : '';
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$dayShort = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat'];

$byDay = [];
foreach ($days as $d) $byDay[$d] = [];
foreach ($schedules as $s) {
    $d = $s['day_of_week'] ?? '';
    if (isset($byDay[$d])) $byDay[$d][] = $s;
}

$minHour = 8;
$maxHour = 18;
foreach ($schedules as $s) {
    if ($s['start_time']) {
        $h = (int)substr($s['start_time'], 0, 2);
        if ($h < $minHour) $minHour = $h;
    }
    if ($s['end_time']) {
        $h = (int)substr($s['end_time'], 0, 2);
        $m = (int)substr($s['end_time'], 3, 2);
        if ($m > 0) $h++;
        if ($h > $maxHour) $maxHour = $h;
    }
}

$totalCredits = 0;
$courseSet = [];
foreach ($schedules as $s) {
    if (!isset($courseSet[$s['course_code']])) {
        $courseSet[$s['course_code']] = (int)$s['credits'];
        $totalCredits += (int)$s['credits'];
    }
}

if (count($byDay['Saturday']) === 0) {
    unset($byDay['Saturday']);
    array_pop($days);
}

$colors = ['#1c3a6e','#2d5f4a','#c89028','#8b4a6b','#5a4f3a','#a04a14','#1a4a7a','#6b4423'];
$courseColors = [];
$ci = 0;
foreach ($courseSet as $code => $cr) {
    $courseColors[$code] = $colors[$ci % count($colors)];
    $ci++;
}

$hourHeight = 60;
$totalHours = $maxHour - $minHour;
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('class_schedule') ?></div>
            <div class="hero-title"><?= __('weekly_schedule') ?></div>
            <div class="hero-desc">
                <?= htmlspecialchars($student ? $student['first_name'] . ' ' . $student['last_name'] : 'Student') ?>
                · <?= count($courseSet) ?> <?= __('courses_count') ?> · <?= $totalCredits ?> <?= __('credit_hours') ?>
                <?php if ($schedules && $schedules[0]['semester_name']): ?>
                    · <?= htmlspecialchars($schedules[0]['semester_name']) ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($schedules) === 0): ?>
            <div class="card" style="text-align:center;padding:40px;color:var(--muted);"><?= __('no_schedule') ?></div>
        <?php else: ?>

        <div class="card" style="padding:0;overflow-x:auto;">
            <div style="min-width:700px;">
                <div style="display:grid;grid-template-columns:60px repeat(<?= count($days) ?>, 1fr);border-bottom:2px solid var(--line);">
                    <div style="padding:12px 8px;font-size:11px;color:var(--muted);text-align:center;border-right:1px solid var(--line-soft);"></div>
                    <?php foreach ($days as $d): ?>
                        <div style="padding:12px 8px;text-align:center;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--ink);border-right:1px solid var(--line-soft);">
                            <?= $dayShort[$d] ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid;grid-template-columns:60px repeat(<?= count($days) ?>, 1fr);position:relative;">
                    <div style="border-right:1px solid var(--line-soft);">
                        <?php for ($h = $minHour; $h < $maxHour; $h++): ?>
                            <div style="height:<?= $hourHeight ?>px;padding:4px 6px 0;font-size:10px;color:var(--muted);text-align:right;border-bottom:1px solid var(--line-soft);" class="mono">
                                <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                            </div>
                        <?php endfor; ?>
                    </div>

                    <?php foreach ($days as $d): ?>
                        <div style="position:relative;border-right:1px solid var(--line-soft);">
                            <?php for ($h = $minHour; $h < $maxHour; $h++): ?>
                                <div style="height:<?= $hourHeight ?>px;border-bottom:1px solid var(--line-soft);"></div>
                            <?php endfor; ?>

                            <?php foreach ($byDay[$d] as $s):
                                if (!$s['start_time'] || !$s['end_time']) continue;
                                $startH = (int)substr($s['start_time'], 0, 2);
                                $startM = (int)substr($s['start_time'], 3, 2);
                                $endH = (int)substr($s['end_time'], 0, 2);
                                $endM = (int)substr($s['end_time'], 3, 2);
                                $startOffset = ($startH - $minHour) * $hourHeight + ($startM / 60) * $hourHeight;
                                $duration = (($endH * 60 + $endM) - ($startH * 60 + $startM)) / 60 * $hourHeight;
                                $color = $courseColors[$s['course_code']] ?? '#5a4f3a';
                                $instructor = getInstructor($pdo, (int)$s['section_id']);
                            ?>
                                <div style="position:absolute;top:<?= $startOffset ?>px;left:3px;right:3px;height:<?= max($duration - 2, 20) ?>px;background:<?= $color ?>;color:#fff;border-radius:4px;padding:6px 8px;overflow:hidden;font-size:11px;line-height:1.35;cursor:default;z-index:2;box-shadow:0 1px 3px rgba(0,0,0,0.15);" title="<?= htmlspecialchars($s['course_code'] . ' ' . $s['course_name_th'] . "\n" . substr($s['start_time'],0,5) . '–' . substr($s['end_time'],0,5) . "\n" . ($s['room_name'] ?? '') . "\n" . $instructor) ?>">
                                    <div style="font-weight:700;font-size:12px;"><?= htmlspecialchars($s['course_code']) ?></div>
                                    <?php if ($duration > 40): ?>
                                        <div style="opacity:0.9;margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($s['course_name_th'], 0, 24, '…')) ?></div>
                                    <?php endif; ?>
                                    <?php if ($duration > 55): ?>
                                        <div style="opacity:0.75;margin-top:2px;font-size:10px;"><?= htmlspecialchars($s['room_name'] ?? '') ?></div>
                                    <?php endif; ?>
                                    <?php if ($duration > 70 && $instructor): ?>
                                        <div style="opacity:0.65;margin-top:1px;font-size:10px;"><?= htmlspecialchars($instructor) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:14px;padding:0 4px;">
            <?php foreach ($courseColors as $code => $color): ?>
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                    <div style="width:12px;height:12px;border-radius:2px;background:<?= $color ?>;"></div>
                    <span class="mono" style="font-weight:600;"><?= htmlspecialchars($code) ?></span>
                    <span style="color:var(--muted);"><?= $courseSet[$code] ?> <?= __('credits') ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="section-title" style="margin-top:32px;"><?= __('daily_breakdown') ?></h3>
        <div class="grid-2">
            <div>
                <?php foreach ($byDay as $day => $items): ?>
                    <?php if (count($items) === 0) continue; ?>
                    <div class="card" style="margin-bottom:12px;">
                        <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);margin-bottom:10px;"><?= __($day) ?></div>
                        <?php foreach ($items as $item): ?>
                            <div class="list-item" style="padding-left:0;padding-right:0;">
                                <div style="display:flex;gap:14px;align-items:flex-start;">
                                    <div class="mono" style="min-width:90px;font-size:12px;color:<?= $courseColors[$item['course_code']] ?? 'var(--ink)' ?>;font-weight:600;">
                                        <?= htmlspecialchars(substr($item['start_time'] ?? '', 0, 5)) ?>–<?= htmlspecialchars(substr($item['end_time'] ?? '', 0, 5)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;"><?= htmlspecialchars($item['course_code']) ?> · <?= htmlspecialchars($item['course_name_th']) ?></div>
                                        <div style="font-size:12px;color:var(--muted);margin-top:2px;">
                                            Sec <?= htmlspecialchars($item['section_number']) ?>
                                            · <?= htmlspecialchars($item['room_name'] ?? '—') ?>
                                            <?php $inst = getInstructor($pdo, (int)$item['section_id']); if ($inst): ?>
                                                · <?= htmlspecialchars($inst) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <h3 class="section-title"><?= __('summary') ?></h3>
                <div class="card">
                    <table style="width:100%;font-size:13px;border-collapse:collapse;">
                        <tr>
                            <td style="padding:8px 0;color:var(--muted);"><?= __('registered_courses') ?></td>
                            <td style="padding:8px 0;text-align:right;font-weight:700;" class="mono"><?= count($courseSet) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:var(--muted);"><?= __('total_credit_hours') ?></td>
                            <td style="padding:8px 0;text-align:right;font-weight:700;" class="mono"><?= $totalCredits ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:var(--muted);"><?= __('class_sessions_week') ?></td>
                            <td style="padding:8px 0;text-align:right;font-weight:700;" class="mono"><?= count($schedules) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="margin-top:12px;">
                    <a class="btn" href="/dci-sis/student/enrollment.php" style="width:100%;text-align:center;display:block;"><?= __('course_registration') ?></a>
                    <a class="btn btn-light" href="/dci-sis/student/courses.php" style="width:100%;text-align:center;display:block;margin-top:8px;"><?= __('my_courses') ?></a>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
