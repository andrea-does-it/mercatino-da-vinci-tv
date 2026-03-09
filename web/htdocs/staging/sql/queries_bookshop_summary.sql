-- ============================================================================
-- MERCATINO DA VINCI - QUERY DI RIEPILOGO
-- ============================================================================
-- Raccolta di query utili per estrarre dati riepilogativi dal mercatino.
-- Eseguibili dalla pagina admin > Utilità Sito > Esecuzione SQL
--
-- Il ricarico viene letto dalla tabella site_settings:
--   bookshop_seller_deduction  = importo sottratto al venditore
--   bookshop_buyer_markup      = importo aggiunto all'acquirente
--   totale ricarico = somma dei due
-- ============================================================================


--LIBRI IN GIACENZA
SELECT
    p.name                                          AS titolo,
    p.autori                                        AS autori,
    p.ISBN                                          AS ISBN,
    p.nota_volumi                                   AS volumi,
    SUM(oi.quantity)                                     AS qta_rimanente
FROM order_item oi
INNER JOIN orders o    ON o.id = oi.order_id
INNER JOIN product p   ON p.id = oi.product_id
INNER JOIN user u      ON u.id = o.user_id
WHERE oi.status = 'vendere'
GROUP BY p.name, p.autori, p.ISBN, p.nota_volumi 
ORDER BY p.name;

-- ────────────────────────────────────────────────────────────────────────────
-- 1. LIBRI ATTUALMENTE IN VENDITA PRESSO IL MERCATINO
--    (order_item.status = 'vendere')
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    o.numPratica                                    AS pratica,
    u.last_name                                     AS cognome_venditore,
    u.first_name                                    AS nome_venditore,
    p.name                                          AS titolo,
    p.autori                                        AS autori,
    p.ISBN                                          AS ISBN,
    p.nota_volumi                                   AS volumi,
    oi.quantity                                     AS qta,
    oi.single_price                                 AS prezzo_venditore,
    oi.single_price + (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )                                               AS prezzo_acquirente
FROM order_item oi
INNER JOIN orders o    ON o.id = oi.order_id
INNER JOIN product p   ON p.id = oi.product_id
INNER JOIN user u      ON u.id = o.user_id
WHERE oi.status = 'vendere'
ORDER BY u.last_name, u.first_name, p.name;


-- ────────────────────────────────────────────────────────────────────────────
-- 2. LIBRI VENDUTI CON DATE DI VENDITA
--    (order_item.status = 'venduto')
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    oi.updated_at                                   AS data_vendita,
    o.numPratica                                    AS pratica,
    u.last_name                                     AS cognome_venditore,
    u.first_name                                    AS nome_venditore,
    p.name                                          AS titolo,
    p.autori                                        AS autori,
    p.ISBN                                          AS ISBN,
    oi.quantity                                     AS qta,
    oi.single_price                                 AS prezzo_venditore,
    oi.single_price + (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )                                               AS prezzo_acquirente
FROM order_item oi
INNER JOIN orders o    ON o.id = oi.order_id
INNER JOIN product p   ON p.id = oi.product_id
INNER JOIN user u      ON u.id = o.user_id
WHERE oi.status = 'venduto'
ORDER BY oi.updated_at DESC, u.last_name;


-- ────────────────────────────────────────────────────────────────────────────
-- 3. TOTALE GUADAGNATO DAL MERCATINO (commissione)
--    Commissione = quantità × ricarico totale per ogni libro venduto
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    COUNT(*)                                        AS libri_venduti,
    SUM(oi.quantity)                                AS copie_vendute,
    SUM(oi.quantity * oi.single_price)              AS totale_incassato_venditori,
    SUM(oi.quantity) * (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )                                               AS commissione_mercatino,
    SUM(oi.quantity * (oi.single_price + (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )))                                             AS totale_incassato_acquirenti
FROM order_item oi
WHERE oi.status = 'venduto';


-- ────────────────────────────────────────────────────────────────────────────
-- 4. RIEPILOGO GENERALE PER STATO LIBRI
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    oi.status                                       AS stato,
    COUNT(*)                                        AS num_righe,
    SUM(oi.quantity)                                AS num_copie,
    SUM(oi.quantity * oi.single_price)              AS valore_venditore,
    SUM(oi.quantity * (oi.single_price + (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )))                                             AS valore_acquirente
FROM order_item oi
GROUP BY oi.status
ORDER BY FIELD(oi.status, 'accettare', 'vendere', 'venduto', 'eliminato');


-- ────────────────────────────────────────────────────────────────────────────
-- 5. VENDITE PER GIORNATA
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    oi.updated_at                                   AS data_vendita,
    COUNT(*)                                        AS libri_venduti,
    SUM(oi.quantity)                                AS copie_vendute,
    SUM(oi.quantity * oi.single_price)              AS totale_venditori,
    SUM(oi.quantity) * (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )                                               AS commissione_giornata,
    SUM(oi.quantity * (oi.single_price + (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
    )))                                             AS incasso_giornata
FROM order_item oi
WHERE oi.status = 'venduto'
  AND oi.updated_at IS NOT NULL
GROUP BY oi.updated_at
ORDER BY oi.updated_at DESC;


-- ────────────────────────────────────────────────────────────────────────────
-- 6. CLASSIFICA VENDITORI (per numero di libri venduti)
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    u.last_name                                     AS cognome,
    u.first_name                                    AS nome,
    u.email                                         AS email,
    o.numPratica                                    AS pratica,
    COUNT(*)                                        AS libri_venduti,
    SUM(oi.quantity)                                AS copie_vendute,
    SUM(oi.quantity * oi.single_price)              AS totale_dovuto_venditore
