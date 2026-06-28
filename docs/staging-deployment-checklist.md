# DCI-SIS Staging Deployment Checklist

**For:** Developers, QA Engineers, Deploy team  
**Environment:** Staging only — NOT for production  
**Last reviewed:** 2026-06-28

> For production deployment see `docs/production-checklist.md` and `docs/production-smoke-checklist.md`  
> For migration workflow see `docs/migrations.md`  
> For seed data guide see `docs/staging-seed-data.md`  
> For backup/restore see `docs/backup-restore-plan.md`

---

## Section 0 — Gate: Before You Start

Complete ALL items before touching any server. If any item cannot be confirmed, stop and resolve it first.

```
Gate — Date: _______  Deployer: _______

[ ] Staging server is NOT the production server
    (double-check hostname, IP, DB connection string)

[ ] You have a current backup of the staging DB:
    bash scripts/backup_database.sh
    File: ___________________________________________________
    Test: gunzip -t <file> && echo OK

[ ] The commit / tag to deploy is confirmed and reviewed:
    Commit: ___________________________________________________
    Tag:    ___________________________________________________

[ ] At least one other person has reviewed the diff/release notes
    Reviewed by: _____________________________________________

Stop here unless all 4 boxes are checked.
```

---

## Section 1 — Pre-Deploy Code Review

```
Code — Date: _______

[ ] Working from the correct branch/tag:
      git branch          # confirm branch
      git log --oneline -5  # confirm commit at HEAD

[ ] git status is clean (no uncommitted changes):
      git status          # must show "nothing to commit, working tree clean"

[ ] diff vs previous release reviewed:
      git diff <prev-tag>..HEAD --stat
      git diff <prev-tag>..HEAD -- config/ includes/ scripts/ docs/

[ ] No .env, config/database.php, or secrets committed:
      git log --oneline --all -- .env config/database.php

[ ] No .sql dump or backup file in tracked files:
      git ls-files | grep -E '\.(sql|sql\.gz|bak|dump)$'
      # must return nothing

[ ] Migration files reviewed (if any new):
      ls database/migrations/
      # any new .sql file must be tested on local dev first

[ ] Seed script not changed to run in production:
      grep -n "production" scripts/seed_staging.php | head
      # must still exit if APP_ENV=production
```

---

## Section 2 — Server / Environment Setup

Run once when setting up a new staging server. Skip items already confirmed for this server.

### 2A. PHP Requirements

```
PHP — Staging server: _______  PHP version: _______

[ ] PHP 8.3 or higher:
      php -v    # must show PHP 8.3.x or higher

[ ] Required extensions installed:
      php -m | grep -E "pdo_mysql|mbstring|openssl|session"
      # must show all 4:
      #   pdo_mysql    — database connection
      #   mbstring     — string length/truncation in validation.php
      #   openssl      — CSRF token generation (random_bytes)
      #   session      — PHP session support

[ ] php.ini: display_errors = Off (for production-like base, then overridden by APP_ENV=staging)
      php -i | grep display_errors
      # APP_ENV=staging sets display_errors=On at runtime — that is intentional for staging

[ ] php.ini: log_errors = On
      php -i | grep "^log_errors"

[ ] error_log points to a writable path outside web root:
      php -i | grep "^error_log"
      # e.g. /var/log/php/dci_errors.log
```

### 2B. MySQL Requirements

```
MySQL — Version: _______

[ ] MySQL 8.0 or higher (or compatible MariaDB 10.6+):
      mysql --version

[ ] MySQL server is running:
      systemctl status mysql  # or: mysqladmin ping -u root -p

[ ] utf8mb4 charset supported:
      mysql -u root -p -e "SHOW VARIABLES LIKE 'character_set_server';"
      # must show utf8mb4
```

### 2C. Web Server

**Apache:**
```
[ ] Apache 2.4+ with mod_rewrite enabled:
      apache2 -v
      apache2ctl -M | grep rewrite

[ ] AllowOverride All enabled for the DCI-SIS directory:
      # in VirtualHost or Directory block:
      # AllowOverride All
      # Required for .htaccess to work (blocks scripts/, config/, etc.)

[ ] VirtualHost document root points to dci-sis project root:
      # DocumentRoot /var/www/dci-sis
      # (NOT /var/www/dci-sis/public — no public/ subdirectory in this project)
```

