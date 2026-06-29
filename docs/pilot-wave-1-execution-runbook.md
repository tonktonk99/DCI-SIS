# DCI-SIS Pilot Wave 1 — Execution Runbook

**Phase:** 2J — Execute Pilot Wave 1  
**Status:** READY TO EXECUTE — All pre-pilot code fixes complete  
**Created:** 2026-06-29  
**HEAD at creation:** `b02a10b`  
**Environment:** Staging only — NOT production  
**Pilot size:** 5–10 internal users  
**Duration:** 5–7 วันทำการ

> All pre-pilot blockers resolved:  
> C1–C6 CSRF ✅ · P1 Pagination ✅ · P2 index.php routing ✅ · P3 dev artifacts removed ✅  
> For scope and policies see `docs/pilot-wave-1-plan.md`  
> For deployment steps see `docs/staging-deployment-checklist.md`

---

## PART 1 — Pilot Entry Gate (ทำก่อน go-live)

ต้องผ่านทุกข้อก่อน notify pilot users

```
PILOT ENTRY GATE
Date: _______  Approver: _______________________

─── CODE STATE ──────────────────────────────────────────────────────────────
[ ] git log --oneline -1 → b02a10b (หรือ commit ล่าสุดบน staging)
[ ] git ls-files | grep -E "task29|task33" → empty
[ ] grep -r "Dashboard (Mock)" . → ไม่พบใน PHP files
[ ] git diff main..HEAD → clean (no uncommitted changes)

─── DATABASE ────────────────────────────────────────────────────────────────
[ ] php scripts/migrate.php status → Pending=0, Modified=0
    Output: ________________________________________
[ ] php scripts/migrate.php checksum → all OK

─── SEED / ACCOUNTS ─────────────────────────────────────────────────────────
[ ] APP_ENV=staging php scripts/seed_staging.php --dry-run → all [SKIP]
[ ] All 5 role accounts exist and reachable:
    [ ] admin_test     (role: admin)
    [ ] registrar_test (role: registrar)
    [ ] prof_test      (role: professor)
    [ ] student_test   (role: student)
    [ ] alumni_test    (role: alumni)
[ ] Passwords communicated to pilot users via secure channel (NOT repo)

─── BACKUP ──────────────────────────────────────────────────────────────────
[ ] DB_NAME=dci_sis DB_USER=... DB_PASS=... bash scripts/backup_database.sh
    Backup file: ___________________________________________
    Size: ___________
[ ] gunzip -t <backup_file> && echo OK  → OK

─── AUTOMATED SMOKE ─────────────────────────────────────────────────────────
[ ] BASE_URL=https://[staging]/dci-sis bash scripts/smoke_check.sh → exit 0
    All checks: PASS
    Sensitive paths (config/, .env, .sql): HTTP 403

─── k6 STAGING SMOKE (5 roles) ──────────────────────────────────────────────
[ ] K6_PROFILE=smoke k6 run tests/load/dci_sis_smoke_load.js
    checks:            100%
    dci_login_success: 100%
    http_req_failed:   0%
    alumni_dashboard ✓ ปรากฏใน output

─── MANUAL BROWSER VERIFICATION ────────────────────────────────────────────
[ ] admin_test     → login → lands on /admin/dashboard.php     (not mock)
[ ] registrar_test → login → lands on /registrar/dashboard.php
[ ] prof_test      → login → lands on /professor/dashboard.php
[ ] student_test   → login → lands on /student/dashboard.php
[ ] alumni_test    → login → lands on /alumni/dashboard.php

─── CSRF RUNTIME CHECK ──────────────────────────────────────────────────────
[ ] curl -s -o /dev/null -w "%{http_code}" -X POST \
      https://[staging]/dci-sis/student/requests.php \
      → 403
[ ] curl ... alumni/transcript_request.php → 403
[ ] curl ... alumni/certificate_request.php → 403
[ ] curl ... professor/exams.php → 403
[ ] curl ... registrar/dashboard.php → 403

─── LOGS (post-smoke) ───────────────────────────────────────────────────────
[ ] PHP error log → ไม่มี Fatal error
[ ] MySQL error log → ไม่มี connection error
[ ] Nginx/Apache log → ไม่มี 5xx หลัง smoke run

─── OPERATIONS ──────────────────────────────────────────────────────────────
[ ] Support owner:    ___________________________________
[ ] Escalation email/chat: ______________________________
[ ] Feedback form URL: __________________________________
[ ] Rollback owner:   ___________________________________
[ ] Pilot briefing delivered (ทุก pilot user รับทราบแล้ว)
[ ] Pilot agreement signed (ถ้าจำเป็น)

─── GATE SIGN-OFF ───────────────────────────────────────────────────────────
Tech Lead:  _________________________ Date: _______
QA Lead:    _________________________ Date: _______
Ops/DBA:    _________________________ Date: _______
```

