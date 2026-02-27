-- ============================================================================
-- MERCATINO DA VINCI - PRODUCTION UPGRADE RECAP
-- ============================================================================
-- Consolidated SQL script with ALL schema changes applied to staging.
-- Run this on the production database to bring it up to date.
--
-- Generated: 2026-02-24  |  Updated: 2026-02-27 (MySQL 5.7 compatibility)
-- Covers migrations from 2025-06 through 2026-02
--
-- COMPATIBILITY: MySQL 5.7+  (does NOT use MariaDB-only IF NOT EXISTS on
--   ALTER TABLE ADD COLUMN / ADD INDEX — uses information_schema checks)
--
-- HOW TO RUN IN phpMyAdmin:
--   Paste the entire script into the SQL tab and execute.
--   phpMyAdmin handles DELIMITER changes automatically.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;


-- ╔═══════════════════════════════════════════════════════════════════════════╗
-- ║  HELPER STORED PROCEDURES (temporary — dropped at end of script)        ║
-- ╚═══════════════════════════════════════════════════════════════════════════╝

DELIMITER //

DROP PROCEDURE IF EXISTS _add_col //
CREATE PROCEDURE _add_col(IN p_tbl VARCHAR(64), IN p_col VARCHAR(64), IN p_def TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tbl AND COLUMN_NAME = p_col
  ) THEN
    SET @_ddl = CONCAT('ALTER TABLE `', p_tbl, '` ADD COLUMN ', p_def);
    PREPARE _stmt FROM @_ddl;
    EXECUTE _stmt;
    DEALLOCATE PREPARE _stmt;
  END IF;
END //

DROP PROCEDURE IF EXISTS _add_idx //
CREATE PROCEDURE _add_idx(IN p_tbl VARCHAR(64), IN p_idx VARCHAR(64), IN p_def TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tbl AND INDEX_NAME = p_idx
  ) THEN
    SET @_ddl = CONCAT('ALTER TABLE `', p_tbl, '` ADD INDEX ', p_def);
    PREPARE _stmt FROM @_ddl;
    EXECUTE _stmt;
    DEALLOCATE PREPARE _stmt;
  END IF;
END //

DROP PROCEDURE IF EXISTS _add_fk //
CREATE PROCEDURE _add_fk(IN p_tbl VARCHAR(64), IN p_fk VARCHAR(64), IN p_def TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = p_tbl AND CONSTRAINT_NAME = p_fk
  ) THEN
    SET @_ddl = CONCAT('ALTER TABLE `', p_tbl, '` ADD CONSTRAINT ', p_def);
    PREPARE _stmt FROM @_ddl;
    EXECUTE _stmt;
    DEALLOCATE PREPARE _stmt;
  END IF;
END //

DELIMITER ;