FROM order_item oi
INNER JOIN orders o ON o.id = oi.order_id
INNER JOIN user u   ON u.id = o.user_id
WHERE oi.status = 'venduto'
GROUP BY u.id, u.last_name, u.first_name, u.email, o.numPratica
ORDER BY copie_vendute DESC;


-- ────────────────────────────────────────────────────────────────────────────
-- 7. VENDITORI CON LIBRI ANCORA IN VENDITA
--    (utile per contattare i venditori a fine mercatino)
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    u.last_name                                     AS cognome,
    u.first_name                                    AS nome,
    u.email                                         AS email,
    o.numPratica                                    AS pratica,
    COUNT(*)                                        AS libri_in_vendita,
    SUM(oi.quantity * oi.single_price)              AS valore_residuo_venditore
FROM order_item oi
INNER JOIN orders o ON o.id = oi.order_id
INNER JOIN user u   ON u.id = o.user_id
WHERE oi.status = 'vendere'
GROUP BY u.id, u.last_name, u.first_name, u.email, o.numPratica
ORDER BY u.last_name, u.first_name;


-- ────────────────────────────────────────────────────────────────────────────
-- 8. SITUAZIONE RIMBORSI VENDITORI
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    u.last_name                                     AS cognome,
    u.first_name                                    AS nome,
    sr.year                                         AS anno,
    sr.status                                       AS stato_rimborso,
    sr.payment_preference                           AS preferenza_pagamento,
    sr.amount_owed                                  AS importo_dovuto,
    sr.amount_paid                                  AS importo_pagato,
    (sr.amount_owed - sr.amount_paid)               AS importo_residuo,
    sr.donate_unsold                                AS donazione_invenduti,
    sr.envelope_prepared                            AS busta_preparata
FROM seller_refund sr
INNER JOIN user u ON u.id = sr.user_id
ORDER BY sr.status, u.last_name, u.first_name;


-- ────────────────────────────────────────────────────────────────────────────
-- 9. TRANSAZIONI DI VENDITA (cassa / POS / satispay / paypal)
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    st.id                                           AS id_transazione,
    st.created_at                                   AS data_ora,
    st.payment_method                               AS metodo_pagamento,
    st.description                                  AS descrizione,
    st.total_amount                                 AS importo_totale,
    COUNT(sti.id)                                   AS num_libri,
    oper.last_name                                  AS operatore
FROM sales_transaction st
LEFT JOIN sales_transaction_item sti ON sti.sales_transaction_id = st.id
LEFT JOIN user oper ON oper.id = st.operator_id
GROUP BY st.id, st.created_at, st.payment_method, st.description,
         st.total_amount, oper.last_name
ORDER BY st.created_at DESC;


-- ────────────────────────────────────────────────────────────────────────────
-- 10. INCASSO PER METODO DI PAGAMENTO
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    st.payment_method                               AS metodo_pagamento,
    COUNT(*)                                        AS num_transazioni,
    SUM(st.total_amount)                            AS totale_incassato
FROM sales_transaction st
GROUP BY st.payment_method
ORDER BY totale_incassato DESC;


-- ────────────────────────────────────────────────────────────────────────────
-- 11. LIBRI PIU' VENDUTI (titoli con più copie vendute)
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    p.name                                          AS titolo,
    p.autori                                        AS autori,
    p.ISBN                                          AS ISBN,
    SUM(oi.quantity)                                AS copie_vendute,
    p.price                                         AS prezzo_listino
FROM order_item oi
INNER JOIN product p ON p.id = oi.product_id
WHERE oi.status = 'venduto'
GROUP BY p.id, p.name, p.autori, p.ISBN, p.price
ORDER BY copie_vendute DESC
LIMIT 20;


-- ────────────────────────────────────────────────────────────────────────────
-- 12. RIEPILOGO COMPLETO DEL MERCATINO (dashboard in una query)
-- ────────────────────────────────────────────────────────────────────────────
SELECT
    (SELECT COUNT(*) FROM order_item WHERE status = 'accettare')   AS libri_da_accettare,
    (SELECT COUNT(*) FROM order_item WHERE status = 'vendere')     AS libri_in_vendita,
    (SELECT COUNT(*) FROM order_item WHERE status = 'venduto')     AS libri_venduti,
    (SELECT COUNT(*) FROM order_item WHERE status = 'eliminato')   AS libri_eliminati,
    (SELECT COUNT(DISTINCT o.user_id)
     FROM orders o
     INNER JOIN order_item oi ON oi.order_id = o.id
     WHERE oi.status IN ('vendere','venduto'))                     AS venditori_attivi,
    (SELECT SUM(oi.quantity * oi.single_price)
     FROM order_item oi WHERE oi.status = 'venduto')               AS totale_venditori,
    (SELECT SUM(oi.quantity) * (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
     ) FROM order_item oi WHERE oi.status = 'venduto')             AS commissione_totale,
    (SELECT SUM(oi.quantity * (oi.single_price + (
        SELECT CAST(s1.setting_value AS DECIMAL(8,2)) + CAST(s2.setting_value AS DECIMAL(8,2))
        FROM site_settings s1, site_settings s2
        WHERE s1.setting_key = 'bookshop_seller_deduction'
          AND s2.setting_key = 'bookshop_buyer_markup'
     ))) FROM order_item oi WHERE oi.status = 'venduto')           AS incasso_totale_acquirenti;
