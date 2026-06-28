# DCI-SIS Staging Execution and Blocker Triage Plan

**Phase:** 2A — Staging Execution + Final Review Blocker Triage  
**Created:** 2026-06-28  
**Source:** Phase 1P Final Production Readiness Review (`docs/final-production-readiness-review.md`)  
**HEAD at creation:** `40e66e8`

---

## Overview

Phase 1P audit returned Overall **79/100 — Staging Ready with Conditions**.  
This document converts Phase 1P findings into an ordered execution plan: fix blockers first, then deploy staging, then complete pre-pilot items.

**Current Go/No-Go:** Staging = NO-GO until commits 1–6 below are complete.

---

## Blocker Summary

### Critical — Must Fix Before Staging (6 items)

| # | File | Issue |
|---|------|-------|
| C1 | `professor/exams.php:31` | POST save_scores — no `verify_csrf()`, no CSRF field in form |
| C2 | `student/requests.php:22` | POST INSERT document_requests — no `verify_csrf()`, no CSRF field in form |
| C3 | `alumni/transcript_request.php:14` | POST INSERT document_requests — no `verify_csrf()`, no CSRF field |
| C4 | `alumni/certificate_request.php:14` | POST INSERT document_requests — no `verify_csrf()`, no CSRF field |
| C5 | `registrar/dashboard.php:11` | POST UPDATE registrar_petitions — no `verify_csrf()`, no CSRF field in 2 forms |
| C6 | `registrar/students.php:79` | `SELECT * FROM students` — no LIMIT/OFFSET — memory overflow at scale |

### High — Must Fix Before Pilot (4 items)

| # | File | Issue |
|---|------|-------|
| H1 | `actions/login-action.php:38` | MD5 fallback active — weak hash for legacy accounts |
| H2 | `task29_duplicate_protection_FULL.sql` (root) | Dev artifact tracked in git |
| H3 | `task33_final_flow_test_checklist.md` (root) | Dev artifact with demo credentials tracked in git |
| H4 | `index.php` | Mock placeholder — no role-based redirect after login |

### Medium — Must Fix Before Production (3 items)

| # | File | Issue |
|---|------|-------|
| M1 | `registrar/dashboard.php` | Petition approve/deny has no `logAudit()` call |
| M2 | `alumni/dashboard.php:28-29` | Hardcoded `/dci-sis/` paths instead of `APP_BASE` constant |
| M3 | Web server config | No HTTP→HTTPS redirect configured at server level |

---

## Ordered Commit Plan

### Group A — Before Staging Deploy (commits 1–6)

```
Commit 1:  fix(security): close CSRF on professor exam score form
           professor/exams.php
           - verify_csrf() at POST block start (line ~31)
           - <?= csrf_field() ?> inside <form> (line ~181)

Commit 2:  fix(security): close CSRF on student document request form
           student/requests.php
           - verify_csrf() at POST block start (line ~22)
           - <?= csrf_field() ?> inside <form> (line ~116)

Commit 3:  fix(security): close CSRF on alumni transcript request form
           alumni/transcript_request.php
           - verify_csrf() at POST block start (line ~14)
           - <?= csrf_field() ?> inside <form> (line ~50)

Commit 4:  fix(security): close CSRF on alumni certificate request form
           alumni/certificate_request.php
           - verify_csrf() at POST block start (line ~14)
           - <?= csrf_field() ?> inside <form> (line ~50)

Commit 5:  fix(security): close CSRF on registrar petition review forms
           registrar/dashboard.php
           - verify_csrf() at POST block start (line ~11)
           - <?= csrf_field() ?> in both approve and deny forms (lines ~146, ~152)

Commit 6:  perf(registrar): paginate student list with search and LIMIT 50
           registrar/students.php
           - Add $page = max(1, (int)($_GET['page'] ?? 1))
           - Add $search = trim($_GET['search'] ?? '')
           - WHERE clause: optional search on student_code, first_name, last_name
           - LIMIT 50 OFFSET ($page - 1) * 50
           - Total count query for pagination controls
```

### Group B — Cleanup During / After Staging (commits 7–9)

