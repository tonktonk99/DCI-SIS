# DCI-SIS Pilot Wave 1 — Daily Monitoring Template

**Phase:** 2K — Daily Monitoring  
**สร้าง:** 2026-06-29  
**ใช้สำหรับ:** ทุกวันทำการตลอด Pilot Wave 1 (5–7 วัน)  
**Environment:** Staging — NOT production  
**Pilot size:** 5–10 internal users  

> ดำเนินการโดย Support Owner ทุกเช้าก่อน 10:00 น.  
> สำหรับ scope และ rollback criteria ดู `docs/pilot-wave-1-plan.md`  
> สำหรับ runbook ดู `docs/pilot-wave-1-execution-runbook.md`

---

## คู่มือใช้งาน

เปิดไฟล์นี้ทุกเช้า กรอก template ด้านล่างจากบนลงล่างตามลำดับ  
บันทึกผล → ตัดสิน Go/No-Go → แจ้ง pilot users ถ้าจำเป็น → เซ็นชื่อ

**สีสัญลักษณ์:**  
- 🟢 **Green** — ทุกอย่างปกติ pilot ดำเนินต่อ  
- 🟡 **Yellow** — มีปัญหาแต่ควบคุมได้ ต้องติดตาม  
- 🔴 **Red** — Critical หรือ High หลายรายการ — พิจารณา Pause/Stop  

---

## SECTION A — Pilot Status Header

```
PILOT WAVE 1 — DAILY MONITORING
=========================================
วันที่:              _________________ (YYYY-MM-DD)
Pilot Day:           Day ___ of ___ (5–7 วันทำการ)
ตรวจสอบโดย:        _________________
เวลาเริ่มตรวจ:     _________________

PILOT USERS วันนี้
Users active วันนี้:         ___ / ___ คน (เป้าหมาย: 5–10)
Roles ที่ login วันนี้:
  [ ] admin        [ ] registrar     [ ] professor
  [ ] student      [ ] alumni

Modules ที่ใช้วันนี้:
  [ ] login/logout               [ ] admin dashboard
  [ ] admin users (view)         [ ] admin audit-logs
  [ ] registrar dashboard        [ ] registrar students (paginated)
  [ ] registrar sections         [ ] registrar transcripts
  [ ] registrar document-requests
  [ ] professor dashboard        [ ] professor gradebook (DCI101/001)
  [ ] professor exams (test section)
  [ ] student dashboard          [ ] student enrollment
  [ ] student transcript (view)  [ ] student grades (view)
  [ ] student requests (submit)
  [ ] alumni dashboard           [ ] alumni transcript_request
  [ ] alumni certificate_request

สถานะรวมวันนี้: [ ] 🟢 Green  [ ] 🟡 Yellow  [ ] 🔴 Red
```

---

## SECTION B — System Health

### B1. PHP Error Log

```
PHP ERROR LOG
─────────────────────────────────────────────────────────────────────────────
Command:
  tail -200 /var/log/php/error.log | grep -iE "fatal|uncaught|parse error" | \
  grep "$(date +%Y-%m-%d)"

ผล:
  จำนวน Fatal error ใหม่วันนี้:   ___
  รายละเอียด (ถ้ามี):
    ________________________________________________________________
    ________________________________________________________________

  สถานะ:
  [ ] ✅ OK — ไม่มี Fatal/Uncaught ใหม่
  [ ] ⚠️  Warning — มี Error แต่ไม่ใช่ Fatal (บันทึกรายละเอียด)
  [ ] 🔴 CRITICAL — มี Fatal/Uncaught → ดู Section F: Stop/Rollback
```

### B2. Web Server Access Log (HTTP 5xx)

```
HTTP 5XX ERRORS
─────────────────────────────────────────────────────────────────────────────
Command:
  grep " 5[0-9][0-9] " /var/log/nginx/access.log | \
  grep "$(date +%Y-%m-%d)" | wc -l

# หรือ Apache:
  grep " 5[0-9][0-9] " /var/log/apache2/access.log | \
  grep "$(date +%Y-%m-%d)" | wc -l

  จำนวน 5xx วันนี้:   ___

  ถ้ามี 5xx — ระบุ path ที่พบ:
    ________________________________________________________________

  สถานะ:
  [ ] ✅ OK — 0 errors
  [ ] ⚠️  Warning — 1–5 errors (บันทึก path)
  [ ] 🔴 STOP — > 5 errors บน core pages (login, dashboard, enrollment, gradebook)
```

### B3. MySQL Error Log

```
MYSQL ERROR LOG
─────────────────────────────────────────────────────────────────────────────
Command:
  tail -30 /var/log/mysql/error.log | grep -i "$(date +%Y-%m-%d)"

  พบ InnoDB error / connection refused:  [ ] ไม่มี  [ ] มี → รายละเอียด:
    ________________________________________________________________

  สถานะ:
  [ ] ✅ OK — ไม่มี error ใหม่
  [ ] 🔴 STOP — Connection refused / InnoDB crash → ดู Section F
```