> **ห้ามเปิด pilot ถ้า gate ไม่ผ่านครบ**

---

## PART 2 — Pilot Execution Runbook (ลำดับขั้นตอน)

### Step 0 — Record Execution Context

```
PILOT WAVE 1 — EXECUTION LOG
=============================
Start date/time: _______________________________
End date (planned): ____________________________
Commit deployed:  b02a10b (หรือ commit ล่าสุด)
Environment:      staging (APP_ENV=staging, APP_DEBUG=false)
Staging URL:      https://[staging]/dci-sis
Pilot lead:       ________________________________
Support owner:    ________________________________
Rollback owner:   ________________________________
Feedback form:    ________________________________
```

### Step 1 — Pre-Pilot Backup

```bash
# รันก่อนเปิด pilot เสมอ — ห้ามข้ามขั้นตอนนี้
DB_NAME=dci_sis \
DB_USER=<db_user> \
DB_PASS=<db_pass> \
bash scripts/backup_database.sh

# ยืนยัน backup ไม่เสีย
BACKUP_FILE=$(ls -t backups/*.sql.gz | head -1)
gunzip -t "$BACKUP_FILE" && echo "Backup OK: $BACKUP_FILE"
```

บันทึก backup file path: ________________________________

### Step 2 — Migration Status Verification

```bash
APP_ENV=staging php scripts/migrate.php status
# Expected: Pending=0, Modified=0

APP_ENV=staging php scripts/migrate.php checksum
# Expected: all checksums OK
```

### Step 3 — Seed / Account Verification

```bash
# dry-run ก่อน ห้าม --apply ถ้าไม่จำเป็น
APP_ENV=staging php scripts/seed_staging.php --dry-run
# Expected: ทุก row แสดง [SKIP] — accounts มีอยู่แล้ว

# ถ้ามี row [DRY] (account ไม่มี) ค่อยรัน apply:
# APP_ENV=staging SEED_CONFIRM=YES SEED_DEFAULT_PASSWORD='<password>' \
#   php scripts/seed_staging.php --apply
```

### Step 4 — Automated Smoke Check

```bash
BASE_URL="https://[staging]/dci-sis" bash scripts/smoke_check.sh
# Expected: exit 0 — all checks PASS
```

### Step 5 — k6 Smoke (5 Roles)

```bash
BASE_URL="https://[staging]/dci-sis" \
ADMIN_USER="admin_test"         ADMIN_PASS="<SEED_DEFAULT_PASSWORD>" \
REGISTRAR_USER="registrar_test" REGISTRAR_PASS="<SEED_DEFAULT_PASSWORD>" \
PROFESSOR_USER="prof_test"      PROFESSOR_PASS="<SEED_DEFAULT_PASSWORD>" \
STUDENT_USER="student_test"     STUDENT_PASS="<SEED_DEFAULT_PASSWORD>" \
ALUMNI_USER="alumni_test"       ALUMNI_PASS="<SEED_DEFAULT_PASSWORD>" \
K6_PROFILE=smoke \
k6 run tests/load/dci_sis_smoke_load.js

# Pass criteria:
# checks:            100%
# dci_login_success: 100%
# http_req_failed:   0%
# alumni_dashboard ✓ ปรากฏใน output
```

