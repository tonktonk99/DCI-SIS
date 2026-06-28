# DCI-SIS Staging Seed Data Guide

**For:** QA Engineers, Developers, CI environments  
**Purpose:** Create test accounts and sample academic data for local/staging testing  
**Last reviewed:** 2026-06-28

> **WARNING — NEVER run against production.**  
> The seed script checks `APP_ENV` and will exit immediately if it is set to `production`.

---

## 1. Purpose

The seed script (`scripts/seed_staging.php`) creates a complete set of test accounts
and interconnected sample data covering all 5 roles and the following workflows:

| Workflow | Covered |
|----------|---------|
| Login as all 5 roles | ✓ |
| Student enrollment in a section | ✓ |
| Professor gradebook (grade items + scores) | ✓ |
| Professor exam management (exam + scores) | ✓ |
| Final grade submission | ✓ |
| Student grades / transcript view | ✓ |
| Student document request | ✓ |
| Alumni document request | ✓ |
| Registrar document request queue | ✓ |
| Admin user management | ✓ |
| Admin audit log review | ✓ |

---

## 2. Safety Guarantees

| Guarantee | How |
|-----------|-----|
| Never runs in production | `exit(1)` if `APP_ENV=production` |
| Requires explicit confirmation | `SEED_CONFIRM=YES` env var required |
| No real data in script | Password from env var, no hardcoded credentials |
| Idempotent — re-run safe | SELECT-before-INSERT on every entity; never deletes |
| No truncate / drop / delete | Script contains zero destructive SQL |
| Prepared statements only | All INSERTs use PDO prepared statements |
| CLI only | `exit(1)` if `PHP_SAPI !== 'cli'` |

---

## 3. Required Environment Variables

| Variable | Required for | Description |
|----------|-------------|-------------|
| `APP_ENV` | Both modes | Must be `local`, `staging`, or `test` |
| `SEED_CONFIRM` | `--apply` | Must be the literal string `YES` |
| `SEED_DEFAULT_PASSWORD` | `--apply` | Password to set on all test accounts |
| `DB_HOST` | Both | Default: `127.0.0.1` |
| `DB_PORT` | Both | Default: `8889` (MAMP) or `3306` |
| `DB_NAME` | Both | Default: `dci_sis` |
| `DB_USER` | Both | Default: `root` |
| `DB_PASS` | Both | Default: `root` (MAMP) |

DB variables use the same defaults as `config/database.php`. If your local `.env` or
shell environment already exports them, you do not need to set them again.

**Never set `SEED_DEFAULT_PASSWORD` to a real or production password.**  
Use a throwaway password for staging only, e.g., `StagingPass123!`.

---

## 4. How to Run

### Step 1 — Dry Run (no data written)

Always run dry first to preview what will be created:

```bash
APP_ENV=staging php scripts/seed_staging.php --dry-run
```

For MAMP local dev (DB on port 8889):

```bash
APP_ENV=local DB_PORT=8889 php scripts/seed_staging.php --dry-run
```

Example output:
```
==========================================================
  DCI-SIS Staging Seed | DRY-RUN (nothing will be written)
==========================================================

=== 1. Users ===
[DRY]    users: would create admin_test (admin)
[DRY]    users: would create registrar_test (registrar)
...
==========================================================
  DRY-RUN complete
  Would create : 23 rows
  Already exist: 0 rows
...
```

### Step 2 — Apply

```bash
APP_ENV=staging \
  SEED_CONFIRM=YES \
  SEED_DEFAULT_PASSWORD='StagingPass123!' \
  php scripts/seed_staging.php --apply
```

For MAMP local dev:

```bash
APP_ENV=local \
  DB_PORT=8889 \
  SEED_CONFIRM=YES \
  SEED_DEFAULT_PASSWORD='StagingPass123!' \
  php scripts/seed_staging.php --apply
```

### Re-running (idempotent)

Running the script a second time is safe — it will skip all existing rows:

```
[SKIP]   users: admin_test (admin) (exists, id=8)
[SKIP]   users: registrar_test (registrar) (exists, id=9)
...
```

---

## 5. Test Account List

> Passwords are set to `SEED_DEFAULT_PASSWORD` at seed time.  
> **Do not share or reuse staging passwords elsewhere.**

| Username | Role | Staff/Student Link | Notes |
|----------|------|--------------------|-------|
| `admin_test` | admin | — | Full admin access |
| `registrar_test` | registrar | — | Document queue, transcripts, sections |
| `prof_test` | professor | staff T900, instructs DCI101/001 | Gradebook, exams |
| `student_test` | student | S9999001, enrolled in DCI101/001 | Grades, enrollment, document requests |
| `alumni_test` | alumni | S9999002 (graduated) | Alumni document request |

---

## 6. Seeded Academic Data

The script re-uses existing records where they already exist, and creates them only
if absent.