### B4. MySQL Slow Query Log (ถ้า enabled)

```
SLOW QUERY LOG
─────────────────────────────────────────────────────────────────────────────
Command:
  mysqldumpslow -s t -t 5 /var/log/mysql/slow.log 2>/dev/null | head -30

  Enabled:  [ ] Yes  [ ] No (ข้ามหัวข้อนี้)

  Slow queries ใหม่ (> 1 วินาที):   ___
  Top slow query (ถ้ามี):
    ________________________________________________________________

  สถานะ:
  [ ] ✅ OK — ไม่มี / เหมือน baseline
  [ ] ⚠️  Yellow — มี slow query ใหม่ (บันทึก path และ query)
```

### B5. HTTP 403 / CSRF Block Count

```
HTTP 403 COUNT (incl. CSRF blocks)
─────────────────────────────────────────────────────────────────────────────
Command:
  grep " 403 " /var/log/nginx/access.log | grep "$(date +%Y-%m-%d)" | wc -l

  จำนวน 403 ทั้งหมดวันนี้:   ___

  Expected 403: หน้า admin ที่ role อื่นเข้า + sensitive paths (.sql, .env, config/)
  Unexpected 403 บน core pages: ___

  สถานะ:
  [ ] ✅ OK — 403 เป็น expected patterns
  [ ] ⚠️  Yellow — 403 บน core page ที่ไม่คาดคิด (บันทึก path)
```

### B6. Database Connection Health

```
DB CONNECTION HEALTH
─────────────────────────────────────────────────────────────────────────────
Command (spot check):
  /Applications/MAMP/Library/bin/mysql -u[user] -p[pass] -h127.0.0.1 \
    -P[port] dci_sis -e "SELECT 1 AS ping;"
  # หรือ:
  php -r "new PDO('mysql:host=127.0.0.1;port=[port];dbname=dci_sis','[user]','[pass]');"
  echo $?  # 0 = OK

  Connection test:  [ ] OK  [ ] FAILED → รายละเอียด: _________________

  สถานะ:
  [ ] ✅ OK
  [ ] 🔴 STOP — Cannot connect → ดู Section F
```

### B7. Disk Usage

```
DISK USAGE
─────────────────────────────────────────────────────────────────────────────
Command:
  df -h /Applications/MAMP/htdocs/dci-sis
  du -sh /Applications/MAMP/htdocs/dci-sis/backups/

  Disk ที่ใช้ / ทั้งหมด:   ___ / ___
  Backup dir size:          ___

  สถานะ:
  [ ] ✅ OK — < 80% used
  [ ] ⚠️  Yellow — 80–90% used (plan cleanup)
  [ ] 🔴 Red — > 90% used (disk full risk)
```

### B8. Login Failure Count

```
LOGIN FAILURE COUNT
─────────────────────────────────────────────────────────────────────────────
SQL:
  SELECT username_attempted, COUNT(*) AS fail_count, MAX(created_at) AS last_attempt
  FROM audit_logs
  WHERE action = 'AUTH.LOGIN_FAIL'
    AND created_at >= CURDATE()
  GROUP BY username_attempted
  ORDER BY fail_count DESC
  LIMIT 10;

  ผล:
    Username                   fail_count   last_attempt
    ______________________    ___          ________________
    ______________________    ___          ________________

  ยอมรับได้: < 5 ครั้งต่อ username ต่อวัน (pilot users ลืม password)
  น่าสงสัย (> 5 ครั้ง): [ ] ไม่มี  [ ] มี → username: _________________

  สถานะ:
  [ ] ✅ OK — ไม่มีที่น่าสงสัย
  [ ] ⚠️  Yellow — มีบางราย ต้องติดตาม
  [ ] 🔴 Red — Brute force pattern → freeze account + notify
```

---

## SECTION C — Security Signals

### C1. CSRF Block Audit

```
CSRF BLOCK CHECK
─────────────────────────────────────────────────────────────────────────────
SQL:
  SELECT action, COUNT(*) AS count, MAX(created_at) AS last_seen
  FROM audit_logs
  WHERE action LIKE '%CSRF%'
    AND created_at >= CURDATE()
  GROUP BY action;

  จำนวน CSRF block วันนี้:   ___

  Expected: 0 ในการใช้งานปกติ (ถ้า > 0 อาจเป็น attack หรือ form bug)
  ถ้ามี > 0 → ตรวจว่า path ใด:
    ________________________________________________________________

  สถานะ:
  [ ] ✅ OK — 0
  [ ] ⚠️  Yellow — มี 1–2 (ตรวจว่า false positive หรือ test)
  [ ] 🔴 CRITICAL — มีจำนวนมาก หรือ POST สำเร็จโดยไม่มี token → หยุดทันที
```

### C2. Role Access Violation (Forbidden)

