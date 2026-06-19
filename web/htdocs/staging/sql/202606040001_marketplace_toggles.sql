-- Marketplace on/off toggles: disable new registrations and add-to-cart
-- until the list of books available for sale is ready.
-- 1 = enabled (default), 0 = disabled. Edit via Admin -> Site Utils -> Settings tab.
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('registrations_enabled', '1', 'Consenti nuove registrazioni (1=si, 0=no)'),
  ('cart_enabled', '1', 'Consenti aggiunta libri al carrello (1=si, 0=no)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