> **ถ้า k6 smoke FAIL → หยุดทันที ห้ามเปิด pilot**

### Step 6 — Notify Pilot Users

ส่ง briefing message ถึงทุก pilot user ก่อนเปิด:

```
เรียน Pilot User ทุกท่าน

ระบบ DCI-SIS Pilot Wave 1 เปิดใช้งานแล้ว

URL:      https://[staging]/dci-sis/login.php
Username: [ส่งให้แต่ละคนแยก]
Password: [ส่งผ่าน channel ที่ปลอดภัยแยกต่างหาก]

ข้อควรทราบ:
- นี่คือ staging environment ไม่ใช่ production
- ข้อมูลที่ใส่จะถูก reset หลังสิ้นสุด pilot
- ถ้าพบปัญหาให้แจ้งผ่าน: [feedback form URL]
- เร่งด่วน (ระบบพัง): ติดต่อ [support owner] ทันที
- ระยะเวลา pilot: [start date] — [end date]

ขอบคุณสำหรับการมีส่วนร่วม
[Pilot Lead]
```

### Step 7 — Open Pilot Window

```
Pilot officially OPEN:
Date/time: _______________________________
Notified users: __ / __ คน
```

### Step 8 — Daily Monitoring (ทำทุกเช้า)

ดู Part 3 — Daily Monitoring Checklist

### Step 9 — Daily Report

ดู Part 6 — Daily Pilot Report Template

### Step 10 — End-of-Pilot Review

ดู Part 7 — End-of-Pilot Review Template

---

## PART 3 — Pilot User Matrix

| Role | จำนวน | Test Account | Modules ที่เปิด | Restrictions |
|------|--------|-------------|----------------|-------------|
| **admin** | 1 | `admin_test` | dashboard, users (view), audit-logs (view/filter), roles (view) | ห้าม mass delete users; ห้ามแก้ settings |
| **registrar** | 1–2 | `registrar_test` | dashboard, students (list/search/add), sections (view/manage), transcripts (search/view), document-requests (view queue), courses, semesters | ห้าม issue official certificate; petition approve/deny ทำได้แต่ไม่มี audit log |
| **professor** | 1–2 | `prof_test` | dashboard, courses, students, gradebook (DCI101/001 only), exams | ห้ามบันทึกคะแนนนอก test section |
| **student** | 2–4 | `student_test` | dashboard, enrollment (view+test add/drop บน DCI101/001), grades, transcript (view only), courses, schedule, requests (submit) | ห้าม print transcript จริง; enrollment บน real sections only |
| **alumni** | 1 | `alumni_test` | dashboard, transcript_request, certificate_request | ห้ามใช้ output เป็นเอกสารราชการ |

**Modules ที่ปิดทุก role:**

| Module | เหตุผล |
|--------|--------|
| `admin/settings.php` | Write forms ยังไม่ audit |
| Production transcript/certificate issue | ห้ามออกเอกสารราชการจาก staging |
| Mass import / bulk delete | ยังไม่ implement |
| Real payment/finance | ไม่มีใน scope |

---

## PART 4 — Allowed Module Map (สรุปเร็ว)

```
LOGIN/LOGOUT           ทุก role ✅
LANGUAGE SWITCH        ทุก role ✅
INDEX REDIRECT         ✅ → role dashboard โดยตรง (P2 fixed)

ADMIN
  dashboard            ✅ view KPI + audit feed
  users                ✅ view list only
  audit-logs           ✅ view + filter
  roles                ✅ view only
  settings             ❌ CLOSED

REGISTRAR
  dashboard            ✅ + petition queue (no audit log ⚠️)
  students             ✅ list/search/add (paginated P1 fixed)
  sections             ✅ view + manage
  transcripts          ✅ search + view
  document-requests    ✅ view queue only
  courses / semesters  ✅ view
  certificate-print    ⚠️ test only, no official output

PROFESSOR
  dashboard            ✅
  courses / students   ✅ view
  gradebook            ✅ DCI101/001 test section only
  exams                ✅ view + save scores (test section only)

STUDENT
  dashboard            ✅
  enrollment           ✅ add/drop DCI101/001 test section
  grades / transcript  ✅ view only
  courses / schedule   ✅ view
  requests             ✅ submit transcript request

ALUMNI
  dashboard            ✅
  transcript_request   ✅ submit form
  certificate_request  ✅ submit form
```

