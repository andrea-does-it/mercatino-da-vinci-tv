-- Add standing "donate unsold books" preference to user profile
-- This profile preference seeds the per-year seller_refund.donate_unsold flag.
ALTER TABLE `user`
  ADD COLUMN `donate_books` TINYINT(1) NOT NULL DEFAULT 0 AFTER `student_class`,
  ADD COLUMN `donate_books_date` DATETIME NULL AFTER `donate_books`;
