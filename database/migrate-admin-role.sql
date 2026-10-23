-- Add admin role (super/admin) to an existing database.
-- Idempotent: safe to import on every deploy.
--
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-admin-role.sql
--
-- After running, promote the owner to super ONCE:
--   mysql -u USER -p DB_NAME -e "UPDATE admin_users SET role='super' WHERE telegram_id='<OWNER_ID>';"
--
-- When the table has no active super admin yet, the earliest active admin is
-- promoted automatically so the panel cannot lock itself out. The bootstrap
-- safety net in backend/admin.php:admin_find_or_bootstrap_user() promotes the
-- configured telegram_id on first login when no active super admin exists.

SET NAMES utf8mb4;

SET @has_role := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'admin_users'
     AND COLUMN_NAME = 'role'
);
SET @sql := IF(@has_role = 0,
  'ALTER TABLE admin_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT ''admin'' AFTER is_active',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_role_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'admin_users'
     AND INDEX_NAME = 'idx_admin_users_role'
);
SET @sql := IF(@has_role_index = 0,
  'ALTER TABLE admin_users ADD KEY idx_admin_users_role (role)',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Safety net: promote the earliest active admin only when no active super
-- admin exists. Values are captured in user variables first so MySQL 5.7 does
-- not raise errno 1093 ("can't specify target table for update in FROM
-- clause") and the statement stays safe on MariaDB 10.2+.
SET @has_active_super := (
  SELECT COUNT(*) FROM admin_users WHERE is_active = 1 AND role = 'super'
);
SET @first_active_admin := (
  SELECT MIN(id) FROM admin_users WHERE is_active = 1
);
UPDATE admin_users
   SET role = 'super'
 WHERE id = @first_active_admin
   AND @has_active_super = 0;