---

## PART 5 — Daily Monitoring Checklist

ให้ Support Owner รันทุกเช้าก่อน 10:00 น.:

```
DAILY PILOT MONITOR
Date: _______  Checked by: _______  Day: ___ of ___

─── ERROR LOGS ──────────────────────────────────────────────────────────────
[ ] PHP error log (since yesterday):
    tail -100 /var/log/php/error.log | grep -iE "fatal|uncaught|parse error"
    Fatal count: ___  Details: _______________________________

[ ] 5xx errors today:
    grep " 5[0-9][0-9] " /var/log/nginx/access.log | grep "$(date +%Y-%m-%d)" | wc -l
    Count: ___ (stop pilot if > 5 on core pages)

[ ] MySQL error log:
    tail -20 /var/log/mysql/error.log
    Status: [ ] OK  [ ] Warning: _______________________________

─── SECURITY SIGNALS ────────────────────────────────────────────────────────
[ ] Login failures (audit_logs):
    SELECT COUNT(*), username_attempted
    FROM audit_logs
    WHERE action = 'AUTH.LOGIN_FAILED' AND created_at >= CURDATE()
    GROUP BY username_attempted
    HAVING COUNT(*) > 5;
    Suspicious accounts: _______________________________________

[ ] CSRF blocks (should be 0):
    SELECT COUNT(*) FROM audit_logs
    WHERE action LIKE 'CSRF%' AND created_at >= CURDATE();
    Count: ___ (if > 0: investigate immediately)

[ ] Role access violations:
    SELECT * FROM audit_logs
    WHERE action = 'AUTH.FORBIDDEN' AND created_at >= CURDATE();
    Count: ___

─── PERFORMANCE ─────────────────────────────────────────────────────────────
[ ] Slow queries (if enabled):
    mysqldumpslow -s t -t 5 /var/log/mysql/slow.log
    New slow queries: ___

[ ] Audit log row count (baseline: < 500/day for 5–10 users):
    SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE();
    Today: ___

─── FUNCTIONAL SPOT CHECK ───────────────────────────────────────────────────
[ ] Login admin_test → admin/dashboard.php → KPI cards load: [ ] OK
[ ] Login student_test → student/enrollment.php → sections visible: [ ] OK
[ ] Login registrar_test → registrar/students.php → list loads ≤ 50 rows: [ ] OK
[ ] Logout from each → redirect to login.php: [ ] OK

─── FEEDBACK ────────────────────────────────────────────────────────────────
[ ] New feedback since yesterday: ___
[ ] Critical/High issues new: ___
[ ] Issues resolved today: ___
[ ] Issues needing immediate action: ___

─── DECISION ────────────────────────────────────────────────────────────────
[ ] Continue pilot tomorrow: YES / NO
[ ] Issues to escalate: ___________________________________
Signature: _______________________
```

---

## PART 6 — Feedback Template

Pilot users กรอก template นี้เมื่อพบปัญหา ส่งผ่าน feedback form:

```
DCI-SIS PILOT WAVE 1 — ISSUE / FEEDBACK REPORT
================================================

วันที่และเวลา:       ___________________________________
ผู้รายงาน (ชื่อ):   ___________________________________
Role ที่ใช้งาน:     [ ] admin  [ ] registrar  [ ] professor  [ ] student  [ ] alumni

MODULE / PAGE
URL หรือชื่อหน้า:    ___________________________________
Action ที่ทำ:        ___________________________________

ผลที่คาดหวัง:       ___________________________________
ผลที่ได้รับจริง:     ___________________________________

ขั้นตอนการเกิดซ้ำ (Reproducible Steps):
  1. _________________________________________________
  2. _________________________________________________
  3. _________________________________________________

ความรุนแรง:
  [ ] Critical — ระบบใช้งานไม่ได้ / ข้อมูลผิด / security breach
  [ ] High     — task หลักทำไม่ได้ แต่ระบบยังทำงาน
  [ ] Medium   — ใช้งานได้แต่ติดปัญหา / validation สับสน
  [ ] Low      — UI / wording / layout เล็กน้อย

สามารถเกิดซ้ำได้:  [ ] ทุกครั้ง  [ ] บางครั้ง  [ ] ครั้งเดียว
Screenshot/Video:  [ ] แนบมาด้วย  [ ] ไม่มี
Browser / Device:   ___________________________________
หมายเหตุ:          ___________________________________
```

