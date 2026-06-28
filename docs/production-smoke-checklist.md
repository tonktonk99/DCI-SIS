# DCI-SIS Production Smoke Checklist

**For:** Deploy team / QA team  
**Use:** Before every production deployment, after every production deployment, after database restore  
**Last reviewed:** 2026-06-28

> Quick reference — for full test coverage see `docs/test-plan.md`  
> For backup/restore steps see `docs/backup-restore-plan.md`  
> For infrastructure config see `docs/production-checklist.md`

---

## Section 1 — Pre-Deploy Checklist

Complete ALL items before starting any production deployment.

```
Pre-Deploy — Date: _______  Deploy by: _______  Approver: _______

Code:
[ ] git status is clean: no uncommitted or untracked changes
[ ] git log shows correct release commit at HEAD
[ ] Rollback commit hash recorded: ____________________
[ ] Code reviewed and approved by at least one other developer

Environment:
[ ] Target server has APP_ENV=production set
[ ] Target server has DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS set (not root/root)
[ ] display_errors=Off verified in staging with same config
[ ] error_log path exists and is writable
[ ] .htaccess in place (or Nginx equivalent config) — test: curl -I /dci-sis/config/database.php → 403

Database:
[ ] Pre-deploy backup completed and file is non-empty:
    File: ____________________________________________
    Size: ____________
[ ] Backup file tested: gunzip -t <file> → OK
[ ] Migrations reviewed: any new migration files? [ ] Yes  [ ] No
    If Yes: migration tested in staging first? [ ] Yes (required)

Testing:
[ ] Smoke test script passed in staging:
    BASE_URL=https://staging.example.ac.th/dci-sis bash scripts/smoke_check.sh
[ ] All 5 roles logged in successfully in staging
[ ] Key pages (enrollment, gradebook, transcript) verified in staging
[ ] Security headers present in staging (curl -I /login.php)

Deploy window:
[ ] Team notified of maintenance window (if needed)
[ ] Rollback plan communicated to team
[ ] DBA on standby for database issues

Start deploy only when ALL boxes above are checked.
```

---

## Section 2 — Post-Deploy Smoke Test (Manual)

Run within 10 minutes of deployment completing. If any item fails, initiate rollback.

```
Post-Deploy — Date: _______  Verified by: _______

Automated check (run first):
[ ] bash scripts/smoke_check.sh (with production BASE_URL if safe, or staging equivalent)
[ ] All checks PASS before proceeding to manual steps

Application health:
[ ] PHP error log has no fatal errors since deploy time
    Check: tail -100 /path/to/php_error.log | grep -i "fatal\|error"
[ ] Apache/Nginx access log shows 200/302 responses (not 500)

Auth flow:
[ ] Login as admin → /admin/dashboard.php loads
[ ] Login as registrar → /registrar/dashboard.php loads
[ ] Login as professor → /professor/dashboard.php loads
[ ] Login as student → /student/dashboard.php loads
[ ] Login as alumni → /alumni/dashboard.php loads
[ ] Logout works for all roles
[ ] Demo credentials box NOT visible on login page (critical: must be hidden in production)

Core pages per role:

Admin:
[ ] /admin/users.php loads
[ ] /admin/audit-logs.php loads and shows recent entries

Registrar:
[ ] /registrar/students.php loads with pagination
[ ] /registrar/sections.php loads
[ ] /registrar/transcripts.php loads

Professor:
[ ] /professor/gradebook.php loads for assigned section
[ ] /professor/exams.php loads

Student:
[ ] /student/enrollment.php loads
[ ] /student/grades.php loads
[ ] /student/transcript.php loads

Alumni:
[ ] /alumni/transcript_request.php loads

Session and cookies:
[ ] Browser DevTools → Cookies: cookie named dci_sess (not PHPSESSID)
[ ] Cookie has HttpOnly flag
[ ] Cookie has Secure flag (if HTTPS)
[ ] Cookie has SameSite=Lax

Security headers:
[ ] curl -I <login_url> shows X-Frame-Options: SAMEORIGIN
[ ] curl -I <login_url> shows X-Content-Type-Options: nosniff
[ ] curl -I <login_url> shows Referrer-Policy: strict-origin-when-cross-origin

Audit logs (verify actions are still being recorded):
[ ] Perform one login → check audit_logs: AUTH.LOGIN_SUCCESS present
[ ] Perform one logout → check: AUTH.LOGOUT present
    SQL: SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5;

Decision:
[ ] ALL checks passed → deployment confirmed SUCCESSFUL
[ ] Any check failed → initiate rollback (Section 4)
```

---

## Section 3 — Post-Restore Smoke Test

Run after any database restore (production or staging).

```
Post-Restore — Date: _______  Verified by: _______  Backup file: _______

Database state:
[ ] Row counts verified:
    users:        _____ rows (expected: _____)
    students:     _____ rows (expected: _____)
    enrollments:  _____ rows (expected: _____)
    final_grades: _____ rows (expected: _____)
    audit_logs:   _____ rows (expected: _____)

    SQL to run:
    SELECT 'users' t, COUNT(*) n FROM users
    UNION ALL SELECT 'students', COUNT(*) FROM students
    UNION ALL SELECT 'enrollments', COUNT(*) FROM enrollments
    UNION ALL SELECT 'final_grades', COUNT(*) FROM final_grades
    UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs;

[ ] No tables are empty that should have data

Application verification:
[ ] Login as admin → dashboard loads
[ ] Login as student → enrollment list shows expected data
[ ] Login as professor → gradebook shows expected data for their section
[ ] Login as registrar → student list loads
[ ] Login as alumni → transcript request form loads

Data verification:
[ ] Spot-check: one student's enrollments match known data
[ ] Spot-check: one section's grade_scores match known data
[ ] Spot-check: recent audit_logs entries present

If restoring to PRODUCTION:
[ ] Notify all affected users of potential data rollback window
[ ] Clear OPcache: php -r "opcache_reset();" or restart PHP-FPM
[ ] Document: what time was restored, how many minutes of data may be lost (RPO gap)
[ ] Create post-incident report (see docs/backup-restore-plan.md Section 8)
```

