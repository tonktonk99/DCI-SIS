# DCI-SIS Pilot Wave 1 — Checklist

**Environment:** Staging only (`APP_ENV=staging`)  
**Scope:** 5–10 internal users · 5–7 วันทำการ  
**ห้าม deploy production จนกว่า Wave 1 จะผ่าน**

---

## 1. Pilot Goal

ทดสอบ system กับ internal users กลุ่มเล็กบน staging environment เพื่อ:
- ยืนยัน core workflows ของทุก role ทำงานถูกต้อง
- ค้นหา usability / workflow gap ก่อนขยายไป Wave 2 หรือ Production
- ยืนยันว่า security, CSRF, role access ทำงานถูกต้องใน multi-user จริง

---

## 2. Pilot Participants

| Role | จำนวน | Test Account |
|------|--------|-------------|
| admin | 1 | `admin_test` |
| registrar | 1–2 | `registrar_test` |
| professor | 1–2 | `prof_test` |
| student | 2–4 | `student_test` |
| alumni | 1 (ถ้าพร้อม) | `alumni_test` |

> Passwords ส่งผ่าน secure channel เท่านั้น — ห้ามส่งใน email/chat ที่ไม่ encrypt

---

## 3. Modules Allowed in Pilot

```
✅ login / logout / language switch (TH/EN)
✅ admin:      dashboard · users (view) · audit logs
✅ registrar:  dashboard · students · sections · transcripts
✅ professor:  dashboard · gradebook (DCI101/001 only) · exams
✅ student:    dashboard · enrollment · transcript (view) · requests
✅ alumni:     dashboard · transcript request · certificate request
```

---

## 4. Modules NOT Allowed in Pilot

```
❌ bulk import / export
❌ destructive delete (users / students)
❌ official certificate / transcript issue (staging ≠ production)
❌ real payment / finance operation
❌ mass user operations
❌ production-wide announcements
❌ admin/settings.php (write forms not yet audited)
```

---

## 5. Pre-Pilot Checklist

ต้องผ่านทุกข้อก่อน notify pilot users:

```
Date: _______  Approved by: _______

[ ] Latest commit deployed to staging — hash: ____________
[ ] php scripts/migrate.php status → Pending=0, Modified=0
[ ] DB backup completed — file: _______________________
[ ] BASE_URL=https://[staging]/dci-sis bash scripts/smoke_check.sh → exit 0
[ ] k6 smoke: checks=100%, login=100%, failed=0%
[ ] k6 baseline (20 VUs): all thresholds pass
[ ] All 5 test accounts login correctly → correct dashboard per role
[ ] CSRF runtime: curl POST without token → 403
[ ] PHP error log: no Fatal/Uncaught errors
[ ] Support owner assigned: _______________________
[ ] Feedback channel ready: _______________________
[ ] Rollback plan: git revert + restore_database.sh confirmed

Sign-off — Tech Lead: _____________  QA Lead: _____________
```

---

## 6. Daily Monitoring Checklist

รันทุกเช้าก่อน 10:00 น.:

```
Date: _______  Day: ___ / ___  Checked by: _______

LOGS
[ ] PHP fatal errors today:       ___  (target: 0)
[ ] HTTP 5xx errors today:        ___  (target: 0 on core pages)
[ ] MySQL error log:              OK / Warning: _______________

SECURITY
[ ] Login failures (>5 per user): ___  Suspicious: ___________
[ ] CSRF blocks today:            ___  (expected: 0)
[ ] Role access violations:       ___

CORE WORKFLOWS (spot check with test account)
[ ] Login → correct dashboard:   ✅ / ❌
[ ] registrar/students.php loads: ✅ / ❌
[ ] student/enrollment.php loads: ✅ / ❌
[ ] Logout → login.php:          ✅ / ❌

FEEDBACK
[ ] New feedback today:          ___
[ ] Critical/High new:           ___

DECISION: [ ] Continue  [ ] Caution  [ ] Pause  [ ] Stop+Rollback

Signed: _______________________
```

---

## 7. Issue Severity