**Nginx (alternative — requires manual config, .htaccess is ignored):**
```
[ ] Nginx equivalent config in place:
      # Block sensitive dirs and files — see docs/production-checklist.md Section 7
      # location ~ ^/(config|includes|scripts|database)/ { return 403; }
      # location ~* \.(sql|log|env|ini|sh|bak)$ { return 403; }
      # autoindex off;
```

### 2D. File Permissions

```
[ ] Web root readable by web server (www-data / apache):
      ls -la /var/www/dci-sis/

[ ] No world-writable files:
      find /var/www/dci-sis -perm -002 -type f
      # must return nothing

[ ] config/database.php readable only by web server:
      chmod 640 config/database.php
      chown deploy_user:www-data config/database.php

[ ] .htaccess in place and not world-writable:
      ls -la .htaccess
      chmod 644 .htaccess

[ ] No uploads/, storage/, logs/ directory in web root (features not yet implemented):
      ls | grep -E "upload|storage|tmp"
      # must return nothing — if found, investigate before proceeding

[ ] PHP session directory is writable by web server:
      php -i | grep session.save_path
      # test: php -r "session_start(); echo 'OK';"
```

---

## Section 3 — Configuration Files

```
Config — Date: _______

[ ] config/database.php exists (not committed, must be created from example):
      ls -la config/database.php
      # if missing:
      cp config/database.example.php config/database.php
      chmod 640 config/database.php

[ ] DB env vars exported for this session (replace placeholders):
      export DB_HOST=<staging-db-host>
      export DB_PORT=3306
      export DB_NAME=dci_sis_staging        # use a staging-specific DB name
      export DB_USER=<staging-db-user>
      export DB_PASS=<staging-db-password>  # NEVER use production password here

[ ] APP_ENV set to staging (not production):
      export APP_ENV=staging
      # Verify: php -r "require 'config/session.php'; echo APP_ENV;"
      # Must print: staging

[ ] APP_TIMEZONE correct:
      export APP_TIMEZONE=Asia/Bangkok

[ ] display_errors behavior confirmed for staging:
      # APP_ENV=staging → APP_DEBUG=true → display_errors=1 at runtime
      # This is intentional for staging: errors are visible to help QA
      # Verify: php -r "require 'config/session.php'; echo ini_get('display_errors');"
      # Must print: 1 (staging shows errors)

[ ] No production DB_PASS or DB_HOST in config/database.php:
      grep -n "password\|host" config/database.php
      # must show env-var-based values, not hardcoded production strings

[ ] .env file NOT committed (gitignored):
      git ls-files | grep "^\.env$"
      # must return nothing

[ ] config/database.php NOT committed (gitignored):
      git ls-files | grep "^config/database\.php$"
      # must return nothing
```

---

## Section 4 — Database Setup

> Skip this section if upgrading an existing staging DB (go to Section 5 — Migrations).
> This section is for fresh staging DB setup only.

```
Database — Date: _______  DB: _______

[ ] Create staging database (use staging-specific name to avoid confusion):
      mysql -h ${DB_HOST} -P ${DB_PORT} -u root -p \
        -e "CREATE DATABASE IF NOT EXISTS dci_sis_staging
            CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

[ ] Create staging DB user with least-privilege:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u root -p <<SQL
        CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
        GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER
          ON dci_sis_staging.* TO '${DB_USER}'@'%';
        FLUSH PRIVILEGES;
      SQL

[ ] Import baseline schema (REQUIRED — core tables not in migration files):
      # Option A: from a sanitized local dev dump (schema + no personal data):
      mysqldump -u root -p --no-data dci_sis > /tmp/dci_sis_schema_only.sql
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging < /tmp/dci_sis_schema_only.sql

      # Option B: from existing staging backup:
      RESTORE_CONFIRM=YES DB_NAME=dci_sis_staging DB_USER=${DB_USER} \
        DB_PASS=${DB_PASS} DB_HOST=${DB_HOST} DB_PORT=${DB_PORT} \
        bash scripts/restore_database.sh <backup_file.sql.gz>

[ ] Verify DB charset is utf8mb4:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        -e "SELECT DEFAULT_CHARACTER_SET_NAME FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME='dci_sis_staging';"
      # must show utf8mb4

[ ] Verify core tables exist:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging -e "SHOW TABLES;" | wc -l
      # expect 27+ tables (28+ if database_migrations exists)

[ ] Verify no production data in staging DB:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging -e "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM students;"
      # schema-only import: must show 0 rows
      # if data present: confirm it is test data only, not real student records
```

