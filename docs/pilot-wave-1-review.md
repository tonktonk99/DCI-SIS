# DCI-SIS Pilot Wave 1 — Review Document

**Phase:** 2L — Pilot Wave 1 Review  
**สร้าง:** 2026-06-29  
**สถานะ:** ⚠️ REVIEW TEMPLATE — Pilot ยังไม่ได้รัน / ไม่มีข้อมูลจากผู้ใช้จริง  
**Environment:** Staging (ยังไม่ได้ deploy) — Local MAMP เท่านั้น  
**Review lead:** [กรอกชื่อหลัง pilot เสร็จ]

> **IMPORTANT: Review นี้ไม่สามารถเสร็จสมบูรณ์ได้**  
> ไม่พบข้อมูล pilot จริง: ไม่มี daily monitoring reports ที่กรอกแล้ว, ไม่มี feedback files, ไม่มี issue logs, ไม่มี staging server results  
> ข้อมูลที่มีจริง: `results/k6-baseline-local.txt` (local MAMP, ไม่ใช่ staging)  
>  
> **วิธีใช้เอกสารนี้:** กรอก `[PENDING]` ทุกช่องหลังจาก pilot รัน 5–7 วันจริงบน staging  
> ดู `docs/pilot-wave-1-daily-monitoring.md` สำหรับ template เก็บข้อมูลรายวัน  
> ดู `docs/pilot-wave-1-plan.md` สำหรับ scope และ criteria  
> ดู `docs/pilot-wave-1-execution-runbook.md` สำหรับ entry gate และ runbook

---

## REVIEW CANNOT BE COMPLETED — สรุปสาเหตุ

| หลักฐานที่ต้องการ | สถานะ | หมายเหตุ |
|-----------------|-------|---------|
| Daily monitoring reports (Day 1–7) | ❌ ไม่พบ | `docs/pilot-wave-1-daily-monitoring.md` เป็น blank template |
| User feedback files / form responses | ❌ ไม่พบ | ไม่มีไฟล์ feedback ใน repo |
| Issue log (actual issues from pilot) | ❌ ไม่พบ | ไม่มีไฟล์ issue log |
| Staging k6 smoke results | ❌ ไม่พบ | `results/load/` ว่างเปล่า |
| Staging k6 baseline results | ❌ ไม่พบ | มีเฉพาะ local MAMP baseline |
| Staging server logs | ❌ ไม่พบ | ไม่มีในระบบ local |
| Pilot user participation records | ❌ ไม่พบ | ไม่มีข้อมูล |
| Alumni k6 coverage (staging) | ❌ ไม่พบ | ALUMNI_USER ไม่ได้ set ใน baseline run |

**Pre-pilot evidence ที่มีอยู่จริง (ใช้ reference ได้):**

| หลักฐาน | สถานะ | รายละเอียด |
|--------|-------|----------|
| k6 baseline (local MAMP) | ✅ | `results/k6-baseline-local.txt` — checks 100%, login 100%, failed 0% |
| All k6 thresholds PASS | ✅ | p95 auth 310ms · fast 18ms · heavy 16ms · public 2ms · standard 10ms |
| C1–C6 CSRF fixed | ✅ | commits a0f0144–a9a4a36 |
| P1 pagination fixed | ✅ | commit 645dd18 |
| P2 index.php redirect | ✅ | commit 7dba85c |
| P3 dev artifacts removed | ✅ | commit b02a10b |
| Seed accounts ready | ✅ | alumni_test id=12 confirmed (dry-run [SKIP]) |
| Runbook ready | ✅ | `docs/pilot-wave-1-execution-runbook.md` |
| Daily monitoring template | ✅ | `docs/pilot-wave-1-daily-monitoring.md` |

---

## PART 1 — Pilot Wave 1 Overview (กรอกหลัง pilot เสร็จ)

