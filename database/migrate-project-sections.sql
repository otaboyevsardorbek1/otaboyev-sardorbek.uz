-- One-time migration for databases created before detail sections were added.
-- Fresh installs already get these columns from database/schema.sql.
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-project-sections.sql
-- Then re-import database/seed.sql to fill the new fields.

SET NAMES utf8mb4;

ALTER TABLE project_translations
  ADD COLUMN results TEXT DEFAULT NULL AFTER tech,
  ADD COLUMN stack_notes TEXT DEFAULT NULL AFTER results;