```
ROLE ACCESS VIOLATION
─────────────────────────────────────────────────────────────────────────────
SQL:
  SELECT user_id, action, ip_address, created_at
  FROM audit_logs
  WHERE action IN ('AUTH.FORBIDDEN', 'AUTH.ROLE_MISMATCH')
    AND created_at >= CURDATE()
  ORDER BY created_at DESC
  LIMIT 20;

  จำนวน Forbidden วันนี้:   ___
  รายละเอียด (ถ้ามี):
    ________________________________________________________________

  Expected: บ้างในการทดสอบ (เช่น student พยายามเข้า admin)
  ผิดปกติ: user role ถูกต้องแต่ได้ 403 → อาจมี session bug

  สถานะ:
  [ ] ✅ OK — ไม่มี หรือ expected patterns
  [ ] ⚠️  Yellow — มีแต่ pattern ปกติ
  [ ] 🔴 CRITICAL — Role guard หลุด (user A เห็นข้อมูล user B) → หยุดทันที
```

### C3. Unauthorized Direct URL Access

```
DIRECT URL / BYPASS ATTEMPT
─────────────────────────────────────────────────────────────────────────────
SQL:
  SELECT ip_address, request_path, created_at
  FROM audit_logs
  WHERE action = 'AUTH.UNAUTHENTICATED'
    AND created_at >= CURDATE()
  ORDER BY created_at DESC
  LIMIT 20;

# หรือตรวจ access log:
  grep "GET /dci-sis/admin/" /var/log/nginx/access.log | \
  grep "$(date +%Y-%m-%d)" | grep -v " 302 \| 403 " | head -10

  พบ attempt ผิดปกติ:  [ ] ไม่มี  [ ] มี → รายละเอียด: _________________

  สถานะ:
  [ ] ✅ OK
  [ ] ⚠️  Yellow — มี attempt แต่ถูก block → บันทึก IP
  [ ] 🔴 CRITICAL — Bypass สำเร็จ → หยุดทันที
```

### C4. Data Anomaly Check (User Reports)

```
DATA ANOMALY
─────────────────────────────────────────────────────────────────────────────
มี user รายงานว่าเห็นข้อมูลของคนอื่น:  [ ] ไม่มี  [ ] มี
รายละเอียด:
  ________________________________________________________________

  สถานะ:
  [ ] ✅ OK — ไม่มี report
  [ ] 🔴 CRITICAL — มี report → หยุดทันที + ตรวจ query ownership
```

### C5. Sensitive Path Block Verification

```
SENSITIVE PATH BLOCK (ตรวจ 1 ครั้งต่อวัน)
─────────────────────────────────────────────────────────────────────────────
Commands:
  curl -s -o /dev/null -w "%{http_code}" https://[staging]/dci-sis/config/database.php
  → ควรได้ 403 — ผล: ___

  curl -s -o /dev/null -w "%{http_code}" https://[staging]/dci-sis/.env
  → ควรได้ 403 — ผล: ___

  curl -s -o /dev/null -w "%{http_code}" https://[staging]/dci-sis/includes/auth.php
  → ควรได้ 403 — ผล: ___

  สถานะ:
  [ ] ✅ OK — ทุก path return 403
  [ ] 🔴 CRITICAL — path ใด return 200 → หยุดทันที + fix .htaccess
```

### C6. Audit Log Volume Baseline

```
AUDIT LOG VOLUME
─────────────────────────────────────────────────────────────────────────────
SQL:
  SELECT COUNT(*) AS today_entries
  FROM audit_logs
  WHERE created_at >= CURDATE();

  จำนวน audit entries วันนี้:   ___
  Baseline สำหรับ 5–10 users: < 500 entries/วัน (login/logout + page actions)

  ถ้า > 500: ตรวจว่ามี loop/bot หรือ มี user มาก
  ถ้า = 0 หลังจาก users active: ตรวจว่า logAudit() ทำงานอยู่

  สถานะ:
  [ ] ✅ OK — ปริมาณสมเหตุสมผล
  [ ] ⚠️  Yellow — มากหรือน้อยผิดปกติ (ตรวจสาเหตุ)
```

---

## SECTION D — Core Workflow Health

### D1. Automated Smoke Spot Check

```
AUTOMATED SMOKE (ทำทุกวัน)
─────────────────────────────────────────────────────────────────────────────
Command:
  BASE_URL="https://[staging]/dci-sis" bash scripts/smoke_check.sh

  Exit code:   ___  (0 = pass)
  ผล:         [ ] ✅ PASS  [ ] 🔴 FAIL → รายละเอียด: _________________

  สถานะ:
  [ ] ✅ OK — exit 0
  [ ] 🔴 STOP — ถ้า exit ≠ 0 บน login.php / core paths
```

### D2. Manual Functional Spot Check