```
PILOT WAVE 1 OVERVIEW
=====================
Pilot start date:     [PENDING]
Pilot end date:       [PENDING]
Duration (actual):    [PENDING] วันทำการ (planned: 5–7)
Environment:          Staging — APP_ENV=staging
Staging URL:          https://[staging]/dci-sis/  [PENDING]
Commit deployed:      [PENDING] (ณ วันที่ go-live)
DB schema status:     [PENDING] — migrate.php status Pending=0

TEAM
Support owner:        [PENDING]
Monitoring owner:     [PENDING]
QA lead:              [PENDING]
Tech lead:            [PENDING]

PILOT USERS
Total enrolled:       [PENDING] / 10 max
Total active:         [PENDING] (users ที่ login อย่างน้อย 1 ครั้ง)
Total sessions:       [PENDING]
Total audit entries:  [PENDING]
```

---

## PART 2 — Pilot Participation by Role (กรอกหลัง pilot เสร็จ)

### Admin

```
จำนวน users:     [PENDING]  (target: 1)
Sessions:         [PENDING]
Modules used:     [ ] dashboard  [ ] users (view)  [ ] audit-logs  [ ] roles
Workflows:        [PENDING]
Issues reported:  [PENDING]
Feedback quality: [PENDING]
Notes:            [PENDING]
```

### Registrar

```
จำนวน users:     [PENDING]  (target: 1–2)
Sessions:         [PENDING]
Modules used:
  [ ] dashboard   [ ] students (paginated/search)  [ ] sections
  [ ] transcripts [ ] document-requests            [ ] courses
Workflows:        [PENDING]
Issues reported:  [PENDING]
Feedback quality: [PENDING]
Notes:            [PENDING]
```

### Professor

```
จำนวน users:     [PENDING]  (target: 1–2)
Sessions:         [PENDING]
Modules used:
  [ ] dashboard  [ ] gradebook (DCI101/001 only)  [ ] exams (test section)
  [ ] courses    [ ] students (view)
Workflows:        [PENDING]
Issues reported:  [PENDING]
Feedback quality: [PENDING]
Notes:            [PENDING]
```

### Student

```
จำนวน users:     [PENDING]  (target: 2–4)
Sessions:         [PENDING]
Modules used:
  [ ] dashboard  [ ] enrollment (view+add/drop DCI101/001)
  [ ] transcript (view)  [ ] grades (view)  [ ] courses
  [ ] schedule   [ ] requests (submit)
Workflows:        [PENDING]
Issues reported:  [PENDING]
Feedback quality: [PENDING]
Notes:            [PENDING]
```

### Alumni

```
จำนวน users:     [PENDING]  (target: 1)
Sessions:         [PENDING]
Modules used:
  [ ] dashboard  [ ] transcript_request  [ ] certificate_request
Workflows:        [PENDING]
Issues reported:  [PENDING]
Feedback quality: [PENDING]
Notes:            [PENDING]

⚠️  NOTE: Alumni k6 coverage ยังไม่ได้ verify บน staging
    ต้องรัน baseline (20+ VUs) ด้วย ALUMNI_USER set ก่อน Wave 2
    ดู docs/load-test-plan.md สำหรับขั้นตอน
```

---

## PART 3 — Core Workflow Results (กรอกหลัง pilot เสร็จ)

| Workflow | Role | สถานะ | Issues | หมายเหตุ |
|---------|------|-------|--------|---------|
| Login (all roles) | all | [PENDING] | | |
| Logout + session clear | all | [PENDING] | | |
| Session idle timeout | all | [PENDING] | | |
| Language switch TH/EN | all | [PENDING] | | |
| Role access guard | all | [PENDING] | | |
| CSRF-protected POST forms | all | [PENDING] | | |
| admin: dashboard KPI | admin | [PENDING] | | |
| admin: users list (view) | admin | [PENDING] | | |
| admin: audit-logs view/filter | admin | [PENDING] | | |
| registrar: dashboard | registrar | [PENDING] | | |
| registrar: students (paginated) | registrar | [PENDING] | | |
| registrar: students search | registrar | [PENDING] | | |
| registrar: sections manage | registrar | [PENDING] | | |
| registrar: transcripts search | registrar | [PENDING] | | |
| registrar: document-requests queue | registrar | [PENDING] | | |
| professor: gradebook (DCI101/001) | professor | [PENDING] | | |
| professor: exams save scores | professor | [PENDING] | | |
| student: enrollment view | student | [PENDING] | | |
| student: enrollment add/drop | student | [PENDING] | | |
| student: transcript view | student | [PENDING] | | |
| student: grades view | student | [PENDING] | | |
| student: document request submit | student | [PENDING] | | |
| alumni: dashboard | alumni | [PENDING] | | |
| alumni: transcript request | alumni | [PENDING] | | |
| alumni: certificate request | alumni | [PENDING] | | |

