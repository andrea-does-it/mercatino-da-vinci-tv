-- Add standing "donate unsold books" preference to user profile
-- This profile preference seeds the per-year seller_refund.donate_unsold flag.
-- NOTE: donate_books is NULLABLE with DEFAULT 0, matching the existing consent
-- columns (privacy_consent, newsletter_consent). DBManager->create() casts the
-- whole User object to an INSERT, so an unset property is sent as NULL; a NOT NULL
-- column would break user registration.
ALTER TABLE `user`
  ADD COLUMN `donate_books` TINYINT(1) DEFAULT 0 AFTER `student_class`,
  ADD COLUMN `donate_books_date` DATETIME NULL AFTER `donate_books`;
