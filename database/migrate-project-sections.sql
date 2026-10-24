-- Add project detail sections (results, stack_notes) to an existing database.
-- Idempotent: safe to import on every deploy, even after the columns exist.
--
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-project-sections.sql

SET NAMES utf8mb4;

SET @has_results := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'project_translations'
     AND COLUMN_NAME = 'results'
);
SET @sql := IF(@has_results = 0,
  'ALTER TABLE project_translations ADD COLUMN results TEXT DEFAULT NULL AFTER tech',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_stack_notes := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'project_translations'
     AND COLUMN_NAME = 'stack_notes'
);
SET @sql := IF(@has_stack_notes = 0,
  'ALTER TABLE project_translations ADD COLUMN stack_notes TEXT DEFAULT NULL AFTER results',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