---

## Section 5 — Migrations

```
Migrations — Date: _______

[ ] Check current migration status:
      APP_ENV=staging DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/migrate.php status
      # Note any 'pending' or '⚠ MODIFIED' entries

[ ] No checksum mismatches on applied migrations:
      APP_ENV=staging DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/migrate.php checksum
      # Must show: Mismatches: 0

[ ] Dry-run — review pending migrations before applying:
      APP_ENV=staging DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/migrate.php migrate --dry-run
      # Review: which files will be applied, in what order

[ ] Apply pending migrations:
      APP_ENV=staging DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/migrate.php migrate --apply
      # Must exit 0 with '[RESULT] Applied: N | Batch: #N | Status: up to date'

[ ] Verify final migration state:
      APP_ENV=staging DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/migrate.php status
      # Must show: Pending: 0 | no ⚠ warnings

[ ] database_migrations table exists and has rows:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging -e "SELECT migration, batch, applied_at FROM database_migrations;"
      # Must show all migration filenames

See docs/migrations.md for full migration workflow details.
```

---

## Section 6 — Seed Staging Data

> Skip this section if upgrading existing staging (data already present).  
> Run only on fresh staging or when resetting test data.

```
Seed — Date: _______

[ ] Confirm APP_ENV is not production (seed refuses to run in production):
      echo $APP_ENV    # must NOT be 'production'

[ ] Set seed password (use a staging-only throwaway password):
      export SEED_DEFAULT_PASSWORD='<staging-only-throwaway-password>'
      # NEVER use a real or production password here

[ ] Dry-run seed — preview what will be created:
      APP_ENV=staging DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/seed_staging.php --dry-run
      # Review: users and academic data that would be created

[ ] Apply seed data:
      APP_ENV=staging SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD=${SEED_DEFAULT_PASSWORD} \
        DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/seed_staging.php --apply
      # Must show: 'Seed complete | Created: N rows'

[ ] Verify test accounts exist:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging \
        -e "SELECT id, username, role FROM users WHERE username LIKE '%_test' ORDER BY id;"
      # Must show: admin_test, registrar_test, prof_test, student_test, alumni_test

[ ] Re-run seed to verify idempotency (no duplicates):
      APP_ENV=staging SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD=${SEED_DEFAULT_PASSWORD} \
        DB_PORT=${DB_PORT} DB_HOST=${DB_HOST} \
        DB_NAME=dci_sis_staging DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
        php scripts/seed_staging.php --apply
      # Must show: 'Created: 0 rows | Skipped: N rows (already existed)'

[ ] Verify no real personal data in staging:
      mysql -h ${DB_HOST} -P ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging \
        -e "SELECT student_code, first_name, last_name FROM students;"
      # Must show only 'Test Student', 'Test Alumni', and any other dev accounts
      # Must NOT show real student names or ID numbers from production

See docs/staging-seed-data.md for full seed documentation and cleanup SQL.
```

---

## Section 7 — Automated Smoke Test

```
Smoke (automated) — Date: _______  BASE_URL: _______

[ ] Set BASE_URL for this staging environment:
      export BASE_URL=https://staging.your-domain.ac.th/dci-sis
      # or:
      export BASE_URL=http://192.168.x.x/dci-sis

[ ] Run automated smoke check (no credentials required):
      BASE_URL=${BASE_URL} bash scripts/smoke_check.sh
      # Must exit 0 with '0 failed'

Checks performed automatically by smoke_check.sh:
  - login.php returns HTTP 200
  - Root / returns HTTP 302 (redirect)
  - Authenticated pages return 302 when unauthenticated (no session leakage)
  - config/database.php blocked (HTTP 403)
  - includes/auth.php blocked (HTTP 403)
  - scripts/ directory blocked (HTTP 403)
  - database/ directory blocked (HTTP 403)
  - .sql files blocked (HTTP 403)
  - X-Frame-Options: SAMEORIGIN present
  - X-Content-Type-Options: nosniff present
  - Referrer-Policy: strict-origin-when-cross-origin present
  - Permissions-Policy present
  - Directory listing disabled

[ ] All checks passed (exit 0):
      # If any FAIL: check .htaccess / web server config before proceeding

See scripts/smoke_check.sh and docs/production-smoke-checklist.md for details.
```

