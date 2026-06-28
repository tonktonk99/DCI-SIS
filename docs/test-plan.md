# DCI-SIS Test Plan

**System:** DCI Student Information System  
**Version:** Phase 1K  
**Scale:** ~10,000 accounts, 500–1,000 peak concurrent users  
**Stack:** PHP 8.3, MySQL 8.0, Apache / Nginx  
**Last reviewed:** 2026-06-28

---

## 1. Test Scope

This plan covers all testing required before, during, and after a production deployment of DCI-SIS.

**In scope:**
- Functional testing of all 5 user roles (admin, registrar, professor, student, alumni)
- Security testing (CSRF, role access, input validation, audit logging)
- Data integrity testing (enrollment, grades, transcripts)
- Performance smoke testing (key pages, pagination, response time baseline)
- Backup and restore verification
- Pre-deploy and post-deploy verification
- Rollback procedure

**Out of scope for this document:**
- Load testing at full scale (500–1,000 concurrent) — requires a dedicated load test environment
- Automated end-to-end UI testing (Selenium/Playwright) — planned for future phase
- Email delivery testing — not applicable (no email feature yet)
- File upload/download testing — not applicable (no upload feature yet)

---

## 2. Test Environments

| Environment | Purpose | DB Name | APP_ENV |
|-------------|---------|---------|---------|
| Local (MAMP) | Developer testing during development | `dci_sis` | `local` |
| Staging | QA testing and pre-deploy validation | `dci_sis_staging` | `production` |
| Production | Live system | `dci_sis` | `production` |

**Rules:**
- All destructive tests (restore, migration rollback) must only run in Local or Staging
- Load testing must only run in a dedicated load test environment, never against Production
- Staging must mirror Production configuration (`APP_ENV=production`, same PHP version)

---

## 3. Test Accounts

> **IMPORTANT:** These are placeholder accounts. Create them in Staging/Local only.
> Never use these names or any variation in Production.
> Never commit passwords to version control.

| Role | Username (example) | Password | Create in |
|------|-------------------|----------|-----------|
| admin | `admin_test` | Set locally only | Staging / Local |
| registrar | `registrar_test` | Set locally only | Staging / Local |
| professor | `prof_test` | Set locally only | Staging / Local |
| student | `student_test` | Set locally only | Staging / Local |
| alumni | `alumni_test` | Set locally only | Staging / Local |

**Required supporting data for test accounts:**
- `prof_test` must have a staff profile linked to at least one section
- `student_test` must have a student profile with at least one active enrollment
- `alumni_test` must have a student profile marked as graduated/alumni

---

## 4. Release Criteria (Definition of Ready to Deploy)

All of the following must be true before deploying to Production:

```
Infrastructure:
[ ] APP_ENV=production set on target server
[ ] display_errors=Off verified
[ ] DB credentials are non-root environment variables
[ ] HTTPS certificate installed and working
[ ] .htaccess or Nginx config blocking config/, includes/, scripts/
[ ] Pre-deploy database backup completed and verified

Code:
[ ] Git working tree is clean (git status shows nothing uncommitted)
[ ] All Phase checklists (0A–1J) are complete
[ ] No PHP fatal errors in staging error log
[ ] All functional test cases in Section 6 passed in Staging
[ ] All security test cases in Section 7 passed in Staging

QA sign-off:
[ ] At least one tester per role has completed functional tests
[ ] Security checklist signed off
[ ] Smoke test script passes in Staging (bash scripts/smoke_check.sh)
[ ] Rollback commit hash recorded: ____________________
```

---

## 5. Rollback Criteria

Initiate rollback if ANY of the following occur after deploy:

- Login fails for any role
- PHP fatal error appears in error log within 5 minutes of deploy
- Database connection error occurs
- Enrollment, gradebook, or transcript pages return errors
- Audit logs stop recording
- Security headers missing from responses

**Rollback SLA:** Initiate within 15 minutes of detecting a critical issue.

See `docs/production-smoke-checklist.md` Section 4 for rollback steps.

---

## 6. Functional Test Cases

### 6A. Auth Module