```
Commit 7:  chore(cleanup): remove dev artifact files from git tracking
           git rm task29_duplicate_protection_FULL.sql
           git rm task33_final_flow_test_checklist.md

Commit 8:  fix(routing): replace index.php mock dashboard with role-based redirect
           index.php
           - After checkLogin(), get $user['role'] and redirect to /dci-sis/{role}/dashboard.php
           - Default fallback: redirect to login.php

Commit 9:  fix(alumni): use APP_BASE constant instead of hardcoded /dci-sis paths
           alumni/dashboard.php
           - Replace /dci-sis/alumni/transcript_request.php with APP_BASE . '/alumni/transcript_request.php'
           - Replace /dci-sis/alumni/certificate_request.php with APP_BASE . '/alumni/certificate_request.php'
```

### Group C — Before Pilot (commits 10–12)

```
Commit 10: feat(audit): log petition review outcomes in registrar dashboard
           registrar/dashboard.php
           - logAudit() after UPDATE registrar_petitions
           - action: 'PETITION.APPROVED' or 'PETITION.DENIED'
           - entity_type: 'registrar_petitions', entity_id: $petitionId

Commit 11: chore(security): add legacy MD5 account detection and rehash script
           scripts/rehash_legacy_passwords.php (new)
           - CLI-only (PHP_SAPI check)
           - Production guard (APP_ENV check)
           - --dry-run: list accounts with MD5 hashes
           - --apply: force password_hash() rehash (requires REHASH_CONFIRM=YES + new temp password)
           - Never hardcode passwords — read from env

Commit 12: docs(deploy): update staging execution plan with load test results
           docs/staging-execution-plan.md (this file)
           - Fill in k6 smoke + baseline p95 results
           - Update Go/No-Go decision
```

---

## Staging Deployment Execution Steps

Run these steps AFTER all commits 1–6 are merged and verified locally.

### Step 0 — Pre-Deploy Gate

All items must pass before touching the staging server.

```bash
# Verify CSRF fixes in all 5 files
grep -c "verify_csrf" professor/exams.php student/requests.php \
  alumni/transcript_request.php alumni/certificate_request.php \
  registrar/dashboard.php
# Each must show count >= 1

# Verify pagination in registrar/students.php
grep -n "LIMIT" registrar/students.php
# Must show LIMIT in the main list query (not just the LIMIT 1 check)

# Verify dev artifacts removed
git ls-files | grep -E "task29|task33"
# Must return nothing (empty)

# Verify clean working tree
git status
# Must show: nothing to commit, working tree clean

# Verify no secrets in git
git log --oneline --all -- .env config/database.php
# Must return nothing

# Verify no .sql in tracking
git ls-files | grep -E '\.(sql|sql\.gz|bak|dump)$'
# Must return nothing

# Confirm deploy commit
git log --oneline -3
# Record: HEAD_COMMIT=_______________________________
```

**Gate sign-off:** `[ ] Developer _______________ Date: _______________`  
**Proceed only if all items pass.**

---

### Step 1 — Backup Staging Database

```bash
export DB_NAME=dci_sis_staging
export DB_USER=...   # from staging env — never hardcode
export DB_PASS=...

bash scripts/backup_database.sh

# Verify backup
BACKUP_FILE=$(ls -t backups/*.sql.gz | head -1)
echo "Backup file: $BACKUP_FILE"
gunzip -t "$BACKUP_FILE" && echo "Backup integrity: OK"
# Record: BACKUP_FILE=_______________________________________________
```

---

### Step 2 — Configure Staging Environment

```bash
# On staging server — copy from example, then edit
cp .env.example .env
# Set in .env:
#   APP_ENV=staging
#   APP_DEBUG=false        <-- MUST be false on staging
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_NAME=dci_sis_staging
#   DB_USER=<staging_db_user>
#   DB_PASS=<staging_db_pass>
#   APP_TIMEZONE=Asia/Bangkok

# Verify
php -r "require 'config/session.php'; echo APP_ENV . PHP_EOL;"
# Must print: staging

php -r "require 'config/session.php'; echo APP_DEBUG ? 'debug ON (FAIL)' : 'debug OFF (OK)'; echo PHP_EOL;"
# Must print: debug OFF (OK)
```

---

### Step 3 — Database Migration

```bash
# Check current status
php scripts/migrate.php status
# Record: Applied=___ Pending=___ Modified=___
# If Modified > 0: STOP — investigate checksum mismatch before continuing

# If Pending > 0:
php scripts/migrate.php migrate --dry-run
# Read and verify SQL before applying

# Apply (only after dry-run review)
MIGRATE_CONFIRM=YES php scripts/migrate.php migrate --apply

# Post-apply verification
php scripts/migrate.php status
# Must: Pending=0, Modified=0

php scripts/migrate.php checksum
# Must: Checksum OK for all migrations
```