---

## PART 4 — Issue Summary (กรอกหลัง pilot เสร็จ)

### Pre-Pilot Known Issues (ยังไม่แก้ — deferred จาก pilot-wave-1-plan.md)

| ID | Module | Role | ปัญหา | Severity | Impact | Status | Fix Before Wave 2? |
|----|--------|------|-------|----------|--------|--------|-------------------|
| H1 | `scripts/rehash_legacy_passwords.php` | admin | ไม่มีสคริปต์ rehash MD5 passwords | High | Pilot ใช้ bcrypt seed — ไม่กระทบ Wave 1 | ⏳ Deferred | YES — ก่อน production |
| M1 | `registrar/dashboard.php` | registrar | ไม่มี `logAudit()` สำหรับ petition approve/deny | Medium | Petition audit trail ขาดหาย | ⏳ Deferred | YES — ก่อน Wave 2 ถ้า petition ถูกใช้ |
| M2 | `alumni/dashboard.php` lines 28–29 | alumni | Hardcoded `/dci-sis/` แทน `APP_BASE` | Medium | ถ้า APP_BASE เปลี่ยน links พัง | ⏳ Deferred | Recommended ก่อน Wave 2 |
| M2b | `registrar/dashboard.php` lines 245–248 | registrar | Hardcoded `/dci-sis/` แทน `APP_BASE` | Medium | เหมือน M2 | ⏳ Deferred | Recommended ก่อน Wave 2 |

### Issues Found During Pilot (กรอกหลัง pilot)

```
ISSUE LOG
─────────────────────────────────────────────────────────────────────────────
Issue #001
  Pilot Day:          [PENDING]
  Module/Page:        [PENDING]
  Role:               [PENDING]
  Description:        [PENDING]
  Severity:           [ ] Critical  [ ] High  [ ] Medium  [ ] Low
  Impact:             [PENDING]
  Reproducible steps: [PENDING]
  Status:             [PENDING]
  Owner:              [PENDING]
  Recommended fix:    [PENDING]
  Must fix before Wave 2: [ ] Yes  [ ] No
  Can defer:          [ ] Yes  [ ] No (เหตุผล: [PENDING])
─────────────────────────────────────────────────────────────────────────────
(เพิ่ม Issue #002, #003... ตามจำนวนจริง)
```

### Issue Summary Table (กรอกหลัง pilot)

```
SEVERITY SUMMARY
  Critical found / resolved:  ___ / ___
  High found / resolved:      ___ / ___
  Medium found / resolved:    ___ / ___
  Low found / resolved:       ___ / ___

MUST FIX BEFORE WAVE 2 (จากปัญหาจริง + pre-existing)
  Critical: ___
  High:     ___
  Medium:   ___

CAN DEFER AFTER WAVE 2
  Low:      ___
  Nice-to-have: ___
```

---

## PART 5 — Security Observations (กรอกหลัง pilot เสร็จ)

### Pre-Pilot Security Baseline (ข้อมูลที่มีอยู่แล้ว)

