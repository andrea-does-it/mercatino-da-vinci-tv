-- GDPR Compliance: Add consent and deletion fields to user table
-- Migration Date: 2026-01-17

-- Add privacy consent fields
ALTER TABLE `user`
ADD COLUMN `privacy_consent` TINYINT(1) DEFAULT 0 AFTER `profile_id`,
ADD COLUMN `privacy_consent_date` DATETIME NULL AFTER `privacy_consent`,
ADD COLUMN `newsletter_consent` TINYINT(1) DEFAULT 0 AFTER `privacy_consent_date`,
ADD COLUMN `newsletter_consent_date` DATETIME NULL AFTER `newsletter_consent`,
ADD COLUMN `deletion_requested` TINYINT(1) DEFAULT 0 AFTER `newsletter_consent_date`,
ADD COLUMN `deletion_requested_date` DATETIME NULL AFTER `deletion_requested`;

-- Set privacy consent to 1 for all existing users (grandfathered)
UPDATE `user` SET privacy_consent = 1, privacy_consent_date = NOW() WHERE privacy_consent = 0;

-- Add index for deletion requests (for cleanup scripts)
ALTER TABLE `user` ADD INDEX `idx_deletion_requested` (`deletion_requested`);