| Severity | คำนิยาม | SLA |
|---------|---------|-----|
| **Critical** | Data leak · role guard หลุด · user เห็นข้อมูลคนอื่น · login ไม่ได้ · data corruption | หยุด pilot ทันที |
| **High** | Enrollment fail · gradebook save fail · transcript ผิด · HTTP 500 ซ้ำ > 3× | แก้ภายใน 4 ชม. |
| **Medium** | หน้าช้า · workflow สับสน · validation ไม่ชัด | แก้ภายใน 1 วัน |
| **Low** | Wording · layout · typo | ก่อน Wave 2 |

---

## 8. Stop Pilot Criteria

หยุด pilot ทันทีถ้าพบ:

```
🔴 data leak หรือ user เห็นข้อมูลคนอื่น
🔴 role guard หลุด (user role A เข้าถึง page ของ role B)
🔴 transcript / grade / enrollment ผิดข้อมูล
🔴 login ไม่ได้ > 50% หรือทุก role
🔴 database error ต่อเนื่อง (connection refused / PDOException)
🔴 HTTP 500 ซ้ำ > 3× บน core pages (login, dashboard, enrollment)
🔴 CSRF bypass ยืนยัน — POST สำเร็จโดยไม่มี token

→ Rollback:
   git revert HEAD --no-edit && git push origin main
   RESTORE_CONFIRM=YES bash scripts/restore_database.sh backups/[pre-pilot].sql.gz
   BASE_URL=... bash scripts/smoke_check.sh   # verify ก่อน resume
```

---

## 9. Feedback Template

```
DCI-SIS Pilot Feedback
──────────────────────────────────────────────
วันที่/เวลา:         ___________________________
ผู้ใช้/Role:          ___________________________
หน้า/Module:         ___________________________
สิ่งที่ทำ:            ___________________________
ผลที่คาดหวัง:         ___________________________
ผลที่เกิดขึ้นจริง:    ___________________________
Severity:            [ ] Critical [ ] High [ ] Medium [ ] Low
Screenshot/Video:    [ ] มี  [ ] ไม่มี
ทำซ้ำได้:            [ ] ทุกครั้ง [ ] บางครั้ง [ ] ครั้งเดียว
หมายเหตุ:            ___________________________
```

---

## 10. End-of-Pilot Review

กรอกหลัง pilot ครบ 5–7 วัน:

```
End-of-Pilot Review
══════════════════════════════════════════════
วันที่:          _____________  ผู้รีวิว: _____________

PARTICIPATION
ผู้ใช้ที่เข้าร่วม:  ___ / ___ คน
Roles ที่ active:   _______________________________
Sessions รวม:      ___

MODULES TESTED
[ ] login/logout     [ ] admin          [ ] registrar
[ ] professor        [ ] student        [ ] alumni

ISSUES
Critical: ___  (resolved: ___ / open: ___)
High:     ___  (resolved: ___ / open: ___)
Medium:   ___
Low:      ___

CRITICAL/HIGH STILL OPEN
1. _______________________________________________
2. _______________________________________________

KEY FEEDBACK
ชอบ: _____________________________________________
ติดปัญหา: ________________________________________
ต้องการเพิ่ม: _____________________________________

PERFORMANCE
หน้าช้าที่สุด: ___  ผู้ใช้รายงาน: ___

DECISION
[ ] ✅ Continue Wave 2  — Critical=0, High unresolved ≤ 1
[ ] 🟡 Extend Wave 1   — มี High หลายรายการที่ต้องแก้ก่อน
[ ] 🔴 Stop and fix    — มี Critical open หรือ data issue

Sign-off — Tech Lead: _____________  QA Lead: _____________
```

---

## Key Files Reference

| File | ใช้สำหรับ |
|------|---------|
| `docs/pilot-wave-1-plan.md` | Full scope + policies |
| `docs/pilot-wave-1-execution-runbook.md` | Entry gate + 10-step runbook |
| `docs/pilot-wave-1-daily-monitoring.md` | Daily monitoring template ละเอียด |
| `docs/pilot-wave-1-review.md` | End-of-pilot review template |
| `scripts/backup_database.sh` | Run before go-live |
| `scripts/smoke_check.sh` | Run after every deploy |
| `scripts/restore_database.sh` | Rollback database |
