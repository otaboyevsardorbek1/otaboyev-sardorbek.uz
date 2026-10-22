-- Add Telegram bot webhook guards to an existing database.
-- This migration is idempotent; it can be run more than once.
--
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-bot-guards.sql

SET NAMES utf8mb4;

-- Telegram retries webhook updates; remember each update_id once.
CREATE TABLE IF NOT EXISTS bot_updates_seen (
  update_id BIGINT UNSIGNED NOT NULL,
  seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (update_id),
  KEY idx_bot_updates_seen_at (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-admin, per-command limit for /send, /inbox and /read.
CREATE TABLE IF NOT EXISTS bot_rate_limit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  command VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bot_rate_admin_cmd_time (admin_id, command, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
