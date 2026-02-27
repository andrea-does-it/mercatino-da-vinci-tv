-- Add student info fields to user table
-- Migration Date: 2026-01-24
-- Required by: UserManager::saveStudentInfo(), register()

ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `student_first_name` VARCHAR(100) NULL AFTER `iban_owner_name`,
  ADD COLUMN IF NOT EXISTS `student_last_name` VARCHAR(100) NULL AFTER `student_first_name`,
  ADD COLUMN IF NOT EXISTS `student_class` VARCHAR(3) NULL AFTER `student_last_name`;