```
MANUAL FUNCTIONAL CHECK (ใช้ test account ทุกวัน)
─────────────────────────────────────────────────────────────────────────────
ADMIN
[ ] Login admin_test → lands on /admin/dashboard.php (ไม่ใช่ index mock)
[ ] KPI cards แสดงผล (ไม่มี error/blank)
[ ] Audit logs tab โหลด ≥ 1 row

REGISTRAR
[ ] Login registrar_test → registrar/dashboard.php
[ ] registrar/students.php → list โหลด ≤ 50 rows, pagination แสดง

PROFESSOR
[ ] Login prof_test → professor/dashboard.php

STUDENT
[ ] Login student_test → student/dashboard.php
[ ] student/enrollment.php → enrolled sections แสดง

ALUMNI
[ ] Login alumni_test → alumni/dashboard.php
[ ] alumni/transcript_request.php → form แสดง

LOGOUT
[ ] Logout → redirect to login.php ทุก role
[ ] หลัง logout ทดสอบ back button → redirect กลับ login.php (ไม่ได้เข้า page)

ผล spot check:
  [ ] ✅ ทั้งหมดผ่าน
  [ ] ⚠️  Yellow — บาง check fail (บันทึก)
  [ ] 🔴 Red — core workflow ล้มเหลว

รายละเอียด fail (ถ้ามี):
  ________________________________________________________________
  ________________________________________________________________
```

### D3. Workflow Health Matrix

```
WORKFLOW HEALTH MATRIX
─────────────────────────────────────────────────────────────────────────────
(กรอกตามที่ users รายงาน + spot check วันนี้)

Workflow                      สถานะ    หมายเหตุ
─────────────────────────────────────────────────
login/logout                  [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
language switch (TH/EN)       [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
session idle timeout          [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
admin: dashboard              [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
admin: audit-logs view/filter [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
registrar: students list/search [ ] ✅ [ ] ⚠️ [ ] 🔴 _______________
registrar: sections manage    [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
registrar: transcripts search [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
professor: gradebook (DCI101) [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
professor: exams save scores  [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
student: enrollment view      [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
student: enrollment add/drop  [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
student: transcript view      [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
student: document request     [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
alumni: transcript request    [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
alumni: certificate request   [ ] ✅ [ ] ⚠️ [ ] 🔴   _______________
```

---

## SECTION E — User Feedback

### E1. New Feedback Today

```
FEEDBACK RECEIVED TODAY
─────────────────────────────────────────────────────────────────────────────
จำนวน feedback ใหม่วันนี้:   ___
ช่องทาง: [ ] Feedback Form  [ ] Slack/LINE  [ ] Direct to Support

Feedback รายการ (คัดลอกจาก form หรือบันทึกสรุป):
─────────────────────────────────────────────────
Issue #___
  วันที่/เวลา:       ___________________
  ผู้รายงาน:         ___________________  Role: [ ]admin [ ]registrar [ ]professor [ ]student [ ]alumni
  หน้า/Module:       ___________________
  Action ที่ทำ:       ___________________
  Expected:          ___________________
  Actual:            ___________________
  Severity:          [ ] Critical  [ ] High  [ ] Medium  [ ] Low
  Reproducible:      [ ] ทุกครั้ง  [ ] บางครั้ง  [ ] ครั้งเดียว
  Screenshot:        [ ] มี (link: ________________)  [ ] ไม่มี
  Steps:             1. _____________ 2. _____________ 3. _____________
  Assigned to:       ___________________
  Status:            [ ] New  [ ] Investigating  [ ] Fix planned  [ ] Resolved
  หมายเหตุ:          ___________________

─────────────────────────────────────────────────
Issue #___
  (ซ้ำ template ด้านบนสำหรับ issue ถัดไป)

─────────────────────────────────────────────────
Issue #___
  (ซ้ำ template)
```

### E2. User Satisfaction Notes

```
USER SATISFACTION (บันทึกคำ quote หรือ sentiment)
─────────────────────────────────────────────────────────────────────────────
สิ่งที่ users ชอบวันนี้:
  ________________________________________________________________

สิ่งที่ users สับสนหรือไม่ชอบ:
  ________________________________________________________________

Feature requests (บันทึก ไม่ commit ทำ):
  ________________________________________________________________
```

---

## SECTION F — Issue Summary & Severity Tracking

### F1. Issues Found Today

```
ISSUES TODAY
─────────────────────────────────────────────────────────────────────────────
Issue #  Severity   Module/Page          Description                  Status
───────  ─────────  ───────────────────  ───────────────────────────  ──────────
___      Critical   ___________________  _______________________      ___________
___      High       ___________________  _______________________      ___________
___      Medium     ___________________  _______________________      ___________
___      Low        ___________________  _______________________      ___________

สรุปวันนี้:
  Critical ใหม่:  ___
  High ใหม่:      ___
  Medium ใหม่:    ___
  Low ใหม่:       ___
```

### F2. Cumulative Unresolved Issues

```
UNRESOLVED ISSUES TRACKER (สะสมทุกวัน)
─────────────────────────────────────────────────────────────────────────────
Issue #  Open Since  Severity   Description                  Owner        Due Date
───────  ──────────  ─────────  ───────────────────────────  ───────────  ────────
___      Day ___     Critical   ___________________________  ___________  ________
___      Day ___     High       ___________________________  ___________  ________
___      Day ___     Medium     ___________________________  ___________  ________
___      Day ___     Low        ___________________________  ___________  ________

สรุป unresolved:
  Critical unresolved:  ___  (target = 0 ก่อน Wave 2)
  High unresolved:      ___  (target ≤ 1 ก่อน Wave 2)
  Medium unresolved:    ___
  Low unresolved:       ___
```