| หัวข้อ | สถานะก่อน pilot | Evidence |
|--------|---------------|---------|
| CSRF protection (C1–C6) | ✅ FIXED | commits a0f0144–a9a4a36 |
| Role access guard (requireRole) | ✅ IN PLACE | ทุก protected page ใช้ requireRole() |
| Sensitive path blocking | ✅ PASS | .htaccess blocks config/, .sql, .env, .sh |
| Session idle timeout | ✅ IN PLACE | _checkIdleTimeout() ใน auth.php |
| Audit logging | ✅ IN PLACE | logAudit() ใน 20+ files |
| k6 CSRF check (login page) | ✅ PASS | `_csrf field in HTML` ✓ |
| dev artifact removed (task33.md) | ✅ FIXED | commit b02a10b — ไม่มี HTTP-accessible credential |

### Security Observations from Pilot (กรอกหลัง pilot)

```
ROLE ACCESS VIOLATIONS
  พบ role guard หลุด:     [ ] ไม่มี  [ ] มี → รายละเอียด: [PENDING]
  พบ user เห็นข้อมูลผิดคน: [ ] ไม่มี  [ ] มี → รายละเอียด: [PENDING]

CSRF OBSERVATIONS
  CSRF blocks (false negative = write สำเร็จไม่มี token): [PENDING]
  CSRF false positive (user ถูก block ทั้งที่ form ถูก): [PENDING]
  รายละเอียด: [PENDING]

LOGIN SECURITY
  Login failures ผิดปกติ (> 5 ครั้งต่อ user ต่อวัน): [PENDING]
  Brute force pattern พบ: [ ] ไม่มี  [ ] มี → [PENDING]
  Unauthorized URL bypass attempt: [PENDING]

AUDIT LOG QUALITY
  logAudit ทำงานครบทุก action: [ ] ใช่  [ ] บาง action ขาด → [PENDING]
  Volume สมเหตุสมผล (< 500/วัน): [PENDING]
  Anomaly พบ: [PENDING]

SENSITIVE DATA
  ข้อมูล sensitive ใน response ผิดปกติ: [PENDING]
  Password/token ใน log ไหม: [PENDING]

OVERALL SECURITY: [ ] 🟢 Clean  [ ] 🟡 Minor issues  [ ] 🔴 Critical
```

---

## PART 6 — Performance Observations (กรอกหลัง pilot เสร็จ)

### Pre-Pilot Performance Baseline (ข้อมูลที่มีอยู่แล้ว)

> **หมายเหตุ:** ข้อมูลด้านล่างมาจาก local MAMP (PHP built-in + MySQL local)  
> ตัวเลขบน staging server จะต่างออกไป — ต้องรัน k6 บน staging ก่อน Wave 2

| Metric | Local MAMP Baseline | Threshold | Status |
|--------|---------------------|-----------|--------|
| checks | 100% (11,790/11,790) | > 95% | ✅ PASS |
| dci_login_success | 100% (324/324) | > 95% | ✅ PASS |
| http_req_failed | 0% (0/4,146) | < 1% | ✅ PASS |
| p(95) auth | 310.06ms | < 2,000ms | ✅ PASS |
| p(95) fast | 18.56ms | < 1,000ms | ✅ PASS |
| p(95) heavy | 16.30ms | < 2,000ms | ✅ PASS |
| p(95) public | 2.83ms | < 1,000ms | ✅ PASS |
| p(95) standard | 10.64ms | < 2,000ms | ✅ PASS |
| VUs | max 20 | baseline profile | ✅ |
| Duration | 10m 6.6s | 10m | ✅ |
| Total iterations | 1,543 | — | — |
| Data received | 52 MB (85 kB/s) | — | — |

**⚠️ Alumni ไม่ได้ cover ใน baseline run นี้**  
ALUMNI_USER env var ไม่ได้ set — slot 8 (VU9) ตก publicFlow แทน alumniFlow  
ต้องรัน baseline อีกครั้งด้วย ALUMNI_USER set เพื่อ verify alumni p95

### Performance Observations from Pilot Staging (กรอกหลัง pilot)

