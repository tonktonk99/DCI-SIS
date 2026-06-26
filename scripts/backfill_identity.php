<?php
/**
 * Phase Identity-1: Backfill identity tables from legacy students data
 *
 * Creates:
 *   persons          - one record per student
 *   student_programs - one record per student (their program enrollment)
 *   identity_links   - maps students.id → persons.id
 *
 * CLI only. Supports --dry-run and --apply.
 * Idempotent: students already linked are skipped (not duplicated).
 *
 * Usage:
 *   php scripts/backfill_identity.php --dry-run
 *   php scripts/backfill_identity.php --apply
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// CLI guard
// ---------------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "ERROR: This script must be run from the command line.\n");
    exit(1);
}

// ---------------------------------------------------------------
// Parse arguments
// ---------------------------------------------------------------
$dryRun = in_array('--dry-run', $argv, true);
$apply  = in_array('--apply',   $argv, true);

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/backfill_identity.php --dry-run   # preview, no DB writes\n");
    fwrite(STDERR, "  php scripts/backfill_identity.php --apply      # write to DB\n");
    exit(1);
}
if ($dryRun && $apply) {
    fwrite(STDERR, "ERROR: cannot use both --dry-run and --apply simultaneously.\n");
    exit(1);
}

// ---------------------------------------------------------------
// Bootstrap: only load database config (no session/auth)
// ---------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config/database.php';
// $pdo is now available

$mode = $dryRun ? 'DRY-RUN' : 'APPLY';

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

/**
 * Generate the next available person_no in DCI########format.
 * Reads the current MAX from DB each time — safe for sequential calls.
 * In APPLY mode this is called inside a transaction; no concurrent
 * migration writers are expected.
 */
function nextPersonNo(PDO $pdo): string
{
    $stmt = $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(person_no, 4) AS UNSIGNED)), 0)
         FROM persons
         WHERE person_no REGEXP '^DCI[0-9]{8}$'"
    );
    $max = (int) $stmt->fetchColumn();
    return 'DCI' . str_pad((string)($max + 1), 8, '0', STR_PAD_LEFT);
}

/**
 * Map students.study_status → student_programs.academic_status.
 * Unknown values are kept as-is (forward-compatible).
 */
function mapAcademicStatus(string $studyStatus): string
{
    return [
        'studying'   => 'active',
        'graduated'  => 'graduated',
        'leave'      => 'leave',
        'suspended'  => 'suspended',
        'withdrawn'  => 'withdrawn',
        'dismissed'  => 'dismissed',
        'applicant'  => 'applicant',
        'alumni'     => 'alumni',
    ][$studyStatus] ?? $studyStatus;
}

function logLine(string $line): void
{
    echo $line . "\n";
}

// ---------------------------------------------------------------
// Main
// ---------------------------------------------------------------
logLine('====================================================');
logLine("  DCI-SIS Identity Backfill — Mode: {$mode}");
logLine('  Started : ' . date('Y-m-d H:i:s'));
logLine('====================================================');

// Verify identity tables exist before starting
foreach (['persons', 'student_programs', 'identity_links'] as $tbl) {
    $check = $pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetchColumn();
    if ($check === false) {
        fwrite(STDERR, "ERROR: Table `{$tbl}` does not exist.\n");
        fwrite(STDERR, "Run database/migrations/identity_v1.sql first.\n");
        exit(1);
    }
}

// Fetch all students ordered by id
$students = $pdo->query(
    "SELECT id, user_id, student_code, program_id,
            first_name, last_name, admission_year, study_status
     FROM students
     ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = count($students);
logLine("Students in DB : {$totalStudents}");
logLine('----------------------------------------------------');

$stats = [
    'created' => 0,
    'skipped' => 0,
    'errors'  => [],
];

// For dry-run: compute a realistic person_no preview without writing
$dryRunCounter = 0;
if ($dryRun) {
    $stmt = $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(person_no, 4) AS UNSIGNED)), 0)
         FROM persons WHERE person_no REGEXP '^DCI[0-9]{8}$'"
    );
    $dryRunCounter = (int) $stmt->fetchColumn();
}

