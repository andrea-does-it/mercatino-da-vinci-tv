-- Add refund_notes column to order_item
-- Stores the reason/note when an item is refunded (status goes back from 'venduto' to 'vendere')

ALTER TABLE `order_item`
  ADD COLUMN IF NOT EXISTS `refund_notes` TEXT NULL COMMENT 'Note entered when item was refunded';
