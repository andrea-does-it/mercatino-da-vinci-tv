-- Soft-delete support for sales_transaction refunds
-- Instead of deleting refunded transactions/items, mark them with refund metadata

-- Header: full transaction refund tracking
ALTER TABLE `sales_transaction`
  ADD COLUMN `refunded_at` DATETIME NULL DEFAULT NULL COMMENT 'When the transaction was fully refunded (NULL = active)',
  ADD COLUMN `refunded_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID of who performed the refund',
  ADD COLUMN `refund_notes` TEXT NULL COMMENT 'Reason for the refund';

-- Detail: individual item refund tracking (partial refunds)
ALTER TABLE `sales_transaction_item`
  ADD COLUMN `refunded_at` DATETIME NULL DEFAULT NULL COMMENT 'When this item was refunded (NULL = active)',
  ADD COLUMN `refunded_by` INT(11) NULL DEFAULT NULL COMMENT 'User ID of who refunded this item',
  ADD COLUMN `refund_notes` TEXT NULL COMMENT 'Reason for the item refund';
