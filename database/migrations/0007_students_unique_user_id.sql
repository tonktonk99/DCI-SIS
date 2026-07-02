-- =============================================================================
-- Migration: 0007_students_unique_user_id.sql
-- Purpose:   Add a database-level UNIQUE constraint on students.user_id,
--            so one login (users.id) cannot be linked to more than one
--            student profile.
-- Date:      2026-07-02
-- Type:      ADDITIVE ONLY — new index, no column type change, no FK change,
--            no data modified
-- Safe:      Idempotent via INFORMATION_SCHEMA guard (SAFE TO RUN MULTIPLE TIMES)
-- Pre-check: Verified 0 duplicate non-NULL user_id values in students
--            (SELECT user_id, COUNT(*) FROM students WHERE user_id IS NOT NULL
--             GROUP BY user_id HAVING COUNT(*) > 1; -> 0 rows) before writing
--            this migration.
-- Rollback:  See rollback section at bottom
--
-- NOTE: staff.user_id is explicitly NOT touched by this migration.
-- A duplicate was found (user_id=3 linked to 2 staff rows) — see the U4
-- task report for details. Adding UNIQUE to staff.user_id is blocked
-- until that duplicate is resolved by a human-reviewed cleanup task;
-- doing so here would either fail outright (MySQL rejects UNIQUE
-- creation over existing duplicate values) or require this migration to
-- silently modify/merge/delete a row, which is explicitly forbidden.
--
-- A MySQL UNIQUE index permits multiple NULL values by design (NULL is
-- never considered equal to another NULL for uniqueness purposes), so
-- student profiles that are not yet linked to a login (user_id IS NULL)
-- remain fully supported — this only prevents the SAME non-NULL user_id
-- from appearing on more than one students row.
--
-- DOES NOT change students.user_id's column type or nullability
-- DOES NOT change the existing FK (students_ibfk_1 -> users.id)
-- DOES NOT modify, merge, or delete any existing student row
-- =============================================================================

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'students'
     AND INDEX_NAME   = 'uq_students_user_id') = 0,
  'ALTER TABLE `students` ADD UNIQUE KEY `uq_students_user_id` (`user_id`)',
  'SELECT 1 /* uq_students_user_id already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- =============================================================================
-- ROLLBACK SQL (copy/paste individually to undo)
-- =============================================================================
-- ALTER TABLE `students` DROP INDEX `uq_students_user_id`;
