# DCI-SIS Database Migration Guide

**For:** Developers, DBAs, Deploy team  
**Tool:** `scripts/migrate.php`  
**Last reviewed:** 2026-06-28

---

## 1. Purpose

The migration runner tracks which SQL changes have been applied to the database
and provides a controlled, auditable way to evolve the schema across environments.

| Without migration runner | With migration runner |
|--------------------------|----------------------|
| Manual `mysql < file.sql` — no record | Tracked in `database_migrations` table |
| Unknown DB state on new environments | `status` command shows exact state |
| Risk of re-running already-applied changes | Runner skips applied migrations |
| No tamper detection | SHA-256 checksum on every applied file |

The runner does **not** auto-rollback. All migrations in this project are additive
(CREATE TABLE IF NOT EXISTS, ADD INDEX IF NOT EXISTS). Recovery is via backup restore
or manual rollback SQL — see [Section 8 — Rollback Policy](#8-rollback-policy).

---

## 2. How It Works

### `database_migrations` table

Created automatically on first run. Never modify this table manually.

| Column | Type | Description |
|--------|------|-------------|
| `id` | int PK | Auto increment |
| `migration` | varchar(255) UNIQUE | Filename, e.g. `identity_v1.sql` |
| `checksum` | varchar(64) | SHA-256 of the file at apply time |
| `batch` | int | All migrations applied in one run share a batch number |
| `execution_time_ms` | int | Milliseconds to execute |
| `applied_at` | timestamp | When applied |

### Migration discovery

The runner reads all `.sql` files from `database/migrations/`, sorted alphabetically.
Files starting with `.` or `_` are excluded.

**Current sorting order:**
```
identity_v1.sql             ← i < p
identity_v2.sql
performance_indexes_v1.sql
```

### SQL execution

Each migration file is passed to the MySQL CLI binary (not PDO exec).
This handles any valid MySQL syntax including PREPARE/EXECUTE blocks and
multi-statement files. No SQL parsing is done in PHP.

---

## 3. Naming Convention

### Current files (legacy convention)

```
identity_v1.sql            — feature + version suffix
identity_v2.sql
performance_indexes_v1.sql
```

**Problem:** alphabetical order ≠ chronological order for new features.
`enrollment_fix_v1.sql` would sort before `identity_v1.sql` (e < i).

### Recommended convention for new migrations

Use a zero-padded sequence prefix to guarantee ordering:

```
0001_identity_initial.sql
0002_identity_add_person_id.sql
0003_performance_indexes.sql
0004_enrollment_status_index.sql   ← sorts correctly after 0003
```

Or use a timestamp prefix (ISO-8601, no hyphens/colons):

```
20260626_000001_identity_initial.sql
20260626_000002_identity_add_person_id.sql
20260626_000003_performance_indexes.sql
```

**Rule:** always choose a prefix that sorts correctly alphabetically.
Never add a migration file that alphabetically precedes an already-applied migration.

---

## 4. Commands

### `status` — Show all migrations

```bash
APP_ENV=local DB_PORT=8889 php scripts/migrate.php status
```

Output columns: MIGRATION | STATUS | BATCH | APPLIED AT

Status values:
- `applied` — in `database_migrations`, checksum matches
- `pending` — not yet in `database_migrations`
- `⚠ MODIFIED` — applied but file has changed since (checksum mismatch)
- `⚠ FILE GONE` — in tracking table but file deleted

### `pending` — List pending only

```bash
APP_ENV=local DB_PORT=8889 php scripts/migrate.php pending
```

### `migrate --dry-run` — Preview (no writes)

```bash
APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --dry-run
```

Shows which files would be applied. Does not write to DB or run any SQL.

### `migrate --apply` — Apply pending

```bash
APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --apply
```

- Checks for checksum mismatches on applied files — aborts if found
- Applies only pending migrations (not already in `database_migrations`)
- Records each migration with checksum and timing after successful execution
- Stops immediately on first failure, prints error, exits non-zero
- Subsequent migrations are NOT run after a failure

### `checksum` — Verify applied files

```bash
APP_ENV=local DB_PORT=8889 php scripts/migrate.php checksum
```

Compares the SHA-256 of each applied migration file against the stored checksum.
Exits non-zero if any mismatch is found.

---

## 5. Apply on Local (MAMP)

```bash
cd /Applications/MAMP/htdocs/dci-sis

# Check state
APP_ENV=local DB_PORT=8889 php scripts/migrate.php status

# Preview
APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --dry-run

# Apply
APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --apply

# Verify
APP_ENV=local DB_PORT=8889 php scripts/migrate.php status
```

If MySQL is not in PATH (MAMP default):

```bash
export MYSQL_BIN=/Applications/MAMP/Library/bin/mysql80/bin/mysql
APP_ENV=local DB_PORT=8889 php scripts/migrate.php migrate --apply
```

---

## 6. Apply on Staging

```bash
# Set DB credentials for staging
export DB_HOST=staging.db.host DB_PORT=3306 DB_NAME=dci_sis
export DB_USER=dci_app DB_PASS=...      # never hardcode

APP_ENV=staging php scripts/migrate.php status
APP_ENV=staging php scripts/migrate.php migrate --dry-run
APP_ENV=staging php scripts/migrate.php migrate --apply
APP_ENV=staging php scripts/migrate.php status
```

No `MIGRATE_CONFIRM` required for staging.

---

## 7. Apply on Production

**Always follow this order. Do not skip steps.**

```bash
# Step 1 — Backup (required before any production migration)
bash scripts/backup_database.sh
# → creates backups/dci_sis_YYYYMMDD_HHMMSS.sql.gz
# → verify: gunzip -t <backup_file> && echo OK

# Step 2 — Check current state
APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php status

# Step 3 — Dry-run to confirm what will be applied
APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php migrate --dry-run

# Step 4 — Apply
APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php migrate --apply

# Step 5 — Verify applied
APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php status
APP_ENV=production MIGRATE_CONFIRM=YES php scripts/migrate.php checksum

# Step 6 — Smoke test
BASE_URL=https://your-domain.ac.th/dci-sis bash scripts/smoke_check.sh
```

`APP_ENV=production` + `migrate --apply` without `MIGRATE_CONFIRM=YES` → exits with error and backup reminder.

---

## 8. Rollback Policy

The runner does **not** perform automatic rollback. MySQL DDL (CREATE TABLE, ALTER TABLE,
ADD INDEX) is not transactional and cannot be rolled back by the runner.

### Recovery options

**Option A — Restore from backup (recommended for production)**

```bash
RESTORE_CONFIRM=YES DB_NAME=dci_sis [env vars] \
  bash scripts/restore_database.sh backups/dci_sis_<timestamp>.sql.gz
```

Always take a backup before applying migrations (Step 1 above).

**Option B — Manual rollback SQL**

`performance_indexes_v1.sql` includes rollback SQL as comments at the bottom.
Copy and run the relevant `DROP INDEX` statements manually.

`identity_v1.sql` and `identity_v2.sql` do not have explicit rollback SQL.
To undo: restore from backup.

**Convention for new migrations:**

Include rollback SQL as comments in every migration file:

```sql
-- =============================================================================
-- ROLLBACK SQL (copy/paste individually to undo)
-- =============================================================================
-- ALTER TABLE `table_name` DROP COLUMN `column_name`;
-- DROP INDEX `idx_name` ON `table_name`;
```

### After failed migration

1. Note which migration failed (runner prints the name and MySQL error)
2. Check database state manually
3. Either fix the SQL and re-run, or restore backup
4. DO NOT mark the failed migration as applied manually
5. Re-run `migrate --apply` after fixing — it will retry only the failed migration

---

## 9. Writing New Migrations

### Template

```sql
-- =============================================================================
-- Migration: 0004_your_description.sql
-- Purpose:   Short description
-- Date:      YYYY-MM-DD
-- Type:      ADDITIVE ONLY / MODIFIES DATA / etc.
-- Safe:      Idempotent via IF NOT EXISTS / INFORMATION_SCHEMA guards
-- Rollback:  See rollback section at bottom
-- =============================================================================

-- Your SQL here

-- =============================================================================
-- ROLLBACK SQL (copy/paste individually to undo)
-- =============================================================================
-- DROP INDEX `idx_name` ON `table_name`;
```

### Naming

- Use sequential prefix: `0004_`, `0005_`, etc. (or timestamp)
- Always verify the name sorts AFTER all existing files alphabetically
- Use only `a-z`, `0-9`, `_`, `.` in filenames
- One logical change per file

### Safety requirements

| Requirement | Why |
|-------------|-----|
| Additive only (prefer) | Cannot auto-rollback DDL in MySQL |
| Include rollback SQL in comments | Enables manual recovery |
| Idempotent where possible | Safe to re-run on bootstrap |
| Test on local + staging first | Never first-run on production |
| Never modify an applied migration | Runner detects checksum change and aborts |

---

## 10. Checksum Warning Policy

If `status` or `checksum` shows `⚠ MODIFIED`:

1. **Do NOT apply new migrations** until the mismatch is resolved
2. Identify what changed in the file (`git diff database/migrations/<file>`)
3. If the change was unintentional: restore the original file (`git checkout -- <file>`)
4. If the change was intentional: create a NEW migration file for the additional change,
   then restore the original file

**Never run `DELETE FROM database_migrations` to reset tracking.**
Deleting tracking records means the runner will attempt to re-apply migrations —
which may cause errors if the schema already has those changes.

---

## 11. Pre-Deploy Migration Checklist

```
Pre-Deploy Migration — Date: _______  By: _______

[ ] Pre-deploy backup taken and verified:
    File: ________________________________________________
    Test: gunzip -t <file> && echo OK

[ ] php scripts/migrate.php status — no ⚠ MODIFIED or ⚠ FILE GONE

[ ] php scripts/migrate.php migrate --dry-run — pending migrations reviewed:
    Files to apply: ______________________________________

[ ] Migrations tested on staging first? [ ] Yes (required for new migrations)

[ ] php scripts/migrate.php migrate --apply — completed with 0 errors

[ ] php scripts/migrate.php status — all migrations show 'applied', Pending: 0

[ ] php scripts/migrate.php checksum — OK: N  |  Mismatches: 0

[ ] Application smoke test passed (bash scripts/smoke_check.sh)
```

---

## 12. Findings (Current Migration Files)

| Finding | Severity | Notes |
|---------|----------|-------|
| `identity_v1.sql` has no rollback SQL | Medium | Create new migration to reverse if needed |
| `identity_v2.sql` has no rollback SQL | Medium | Reverse via restore backup |
| Legacy `feature_vN.sql` naming | Low | Safe for current 3 files; new migrations should use numeric prefix |
| 3 migrations bootstrapped in batch #1 | Info | All idempotent — re-run was safe |
