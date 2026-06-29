# DCI-SIS Pilot Wave 1 — Execution Plan

**Phase:** 2F — Pilot Wave 1  
**Status:** GO — Pilot approved based on C1–C6 + P1 fixes + k6 staging smoke pass  
**Created:** 2026-06-29  
**Scope:** Internal users only, 5–10 people, controlled environment, rollback-ready  
**Environment:** Staging server — NOT production

> **ห้าม deploy production ก่อนผ่าน Wave 1 review**  
> For staging deploy steps see `docs/staging-deployment-checklist.md`  
> For backup/restore see `docs/backup-restore-plan.md`  
> For test coverage see `docs/test-plan.md`

---

## 1. Purpose

Pilot Wave 1 มีวัตถุประสงค์เพื่อ:

1. ทดสอบ system ในสภาพแวดล้อม staging จริง กับ internal users จริง
2. ยืนยัน functional correctness ของ core flows ทั้ง 5 roles ในสภาพการใช้งานจริง
3. ค้นหา usability issues และ workflow gaps ที่ไม่ปรากฏใน automated test
4. วัด user experience และ feedback ก่อนเปิด Wave 2 หรือ production
5. ยืนยันว่า security, session, CSRF, role access ทำงานถูกต้องในสภาพ multi-user จริง
6. สร้าง confidence ให้ทีม operations ในการ support production

**ไม่ใช่วัตถุประสงค์ของ Wave 1:**
- Load/stress test (ใช้ k6 แยก)
- Feature testing ของ module ที่ยังไม่ ready
- Production data migration
- การเปิดระบบให้ student/alumni ทั่วไป

---

## 2. Pilot Decision — Evidence Summary

| หลักฐาน | สถานะ | รายละเอียด |
|--------|--------|-----------|
| C1–C5 CSRF blockers | ✅ FIXED | commits `a0f0144` → `a9a4a36` |
| C6/P1 pagination | ✅ FIXED | commit `645dd18` |
| k6 local smoke | ✅ PASS | checks 100%, login_success 100%, failed 0% |
| k6 local baseline (20 VUs, 10 min) | ✅ PASS | p95 auth 310ms, p95 heavy 16ms (MAMP) |
| k6 staging smoke | ✅ PASS | admin_dashboard confirmed in k6 checks |
| All 25 endpoints exist | ✅ | grep confirmed Phase 2D |
| Security headers | ✅ | X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| Sensitive files blocked | ✅ | .htaccess blocks config/, includes/, .sql, .env, .sh |
| Backup scripts ready | ✅ | `scripts/backup_database.sh`, `scripts/restore_database.sh` |

**Pilot Decision: ✅ GO**

---

## 3. Pilot Scope

### 3A. Environment

| รายการ | ค่า |
|--------|-----|
| Environment | Staging server (`APP_ENV=staging`) |
| Access | Internal network หรือ VPN เท่านั้น |
| URL | `https://staging.[domain]/dci-sis/` (ห้ามใช้ production URL) |
| Duration | Wave 1: **5–7 วันทำการ** นับจาก go-live date |
| Users | **5–10 internal users** (ห้ามเปิดทั่วไป) |
| Data | Seeded test data เท่านั้น — ห้ามนำ production data เข้า staging |

### 3B. Pilot Users

| Role | จำนวน | หมายเหตุ |
|------|--------|---------|
| admin | 1 คน | Senior staff หรือ IT lead |
| registrar | 1–2 คน | Academic registrar office |
| professor | 1–2 คน | ทดสอบกับ test section DCI101/001 |
| student | 2–4 คน | Internal staff ที่รับ student role ทดสอบ |
| alumni | 1 คน | ถ้ามี account พร้อม (alumni_test or approved internal) |
| **รวม** | **5–10 คน** | ทุกคนต้องลงนาม pilot agreement ก่อน |

> Pilot users ต้องได้รับ briefing เรื่อง:  
> (1) นี่คือ staging environment ไม่ใช่ production  
> (2) ข้อมูลที่ใส่จะถูก reset หลัง pilot  
> (3) ช่องทาง feedback และ incident report

### 3C. Modules ที่เปิดให้ทดสอบ