### F3. Fixes Deployed Today

```
FIXES DEPLOYED TODAY
─────────────────────────────────────────────────────────────────────────────
(ถ้ามี hotfix ระหว่าง pilot)

Commit hash:      ___________________________________________
Description:      ___________________________________________
Tested by:        ___________________________________________
Smoke check run:  [ ] Yes — exit 0  [ ] No
Rollback plan:    ___________________________________________

[ ] ไม่มี fix deploy วันนี้
```

---

## SECTION G — Pilot Metrics

```
PILOT METRICS (กรอกทุกวัน)
─────────────────────────────────────────────────────────────────────────────
วันที่:   _______________   Pilot Day:   ___

Participation
  Active users วันนี้:                ___  / ___ total enrolled
  Total logins วันนี้:                ___
  Login failures วันนี้:              ___  (expected: < 5 ต่อ user)
  Sessions active ณ เวลาตรวจ:         ___

Workflows
  Workflows completed วันนี้:         ___
  Workflows ที่ fail หรือ incomplete:  ___

Issues
  Critical วันนี้:       ___
  High วันนี้:           ___
  Medium วันนี้:         ___
  Low วันนี้:            ___
  Resolved วันนี้:       ___
  Unresolved (cumulative Critical+High): ___

Performance (user-reported)
  หน้าที่ช้าที่สุดที่ user แจ้ง:  ___________________________
  ช้าประมาณ (วินาที):             ___  (threshold: < 3 วินาที)
  Timeout complaint:              [ ] ไม่มี  [ ] มี

Support
  Support messages / tickets วันนี้:  ___
  Incidents escalated:               ___
  Avg resolution time (issues วันนี้): ___

Audit log entries วันนี้:  ___  (baseline: < 500 สำหรับ 5–10 users)
```

---

## SECTION H — Daily Go/No-Go Decision

### H1. Issue Severity Rules (อ้างอิง)

| Severity | คำนิยาม | SLA | ตัวอย่าง |
|---------|---------|-----|--------|
| **Critical** | System down, data integrity หรือ security breach | **ทันที** | Role guard หลุด, data leak, login ทุก role ไม่ได้, CSRF bypass, wrong-user data |
| **High** | Core task ทำไม่ได้ แต่ระบบยังทำงาน | **ภายใน 4 ชม.** | Enrollment fail, gradebook save error, transcript ผิดคน, pagination พัง |
| **Medium** | ใช้งานได้แต่มี workaround, validation สับสน, หน้าช้าผิดปกติ | **1 วันทำการ** | Error message ไม่ชัด, layout shift, search เงื่อนไขไม่ตรง |
| **Low** | UI/wording/layout/typo | **ก่อน Wave 2** | ปุ่มไม่ align, ข้อความสะกดผิด |

### H2. Go/No-Go Decision Criteria

