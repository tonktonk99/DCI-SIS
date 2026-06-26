-- =============================================================
-- Migration: identity_v2.sql
-- Phase Identity-3: Add users.person_id (nullable, additive)
-- Database: dci_sis
-- Date: 2026-06-26
--
-- SAFE TO RUN MULTIPLE TIMES (conditional via INFORMATION_SCHEMA)
-- DOES NOT modify existing columns or break existing auth flow
-- users.role is NOT changed
-- =============================================================

-- 1. Add person_id column to users (only if not already present)
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'users'
     AND COLUMN_NAME  = 'person_id') = 0,
  'ALTER TABLE `users` ADD COLUMN `person_id` bigint DEFAULT NULL COMMENT \'FK to persons.id — populated by backfill_user_person_id.php\' AFTER `created_at`',
  'SELECT 1 /* users.person_id already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 2. Add index on users.person_id (only if not already present)
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'users'
     AND INDEX_NAME   = 'idx_users_person_id') = 0,
  'ALTER TABLE `users` ADD KEY `idx_users_person_id` (`person_id`)',
  'SELECT 1 /* idx_users_person_id already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- 3. Add FK users.person_id → persons.id (only if not already present)
--    NULL values are allowed (not all users have persons yet)
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA   = DATABASE()
     AND TABLE_NAME     = 'users'
     AND CONSTRAINT_NAME = 'fk_users_person'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`)',
  'SELECT 1 /* fk_users_person already exists */'
));
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