| Module | Role | หมายเหตุ |
|--------|------|---------|
| Login / Logout | ทุก role | รวม language switch TH/EN |
| Session expiry / idle timeout | ทุก role | ทดสอบ idle 2 ชั่วโมง |
| **Admin: dashboard** | admin | KPI, audit log feed |
| **Admin: users** | admin | View list — ห้าม mass delete |
| **Admin: audit logs** | admin | View + filter — core monitoring tool |
| Admin: roles | admin | View only |
| **Registrar: dashboard** | registrar | KPI + pending petitions |
| **Registrar: students** | registrar | List (paginated), search, add, toggle status |
| **Registrar: sections** | registrar | View + manage sections |
| **Registrar: transcripts** | registrar | Search + view — ห้าม issue certificate จริง |
| Registrar: document requests | registrar | View queue only |
| Registrar: courses | registrar | View |
| Registrar: semesters | registrar | View |
| **Professor: dashboard** | professor | |
| **Professor: gradebook** | professor | Sample section DCI101/001 เท่านั้น |
| Professor: courses | professor | View |
| Professor: exams | professor | View + score save บน test section เท่านั้น |
| Professor: students | professor | View |
| **Student: dashboard** | student | GPA, schedule widget |
| **Student: enrollment** | student | View enrolled sections; ทดสอบ add/drop บน test section |
| **Student: transcript** | student | View — ห้าม print/export จริง |
| **Student: grades** | student | View |
| Student: courses | student | View |
| Student: schedule | student | View |
| **Student: requests** | student | Submit document request (transcript type) |
| **Alumni: dashboard** | alumni | View + links |
| **Alumni: transcript request** | alumni | Submit request form |
| **Alumni: certificate request** | alumni | Submit request form |

### 3D. Modules ที่ **จำกัด** หรือ **ยังไม่เปิด** ใน Wave 1

| Module | สถานะ | เหตุผล |
|--------|--------|--------|
| `admin/settings.php` | ❌ ปิด | มี write forms ที่ยังไม่ audit |
| Production transcript issue (official) | ❌ ปิด | ห้ามออกเอกสารราชการจาก staging |
| Production certificate generation | ❌ ปิด | เหมือนกัน |
| Mass import / bulk operations | ❌ ปิด | ยังไม่ implement |
| Destructive delete (user/student) | ❌ ปิด | ห้ามลบข้อมูลใน pilot |
| Petition approve/deny (registrar/dashboard) | ⚠️ จำกัด | ทำได้แต่ไม่มี audit log — แจ้ง registrar ล่วงหน้า |
| Print endpoints (transcript_print, certificate_print) | ⚠️ จำกัด | ทดสอบได้แต่ไม่ใช้ output จริง |
| Real payment / finance operations | ❌ ปิด | ไม่มีใน scope |
| Bulk user creation/deletion | ❌ ปิด | ยังไม่ implement |

---

## 4. Pilot Entry Criteria

ต้องผ่านทุกข้อก่อน go-live ของ Wave 1:

