-- =============================================================================
-- Migration: 0006_users_unique_username.sql
-- Purpose:   Add a database-level UNIQUE constraint on users.username.
--            Per the U1 User Management Audit, login-action.php and
--            admin/users.php both assume username uniqueness, but only
--            admin/users.php enforces it — at the application level only,
--            via a pre-check SELECT before INSERT. That is not race-safe
--            and has no backstop against any other insert path (seed
--            scripts, future code). This migration closes that gap at
--            the schema level.
-- Date:      2026-07-02
-- Type:      ADDITIVE ONLY — new index, no column type change, no data
--            modified, no rows deleted or merged
-- Safe:      Idempotent via INFORMATION_SCHEMA guard (SAFE TO RUN MULTIPLE TIMES)
-- Pre-check: Verified 0 duplicate usernames exist in the current dataset
--            (SELECT username, COUNT(*) FROM users GROUP BY username
--             HAVING COUNT(*) > 1; -> 0 rows) before writing this migration.
-- Rollback:  See rollback section at bottom
--
-- DOES NOT change users.username's column type or nullability
-- DOES NOT touch login-action.php's query pattern (still works unchanged —
-- LIMIT 1 becomes a formality once uniqueness is guaranteed, not a
-- requirement)
-- DOES NOT modify, merge, or delete any existing user row
-- =============================================================================

SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'users'
     AND INDEX_NAME   = 'uq_users_username') = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`)',
  'SELECT 1 /* uq_users_username already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- =============================================================================
-- ROLLBACK SQL (copy/paste individually to undo)
-- =============================================================================
-- ALTER TABLE `users` DROP INDEX `uq_users_username`;