```
DAILY GO/NO-GO DECISION
─────────────────────────────────────────────────────────────────────────────

ประเมินสถานะทุกข้อก่อนตัดสิน:

   Critical unresolved:       ___  (ต้อง = 0 เพื่อ Continue)
   High unresolved:           ___  (ต้อง ≤ 2 เพื่อ Continue with Caution)
   Core workflow FAIL:        ___  (ต้อง = 0 เพื่อ Continue)
   PHP Fatal count วันนี้:    ___  (ต้อง = 0 เพื่อ Continue)
   DB connection error:       ___  (ต้อง = 0 เพื่อ Continue)
   5xx count วันนี้:          ___  (ต้อง ≤ 5 เพื่อ Continue)
   CSRF block ผิดปกติ:        ___  (ต้อง = 0)
   Backup OK วันนี้:          [ ] Yes  [ ] No

─────────────────────────────────────────────────────────────────────────────
ผลการตัดสิน:

[ ] ✅ CONTINUE PILOT (Green)
    เงื่อนไข:
    - Critical unresolved = 0
    - High unresolved ≤ 2 (มี workaround ชัดเจนและมี owner)
    - Core workflows ทั้งหมดผ่าน spot check
    - ไม่มี Fatal PHP error ซ้ำ
    - DB connection OK
    - Backup OK
    การแจ้ง users: ไม่จำเป็น — pilot ดำเนินต่อปกติ

[ ] 🟡 CONTINUE WITH CAUTION (Yellow)
    เงื่อนไข:
    - ไม่มี Critical
    - มี High 1–2 รายการ (มี owner + workaround + fix plan)
    - Core workflows ผ่าน แต่มีบาง UX หรือ Medium issue
    - ไม่กระทบ data integrity หรือ access control
    การแจ้ง users: แจ้ง pilot users ว่า "พบปัญหา X กำลังแก้ไข"

[ ] 🟠 PAUSE PILOT (Orange)
    เงื่อนไข:
    - มี High หลายรายการ (≥ 3) ที่ยังไม่มี workaround
    - Core workflow หลัก fail (enrollment, gradebook, หรือ login)
    - Performance รุนแรงผิดปกติ (> 30 วินาที < 10 users)
    - PHP Fatal ซ้ำ > 3 ครั้งต่อวัน
    - Backup ล้มเหลว
    การแจ้ง users: "ระบบ pause ชั่วคราว จะแจ้งเมื่อกลับมา"
    ก่อน resume: ต้องแก้ทุก High + รัน smoke + sign-off

[ ] 🔴 STOP AND ROLLBACK (Red)
    เงื่อนไข (S1–S9 จาก pilot-wave-1-plan.md):
    - S1: Login fail > 50% หรือทุก role เข้าไม่ได้
    - S2: Role guard หลุด — user A เห็นข้อมูล user B
    - S3: User เห็น transcript/grade ของคนอื่น
    - S4: Enrollment/grade write ผิด student_id
    - S5: HTTP 500 > 3 ครั้งต่อเนื่องบน core pages
    - S6: DB connection refused ต่อเนื่อง
    - S7: Backup/restore ล้มเหลวในกรณีฉุกเฉิน
    - S8: หน้าไม่โหลดใน 30 วินาที < 10 users
    - S9: CSRF bypass ยืนยัน — write สำเร็จไม่มี token
    การแจ้ง users: "ระบบหยุดชั่วคราว กำลังตรวจสอบ" (ทันที)
    → ดู Section I: Rollback Procedure

ผลการตัดสินวันนี้:  [ ] Continue  [ ] Caution  [ ] Pause  [ ] Stop+Rollback
เหตุผล:  ________________________________________________________________
ตัดสินโดย: _______________________  เวลา: _______
```

---

## SECTION I — Escalation Process

### I1. Critical Issue — ขั้นตอน

```
CRITICAL ISSUE ESCALATION
─────────────────────────────────────────────────────────────────────────────
เมื่อพบ Critical (S1–S9):

STEP 1  [ทันที] แจ้ง Release Owner / Support Owner ทันที
          ช่องทาง: _______________________________________________
          เวลาที่แจ้ง: _____________  แจ้งแล้ว: [ ] Yes

STEP 2  [ทันที] Freeze pilot — หยุด users ใหม่จาก login
          วิธี: แจ้ง pilot users ผ่าน Slack/LINE: "ระบบ pause ชั่วคราว"

STEP 3  [5 นาที] Capture evidence
          [ ] Copy PHP error log: tail -100 /var/log/php/error.log > /tmp/incident_[date].log
          [ ] Screenshot หน้าที่พัง
          [ ] บันทึก audit_log entries ณ เวลาเกิดเหตุ:
              SELECT * FROM audit_logs
              WHERE created_at >= '[incident_time]'
              ORDER BY created_at DESC LIMIT 50;

STEP 4  [10 นาที] ประเมิน rollback
          [ ] ปัญหาเกิดจาก recent commit? → git revert
          [ ] ปัญหาเกิดจาก data? → restore backup
          [ ] ปัญหาเกิดจาก config? → fix + smoke ก่อน resume

STEP 5  [ทันที ถ้าจำเป็น] Disable affected module
          วิธี: ประกาศให้ pilot users หยุดใช้ module นั้น
          (ห้ามแก้ code รอบนี้ เว้นแต่ critical fix เท่านั้น)

STEP 6  [ทันที] Create incident note
          วันที่/เวลาเกิด:   ___________________
          Trigger (S1–S9):   ___________________
          ผลกระทบ:           ___________________
          Status เวลา 1 ชม.: ___________________

STEP 7  ห้าม expand pilot จนกว่า Critical จะ resolved และผ่าน review
```

### I2. High Issue — ขั้นตอน

```
HIGH ISSUE HANDLING
─────────────────────────────────────────────────────────────────────────────
เมื่อพบ High:

STEP 1  [4 ชม.] Assign owner ทันที
          Owner: _______________________  เวลา assign: _______

STEP 2  [4 ชม.] ประกาศ workaround ให้ pilot users
          Workaround: _________________________________________________

STEP 3  [4 ชม.] Fix plan:
          File ที่ต้องแก้: _____________________________________________
          Commit plan:    _____________________________________________
          Target resolve: _____________________________________________

STEP 4  Monitor ทุกวันจนกว่าจะ resolved

STEP 5  ถ้า High ยังไม่ resolved ภายใน 2 วัน → upgrade เป็น Pause criteria
```

### I3. Rollback Procedure

