-- Create seller refund tracking table for yearly seller payments
-- Migration Date: 2026-01-20

-- Table to track refund payments to book sellers per year
-- Each seller (user with pratica) can have one record per year
CREATE TABLE `seller_refund` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Reference to user table - the seller',
  `year` SMALLINT(4) NOT NULL COMMENT 'Calendar year (e.g., 2026)',
  `payment_preference` ENUM('cash', 'wire_transfer') NULL COMMENT 'Seller preference for payment method',
  `preference_set_at` DATETIME NULL COMMENT 'When the preference was set via landing page',
  `preference_token` VARCHAR(64) NULL COMMENT 'Secure token for landing page access',
  `preference_token_expires` DATETIME NULL COMMENT 'Token expiration date',
  `amount_owed` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amount owed to seller (calculated from sold books)',
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

-- Table to track individual payment transactions for partial payments
CREATE TABLE `seller_refund_payment` (
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
