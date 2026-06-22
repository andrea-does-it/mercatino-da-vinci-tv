-- =====================================================================
-- Arrotonda i prezzi dei libri al multiplo di 0,50 EUR piu' vicino.
-- Colonna interessata: product.price (prezzo di vendita "attuale").
-- Formula: ROUND(price * 2) / 2
--   es. 3,20 -> 3,00 | 3,30 -> 3,50 | 3,25 -> 3,50 | 3,75 -> 4,00
-- I valori NULL e quelli gia' su un multiplo di 0,50 NON vengono toccati.
--
-- ESEGUIRE I PASSI IN ORDINE. Il PASSO 1 e il PASSO 2 sono di sola lettura/backup.
-- =====================================================================

-- ---------------------------------------------------------------------
-- PASSO 1 - ANTEPRIMA (sola lettura): elenco dei prezzi che cambierebbero.
-- Eseguire da solo e controllare il risultato prima di proseguire.
-- ---------------------------------------------------------------------
SELECT
    id,
    name,
    price                  AS prezzo_attuale,
    ROUND(price * 2) / 2   AS prezzo_arrotondato
FROM `product`
WHERE price IS NOT NULL
  AND price <> ROUND(price * 2) / 2
ORDER BY id;

-- ---------------------------------------------------------------------
-- PASSO 2 - BACKUP (consigliato): copia i prezzi correnti in una tabella
-- di salvataggio, cosi' si puo' ripristinare in caso di errore.
-- Per ripristinare:
--   UPDATE product p JOIN product_price_backup_20260622 b ON b.id = p.id
--   SET p.price = b.price;
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_price_backup_20260622` AS
    SELECT id, price, prezzo_listino FROM `product`;

-- ---------------------------------------------------------------------
-- PASSO 3 - AGGIORNAMENTO in transazione.
-- Eseguire l'UPDATE, controllare i due SELECT di verifica e POI COMMIT
-- (oppure ROLLBACK se qualcosa non torna).
-- ---------------------------------------------------------------------
START TRANSACTION;

UPDATE `product`
SET price = ROUND(price * 2) / 2
WHERE price IS NOT NULL
  AND price <> ROUND(price * 2) / 2;

-- Quante righe sono state modificate (eseguire subito dopo l'UPDATE):
SELECT ROW_COUNT() AS righe_aggiornate;

-- Verifica: dopo l'update non deve restare alcun prezzo fuori dal multiplo di 0,50.
SELECT COUNT(*) AS prezzi_fuori_multiplo_residui
FROM `product`
WHERE price IS NOT NULL
  AND price <> ROUND(price * 2) / 2;

-- Se i controlli sono ok, confermare:
COMMIT;
-- In alternativa, annullare tutto:
-- ROLLBACK;

-- ---------------------------------------------------------------------
-- NOTA: questo script arrotonda SOLO product.price.
-- Se servisse arrotondare anche il prezzo di listino, scommentare:
-- UPDATE `product`
-- SET prezzo_listino = ROUND(prezzo_listino * 2) / 2
-- WHERE prezzo_listino IS NOT NULL
--   AND prezzo_listino <> ROUND(prezzo_listino * 2) / 2;
--
-- Dopo aver verificato che tutto e' corretto, la tabella di backup si puo' rimuovere:
-- DROP TABLE `product_price_backup_20260622`;
-- =====================================================================