```
ROLLBACK PROCEDURE (เมื่อตัดสิน Stop and Rollback)
─────────────────────────────────────────────────────────────────────────────
เวลาเริ่ม rollback:  _______________________

STEP 1  แจ้ง pilot users ทันที
          Message: "ระบบ DCI-SIS หยุดชั่วคราว กำลังดำเนินการแก้ไข จะแจ้งให้ทราบเมื่อกลับมา"

STEP 2  บันทึก incident details
          [ ] Trigger (S1–S9): _______________
          [ ] Commit ที่ deploy: _______________
          [ ] เวลาเกิด: _______________
          [ ] ผลกระทบ user: _______________

STEP 3  Code rollback (ถ้าจำเป็น)
          git log --oneline -5
          # ระบุ last known-good commit: _______________
          git revert HEAD --no-edit
          git push origin main
          # deploy to staging

STEP 4  Database restore (ถ้ามี data corruption)
          BACKUP_FILE=$(ls -t backups/*.sql.gz | head -1)
          gunzip -t "$BACKUP_FILE" && echo "Backup OK"
          RESTORE_CONFIRM=YES bash scripts/restore_database.sh "$BACKUP_FILE"

STEP 5  Verify after rollback
          php scripts/migrate.php status
          BASE_URL="https://[staging]/dci-sis" bash scripts/smoke_check.sh
          # ต้องผ่าน exit 0

STEP 6  Post-incident review (ก่อน resume)
          [ ] Root cause documented
          [ ] Fix verified in test
          [ ] Tech Lead + QA sign-off
          [ ] Pilot users briefed ก่อน resume

เวลา rollback เสร็จ:  _______________________
Resume approved by:     _______________________  Date: _______
```

---

## SECTION J — End-of-Day Checklist

```
END-OF-DAY CHECKLIST — Day ___ of ___
─────────────────────────────────────────────────────────────────────────────
ทำก่อนจบวันทุกวัน ไม่ควรข้ามขั้นตอน

LOGS
[ ] PHP error log ตรวจแล้ว → Fatal count วันนี้: ___
[ ] 5xx count วันนี้: ___
[ ] MySQL error log ตรวจแล้ว → OK: [ ] Yes  [ ] No
[ ] Slow query log ตรวจแล้ว (ถ้า enabled): ___
[ ] Audit log volume สมเหตุสมผล (< 500/วัน): ___

SECURITY
[ ] CSRF block ตรวจแล้ว → count: ___ (expected: 0)
[ ] Login failures ตรวจแล้ว → suspicious: [ ] ไม่มี  [ ] มี
[ ] Role violations ตรวจแล้ว → anomaly: [ ] ไม่มี  [ ] มี
[ ] Sensitive paths block ตรวจแล้ว → ทุก path 403: [ ] Yes  [ ] No

ISSUES
[ ] Issues วันนี้ categorized ทั้งหมด
[ ] Critical/High reviewed → owner assigned: [ ] Yes  [ ] N/A
[ ] Unresolved Critical: ___ (ต้อง = 0)
[ ] Unresolved High: ___ (ต้อง ≤ 2)
[ ] Issues ที่ต้อง fix ก่อนพรุ่งนี้: ___

BACKUP
[ ] Backup ทำงานปกติวันนี้: [ ] Yes  [ ] No (แก้ทันที)
[ ] Backup file ล่าสุด: ___________________________
[ ] gunzip test: [ ] OK  [ ] FAIL → แก้ทันที

PILOT DECISION
[ ] Go/No-Go recorded ใน Section H
[ ] Pilot users รับแจ้งถ้าจำเป็น: [ ] Yes  [ ] N/A
[ ] Tomorrow's plan noted: ___________________________

SIGN-OFF
Support Owner: _________________________  เวลา: _______
QA Lead (ถ้ามี): ______________________  เวลา: _______
```

---

## SECTION K — Daily Report Summary

```
DAILY PILOT REPORT — Day ___ of ___
═══════════════════════════════════════════════════════════════════════════════
วันที่:          _______________  เวลาสรุป: _______________
รายงานโดย:      _______________

EXECUTIVE SUMMARY (1–2 ประโยค)
  _____________________________________________________________
  _____________________________________________________________

METRICS SNAPSHOT
  Active users:           ___  / ___ total
  Total logins today:     ___
  Workflows completed:    ___
  Issues today:           Critical ___ / High ___ / Medium ___ / Low ___
  Issues resolved:        ___
  Unresolved Critical:    ___  (must = 0)
  Unresolved High:        ___
  PHP fatals:             ___
  5xx errors:             ___
  CSRF blocks:            ___
  Audit entries:          ___

CORE WORKFLOW SUMMARY
  [ ] ✅ All core workflows healthy
  [ ] ⚠️  Some issues (see Section D)
  [ ] 🔴 Core workflow failure (see details)

TOP ISSUES TODAY
  1. [___] ___________________________________________________
  2. [___] ___________________________________________________
  3. [___] ___________________________________________________

FIXES DEPLOYED TODAY
  [ ] ไม่มี
  [ ] มี: ____________________________________________________

ROLLBACK REQUIRED
  [ ] No
  [ ] Yes — ดู Section I

DECISION FOR TOMORROW
  [ ] ✅ Continue Pilot
  [ ] 🟡 Continue with Caution — ติดตาม: ___________________
  [ ] 🟠 Pause — เหตุผล: ___________________________________
  [ ] 🔴 Stop + Rollback — ดู Section I

SIGN-OFF
  Support Owner:  _______________________  Date: _______  Time: _______
  Tech Lead:      _______________________  Date: _______  Time: _______
═══════════════════════════════════════════════════════════════════════════════
```

