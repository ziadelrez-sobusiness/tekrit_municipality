-- Phase 7: Database Cleanup & Deduplication Migration
-- Date: 2026-06-12
-- Target: Tekrit Municipality Database

SET FOREIGN_KEY_CHECKS=0;

-- 1. Drop backup development projects table from Phase 5E
DROP TABLE IF EXISTS `_backup_development_projects_phase5e`;

-- 2. Permanently resolve the Arabic character-encoding bug on municipal_forms table
ALTER TABLE `municipal_forms` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Correct default value of status to proper Arabic 'مقدم'
ALTER TABLE `municipal_forms` 
    MODIFY COLUMN `status` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'مقدم';

SET FOREIGN_KEY_CHECKS=1;