-- ────────────────────────────────────────────────────────────────────────────
-- 1. PRODUCT ENHANCEMENTS (2025-06-19)
--    Source: 20250619_products.sql
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('product', 'nota_volumi',    '`nota_volumi` VARCHAR(50) DEFAULT '''' AFTER `editore`');
CALL _add_col('product', 'fl_esaurimento', '`fl_esaurimento` INT DEFAULT 0 AFTER `nota_volumi`');


-- ────────────────────────────────────────────────────────────────────────────
-- 2. NEWS & DOWNLOADS TABLES (2025)
--    Source: tab_news_download.sql
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_published` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `user`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `downloads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(255) NOT NULL,
  `filesize` INT,
  `filetype` VARCHAR(100),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_published` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `user`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `news_downloads` (
  `news_id` INT NOT NULL,
  `download_id` INT NOT NULL,
  PRIMARY KEY (`news_id`, `download_id`),
  FOREIGN KEY (`news_id`) REFERENCES `news`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`download_id`) REFERENCES `downloads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ────────────────────────────────────────────────────────────────────────────
-- 3. NEWS: ADD is_pinned COLUMN (was pending on staging, now included)
--    Source: new — required by NewsManager::togglePin()
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('news', 'is_pinned', '`is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_published`');


-- ────────────────────────────────────────────────────────────────────────────
-- 4. GDPR CONSENT & DELETION FIELDS ON USER (2026-01-17)
--    Source: 202601170001_gdpr_fields.sql
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('user', 'privacy_consent',          '`privacy_consent` TINYINT(1) DEFAULT 0 AFTER `profile_id`');
CALL _add_col('user', 'privacy_consent_date',     '`privacy_consent_date` DATETIME NULL AFTER `privacy_consent`');
CALL _add_col('user', 'newsletter_consent',       '`newsletter_consent` TINYINT(1) DEFAULT 0 AFTER `privacy_consent_date`');
CALL _add_col('user', 'newsletter_consent_date',  '`newsletter_consent_date` DATETIME NULL AFTER `newsletter_consent`');
CALL _add_col('user', 'deletion_requested',       '`deletion_requested` TINYINT(1) DEFAULT 0 AFTER `newsletter_consent_date`');
CALL _add_col('user', 'deletion_requested_date',  '`deletion_requested_date` DATETIME NULL AFTER `deletion_requested`');

-- Grandfather existing users as having consented
UPDATE `user` SET privacy_consent = 1, privacy_consent_date = NOW()
  WHERE privacy_consent = 0;

-- Index for cleanup scripts
CALL _add_idx('user', 'idx_deletion_requested', '`idx_deletion_requested` (`deletion_requested`)');


-- ────────────────────────────────────────────────────────────────────────────
-- 5. IBAN ENCRYPTED STORAGE ON USER (2026-01-17)
--    Source: 202601170002_iban_field.sql
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('user', 'iban',            '`iban` VARCHAR(150) NULL AFTER `deletion_requested_date`');
CALL _add_col('user', 'iban_updated_at', '`iban_updated_at` DATETIME NULL AFTER `iban`');


-- ────────────────────────────────────────────────────────────────────────────
-- 6. PASSWORD COLUMN — WIDEN FOR BCRYPT/ARGON2 (2026-01-17)
--    Source: 202601170003_password_column.sql
--    NOTE: Skipped. Column is already VARCHAR(255) on both production and
--          staging. The original migration also set NOT NULL, but that was
--          never actually applied on staging (column is still nullable).
--          Forcing NOT NULL here could fail if any user has a NULL password.
-- ────────────────────────────────────────────────────────────────────────────
-- ALTER TABLE `user` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;


-- ────────────────────────────────────────────────────────────────────────────
-- 7. IBAN OWNER NAME ON USER (2026-01-19)
--    Source: 202601190001_iban_owner_name.sql
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('user', 'iban_owner_name', '`iban_owner_name` VARCHAR(100) NULL AFTER `iban_updated_at`');


-- ────────────────────────────────────────────────────────────────────────────
-- 8. STUDENT INFO ON USER (new — was missing from migrations)
--    Required by: UserManager::saveStudentInfo(), register()
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('user', 'student_first_name', '`student_first_name` VARCHAR(100) NULL AFTER `iban_owner_name`');
CALL _add_col('user', 'student_last_name',  '`student_last_name` VARCHAR(100) NULL AFTER `student_first_name`');
CALL _add_col('user', 'student_class',      '`student_class` VARCHAR(3) NULL AFTER `student_last_name`');


-- ────────────────────────────────────────────────────────────────────────────
-- 9. SALES TRANSACTION TABLES (2026-01-19)
--    Source: 202601190002_sales_transaction.sql
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sales_transaction` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `payment_method` ENUM('cash', 'POS', 'satispay', 'paypal') NOT NULL,
  `description` VARCHAR(255) NULL COMMENT 'Customer name or free note',
  `operator_id` INT(11) NULL COMMENT 'User ID of the operator who registered the sale',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_payment_method` (`payment_method`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_operator_id` (`operator_id`),
  CONSTRAINT `fk_sales_transaction_operator` FOREIGN KEY (`operator_id`) REFERENCES `user`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_transaction_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sales_transaction_id` INT(11) NOT NULL,
  `order_item_id` INT(11) NOT NULL COMMENT 'Reference to order_item.id',
  `price` DECIMAL(8,2) NOT NULL COMMENT 'Sale price including markup',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sales_transaction_id` (`sales_transaction_id`),
  INDEX `idx_order_item_id` (`order_item_id`),
  CONSTRAINT `fk_sales_item_transaction` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transaction`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_item_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_item`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────────────────────
-- 10. SELLER REFUND TABLES (2026-01-20)
--     Source: 202601200001_seller_refund.sql
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `seller_refund` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Reference to user table - the seller',
  `year` SMALLINT(4) NOT NULL COMMENT 'Calendar year (e.g., 2026)',
  `payment_preference` ENUM('cash', 'wire_transfer') NULL COMMENT 'Seller preference for payment method',
  `preference_set_at` DATETIME NULL COMMENT 'When the preference was set via landing page',
  `preference_token` VARCHAR(64) NULL COMMENT 'Secure token for landing page access',
  `preference_token_expires` DATETIME NULL COMMENT 'Token expiration date',
  `amount_owed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount owed to seller',
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount already paid',
  `payment_date` DATE NULL COMMENT 'Date of last/main payment',
  `status` ENUM('pending', 'partial', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `comments` TEXT NULL COMMENT 'Admin notes about this refund',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_year` (`user_id`, `year`),
  INDEX `idx_year` (`year`),
  INDEX `idx_status` (`status`),
  INDEX `idx_payment_preference` (`payment_preference`),
  INDEX `idx_preference_token` (`preference_token`),
  CONSTRAINT `fk_seller_refund_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seller_refund_payment` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `seller_refund_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount of this payment',
  `payment_method` ENUM('cash', 'wire_transfer') NOT NULL,
  `payment_date` DATE NOT NULL,
  `reference` VARCHAR(100) NULL COMMENT 'Bank transfer reference or receipt number',
  `notes` VARCHAR(255) NULL,
  `operator_id` INT(11) NULL COMMENT 'Admin user who recorded this payment',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_seller_refund_id` (`seller_refund_id`),
  INDEX `idx_payment_date` (`payment_date`),
  CONSTRAINT `fk_payment_seller_refund` FOREIGN KEY (`seller_refund_id`) REFERENCES `seller_refund`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_operator` FOREIGN KEY (`operator_id`) REFERENCES `user`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ────────────────────────────────────────────────────────────────────────────
-- 11. SELLER REFUND — NEWSLETTER TRACKING (2026-01-20)
--     Source: 202601200002_seller_refund_newsletter.sql
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('seller_refund', 'newsletter_sent',
  '`newsletter_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Whether preference request newsletter was sent'' AFTER `preference_token_expires`');
CALL _add_col('seller_refund', 'newsletter_sent_at',
  '`newsletter_sent_at` DATETIME NULL COMMENT ''When the newsletter was sent'' AFTER `newsletter_sent`');
CALL _add_col('seller_refund', 'newsletter_sent_by',
  '`newsletter_sent_by` INT(11) NULL COMMENT ''Admin user who sent the newsletter'' AFTER `newsletter_sent_at`');

CALL _add_idx('seller_refund', 'idx_newsletter_sent', '`idx_newsletter_sent` (`newsletter_sent`)');

CALL _add_fk('seller_refund', 'fk_seller_refund_newsletter_sender',
  '`fk_seller_refund_newsletter_sender` FOREIGN KEY (`newsletter_sent_by`) REFERENCES `user`(`id`) ON DELETE SET NULL');


-- ────────────────────────────────────────────────────────────────────────────
-- 12. SELLER REFUND — DONATION / ENVELOPE / SELLER NOTES (2026-01-20)
--     Source: 202601200003_seller_refund_extra_fields.sql
-- ────────────────────────────────────────────────────────────────────────────
CALL _add_col('seller_refund', 'donate_unsold',
  '`donate_unsold` TINYINT(1) NULL COMMENT ''Whether seller wants to donate unsold books'' AFTER `comments`');
CALL _add_col('seller_refund', 'donate_unsold_set_at',
  '`donate_unsold_set_at` DATETIME NULL COMMENT ''When the donation preference was set'' AFTER `donate_unsold`');
CALL _add_col('seller_refund', 'seller_notes',
  '`seller_notes` TEXT NULL COMMENT ''Notes from seller to shop managers'' AFTER `donate_unsold_set_at`');
CALL _add_col('seller_refund', 'envelope_prepared',
  '`envelope_prepared` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Whether cash envelope has been prepared'' AFTER `seller_notes`');


-- ────────────────────────────────────────────────────────────────────────────
-- 13. SITE SETTINGS TABLE (2026-02-27)
--     Source: 202602270001_site_settings.sql
-- ────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('bookshop_seller_deduction', '1.00', 'Euro deducted from seller price (subtracted from 50% of cover)'),
  ('bookshop_buyer_markup', '1.00', 'Euro added to buyer price (added to 50% of cover)');


-- ╔═══════════════════════════════════════════════════════════════════════════╗
-- ║  CLEANUP — drop helper procedures                                       ║
-- ╚═══════════════════════════════════════════════════════════════════════════╝
DROP PROCEDURE IF EXISTS _add_col;
DROP PROCEDURE IF EXISTS _add_idx;
DROP PROCEDURE IF EXISTS _add_fk;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF PRODUCTION UPGRADE RECAP
-- ============================================================================
