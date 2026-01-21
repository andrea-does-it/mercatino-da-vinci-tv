-- Add IBAN owner name field to user table
-- Migration Date: 2026-01-19

-- The IBAN owner name stores the name of the bank account holder
-- This should match the person associated with the IBAN for payment verification
ALTER TABLE `user`
ADD COLUMN `iban_owner_name` VARCHAR(100) NULL AFTER `iban_updated_at`;
