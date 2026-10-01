# 04 — Database

MySQL/MariaDB. See `../docs/database_schema.md` for fuller column lists; this file is the
current, practical summary plus the migration workflow.

## Migration workflow (important)
- Schema/data changes are plain SQL files in `web/htdocs/sql/` (mirror to
  `web/htdocs/staging/sql/`).
- Naming: `YYYYMMDDNNNN_short_description.sql` (e.g. `202606170002_product_nascosto.sql`).
- **Migrations are applied to a database by hand** (per environment). A committed migration
  file does NOT mean the column exists on the server — this mismatch is a recurring source
  of "it works on staging but errors on production" bugs.
- Through `202606220001` all migrations are applied in production (as of 2026-06).
  `202610010001_sepa_distinte.sql` (distinte SEPA) e `202610020001_sepa_localita.sql`
  (località e Category Purpose, richiesti da UniCredit — vedi sotto) sono nel repo e
  **vanno applicate a mano su ogni ambiente, in ordine**: finché non lo sono entrambe,
  `SepaBatchManager::isInstalled()` restituisce `false` e sia la pagina
  `seller-refund-sepa.php` sia l'endpoint di generazione si **bloccano con un messaggio
  prima di scrivere qualunque cosa** (stesso riscontro nel self-test
  `admin/?page=sepa-selftest`) — non è un salvataggio che fallisce a metà, è un blocco esplicito
  a monte.

## Main tables (the ones you'll touch most)
### `user`
Auth + profile. Key columns: `id`, `first_name`, `last_name`, `email`, `password`,
`user_type` (`regular`/`admin`/`pwuser`), `profile_id`,
GDPR: `privacy_consent`(+`_date`), `newsletter_consent`(+`_date`),
IBAN: `iban` (AES-256-GCM encrypted), `iban_owner_name`, `iban_updated_at`,
`iban_town` (migrazione `202610020001`, nullable: località del beneficiario per i bonifici
SEPA; se vuota si usa il setting `sepa_default_creditor_town`),
student: `student_first_name/last_name/class`,
`donate_books` (TINYINT, **nullable DEFAULT 0**), `donate_books_date`.

### `product`
Catalog of adopted books. Key columns: `id`, `name`, `autori`, `editore`, `ISBN`,
`price` (seller/selling price), `prezzo_listino`, `category_id`, `sconto` + discount dates,
`qta`, `nota_volumi`, `fl_esaurimento` (TINYINT), `nascosto` (TINYINT NOT NULL DEFAULT 0;
`1` = hidden from shop). Shop listing queries filter `nascosto = 0`.

### `product_images`
`id`, `product_id`, `image_extension` (always `jpg`), `title`, `alt`, `order_number`.
File on disk: `images/<product_id>/<image_id>.jpg` (+ `<id>_thumbnail.jpg`).

### `orders` (a *pratica*) and `order_item`
- `orders`: `id`, `numPratica` (assigned at acceptance), `user_id` (seller), `status`
  (`inviata`/`accettata`/`chiusa`/`annullata`/`eliminato`), timestamps, `is_email_sent`,
  shipment fields.
- `order_item`: `id`, `order_id`, `product_id`, `quantity`, `single_price`,
  `status` (`accettare` → `vendere` → `venduto`, or `eliminato`), `updated_at`,
  `refund_notes`.
- `pratica`: single-row counter feeding `numPratica` (via `PraticaManager`).
- `order_item1`: temp/summary table populated on sale (`calcolaVendita`).

### `sales_transaction` and `sales_transaction_item` (current sales)
- `sales_transaction`: `id`, `payment_method` (`cash`/`POS`/`satispay`/`paypal`),
  `description` (customer/note), `operator_id`, `total_amount`, timestamps,
  soft-delete refund fields: `refunded_at`, `refunded_by`, `refund_notes`.
- `sales_transaction_item`: `id`, `sales_transaction_id`, `order_item_id`, `price`,
  `created_at`, + per-item refund fields. Selling a book sets its `order_item.status` to
  `venduto`; refunding restores it to `vendere`.

