-- =============================================================================
-- Migration: performance_indexes_v1.sql
-- Purpose:   Add performance indexes for SIS hot paths
-- Date:      2026-06-26
-- Type:      ADDITIVE ONLY — no DROP TABLE, DROP COLUMN, RENAME, or data changes
-- Safe:      Idempotent via INFORMATION_SCHEMA pre-check on every index
-- Rollback:  See rollback section at bottom of this file
-- Targets:   MySQL 8.0+ (tested on 8.0.44)
--
-- Affected tables: enrollments, semesters, sections, grade_scores,
--                  final_grades, audit_logs, students
-- =============================================================================
-- Pre-apply checklist:
--   1. Back up database:
--      mysqldump -u root -p dci_sis > dci_sis_pre_perf_index.sql
--   2. Verify no duplicate student_code:
--      SELECT student_code, COUNT(*) FROM students GROUP BY student_code HAVING COUNT(*) > 1;
--   3. Run SHOW INDEX on each table to confirm no conflicts:
--      SHOW INDEX FROM enrollments; SHOW INDEX FROM final_grades; etc.
--   4. Apply on local/staging first, verify app works, then apply production
-- =============================================================================

-- Helper macro: add index only if it doesn't already exist
-- Pattern: SET @s = (SELECT IF(...STATISTICS count = 0, 'ALTER TABLE...', 'SELECT 1'));
--          PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =============================================================================
-- GROUP 1: enrollments
-- Hot paths:
--   - Cart query:     WHERE student_id = ? AND status = 'enrolled'
--   - Conflict check: WHERE student_id = ? AND status = 'enrolled'
--   - Credit sum:     WHERE student_id = ? AND status = 'enrolled'
--   - Roster load:    WHERE section_id  = ? AND status = 'enrolled'
--   - Dup check:      WHERE student_id = ? AND section_id = ?
-- =============================================================================

-- 1a. (student_id, status) — most critical: eliminates status post-filter on student scans
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollments'
     AND INDEX_NAME = 'idx_en_student_status') = 0,
  'ALTER TABLE `enrollments` ADD INDEX `idx_en_student_status` (`student_id`, `status`)',
  'SELECT 1 -- idx_en_student_status already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 1b. (section_id, status) — roster loading in gradebook
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollments'
     AND INDEX_NAME = 'idx_en_section_status') = 0,
  'ALTER TABLE `enrollments` ADD INDEX `idx_en_section_status` (`section_id`, `status`)',
  'SELECT 1 -- idx_en_section_status already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 1c. (student_id, section_id) — duplicate enrollment check before INSERT
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollments'
     AND INDEX_NAME = 'idx_en_student_section') = 0,
  'ALTER TABLE `enrollments` ADD INDEX `idx_en_student_section` (`student_id`, `section_id`)',
  'SELECT 1 -- idx_en_student_section already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- GROUP 2: semesters
-- Hot path: every student page load → SELECT * FROM semesters WHERE is_current = 1 LIMIT 1
-- =============================================================================

-- 2a. (is_current) — tiny table now, critical at scale (all requests hit this)
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semesters'
     AND INDEX_NAME = 'idx_semesters_is_current') = 0,
  'ALTER TABLE `semesters` ADD INDEX `idx_semesters_is_current` (`is_current`)',
  'SELECT 1 -- idx_semesters_is_current already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- GROUP 3: sections
-- Hot path: enrollment catalog → WHERE status = 'active' AND semester_id = ?
-- =============================================================================

-- 3a. (semester_id, status) — covers semester_id prefix + eliminates status scan
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sections'
     AND INDEX_NAME = 'idx_sections_semester_status') = 0,
  'ALTER TABLE `sections` ADD INDEX `idx_sections_semester_status` (`semester_id`, `status`)',
  'SELECT 1 -- idx_sections_semester_status already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- GROUP 4: grade_scores
-- Hot path: gradebook save_scores → WHERE grade_item_id = ? AND student_id = ? LIMIT 1
--           (called once per student per grade item on every save — N*M calls)
-- =============================================================================

