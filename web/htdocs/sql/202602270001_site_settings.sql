-- Create site_settings table for configurable parameters
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookshop price markup parameters
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('bookshop_seller_deduction', '1.00', 'Euro deducted from seller price (subtracted from 50% of cover)'),
  ('bookshop_buyer_markup', '1.00', 'Euro added to buyer price (added to 50% of cover)');