| # | Test Case | Steps | Expected | Tester |
|---|-----------|-------|----------|--------|
| A01 | Login success — admin | Login with admin_test | Redirect to /admin/dashboard.php | |
| A02 | Login success — registrar | Login with registrar_test | Redirect to /registrar/dashboard.php | |
| A03 | Login success — professor | Login with prof_test | Redirect to /professor/dashboard.php | |
| A04 | Login success — student | Login with student_test | Redirect to /student/dashboard.php | |
| A05 | Login success — alumni | Login with alumni_test | Redirect to /alumni/dashboard.php | |
| A06 | Login fail — wrong password | Submit wrong password | Stay on login, show error message, no session created | |
| A07 | Login fail — unknown user | Submit nonexistent username | Same as A06 (no user enumeration) | |
| A08 | Logout | Click logout from any role | Session cleared, redirect to login.php | |
| A09 | Session timeout | Wait 2h+ (or set SESSION_IDLE_TTL=30 in test) | Auto-redirect to login with timeout message | |
| A10 | Wrong role access | Student tries /admin/dashboard.php | Redirect to login with reason=forbidden | |
| A11 | Language switch TH→EN | Click EN on login page | Page reloads in English | |
| A12 | Language switch EN→TH | Click TH on login page | Page reloads in Thai | |
| A13 | Demo box hidden in production | Set APP_ENV=production in staging | Demo accounts box not visible on login page | |
| A14 | Session regenerate on login | Check session ID before/after login | Session ID changes after successful login | |

### 6B. Admin Module

| # | Test Case | Steps | Expected | Tester |
|---|-----------|-------|----------|--------|
| B01 | Admin dashboard loads | Login as admin → /admin/dashboard.php | Page loads, no errors | |
| B02 | Users list loads | /admin/users.php | User list displays | |
| B03 | Create user | Fill form, submit | New user appears in list | |
| B04 | Change user role | Select user → change role | Role updated, audit log recorded | |
| B05 | Reset password | Select user → reset password | Password changed without showing old value | |
| B06 | Roles page loads | /admin/roles.php | Role table displays | |
| B07 | Settings page loads | /admin/settings.php | Settings page renders | |
| B08 | Audit logs load | /admin/audit-logs.php | Log entries display, pagination works | |
| B09 | Audit log shows AUTH.LOGIN_SUCCESS | Login as any role → check audit logs | Entry recorded with correct user/action | |

### 6C. Registrar Module

| # | Test Case | Steps | Expected | Tester |
|---|-----------|-------|----------|--------|
| C01 | Registrar dashboard loads | Login as registrar | Dashboard loads | |
| C02 | Students list loads | /registrar/students.php | Student list with pagination | |
| C03 | Create student | Fill student form | New student created, STUDENT.CREATE in audit log | |
| C04 | Change student status | Select student → change study_status | Status updated, STUDENT.STATUS_CHANGE in audit log | |
| C05 | Professors list loads | /registrar/professors.php | Staff list displays | |
| C06 | Create professor | Fill staff form | New staff created, STAFF.CREATE in audit log | |
| C07 | Courses list loads | /registrar/courses.php | Course catalog displays | |
| C08 | Create course | Fill course form | New course created, COURSE.CREATE in audit log | |
| C09 | Toggle course status | Click toggle on course | Status changes, COURSE.TOGGLE_STATUS in audit log | |
| C10 | Sections list loads | /registrar/sections.php | Section list with pagination | |
| C11 | Create section | Fill section form | New section created, SECTION.CREATE in audit log | |
| C12 | Toggle section status | Click toggle | Status changes, SECTION.TOGGLE_STATUS in audit log | |
| C13 | Semesters list loads | /registrar/semesters.php | Semester list displays | |
| C14 | Create semester | Fill semester form | New semester created, SEMESTER.CREATE in audit log | |
| C15 | Set current semester | Click set current | Semester marked current, SEMESTER.SET_CURRENT in audit log | |
| C16 | Academic years loads | /registrar/academic-years.php | Year list displays | |
| C17 | Create academic year | Fill year form | New year created, ACADEMIC_YEAR.CREATE in audit log | |
| C18 | Programs list loads | /registrar/programs.php | Program list displays | |
| C19 | Create program | Fill program form | New program created, PROGRAM.CREATE in audit log | |
| C20 | Grades page loads | /registrar/grades.php | Grade management loads | |
| C21 | Exams list loads | /registrar/exams.php | Exam list displays | |
| C22 | Create exam | Fill exam form | New exam created, EXAM.CREATE in audit log | |
| C23 | Transcripts page loads | /registrar/transcripts.php | Transcript viewer loads | |
| C24 | View student transcript | Search student → view transcript | Transcript shows correct data for correct student | |
| C25 | Certificate print | /registrar/certificate_print.php | Print preview renders | |
| C26 | Document requests loads | /registrar/document-requests.php | Request list with pagination | |
| C27 | Process document request | Select request → change status | Status updated, DOCUMENT.PROCESS in audit log | |

