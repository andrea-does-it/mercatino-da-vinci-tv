-- Add IBAN field to user table for payment refunds
-- Migration Date: 2026-01-17

-- Add IBAN field with encrypted storage
-- Encrypted IBAN (AES-256-GCM) requires more space than plain text:
-- Base64(IV[12] + Tag[16] + Ciphertext[~34]) ≈ 100 characters
ALTER TABLE `user`
ADD COLUMN `iban` VARCHAR(150) NULL AFTER `deletion_requested_date`,
ADD COLUMN `iban_updated_at` DATETIME NULL AFTER `iban`;

-- Note: IBAN is stored encrypted using AES-256-GCM for GDPR compliance
-- Plain IBAN max length is 34 chars, encrypted+base64 needs ~100 chars