---

## PART 7 — Issue Severity Matrix

| Severity | คำนิยาม | SLA | Action |
|---------|---------|-----|--------|
| **Critical** | Data leak · Role guard หลุด · Login ทุก role ไม่ได้ · CSRF bypass · Data corruption | **ทันที** | หยุด pilot ทันที → notify support owner → escalate |
| **High** | Core task ทำไม่ได้ · Enrollment/gradebook fail · Transcript ผิดคน · 500 ซ้ำ > 3 ครั้ง | **4 ชั่วโมง** | Report → assign fix → verify before resuming |
| **Medium** | หน้าช้า · Validation สับสน · Pagination ผิด · Workflow bug ที่มี workaround | **1 วันทำการ** | Log → plan fix in next cycle |
| **Low** | UI wording · Layout · ปุ่ม align · สะกดผิด | **ก่อน Wave 2** | Backlog |

---

## PART 8 — Stop / Rollback Criteria

### 8A. หยุด Pilot ทันที (Critical — 9 triggers)

| # | เหตุการณ์ | Action ทันที |
|---|---------|------------|
| S1 | Login ล้มเหลว > 50% หรือทุก role เข้าไม่ได้ | หยุด + ตรวจ session/auth config |
| S2 | Role guard หลุด — user role A เห็น data ของ role B | หยุดทันที + revoke all sessions + security escalate |
| S3 | User เห็น transcript / grade ของคนอื่น | หยุดทันที — data breach protocol |
| S4 | Enrollment/grade write ผิด student_id | หยุด + ตรวจ query ownership |
| S5 | HTTP 500 ซ้ำ > 3 ครั้งบน core pages (login/dashboard/enrollment) | หยุด + ตรวจ PHP error log |
| S6 | PDOException / DB connection refused ต่อเนื่อง | หยุด + ตรวจ MySQL status |
| S7 | Backup script ล้มเหลวเมื่อต้องใช้งาน | escalate ก่อน rollback |
| S8 | หน้าไม่โหลดภายใน 30 วินาที สำหรับ < 10 concurrent users | ตรวจ server resources + slow query log |
| S9 | CSRF bypass ยืนยัน — write สำเร็จโดยไม่มี valid token | หยุดทันที — security incident |

### 8B. หยุด Pilot ภายใน 4 ชั่วโมง (High)

| # | เหตุการณ์ | Action |
|---|---------|--------|
| H1 | Student/alumni submit request ไม่ได้ซ้ำๆ | ตรวจ CSRF flow + form validation |
| H2 | Registrar student list ผิดหรือ pagination พัง | ตรวจ P1 fix, query log |
| H3 | Professor save exam scores ไม่ได้ | ตรวจ exams.php + verify_csrf |
| H4 | Student transcript แสดง GPA ผิดหรือข้อมูลผิดคน | ตรวจ query ownership clause |
| H5 | Audit log ไม่บันทึก events บาง action | ตรวจ logAudit() call |

### 8C. Rollback Procedure

```bash
# ━━━━━━ INCIDENT RESPONSE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

# STEP 1: แจ้ง pilot users ว่าระบบหยุดชั่วคราว (IMMEDIATELY)
# STEP 2: บันทึก incident details + timestamp

# STEP 3: Rollback code (ถ้าปัญหาเกิดจาก recent commit)
git log --oneline -5          # identify last known-good commit
git revert HEAD --no-edit     # revert latest commit
git push origin main
# deploy to staging

# STEP 4: Restore database (ถ้าเกิด data corruption)
RESTORE_CONFIRM=YES \
  bash scripts/restore_database.sh backups/[backup-file].sql.gz

# STEP 5: Verify after rollback/restore
APP_ENV=staging php scripts/migrate.php status
BASE_URL="https://[staging]/dci-sis" bash scripts/smoke_check.sh

# STEP 6: Post-incident review ก่อน resume pilot
#         - Root cause documented
#         - Fix verified
#         - Tech Lead sign-off
```