### `seller_refund` (+ `seller_refund_payment`)
Per-seller, per-year payout record used by the closing report: `user_id`, `year`,
`amount_owed`, `amount_paid`, `status`, `payment_preference`, `donate_unsold`
(seeded from `user.donate_books`), `seller_notes`, `envelope_prepared`, newsletter fields.
`status` enum: `pending`/`partial`/**`xmlsaved`**/`completed`/`cancelled` — `xmlsaved`
("Distinta generata", migrazione `202610010001`) significa **incluso in una distinta generata e
non ancora pagata tramite distinta**. Un pagamento registrato a mano su
`seller-refund-view.php` può comunque portarlo a `partial`/`completed` nel frattempo: in quel
caso la pagina avvisa (riquadro rosso sopra il form "Registra Pagamento") che il rimborso è
ancora in una distinta `generated`, per evitare di pagarlo due volte.

### `sepa_batch` / `sepa_batch_item` (migrazioni `202610010001` e `202610020001`)
Storico delle distinte SEPA (vedi `05-domain-workflows.md` §C). **L'XML non viene salvato**:
solo i metadati.
- `sepa_batch`: una riga per file generato — `year`, `msg_id` (= `PmtInfId` inviato alla
  banca, univoco), `execution_date`, `remittance_template`, `debtor_iban_masked`, `tx_count`,
  `total_amount`, `status` (`generated`/`paid`/`discarded`), `created_by`/`created_at`,
  `paid_at`/`paid_by`.
- `sepa_batch_item`: una riga per bonifico — `batch_id`, `seller_refund_id`, `amount`,
  `end_to_end_id`, `remittance`, `beneficiary_name`, `iban_masked`. **Solo IBAN mascherato**:
  l'IBAN in chiaro resta cifrato in `user.iban` e viene decifrato solo in memoria durante la
  generazione, mai scritto qui né altrove.
- Un `seller_refund` può comparire in più batch (rigenerazione / correzioni): le righe
  storiche restano, una è "superata" se lo stesso rimborso è anche in un batch più recente
  non scartato.

### `site_settings`
Key/value configuration read by `SiteSettings` (pricing markups, `registrations_enabled`,
`cart_enabled`, …). Chiavi delle distinte SEPA: `sepa_debtor_name`, `sepa_debtor_iban`,
`sepa_debtor_cuc` (facoltativo: la banca lo sostituisce comunque), `sepa_debtor_country`,
`sepa_debtor_town` (migrazione `202610020001`, località dell'ordinante richiesta da
UniCredit) (dati ordinante, conto del Comitato), `sepa_default_creditor_town` (migrazione
`202610020001`, località usata per i beneficiari senza `user.iban_town`),
`sepa_category_purpose` (migrazione `202610020001`, default `SUPP`, codice CtgyPurp
richiesto da UniCredit), e `sepa_remittance_template` (causale di default, segnaposto
`{anno}`/`{pratiche}`).

### `email_template` and `order_email_log` (Email Ordini)
- `email_template`: `id`, `name`, `subject`, `body` (testo semplice con segnaposto
  `{nome}`, `{num_pratica}`, `{elenco_libri}`, ...), timestamps. Gestita dal tab
  "Template Email" di site_utils.
- `order_email_log`: `id`, `order_id`, `template_id` (NULL se testo ad-hoc o template
  eliminato — nessuna FK), `recipient_email`, `subject` (copia del merged), `sent_at`,
  `sent_by` (admin). Alimenta l'avviso "già inviata" del tab "Email Ordini".
- Migrazione: `202607070001_email_template_e_log.sql`.

### Others
`category`, `cart`/`cart_item`, `shipment`, `news`, `download`, `user_activity_log`,
`profile`, `special_treatment`.

## DB access pattern
Go through manager classes (`DBManager` subclasses). They use parameterized PDO queries.
`create()`/`update()` cast the object to an array keyed by property name — so an object's
public properties must line up with real columns (see gotchas in 06).