---

## Section 8 — Manual Smoke Test (All 5 Roles)

Use test accounts created in Section 6. Login password = `SEED_DEFAULT_PASSWORD` value used at seed time.

```
Manual smoke — Date: _______  Tester: _______  BASE_URL: _______

─── Admin (admin_test) ───────────────────────────────────────────────

[ ] Login as admin_test → /admin/dashboard.php loads without error
[ ] /admin/users.php — user list loads, test accounts visible
[ ] /admin/audit-logs.php — recent entries visible
[ ] Logout → redirected to /login.php
[ ] Demo credentials box IS visible on login page
    (APP_ENV=staging → APP_DEBUG=true → demo box shows — expected for staging)

─── Registrar (registrar_test) ───────────────────────────────────────

[ ] Login as registrar_test → /registrar/dashboard.php loads
[ ] /registrar/students.php — student list loads, S9999001 visible
[ ] /registrar/sections.php — DCI101/001 section visible
[ ] /registrar/transcripts.php — loads
[ ] Document request queue shows 2 pending requests (student_test + alumni_test)
[ ] Logout

─── Professor (prof_test) ────────────────────────────────────────────

[ ] Login as prof_test → /professor/dashboard.php loads
[ ] /professor/gradebook.php — DCI101/001 section visible
[ ] Grade list shows student_test (S9999001)
[ ] /professor/exams.php — Midterm Exam entry visible
[ ] Logout

─── Student (student_test) ───────────────────────────────────────────

[ ] Login as student_test → /student/dashboard.php loads
[ ] /student/enrollment.php — DCI101/001 section visible as enrolled
[ ] /student/grades.php — Midterm 80, Final Exam 90 visible
[ ] /student/transcript.php — grade A (4.00) for DCI101 visible
[ ] /student/requests.php — transcript request (pending) visible
[ ] Logout

─── Alumni (alumni_test) ─────────────────────────────────────────────

[ ] Login as alumni_test → /alumni/dashboard.php loads
[ ] /alumni/transcript_request.php — form accessible
[ ] Alumni document request (pending) visible
[ ] Logout

─── Cross-role verification ──────────────────────────────────────────

[ ] student_test cannot access /admin/dashboard.php → redirected to login
[ ] student_test cannot access /professor/gradebook.php → redirected or 403
[ ] prof_test cannot access /admin/users.php → redirected or 403
[ ] Alumni cannot enroll or submit grades

─── Session and cookies ──────────────────────────────────────────────

[ ] Browser DevTools → Application → Cookies:
      - Cookie name: dci_sess (NOT PHPSESSID)
      - HttpOnly: true
      - SameSite: Lax
      - Secure: true (if HTTPS)
[ ] Session expires after 2 hours of inactivity (SESSION_IDLE_TTL = 7200)
[ ] Session cookie disappears after browser close (lifetime=0)

─── CSRF verification ────────────────────────────────────────────────

[ ] Submit any POST form (e.g. enroll/drop) normally → succeeds
[ ] Remove CSRF token from form → should receive 403 or flash error
    (test by temporarily intercepting POST with DevTools or Burp)

─── Error handling ───────────────────────────────────────────────────

[ ] PHP errors shown in staging (display_errors=1 expected — for QA visibility)
    Confirm: no raw SQL or passwords are exposed in error output
[ ] Invalid URLs → error page shown, not a stack trace with file paths
[ ] Direct access to /config/database.php → HTTP 403
```

---

## Section 9 — Security Verification