```
PILOT ENTRY GATE — Date: _______ Approved by: _______

CODE & DEPLOY
[ ] Latest commit deployed to staging (hash: _______________)
[ ] task29_duplicate_protection_FULL.sql — ไม่อยู่ใน git หรือ blocked โดย .htaccess
[ ] task33_final_flow_test_checklist.md — ไม่ accessible ทาง HTTP
[ ] git ls-files | grep -E "task29|task33" → empty

DATABASE
[ ] php scripts/migrate.php status → Pending=0, Modified=0
[ ] php scripts/migrate.php checksum → all OK

SEED / ACCOUNTS
[ ] SEED_CONFIRM=YES php scripts/seed_staging.php --apply → idempotent pass
[ ] Pilot user accounts created and password communicated securely:
    [ ] admin_test (หรือ internal admin account)
    [ ] registrar_test (หรือ internal registrar account)
    [ ] prof_test
    [ ] student_test × 2–4
    [ ] alumni_test (ถ้ามี)

BACKUP
[ ] bash scripts/backup_database.sh → completed
[ ] BACKUP_FILE=$(ls -t backups/*.sql.gz | head -1) recorded: ________________
[ ] gunzip -t "$BACKUP_FILE" && echo OK → verified

AUTOMATED SMOKE
[ ] BASE_URL=https://[staging]/dci-sis bash scripts/smoke_check.sh → exit 0
[ ] All checks: PASS
[ ] Sensitive paths blocked (config/, .env, .sql) → HTTP 403

k6 STAGING SMOKE (ครบ 5 roles)
[ ] K6_PROFILE=smoke k6 run tests/load/dci_sis_smoke_load.js (staging URL) → PASS
[ ] checks = 100%
[ ] dci_login_success = 100%
[ ] http_req_failed = 0%
[ ] alumni_dashboard ✓ ปรากฏใน check output

MANUAL ROLE VERIFICATION (browser)
[ ] admin_test → login → lands on admin/dashboard.php (ไม่ใช่ mock index)
[ ] registrar_test → login → registrar/dashboard.php
[ ] prof_test → login → professor/dashboard.php
[ ] student_test → login → student/dashboard.php
[ ] alumni_test → login → alumni/dashboard.php

CSRF RUNTIME GATE
[ ] curl POST student/requests.php (no _csrf) → HTTP 403
[ ] curl POST professor/exams.php (no _csrf) → HTTP 403
[ ] curl POST alumni/transcript_request.php (no _csrf) → HTTP 403
[ ] curl POST alumni/certificate_request.php (no _csrf) → HTTP 403
[ ] curl POST registrar/dashboard.php (no _csrf) → HTTP 403

LOGS
[ ] PHP error log → ไม่มี Fatal error / Uncaught exception
[ ] MySQL error log → ไม่มี connection error
[ ] Nginx/Apache log → ไม่มี 500 ในช่วง smoke

OPERATIONS
[ ] Support owner ระบุชื่อ: _______________________________
[ ] Incident Slack channel / email group: ___________________
[ ] Feedback form URL ready: _______________________________
[ ] Rollback plan ได้รับ sign-off จาก tech lead: ___________
[ ] Pilot users ได้รับ briefing แล้ว: ____________________
[ ] Pilot agreement / NDA (ถ้าจำเป็น) ลงนามแล้ว
```

> **Gate sign-off :**  
> Tech Lead: _________________ Date: _________  
> QA Lead:   _________________ Date: _________  
> Ops/DBA:   _________________ Date: _________

---

## 5. Daily Monitoring Checklist

ให้ Support Owner ตรวจทุกเช้าก่อน 10:00 น. ตลอด Wave 1:

```
DAILY PILOT MONITOR — Date: _______  Checked by: _______

ERROR LOGS (ตรวจก่อนเปิด pilot วันใหม่)
[ ] tail -50 /var/log/php/error.log | grep -iE "fatal|error|exception"
    → จำนวน fatal ใหม่ตั้งแต่เมื่อวาน: ___
    → รายละเอียด (ถ้ามี): _________________________________

[ ] grep " 5[0-9][0-9] " /var/log/nginx/access.log | grep "$(date +%Y-%m-%d)" | wc -l
    → จำนวน 5xx วันนี้: ___ (stop criteria: > 5 ใน core pages)

[ ] tail -20 /var/log/mysql/error.log
    → ไม่มี InnoDB error / connection refused: [ ] OK  [ ] WARNING

SECURITY SIGNALS
[ ] ตรวจ audit_logs สำหรับ AUTH.LOGIN_FAIL ผิดปกติ:
    SELECT username_attempted, COUNT(*) AS fails, MAX(created_at)
    FROM audit_logs
    WHERE action = 'AUTH.LOGIN_FAIL'
      AND created_at >= CURDATE()
    GROUP BY username_attempted
    ORDER BY fails DESC;
    → จำนวน fails ผิดปกติ (> 10 ครั้ง ต่อ user ต่อวัน): ___

[ ] ตรวจ CSRF block ผิดปกติ:
    SELECT COUNT(*) FROM audit_logs
    WHERE action LIKE 'CSRF%'
      AND created_at >= CURDATE();
    → ถ้า > 0: ตรวจว่าเป็น false positive หรือ attack attempt

PERFORMANCE
[ ] ตรวจ MySQL slow query log (ถ้า enabled):
    mysqldumpslow -s t -t 5 /var/log/mysql/slow.log
    → slow queries ใหม่: ___

[ ] ตรวจ PHP-FPM workers ถ้า applicable:
    → Workers saturated? [ ] No  [ ] Yes → escalate

AUDIT LOG GROWTH
[ ] SELECT COUNT(*) AS today_logs FROM audit_logs WHERE created_at >= CURDATE();
    → จำนวน audit entries วันนี้: ___ (baseline สำหรับ 5–10 users: < 500/วัน)

FUNCTIONAL SPOT CHECK (ทำทุกวัน, ใช้ test account)
[ ] Login admin_test → admin/dashboard.php → KPI cards โหลด
[ ] Login student_test → student/enrollment.php → enrolled sections แสดง
[ ] Login registrar_test → registrar/students.php → student list โหลด ≤ 50 rows
[ ] Logout → redirect to login.php

FEEDBACK REVIEW
[ ] อ่าน feedback ใหม่จาก feedback form ตั้งแต่เมื่อวาน
[ ] Issue ใหม่ที่รับแจ้ง: ___
[ ] Issue ที่ Severity Critical/High: ___
[ ] Issue ที่ต้องการ immediate fix: ___
```

