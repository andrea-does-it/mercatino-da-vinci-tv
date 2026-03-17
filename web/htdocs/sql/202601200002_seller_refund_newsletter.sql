-- Add newsletter tracking fields to seller_refund table
-- Migration Date: 2026-01-20

ALTER TABLE `seller_refund`
ADD COLUMN `newsletter_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether preference request newsletter was sent' AFTER `preference_token_expires`,
ADD COLUMN `newsletter_sent_at` DATETIME NULL COMMENT 'When the newsletter was sent' AFTER `newsletter_sent`,
ADD COLUMN `newsletter_sent_by` INT(11) NULL COMMENT 'Admin user who sent the newsletter' AFTER `newsletter_sent_at`,
ADD INDEX `idx_newsletter_sent` (`newsletter_sent`);

-- Add foreign key for newsletter_sent_by
ALTER TABLE `seller_refund`
ADD CONSTRAINT `fk_seller_refund_newsletter_sender` FOREIGN KEY (`newsletter_sent_by`) REFERENCES `user`(`id`) ON DELETE SET NULL;
