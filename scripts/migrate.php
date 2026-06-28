#!/usr/bin/env php
<?php
/**
 * DCI-SIS Migration Runner
 * Tracks applied migrations in the `database_migrations` table.
 *
 * Commands:
 *   php scripts/migrate.php status             — all migrations (applied / pending / checksum)
 *   php scripts/migrate.php pending            — pending migrations only
 *   php scripts/migrate.php migrate --dry-run  — preview pending, no writes
 *   php scripts/migrate.php migrate --apply    — apply all pending migrations
 *   php scripts/migrate.php checksum           — verify checksums of applied migrations
 *
 * Environment:
 *   APP_ENV         — local | staging | test | production  (default: local)
 *   MIGRATE_CONFIRM — must be YES to apply in production
 *   MYSQL_BIN       — override mysql CLI path (MAMP: /Applications/MAMP/Library/bin/mysql80/bin/mysql)
 *   DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS — same as config/database.php
 *
 * See docs/migrations.md for full workflow guide.
 */

// =============================================================================
// Bootstrap
// =============================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line only.' . PHP_EOL);
}

define('APP_ENV',   getenv('APP_ENV') ?: 'local');
define('APP_DEBUG', APP_ENV !== 'production');
define('APP_BASE',  '/dci-sis');

const TRACKING_TABLE = 'database_migrations';

$projectRoot   = dirname(__DIR__);
$migrationsDir = $projectRoot . '/database/migrations';

// =============================================================================
// Argument parsing
// =============================================================================

$command = $argv[1] ?? null;
$dryRun  = in_array('--dry-run', $argv, true);
$doApply = in_array('--apply', $argv, true);

$usage = <<<'USAGE'

DCI-SIS Migration Runner

Commands:
  status              Show all migrations (applied / pending / checksum status)
  pending             List pending migrations only
  migrate --dry-run   Preview pending migrations without writing to DB
  migrate --apply     Apply all pending migrations
  checksum            Verify SHA-256 checksums of applied migrations

Environment variables:
  APP_ENV=production  Requires MIGRATE_CONFIRM=YES for migrate --apply
  MYSQL_BIN=<path>    Override mysql CLI binary (MAMP users: set this)

Examples:
  APP_ENV=local DB_PORT=8889 php scripts/migrate.php status
  APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --dry-run
  APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --apply

  # Production (always backup first):
  bash scripts/backup_database.sh
  APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php migrate --dry-run
  APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php migrate --apply

See docs/migrations.md for the complete workflow guide.

USAGE;

$validCommands = ['status', 'pending', 'migrate', 'checksum'];
if (!in_array($command, $validCommands, true)) {
    echo $usage;
    exit(0);
}

if ($command === 'migrate' && !$dryRun && !$doApply) {
    fwrite(STDERR, "[ERROR] 'migrate' requires --dry-run or --apply\n\n");
    fwrite(STDERR, "  php scripts/migrate.php migrate --dry-run\n");
    fwrite(STDERR, "  php scripts/migrate.php migrate --apply\n\n");
    exit(1);
}

// =============================================================================
// Production guard (write operations only)
// =============================================================================

if (APP_ENV === 'production' && $command === 'migrate' && $doApply) {
    if (getenv('MIGRATE_CONFIRM') !== 'YES') {
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  ╔══════════════════════════════════════════════════════════════╗\n");
        fwrite(STDERR, "  ║  ⚠  Production migration — confirmation required             ║\n");
        fwrite(STDERR, "  ╚══════════════════════════════════════════════════════════════╝\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  APP_ENV=production detected.\n");
        fwrite(STDERR, "  Before applying migrations in production:\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "    1. Back up the database:\n");
        fwrite(STDERR, "         bash scripts/backup_database.sh\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "    2. Dry-run to see pending migrations:\n");
        fwrite(STDERR, "         APP_ENV=production MIGRATE_CONFIRM=YES \\\n");
        fwrite(STDERR, "           php scripts/migrate.php migrate --dry-run\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "    3. Apply with confirmation:\n");
        fwrite(STDERR, "         APP_ENV=production MIGRATE_CONFIRM=YES \\\n");
        fwrite(STDERR, "           php scripts/migrate.php migrate --apply\n");
        fwrite(STDERR, "\n");
        fwrite(STDERR, "  See docs/migrations.md for full production checklist.\n\n");
        exit(1);
    }
}

// =============================================================================
// DB connection
// =============================================================================

$configFile = $projectRoot . '/config/database.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "[ERROR] config/database.php not found. Run from the project root.\n");
    exit(1);
}
require $configFile;
// $pdo is now available

// =============================================================================
// Helpers
// =============================================================================

function ensure_tracking_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `" . TRACKING_TABLE . "` (
        `id`                int          NOT NULL AUTO_INCREMENT,
        `migration`         varchar(255) NOT NULL              COMMENT 'filename, e.g. identity_v1.sql',
        `checksum`          varchar(64)  NOT NULL              COMMENT 'SHA-256 of file at apply time',
        `batch`             int          NOT NULL DEFAULT 1    COMMENT 'Migrations applied in the same run share a batch number',
        `execution_time_ms` int          NOT NULL DEFAULT 0,
        `applied_at`        timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_dm_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
      COMMENT='Tracks applied database migrations — managed by scripts/migrate.php'
    ");
}

function discover_migrations(string $dir): array
{
    if (!is_dir($dir)) {
        fwrite(STDERR, "[ERROR] Migrations directory not found: {$dir}\n");
        exit(1);
    }
    $files = glob($dir . '/*.sql') ?: [];
    // Exclude hidden files and temp backups (starting with '.' or '_')
    $files = array_filter($files, fn($f) => preg_match('/^[^._]/', basename($f)));
    sort($files);          // alphabetical — see docs/migrations.md for naming convention
    return array_values($files);
}

function load_applied(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT migration, checksum, batch, applied_at FROM ' . TRACKING_TABLE . ' ORDER BY id ASC'
    )->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[$row['migration']] = $row;
    }
    return $map;
}