---

### Step 4 — Seed Staging Data

```bash
# Dry-run first
SEED_DEFAULT_PASSWORD='StagingTest@2026' \
  php scripts/seed_staging.php --dry-run
# Review expected rows

# Apply
SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD='StagingTest@2026' \
  php scripts/seed_staging.php --apply

# Idempotency check
SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD='StagingTest@2026' \
  php scripts/seed_staging.php --apply
# Must: Created 0 new records (all already exist)

# Test accounts created:
# admin_test / StagingTest@2026      → admin role
# registrar_test / StagingTest@2026  → registrar role
# prof_test / StagingTest@2026       → professor role
# student_test / StagingTest@2026    → student role
# alumni_test / StagingTest@2026     → alumni role
```

---

### Step 5 — Automated Smoke Test

```bash
export BASE_URL=https://staging.dci-sis.example.com/dci-sis

bash scripts/smoke_check.sh
# Must: exit 0, all checks pass

# Manual HTTP checks
curl -sI $BASE_URL/login.php | head -3
# Must: HTTP/1.1 200 OK

curl -sI $BASE_URL/login.php | grep -iE "x-frame-options|x-content-type|referrer-policy"
# Must return all 3 headers

# Verify sensitive paths blocked
curl -o /dev/null -s -w "%{http_code}" $BASE_URL/../config/database.php
# Must: 403

curl -o /dev/null -s -w "%{http_code}" $BASE_URL/../.env
# Must: 403 or 404
```

---

### Step 6 — Manual Role Access Test

```
Using test accounts from Step 4 (password: StagingTest@2026)

[ ] admin_test → login → lands on admin/dashboard.php (not index.php mock)
[ ] registrar_test → login → registrar/dashboard.php
[ ] prof_test → login → professor/dashboard.php
[ ] student_test → login → student/dashboard.php
[ ] alumni_test → login → alumni/dashboard.php
[ ] student_test accesses /dci-sis/admin/ → 403 or redirect to login
[ ] prof_test accesses /dci-sis/registrar/ → 403 or redirect to login
[ ] Idle 5 minutes → session expired → redirect to login.php
[ ] Logout → session destroyed → back to login.php
```

---

### Step 7 — CSRF Security Smoke

```
Manual test for each of the 5 fixed files:

[ ] student/requests.php
    - Login as student_test, submit document request form normally → success
    - Submit POST without _csrf via curl → HTTP 403

[ ] professor/exams.php
    - Login as prof_test, submit exam scores form normally → success
    - Submit POST without _csrf via curl → HTTP 403

[ ] alumni/transcript_request.php
    - Login as alumni_test, submit form normally → success
    - Submit POST without _csrf → HTTP 403

[ ] alumni/certificate_request.php
    - Login as alumni_test, submit form normally → success
    - Submit POST without _csrf → HTTP 403

[ ] registrar/dashboard.php
    - Login as registrar_test, approve or deny petition normally → success
    - Submit POST without _csrf → HTTP 403

CSRF test command (replace SESSION and URL):
curl -X POST https://staging.../student/requests.php \
  -d "request_type=transcript&purpose=test&delivery_method=pickup" \
  -b "dci_sess=<session_cookie>" \
  -L -s -o /dev/null -w "%{http_code}"
# Expected: 403
```

---

### Step 8 — k6 Load Smoke Profile

```bash
# Requires k6 installed on test runner machine
export BASE_URL=https://staging.dci-sis.example.com/dci-sis
export K6_PROFILE=smoke
export ADMIN_USER=admin_test       ADMIN_PASS='StagingTest@2026'
export REGISTRAR_USER=registrar_test REGISTRAR_PASS='StagingTest@2026'
export PROFESSOR_USER=prof_test    PROFESSOR_PASS='StagingTest@2026'
export STUDENT_USER=student_test   STUDENT_PASS='StagingTest@2026'
export ALUMNI_USER=alumni_test     ALUMNI_PASS='StagingTest@2026'

k6 run tests/load/dci_sis_smoke_load.js

# Smoke profile: 30s ramp → 60s at 5 VUs → 30s ramp down
# Pass criteria:
#   http_req_failed < 1%
#   checks rate > 95%
#   dci_login_success > 95%
#   p95 public < 1,000ms
#   p95 auth < 2,000ms
#   p95 heavy < 2,000ms

# Record results:
# p95 public:   _____ ms
# p95 auth:     _____ ms
# p95 fast:     _____ ms
# p95 standard: _____ ms
# p95 heavy:    _____ ms
# checks pass:  _____ %
# login success:_____ %
```

