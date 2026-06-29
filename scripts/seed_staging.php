#!/usr/bin/env php
<?php
/**
 * DCI-SIS Staging / Local Seed Script
 *
 * Creates test accounts and sample academic data for QA testing.
 * NEVER runs if APP_ENV=production.
 *
 * Dry run:
 *   APP_ENV=staging php scripts/seed_staging.php --dry-run
 *
 * Apply:
 *   APP_ENV=staging \
 *     SEED_CONFIRM=YES \
 *     SEED_DEFAULT_PASSWORD='YourPass!' \
 *     php scripts/seed_staging.php --apply
 *
 * See: docs/staging-seed-data.md for full documentation.
 */

// =============================================================================
// Bootstrap — safety guards
// =============================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line only.' . PHP_EOL);
}

$appEnv = getenv('APP_ENV') ?: 'local';

if ($appEnv === 'production') {
    fwrite(STDERR, "[ERROR] APP_ENV=production detected. Seeding is not allowed in production.\n");
    exit(1);
}

if (!in_array($appEnv, ['local', 'staging', 'test'], true)) {
    fwrite(STDERR, "[ERROR] Unrecognized APP_ENV={$appEnv}. Allowed values: local, staging, test\n");
    exit(1);
}

$args   = array_slice($argv ?? [], 1);
$dryRun = in_array('--dry-run', $args, true);
$apply  = in_array('--apply', $args, true);

if (!$dryRun && !$apply) {
    echo "DCI-SIS Staging Seed Script\n\n";
    echo "Usage:\n";
    echo "  Dry run (no writes):  APP_ENV=staging php scripts/seed_staging.php --dry-run\n";
    echo "  Apply (writes data):  APP_ENV=staging SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD='Pass!' \\\n";
    echo "                          php scripts/seed_staging.php --apply\n\n";
    echo "See docs/staging-seed-data.md for the full guide.\n\n";
    exit(0);
}

$rawPassword = '';
if ($apply) {
    if (getenv('SEED_CONFIRM') !== 'YES') {
        fwrite(STDERR, "[ERROR] Set SEED_CONFIRM=YES to apply seed data.\n");
        fwrite(STDERR, "        Run with --dry-run first to preview what will be created.\n");
        exit(1);
    }
    $rawPassword = getenv('SEED_DEFAULT_PASSWORD') ?: '';
    if ($rawPassword === '') {
        fwrite(STDERR, "[ERROR] SEED_DEFAULT_PASSWORD is required for --apply.\n");
        fwrite(STDERR, "        Example: SEED_DEFAULT_PASSWORD='StrongPass1!' php scripts/seed_staging.php --apply\n");
        exit(1);
    }
}

// =============================================================================
// Constants and DB connection
// =============================================================================

define('APP_ENV',   $appEnv);
define('APP_DEBUG', $appEnv !== 'production');
define('APP_BASE',  '/dci-sis');

$configFile = dirname(__DIR__) . '/config/database.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "[ERROR] Cannot find config/database.php — check you are running from the project root.\n");
    exit(1);
}
require $configFile;
// $pdo is now available

// =============================================================================
// Helpers
// =============================================================================

$stats = ['created' => 0, 'skipped' => 0, 'would_create' => 0];

function find_one(PDO $pdo, string $sql, array $bindings): ?int
{
    $st = $pdo->prepare($sql);
    $st->execute($bindings);
    $val = $st->fetchColumn();
    return $val !== false ? (int)$val : null;
}

function log_create(string $table, string $desc, int $id): void
{
    echo "[CREATE] {$table}: {$desc} → id={$id}\n";
}

function log_skip(string $table, string $desc, int $id): void
{
    echo "[SKIP]   {$table}: {$desc} (exists, id={$id})\n";
}

function log_dry(string $table, string $desc): void
{
    echo "[DRY]    {$table}: would create {$desc}\n";
}

function log_info(string $msg): void
{
    echo "[INFO]   {$msg}\n";
}

/**
 * Find-or-create a user. Returns id (or 0 in dry-run for would-create).
 */
