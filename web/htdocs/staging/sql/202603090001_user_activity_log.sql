-- Migration: Create user_activity_log table
-- Date: 2026-03-09
-- GDPR-compliant activity log. Retention: 12 months.
-- Users may export/delete their own data via the privacy page.
-- IP addresses are pseudonymised (SHA-256 hash) per GDPR Art. 25.

CREATE TABLE IF NOT EXISTS `user_activity_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL COMMENT 'NULL for pre-authentication events (e.g. failed login)',
  `action` VARCHAR(100) NOT NULL COMMENT 'Event type identifier (login, logout, register, etc.)',
  `detail` VARCHAR(500) NULL COMMENT 'Non-personal contextual info only (e.g. page name, order id)',
  `ip_hash` CHAR(64) NULL COMMENT 'SHA-256 of client IP — pseudonymised per GDPR Art. 25',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='GDPR-compliant activity log. Retention: 12 months.';