---

## PART 9 — Daily Pilot Report Template

```
DCI-SIS PILOT WAVE 1 — DAILY REPORT
=====================================
Date:        _______________  Day: ___ of ___
Reported by: _______________

PARTICIPATION
Active users today:  ___  / ___ total pilot users
Roles active:        [ ] admin  [ ] registrar  [ ] professor  [ ] student  [ ] alumni
Sessions today:      ___
Logins today:        ___

MODULES TESTED TODAY
[ ] Login/logout
[ ] Admin: dashboard / users / audit-logs
[ ] Registrar: students / sections / transcripts / document-requests
[ ] Professor: gradebook / exams
[ ] Student: enrollment / grades / transcript / requests
[ ] Alumni: transcript_request / certificate_request

ISSUES TODAY
New issues:                      ___
  Critical: ___  High: ___  Medium: ___  Low: ___
Issues resolved today:           ___
Issues still open (Critical):    ___
Issues still open (High):        ___

MONITORING SUMMARY
PHP fatal errors:   ___  (new)
5xx errors:         ___
Login failures:     ___  (suspicious: ___)
CSRF blocks:        ___
Audit log entries:  ___

PERFORMANCE NOTES
Slowest page (user-reported): ___________________________________
Performance issues: [ ] None  [ ] Medium  [ ] Severe

USER FEEDBACK HIGHLIGHTS
Positive: ___________________________________________________
Issues/Confusions: ___________________________________________
Requests: ___________________________________________________

ACTIONS TAKEN TODAY
1. ___________________________________________________________
2. ___________________________________________________________

DECISION
[ ] Continue pilot tomorrow — no blockers
[ ] Continue with caution — monitoring [High] issue: ___________
[ ] PAUSE pilot — investigating [Critical]: ____________________
[ ] STOP pilot — rollback required

Signed: _______________________  Time: ________
```

---

## PART 10 — End-of-Pilot Wave 1 Review Template

```
DCI-SIS PILOT WAVE 1 — END-OF-PILOT REVIEW
============================================
Date:      _______________
Attendees: _______________________________________________

EXECUTION SUMMARY
Start date:              _______________
End date:                _______________
Actual duration:         ___ วันทำการ (planned: 5–7 วัน)
Commit deployed:         b02a10b (หรือ commit ล่าสุด)
Environment:             staging (APP_ENV=staging)

PARTICIPATION
Total pilot users:       ___  (target: 5–10)
Roles represented:       ___
Total sessions (logins): ___
Total audit log entries: ___

MODULES COVERAGE
[ ] Login/logout ✓
[ ] Admin dashboard / users / audit-logs
[ ] Registrar dashboard / students (pagination) / sections / transcripts
[ ] Professor gradebook / exams (DCI101/001)
[ ] Student enrollment / grades / transcript / requests
[ ] Alumni transcript_request / certificate_request
Modules NOT tested: _________________________________________
Reason: ___________________________________________________

ISSUE SUMMARY
                   Found   Resolved   Unresolved
  Critical:        ___     ___        ___
  High:            ___     ___        ___
  Medium:          ___     ___        ___
  Low:             ___     ___        ___

TOP ISSUES FOUND
1. [Severity] _____________________________________________
2. [Severity] _____________________________________________
3. [Severity] _____________________________________________

PERFORMANCE (staging server)
Slowest page reported:   ___  avg: ___ms
Slow queries found:      ___
Any resource saturation: ___

SECURITY OBSERVATIONS
CSRF bypass attempts:    ___
Role access violations:  ___
Login brute force:       ___
Unexpected audit events: ___

SUPPORT WORKLOAD
Total incidents:         ___
Critical incidents:      ___
Avg resolution time:     ___
Escalations:             ___

USER FEEDBACK
สิ่งที่ users ชอบ:
  _________________________________________________________
สิ่งที่ users สับสน:
  _________________________________________________________
สิ่งที่ users ต้องการเพิ่ม (Wave 2):
  _________________________________________________________

GO / NO-GO for Pilot Wave 2
[ ] ✅ GO          — Critical unresolved = 0, High unresolved ≤ 1
[ ] 🟡 GO WITH CONDITIONS — High unresolved ≤ 3 with workaround plan
[ ] 🔴 NO-GO       — Critical unresolved > 0, or High unresolved > 3

MUST-FIX BEFORE WAVE 2
1. ___________________________________________________________
2. ___________________________________________________________
3. ___________________________________________________________

CAN DEFER TO WAVE 2 OR LATER
1. ___________________________________________________________
2. ___________________________________________________________

RECOMMENDATION
[ ] Proceed to Pilot Wave 2 (expand to ___ users)
[ ] Extend Wave 1 by ___ days (reason: _______________________)
[ ] Stop and fix blockers (reason: ___________________________)
[ ] Proceed to production (if Wave 2 scope is minimal)

SIGN-OFF
Tech Lead:  _________________________ Date: _______
QA Lead:    _________________________ Date: _______
Ops/DBA:    _________________________ Date: _______
Pilot Lead: _________________________ Date: _______
```