### 6D. Professor Module

| # | Test Case | Steps | Expected | Tester |
|---|-----------|-------|----------|--------|
| D01 | Professor dashboard loads | Login as professor | Dashboard loads | |
| D02 | My courses loads | /professor/courses.php | Sections assigned to this professor shown only | |
| D03 | My students loads | /professor/students.php | Roster of students in professor's sections | |
| D04 | Exams page loads | /professor/exams.php | Exams for professor's sections shown | |
| D05 | Save exam scores | Fill scores → submit | Scores saved, EXAM.SAVE_SCORES in audit log | |
| D06 | Gradebook loads | /professor/gradebook.php | Grade items and roster for professor's section | |
| D07 | Add grade item | Fill item form → submit | New item created, GRADEBOOK.ADD_ITEM in audit log | |
| D08 | Save grade scores | Fill scores → save | Scores saved, GRADEBOOK.SAVE_SCORES in audit log | |
| D09 | Submit final grades | Click submit final | Grades locked, GRADEBOOK.SUBMIT_FINAL in audit log | |
| D10 | Professor cannot see other's sections | Try accessing section not assigned | Section data not shown / redirected | |

### 6E. Student Module

| # | Test Case | Steps | Expected | Tester |
|---|-----------|-------|----------|--------|
| E01 | Student dashboard loads | Login as student | Dashboard loads | |
| E02 | Enrollment page loads | /student/enrollment.php | Available sections listed | |
| E03 | Enroll in course | Select section → enroll | Enrollment created, ENROLLMENT.ENROLL in audit log | |
| E04 | Duplicate enrollment blocked | Try to enroll in same section again | Error: already enrolled | |
| E05 | Drop course | Select enrolled section → drop | Enrollment dropped, ENROLLMENT.DROP in audit log | |
| E06 | My courses loads | /student/courses.php | Enrolled courses listed | |
| E07 | Schedule loads | /student/schedule.php | Class schedule displays | |
| E08 | Grades page loads | /student/grades.php | Grade items and scores for this student only | |
| E09 | Exams page loads | /student/exams.php | Upcoming exams for enrolled sections | |
| E10 | Finance page loads | /student/finance.php | Student invoice/payment info | |
| E11 | Document requests loads | /student/requests.php | Request list and form | |
| E12 | Submit document request | Fill form → submit | Request created, DOCUMENT_REQUEST.SUBMIT in audit log | |
| E13 | Transcript view loads | /student/transcript.php | Student's own transcript | |
| E14 | Transcript print | /student/transcript_print.php | Print preview renders correctly | |
| E15 | Student cannot see other students' data | Manually try ?student_id= for another student | Data for self only, not another student's | |

### 6F. Alumni Module

| # | Test Case | Steps | Expected | Tester |
|---|-----------|-------|----------|--------|
| F01 | Alumni dashboard loads | Login as alumni | Dashboard loads | |
| F02 | Profile page loads | /alumni/profile.php | Shows own profile only | |
| F03 | Transcript request loads | /alumni/transcript_request.php | Request form displays | |
| F04 | Submit transcript request | Fill form → submit | Request created, DOCUMENT_REQUEST.SUBMIT in audit log | |
| F05 | Certificate request loads | /alumni/certificate_request.php | Request form displays | |
| F06 | Submit certificate request | Fill form → submit | Request created, DOCUMENT_REQUEST.SUBMIT in audit log | |
| F07 | Alumni cannot access student pages | Try /student/enrollment.php | Redirect to login with forbidden | |

---

