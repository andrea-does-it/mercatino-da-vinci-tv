-- Ensure password column is large enough for bcrypt hashes
-- Migration Date: 2026-01-17

-- Bcrypt hashes are 60 characters, but PASSWORD_DEFAULT may use different
-- algorithms in the future (like Argon2) which can be longer.
-- Using VARCHAR(255) for future compatibility.

ALTER TABLE `user` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;