$checkLink = $pdo->prepare(
    "SELECT il.person_id
     FROM identity_links il
     WHERE il.source_table = 'students' AND il.source_id = ?"
);

foreach ($students as $s) {
    $studentId   = (int)$s['id'];
    $studentCode = (string)$s['student_code'];
    $firstName   = trim((string)$s['first_name']);
    $lastName    = trim((string)$s['last_name']);
    $programId   = $s['program_id'] !== null ? (int)$s['program_id'] : null;
    $admitYear   = $s['admission_year'] !== '' ? $s['admission_year'] : null;
    $acadStatus  = mapAcademicStatus((string)($s['study_status'] ?? 'active'));

    // Idempotency check: already linked?
    $checkLink->execute([$studentId]);
    $existingPersonId = $checkLink->fetchColumn();

    if ($existingPersonId !== false) {
        logLine("  [SKIP] students.id={$studentId} ({$studentCode})"
               . " → already linked to person_id={$existingPersonId}");
        $stats['skipped']++;
        continue;
    }

    // ── DRY-RUN ────────────────────────────────────────────────
    if ($dryRun) {
        $dryRunCounter++;
        $previewNo = 'DCI' . str_pad((string)$dryRunCounter, 8, '0', STR_PAD_LEFT);
        logLine("  [DRY ] students.id={$studentId} ({$studentCode})"
               . " → person_no={$previewNo}"
               . " name=\"{$firstName} {$lastName}\""
               . " program_id=" . ($programId ?? 'NULL')
               . " admit_year=" . ($admitYear ?? 'NULL')
               . " academic_status={$acadStatus}");
        $stats['created']++;
        continue;
    }

    // ── APPLY ───────────────────────────────────────────────────
    try {
        $pdo->beginTransaction();

        $personNo = nextPersonNo($pdo);

        // 1. Insert person
        $pdo->prepare(
            "INSERT INTO persons (person_no, first_name, last_name, status, created_at, updated_at)
             VALUES (?, ?, ?, 'active', NOW(), NOW())"
        )->execute([$personNo, $firstName, $lastName]);
        $personId = (int)$pdo->lastInsertId();

        // 2. Insert student_program
        $pdo->prepare(
            "INSERT INTO student_programs
               (person_id, student_no, program_id, admit_year,
                academic_status, is_primary, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())"
        )->execute([$personId, $studentCode, $programId, $admitYear, $acadStatus]);

        // 3. Insert identity_link
        $pdo->prepare(
            "INSERT INTO identity_links
               (person_id, source_table, source_id, source_code, link_type, created_at)
             VALUES (?, 'students', ?, ?, 'student', NOW())"
        )->execute([$personId, $studentId, $studentCode]);

        $pdo->commit();

        logLine("  [OK  ] students.id={$studentId} ({$studentCode})"
               . " → person_id={$personId} person_no={$personNo}"
               . " \"{$firstName} {$lastName}\"");
        $stats['created']++;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMsg = "  [ERR ] students.id={$studentId} ({$studentCode}): " . $e->getMessage();
        logLine($errMsg);
        $stats['errors'][] = $errMsg;
    }
}

// ---------------------------------------------------------------
// Summary
// ---------------------------------------------------------------
logLine('----------------------------------------------------');
logLine("  Summary ({$mode})");
logLine("  Total students  : {$totalStudents}");
logLine("  Created         : {$stats['created']}");
logLine("  Skipped (exist) : {$stats['skipped']}");
logLine("  Errors          : " . count($stats['errors']));
if ($dryRun) {
    logLine('');
    logLine('  >>> DRY-RUN complete. No data was written to the DB. <<<');
    logLine('  >>> Run with --apply to execute.                     <<<');
}
logLine('====================================================');

if (!empty($stats['errors'])) {
    fwrite(STDERR, "\nErrors encountered:\n");
    foreach ($stats['errors'] as $err) {
        fwrite(STDERR, $err . "\n");
    }
    exit(2);
}

exit(0);