```
Security — Date: _______

─── Environment ──────────────────────────────────────────────────────

[ ] APP_ENV is 'staging' (not 'production' by accident):
      php -r "define('APP_ENV', getenv('APP_ENV') ?: 'local'); echo APP_ENV;"
      # Must print: staging

[ ] display_errors is 1 (expected: staging shows errors for QA):
      php -r "require 'config/session.php'; echo ini_get('display_errors');"
      # Must print: 1 (staging intentionally shows errors)

[ ] No production APP_ENV set in system environment variables:
      printenv APP_ENV
      # Must show 'staging', not 'production'

─── Security headers ─────────────────────────────────────────────────

[ ] Verify all 4 headers are present on login page:
      curl -sI ${BASE_URL}/login.php | grep -iE "x-frame|x-content|referrer|permissions"
      # Must show all 4 headers

[ ] X-Frame-Options: SAMEORIGIN
[ ] X-Content-Type-Options: nosniff
[ ] Referrer-Policy: strict-origin-when-cross-origin
[ ] Permissions-Policy: camera=(), microphone=(), geolocation=()

─── File protection ──────────────────────────────────────────────────

[ ] .env NOT accessible via HTTP:
      curl -sI ${BASE_URL}/.env | head -1
      # Must show: HTTP/1.1 403 or 404 (NOT 200)

[ ] config/database.php NOT accessible via HTTP:
      curl -sI ${BASE_URL}/config/database.php | head -1
      # Must show: HTTP/1.1 403

[ ] scripts/ directory NOT accessible via HTTP:
      curl -sI ${BASE_URL}/scripts/ | head -1
      # Must show: HTTP/1.1 403

[ ] backup files not in web root:
      find . -maxdepth 1 -name "*.sql.gz" -o -name "*.bak" -o -name "*.dump"
      # Must return nothing

[ ] backups/ directory (if exists) NOT in web root:
      ls -la backups/ 2>/dev/null | head
      # if present: must NOT be inside web root, OR must be blocked by web server config

─── Data protection ──────────────────────────────────────────────────

[ ] No production personal data in staging DB (verified in Section 6)

[ ] config/database.php not tracked by git:
      git ls-files config/database.php
      # Must return nothing

[ ] .env not tracked by git:
      git ls-files .env
      # Must return nothing

─── Audit logging ────────────────────────────────────────────────────

[ ] Audit log records login events:
      # After logging in and out as admin_test, run:
      mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} \
        dci_sis_staging \
        -e "SELECT action, entity_type, created_at FROM audit_logs ORDER BY id DESC LIMIT 5;"
      # Must show: AUTH.LOGIN_SUCCESS, AUTH.LOGOUT entries
```

---

## Section 10 — Rollback

If any section above fails and cannot be resolved quickly, use this procedure.

```
Rollback — Date: _______  Initiated by: _______  Reason: _______

─── Code rollback ────────────────────────────────────────────────────

[ ] Identify previous stable commit or tag:
      git log --oneline | head -10
      Rollback target: ___________________________________________

[ ] Deploy previous commit (code only):
      git checkout <prev-commit-or-tag>
      # or via your deployment tool

[ ] Reload PHP-FPM / clear OPcache:
      sudo systemctl reload php8.3-fpm
      # or:
      php -r "opcache_reset();" 2>/dev/null || true

─── Database rollback (only if migrations were applied) ──────────────

[ ] If migrations were applied in this deployment:
      # Option A — restore from pre-deploy backup (safest):
      RESTORE_CONFIRM=YES DB_NAME=dci_sis_staging DB_USER=${DB_USER} \
        DB_PASS=${DB_PASS} DB_HOST=${DB_HOST} DB_PORT=${DB_PORT} \
        bash scripts/restore_database.sh <pre-deploy-backup.sql.gz>

      # Option B — run rollback SQL from the migration file:
      # See rollback comments at bottom of database/migrations/<file>.sql
      # Note: identity_v1.sql and identity_v2.sql do not have rollback SQL
      # → use Option A (restore backup) for those migrations

[ ] Verify migration status after rollback:
      php scripts/migrate.php status
      # rollback state may require manually removing rows from database_migrations
      # if backup restore was used, the table state is restored automatically

─── Verify rollback ──────────────────────────────────────────────────

[ ] Re-run automated smoke test:
      BASE_URL=${BASE_URL} bash scripts/smoke_check.sh
      # Must exit 0

[ ] Login as admin_test → dashboard loads

─── Document ─────────────────────────────────────────────────────────

[ ] What failed: _________________________________________________
[ ] When rollback initiated: _____________________________________
[ ] Rollback completed at: _______________________________________
[ ] Follow-up ticket created: ____________________________________
```

---

## Section 11 — Release Sign-Off

Complete after Sections 0–9 all pass. All sign-offs required for staging → production promotion.

