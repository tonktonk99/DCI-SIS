# DCI-SIS Backup, Restore and Data Retention Plan

**System:** DCI Student Information System  
**Database:** MySQL 8.0 — `dci_sis` (27 tables)  
**Scale:** ~10,000 accounts, 500–1,000 peak concurrent users  
**Last reviewed:** 2026-06-28

---

## 1. Scope

This document covers:
- Database backup and restore procedures
- Data retention policy per data class
- Restore drill checklist (monthly)
- Pre-deploy backup discipline
- Emergency recovery procedure
- Responsibility matrix

**Out of scope for this version:**
- File/document upload storage (feature not yet implemented)
- Replication or HA failover
- Cloud object storage integration

---

## 2. Data Classification

### 2A. Critical — Must never be lost

These records define academic outcomes and identity. Loss is unrecoverable from other sources.

| Table | Description |
|-------|-------------|
| `users` | Login credentials and role assignments |
| `persons` | Permanent identity master (person_no = DCI#######) |
| `user_roles` | Role→user mapping |
| `identity_links` | User–person linkage |
| `students` | Student master data |
| `student_programs` | Student degree program enrollment |
| `enrollments` | Course enrollment records |
| `grade_items` | Grade component definitions (weights) |
| `grade_scores` | Per-student per-item scores |
| `final_grades` | Submitted final letter grades |
| `exam_scores` | Exam score records |
| `semesters` | Academic calendar |
| `sections` | Course sections |
| `courses` | Course catalog |
| `academic_years` | Academic year definitions |
| `audit_logs` | Forensic and compliance audit trail |

### 2B. Important — Difficult to reconstruct

| Table | Description |
|-------|-------------|
| `staff` | Professor / staff profiles |
| `programs` | Degree program catalog |
| `exams` | Exam definitions |
| `document_requests` | Student document request history |
| `registrar_petitions` | Academic petition records |
| `section_instructors` | Instructor–section assignments |
| `section_schedules` | Class schedule |

### 2C. Operational — Recoverable from external records

| Table | Description |
|-------|-------------|
| `student_holds` | Hold flags (recoverable from HR/Finance) |
| `student_invoices` | Invoice records |
| `student_payments` | Payment records |

### 2D. File Storage

**Current status: Not applicable.** The system has no file upload feature. The only static asset is `assets/images/logo.png` (version-controlled in git).

**When file uploads are added:** Implement rsync or object-storage (S3-compatible) backup separately from the database backup. Do not store uploaded files inside the database (LONGBLOB).

### 2E. Secrets and Credentials

`config/database.php` and `.env` are **gitignored** and must be managed via your secrets management system (environment variables, vault, or secured deploy process). Never back up credentials inside database dumps or committed files.

---

## 3. Backup Schedule

### 3A. Automated Daily Backup

- **Frequency:** Every day at 02:00 local time
- **Method:** `scripts/backup_database.sh` via cron
- **Retention:** 14 days (rolling delete)
- **Location:** `/var/backups/dci-sis/daily/` (or equivalent outside web root)
- **Format:** `dci_sis_YYYYMMDD_HHMMSS.sql.gz`

**Cron example:**
```cron
0 2 * * * DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=dci_sis DB_USER=dci_backup DB_PASS=secret BACKUP_DIR=/var/backups/dci-sis/daily /var/www/dci-sis/scripts/backup_database.sh >> /var/log/dci_backup.log 2>&1
```

### 3B. Weekly Backup

- **Frequency:** Every Monday at 01:00 local time
- **Retention:** 12 weeks
- **Location:** `/var/backups/dci-sis/weekly/`

**Cron example:**
```cron
0 1 * * 1 BACKUP_DIR=/var/backups/dci-sis/weekly [env vars] /var/www/dci-sis/scripts/backup_database.sh
```

### 3C. Monthly Archive

- **Frequency:** 1st of each month at 00:00
- **Retention:** 12 months minimum; graduate cohort records recommended permanent retention
- **Location:** `/var/backups/dci-sis/monthly/` or cold storage (S3 Glacier, tape)

### 3D. Pre-Deploy Backup

**Required before every production deployment.**

```bash
# Run immediately before any code deploy or migration
BACKUP_DIR=/var/backups/dci-sis/pre-deploy \
  [other env vars] \
  bash scripts/backup_database.sh
```

Retain for 30 days. This is the primary rollback point for any failed deployment.

### 3E. Offsite / Cloud Backup

- Sync daily backup to offsite location using rclone, AWS S3, or equivalent
- Minimum: 1 offsite copy at a geographically separate location
- Encrypt before transfer (see Section 3F)

**rclone example:**
```bash
rclone copy /var/backups/dci-sis/ remote:dci-sis-backups/ --min-age 1m
```

### 3F. Backup Encryption

Encrypt all backups stored offsite or on removable media:

```bash
# Encrypt with GPG (symmetric, passphrase must be stored in vault)
gpg --symmetric --cipher-algo AES256 dci_sis_20260628_020000.sql.gz

# Or use openssl
openssl enc -aes-256-cbc -pbkdf2 -in dci_sis_backup.sql.gz -out dci_sis_backup.sql.gz.enc
```

Store the encryption key/passphrase in a password manager or vault, **never next to the backup files**.

---

## 4. Restore Procedure

### 4A. Standard Restore (Staging / Local)

> **IMPORTANT:** Always restore to staging or local first. Verify data integrity before touching production.

**Step 1: Set environment variables**
```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=dci_sis_staging    # Use staging DB name, not production
export DB_USER=dci_app
export DB_PASS=your_password
```

**Step 2: Identify the backup file**
```bash
ls -lth /var/backups/dci-sis/daily/ | head -10
```

**Step 3: Run restore script**
```bash
RESTORE_CONFIRM=YES bash scripts/restore_database.sh /var/backups/dci-sis/daily/dci_sis_20260628_020000.sql.gz
```

**Step 4: Verify row counts**
```sql
SELECT 'users' AS tbl, COUNT(*) AS cnt FROM users
UNION ALL SELECT 'students', COUNT(*) FROM students
UNION ALL SELECT 'enrollments', COUNT(*) FROM enrollments
UNION ALL SELECT 'final_grades', COUNT(*) FROM final_grades
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs;
```

**Step 5: Verify application login**
- Login as admin → verify dashboard loads
- Login as student → verify enrollment list
- Login as professor → verify gradebook
- Login as registrar → verify student list
- Login as alumni → verify document request

**Step 6: Verify recent audit log**
```sql
SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10;
```

### 4B. Emergency Production Restore

Use only when staging restore has been verified successfully.

```bash
# Step 1: Confirm you have authorization
# Step 2: Take a LIVE backup of the broken production DB first
BACKUP_DIR=/var/backups/dci-sis/emergency [env vars] bash scripts/backup_database.sh

# Step 3: Restore to production (requires explicit confirmation)
DB_NAME=dci_sis RESTORE_CONFIRM=YES bash scripts/restore_database.sh <backup_file>

# Step 4: Verify (see 4A steps 4-6)
# Step 5: Clear OPcache if PHP is running
php -r "opcache_reset();" 2>/dev/null || true
```

### 4C. Partial / Table-Level Restore

If only specific tables need to be restored (e.g., accidentally deleted enrollments):

```bash
# Extract only specific tables from a full dump
gunzip -c backup.sql.gz | grep -A 1000 "CREATE TABLE \`enrollments\`" | head -500 > enrollments_extract.sql
# Then manually review and import
```

For critical-path cases, restore to a separate database first and then copy rows:
```sql
INSERT INTO dci_sis.enrollments SELECT * FROM dci_sis_restore.enrollments WHERE id > <last_known_good_id>;
```

---

## 5. Data Retention Policy

| Data Category | Minimum Retention | Recommended | Basis |
|---------------|------------------|-------------|-------|
| grades / final_grades | Permanent | Permanent | Academic record law |
| transcripts / enrollments | Permanent | Permanent | Academic record law |
| audit_logs | 2 years | 5 years | Compliance / forensics |
| document_requests | 5 years | 7 years | Administrative record |
| registrar_petitions | 5 years | 7 years | Administrative record |
| student_holds | 3 years after graduation | 5 years | Administrative |
| student_invoices | 7 years | 7 years | Financial / tax law |
| student_payments | 7 years | 7 years | Financial / tax law |
| persons / identity | Permanent | Permanent | Identity continuity |
| exam_scores | Until graduation + 2y | Permanent | Academic record |
| staff records | Until termination + 5y | 10 years | HR law |

### 5A. audit_logs Retention in Database

The `audit_logs` table will grow continuously. Implement a retention policy:

```sql
-- Archive logs older than 2 years before deleting
-- Run as a scheduled job (monthly)
INSERT INTO audit_logs_archive SELECT * FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

> Do not delete audit_logs without first exporting them to a separate archive table or file.

### 5B. Backup File Retention (Automated Cleanup)

Add to cron (run after daily backup):

```bash
# Delete daily backups older than 14 days
find /var/backups/dci-sis/daily/ -name "*.sql.gz" -mtime +14 -delete

# Delete weekly backups older than 84 days (12 weeks)
find /var/backups/dci-sis/weekly/ -name "*.sql.gz" -mtime +84 -delete

# Monthly and pre-deploy: delete manually after review
```

---

## 6. Restore Drill Checklist (Monthly)

Perform this drill at least once per month, ideally before semester start or major deployments.

**Target:** Complete drill in under 2 hours (practice brings RTO within 4 hours for emergencies)

```
Restore Drill — [Date: ___________] — [Performed by: ___________]

[ ] 1. Identify most recent daily backup file
[ ] 2. Confirm backup file size is reasonable (compare to previous month)
[ ] 3. Gunzip and verify backup is valid SQL:
        gunzip -c backup.sql.gz | head -50
[ ] 4. Restore to LOCAL or STAGING database (not production)
        RESTORE_CONFIRM=YES DB_NAME=dci_sis_drill bash scripts/restore_database.sh <file>
[ ] 5. Verify row counts:
        users count matches expected: _____ rows
        students count: _____ rows
        enrollments count: _____ rows
        final_grades count: _____ rows
        audit_logs count: _____ rows
[ ] 6. Point local app config to staging DB and test:
        [ ] Login as admin → dashboard loads
        [ ] Login as student → enrollment list visible
        [ ] Login as professor → gradebook accessible
        [ ] Login as registrar → student management works
        [ ] Login as alumni → document request works
[ ] 7. Check audit_logs shows recent entries:
        SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5;
[ ] 8. Record drill results:
        RTO achieved: _____ minutes from backup identification to verified login
        Issues found: ________________________________________
        Action items: ________________________________________
[ ] 9. Drop drill database and clean up:
        DROP DATABASE dci_sis_drill;
```

---

## 7. Pre-Deploy Backup Checklist

Run this before every deployment (code change or migration):

```
Pre-Deploy Backup — [Date: ___________] — [Deploy by: ___________]

[ ] 1. Run backup script:
        BACKUP_DIR=/var/backups/dci-sis/pre-deploy [env vars] bash scripts/backup_database.sh
[ ] 2. Confirm backup file was created and is non-zero
[ ] 3. Note backup filename: ________________________________________
[ ] 4. Proceed with deployment
[ ] 5. If deployment fails:
        [ ] Roll back code (git revert)
        [ ] Restore from pre-deploy backup if data was changed:
            RESTORE_CONFIRM=YES bash scripts/restore_database.sh <pre-deploy-file>
```

---

## 8. Emergency Recovery Checklist

If production database is corrupted or inaccessible:

```
[ ] 1. DO NOT restart MySQL blindly — may lose InnoDB redo log recovery chance
[ ] 2. Contact DBA / sysadmin immediately
[ ] 3. Identify most recent valid backup:
        ls -lth /var/backups/dci-sis/daily/ | head -5
[ ] 4. Estimate data loss window (now minus backup timestamp = RPO gap)
[ ] 5. Notify stakeholders of estimated downtime
[ ] 6. Restore to staging first and verify (see Section 4A)
[ ] 7. Once staging restore is verified, proceed with production restore (Section 4B)
[ ] 8. After production restore, run OPcache reset
[ ] 9. Verify login and critical flows
[ ] 10. Document incident: what happened, when, what data was lost, how recovered
```

---

## 9. Rollback Strategy

| Scenario | Rollback Method |
|----------|----------------|
| Bad code deploy (no schema change) | `git revert <commit>` + redeploy |
| Bad migration (schema change) | Restore from pre-deploy backup |
| Accidental row deletion | Restore to staging, extract rows, re-insert |
| Full database corruption | Restore from most recent daily backup |
| Pre-deploy backup missing | Use most recent daily backup (accept RPO gap) |

---

## 10. Responsibility Matrix

| Task | Responsible | Backup |
|------|-------------|--------|
| Run daily backup | Cron / DevOps | DBA |
| Verify backup weekly | DBA | IT Lead |
| Perform monthly restore drill | DBA or Senior Dev | IT Lead |
| Run pre-deploy backup | Developer doing deploy | Team Lead |
| Manage offsite backup | DevOps / Sysadmin | IT Lead |
| Define retention policy | Data Governance Officer | Registrar |
| Review audit_logs retention | Compliance / IT Lead | Registrar |
| Encrypt offsite backups | DevOps | DBA |

---

## 11. Quick Reference Commands

```bash
# Backup (interactive)
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=dci_sis DB_USER=dci_backup DB_PASS=secret
export BACKUP_DIR=./backups
bash scripts/backup_database.sh

# Restore to staging (NEVER run this on production without review)
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=dci_sis_staging DB_USER=dci_app DB_PASS=secret
RESTORE_CONFIRM=YES bash scripts/restore_database.sh backups/dci_sis_20260628_020000.sql.gz

# Verify table counts after restore
mysql -h127.0.0.1 -P3306 -udci_app -p dci_sis_staging \
  -e "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='dci_sis_staging' ORDER BY TABLE_ROWS DESC;"

# Check backup file integrity
gunzip -t backups/dci_sis_20260628_020000.sql.gz && echo "OK" || echo "CORRUPT"
```