-- 4a. (grade_item_id, student_id) — turns N*M individual lookups into indexed seeks
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grade_scores'
     AND INDEX_NAME = 'idx_gs_item_student') = 0,
  'ALTER TABLE `grade_scores` ADD INDEX `idx_gs_item_student` (`grade_item_id`, `student_id`)',
  'SELECT 1 -- idx_gs_item_student already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- GROUP 5: final_grades
-- Hot paths:
--   - Transcript:        WHERE student_id = ? AND status IN ('released','locked')
--   - Registrar grades:  WHERE status = ?  ORDER BY id DESC
--   - Registrar transcripts: GROUP BY students.id with final_grades JOIN
-- =============================================================================

-- 5a. (student_id, status) — transcript query: avoids filtering all student grades for status
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'final_grades'
     AND INDEX_NAME = 'idx_fg_student_status') = 0,
  'ALTER TABLE `final_grades` ADD INDEX `idx_fg_student_status` (`student_id`, `status`)',
  'SELECT 1 -- idx_fg_student_status already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 5b. (status) — registrar grades page: WHERE final_grades.status = ? ORDER BY id DESC
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'final_grades'
     AND INDEX_NAME = 'idx_fg_status') = 0,
  'ALTER TABLE `final_grades` ADD INDEX `idx_fg_status` (`status`)',
  'SELECT 1 -- idx_fg_status already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- GROUP 6: audit_logs
-- Hot paths:
--   - Main listing: ORDER BY id DESC LIMIT 300 (PK already fast)
--   - Today count:  WHERE DATE(created_at) = CURDATE()
--   - Top actions:  GROUP BY action ORDER BY total DESC
--   - user_id filter already has index (user_id)
-- Note: action LIKE '%keyword%' cannot use btree index (leading wildcard)
--       idx_al_action helps GROUP BY action but not LIKE '%..%' filter
-- =============================================================================

-- 6a. (created_at) — today count + potential future range queries
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
     AND INDEX_NAME = 'idx_al_created_at') = 0,
  'ALTER TABLE `audit_logs` ADD INDEX `idx_al_created_at` (`created_at`)',
  'SELECT 1 -- idx_al_created_at already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- 6b. (action) — GROUP BY action in top-actions summary widget
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
     AND INDEX_NAME = 'idx_al_action') = 0,
  'ALTER TABLE `audit_logs` ADD INDEX `idx_al_action` (`action`)',
  'SELECT 1 -- idx_al_action already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- GROUP 7: students
-- Hot path: registrar duplicate check → WHERE student_code = ? LIMIT 1
-- Pre-verified: SELECT student_code, COUNT(*) FROM students GROUP BY student_code
--               HAVING COUNT(*) > 1  → returned 0 rows (no duplicates)
-- Safe to add UNIQUE index.
-- =============================================================================

-- 7a. UNIQUE(student_code) — dup check on add_student + data integrity guarantee
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students'
     AND INDEX_NAME = 'uq_students_student_code') = 0,
  'ALTER TABLE `students` ADD UNIQUE INDEX `uq_students_student_code` (`student_code`)',
  'SELECT 1 -- uq_students_student_code already exists, skipping'
));
PREPARE _s FROM @s; EXECUTE _s; DEALLOCATE PREPARE _s;

-- =============================================================================
-- ROLLBACK SQL (run these to undo — copy/paste individually)
-- =============================================================================
-- DROP INDEX `idx_en_student_status`    ON `enrollments`;
-- DROP INDEX `idx_en_section_status`    ON `enrollments`;
-- DROP INDEX `idx_en_student_section`   ON `enrollments`;
-- DROP INDEX `idx_semesters_is_current` ON `semesters`;
-- DROP INDEX `idx_sections_semester_status` ON `sections`;
-- DROP INDEX `idx_gs_item_student`      ON `grade_scores`;
-- DROP INDEX `idx_fg_student_status`    ON `final_grades`;
-- DROP INDEX `idx_fg_status`            ON `final_grades`;
-- DROP INDEX `idx_al_created_at`        ON `audit_logs`;
-- DROP INDEX `idx_al_action`            ON `audit_logs`;
-- DROP INDEX `uq_students_student_code` ON `students`;
