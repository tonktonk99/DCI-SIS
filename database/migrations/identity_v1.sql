-- =============================================================
-- Migration: identity_v1.sql
-- Phase Identity-0: Add identity model tables (ADDITIVE ONLY)
-- Database: dci_sis
-- Date: 2026-06-26
--
-- SAFE TO RUN MULTIPLE TIMES (uses CREATE TABLE IF NOT EXISTS)
-- DOES NOT modify, drop, or rename any existing columns/tables
-- Run order: persons → student_programs → user_roles → identity_links
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- 1. persons
--    ตัวตนถาวรของมนุษย์หนึ่งคน
--    person_no = non-semantic unique ID (DCI00000001, DCI00000002, ...)
--    ไม่ฝังปี คณะ หรือหลักสูตร
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `persons` (
  `id`            bigint        NOT NULL AUTO_INCREMENT,
  `person_no`     varchar(20)   NOT NULL COMMENT 'Non-semantic permanent ID e.g. DCI00000001',
  `first_name`    varchar(100)  NOT NULL,
  `last_name`     varchar(100)  NOT NULL,
  `first_name_en` varchar(100)  DEFAULT NULL,
  `last_name_en`  varchar(100)  DEFAULT NULL,
  `birth_date`    date          DEFAULT NULL,
  `primary_email` varchar(255)  DEFAULT NULL,
  `phone`         varchar(50)   DEFAULT NULL,
  `status`        varchar(50)   NOT NULL DEFAULT 'active',
  `created_at`    timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_persons_person_no`  (`person_no`),
  KEY `idx_persons_email`            (`primary_email`),
  KEY `idx_persons_name`             (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------------------------------------------
-- 2. student_programs
--    การเป็นนักศึกษาในแต่ละหลักสูตร
--    1 person มีได้หลาย records ถ้าเรียนหลายหลักสูตร
--    student_no = รหัสแสดงผลของการเรียนใน program นั้น ≠ ตัวตนหลัก
--
--    academic_status values:
--      applicant | active | leave | suspended | withdrawn
--      graduated | dismissed | alumni
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_programs` (
  `id`                       bigint       NOT NULL AUTO_INCREMENT,
  `person_id`                bigint       NOT NULL,
  `student_no`               varchar(50)  NOT NULL COMMENT 'Display student ID for this program enrollment',
  `program_id`               int          DEFAULT NULL,
  `degree_level`             varchar(100) DEFAULT NULL,
  `admit_year`               varchar(20)  DEFAULT NULL,
  `class_year`               varchar(20)  DEFAULT NULL,
  `expected_graduation_year` varchar(20)  DEFAULT NULL,
  `actual_graduation_year`   varchar(20)  DEFAULT NULL,
  `academic_status`          varchar(50)  NOT NULL DEFAULT 'active',
  `is_primary`               tinyint(1)   NOT NULL DEFAULT '1',
  `started_at`               timestamp    NULL DEFAULT NULL,
  `ended_at`                 timestamp    NULL DEFAULT NULL,
  `created_at`               timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sp_student_no`  (`student_no`),
  KEY `idx_sp_person`            (`person_id`),
  KEY `idx_sp_program`           (`program_id`),
  KEY `idx_sp_status`            (`academic_status`),
  CONSTRAINT `fk_sp_person`  FOREIGN KEY (`person_id`)  REFERENCES `persons`   (`id`),
  CONSTRAINT `fk_sp_program` FOREIGN KEY (`program_id`) REFERENCES `programs`  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------------------------------------------
-- 3. user_roles
--    รองรับ 1 user มีหลาย role
--    scope_type/scope_id ใช้ระบุ context เช่น
--      scope_type='student_program', scope_id=student_programs.id
--
--    ยังไม่ backfill ใน Phase Identity-1 (รอ Phase 2)
--    users.role เดิมยังคงใช้งานได้ปกติ
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id`         bigint       NOT NULL AUTO_INCREMENT,
  `user_id`    int          NOT NULL,
  `role`       varchar(50)  NOT NULL,
  `scope_type` varchar(50)  DEFAULT NULL COMMENT 'e.g. student_program, section',
  `scope_id`   int          DEFAULT NULL COMMENT 'FK to table indicated by scope_type',
  `status`     varchar(50)  NOT NULL DEFAULT 'active',
  `starts_at`  timestamp    NULL DEFAULT NULL,
  `ends_at`    timestamp    NULL DEFAULT NULL,
  `created_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ur_user`   (`user_id`),
  KEY `idx_ur_role`   (`role`),
  KEY `idx_ur_scope`  (`scope_type`, `scope_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------------------------------------------
-- 4. identity_links
--    Mapping ข้อมูล legacy กับ persons
--    ใช้ป้องกัน backfill ซ้ำ และ trace กลับ
--
--    link_type values:
--      student | alumni | professor | staff | user | legacy
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `identity_links` (
  `id`           bigint       NOT NULL AUTO_INCREMENT,
  `person_id`    bigint       NOT NULL,
  `source_table` varchar(100) NOT NULL COMMENT 'e.g. students, staff',
  `source_id`    int          NOT NULL COMMENT 'PK from source_table',
  `source_code`  varchar(100) DEFAULT NULL COMMENT 'e.g. student_code, staff_code',
  `link_type`    varchar(50)  NOT NULL COMMENT 'student | alumni | professor | staff | user | legacy',
  `created_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_il_source`   (`source_table`, `source_id`),
  KEY `idx_il_person`         (`person_id`),
  KEY `idx_il_link_type`      (`link_type`),
  CONSTRAINT `fk_il_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