```
USER-REPORTED SLOWNESS
  หน้าที่ช้าที่สุด (user-reported):  [PENDING]
  เวลาประมาณ (วินาที):               [PENDING]
  เกิด timeout:  [ ] ไม่มี  [ ] มี → [PENDING]

HOT PATH OBSERVATIONS
  registrar/students.php pagination:  [ ] ✅ OK  [ ] ⚠️ [PENDING]
  professor/gradebook.php:            [ ] ✅ OK  [ ] ⚠️ [PENDING]
  student/enrollment.php:             [ ] ✅ OK  [ ] ⚠️ [PENDING]
  admin/audit-logs.php:               [ ] ✅ OK  [ ] ⚠️ [PENDING]
  registrar/transcripts.php:          [ ] ✅ OK  [ ] ⚠️ [PENDING]
  alumni/transcript_request.php:      [ ] ✅ OK  [ ] ⚠️ [PENDING]

STAGING k6 SMOKE RESULTS (ต้องรันก่อน Wave 2)
  checks:             [PENDING] %  (threshold: > 95%)
  dci_login_success:  [PENDING] %  (threshold: > 95%)
  http_req_failed:    [PENDING] %  (threshold: < 1%)
  alumni_dashboard ✓: [PENDING]    (ต้องปรากฏ)
  p(95) auth:         [PENDING] ms (threshold: < 2,000ms)

SERVER RESOURCE (ถ้ามี access)
  CPU peak:  [PENDING]
  RAM peak:  [PENDING]
  MySQL connections peak: [PENDING]
  Disk usage: [PENDING]

OVERALL PERFORMANCE: [ ] 🟢 Within thresholds  [ ] 🟡 Marginal  [ ] 🔴 Over threshold
```

---

## PART 7 — Operational Observations (กรอกหลัง pilot เสร็จ)

### Pre-Pilot Operational Readiness (ยืนยันแล้วก่อน go-live)

| รายการ | สถานะ | Evidence |
|--------|-------|---------|
| Backup scripts ready | ✅ | `scripts/backup_database.sh`, `scripts/restore_database.sh` |
| Rollback plan documented | ✅ | `docs/pilot-wave-1-execution-runbook.md` Section I3 |
| smoke_check.sh ready | ✅ | `scripts/smoke_check.sh` |
| migrate.php ready | ✅ | `scripts/migrate.php` |
| seed_staging.php ready | ✅ | accounts confirmed dry-run |
| Monitoring template ready | ✅ | `docs/pilot-wave-1-daily-monitoring.md` |
| Runbook documented | ✅ | `docs/pilot-wave-1-execution-runbook.md` |

### Operational Observations from Pilot (กรอกหลัง pilot)

```
BACKUP
  Pre-pilot backup completed:           [ ] Yes  [ ] No
  Backup file verified (gunzip -t OK):  [ ] Yes  [ ] No
  Daily backup during pilot:            [ ] ทุกวัน  [ ] บางวัน  [ ] ไม่ได้ทำ
  Backup issue found:                   [PENDING]

ROLLBACK
  Rollback trigger during pilot:        [ ] ไม่มี  [ ] มี (Day ___) → [PENDING]
  Rollback successful (ถ้าใช้):         [ ] N/A  [ ] Yes  [ ] No
  Restore drill ran:                    [ ] Yes  [ ] No (ควรทำก่อน production)

MONITORING
  Daily monitoring completed:           [PENDING] วัน / ___ วัน
  Monitoring กรอกครบทุกวัน:            [ ] ใช่  [ ] ขาด Day ___
  Logs checked every morning:           [ ] ทุกวัน  [ ] บางวัน
  Issue triage ทำงาน:                   [ ] ✅ OK  [ ] ⚠️ Issues

SUPPORT PROCESS
  Support owner responsive:             [ ] ✅ OK  [ ] ⚠️ Issues
  Feedback channel active:              [ ] ✅ OK  [ ] ⚠️ Low response
  Average response time:                [PENDING]
  Unresolved escalations:               [PENDING]

LOGS HEALTH (สรุปทั้ง pilot)
  Total PHP fatals (all days):          [PENDING]  (target = 0)
  Total 5xx (all days):                 [PENDING]  (target = 0 on core)
  Total DB errors (all days):           [PENDING]  (target = 0)
  Any slow query not seen before:       [PENDING]

OVERALL OPS: [ ] 🟢 Smooth  [ ] 🟡 Minor gaps  [ ] 🔴 Issues found
```