---

## 6. Stop / Rollback Criteria

### 6A. หยุด Pilot Wave 1 ทันที (Critical)

หยุดและ notify support owner ทันที ถ้าพบ:

| # | เหตุการณ์ | Action ทันที |
|---|---------|------------|
| S1 | Login ไม่ได้ทุก role หรือ > 50% login ล้มเหลว | หยุด pilot, ตรวจ auth.php + session config |
| S2 | User role A เห็นข้อมูลของ role B (role guard หลุด) | หยุดทันที, revoke sessions, escalate security |
| S3 | User คนหนึ่งเห็น grade / transcript ของนักศึกษาคนอื่น | หยุดทันที — data breach |
| S4 | Enrollment หรือ grade write ข้อมูลผิดคน (wrong student_id) | หยุด, ตรวจ record ownership |
| S5 | HTTP 500 บน login.php, dashboard, enrollment, gradebook มากกว่า 3 ครั้งต่อเนื่อง | หยุด, ตรวจ PHP error log |
| S6 | Database error ต่อเนื่อง (PDOException, connection refused) | หยุด, ตรวจ MySQL + DB credentials |
| S7 | Backup/restore script ล้มเหลว เมื่อต้องการใช้ในกรณีฉุกเฉิน | escalate ก่อน rollback |
| S8 | Performance degraded รุนแรง — หน้าไม่โหลดภายใน 30 วินาที สำหรับ < 10 users | ตรวจ server resources + MySQL |
| S9 | CSRF bypass — write action สำเร็จโดยไม่มี valid token | หยุดทันที — security incident |

### 6B. หยุด Pilot (High) — ภายใน 4 ชั่วโมง

| # | เหตุการณ์ | Action |
|---|---------|--------|
| H1 | Student/alumni ไม่สามารถ submit request ได้ซ้ำๆ | ตรวจ CSRF, form validation |
| H2 | Registrar เห็น student list ผิดหรือ pagination พัง | ตรวจ P1 fix, query log |
| H3 | Professor ไม่สามารถ save exam scores ได้ | ตรวจ exams.php, verify_csrf |
| H4 | Student transcript แสดงข้อมูลผิดหรือ GPA ผิด | ตรวจ query ownership |
| H5 | Audit log ไม่บันทึก events บาง action | ตรวจ logAudit call |

### 6C. Rollback Procedure

```bash
# Step 1: หยุด pilot users (แจ้ง support owner ก่อน)
# Step 2: Record รายละเอียด incident + timestamp

# Step 3: Rollback code (ถ้าปัญหาเกิดจาก recent commit)
git log --oneline -5                          # identify last stable commit
git revert HEAD --no-edit                     # revert สุดท้าย (หรือ specific commit)
git push origin main

# Step 4: Restore database (ถ้าปัญหาเกิดจาก data corruption)
RESTORE_CONFIRM=YES bash scripts/restore_database.sh backups/[backup-file].sql.gz

# Step 5: Verify restore
php scripts/migrate.php status               # must show no pending
BASE_URL=https://[staging]/dci-sis bash scripts/smoke_check.sh

# Step 6: Post-incident review ก่อน resume pilot
```

