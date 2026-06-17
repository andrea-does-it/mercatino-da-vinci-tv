ALTER TABLE `product`
ADD COLUMN `prezzo_listino` DECIMAL(8,2) DEFAULT NULL AFTER `price`;