---

## PART 8 — User Feedback Summary (กรอกหลัง pilot เสร็จ)

```
USER FEEDBACK SUMMARY
═════════════════════
Total feedback received:    [PENDING]
Feedback by role:
  admin:      [PENDING]
  registrar:  [PENDING]
  professor:  [PENDING]
  student:    [PENDING]
  alumni:     [PENDING]

TOP POSITIVES (สิ่งที่ users ชอบ)
1. [PENDING]
2. [PENDING]
3. [PENDING]

TOP PAIN POINTS (สิ่งที่ users สับสน / ไม่ชอบ)
1. [PENDING]
2. [PENDING]
3. [PENDING]

FEATURE REQUESTS (บันทึก ไม่ commit ทำใน Wave 2 โดยไม่ review)
1. [PENDING]
2. [PENDING]

WORKFLOW CONFUSION POINTS
  Workflow ที่ user สับสนมากที่สุด: [PENDING]
  UX/wording ที่ต้องปรับก่อน Wave 2: [PENDING]

FEEDBACK QUALITY
  จำนวน reproducible reports:  [PENDING] / [PENDING] total
  Quality overall: [ ] High  [ ] Medium  [ ] Low
```

---

## PART 9 — Pilot Wave 1 Scorecard

> **หมายเหตุ:** คะแนนด้านล่างแบ่งเป็น 2 ส่วน  
> - **Pre-Pilot Score** — คะแนนที่ประเมินได้จากหลักฐานก่อน pilot (มีข้อมูลจริง)  
> - **Pilot Score** — ต้องกรอกหลัง pilot เสร็จ (ยังไม่มีข้อมูล)

### Pre-Pilot Assessment (ประเมินได้แล้ว)

| Category | Pre-Pilot Score | เหตุผล |
|---------|----------------|--------|
| Security | 88 | C1–C6 CSRF fixed, role guard in place, dev artifacts removed, audit logging 20+ files — deferred: MD5 rehash script |
| Functional readiness | 85 | k6 checks 100%, all 25 endpoints verified, pagination fixed, redirect fixed — deferred: petition audit log |
| Performance (local) | 90 | ALL thresholds pass; p95 auth 310ms, fast 18ms — **note: staging numbers unknown** |
| Data correctness | 82 | PDO prepared statements throughout, ownership checks in queries — unverified on real staging data |
| Role coverage | 80 | 5 roles in seed + k6 — alumni not covered in baseline (ALUMNI_USER not set) |
| Operational readiness | 85 | Scripts ready, backup ready, runbook documented, monitoring template ready |
| **Pre-Pilot Average** | **85** | ปัญหาหลัก: alumni k6 gap + staging not deployed |

### Pilot Score (กรอกหลัง pilot เสร็จ)

| Category | Score (0–100) | เหตุผล |
|---------|--------------|--------|
| Security | [PENDING] | role guard violations / CSRF issues / login failures |
| Functional readiness | [PENDING] | workflows pass/fail ratio |
| Performance | [PENDING] | staging p95, user-reported slowness |
| Data correctness | [PENDING] | enrollment/grade/transcript accuracy |
| Role coverage | [PENDING] | ครบ 5 roles + alumni verified |
| User feedback quality | [PENDING] | feedback count, reproducibility |
| Operational readiness | [PENDING] | backup / monitoring / support |
| Support readiness | [PENDING] | response time / escalation effectiveness |
| Pilot process quality | [PENDING] | daily monitoring completeness |
| **Overall** | **[PENDING]** | |