---

## 7. Feedback and Issue Reporting

### 7A. Feedback Channel

| ช่องทาง | ใช้สำหรับ |
|--------|---------|
| Feedback Form (Google Form / shared doc) | Bug reports, UX issues, feature requests |
| Slack/LINE channel `#dci-sis-pilot` | Real-time support, quick questions |
| Direct to Support Owner | Critical/High incidents |
| Daily standup (5 นาที) | Summary ประจำวัน |

### 7B. Feedback Template

Pilot users กรอกข้อมูลต่อไปนี้เมื่อพบปัญหา:

```
DCI-SIS Pilot Wave 1 — Feedback / Bug Report

วันที่และเวลา: ________________________________
ผู้รายงาน (ชื่อ): ________________________________
Role ที่ใช้งาน:  [ ] admin  [ ] registrar  [ ] professor  [ ] student  [ ] alumni

MODULE / PAGE
หน้าที่เกิดปัญหา (URL หรือชื่อหน้า): ________________
Action ที่ทำ: ____________________________________

ผลที่ได้รับ
สิ่งที่คาดว่าจะเกิดขึ้น: ___________________________
สิ่งที่เกิดขึ้นจริง: _________________________________

ขั้นตอน (Reproducible Steps):
1. ________________________________
2. ________________________________
3. ________________________________

ความรุนแรง:
[ ] Critical — ระบบใช้งานไม่ได้ / data ผิด / security
[ ] High     — task หลักทำไม่ได้
[ ] Medium   — ใช้งานได้แต่มีปัญหา
[ ] Low      — UI/wording/layout

แนบ Screenshot / Video: [ ] มี  [ ] ไม่มี
Browser / Device: ________________________________
สามารถ reproduce ได้: [ ] ทุกครั้ง  [ ] บางครั้ง  [ ] ครั้งเดียว
หมายเหตุเพิ่มเติม: _________________________________
```

### 7C. Issue Severity Definition

| Severity | คำนิยาม | SLA Response | ตัวอย่าง |
|---------|---------|-------------|---------|
| **Critical** | System down หรือ data integrity/security breach | ทันที — หยุด pilot | Role guard หลุด, data leak, login ทุก role ไม่ได้, CSRF bypass |
| **High** | Core task ทำไม่ได้ หรือ wrong data แต่ระบบยังทำงาน | ภายใน 4 ชั่วโมง | Enrollment fail, gradebook save error, transcript ผิดคน |
| **Medium** | ทำงานได้แต่มีปัญหา หรือ validation สับสน | ภายใน 1 วันทำการ | หน้าช้า, ข้อความ error ไม่ชัด, pagination ไม่ตรง |
| **Low** | UI/wording/layout issues | ก่อน Wave 2 | ปุ่มไม่ align, ข้อความสะกดผิด, label ไม่ชัด |

---

## 8. End-of-Pilot Wave 1 Review

หลังครบ 5–7 วัน ให้ Support Owner นัด review meeting และกรอก:

