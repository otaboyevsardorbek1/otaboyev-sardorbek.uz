-- Add the contact form inbox to an existing database.
-- This migration is idempotent; it can be run more than once.
--
-- Usage:
--   mysql -u USER -p DB_NAME < database/migrate-contact-messages.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id VARCHAR(80) NOT NULL,
  locale CHAR(2) DEFAULT NULL,
  name VARCHAR(160) DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  payload MEDIUMTEXT NOT NULL,
  ip_hash VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_messages_unread (is_read, created_at),
  KEY idx_contact_messages_created (created_at),
  KEY idx_contact_messages_ip (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing installations created before the per-IP flood guard need the
-- composite index too. Creating it separately keeps this file idempotent.
SET @has_ip_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'contact_messages'
     AND INDEX_NAME = 'idx_contact_messages_ip'
);
SET @sql := IF(@has_ip_index = 0,
  'ALTER TABLE contact_messages ADD KEY idx_contact_messages_ip (ip_hash, created_at)',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
