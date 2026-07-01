-- =============================================================================
-- Migration: 0004_courses_add_category.sql
-- Purpose:   Add courses.category (nullable, additive) for Dhammachai
--            Institute curriculum structure
-- Date:      2026-06-29
-- Type:      ADDITIVE ONLY — new nullable column, no existing data touched
-- Safe:      Idempotent via INFORMATION_SCHEMA guard (SAFE TO RUN MULTIPLE TIMES)
-- Rollback:  See rollback section at bottom
--
-- Allowed values for courses.category (validated in application code,
-- not a DB-level ENUM/CHECK — keeps the column additive/flexible):
--   ips           — IPS / Foundation courses, Year 1
--   concentration — Program concentration courses, Year 2-4
--   general_ed    — General Education & Distribution
--   elective      — Free electives
--   other         — Uncategorized / other
--
-- DOES NOT modify, drop, or rename any existing column
-- DOES NOT backfill existing rows — category stays NULL until set explicitly
-- courses.status / courses.program_id / all other columns are NOT changed
-- =============================================================================

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'courses'
     AND COLUMN_NAME  = 'category') = 0,
  'ALTER TABLE `courses` ADD COLUMN `category` varchar(50) DEFAULT NULL COMMENT \'Course category: ips | concentration | general_ed | elective | other\' AFTER `program_id`',
  'SELECT 1 /* courses.category already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- =============================================================================
-- ROLLBACK SQL (copy/paste individually to undo)
-- =============================================================================
-- ALTER TABLE `courses` DROP COLUMN `category`;
