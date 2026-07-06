-- =====================================================================
-- Allinea order_item.single_price al prezzo corrente del prodotto
-- Imposta oi.single_price = p.price per tutte le righe in cui i due
-- valori differiscono (es. dopo un ricalcolo/arrotondamento dei prezzi
-- a catalogo avvenuto DOPO la creazione delle righe ordine).
-- =====================================================================

-- Verifica preventiva: righe che verranno modificate (con differenza).
-- Esegui questa SELECT PRIMA dell'UPDATE per controllare cosa cambia.
SELECT o.numPratica, o.id AS order_id, p.name, oi.single_price, p.price
FROM order_item oi
INNER JOIN orders o  ON oi.order_id  = o.id
INNER JOIN product p ON oi.product_id = p.id
WHERE oi.single_price <> p.price
ORDER BY o.numPratica, p.name;

START TRANSACTION;

UPDATE order_item oi
INNER JOIN product p ON oi.product_id = p.id
SET oi.single_price = p.price
WHERE oi.single_price <> p.price;

COMMIT;

-- Controllo finale: deve restituire 0 righe residue con differenza.
SELECT COUNT(*) AS righe_ancora_diverse
FROM order_item oi
INNER JOIN product p ON oi.product_id = p.id
WHERE oi.single_price <> p.price;
