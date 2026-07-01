-- =============================================================================
-- Migration: 0005_students_add_year_level.sql
-- Purpose:   Add students.year_level (nullable, additive) as the reliable
--            source of truth for a student's current academic year, per
--            the A4-Pre review gate decision.
-- Date:      2026-07-01
-- Type:      ADDITIVE ONLY — new nullable column, no existing data touched
-- Safe:      Idempotent via INFORMATION_SCHEMA guard (SAFE TO RUN MULTIPLE TIMES)
-- Rollback:  See rollback section at bottom
--
-- Allowed values for students.year_level (validated in application code,
-- not a DB-level ENUM/CHECK — keeps the column additive/flexible):
--   1 — Year 1 (IPS / Foundation)
--   2 — Year 2
--   3 — Year 3
--   4 — Year 4
--
-- alumni/graduated status is NOT encoded here — that remains on
-- students.study_status as before. year_level only represents current
-- academic year for students actively progressing through a program.
--
-- DOES NOT modify, drop, or rename any existing column
-- DOES NOT backfill existing rows — year_level stays NULL until set
-- explicitly by a separate backfill/registrar-entry task
-- No NOT NULL constraint, no default value (explicitly not defaulted to 1)
-- =============================================================================

-- 1. Add year_level column (only if not already present)
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'students'
     AND COLUMN_NAME  = 'year_level') = 0,
  'ALTER TABLE `students` ADD COLUMN `year_level` tinyint unsigned DEFAULT NULL COMMENT \'Current academic year: 1-4. NULL = not yet set. Not used for alumni/graduated (see study_status).\' AFTER `admission_year`',
  'SELECT 1 /* students.year_level already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 2. Add index on year_level (only if not already present)
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'students'
     AND INDEX_NAME   = 'idx_students_year_level') = 0,
  'ALTER TABLE `students` ADD KEY `idx_students_year_level` (`year_level`)',
  'SELECT 1 /* idx_students_year_level already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- =============================================================================
-- ROLLBACK SQL (copy/paste individually to undo)
-- =============================================================================
-- ALTER TABLE `students` DROP INDEX `idx_students_year_level`;
-- ALTER TABLE `students` DROP COLUMN `year_level`;
