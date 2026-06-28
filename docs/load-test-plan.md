# DCI-SIS Load Test Plan

**For:** Performance Engineers, QA Engineers, Deploy team  
**Environment:** Staging ONLY — never production without explicit written approval  
**Tool:** k6 (https://k6.io)  
**Script:** `tests/load/dci_sis_smoke_load.js`  
**Last reviewed:** 2026-06-28

> For staging setup see `docs/staging-deployment-checklist.md`  
> For test accounts see `docs/staging-seed-data.md`  
> For functional test plan see `docs/test-plan.md`

---

## 1. Purpose

Measure the performance characteristics of DCI-SIS under simulated concurrent user load on staging, identify bottlenecks in hot paths before production deployment, and establish baseline response time numbers that inform production readiness.

**Goals:**

| Goal | Measure |
|------|---------|
| Baseline response times | p95 per page type at 10–20 concurrent users |
| Identify slow hot paths | enrollment, gradebook, transcript, audit-logs |
| Confirm login throughput | Login POST including CSRF extraction + session creation |
| Threshold validation | Verify thresholds hold at staging load (50 VUs) |
| Pre-production readiness | Clear go/no-go criteria before scaling further |

---

## 2. Scope

### In scope (this plan)

- Login flow for all 5 roles (CSRF extraction + POST + session setup)
- Read-only page loads for all roles
- Hot paths:
  - `student/enrollment.php` — enrollment list (joins sections, semesters, courses)
  - `student/transcript.php` — transcript view (joins grades, final_grades, courses)
  - `professor/gradebook.php` — gradebook (joins enrollments, grade_items, grade_scores)
  - `registrar/students.php` — paginated student list (50 per page)
  - `registrar/transcripts.php` — transcript search + pagination
  - `admin/audit-logs.php` — audit log table (large, indexed, paginated)

### Out of scope (future phases)

- Write flows: grade submission, enrollment add/drop, document request approval
- `admin/settings.php` — may contain write forms; audit before testing
- `alumni/certificate_request.php` — write action; test separately
- Print endpoints (`transcript_print.php`, `certificate_print.php`) — heavy rendering
- Full production-scale load (500–1,000 VUs) — requires dedicated load test environment
- Automated UI/browser testing (Playwright/Selenium)

---

## 3. Safety Rules

**These rules are mandatory. No exceptions.**

```
SAFETY RULES — must be confirmed before every run

[ ] BASE_URL points to STAGING, not production
    Verify: echo $BASE_URL | grep -v production
    Verify: curl -sI $BASE_URL/login.php | head -1  → HTTP/1.1 200

[ ] Staging backup taken before test with write flows (none in this plan, but habit)
    Backup: bash scripts/backup_database.sh

[ ] Only staging test accounts used — credentials from SEED_DEFAULT_PASSWORD
    Never use real user credentials or production passwords

[ ] Team notified before running baseline or staging profile
    Staging (50 VUs) can saturate a small server — notify ops/DBA first

[ ] k6 machine is separate from staging server
    Running k6 on the same VM as the app will skew results

[ ] Monitor server resources during test (Section 10)
    Stop test immediately if CPU > 90% sustained, error rate > 5%, or DB connections saturated

[ ] No write/destructive actions in this script
    All flows are read-only (GET + login POST only)
    Confirm: grep -n "http.post\|http.put\|http.del" tests/load/dci_sis_smoke_load.js
    Should show ONLY login_post actions

[ ] NEVER run staging or baseline profiles against production
    Production load testing requires a separate written approval and change window
```

---

## 4. Environment Setup

### 4A. Install k6

```bash
# macOS (Homebrew)
brew install k6

# Ubuntu/Debian
sudo apt-get install k6

# Docker (no install required)
docker run --rm grafana/k6 run - < tests/load/dci_sis_smoke_load.js

# Verify version (requires k6 v0.42+)
k6 version
```

### 4B. Staging prerequisites

```bash
# 1. Staging server is up and seeded
BASE_URL=https://staging.your-domain.ac.th/dci-sis
curl -sI $BASE_URL/login.php | head -1   # must show HTTP/1.1 200 OK

# 2. Test accounts exist (from seed script)
#    See docs/staging-seed-data.md for seed instructions

# 3. Automated smoke check passes
bash scripts/smoke_check.sh  # must exit 0

# 4. Export credentials (use SEED_DEFAULT_PASSWORD from staging seed)
export BASE_URL="https://staging.your-domain.ac.th/dci-sis"  # no trailing slash
export ADMIN_USER="admin_test"
export ADMIN_PASS="<staging-seed-password>"
export REGISTRAR_USER="registrar_test"
export REGISTRAR_PASS="<staging-seed-password>"
export PROFESSOR_USER="prof_test"
export PROFESSOR_PASS="<staging-seed-password>"
export STUDENT_USER="student_test"
export STUDENT_PASS="<staging-seed-password>"
export ALUMNI_USER="alumni_test"
export ALUMNI_PASS="<staging-seed-password>"
# Never hardcode passwords — always export from environment
```

### 4C. Create results directory

```bash
mkdir -p results/load
```

---

## 5. Test Accounts

Use accounts created by `scripts/seed_staging.php`. All accounts use `SEED_DEFAULT_PASSWORD`.

| Username | Role | What is tested |
|----------|------|----------------|
| `admin_test` | admin | dashboard, users, audit-logs, roles |
| `registrar_test` | registrar | dashboard, students (paginated), sections, transcripts, doc-requests |
| `prof_test` | professor | dashboard, courses, students, gradebook, exams |
| `student_test` | student | dashboard, enrollment, courses, schedule, grades, transcript, requests |
| `alumni_test` | alumni | dashboard, profile, transcript_request form |

> **Never use real student usernames or production passwords.**  
> See `docs/staging-seed-data.md` for full seed guide and cleanup SQL.

---

## 6. Scenarios

All scenarios are read-only. Login POST is the only write action (creates PHP session, logs AUTH.LOGIN_SUCCESS to audit_logs).

| Scenario | Role | VU % | Hot path |
|----------|------|------|----------|
| `public_flow` | none | 22% | login page availability |
| `student_flow` | student | 33% | enrollment, transcript |
| `professor_flow` | professor | 11% | gradebook |
| `registrar_flow` | registrar | 11% | students list, transcripts |
| `admin_flow` | admin | 11% | audit-logs |
| `alumni_flow` | alumni | 11% | dashboard, transcript request form |

**VU distribution:** 9-slot round-robin by `__VU` number. Student is weighted 3× (largest real population). Missing credentials → scenario falls back to `public_flow` silently.

**CSRF flow per login:**
1. GET `/login.php` — PHP sets `dci_sess` session cookie; CSRF token rendered in hidden input
2. Regex extract: `name="_csrf" value="<64hexchars>"`
3. POST `/actions/login-action.php` with `username`, `password`, `_csrf`
4. 302 → k6 follows → 200 on dashboard
5. Session cookie stored in k6 per-VU cookie jar; reused for subsequent GETs
6. If session still valid on next iteration: GET login.php → 302 to dashboard → skip POST

---

## 7. Load Profiles

| Profile | Duration | Max VUs | Purpose |
|---------|----------|---------|---------|
| smoke | 2 min | 5 | Sanity check: script works, pages return 200, login succeeds |
| baseline | 10 min | 20 | Establish normal response times; p95 numbers for threshold calibration |
| staging | 20 min | 50 | Staging load simulation; find bottlenecks under moderate load |
| peak† | – | 200–500 | Future: requires larger staging server + team approval |
| production-like† | – | 500–1,000 | Future: dedicated load environment after staging proven stable |

† = Not in this script. Documented as next steps in Section 14.

### Stage ramp-up details

```
smoke (2 min total):
  0:00 → 0:30   ramp  0 → 2 VUs
  0:30 → 1:30   hold  5 VUs
  1:30 → 2:00   ramp  5 → 0 VUs

baseline (10 min total):
  0:00 → 1:00   ramp  0 → 10 VUs
  1:00 → 7:00   ramp  10 → 20 VUs (hold at 20)
  7:00 → 9:00   ramp  20 → 10 VUs
  9:00 → 10:00  ramp  10 → 0 VUs

staging (20 min total):
  0:00 → 2:00   ramp  0 → 25 VUs
  2:00 → 14:00  ramp  25 → 50 VUs (hold at 50)
  14:00 → 17:00 ramp  50 → 25 VUs
  17:00 → 20:00 ramp  25 → 0 VUs
```

---

## 8. Thresholds

These are conservative starting thresholds for staging. **Adjust after baseline run** — set thresholds based on real baseline p95 values, not guesses.

| Threshold | Target | Notes |
|-----------|--------|-------|
| `http_req_failed` | `rate < 1%` | HTTP-level errors (timeout, connection refused) |
| `checks` | `rate > 95%` | All response content checks combined |
| `dci_login_success` | `rate > 95%` | Login POST must succeed for sessions to work |
| `http_req_duration{type:public}` p95 | `< 1,000 ms` | Login page (no DB query) |
| `http_req_duration{type:auth}` p95 | `< 2,000 ms` | Login POST (password_verify + session write) |
| `http_req_duration{type:fast}` p95 | `< 1,000 ms` | Dashboards, simple role pages |
| `http_req_duration{type:standard}` p95 | `< 2,000 ms` | Courses, schedule, profile |
| `http_req_duration{type:heavy}` p95 | `< 2,000 ms` | Enrollment, gradebook, transcript, audit-logs |

**p99 monitoring (trend only, no hard threshold yet):**
Run `k6 run --summary-export=results/load/summary.json ...` then inspect `p(99)` values from the JSON output. Set p99 thresholds after 2–3 baseline runs.

---

## 9. How to Run

### Pre-run checklist (every run)

```bash
# 1. Confirm staging server is up
curl -sI $BASE_URL/login.php | head -1          # must show 200

# 2. Run automated smoke check
BASE_URL=$BASE_URL bash scripts/smoke_check.sh   # must exit 0

# 3. Confirm credentials are exported (do NOT hardcode)
echo $STUDENT_USER                               # must print username, not empty
echo ${#STUDENT_PASS}                            # must print length > 0

# 4. Confirm no production URL
echo $BASE_URL | grep -i "prod\|production"      # must return nothing

# 5. Confirm k6 is installed
k6 version
```

### Run: Smoke (always run first)

```bash
K6_PROFILE=smoke \
BASE_URL="https://staging.your-domain.ac.th/dci-sis" \
ADMIN_USER="admin_test"      ADMIN_PASS="<seed-password>" \
REGISTRAR_USER="registrar_test" REGISTRAR_PASS="<seed-password>" \
PROFESSOR_USER="prof_test"   PROFESSOR_PASS="<seed-password>" \
STUDENT_USER="student_test"  STUDENT_PASS="<seed-password>" \
ALUMNI_USER="alumni_test"    ALUMNI_PASS="<seed-password>" \
k6 run tests/load/dci_sis_smoke_load.js
```

Expected: all checks pass, thresholds green, `dci_login_success` = 1.00.  
If smoke fails → fix before running baseline.

### Run: Baseline

```bash
K6_PROFILE=baseline \
BASE_URL="https://staging.your-domain.ac.th/dci-sis" \
ADMIN_USER="admin_test"      ADMIN_PASS="<seed-password>" \
REGISTRAR_USER="registrar_test" REGISTRAR_PASS="<seed-password>" \
PROFESSOR_USER="prof_test"   PROFESSOR_PASS="<seed-password>" \
STUDENT_USER="student_test"  STUDENT_PASS="<seed-password>" \
ALUMNI_USER="alumni_test"    ALUMNI_PASS="<seed-password>" \
k6 run \
  --out json=results/load/baseline_$(date +%Y%m%d_%H%M%S).json \
  tests/load/dci_sis_smoke_load.js
```

After run: record p95 per type tag. Use these numbers to calibrate thresholds.

### Run: Staging Load

**Notify team before running.** This profile ramps to 50 VUs and holds for 12 minutes.

```bash
# Notify team first (Slack/email), then:
K6_PROFILE=staging \
BASE_URL="https://staging.your-domain.ac.th/dci-sis" \
ADMIN_USER="admin_test"      ADMIN_PASS="<seed-password>" \
REGISTRAR_USER="registrar_test" REGISTRAR_PASS="<seed-password>" \
PROFESSOR_USER="prof_test"   PROFESSOR_PASS="<seed-password>" \
STUDENT_USER="student_test"  STUDENT_PASS="<seed-password>" \
ALUMNI_USER="alumni_test"    ALUMNI_PASS="<seed-password>" \
k6 run \
  --out json=results/load/staging_$(date +%Y%m%d_%H%M%S).json \
  tests/load/dci_sis_smoke_load.js
```

### Stop a running test

```bash
Ctrl+C   # graceful stop — k6 ramps down and prints summary
```

---

## 10. Monitoring During Load Test

**Start monitoring BEFORE starting k6.** Keep these views open during the test.

### Server monitoring

| Metric | How to check | Stop threshold |
|--------|-------------|----------------|
| CPU % | `htop` or `vmstat 5` | Stop if sustained > 90% |
| RAM usage | `free -h` | Stop if swap in use and growing |
| Disk I/O | `iostat -x 5` | Stop if await > 200ms |
| PHP-FPM workers | `php-fpm8.3 status` or `pm.status_path` | Stop if `active = max` sustained |
| Apache/Nginx connections | `ss -s` or `netstat -an | grep :80 | wc -l` | Stop if connections > server limit |
| Web server error log | `tail -f /var/log/apache2/error.log` | Stop on 502/503 spike |
| PHP error log | `tail -f /var/log/php/error.log` | Stop on Fatal error spike |

### MySQL monitoring

| Metric | Command | Stop threshold |
|--------|---------|----------------|
| Active connections | `SHOW STATUS LIKE 'Threads_connected'` | Stop if > `max_connections × 0.8` |
| Slow queries | `SHOW STATUS LIKE 'Slow_queries'` | Watch for growth rate |
| Table locks | `SHOW STATUS LIKE 'Table_locks_waited'` | Stop if > 100/min |
| InnoDB waits | `SHOW ENGINE INNODB STATUS\G` | Watch deadlocks section |
| MySQL CPU | `htop` → mysql process | Stop if > 90% |

```sql
-- Run during test to monitor MySQL health
SHOW GLOBAL STATUS LIKE 'Threads_connected';
SHOW GLOBAL STATUS LIKE 'Slow_queries';
SHOW GLOBAL STATUS LIKE 'Table_locks_waited';
SHOW PROCESSLIST;
```

Enable slow query log before test:

```sql
SET GLOBAL slow_query_log = 1;
SET GLOBAL long_query_time = 1;  -- log queries > 1 second
SHOW VARIABLES LIKE 'slow_query_log_file';
```

Review after test:

```bash
mysqldumpslow -s t -t 10 /var/log/mysql/mysql-slow.log
```

### Response time monitoring (k6 output)

```
k6 prints a live summary every 10s during the run:
  ✓ http_req_duration.............. avg=342ms min=89ms med=301ms max=2.1s p(90)=512ms p(95)=678ms
  ✓ dci_login_success.............. 100.00%

Watch for:
  - p(95) climbing beyond thresholds — page or DB issue
  - http_req_failed rate > 0 — connection or server error
  - dci_login_success < 100% — session/CSRF issue
  - checks pass rate dropping — response content changed (PHP error?)
```

### Audit log growth

Load test login events write to `audit_logs`. Monitor growth rate to ensure table doesn't become a bottleneck:

```sql
SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE);
-- Should be proportional to VUs × iterations × login calls
-- Each login = 1 AUTH.LOGIN_SUCCESS row
```

---

## 11. Result Interpretation

### http_req_failed rate

| Value | Meaning | Action |
|-------|---------|--------|
| 0% | No HTTP errors | Continue |
| 0.01–1% | Small number of errors | Investigate but may be noise |
| > 1% | Threshold breached | Stop staging profile, investigate |
| Spike mid-test | Server saturated or restarted | Stop immediately |

Common causes: connection refused (PHP-FPM pool full), timeout (slow MySQL query), 5xx error (PHP fatal).

### p95 / p99 response time

| Value (heavy type) | Meaning |
|-------------------|---------|
| < 500ms | Excellent — well under threshold |
| 500ms–1,000ms | Good |
| 1,000ms–2,000ms | Acceptable (within staging threshold) |
| > 2,000ms | Threshold breached for heavy pages — investigate |
| > 5,000ms | Critical — DB lock, missing index, or server saturation |

If a specific page type exceeds threshold:
1. Check `k6 run --out json=...` then filter by `page` tag for the slow endpoint
2. Check MySQL slow query log for that endpoint's queries
3. Run `EXPLAIN` on the slow query
4. Check audit_logs for that endpoint's activity during the test window

### checks failed

`checks` pass rate measures whether response content is as expected.

| Failure | Likely cause |
|---------|-------------|
| `login POST: landed on dashboard` fails | Wrong credentials, CSRF bug, session issue |
| `login page: _csrf field present` fails | Page error before CSRF render, PHP fatal |
| `{page}: not login redirect` fails | Session expired or PHP session issue |
| `{page}: no PHP fatal error` fails | PHP error in that page — check PHP error log |

### dci_login_success rate < 95%

1. Check `dci_csrf_missing` counter — if > 0, CSRF extraction failing (regex issue or login page HTML changed)
2. Check credentials: `echo $STUDENT_USER` — must not be empty
3. Manual test: try logging in with the same credentials in a browser
4. Check PHP error log for session/login errors during test

### threshold failed → what to do

1. Stop the staging profile if running
2. Run baseline only to get clean p95 numbers
3. Identify the slow endpoint from `--out json` results
4. Check MySQL slow query log
5. Run `EXPLAIN SELECT ...` on the suspected query
6. Add index if missing (create new migration, test on local first)
7. Re-run baseline after fix to verify improvement

---

## 12. Go / No-Go Criteria

### Smoke (run after every deployment)

| Criteria | Pass | Fail |
|----------|------|------|
| All checks pass | 100% | < 100% |
| `dci_login_success` | 100% | < 100% |
| `http_req_failed` | 0% | > 0% |
| Thresholds | all green | any red |
| PHP error log | no new fatals | new fatals found |

→ **Fail on any item: do not proceed to baseline. Fix first.**

### Baseline (run before staging load test)

| Criteria | Pass | Fail |
|----------|------|------|
| `http_req_failed` | < 0.5% | ≥ 0.5% |
| `checks` pass rate | > 98% | < 98% |
| `http_req_duration{type:fast}` p95 | < 600ms | ≥ 800ms |
| `http_req_duration{type:heavy}` p95 | < 1,200ms | ≥ 1,500ms |
| MySQL slow queries during test | < 5 unique queries | ≥ 5 unique queries |
| PHP-FPM workers saturated | No | Yes |

→ **Fail any item: investigate before running staging profile.**

### Staging load (50 VUs — pre-production clearance)

| Criteria | GO | NO-GO |
|----------|----|-------|
| `http_req_failed` | < 1% | ≥ 1% |
| `checks` pass rate | > 95% | < 95% |
| `http_req_duration{type:heavy}` p95 | < 2,000ms | ≥ 2,000ms |
| `http_req_duration{type:fast}` p95 | < 1,000ms | ≥ 1,000ms |
| `dci_login_success` | > 95% | < 95% |
| MySQL connections during test | < 80% of max | ≥ 80% of max |
| PHP-FPM workers saturated | No | Yes |
| Slow queries (> 1s) introduced | None new | Any new |
| CPU sustained | < 80% | ≥ 90% sustained |
| Error log | no new fatals | new fatals found |

→ **All GO required to promote staging to production.**

---

## 13. Known Limitations

| Limitation | Impact |
|------------|--------|
| Single test account per role | All VUs share same DB data; gradebook shows 1 section for all professor VUs |
| Read-only flows only | Enrollment adds, grade saves, document approval not tested |
| No browser-level testing | JavaScript rendering, SPA behavior not measured |
| audit_logs growth | Every login adds 1 row; large tests generate many rows; consider cleanup after test |
| k6 on same host | Running k6 on the staging server itself inflates resource usage and skews results — always run k6 from a separate machine |
| Session reuse between iterations | When session persists, login POST is skipped; reduces auth load (tests real-session behavior but may undercount login throughput) |
| No background jobs | No email, no report generation, no cron — if added later, re-test with those active |
| Staging spec may differ from production | Results may not directly transfer; use as directional, not absolute numbers |

---

## 14. Next Steps

| Step | Priority | Notes |
|------|----------|-------|
| Run smoke test first to verify script works | Immediate | Do this before anything else |
| Run baseline (20 VUs) to establish p95 numbers | High | Need real numbers to set good thresholds |
| Calibrate thresholds from baseline results | High | Update options.thresholds in k6 script |
| Add write flow (optional, separate script) | Medium | Enrollment add/drop with test section only; separate file |
| Add print endpoint tests | Medium | `transcript_print.php` load; expect higher p95 |
| Peak simulation (200–500 VUs) | Future | Larger staging server + ops approval required |
| MySQL slow query log review | Ongoing | Review after every staging run |
| k6 Cloud integration | Future | Better real-time dashboard, geographic distribution |
| Clean up audit_logs after load tests | Ongoing | `DELETE FROM audit_logs WHERE created_at > <test_start> AND action='AUTH.LOGIN_SUCCESS'` — on staging only |

---

## 15. Quick Reference

```bash
# ── Install k6 (macOS) ─────────────────────────────────────────────────────
brew install k6

# ── Export env vars (replace <seed-password> with SEED_DEFAULT_PASSWORD) ──
export BASE_URL="https://staging.your-domain.ac.th/dci-sis"
export ADMIN_USER="admin_test"       ADMIN_PASS="<seed-password>"
export REGISTRAR_USER="registrar_test" REGISTRAR_PASS="<seed-password>"
export PROFESSOR_USER="prof_test"    PROFESSOR_PASS="<seed-password>"
export STUDENT_USER="student_test"   STUDENT_PASS="<seed-password>"
export ALUMNI_USER="alumni_test"     ALUMNI_PASS="<seed-password>"

# ── Smoke test ─────────────────────────────────────────────────────────────
K6_PROFILE=smoke k6 run tests/load/dci_sis_smoke_load.js

# ── Baseline (10 min, 20 VUs) ──────────────────────────────────────────────
K6_PROFILE=baseline k6 run \
  --out json=results/load/baseline_$(date +%Y%m%d_%H%M%S).json \
  tests/load/dci_sis_smoke_load.js

# ── Staging load (20 min, 50 VUs) — notify team first ─────────────────────
K6_PROFILE=staging k6 run \
  --out json=results/load/staging_$(date +%Y%m%d_%H%M%S).json \
  tests/load/dci_sis_smoke_load.js

# ── Run with HTML report (requires k6 extension) ───────────────────────────
K6_PROFILE=smoke k6 run \
  --out json=results/load/smoke_$(date +%Y%m%d_%H%M%S).json \
  tests/load/dci_sis_smoke_load.js
# Then: k6 cloud convert --out results/html results/load/smoke_*.json

# ── Enable MySQL slow query log before test ────────────────────────────────
mysql -u root -p -e "SET GLOBAL slow_query_log=1; SET GLOBAL long_query_time=1;"

# ── Check audit_logs growth during test ───────────────────────────────────
mysql -u $DB_USER -p$DB_PASS $DB_NAME \
  -e "SELECT COUNT(*) FROM audit_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE);"

# ── Verify script has no production URL hardcoded ─────────────────────────
grep -n "production\|prod\." tests/load/dci_sis_smoke_load.js | grep -v "comment\|#"

# ── Verify no passwords in script ────────────────────────────────────────
grep -n "PASS\s*=" tests/load/dci_sis_smoke_load.js | grep -v "__ENV\."
```

---

## Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/staging-deployment-checklist.md` | Deploy DCI-SIS to staging before running load tests |
| `docs/staging-seed-data.md` | Create test accounts used by load test |
| `docs/test-plan.md` | Functional test plan (148 test cases) |
| `docs/production-checklist.md` | Production infrastructure requirements |
| `docs/production-smoke-checklist.md` | Post-deploy smoke check checklist |
| `docs/migrations.md` | Database migration guide |
| `docs/backup-restore-plan.md` | Backup before any test with write flows |