---

## Section 4 — Rollback Checklist

Initiate if post-deploy smoke test fails or critical error is detected.

```
Rollback — Date: _______  Initiated by: _______  Reason: _______

Code rollback (always try this first):
[ ] Identify rollback target commit: ____________________
[ ] git revert <commit> or deploy previous release package
[ ] Reload PHP-FPM / clear OPcache:
    sudo systemctl reload php8.3-fpm
    # or: php -r "opcache_reset();"
[ ] Re-run post-deploy smoke test (Section 2) → all checks pass?

Database rollback (only if schema or data migration was part of deploy):
[ ] Use pre-deploy backup:
    RESTORE_CONFIRM=YES DB_NAME=dci_sis [other env vars] \
      bash scripts/restore_database.sh <pre-deploy-backup-file>
[ ] Verify row counts after restore (see Section 3)
[ ] Login and verify data intact

Post-rollback:
[ ] Login as all 5 roles → all dashboards load
[ ] Error log: no new fatals
[ ] Document: what failed, when rollback initiated, how long downtime was
[ ] Notify team and stakeholders
[ ] Schedule post-mortem if critical failure
```

---

## Section 5 — Monthly Restore Drill Checklist

Run at least once per month. Records RTO (Recovery Time Objective) practice.

```
Restore Drill — Date: _______  Performed by: _______

Preparation (5 min):
[ ] Identify latest backup file: ls -lth $BACKUP_DIR | head -5
[ ] File: ____________________
[ ] Size reasonable compared to last month? [ ] Yes  [ ] No

Restore to LOCAL or STAGING database (never production for drills):
[ ] Create drill DB: CREATE DATABASE dci_sis_drill;
[ ] Run: RESTORE_CONFIRM=YES DB_NAME=dci_sis_drill [env vars] \
         bash scripts/restore_database.sh <backup_file>
[ ] Check row counts (see Section 3 SQL)

Application test (point config to drill DB):
[ ] Login as admin → works
[ ] Login as student → enrollment shows data
[ ] Login as professor → gradebook loads
[ ] Login as registrar → students list loads
[ ] Login as alumni → transcript request loads

Audit logs check:
[ ] SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5; → entries present

Cleanup:
[ ] DROP DATABASE dci_sis_drill;
[ ] Reset config/database.php back to normal DB

Results:
[ ] Total time from file identification to verified login: _____ minutes
    (Goal: < 60 min for drill; production RTO target: < 4 hours)
[ ] Issues found: ________________________________________________
[ ] Action items: ________________________________________________
[ ] Next drill date: _____________________________________________
```

---

## Section 6 — Quick Reference Commands

```bash
# Run automated smoke check (public pages only, no credentials needed)
BASE_URL=http://localhost/dci-sis bash scripts/smoke_check.sh

# Take a pre-deploy backup
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=dci_sis DB_USER=dci_backup DB_PASS=secret
export BACKUP_DIR=/var/backups/dci-sis/pre-deploy
bash scripts/backup_database.sh

# Check PHP error log (last 50 lines)
tail -50 /var/log/php_errors.log | grep -i "fatal\|error"

# Check security headers
curl -sI http://localhost/dci-sis/login.php | grep -iE "x-frame|x-content|referrer|permissions"

# Check .htaccess protection
curl -sI http://localhost/dci-sis/config/database.php | head -1
# → Expected: HTTP/1.1 403 Forbidden

# Check cookie settings after login (DevTools → Application → Cookies)
# Cookie name must be: dci_sess
# Flags: HttpOnly=true, SameSite=Lax, Secure=true (HTTPS)

# Verify DB row counts after restore
mysql -h127.0.0.1 -P3306 -udci_app -p dci_sis -e \
  "SELECT 'users' t, COUNT(*) n FROM users
   UNION ALL SELECT 'students', COUNT(*) FROM students
   UNION ALL SELECT 'enrollments', COUNT(*) FROM enrollments;"

# Check audit logs after action
mysql -h127.0.0.1 -P3306 -udci_app -p dci_sis -e \
  "SELECT id, action, entity_type, entity_id, details, created_at
   FROM audit_logs ORDER BY id DESC LIMIT 10;"

# Clear OPcache after deploy
php -r "opcache_reset();" 2>/dev/null || sudo systemctl reload php8.3-fpm
```

---

## Section 7 — Emergency Contacts Template

```
Incident date/time: _______________________
Severity: [ ] P1 (down)  [ ] P2 (degraded)  [ ] P3 (minor)

DBA contact: ________________________________  Phone: ________________
Lead Dev contact: ___________________________  Phone: ________________
IT Lead contact: ____________________________  Phone: ________________
Registrar office contact: ___________________  Phone: ________________

Affected users: ____________________________________________________
Status page URL: ___________________________________________________
Incident log URL: __________________________________________________
```