**เกณฑ์ Wave 2:**
- 90–100 = ✅ GO — พร้อมขยาย Wave 2
- 80–89 = 🟡 GO WITH CONDITIONS
- 70–79 = 🟠 แก้ High/Medium ก่อน
- < 70 = 🔴 NO-GO — หยุดและแก้ blockers ก่อน

---

## PART 10 — Wave 2 Go/No-Go Decision (กรอกหลัง pilot เสร็จ)

```
WAVE 2 DECISION
═══════════════════════════════════════════════════════════════
การตัดสินใจนี้ต้องทำหลัง pilot 5–7 วันเสร็จสิ้น

ข้อมูลที่ต้องมีก่อนตัดสิน:
[ ] Daily monitoring reports ครบ (Day 1–7)
[ ] Issue log reviewed (Critical/High resolved count)
[ ] Security observations clean
[ ] Staging k6 smoke PASS (รวม alumni)
[ ] User feedback summary complete
[ ] Scorecard filled

DECISION:
[ ] ✅ WAVE 2: GO
    เงื่อนไข: Overall ≥ 90, Critical=0, High unresolved=0
    หมายเหตุ: [PENDING]

[ ] 🟡 WAVE 2: GO WITH CONDITIONS
    เงื่อนไข: Overall 80–89, Critical=0, High unresolved ≤ 1 (มี workaround)
    Conditions: [PENDING]
    Must fix before expanding to Wave 2: [PENDING]

[ ] 🟠 WAVE 2: EXTEND WAVE 1 FIRST
    เงื่อนไข: Overall 70–79 หรือ High > 1 unresolved
    เหตุผล: [PENDING]
    Duration เพิ่ม: ___ วัน
    Fix required first: [PENDING]

[ ] 🔴 WAVE 2: NO-GO — STOP AND FIX
    เงื่อนไข: Overall < 70, Critical unresolved > 0, หรือ data integrity breach
    เหตุผล: [PENDING]
    Blockers: [PENDING]

ผลการตัดสิน: [PENDING]
เหตุผล: [PENDING]

Tech Lead:  _______________________  Date: _______
QA Lead:    _______________________  Date: _______
Pilot Lead: _______________________  Date: _______
```

---

## PART 11 — Must Fix Before Wave 2

### Pre-Pilot Known Must-Fix (ยืนยันแล้ว)

| # | ID | ปัญหา | Priority | เหตุผล |
|---|----|----|---------|--------|
| 1 | H1 | สร้าง `scripts/rehash_legacy_passwords.php` | High | Production users อาจมี MD5 passwords — Pilot ใช้ bcrypt seed ผ่านได้ แต่ก่อน production จำเป็น |
| 2 | M1 | เพิ่ม `logAudit()` ใน petition approve/deny (`registrar/dashboard.php`) | Medium | ถ้า Wave 2 ใช้ petition workflow จะขาด audit trail |
| 3 | M2 | Replace hardcoded `/dci-sis/` ด้วย `APP_BASE` ใน `alumni/dashboard.php:28–29` | Medium | ถ้า deploy path เปลี่ยน links พัง |
| 4 | M2b | Replace hardcoded `/dci-sis/` ด้วย `APP_BASE` ใน `registrar/dashboard.php:245–248` | Medium | เหมือน M2 |
| 5 | — | รัน k6 staging baseline + alumni ด้วย ALUMNI_USER env set | High | ยังไม่มี staging performance baseline / alumni slot unverified |

### Must-Fix จาก Pilot จริง (กรอกหลัง pilot)

```
Critical blockers ที่ต้องแก้ก่อน Wave 2:
1. [PENDING]
2. [PENDING]

High blockers ที่ต้องแก้ก่อน Wave 2:
1. [PENDING]
2. [PENDING]

Medium items ที่กระทบ Wave 2:
1. [PENDING]

QA gaps:
1. alumni k6 staging coverage (ยังไม่มี)
2. [PENDING]

Monitoring gaps:
1. [PENDING]
```