---

### Step 9 — Log Review

```bash
# Check PHP error log on staging server
tail -50 /var/log/php/error.log  # adjust path per server config
# Must: No Fatal errors, no Uncaught exceptions

# Check MySQL error log
tail -20 /var/log/mysql/error.log  # adjust path
# Must: No InnoDB errors, no connection failures

# Check Apache/Nginx access log for 5xx
grep " 5[0-9][0-9] " /var/log/apache2/access.log | tail -20
# Must: No 500 errors during smoke period
```

---

### Step 10 — Staging Sign-Off

```
Staging Deployment — Sign-Off Sheet

Environment:  staging
Date:         _______________
Deploy by:    _______________
Commit hash:  _______________
Backup file:  _______________

[ ] Developer sign-off:              _______________
[ ] Security sign-off (CSRF pass):   _______________
[ ] QA sign-off (role access pass):  _______________
[ ] Tech/DBA sign-off (migration OK):_______________

k6 smoke: PASS / FAIL
All roles login: PASS / FAIL
CSRF block test: PASS / FAIL
Sensitive files blocked: PASS / FAIL

Staging Go/No-Go: [ ] GO  [ ] NO-GO

If NO-GO — reason: _______________________________________________
Rollback command:
  git checkout <previous-commit>
  RESTORE_CONFIRM=YES bash scripts/restore_database.sh <backup-file>
  # OPcache: restart PHP-FPM or touch .php file to invalidate
```

---

## Pre-Pilot Checklist (after staging validated)

After staging sign-off, complete these before pilot:

```
[ ] Commit 7:  git rm task29 and task33 artifacts
[ ] Commit 8:  index.php role-based redirect
[ ] Commit 9:  alumni/dashboard.php APP_BASE fix
[ ] Commit 10: registrar/dashboard.php petition audit logging
[ ] Commit 11: scripts/rehash_legacy_passwords.php created
[ ] Run rehash script --dry-run on staging → identify MD5 accounts
[ ] Disable or force-rehash all MD5 accounts
[ ] HTTPS configured at web server level
[ ] k6 baseline profile run on staging → p95 recorded
[ ] k6 staging profile run → all thresholds pass
[ ] Restore drill completed (restore from staging backup, verify)
[ ] All 5 roles manual smoke on staging environment
[ ] Staging load test results documented in this file (Step 8 above)
```

---

## Pilot Go/No-Go Criteria

| Check | Threshold | Result |
|-------|-----------|--------|
| All Critical blockers (C1–C6) resolved | 6/6 | ___ |
| All High blockers (H2–H4) resolved | 3/3 | ___ |
| MD5 accounts retired (H1) | 0 MD5 hashes remaining | ___ |
| Staging smoke test pass | exit 0 | ___ |
| Staging k6 smoke pass | all thresholds | ___ |
| Staging k6 baseline run | p95 recorded | ___ |
| Restore drill completed | RTO < 60 min | ___ |
| HTTPS configured | HTTP→HTTPS redirects | ___ |
| All 5 roles verified on staging | manual test | ___ |

Pilot Go/No-Go: [ ] GO  [ ] NO-GO

---

## Production Go/No-Go Criteria

| Check | Threshold |
|-------|-----------|
| All Pilot criteria above | pass |
| k6 staging profile (50 VUs, 20 min) | all thresholds pass |
| p95 heavy < 2,000ms at 50 VUs | confirmed |
| Monitoring configured (alerts on PHP 500 + MySQL slow) | confirmed |
| MySQL slow query log reviewed | no critical slow queries |
| Audit log retention policy confirmed | archival plan documented |
| Production HTTPS + HSTS plan | documented |
| Data backup on production server verified | first backup taken |

---

## Key Reference

| Document | Link |
|----------|------|
| Final Production Readiness Review | `docs/final-production-readiness-review.md` |
| Staging Deployment Checklist | `docs/staging-deployment-checklist.md` |
| Load Test Plan | `docs/load-test-plan.md` |
| Migration Guide | `docs/migrations.md` |
| Backup / Restore Plan | `docs/backup-restore-plan.md` |
| Seed Data Guide | `docs/staging-seed-data.md` |
| Test Plan (148 cases) | `docs/test-plan.md` |
| Production Smoke Checklist | `docs/production-smoke-checklist.md` |
