<?php
/**
 * Phase Identity-2: Backfill professors from staff table → persons + identity_links
 *
 * Deduplication strategy:
 *   - Staff records with the same user_id → same person
 *   - Primary record = status='active' preferred, else lowest id
 *   - Duplicate rows for same user_id are reported and skipped
 *   - Staff with user_id=NULL → one person per record (reported separately)
 *
 * Does NOT backfill user_roles (handled by backfill_user_roles.php)
 * Does NOT update users.person_id (handled by backfill_user_person_id.php)
 *
 * Usage:
 *   php scripts/backfill_professors.php --dry-run
 *   php scripts/backfill_professors.php --apply
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "ERROR: CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$apply  = in_array('--apply',   $argv, true);

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Usage:\n  php scripts/backfill_professors.php --dry-run\n  php scripts/backfill_professors.php --apply\n");
    exit(1);
}
if ($dryRun && $apply) {
    fwrite(STDERR, "ERROR: cannot use both --dry-run and --apply.\n");
    exit(1);
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config/database.php';

$mode = $dryRun ? 'DRY-RUN' : 'APPLY';

function logLine(string $line): void { echo $line . "\n"; }

function nextPersonNo(PDO $pdo): string
{
    $stmt = $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(person_no, 4) AS UNSIGNED)), 0)
         FROM persons WHERE person_no REGEXP '^DCI[0-9]{8}$'"
    );
    return 'DCI' . str_pad((string)((int)$stmt->fetchColumn() + 1), 8, '0', STR_PAD_LEFT);
}

logLine('====================================================');
logLine("  DCI-SIS Professor Backfill — Mode: {$mode}");
logLine('  Started : ' . date('Y-m-d H:i:s'));
logLine('====================================================');

foreach (['persons', 'identity_links'] as $tbl) {
    if ($pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetchColumn() === false) {
        fwrite(STDERR, "ERROR: Table `{$tbl}` missing. Run identity_v1.sql first.\n");
        exit(1);
    }
}

$allStaff = $pdo->query(
    "SELECT id, user_id, staff_code, first_name, last_name, position, status
     FROM staff ORDER BY id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

logLine("Staff records found: " . count($allStaff));

// Group by user_id for deduplication
$byUserId = [];
$noUserId = [];
foreach ($allStaff as $s) {
    if ($s['user_id'] !== null) {
        $byUserId[(int)$s['user_id']][] = $s;
    } else {
        $noUserId[] = $s;
    }
}

$stats = ['created' => 0, 'skipped' => 0, 'duplicates' => 0, 'errors' => []];

$checkLink = $pdo->prepare(
    "SELECT person_id FROM identity_links
     WHERE source_table = 'staff' AND source_id = ?"
);

// Dry-run counter
$dryCounter = 0;
if ($dryRun) {
    $stmt = $pdo->query(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(person_no,4) AS UNSIGNED)),0)
         FROM persons WHERE person_no REGEXP '^DCI[0-9]{8}$'"
    );
    $dryCounter = (int)$stmt->fetchColumn();
}

logLine('----------------------------------------------------');

// ── Process staff grouped by user_id ────────────────────────
foreach ($byUserId as $userId => $records) {
    // Pick primary: active preferred, else lowest id
    $primary = null;
    foreach ($records as $r) {
        if ($r['status'] === 'active') { $primary = $r; break; }
    }
    if ($primary === null) { $primary = $records[0]; }

    if (count($records) > 1) {
        logLine("  [WARN] user_id={$userId} has " . count($records) . " staff records"
               . " → using staff.id={$primary['id']} (status={$primary['status']})");
        foreach ($records as $r) {
            if ((int)$r['id'] !== (int)$primary['id']) {
                logLine("    [DUP ] Skipping staff.id={$r['id']}"
                       . " staff_code={$r['staff_code']} status={$r['status']}");
                $stats['duplicates']++;
            }
        }
    }

    $staffId   = (int)$primary['id'];
    $staffCode = (string)$primary['staff_code'];
    $firstName = trim((string)$primary['first_name']);
    $lastName  = trim((string)$primary['last_name']);

    $checkLink->execute([$staffId]);
    if ($checkLink->fetchColumn() !== false) {
        logLine("  [SKIP] staff.id={$staffId} ({$staffCode}) → already linked");
        $stats['skipped']++;
        continue;
    }

    if ($dryRun) {
        $dryCounter++;
        $pno = 'DCI' . str_pad((string)$dryCounter, 8, '0', STR_PAD_LEFT);
        logLine("  [DRY ] staff.id={$staffId} ({$staffCode})"
               . " → person_no={$pno} \"{$firstName} {$lastName}\"");
        $stats['created']++;
        continue;
    }

    try {
        $pdo->beginTransaction();

        $personNo = nextPersonNo($pdo);

        $pdo->prepare(
            "INSERT INTO persons (person_no, first_name, last_name, status, created_at, updated_at)
             VALUES (?, ?, ?, 'active', NOW(), NOW())"
        )->execute([$personNo, $firstName, $lastName]);
        $personId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO identity_links
               (person_id, source_table, source_id, source_code, link_type, created_at)
             VALUES (?, 'staff', ?, ?, 'professor', NOW())"
        )->execute([$personId, $staffId, $staffCode]);

        $pdo->commit();

        logLine("  [OK  ] staff.id={$staffId} ({$staffCode})"
               . " → person_id={$personId} person_no={$personNo} \"{$firstName} {$lastName}\"");
        $stats['created']++;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = "  [ERR ] staff.id={$staffId}: " . $e->getMessage();
        logLine($err);
        $stats['errors'][] = $err;
    }
}

// ── Process staff with no user_id ────────────────────────────
foreach ($noUserId as $s) {
    $staffId   = (int)$s['id'];
    $staffCode = (string)$s['staff_code'];
    $firstName = trim((string)$s['first_name']);
    $lastName  = trim((string)$s['last_name']);

    $checkLink->execute([$staffId]);
    if ($checkLink->fetchColumn() !== false) {
        logLine("  [SKIP] staff.id={$staffId} ({$staffCode}) no-userid → already linked");
        $stats['skipped']++;
        continue;
    }

    if ($dryRun) {
        $dryCounter++;
        $pno = 'DCI' . str_pad((string)$dryCounter, 8, '0', STR_PAD_LEFT);
        logLine("  [DRY ] staff.id={$staffId} ({$staffCode}) no-userid"
               . " → person_no={$pno} \"{$firstName} {$lastName}\"");
        $stats['created']++;
        continue;
    }

    try {
        $pdo->beginTransaction();
        $personNo = nextPersonNo($pdo);
        $pdo->prepare(
            "INSERT INTO persons (person_no, first_name, last_name, status, created_at, updated_at)
             VALUES (?, ?, ?, 'active', NOW(), NOW())"
        )->execute([$personNo, $firstName, $lastName]);
        $personId = (int)$pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO identity_links
               (person_id, source_table, source_id, source_code, link_type, created_at)
             VALUES (?, 'staff', ?, ?, 'professor', NOW())"
        )->execute([$personId, $staffId, $staffCode]);
        $pdo->commit();
        logLine("  [OK  ] staff.id={$staffId} ({$staffCode}) no-userid"
               . " → person_id={$personId} person_no={$personNo}");
        $stats['created']++;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = "  [ERR ] staff.id={$staffId}: " . $e->getMessage();
        logLine($err);
        $stats['errors'][] = $err;
    }
}

// ── Warn about users with role=professor but no staff record ──
$profUsers = $pdo->query(
    "SELECT u.id, u.username
     FROM users u
     LEFT JOIN staff s ON s.user_id = u.id
     WHERE u.role = 'professor' AND s.id IS NULL"
)->fetchAll(PDO::FETCH_ASSOC);

if (!empty($profUsers)) {
    logLine('');
    logLine('  [WARN] Professor users with NO staff record (cannot create person):');
    foreach ($profUsers as $pu) {
        logLine("    users.id={$pu['id']} username={$pu['username']} → person_id will remain NULL");
    }
}

logLine('----------------------------------------------------');
logLine("  Summary ({$mode})");
logLine("  Staff records   : " . count($allStaff));
logLine("  Created         : {$stats['created']}");
logLine("  Skipped (exist) : {$stats['skipped']}");
logLine("  Duplicates skip : {$stats['duplicates']}");
logLine("  Errors          : " . count($stats['errors']));
if ($dryRun) {
    logLine('');
    logLine('  >>> DRY-RUN complete. No data written. Run --apply to execute. <<<');
}
logLine('====================================================');

if (!empty($stats['errors'])) {
    foreach ($stats['errors'] as $e) fwrite(STDERR, $e . "\n");
    exit(2);
}
exit(0);