function seed_user(PDO $pdo, string $username, string $role, string $hash, bool $dry, array &$stats): int
{
    $id = find_one($pdo, 'SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
    if ($id !== null) {
        log_skip('users', "{$username} ({$role})", $id);
        $stats['skipped']++;
        return $id;
    }
    if ($dry) {
        log_dry('users', "{$username} ({$role})");
        $stats['would_create']++;
        return 0;
    }
    $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)')->execute([$username, $hash, $role]);
    $id = (int)$pdo->lastInsertId();
    log_create('users', "{$username} ({$role})", $id);
    $stats['created']++;
    return $id;
}

/**
 * Find-or-create a student. Returns id (or 0 in dry-run for would-create).
 * Idempotency key: student_code (has UNIQUE KEY uq_students_student_code).
 */
function seed_student(PDO $pdo, int $userId, string $code, string $firstName, string $lastName, string $studyStatus, ?int $programId, bool $dry, array &$stats): int
{
    $id = find_one($pdo, 'SELECT id FROM students WHERE student_code = ? LIMIT 1', [$code]);
    if ($id !== null) {
        log_skip('students', "{$code} {$firstName} {$lastName}", $id);
        $stats['skipped']++;
        return $id;
    }
    if ($dry) {
        log_dry('students', "{$code} {$firstName} {$lastName} ({$studyStatus})");
        $stats['would_create']++;
        return 0;
    }
    $pdo->prepare('INSERT INTO students (user_id, student_code, program_id, first_name, last_name, admission_year, study_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$userId ?: null, $code, $programId, $firstName, $lastName, '2569', $studyStatus]);
    $id = (int)$pdo->lastInsertId();
    log_create('students', "{$code} {$firstName} {$lastName}", $id);
    $stats['created']++;
    return $id;
}

// =============================================================================
// Header
// =============================================================================

$mode = $dryRun ? 'DRY-RUN (nothing will be written)' : "APPLY (APP_ENV={$appEnv})";
echo "\n";
echo "==========================================================\n";
echo "  DCI-SIS Staging Seed | {$mode}\n";
echo "==========================================================\n\n";

$passwordHash = '';
if ($apply) {
    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
}

// =============================================================================
// 1. Users (5 test accounts)
// =============================================================================
echo "=== 1. Users ===\n";
$uid_admin     = seed_user($pdo, 'admin_test',     'admin',     $passwordHash, $dryRun, $stats);
$uid_registrar = seed_user($pdo, 'registrar_test', 'registrar', $passwordHash, $dryRun, $stats);
$uid_prof      = seed_user($pdo, 'prof_test',      'professor', $passwordHash, $dryRun, $stats);
$uid_student   = seed_user($pdo, 'student_test',   'student',   $passwordHash, $dryRun, $stats);
$uid_alumni    = seed_user($pdo, 'alumni_test',    'alumni',    $passwordHash, $dryRun, $stats);
echo "\n";

// =============================================================================
// 2. Programs — Dhammachai Institute / คณะพุทธศาสตร์
//    IPS : Year 1 foundation — International Program Scholarships
//    BS  : Year 2–4 — Buddhist Studies (สาขาวิชาพุทธศาสตร์)
//    BD  : Year 2–4 — Buddhadhamma Dissemination (สาขาวิชาเผยแผ่พุทธธรรม)
//    TD  : Year 2–4 — Technology Use in Disseminating Dhamma (สาขาเทคโนโลยีการเผยแผ่พุทธธรรม)
// =============================================================================
echo "=== 2. Programs ===\n";

// IPS — Year 1 foundation
$program_ips_id = find_one($pdo, "SELECT id FROM programs WHERE program_code = 'IPS' LIMIT 1", []);
if ($program_ips_id !== null) {
    log_skip('programs', 'IPS', $program_ips_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('programs', 'IPS โครงการทุนเรียนภาษานานาชาติ');
    $stats['would_create']++;
    $program_ips_id = 0;
} else {
    $pdo->prepare("INSERT INTO programs (program_code, program_name_th, program_name_en, status) VALUES ('IPS', 'โครงการทุนเรียนภาษานานาชาติ', 'International Program Scholarships', 'active')")->execute([]);
    $program_ips_id = (int)$pdo->lastInsertId();
    log_create('programs', 'IPS โครงการทุนเรียนภาษานานาชาติ', $program_ips_id);
    $stats['created']++;
}

// BS — Buddhist Studies, Year 2–4
$program_id = find_one($pdo, "SELECT id FROM programs WHERE program_code = 'BS' LIMIT 1", []);
if ($program_id !== null) {
    log_skip('programs', 'BS', $program_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('programs', 'BS สาขาวิชาพุทธศาสตร์');
    $stats['would_create']++;
    $program_id = 0;
} else {
    $pdo->prepare("INSERT INTO programs (program_code, program_name_th, program_name_en, status) VALUES ('BS', 'สาขาวิชาพุทธศาสตร์', 'Buddhist Studies', 'active')")->execute([]);
    $program_id = (int)$pdo->lastInsertId();
    log_create('programs', 'BS สาขาวิชาพุทธศาสตร์', $program_id);
    $stats['created']++;
}

// BD — Buddhadhamma Dissemination, Year 2–4
$program_bd_id = find_one($pdo, "SELECT id FROM programs WHERE program_code = 'BD' LIMIT 1", []);
if ($program_bd_id !== null) {
    log_skip('programs', 'BD', $program_bd_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('programs', 'BD สาขาวิชาเผยแผ่พุทธธรรม');
    $stats['would_create']++;
    $program_bd_id = 0;
} else {
    $pdo->prepare("INSERT INTO programs (program_code, program_name_th, program_name_en, status) VALUES ('BD', 'สาขาวิชาเผยแผ่พุทธธรรม', 'Buddhadhamma Dissemination', 'active')")->execute([]);
    $program_bd_id = (int)$pdo->lastInsertId();
    log_create('programs', 'BD สาขาวิชาเผยแผ่พุทธธรรม', $program_bd_id);
    $stats['created']++;
}

// TD — Technology Use in Disseminating Dhamma, Year 2–4
$program_td_id = find_one($pdo, "SELECT id FROM programs WHERE program_code = 'TD' LIMIT 1", []);
if ($program_td_id !== null) {
    log_skip('programs', 'TD', $program_td_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('programs', 'TD สาขาเทคโนโลยีการเผยแผ่พุทธธรรม');
    $stats['would_create']++;
    $program_td_id = 0;
} else {
    $pdo->prepare("INSERT INTO programs (program_code, program_name_th, program_name_en, status) VALUES ('TD', 'สาขาเทคโนโลยีการเผยแผ่พุทธธรรม', 'Technology Use in Disseminating Dhamma', 'active')")->execute([]);
    $program_td_id = (int)$pdo->lastInsertId();
    log_create('programs', 'TD สาขาเทคโนโลยีการเผยแผ่พุทธธรรม', $program_td_id);
    $stats['created']++;
}
echo "\n";

// =============================================================================
// 3. Academic Year
// =============================================================================
echo "=== 3. Academic Year ===\n";
$year_id = find_one($pdo, "SELECT id FROM academic_years WHERE year_label = '2569' LIMIT 1", []);
if ($year_id !== null) {
    log_skip('academic_years', '2569', $year_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('academic_years', '2569');
    $stats['would_create']++;
    $year_id = 0;
} else {
    $pdo->prepare("INSERT INTO academic_years (year_label, start_date, end_date, is_current) VALUES ('2569', '2026-06-01', '2027-05-31', 1)")->execute([]);
    $year_id = (int)$pdo->lastInsertId();
    log_create('academic_years', '2569', $year_id);
    $stats['created']++;
}
echo "\n";

// =============================================================================
// 4. Semester (active term 1 of year 2569)
// =============================================================================
echo "=== 4. Semester ===\n";
$effectiveYearId = $year_id ?: 1;
$semester_id = find_one($pdo, "SELECT id FROM semesters WHERE term = '1' AND academic_year_id = ? LIMIT 1", [$effectiveYearId]);
if ($semester_id !== null) {
    log_skip('semesters', 'ภาคเรียนที่ 1/2569', $semester_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('semesters', 'ภาคเรียนที่ 1/2569 (active)');
    $stats['would_create']++;
    $semester_id = 0;
} else {
    $pdo->prepare("INSERT INTO semesters (academic_year_id, semester_name, term, start_date, end_date, registration_start, registration_end, status, is_current) VALUES (?, ?, '1', '2026-06-01', '2026-09-30', '2026-05-15', '2026-06-15', 'active', 0)")
        ->execute([$effectiveYearId, 'ภาคเรียนที่ 1/2569']);
    $semester_id = (int)$pdo->lastInsertId();
    log_create('semesters', 'ภาคเรียนที่ 1/2569 (active)', $semester_id);
    $stats['created']++;
}
echo "\n";

// =============================================================================
// 5. Course DCI101
// =============================================================================
echo "=== 5. Course ===\n";
$course_id = find_one($pdo, "SELECT id FROM courses WHERE course_code = 'DCI101' LIMIT 1", []);
if ($course_id !== null) {
    log_skip('courses', 'DCI101', $course_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('courses', 'DCI101 พื้นฐานพุทธศาสตร์');
    $stats['would_create']++;
    $course_id = 0;
} else {
    $effectiveProgramId = $program_id ?: 1;
    $pdo->prepare("INSERT INTO courses (program_id, course_code, course_name_th, credits, status) VALUES (?, 'DCI101', 'พื้นฐานพุทธศาสตร์', 3, 'active')")
        ->execute([$effectiveProgramId]);
    $course_id = (int)$pdo->lastInsertId();
    log_create('courses', 'DCI101 พื้นฐานพุทธศาสตร์', $course_id);
    $stats['created']++;
}
echo "\n";

// =============================================================================
// 6. Section DCI101/001 in semester 1
// =============================================================================
echo "=== 6. Section ===\n";
$section_id = null;
$effectiveSemesterId = $semester_id ?: 1;
$effectiveCourseId   = $course_id   ?: 1;
$section_id = find_one($pdo, "SELECT id FROM sections WHERE semester_id = ? AND course_id = ? AND section_number = '001' LIMIT 1", [$effectiveSemesterId, $effectiveCourseId]);
if ($section_id !== null) {
    log_skip('sections', 'DCI101/001', $section_id);
    $stats['skipped']++;
} elseif ($dryRun) {
    log_dry('sections', 'DCI101/001');
    $stats['would_create']++;
    $section_id = 0;
} else {
    $pdo->prepare("INSERT INTO sections (semester_id, course_id, section_number, capacity, enrolled_count, room_name, status) VALUES (?, ?, '001', 30, 0, 'ห้อง 101', 'active')")
        ->execute([$effectiveSemesterId, $effectiveCourseId]);
    $section_id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO section_schedules (section_id, day_of_week, start_time, end_time, room_name) VALUES (?, 'Monday', '09:00:00', '12:00:00', 'ห้อง 101')")
        ->execute([$section_id]);
    log_create('sections', 'DCI101/001 (+ schedule Mon 09:00–12:00)', $section_id);
    $stats['created']++;
}
echo "\n";

// =============================================================================
// 7. Staff (for prof_test)
// =============================================================================
echo "=== 7. Staff ===\n";
$staff_id = null;
if ($uid_prof) {
    $staff_id = find_one($pdo, 'SELECT id FROM staff WHERE user_id = ? LIMIT 1', [$uid_prof]);
    if ($staff_id !== null) {
        log_skip('staff', 'T900 / prof_test', $staff_id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('staff', 'T900 Test Professor');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO staff (user_id, staff_code, first_name, last_name, position, status) VALUES (?, 'T900', 'Test', 'Professor', 'อาจารย์', 'active')")
            ->execute([$uid_prof]);
        $staff_id = (int)$pdo->lastInsertId();
        log_create('staff', 'T900 Test Professor', $staff_id);
        $stats['created']++;
    }
} else {
    // uid_prof is 0 (dry-run would-create) — staff would depend on it
    $existing = find_one($pdo, "SELECT id FROM staff WHERE staff_code = 'T900' LIMIT 1", []);
    if ($existing !== null) {
        log_skip('staff', 'T900 (found by code)', $existing);
        $stats['skipped']++;
        $staff_id = $existing;
    } else {
        log_dry('staff', 'T900 (depends on prof_test user)');
        $stats['would_create']++;
    }
}
if (!$staff_id && !$dryRun) {
    $staff_id = find_one($pdo, "SELECT id FROM staff WHERE staff_code = 'T900' LIMIT 1", []);
}
echo "\n";

// =============================================================================
// 8. Section Instructor — link T900 to section
// =============================================================================
echo "=== 8. Section Instructor ===\n";
$effectiveSectionId = $section_id ?: 1;
if ($staff_id && $effectiveSectionId) {
    $si_id = find_one($pdo, 'SELECT id FROM section_instructors WHERE section_id = ? AND staff_id = ? LIMIT 1', [$effectiveSectionId, $staff_id]);
    if ($si_id !== null) {
        log_skip('section_instructors', "section={$effectiveSectionId} ← T900", $si_id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('section_instructors', 'DCI101/001 ← T900 (prof_test)');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO section_instructors (section_id, staff_id, role) VALUES (?, ?, 'primary')")
            ->execute([$effectiveSectionId, $staff_id]);
        $si_id = (int)$pdo->lastInsertId();
        log_create('section_instructors', "section={$effectiveSectionId} ← T900", $si_id);
        $stats['created']++;
    }
} else {
    log_info('section_instructors: skipped — staff_id not available yet (dry-run dependency)');
}
echo "\n";

// =============================================================================
// 9. Students (student_test + alumni_test)
// =============================================================================
echo "=== 9. Students ===\n";
$effectiveProgramId = $program_id ?: 1;
$student_id        = seed_student($pdo, $uid_student, 'S9999001', 'Test', 'Student', 'studying',  $effectiveProgramId, $dryRun, $stats);
$alumni_student_id = seed_student($pdo, $uid_alumni,  'S9999002', 'Test', 'Alumni',  'graduated', $effectiveProgramId, $dryRun, $stats);
echo "\n";

// =============================================================================
// 10. Enrollment — student_test → section DCI101/001
// =============================================================================
echo "=== 10. Enrollment ===\n";
$enrollment_id = null;
if ($student_id && $effectiveSectionId && $effectiveSemesterId) {
    $enrollment_id = find_one($pdo, 'SELECT id FROM enrollments WHERE student_id = ? AND section_id = ? LIMIT 1', [$student_id, $effectiveSectionId]);
    if ($enrollment_id !== null) {
        log_skip('enrollments', "S9999001 → DCI101/001", $enrollment_id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('enrollments', 'S9999001 → DCI101/001 (enrolled)');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO enrollments (student_id, section_id, semester_id, status, enrolled_at) VALUES (?, ?, ?, 'enrolled', NOW())")
            ->execute([$student_id, $effectiveSectionId, $effectiveSemesterId]);
        $enrollment_id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE sections SET enrolled_count = enrolled_count + 1 WHERE id = ?")->execute([$effectiveSectionId]);
        log_create('enrollments', 'S9999001 → DCI101/001', $enrollment_id);
        $stats['created']++;
    }
} else {
    log_info('enrollments: skipped — student_id or section_id not available (dry-run dependency)');
}
echo "\n";

// =============================================================================
// 11. Grade Items (Midterm 50%, Final Exam 50%)
// =============================================================================
echo "=== 11. Grade Items ===\n";
$grade_item_ids = [];
if ($effectiveSectionId) {
    $items = [
        ['Midterm',    50.00, 100.00, 1],
        ['Final Exam', 50.00, 100.00, 2],
    ];
    foreach ($items as [$name, $weight, $maxScore, $order]) {
        $id = find_one($pdo, 'SELECT id FROM grade_items WHERE section_id = ? AND name = ? LIMIT 1', [$effectiveSectionId, $name]);
        if ($id !== null) {
            log_skip('grade_items', "{$name} weight={$weight}%", $id);
            $stats['skipped']++;
            $grade_item_ids[$name] = $id;
        } elseif ($dryRun) {
            log_dry('grade_items', "{$name} weight={$weight}% max={$maxScore}");
            $stats['would_create']++;
            $grade_item_ids[$name] = 0;
        } else {
            $pdo->prepare("INSERT INTO grade_items (section_id, name, weight, max_score, sort_order) VALUES (?, ?, ?, ?, ?)")
                ->execute([$effectiveSectionId, $name, $weight, $maxScore, $order]);
            $id = (int)$pdo->lastInsertId();
            log_create('grade_items', "{$name} weight={$weight}%", $id);
            $stats['created']++;
            $grade_item_ids[$name] = $id;
        }
    }
}
echo "\n";

// =============================================================================
// 12. Grade Scores (student_test: Midterm=80, Final=90)
// =============================================================================
echo "=== 12. Grade Scores ===\n";
if ($student_id && !empty($grade_item_ids)) {
    $scores = ['Midterm' => 80.00, 'Final Exam' => 90.00];
    foreach ($scores as $itemName => $score) {
        $gid = $grade_item_ids[$itemName] ?? 0;
        if (!$gid) {
            log_info("grade_scores: {$itemName} — grade_item not available (dry-run dependency)");
            continue;
        }
        $id = find_one($pdo, 'SELECT id FROM grade_scores WHERE grade_item_id = ? AND student_id = ? LIMIT 1', [$gid, $student_id]);
        if ($id !== null) {
            log_skip('grade_scores', "{$itemName}: {$score}/100 for S9999001", $id);
            $stats['skipped']++;
        } elseif ($dryRun) {
            log_dry('grade_scores', "{$itemName}: {$score}/100 for S9999001");
            $stats['would_create']++;
        } else {
            $pdo->prepare("INSERT INTO grade_scores (grade_item_id, student_id, score, updated_at) VALUES (?, ?, ?, NOW())")
                ->execute([$gid, $student_id, $score]);
            $id = (int)$pdo->lastInsertId();
            log_create('grade_scores', "{$itemName}: {$score}/100 for S9999001", $id);
            $stats['created']++;
        }
    }
} else {
    log_info('grade_scores: skipped — student_id not available (dry-run dependency)');
}
echo "\n";

// =============================================================================
// 13. Final Grade (student_test in DCI101/001 → A, 4.00, submitted)
// =============================================================================
echo "=== 13. Final Grade ===\n";
if ($enrollment_id && $student_id && $effectiveSectionId) {
    $id = find_one($pdo, 'SELECT id FROM final_grades WHERE enrollment_id = ? LIMIT 1', [$enrollment_id]);
    if ($id !== null) {
        log_skip('final_grades', 'S9999001 → A (4.00) submitted', $id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('final_grades', 'S9999001 → raw=85, A (4.00) submitted');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO final_grades (enrollment_id, student_id, section_id, raw_score, letter_grade, grade_point, status, submitted_at) VALUES (?, ?, ?, 85.00, 'A', 4.00, 'submitted', NOW())")
            ->execute([$enrollment_id, $student_id, $effectiveSectionId]);
        $id = (int)$pdo->lastInsertId();
        log_create('final_grades', 'S9999001 → raw=85, A (4.00) submitted', $id);
        $stats['created']++;
    }
} else {
    log_info('final_grades: skipped — enrollment_id not available (dry-run dependency)');
}
echo "\n";

// =============================================================================
// 14. Exam + Exam Score
// =============================================================================
echo "=== 14. Exam & Exam Score ===\n";
$exam_id = null;
if ($effectiveSectionId) {
    $exam_id = find_one($pdo, "SELECT id FROM exams WHERE section_id = ? AND exam_type = 'midterm' LIMIT 1", [$effectiveSectionId]);
    if ($exam_id !== null) {
        log_skip('exams', 'DCI101/001 midterm', $exam_id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('exams', 'DCI101/001 Midterm Exam (scheduled)');
        $stats['would_create']++;
    } else {
        $examDate = date('Y-m-d', strtotime('+14 days'));
        $pdo->prepare("INSERT INTO exams (section_id, exam_type, exam_title, exam_date, start_time, end_time, room_name, max_score, status) VALUES (?, 'midterm', 'Midterm Exam — DCI101', ?, '09:00:00', '12:00:00', 'ห้อง 101', 100.00, 'scheduled')")
            ->execute([$effectiveSectionId, $examDate]);
        $exam_id = (int)$pdo->lastInsertId();
        log_create('exams', "DCI101/001 Midterm Exam ({$examDate})", $exam_id);
        $stats['created']++;
    }
}
if ($exam_id && $student_id) {
    $id = find_one($pdo, 'SELECT id FROM exam_scores WHERE exam_id = ? AND student_id = ? LIMIT 1', [$exam_id, $student_id]);
    if ($id !== null) {
        log_skip('exam_scores', 'S9999001 midterm = 80/100', $id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('exam_scores', 'S9999001 midterm = 80/100');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO exam_scores (exam_id, student_id, score, graded_at) VALUES (?, ?, 80.00, NOW())")
            ->execute([$exam_id, $student_id]);
        $id = (int)$pdo->lastInsertId();
        log_create('exam_scores', 'S9999001 midterm = 80/100', $id);
        $stats['created']++;
    }
} elseif (!$exam_id || !$student_id) {
    log_info('exam_scores: skipped — exam_id or student_id not available (dry-run dependency)');
}
echo "\n";

// =============================================================================
// 15. Document Requests
// =============================================================================
echo "=== 15. Document Requests ===\n";
// student_test → transcript request (pending)
if ($uid_student && $student_id) {
    $id = find_one($pdo, "SELECT id FROM document_requests WHERE requester_user_id = ? AND request_type = 'transcript' AND status = 'pending' LIMIT 1", [$uid_student]);
    if ($id !== null) {
        log_skip('document_requests', 'student_test → transcript (pending)', $id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('document_requests', 'student_test → transcript (pending)');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO document_requests (requester_user_id, student_id, requester_type, request_type, purpose, delivery_method, status, requested_at) VALUES (?, ?, 'student', 'transcript', 'ทดสอบระบบ QA', 'pickup', 'pending', NOW())")
            ->execute([$uid_student, $student_id]);
        $id = (int)$pdo->lastInsertId();
        log_create('document_requests', 'student_test → transcript (pending)', $id);
        $stats['created']++;
    }
} else {
    log_info('document_requests (student): skipped — uid_student or student_id not available');
}
// alumni_test → transcript request (pending)
if ($uid_alumni && $alumni_student_id) {
    $id = find_one($pdo, "SELECT id FROM document_requests WHERE requester_user_id = ? AND request_type = 'transcript' AND status = 'pending' LIMIT 1", [$uid_alumni]);
    if ($id !== null) {
        log_skip('document_requests', 'alumni_test → transcript (pending)', $id);
        $stats['skipped']++;
    } elseif ($dryRun) {
        log_dry('document_requests', 'alumni_test → transcript (pending, requester_type=alumni)');
        $stats['would_create']++;
    } else {
        $pdo->prepare("INSERT INTO document_requests (requester_user_id, student_id, requester_type, request_type, purpose, delivery_method, status, requested_at) VALUES (?, ?, 'alumni', 'transcript', 'ทดสอบระบบ QA (alumni)', 'email_pdf', 'pending', NOW())")
            ->execute([$uid_alumni, $alumni_student_id]);
        $id = (int)$pdo->lastInsertId();
        log_create('document_requests', 'alumni_test → transcript (pending, alumni)', $id);
        $stats['created']++;
    }
} else {
    log_info('document_requests (alumni): skipped — uid_alumni or alumni_student_id not available');
}
echo "\n";

// =============================================================================
// Summary
// =============================================================================
echo "==========================================================\n";
if ($dryRun) {
    echo "  DRY-RUN complete\n";
    printf("  Would create : %d rows\n", $stats['would_create']);
    printf("  Already exist: %d rows\n", $stats['skipped']);
    echo "\n  To apply, run:\n";
    echo "    APP_ENV=staging SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD='YourPass!' \\\n";
    echo "      php scripts/seed_staging.php --apply\n";
} else {
    echo "  Seed complete\n";
    printf("  Created : %d rows\n", $stats['created']);
    printf("  Skipped : %d rows (already existed)\n", $stats['skipped']);
    echo "\n  Test accounts (use SEED_DEFAULT_PASSWORD as the password):\n";
    echo "    admin_test       (role: admin)\n";
    echo "    registrar_test   (role: registrar)\n";
    echo "    prof_test        (role: professor)  → staff T900, instructing DCI101/001\n";
    echo "    student_test     (role: student)    → S9999001, enrolled in DCI101/001\n";
    echo "    alumni_test      (role: alumni)     → S9999002, graduated\n";
    echo "\n  See docs/staging-seed-data.md for QA scenarios and workflows.\n";
}
echo "==========================================================\n\n";
