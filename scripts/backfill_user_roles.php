<?php
/**
 * Phase Identity-4: Seed user_roles from users.role
 *
 * Maps each user's current users.role → one user_roles record.
 * For student role: also sets scope_type='student_program' + scope_id
 *   when a student_programs record can be found via identity_links.
 *
 * Idempotent: skips users already having a matching (user_id, role) row.
 * Does NOT change users.role — existing auth flow is untouched.
 *
 * Usage:
 *   php scripts/backfill_user_roles.php --dry-run
 *   php scripts/backfill_user_roles.php --apply
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "ERROR: CLI only.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv, true);
$apply  = in_array('--apply',   $argv, true);

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Usage:\n  php scripts/backfill_user_roles.php --dry-run\n  php scripts/backfill_user_roles.php --apply\n");
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

foreach (['user_roles', 'persons', 'student_programs', 'identity_links'] as $tbl) {
    if ($pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetchColumn() === false) {
        fwrite(STDERR, "ERROR: Table `{$tbl}` missing. Run identity_v1.sql first.\n");
        exit(1);
    }
}

logLine('====================================================');
logLine("  DCI-SIS Backfill user_roles — Mode: {$mode}");
logLine('  Started : ' . date('Y-m-d H:i:s'));
logLine('====================================================');

$users = $pdo->query(
    "SELECT id, username, role FROM users ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

logLine("Users found: " . count($users));
logLine('----------------------------------------------------');

$stats = ['created' => 0, 'skipped' => 0, 'errors' => []];

$checkExisting = $pdo->prepare(
    "SELECT id FROM user_roles WHERE user_id = ? AND role = ? LIMIT 1"
);

// Lookup student_programs.id via users → students → identity_links → student_programs
$findStudentProgram = $pdo->prepare(
    "SELECT sp.id
     FROM students s
     JOIN identity_links il ON il.source_table = 'students' AND il.source_id = s.id
     JOIN student_programs sp ON sp.person_id = il.person_id
     WHERE s.user_id = ?
     ORDER BY sp.is_primary DESC, sp.id ASC
     LIMIT 1"
);

foreach ($users as $u) {
    $userId   = (int)$u['id'];
    $username = $u['username'];
    $role     = $u['role'];

    $checkExisting->execute([$userId, $role]);
    if ($checkExisting->fetchColumn() !== false) {
        logLine("  [SKIP] users.id={$userId} ({$username}, role={$role}) → already in user_roles");
        $stats['skipped']++;
        continue;
    }

    // Determine scope for student role
    $scopeType = null;
    $scopeId   = null;

    if ($role === 'student') {
        $findStudentProgram->execute([$userId]);
        $spId = $findStudentProgram->fetchColumn();
        if ($spId !== false) {
            $scopeType = 'student_program';
            $scopeId   = (int)$spId;
        }
    }

    $scopeDesc = $scopeType ? "scope={$scopeType}:{$scopeId}" : 'scope=none';

    if ($dryRun) {
        logLine("  [DRY ] users.id={$userId} ({$username}) → role={$role} {$scopeDesc}");
        $stats['created']++;
        continue;
    }

    try {
        $pdo->prepare(
            "INSERT INTO user_roles
               (user_id, role, scope_type, scope_id, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'active', NOW(), NOW())"
        )->execute([$userId, $role, $scopeType, $scopeId]);

        logLine("  [OK  ] users.id={$userId} ({$username}) → role={$role} {$scopeDesc}");
        $stats['created']++;
    } catch (PDOException $e) {
        $err = "  [ERR ] users.id={$userId}: " . $e->getMessage();
        logLine($err);
        $stats['errors'][] = $err;
    }
}

logLine('----------------------------------------------------');
logLine("  Summary ({$mode})");
logLine("  Total users     : " . count($users));
logLine("  Created         : {$stats['created']}");
logLine("  Skipped (exist) : {$stats['skipped']}");
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
