ALTER TABLE `product` 
ADD COLUMN `nota_volumi` VARCHAR(50) DEFAULT '' AFTER `editore`,
ADD COLUMN `fl_esaurimento` INT DEFAULT 0 AFTER `nota_volumi`;