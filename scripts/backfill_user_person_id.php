<?php
/**
 * Phase Identity-3b: Update users.person_id from identity_links
 *
 * Lookup chain:
 *   student role : users.id → students.user_id → identity_links(source='students') → person_id
 *   professor role: users.id → staff.user_id   → identity_links(source='staff')    → person_id
 *   others        : person_id stays NULL (no person yet)
 *
 * Idempotent: only updates rows where person_id IS NULL.
 * Requires identity_v2.sql to have been applied (users.person_id column must exist).
 *
 * Usage:
 *   php scripts/backfill_user_person_id.php --dry-run
 *   php scripts/backfill_user_person_id.php --apply
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "ERROR: CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$apply  = in_array('--apply',   $argv, true);

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Usage:\n  php scripts/backfill_user_person_id.php --dry-run\n  php scripts/backfill_user_person_id.php --apply\n");
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

// Check column exists
$colExists = $pdo->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'person_id'"
)->fetchColumn();

if (!$colExists) {
    fwrite(STDERR, "ERROR: users.person_id column missing.\n");
    fwrite(STDERR, "Run database/migrations/identity_v2.sql first.\n");
    exit(1);
}

logLine('====================================================');
logLine("  DCI-SIS Backfill users.person_id — Mode: {$mode}");
logLine('  Started : ' . date('Y-m-d H:i:s'));
logLine('====================================================');

$users = $pdo->query(
    "SELECT id, username, role FROM users WHERE person_id IS NULL ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

logLine("Users with person_id=NULL : " . count($users));
logLine('----------------------------------------------------');

$stats = ['updated' => 0, 'no_person' => 0, 'errors' => []];

foreach ($users as $u) {
    $userId   = (int)$u['id'];
    $username = $u['username'];
    $role     = $u['role'];

    $personId = null;

    if ($role === 'student') {
        // Find via students table
        $row = $pdo->prepare(
            "SELECT il.person_id
             FROM students s
             JOIN identity_links il ON il.source_table = 'students' AND il.source_id = s.id
             WHERE s.user_id = ?
             ORDER BY s.id ASC LIMIT 1"
        );
        $row->execute([$userId]);
        $personId = $row->fetchColumn() ?: null;

    } elseif ($role === 'professor') {
        // Find via staff table (active preferred)
        $row = $pdo->prepare(
            "SELECT il.person_id
             FROM staff s
             JOIN identity_links il ON il.source_table = 'staff' AND il.source_id = s.id
             WHERE s.user_id = ?
             ORDER BY FIELD(s.status,'active','inactive'), s.id ASC LIMIT 1"
        );
        $row->execute([$userId]);
        $personId = $row->fetchColumn() ?: null;
    }

    if ($personId === null) {
        logLine("  [SKIP] users.id={$userId} ({$username}, role={$role}) → no person found");
        $stats['no_person']++;
        continue;
    }

    if ($dryRun) {
        logLine("  [DRY ] users.id={$userId} ({$username}) → SET person_id={$personId}");
        $stats['updated']++;
        continue;
    }

    try {
        $pdo->prepare("UPDATE users SET person_id = ? WHERE id = ? AND person_id IS NULL")
            ->execute([$personId, $userId]);
        logLine("  [OK  ] users.id={$userId} ({$username}) → person_id={$personId}");
        $stats['updated']++;
    } catch (PDOException $e) {
        $err = "  [ERR ] users.id={$userId}: " . $e->getMessage();
        logLine($err);
        $stats['errors'][] = $err;
    }
}

logLine('----------------------------------------------------');
logLine("  Summary ({$mode})");
logLine("  Updated         : {$stats['updated']}");
logLine("  No person found : {$stats['no_person']}");
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
