-- Portfolio content schema (MySQL 5.7+ / MariaDB 10.2+)
-- Usage:
--   mysql -u USER -p DB_NAME < database/schema.sql
-- The database itself is created by the hosting panel; this file only creates tables.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS profiles (
  id TINYINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  github VARCHAR(190) NOT NULL,
  telegram VARCHAR(190) NOT NULL,
  telegram_handle VARCHAR(100) NOT NULL,
  avatar_url VARCHAR(255) DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_translations (
  profile_id TINYINT UNSIGNED NOT NULL,
  lang CHAR(2) NOT NULL,
  role VARCHAR(160) NOT NULL,
  bio TEXT NOT NULL,
  quick_text VARCHAR(255) NOT NULL,
  location VARCHAR(120) NOT NULL,
  PRIMARY KEY (profile_id, lang),
  CONSTRAINT fk_profile_translations_profile
    FOREIGN KEY (profile_id) REFERENCES profiles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  category VARCHAR(30) NOT NULL,
  repo_url VARCHAR(255) DEFAULT NULL,
  demo_url VARCHAR(255) DEFAULT NULL,
  image_url VARCHAR(255) DEFAULT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_projects_slug (slug),
  KEY idx_projects_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_translations (
  project_id SMALLINT UNSIGNED NOT NULL,
  lang CHAR(2) NOT NULL,
  tag VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  summary TEXT NOT NULL,
  description MEDIUMTEXT DEFAULT NULL,
  tech TEXT NOT NULL,
  results TEXT DEFAULT NULL,
  stack_notes TEXT DEFAULT NULL,
  PRIMARY KEY (project_id, lang),
  CONSTRAINT fk_project_translations_project
    FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Contact form inbox
-- ---------------------------------------------------------------------------

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

-- ---------------------------------------------------------------------------
-- Admin authentication and audit tables
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  telegram_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  display_name VARCHAR(120) DEFAULT NULL,
  telegram_username VARCHAR(64) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  role VARCHAR(20) NOT NULL DEFAULT 'admin',
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_users_telegram (telegram_id),
  KEY idx_admin_users_active (is_active),
  KEY idx_admin_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_challenges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  challenge_hash VARCHAR(64) NOT NULL,
  code_hash VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME DEFAULT NULL,
  ip_hash VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_challenges_hash (challenge_hash),
  KEY idx_admin_challenges_admin (admin_id, created_at),
  KEY idx_admin_challenges_expiry (expires_at),
  CONSTRAINT fk_admin_challenges_admin
    FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_sessions_hash (token_hash),
  KEY idx_admin_sessions_admin (admin_id),
  KEY idx_admin_sessions_expiry (expires_at),
  CONSTRAINT fk_admin_sessions_admin
    FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_auth_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(40) NOT NULL,
  telegram_id BIGINT UNSIGNED DEFAULT NULL,
  ip_hash VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_auth_events_type_time (event_type, created_at),
  KEY idx_admin_auth_events_telegram_time (telegram_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(48) NOT NULL,
  entity VARCHAR(32) DEFAULT NULL,
  entity_id VARCHAR(64) DEFAULT NULL,
  ip_hash VARCHAR(64) DEFAULT NULL,
  details TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_audit_admin_time (admin_id, created_at),
  KEY idx_admin_audit_action_time (action, created_at),
  CONSTRAINT fk_admin_audit_admin
    FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Telegram bot bridge guards: webhook retry idempotency and per-command limits.
CREATE TABLE IF NOT EXISTS bot_updates_seen (
  update_id BIGINT UNSIGNED NOT NULL,
  seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (update_id),
  KEY idx_bot_updates_seen_at (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bot_rate_limit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  command VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bot_rate_admin_cmd_time (admin_id, command, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