## 7. Security Test Cases

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| S01 | CSRF — missing token | Submit any POST form without _csrf field | HTTP 403 — redirect to login?error=csrf |
| S02 | CSRF — invalid token | Submit POST with _csrf=invalid_value | HTTP 403 — redirect to login?error=csrf |
| S03 | CSRF — replayed token | Submit same form twice with same token | Second submission rejected |
| S04 | No GET write actions | Check all links/buttons that write data | All writes use POST forms only |
| S05 | Wrong role — admin pages | Login as student → GET /admin/dashboard.php | Redirect login?reason=forbidden |
| S06 | Wrong role — registrar pages | Login as alumni → GET /registrar/students.php | Redirect login?reason=forbidden |
| S07 | Wrong role — professor pages | Login as student → GET /professor/gradebook.php | Redirect login?reason=forbidden |
| S08 | Student data isolation | student_test views ?student_id=<other_id> | Cannot see other student's grades/enrollment |
| S09 | Professor section isolation | prof_test accesses gradebook of section they don't teach | Section data not accessible |
| S10 | Status value validation | POST with status=malicious_value | Input rejected or sanitized (enum validation) |
| S11 | Date value validation | POST with date=not-a-date | Input rejected or treated as null |
| S12 | Integer validation | POST with id=abc | Input rejected or treated as 0/invalid |
| S13 | No password in audit logs | Check audit_logs.details after login | No password or hash in details field |
| S14 | No raw POST dump in logs | Check audit_logs.details for any action | No raw POST data or session token in details |
| S15 | DB error not exposed | Simulate DB error (invalid DB_NAME in staging) | Generic "Database connection error" — no connection string |
| S16 | PHP errors not exposed | Set APP_ENV=production, trigger notice | Error logged, not displayed to user |
| S17 | X-Frame-Options present | curl -I /login.php | Header: X-Frame-Options: SAMEORIGIN |
| S18 | X-Content-Type-Options present | curl -I /login.php | Header: X-Content-Type-Options: nosniff |
| S19 | Referrer-Policy present | curl -I /login.php | Header: Referrer-Policy: strict-origin-when-cross-origin |
| S20 | config/ directory blocked | curl /config/database.php | HTTP 403 (enforced by .htaccess or Nginx) |
| S21 | includes/ directory blocked | curl /includes/auth.php | HTTP 403 |
| S22 | .sql files blocked | curl /task29_duplicate_protection_FULL.sql | HTTP 403 |
| S23 | Session cookie HttpOnly | Check browser DevTools → Application → Cookies | HttpOnly flag set on dci_sess cookie |
| S24 | Session cookie name | Check browser DevTools → Cookies | Cookie named dci_sess, not PHPSESSID |
| S25 | Session regenerate on login | Check session ID before/after login (DevTools) | Session ID changes on successful login |

---

## 8. Data Integrity Test Cases

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| DI01 | No duplicate enrollment | Enroll student in section → try to enroll same section again | Error: already enrolled, only 1 row in enrollments |
| DI02 | Enrolled count consistency | Enroll student → check sections.enrolled_count | enrolled_count incremented by 1 |
| DI03 | Drop course decrement | Drop enrollment → check sections.enrolled_count | enrolled_count decremented by 1 (min 0) |
| DI04 | Grade scores no duplicate | Save scores for student → save again | UPSERT: only 1 row per student per grade_item |
| DI05 | Final grade submission | Submit final grades → verify final_grades table | One row per student per section, no duplicates |
| DI06 | Transaction rollback | Simulate error mid-enrollment (disconnect mid-txn) | Partial enrollment not committed, data consistent |
| DI07 | Transcript data isolation | View transcript for student A | Only shows student A's sections/grades |
| DI08 | Document request status | Create request → process by registrar | Status changes correctly, updated_at set |
| DI09 | Audit log completeness | Perform 5 different write actions | 5 audit_log entries created with correct entity_type/id |
| DI10 | Audit log after transaction | Actions inside transactions still audit logged | Audit log entry exists even when inside a successful transaction |
| DI11 | Backup row count consistency | Backup then restore to staging | Row counts in critical tables match pre-backup counts |

---

## 9. Performance Smoke Test Cases

> **Purpose:** Verify no obvious N+1 queries or full-table scans on key pages.
> **NOT** a load test. Run manually in staging with browser DevTools or server-side timer.
> Target response time: under 1 second for all pages below (no concurrent load).

| # | Page | URL | Target |
|---|------|-----|--------|
| P01 | Student enrollment | /student/enrollment.php | < 1s page load |
| P02 | Professor gradebook | /professor/gradebook.php?section_id=N | < 1s page load |
| P03 | Registrar students | /registrar/students.php?page=1 | < 1s page load |
| P04 | Registrar transcripts | /registrar/transcripts.php | < 1s page load |
| P05 | Admin audit logs | /admin/audit-logs.php?page=1 | < 1s page load (first page, with index) |
| P06 | Pagination | Any list page → click page 2 | < 1s, correct items |
| P07 | Section create | POST /registrar/sections.php | < 2s including schedule insert |
| P08 | Gradebook save scores | POST /professor/gradebook.php save_scores | < 2s for 60-student section |
| P09 | Gradebook submit final | POST /professor/gradebook.php submit_final | < 3s for 60-student section |

