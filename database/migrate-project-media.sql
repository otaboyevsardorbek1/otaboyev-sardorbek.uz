-- Add an optional project cover image to an existing database.
-- Idempotent: safe to import on every deploy, even after the column exists.
--
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-project-media.sql

SET NAMES utf8mb4;

SET @has_image_url := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'projects'
     AND COLUMN_NAME = 'image_url'
);
SET @sql := IF(@has_image_url = 0,
  'ALTER TABLE projects ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER demo_url',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
