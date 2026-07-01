<?php
/**
 * A4-Pre-2: Backfill students.year_level safely
 *
 * Populates students.year_level ONLY where a high-confidence source exists.
 * Every row that lacks reliable evidence is left NULL and reported for
 * manual registrar review — this script never guesses.
 *
 * Confidence rules (in order):
 *   1. year_level already set              -> skip (already_set)
 *   2. study_status IN (graduated, alumni) -> skip (graduated_alumni), never auto-set,
 *                                              never defaulted to 4
 *   3. student_programs.class_year (via identity_links) is set and in 1-4
 *                                           -> HIGH confidence, copy directly
 *   4. program_code = 'IPS' AND study_status = 'studying' AND no class_year
 *      conflict                            -> MEDIUM confidence, suggest year_level = 1
 *                                              — only written if --allow-ips-year1-rule
 *                                              is passed alongside --apply (this rule
 *                                              requires separate approval per the
 *                                              A4-Pre-2 task spec; it is NOT included
 *                                              in a bare --apply run)
 *   5. program_id IS NULL                  -> skip (insufficient_data)
 *   6. everything else (active BS/BD/TD student, no class_year)
 *                                           -> skip (manual_review)
 *
 * Explicitly NOT used as a source, by design:
 *   - students.admission_year vs current academic_years (proven unreliable —
 *     see A4-Pre-2 report: produces a contradiction against real data)
 *   - any parsing of students.student_code
 *
 * CLI only. Supports --dry-run and --apply.
 * --apply requires YEAR_LEVEL_BACKFILL_CONFIRM=YES in the environment.
 * Idempotent: only ever considers rows where year_level IS NULL.
 *
 * Usage:
 *   php scripts/backfill_student_year_level.php --dry-run
 *   YEAR_LEVEL_BACKFILL_CONFIRM=YES php scripts/backfill_student_year_level.php --apply
 *   YEAR_LEVEL_BACKFILL_CONFIRM=YES php scripts/backfill_student_year_level.php --apply --allow-ips-year1-rule
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// CLI guard
// ---------------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "ERROR: This script must be run from the command line only.\n");
    exit(1);
}

// ---------------------------------------------------------------
// Parse arguments
// ---------------------------------------------------------------
$dryRun          = in_array('--dry-run', $argv, true);
$apply           = in_array('--apply', $argv, true);
$allowIpsYear1   = in_array('--allow-ips-year1-rule', $argv, true);

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/backfill_student_year_level.php --dry-run\n");
    fwrite(STDERR, "  YEAR_LEVEL_BACKFILL_CONFIRM=YES php scripts/backfill_student_year_level.php --apply\n");
    fwrite(STDERR, "  YEAR_LEVEL_BACKFILL_CONFIRM=YES php scripts/backfill_student_year_level.php --apply --allow-ips-year1-rule\n");
    exit(1);
}
if ($dryRun && $apply) {
    fwrite(STDERR, "ERROR: cannot use both --dry-run and --apply simultaneously.\n");
    exit(1);
}

// ---------------------------------------------------------------
// Environment / production guard
// ---------------------------------------------------------------
define('APP_ENV', getenv('APP_ENV') ?: 'local');

if ($apply) {
    if (getenv('YEAR_LEVEL_BACKFILL_CONFIRM') !== 'YES') {
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  ╔══════════════════════════════════════════════════════════════╗\n");
        fwrite(STDERR, "  ║  ⚠  Confirmation required to write students.year_level        ║\n");
        fwrite(STDERR, "  ╚══════════════════════════════════════════════════════════════╝\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  Set YEAR_LEVEL_BACKFILL_CONFIRM=YES to run --apply.\n");
        fwrite(STDERR, "  Example:\n");
        fwrite(STDERR, "    YEAR_LEVEL_BACKFILL_CONFIRM=YES php scripts/backfill_student_year_level.php --apply\n\n");
        exit(1);
    }
    if (APP_ENV === 'production') {
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  ╔══════════════════════════════════════════════════════════════╗\n");
        fwrite(STDERR, "  ║  ⚠  APP_ENV=production — proceeding with confirmed backfill   ║\n");
        fwrite(STDERR, "  ╚══════════════════════════════════════════════════════════════╝\n");
        fwrite(STDERR, "  Back up the database first: bash scripts/backup_database.sh\n\n");
    }
}

// ---------------------------------------------------------------
// Bootstrap: DB only (no session/auth)
// ---------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config/database.php';
// $pdo is now available

$mode = $dryRun ? 'DRY-RUN' : 'APPLY';

function logLine(string $line): void { echo $line . "\n"; }

// ---------------------------------------------------------------
// Guard: required columns/tables exist
// ---------------------------------------------------------------
$colExists = $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'year_level'"
)->fetchColumn();
if (!$colExists) {
    fwrite(STDERR, "ERROR: students.year_level column missing.\n");
    fwrite(STDERR, "Run database/migrations/0005_students_add_year_level.sql first.\n");
    exit(1);
}

logLine('====================================================');
logLine("  DCI-SIS Backfill students.year_level — Mode: {$mode}");
logLine('  IPS year-1 rule: ' . ($allowIpsYear1 ? 'ENABLED (--allow-ips-year1-rule)' : 'disabled (report-only)'));
logLine('  Started : ' . date('Y-m-d H:i:s'));
logLine('====================================================');

// ---------------------------------------------------------------
// Fetch candidates: only students where year_level IS NULL
// (idempotency: already-set rows are structurally excluded)
// ---------------------------------------------------------------
$students = $pdo->query(
    "SELECT
        s.id, s.student_code, s.program_id, s.study_status, s.year_level,
        p.program_code,
        sp.class_year
     FROM students s
     LEFT JOIN programs p ON p.id = s.program_id
     LEFT JOIN identity_links il ON il.source_table = 'students' AND il.source_id = s.id
     LEFT JOIN student_programs sp ON sp.person_id = il.person_id AND sp.is_primary = 1
     ORDER BY s.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$totalStudents = count($students);
logLine("Total students : {$totalStudents}");
logLine('----------------------------------------------------');

$stats = [
    'already_set'              => 0,
    'candidates'                => 0,
    'updated'                   => 0,
    'skipped_manual_review'     => 0,
    'skipped_graduated_alumni'  => 0,
    'skipped_insufficient_data' => 0,
    'pending_approval_ips_rule' => 0,
    'errors'                    => [],
];

$updateStmt = $pdo->prepare("UPDATE students SET year_level = ? WHERE id = ? AND year_level IS NULL");

foreach ($students as $s) {
    $id          = (int)$s['id'];
    $code        = (string)$s['student_code'];
    $programCode = $s['program_code'];
    $status      = (string)$s['study_status'];
    $classYear   = $s['class_year'];

    // Rule 1: already set
    if ($s['year_level'] !== null) {
        logLine("  [SKIP] {$code} → year_level already set ({$s['year_level']})");
        $stats['already_set']++;
        continue;
    }

    // Rule 5: no program at all
    if ($s['program_id'] === null) {
        logLine("  [SKIP] {$code} → insufficient_data (no program_id)");
        $stats['skipped_insufficient_data']++;
        continue;
    }

    // Rule 2: graduated/alumni — never auto-set, never defaulted to 4
    if (in_array($status, ['graduated', 'alumni'], true)) {
        logLine("  [SKIP] {$code} → graduated_alumni (study_status={$status}); not auto-backfilled");
        $stats['skipped_graduated_alumni']++;
        continue;
    }

    // Rule 3: high confidence — student_programs.class_year in 1-4
    if ($classYear !== null && in_array((string)$classYear, ['1', '2', '3', '4'], true)) {
        $value = (int)$classYear;
        $stats['candidates']++;

        if ($dryRun) {
            logLine("  [DRY ] {$code} → would SET year_level={$value} (source: student_programs.class_year)");
            continue;
        }

        try {
            $pdo->beginTransaction();
            $updateStmt->execute([$value, $id]);
            $pdo->commit();
            logLine("  [OK  ] {$code} → year_level={$value} (source: student_programs.class_year)");
            $stats['updated']++;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "  [ERR ] {$code}: " . $e->getMessage();
            logLine($err);
            $stats['errors'][] = $err;
        }
        continue;
    }

    // Rule 4: IPS + studying + no class_year conflict → medium confidence,
    // requires --allow-ips-year1-rule to actually write
    if ($programCode === 'IPS' && $status === 'studying' && $classYear === null) {
        if (!$allowIpsYear1) {
            logLine("  [PEND] {$code} → IPS + studying, suggest year_level=1, "
                   . "NOT written (pass --allow-ips-year1-rule to enable this rule)");
            $stats['pending_approval_ips_rule']++;
            continue;
        }

        $value = 1;
        $stats['candidates']++;

        if ($dryRun) {
            logLine("  [DRY ] {$code} → would SET year_level={$value} (source: IPS+studying rule, approved)");
            continue;
        }

        try {
            $pdo->beginTransaction();
            $updateStmt->execute([$value, $id]);
            $pdo->commit();
            logLine("  [OK  ] {$code} → year_level={$value} (source: IPS+studying rule, approved)");
            $stats['updated']++;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = "  [ERR ] {$code}: " . $e->getMessage();
            logLine($err);
            $stats['errors'][] = $err;
        }
        continue;
    }

    // Rule 6: everything else — active BS/BD/TD (or unclassified) student
    // with no reliable year source. Never guessed.
    logLine("  [SKIP] {$code} → manual_review (program={$programCode}, status={$status}, no class_year)");
    $stats['skipped_manual_review']++;
}

// ---------------------------------------------------------------
// Summary
// ---------------------------------------------------------------
logLine('----------------------------------------------------');
logLine("  Summary ({$mode})");
logLine("  Total students             : {$totalStudents}");
logLine("  year_level already set     : {$stats['already_set']}");
logLine("  Candidates to update       : {$stats['candidates']}");
logLine("  Updated                    : {$stats['updated']}");
logLine("  Skipped manual_review      : {$stats['skipped_manual_review']}");
logLine("  Skipped graduated/alumni   : {$stats['skipped_graduated_alumni']}");
logLine("  Skipped insufficient_data  : {$stats['skipped_insufficient_data']}");
logLine("  Pending approval (IPS rule): {$stats['pending_approval_ips_rule']}");
logLine("  Errors                     : " . count($stats['errors']));
if ($dryRun) {
    logLine('');
    logLine('  >>> DRY-RUN complete. No data was written to the DB. <<<');
    logLine('  >>> Run with --apply (+ YEAR_LEVEL_BACKFILL_CONFIRM=YES) to execute. <<<');
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
