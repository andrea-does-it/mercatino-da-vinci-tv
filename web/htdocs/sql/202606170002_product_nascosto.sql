ALTER TABLE `product`
ADD COLUMN `nascosto` TINYINT(1) NOT NULL DEFAULT 0 AFTER `fl_esaurimento`;
