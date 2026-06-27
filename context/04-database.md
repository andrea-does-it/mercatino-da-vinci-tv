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

## Main tables (the ones you'll touch most)
### `user`
Auth + profile. Key columns: `id`, `first_name`, `last_name`, `email`, `password`,
`user_type` (`regular`/`admin`/`pwuser`), `profile_id`,
GDPR: `privacy_consent`(+`_date`), `newsletter_consent`(+`_date`),
IBAN: `iban` (AES-256-GCM encrypted), `iban_owner_name`, `iban_updated_at`,
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

### `site_settings`
Key/value configuration read by `SiteSettings` (pricing markups, `registrations_enabled`,
`cart_enabled`, …).

### Others
`category`, `cart`/`cart_item`, `shipment`, `news`, `download`, `user_activity_log`,
`profile`, `special_treatment`.

## DB access pattern
Go through manager classes (`DBManager` subclasses). They use parameterized PDO queries.
`create()`/`update()` cast the object to an array keyed by property name — so an object's
public properties must line up with real columns (see gotchas in 06).