```
END-OF-PILOT WAVE 1 REVIEW

Date: _______  Attendees: _______________________

PARTICIPATION
จำนวน pilot users จริง: ___
Roles ที่เข้าร่วม: ___
วันที่เริ่ม / สิ้นสุด: ___ / ___
Sessions (logins) รวม: ___

ISSUES SUMMARY
Critical: ___  (resolved: ___ / unresolved: ___)
High:     ___  (resolved: ___ / unresolved: ___)
Medium:   ___  (resolved: ___ / unresolved: ___)
Low:      ___

MODULES TESTED
[ ] Admin dashboard / users / audit-logs
[ ] Registrar dashboard / students / transcripts / sections
[ ] Professor gradebook / exams
[ ] Student enrollment / transcript / requests
[ ] Alumni transcript request / certificate request
Module ที่ยังไม่ได้ทดสอบ: ___

PERFORMANCE (บน staging จริง)
หน้าที่ช้าที่สุด (user-reported): ___
p95 ที่วัดได้บน staging: ___
มี slow query ใหม่ไหม: ___

SUPPORT WORKLOAD
จำนวน incidents ที่ support รับ: ___
เวลาเฉลี่ย resolution: ___

USER FEEDBACK (สรุป)
สิ่งที่ users ชอบ: ________________________________
สิ่งที่ users ต้องการเพิ่ม: _________________________
Workflow ที่สับสน: ________________________________

GO / NO-GO สำหรับ Pilot Wave 2
[ ] GO          — ไม่มี Critical/High unresolved
[ ] GO WITH CONDITIONS — มี High แต่ workaround ได้
[ ] NO-GO       — มี Critical unresolved หรือ High > 3 unresolved

MUST-FIX BEFORE WAVE 2
1. ________________________________
2. ________________________________
3. ________________________________

CAN DEFER TO WAVE 2 / POST-WAVE 2
1. ________________________________
2. ________________________________

SIGN-OFF
Tech Lead:  _________________ Date: _____
QA Lead:    _________________ Date: _____
Ops/DBA:    _________________ Date: _____
```

---

## 9. Must Fix Before Pilot (code items ที่ยังค้างอยู่)

รายการต่อไปนี้ยังไม่ได้แก้ — ควร commit ก่อนหรือภายใน Wave 1:

| # | ID | ปัญหา | Priority | Commit Plan |
|---|----|----|---------|-------------|
| 1 | H3 | `task33_final_flow_test_checklist.md` — accessible via HTTP (`.md` ไม่ถูก block โดย `.htaccess`) | **Before pilot** | `chore(repo): remove dev artifacts from git tracking` |
| 2 | H2 | `task29_duplicate_protection_FULL.sql` — tracked ใน git (blocked by .htaccess แต่ยัง tidy) | **Before pilot** | รวมกับ H3 ใน 1 commit |
| 3 | H4 | `index.php` mock placeholder — pilot users ที่ login จะเห็น "Dashboard (Mock)" | **Before pilot** | `fix(routing): replace index.php mock with role-based redirect` |

---

## 10. Can Defer After Wave 1

| ID | ปัญหา | เหตุผลที่เลื่อนได้ |
|----|----|--------------------|
| H1 | MD5 fallback — auto-rehash on login ทำงานอยู่แล้ว; `scripts/rehash_legacy_passwords.php` ไม่มี | Pilot ใช้ seed accounts (bcrypt) — ไม่กระทบ |
| M1 | `registrar/dashboard.php` ไม่มี logAudit สำหรับ petition | Petition workflow ยังไม่ activate ใน Wave 1 |
| M2 | `alumni/dashboard.php` hardcoded `/dci-sis/` | APP_BASE ไม่เปลี่ยน ใน staging |
| M2-b | `registrar/dashboard.php` quick action hardcoded | เหมือน M2 |
| — | k6 staging baseline (20 VUs) | หลัง staging deploy stable |
| — | k6 staging load (50 VUs) | ก่อน production |
| — | Restore drill | ก่อน production |
| — | HTTPS / HSTS | ก่อน production |
| — | Monitoring (Prometheus/Grafana) | Phase 3 |

---

## 11. Key Files Reference

| File | Purpose |
|------|---------|
| `docs/staging-deployment-checklist.md` | Deploy staging before pilot |
| `docs/staging-execution-plan.md` | Blocker triage + pilot go/no-go criteria |
| `docs/final-production-readiness-review.md` | Original scorecard + all blockers |
| `docs/backup-restore-plan.md` | Backup procedure + restore drill |
| `docs/test-plan.md` | 148 functional test cases |
| `docs/load-test-plan.md` | k6 profiles + monitoring |
| `docs/staging-seed-data.md` | Test accounts setup |
| `docs/production-smoke-checklist.md` | Pre/post-deploy smoke checks |
| `scripts/backup_database.sh` | Run before pilot go-live |
| `scripts/smoke_check.sh` | Run after every deploy |
| `tests/load/dci_sis_smoke_load.js` | k6 smoke/baseline |
