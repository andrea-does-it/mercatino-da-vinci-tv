-- Create sales transaction tables for tracking sales
-- Migration Date: 2026-01-19

-- Main sales transaction table
CREATE TABLE `sales_transaction` (
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

-- Sales transaction items (detail table)
-- Links to order_item table to track which books were sold
CREATE TABLE `sales_transaction_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sales_transaction_id` INT(11) NOT NULL,
  `order_item_id` INT(11) NOT NULL COMMENT 'Reference to order_item.id - the book being sold',
  `price` DECIMAL(8,2) NOT NULL COMMENT 'Sale price including markup',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sales_transaction_id` (`sales_transaction_id`),
  INDEX `idx_order_item_id` (`order_item_id`),
  CONSTRAINT `fk_sales_item_transaction` FOREIGN KEY (`sales_transaction_id`) REFERENCES `sales_transaction`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sales_item_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_item`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