function file_checksum(string $filePath): string
{
    return hash_file('sha256', $filePath);
}

function next_batch(PDO $pdo): int
{
    $max = $pdo->query('SELECT MAX(batch) FROM ' . TRACKING_TABLE)->fetchColumn();
    return $max === null ? 1 : (int)$max + 1;
}

function record_migration(PDO $pdo, string $migration, string $checksum, int $batch, int $timeMs): void
{
    $pdo->prepare(
        'INSERT INTO ' . TRACKING_TABLE . ' (migration, checksum, batch, execution_time_ms) VALUES (?, ?, ?, ?)'
    )->execute([$migration, $checksum, $batch, $timeMs]);
}

function find_mysql_bin(): string
{
    // 1. Explicit env override
    $envBin = getenv('MYSQL_BIN');
    if ($envBin !== false && $envBin !== '' && is_executable($envBin)) {
        return $envBin;
    }
    // 2. mysql in PATH
    $which = trim((string)(shell_exec('which mysql 2>/dev/null') ?: ''));
    if ($which !== '' && is_executable($which)) {
        return $which;
    }
    // 3. Common MAMP location (MySQL 8.0)
    $mamp = '/Applications/MAMP/Library/bin/mysql80/bin/mysql';
    if (is_executable($mamp)) {
        return $mamp;
    }
    fwrite(STDERR, "[ERROR] mysql CLI binary not found.\n");
    fwrite(STDERR, "  Set MYSQL_BIN to the full path and retry.\n");
    fwrite(STDERR, "  MAMP: export MYSQL_BIN=/Applications/MAMP/Library/bin/mysql80/bin/mysql\n");
    exit(1);
}

/**
 * Execute a .sql file via the MySQL CLI binary.
 * Uses a temp credentials file (never exposes password in process list).
 * Returns ['success'=>bool, 'stderr'=>string, 'time_ms'=>int].
 */
