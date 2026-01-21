-- Add extra fields to seller_refund table
-- Migration Date: 2026-01-20

-- Add field for donation preference (donate unsold books)
ALTER TABLE `seller_refund`
ADD COLUMN `donate_unsold` TINYINT(1) NULL COMMENT 'Whether seller wants to donate unsold books to the bookshop' AFTER `comments`,
ADD COLUMN `donate_unsold_set_at` DATETIME NULL COMMENT 'When the donation preference was set' AFTER `donate_unsold`;

-- Add field for seller notes (visible to shop managers)
ALTER TABLE `seller_refund`
ADD COLUMN `seller_notes` TEXT NULL COMMENT 'Notes from seller to shop managers' AFTER `donate_unsold_set_at`;

-- Add field for envelope prepared (for cash payments)
ALTER TABLE `seller_refund`
ADD COLUMN `envelope_prepared` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether cash envelope has been prepared' AFTER `seller_notes`;
