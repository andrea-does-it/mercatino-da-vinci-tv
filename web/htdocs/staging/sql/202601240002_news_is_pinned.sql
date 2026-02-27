-- Add is_pinned column to news table
-- Migration Date: 2026-01-24
-- Required by: NewsManager::togglePin()

ALTER TABLE `news`
  ADD COLUMN IF NOT EXISTS `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_published`;