function execute_sql_file(string $filePath): array
{
    $mysqlBin = find_mysql_bin();

    // Read DB connection info from env (same variables as config/database.php)
    $dbHost    = getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1';
    $dbPort    = getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306';
    $dbName    = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'dci_sis';
    $dbUser    = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
    $dbPassEnv = getenv('DB_PASS');
    $dbPass    = $dbPassEnv !== false ? $dbPassEnv : 'root';

    // Write temporary credentials file — avoids password in process list
    $confFile = tempnam(sys_get_temp_dir(), 'dci_mig_');
    if ($confFile === false) {
        return ['success' => false, 'stderr' => 'Failed to create temp credentials file', 'time_ms' => 0];
    }
    chmod($confFile, 0600);
    file_put_contents($confFile, "[client]\npassword={$dbPass}\n");

    $startMs = (int)(microtime(true) * 1000);

    $cmd = implode(' ', [
        escapeshellarg($mysqlBin),
        '--defaults-extra-file=' . escapeshellarg($confFile),
        '--host=' . escapeshellarg($dbHost),
        '--port=' . escapeshellarg($dbPort),
        '--user=' . escapeshellarg($dbUser),
        escapeshellarg($dbName),
    ]);

    $descriptors = [
        0 => ['file', $filePath, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($process)) {
        unlink($confFile);
        return ['success' => false, 'stderr' => 'proc_open failed — cannot start mysql process', 'time_ms' => 0];
    }

    $stdout  = stream_get_contents($pipes[1]);
    $stderr  = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    unlink($confFile);

    $timeMs = (int)(microtime(true) * 1000) - $startMs;

    $combined = trim($stderr . ($stdout !== '' ? "\n{$stdout}" : ''));

    return [
        'success' => $exitCode === 0,
        'stderr'  => $combined,
        'time_ms' => $timeMs,
    ];
}

// =============================================================================
// Checksum mismatch check (shared between status and apply)
// Returns array of filenames with mismatched checksums.
// =============================================================================

function detect_mismatches(array $files, array $applied): array
{
    $mismatches = [];
    foreach ($files as $filePath) {
        $name = basename($filePath);
        if (!isset($applied[$name])) {
            continue;
        }
        if ($applied[$name]['checksum'] !== file_checksum($filePath)) {
            $mismatches[] = $name;
        }
    }
    return $mismatches;
}

// =============================================================================
// Command: status
// =============================================================================

function cmd_status(array $files, array $applied): void
{
    $pending    = 0;
    $mismatches = 0;

    echo "\n";
    printf("%-46s  %-10s  %-5s  %s\n", 'MIGRATION', 'STATUS', 'BATCH', 'APPLIED AT');
    echo str_repeat('─', 84) . "\n";

    if (empty($files)) {
        echo "  (no .sql files found in database/migrations/)\n";
    }

    foreach ($files as $filePath) {
        $name = basename($filePath);
        if (isset($applied[$name])) {
            $row      = $applied[$name];
            $current  = file_checksum($filePath);
            $mismatch = $row['checksum'] !== $current;
            $status   = $mismatch ? '⚠ MODIFIED' : 'applied';
            if ($mismatch) {
                $mismatches++;
            }
            printf("%-46s  %-10s  %-5s  %s\n", $name, $status, $row['batch'], $row['applied_at']);
        } else {
            printf("%-46s  %-10s  %-5s  %s\n", $name, 'pending', '—', '—');
            $pending++;
        }
    }

    // Applied migrations whose files are gone (deleted after apply)
    foreach ($applied as $name => $row) {
        $stillExists = false;
        foreach ($files as $fp) {
            if (basename($fp) === $name) {
                $stillExists = true;
                break;
            }
        }
        if (!$stillExists) {
            printf("%-46s  %-10s  %-5s  %s\n", $name, '⚠ FILE GONE', $row['batch'], $row['applied_at']);
            $mismatches++;
        }
    }

    echo str_repeat('─', 84) . "\n";
    printf("  Total: %d  |  Applied: %d  |  Pending: %d", count($files), count($applied), $pending);
    if ($mismatches > 0) {
        printf("  |  ⚠ Problems: %d (run: checksum)", $mismatches);
    }
    echo "\n\n";

    if ($pending > 0) {
        echo "  Run 'migrate --dry-run' to preview, then 'migrate --apply' to apply.\n\n";
    }
}

// =============================================================================
// Command: pending
// =============================================================================

function cmd_pending(array $files, array $applied): void
{
    $found = false;
    echo "\nPending migrations:\n";
    foreach ($files as $filePath) {
        if (!isset($applied[basename($filePath)])) {
            $name = basename($filePath);
            $size = number_format(filesize($filePath));
            echo "  - {$name}  ({$size} bytes)\n";
            $found = true;
        }
    }
    if (!$found) {
        echo "  (none — database is up to date)\n";
    }
    echo "\n";
}

// =============================================================================
// Command: migrate --dry-run
// =============================================================================

function cmd_migrate_dry(array $files, array $applied): void
{
    $pending = [];
    foreach ($files as $filePath) {
        if (!isset($applied[basename($filePath)])) {
            $pending[] = $filePath;
        }
    }

    echo "\n[DRY-RUN] Pending migrations that would be applied:\n\n";

    if (empty($pending)) {
        echo "  (none — database is up to date)\n\n";
        return;
    }

    foreach ($pending as $i => $filePath) {
        $name = basename($filePath);
        $size = number_format(filesize($filePath));
        printf("  %d. %-44s (%s bytes)\n", $i + 1, $name, $size);
    }

    echo "\n  To apply: php scripts/migrate.php migrate --apply\n\n";
}

// =============================================================================
// Command: migrate --apply
// =============================================================================

function cmd_migrate_apply(PDO $pdo, array $files, array $applied): void
{
    // Abort if any already-applied migration file has been modified
    $mismatches = detect_mismatches($files, $applied);
    if (!empty($mismatches)) {
        fwrite(STDERR, "\n[ERROR] Checksum mismatch — migration files modified after apply:\n");
        foreach ($mismatches as $name) {
            fwrite(STDERR, "    ⚠  {$name}\n");
        }
        fwrite(STDERR, "\n  Applied migrations must NOT be edited.\n");
        fwrite(STDERR, "  If you need a schema change, create a NEW migration file.\n");
        fwrite(STDERR, "  Inspect: php scripts/migrate.php checksum\n\n");
        exit(1);
    }

    $pending = [];
    foreach ($files as $filePath) {
        if (!isset($applied[basename($filePath)])) {
            $pending[] = $filePath;
        }
    }

    if (empty($pending)) {
        echo "\n[OK] Nothing to migrate — database is up to date.\n\n";
        return;
    }

    $batch = next_batch($pdo);
    $appliedCount = 0;

    echo "\n";
    echo "[APPLY] Batch #{$batch} — " . count($pending) . " pending migration(s)\n\n";

    foreach ($pending as $filePath) {
        $name     = basename($filePath);
        $checksum = file_checksum($filePath);

        echo "  → {$name} ... ";
        flush();

        $result = execute_sql_file($filePath);

        if ($result['success']) {
            record_migration($pdo, $name, $checksum, $batch, $result['time_ms']);
            echo "OK ({$result['time_ms']} ms)\n";
            $appliedCount++;
        } else {
            echo "FAILED\n\n";
            fwrite(STDERR, "[ERROR] Migration failed: {$name}\n");
            if ($result['stderr'] !== '') {
                fwrite(STDERR, "  MySQL output:\n");
                foreach (explode("\n", $result['stderr']) as $line) {
                    if (trim($line) !== '') {
                        fwrite(STDERR, "    " . $line . "\n");
                    }
                }
            }
            fwrite(STDERR, "\n  ── Migration halted ──\n");
            fwrite(STDERR, "  Applied in this run: {$appliedCount}\n");
            fwrite(STDERR, "  Remaining migrations have NOT been run.\n");
            fwrite(STDERR, "  Recovery options:\n");
            fwrite(STDERR, "    1. Fix the SQL and re-run 'migrate --apply'\n");
            fwrite(STDERR, "    2. Use rollback SQL in the migration file (if present)\n");
            fwrite(STDERR, "    3. Restore from backup: bash scripts/restore_database.sh <file>\n");
            fwrite(STDERR, "  See docs/migrations.md — Rollback section\n\n");
            exit(1);
        }
    }

    echo "\n[RESULT] Applied: {$appliedCount} | Batch: #{$batch} | Status: up to date\n\n";
}

// =============================================================================
// Command: checksum
// =============================================================================

function cmd_checksum(array $files, array $applied): void
{
    echo "\nChecksum verification of applied migrations:\n\n";

    $ok         = 0;
    $mismatches = 0;

    foreach ($files as $filePath) {
        $name = basename($filePath);
        if (!isset($applied[$name])) {
            continue;
        }

        $stored  = $applied[$name]['checksum'];
        $current = file_checksum($filePath);

        if ($stored === $current) {
            printf("  [OK]       %s\n", $name);
            printf("             sha256: %s\n", $stored);
            $ok++;
        } else {
            printf("  [MISMATCH] %s\n", $name);
            printf("    stored : %s\n", $stored);
            printf("    current: %s\n", $current);
            $mismatches++;
        }
    }

    if ($ok === 0 && $mismatches === 0) {
        echo "  (no applied migrations to verify)\n";
        echo "  Run 'migrate --apply' first to apply pending migrations.\n";
    }

    echo "\n";
    printf("  OK: %d  |  Mismatches: %d\n\n", $ok, $mismatches);

    if ($mismatches > 0) {
        echo "  ⚠  Applied migration files have been modified since they were run.\n";
        echo "     If changes are intentional, create a NEW migration file instead.\n\n";
        exit(1);
    }
}

// =============================================================================
// Main dispatch
// =============================================================================

ensure_tracking_table($pdo);
$files   = discover_migrations($migrationsDir);
$applied = load_applied($pdo);

switch ($command) {
    case 'status':
        cmd_status($files, $applied);
        break;

    case 'pending':
        cmd_pending($files, $applied);
        break;

    case 'migrate':
        if ($dryRun) {
            cmd_migrate_dry($files, $applied);
        } else {
            cmd_migrate_apply($pdo, $files, $applied);
        }
        break;

    case 'checksum':
        cmd_checksum($files, $applied);
        break;
}