---

## SECTION L — SQL Reference Queries

Quick reference สำหรับ Support Owner:

```sql
-- 1. Login failures today
SELECT username_attempted, COUNT(*) AS fails, MAX(created_at) AS last
FROM audit_logs
WHERE action = 'AUTH.LOGIN_FAIL'
  AND created_at >= CURDATE()
GROUP BY username_attempted
ORDER BY fails DESC;

-- 2. CSRF blocks today
SELECT action, COUNT(*) AS count
FROM audit_logs
WHERE action LIKE '%CSRF%'
  AND created_at >= CURDATE()
GROUP BY action;

-- 3. Role violations today
SELECT user_id, action, ip_address, created_at
FROM audit_logs
WHERE action IN ('AUTH.FORBIDDEN', 'AUTH.ROLE_MISMATCH', 'AUTH.UNAUTHENTICATED')
  AND created_at >= CURDATE()
ORDER BY created_at DESC;

-- 4. Audit log volume today
SELECT COUNT(*) AS today_entries
FROM audit_logs
WHERE created_at >= CURDATE();

-- 5. Hourly activity breakdown
SELECT HOUR(created_at) AS hour, COUNT(*) AS events
FROM audit_logs
WHERE created_at >= CURDATE()
GROUP BY HOUR(created_at)
ORDER BY hour;

-- 6. Top actions today
SELECT action, COUNT(*) AS count
FROM audit_logs
WHERE created_at >= CURDATE()
GROUP BY action
ORDER BY count DESC
LIMIT 20;

-- 7. Active users today (unique)
SELECT COUNT(DISTINCT user_id) AS unique_users
FROM audit_logs
WHERE created_at >= CURDATE()
  AND user_id IS NOT NULL;

-- 8. Recent errors in audit log
SELECT user_id, action, created_at
FROM audit_logs
WHERE action LIKE '%ERROR%'
  AND created_at >= CURDATE()
ORDER BY created_at DESC
LIMIT 10;
```

---

## SECTION M — Key Files & Contacts Reference

```
QUICK REFERENCE CARD
─────────────────────────────────────────────────────────────────────────────
STAGING URL:          https://[staging]/dci-sis/login.php
FEEDBACK FORM:        [URL — กรอกก่อน go-live]
SUPPORT OWNER:        [ชื่อ / LINE / Slack]
TECH LEAD (escalate): [ชื่อ / contact]
ROLLBACK OWNER:       [ชื่อ / contact]

KEY DOCS
  Pilot scope:         docs/pilot-wave-1-plan.md
  Execution runbook:   docs/pilot-wave-1-execution-runbook.md
  This template:       docs/pilot-wave-1-daily-monitoring.md
  Backup/restore:      docs/backup-restore-plan.md
  Test plan (148 TCs): docs/test-plan.md
  Load test plan:      docs/load-test-plan.md

KEY SCRIPTS
  Smoke check:         BASE_URL=... bash scripts/smoke_check.sh
  Backup:              DB_NAME=... DB_USER=... DB_PASS=... bash scripts/backup_database.sh
  Restore:             RESTORE_CONFIRM=YES bash scripts/restore_database.sh [file]
  Migration status:    php scripts/migrate.php status
  Seed dry-run:        APP_ENV=staging php scripts/seed_staging.php --dry-run

STOP CRITERIA TRIGGERS (S1–S9 from pilot-wave-1-plan.md)
  S1 Login fail > 50%          S2 Role guard breach
  S3 User sees others' data    S4 Wrong-user write
  S5 5xx > 3× core             S6 DB connection refused
  S7 Backup fails emergency     S8 Page timeout < 10 users
  S9 CSRF bypass confirmed

k6 BASELINE (local reference — results/k6-baseline-local.txt)
  checks 100% | dci_login_success 100% | http_req_failed 0%
  p95 auth 310ms | p95 heavy 16ms (MAMP local — staging will differ)
```

---

## Log of Daily Decisions

_กรอกทุกวัน — ใช้เป็น audit trail ของ pilot_

| Day | วันที่ | Active Users | Issues C/H/M/L | Decision | Signed By |
|-----|--------|-------------|----------------|----------|-----------|
| 1   |        |             | / / /          |          |           |
| 2   |        |             | / / /          |          |           |
| 3   |        |             | / / /          |          |           |
| 4   |        |             | / / /          |          |           |
| 5   |        |             | / / /          |          |           |
| 6   |        |             | / / /          |          |           |
| 7   |        |             | / / /          |          |           |

**End-of-Pilot Go/No-Go:** ดู `docs/pilot-wave-1-execution-runbook.md` Part 7