---

## PART 12 — Can Defer After Wave 2

| # | รายการ | เหตุผล |
|---|--------|--------|
| 1 | UI polish / layout improvements | Wave 1 users แจ้งเป็น Low — ไม่กระทบ functionality |
| 2 | Wording / label clarification | บางส่วน Medium แต่มี workaround |
| 3 | Admin dashboard redesign | In-scope Wave 2+ scope |
| 4 | Advanced reports / export | Not in Wave 1/2 scope |
| 5 | Redis / cache layer | Performance ยังอยู่ใน threshold |
| 6 | Repository / service refactor | ไม่กระทบ Wave 2 functional scope |
| 7 | Advanced monitoring (Prometheus/Grafana) | Phase 3 |
| 8 | Full production hardening (HTTPS/HSTS) | ก่อน production release เท่านั้น |
| 9 | k6 full-scale load (50+ VUs, 500 VUs) | ก่อน production — ไม่จำเป็นกับ Wave 2 scope |
| 10 | Restore drill on staging | ก่อน production — แนะนำทำก่อน Wave 2 ถ้าได้ |
| + | Items จาก pilot feedback ที่ marked Low | กรอกหลัง pilot |

---

## PART 13 — Recommended Next Commits

**Priority ที่ควรทำก่อน Wave 2:**

| ลำดับ | Commit | Priority | เมื่อไร |
|-------|--------|----------|---------|
| 1 | `fix(auth): create rehash_legacy_passwords.php` | High | ก่อน Wave 2 / ก่อน production |
| 2 | `fix(audit): add logAudit to petition approve/deny in registrar/dashboard.php` | Medium | ก่อน Wave 2 (ถ้า petition ใช้งาน) |
| 3 | `fix(routing): replace hardcoded /dci-sis/ with APP_BASE in alumni/dashboard.php` | Medium | ก่อน Wave 2 |
| 4 | `fix(routing): replace hardcoded /dci-sis/ with APP_BASE in registrar/dashboard.php` | Medium | ก่อน Wave 2 |
| 5 | `fix(critical/high): [จาก pilot issues]` | Critical/High | ทันที — ก่อน Wave 2 |
| 6 | `docs(pilot): add wave 1 daily monitoring day 1–7 results` | Docs | หลัง pilot เสร็จ |
| 7 | `docs(pilot): complete wave 1 review with actual pilot data` | Docs | หลัง pilot เสร็จ |
| 8 | `docs(pilot): wave 2 decision and plan` | Docs | หลัง Wave 2 decision |
| 9 | `test: run k6 staging baseline with ALUMNI_USER` | Test/Ops | ก่อน Wave 2 go-live |

**หมายเหตุ:** ลำดับ 1–4 มีข้อมูลพอแก้ได้แล้ว  
ลำดับ 5 ขึ้นกับผล pilot จริง — กรอก issue ID และ file ที่ต้องแก้หลัง pilot เสร็จ

---

## PART 14 — Key Files Reference

| File | บทบาทใน Wave 1 Review |
|------|----------------------|
| `docs/pilot-wave-1-plan.md` | Scope, criteria, stop triggers S1–S9 |
| `docs/pilot-wave-1-execution-runbook.md` | Entry gate, runbook steps, rollback |
| `docs/pilot-wave-1-daily-monitoring.md` | Template เก็บข้อมูลรายวัน (กรอกระหว่าง pilot) |
| `docs/pilot-wave-1-review.md` | เอกสารนี้ — กรอกหลัง pilot เสร็จ |
| `results/k6-baseline-local.txt` | Local MAMP baseline — ALL PASS |
| `results/load/` | ว่างเปล่า — ต้องเพิ่ม staging results |
| `docs/test-plan.md` | 148 functional test cases |
| `docs/load-test-plan.md` | k6 profiles + thresholds |
| `docs/backup-restore-plan.md` | Backup procedure |
| `docs/final-production-readiness-review.md` | Original scorecard (79/100) |