```
Sign-Off — Date: _______  Release: _______

[ ] Developer sign-off
    Code reviewed, tests passed, no known regressions
    Name: _______________________  Date: _______  Signature: _______

[ ] QA sign-off
    All 5 roles verified, core workflows tested (Section 8 complete)
    Name: _______________________  Date: _______  Signature: _______

[ ] Security sign-off
    Security headers present, file protection verified, no credential exposure
    Name: _______________________  Date: _______  Signature: _______

[ ] Data / DB sign-off
    Migrations applied cleanly, no data loss, backup verified, checksums OK
    Name: _______________________  Date: _______  Signature: _______

[ ] Business owner / stakeholder sign-off
    Staging environment reviewed and accepted as ready for QA or production promotion
    Name: _______________________  Date: _______  Signature: _______

───────────────────────────────────────────────────────────────────────

GO / NO-GO decision:

  [ ] GO — all sign-offs complete, proceed to production deployment
      (see docs/production-smoke-checklist.md Section 1 — Pre-Deploy)

  [ ] NO-GO — one or more sign-offs outstanding
      Blocking issue: ___________________________________________
      Re-evaluate date: _________________________________________
```

---

## Section 12 — Quick Reference Commands

All commands in one place. Export DB env vars first:

```bash
export APP_ENV=staging
export DB_HOST=<staging-db-host>
export DB_PORT=3306
export DB_NAME=dci_sis_staging
export DB_USER=<staging-db-user>
export DB_PASS=<staging-db-password>    # staging-only, never production
export SEED_DEFAULT_PASSWORD=<staging-seed-pass>   # staging-only throwaway
export BASE_URL=https://staging.your-domain.ac.th/dci-sis
export BACKUP_DIR=./backups

# ── Pre-deploy backup ─────────────────────────────────────────────────
DB_NAME=${DB_NAME} DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
  DB_HOST=${DB_HOST} DB_PORT=${DB_PORT} \
  bash scripts/backup_database.sh

# ── Migration: check status ───────────────────────────────────────────
php scripts/migrate.php status

# ── Migration: dry-run ────────────────────────────────────────────────
php scripts/migrate.php migrate --dry-run

# ── Migration: apply ──────────────────────────────────────────────────
php scripts/migrate.php migrate --apply

# ── Migration: verify checksums ───────────────────────────────────────
php scripts/migrate.php checksum

# ── Seed: dry-run ────────────────────────────────────────────────────
php scripts/seed_staging.php --dry-run

# ── Seed: apply ──────────────────────────────────────────────────────
SEED_CONFIRM=YES php scripts/seed_staging.php --apply

# ── Automated smoke check ────────────────────────────────────────────
BASE_URL=${BASE_URL} bash scripts/smoke_check.sh

# ── Verify row counts ────────────────────────────────────────────────
mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} ${DB_NAME} \
  -e "SELECT 'users' t, COUNT(*) n FROM users
      UNION ALL SELECT 'students', COUNT(*) FROM students
      UNION ALL SELECT 'enrollments', COUNT(*) FROM enrollments
      UNION ALL SELECT 'database_migrations', COUNT(*) FROM database_migrations;"

# ── Check audit logs ─────────────────────────────────────────────────
mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER} -p${DB_PASS} ${DB_NAME} \
  -e "SELECT action, entity_type, created_at FROM audit_logs ORDER BY id DESC LIMIT 10;"

# ── Clear OPcache (after deploy) ─────────────────────────────────────
php -r "opcache_reset();" 2>/dev/null || sudo systemctl reload php8.3-fpm

# ── Restore from backup ──────────────────────────────────────────────
RESTORE_CONFIRM=YES DB_NAME=${DB_NAME} DB_USER=${DB_USER} DB_PASS=${DB_PASS} \
  DB_HOST=${DB_HOST} DB_PORT=${DB_PORT} \
  bash scripts/restore_database.sh <backup_file.sql.gz>
```

---

## Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/migrations.md` | Migration runner guide, naming convention, rollback policy |
| `docs/staging-seed-data.md` | Seed data guide, QA scenarios, cleanup SQL |
| `docs/backup-restore-plan.md` | Backup schedule, retention policy, restore drill |
| `docs/production-checklist.md` | Production infrastructure requirements |
| `docs/production-smoke-checklist.md` | Production pre/post-deploy checklist |
| `docs/test-plan.md` | Full test plan with 148 test cases across all roles |
| `scripts/migrate.php` | Migration runner CLI |
| `scripts/seed_staging.php` | Staging seed script CLI |
| `scripts/backup_database.sh` | Database backup script |
| `scripts/restore_database.sh` | Database restore script |
| `scripts/smoke_check.sh` | Automated public smoke check |