| Table | Record | Idempotency Key |
|-------|--------|----------------|
| `programs` | BS (สาขาวิชาพุทธศาสตร์) | `program_code = 'BS'` |
| `academic_years` | 2569 | `year_label = '2569'` |
| `semesters` | ภาคเรียนที่ 1/2569 (active) | `term='1'` AND `academic_year_id` |
| `courses` | DCI101 พื้นฐานพุทธศาสตร์ | `course_code = 'DCI101'` |
| `sections` | DCI101/001 (cap=30) | `semester_id` + `course_id` + `section_number` |
| `section_schedules` | Mon 09:00–12:00 ห้อง 101 | Created with section (new sections only) |
| `staff` | T900 Test Professor | `user_id = uid_prof` |
| `section_instructors` | T900 → DCI101/001 (primary) | `section_id` + `staff_id` |
| `students` | S9999001 Test Student | `student_code = 'S9999001'` |
| `students` | S9999002 Test Alumni (graduated) | `student_code = 'S9999002'` |
| `enrollments` | S9999001 → DCI101/001 (enrolled) | `student_id` + `section_id` |
| `grade_items` | Midterm 50%, Final Exam 50% | `section_id` + `name` |
| `grade_scores` | S9999001: Midterm=80, Final=90 | `grade_item_id` + `student_id` |
| `final_grades` | S9999001 → raw=85, A (4.00) submitted | `enrollment_id` |
| `exams` | Midterm Exam (scheduled, +14 days) | `section_id` + `exam_type='midterm'` |
| `exam_scores` | S9999001 midterm = 80/100 | `exam_id` + `student_id` |
| `document_requests` | student_test → transcript (pending) | `requester_user_id` + `request_type` + `status` |
| `document_requests` | alumni_test → transcript (pending) | `requester_user_id` + `request_type` + `status` |

---

## 7. QA Scenarios Supported

After seeding, the following test scenarios can be verified immediately:

### Auth (all roles)
- [ ] Login as `admin_test` → `/admin/dashboard.php`
- [ ] Login as `registrar_test` → `/registrar/dashboard.php`
- [ ] Login as `prof_test` → `/professor/dashboard.php`
- [ ] Login as `student_test` → `/student/dashboard.php`
- [ ] Login as `alumni_test` → `/alumni/dashboard.php`
- [ ] Logout each role → redirects to login

### Student workflows
- [ ] `student_test` → `/student/enrollment.php` → DCI101/001 visible
- [ ] `student_test` → `/student/grades.php` → Midterm 80, Final 90 visible
- [ ] `student_test` → `/student/transcript.php` → grade A (4.00) visible
- [ ] `student_test` → submit additional document request

### Professor workflows
- [ ] `prof_test` → `/professor/gradebook.php` → DCI101/001 section visible
- [ ] `prof_test` → `/professor/gradebook.php` → `student_test` in grade list
- [ ] `prof_test` → `/professor/exams.php` → Midterm Exam visible

### Registrar workflows
- [ ] `registrar_test` → `/registrar/students.php` → S9999001 and S9999002 visible
- [ ] `registrar_test` → document request queue → 2 pending requests

### Alumni workflows
- [ ] `alumni_test` → `/alumni/transcript_request.php` → form accessible

### Admin workflows
- [ ] `admin_test` → `/admin/users.php` → all test accounts visible
- [ ] `admin_test` → `/admin/audit-logs.php` → login events visible after logins above

---

## 8. Rollback / Cleanup

The seed script never modifies existing data. To remove seed data from a staging DB,
run the following SQL **manually** — review each statement before executing:

```sql
-- Remove test document requests
DELETE FROM document_requests WHERE requester_user_id IN (
  SELECT id FROM users WHERE username IN ('student_test','alumni_test')
);

-- Remove test exam scores and exams
DELETE es FROM exam_scores es
JOIN exams e ON es.exam_id = e.id
WHERE e.section_id IN (SELECT id FROM sections WHERE section_number = '001')
  AND es.student_id IN (SELECT id FROM students WHERE student_code IN ('S9999001','S9999002'));

DELETE FROM exams
WHERE section_id IN (SELECT id FROM sections WHERE section_number = '001')
  AND exam_type = 'midterm';

-- Remove test grades
DELETE FROM final_grades WHERE student_id IN (
  SELECT id FROM students WHERE student_code IN ('S9999001','S9999002')
);
DELETE FROM grade_scores WHERE student_id IN (
  SELECT id FROM students WHERE student_code IN ('S9999001','S9999002')
);
DELETE FROM grade_items WHERE name IN ('Midterm','Final Exam');

-- Remove test enrollment
DELETE FROM enrollments WHERE student_id IN (
  SELECT id FROM students WHERE student_code IN ('S9999001','S9999002')
);

-- Remove test students, staff
DELETE FROM students WHERE student_code IN ('S9999001','S9999002');
DELETE FROM staff WHERE staff_code = 'T900';

-- Remove test users (last — FK dependencies above must be cleared first)
DELETE FROM users WHERE username IN (
  'admin_test','registrar_test','prof_test','student_test','alumni_test'
);
```

> Alternatively, on a staging environment that can be fully reset:
> `bash scripts/restore_database.sh <known-clean-backup.sql.gz>`

---

## 9. Troubleshooting

| Error | Likely cause | Fix |
|-------|-------------|-----|
| `APP_ENV=production detected` | Shell has APP_ENV=production | Add `APP_ENV=staging` to the command |
| `SEED_CONFIRM=YES is required` | Missing env var | Add `SEED_CONFIRM=YES` to the command |
| `SEED_DEFAULT_PASSWORD is required` | Missing env var | Add `SEED_DEFAULT_PASSWORD='...'` to the command |
| `Cannot find config/database.php` | Wrong working directory | Run from the project root: `php scripts/seed_staging.php --apply` |
| DB connection error | Wrong DB_HOST/PORT | Set `DB_PORT=8889` for MAMP local |
| `[SKIP]` on all rows | Script was already run | Normal — all data already exists |