---

## PART 11 — Quick Reference (Pilot Support Card)

สำหรับ Support Owner — พิมพ์ให้พร้อมระหว่าง pilot

```
┌─────────────────────────────────────────────────────────────────┐
│  DCI-SIS PILOT WAVE 1 — SUPPORT QUICK REFERENCE                │
├─────────────────────────────────────────────────────────────────┤
│  STAGING URL:    https://[staging]/dci-sis/login.php           │
│  FEEDBACK FORM:  [URL]                                          │
│  SUPPORT:        [Name / contact]                               │
│  ESCALATION:     [Name / channel]                               │
├─────────────────────────────────────────────────────────────────┤
│  ROLLBACK (if needed):                                          │
│    git revert HEAD --no-edit && git push origin main            │
│    RESTORE_CONFIRM=YES bash scripts/restore_database.sh \       │
│      backups/[pre-pilot-backup].sql.gz                          │
├─────────────────────────────────────────────────────────────────┤
│  DAILY SPOT CHECK:                                              │
│    tail -50 /var/log/php/error.log | grep -iE "fatal|error"    │
│    SELECT COUNT(*) FROM audit_logs WHERE created_at>=CURDATE();│
├─────────────────────────────────────────────────────────────────┤
│  STOP PILOT IF:                                                 │
│    ✗ Login fails > 50%                                          │
│    ✗ Role guard breach                                          │
│    ✗ User sees another user's data                              │
│    ✗ HTTP 500 > 3× on core pages                               │
│    ✗ CSRF bypass confirmed                                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## PART 12 — Key Files Reference

| File | Purpose |
|------|---------|
| `docs/pilot-wave-1-plan.md` | Scope, entry criteria, stop criteria (Phase 2F) |
| `docs/staging-deployment-checklist.md` | Server setup + deploy steps |
| `docs/backup-restore-plan.md` | Backup schedule + restore drill |
| `docs/test-plan.md` | 148 functional test cases |
| `docs/load-test-plan.md` | k6 profiles + thresholds |
| `docs/staging-seed-data.md` | Test account setup guide |
| `docs/production-smoke-checklist.md` | Manual smoke check steps |
| `scripts/backup_database.sh` | Run before pilot go-live |
| `scripts/restore_database.sh` | Run if data corruption occurs |
| `scripts/smoke_check.sh` | Automated public smoke check |
| `scripts/migrate.php` | Migration status verification |
| `tests/load/dci_sis_smoke_load.js` | k6 5-role smoke + baseline |
| `results/k6-baseline-local.txt` | Local baseline evidence (reference) |