**How to measure:**
```bash
# Server-side (Apache/Nginx log)
grep "POST /dci-sis/professor/gradebook.php" /var/log/apache2/access.log | tail -5

# Browser: DevTools → Network → look at "Time" column for the document request

# Command line (public pages only):
time curl -s -o /dev/null "${BASE_URL}/login.php"
```

**MySQL slow query monitoring:**
```sql
-- After any performance concern, check slow query log
-- Or run EXPLAIN on the suspected query in staging
SHOW STATUS LIKE 'Slow_queries';
```

**Future (optional):** k6 load test script — run only in dedicated load test environment with synthetic data, never against Production. Create as a separate `scripts/loadtest/` directory when ready.

---

## 10. Backup and Restore Tests

| # | Test Case | Steps | Expected |
|---|-----------|-------|----------|
| BR01 | Backup script succeeds | Set env vars → run scripts/backup_database.sh | .sql.gz file created in BACKUP_DIR |
| BR02 | Backup file valid | gunzip -t <backup_file> | Exit code 0, no corruption message |
| BR03 | Backup script fails without env vars | Run without DB_NAME | Exit with error: DB_NAME is required |
| BR04 | Backup not in git | After backup, run git status | backups/ directory not shown as untracked |
| BR05 | Restore fails without confirmation | Run restore without RESTORE_CONFIRM=YES | Exit with warning, no restore performed |
| BR06 | Restore from .sql.gz | Set RESTORE_CONFIRM=YES → run restore | Database populated from compressed file |
| BR07 | Restore row count | After restore, check critical table counts | Counts match pre-backup values |
| BR08 | Login after restore | Login as all 5 roles after restore | All logins succeed |
| BR09 | Enrollment after restore | Check enrollment data | Rows present and consistent |
| BR10 | Gradebook after restore | Check grade_items and grade_scores | Data intact |
| BR11 | Audit logs after restore | Check audit_logs | Recent entries present |

See `docs/backup-restore-plan.md` for full restore procedure and monthly drill checklist.

---

## 11. Regression Test Cases

Run after any code change to ensure nothing broken:

| # | Area | What to verify |
|---|------|----------------|
| R01 | Login | All 5 roles can still login |
| R02 | CSRF | All POST forms still include _csrf field |
| R03 | Session | dci_sess cookie still set with HttpOnly |
| R04 | Security headers | X-Frame-Options still in response |
| R05 | Config protection | /config/database.php still returns 403 |
| R06 | Input validation | status field still validated by allowlist |
| R07 | Audit logs | Actions still recorded in audit_logs table |
| R08 | Enrollment transaction | Enroll and drop still work atomically |
| R09 | Gradebook transaction | Save scores and submit final still transactional |
| R10 | Error display | APP_ENV=production still hides PHP errors |

---

## 12. Known Risks and Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| DB migration fails mid-deployment | Low | High | Always run pre-deploy backup; rollback procedure in place |
| OPcache serves stale code after deploy | Medium | Medium | Run opcache_reset() or PHP-FPM reload after every deploy |
| .htaccess not active (AllowOverride None) | Medium | High | Verify .htaccess works in staging before deploy; document Nginx alternative |
| task29_duplicate_protection_FULL.sql in web root | High | Medium | .htaccess blocks it; also delete or move this file before production launch |
| Session cookie name change (PHPSESSID→dci_sess) | Low | Low | All existing sessions invalidated on first deploy — expected behavior |
| Demo credentials box visible in production | Low | High | APP_ENV=production hides it; verify in staging before deploy |
| audit_logs table grows unbounded | Medium | Medium | See backup-restore-plan.md Section 5A for retention/archive strategy |
| No CSP header yet | High | Medium | Planned for future phase; inline styles/scripts need audit first |

---

## 13. Sign-off Checklist

Complete before approving production deployment:

```
Release Sign-off — Version: _______  Date: _______

[ ] QA Engineer: all functional tests in Sections 6A–6F completed in Staging
    Signed: ______________________

[ ] Security Reviewer: all security tests in Section 7 passed
    Signed: ______________________

[ ] DBA: database backup completed and verified before deploy
    Signed: ______________________

[ ] Lead Developer: no uncommitted changes, code reviewed, smoke test passed
    Signed: ______________________

[ ] Team Lead / IT Manager: release criteria in Section 4 satisfied
    Signed: ______________________

Rollback commit hash: ______________________
Pre-deploy backup file: ____________________
Expected deploy time: _____________________
Maintenance window needed: [ ] Yes  [ ] No
```
