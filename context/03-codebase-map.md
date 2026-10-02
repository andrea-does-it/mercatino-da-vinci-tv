# 03 — Codebase Map

All paths are under `web/htdocs/` (mirror everything in `web/htdocs/staging/`).

## Top-level directories
| Dir | Purpose |
|-----|---------|
| `admin/` | Admin UI: `index.php` router + `pages/*.php` controllers |
| `api/` | JSON/AJAX + standalone endpoints (`api/admin/`, `api/shop/`) |
| `auth/` | Login, registration, password reset |
| `public/` | Public pages + `template-parts/` (header, footer, sidebar) |
| `shop/` | Catalog, cart, checkout, `invoices/` (PDF), payment bits |
| `user/` | Logged-in user dashboard/profile/privacy |
| `classes/` | Business-logic "manager" classes + `utilities/` |
| `inc/` | Bootstrap, config, helpers (see 02) |
| `sql/` | Migration `.sql` files (applied manually) |
| `jobs/` | CLI jobs (e.g. `generate-images.php`) |
| `images/`, `uploads/` | Filesystem storage for product images / uploads |
| `lib/`, `vendor/` | Third-party libs (PHPMailer, FPDF, PayPal, Stripe) |

## Classes (`classes/`) — one line each
| File | Purpose |
|------|---------|
| `DB.php` | PDO wrapper `DB` (`prepare`, `execute`, `insert_one`, `update_one`, `select_one`, `select_all`) + base `DBManager` (`get/getAll/create/update/delete` using `$this->tableName` + `$this->columns`). **`create()`/`update()` cast the whole object to an array** — see gotchas. |
| `User.php` | `User` + `UserManager`: auth, `register()`, GDPR consent, IBAN (encrypted), `donate_books` preference, `updateDonateBooks`/`getDonateBooks`. `validateAdminData($data, $excludeId=0)` (pure validation, no writes — shared by `adminUpdate()` and the admin "add user" flow) + `adminUpdate($id, $data)` (used by `admin/pages/user.php`): validates everything first, encrypts a new IBAN *before* opening any transaction (so a configuration/encryption failure writes nothing), then writes the whitelisted columns that actually changed, `donate_books`+`donate_books_date`, and the IBAN all together in **one transaction** on `$this->db->pdo` (rollback + "Errore nel salvataggio" on any exception); returns `['ok','errors','changed']`. `iban_town` (migration 202610020001) is optional: `supportsIbanTown()`/the private `hasIbanTownColumn()` detect it (cached per-process) and both the SELECTs and the editable columns adapt so a DB missing that migration doesn't fatal. `getAdminRow($id)` reads the extra fields the `User` object doesn't expose (student_*, iban_*, donate_*, consent dates) — never password/reset_link. |
| `Product.php` | `Product`, `ProductManager`, `ProductImage`, `ProductImageManager`. Columns include `price`, `prezzo_listino`, `nascosto`, `fl_esaurimento`, `nota_volumi`, `ISBN`. Shop queries filter `nascosto=0`. |
| `Cart.php` | `Cart`/`CartManager`, `Order`/`OrderManager`, `PraticaManager` (the `numPratica` counter). Manages `order_item` lifecycle and `sendAcceptanceEmail()`. |
| `SalesTransaction.php` | **New in-person sales**: `SalesTransaction`, `SalesTransactionItem`, their managers. `createTransaction()`, refunds (soft-delete), `getOperatorName()`, daily totals, payment methods. |
| `SellerRefund.php` | End-of-mercatino seller payouts + closing report data. New records inherit profile defaults: `donate_unsold` from `user.donate_books`, `payment_preference` = bonifico/contanti from `user.iban`; `applyUserDefaultsToYear()` backfills existing rows (NULL columns only). `SQL_HAS_IBAN`/`SQL_DONATES` consts hold the shared profile conditions. `BOOKSHOP_PRATICA` (=100) + `sqlIsRealSeller()` exclude the mercatino's own stock from every refund amount/list/report (sales views keep it). |
| `SiteSettings.php` | Configurable settings: pricing (`sellerDeduction`, `buyerMarkup`, `totalMarkup`) and toggles (`registrationsEnabled`, `cartEnabled`). |
| `CSRF.php` | Token generation/validation: `validateToken`, `validateAjaxOrDie`, `getTokenForAjax`, `tokenField` (via `csrf_field()`). |
| `Category.php`, `Shipment.php`, `Profile.php`, `SpecialTreatment.php` | Catalog categories, shipment methods, user profiles, special pricing treatments. |
| `EmailTemplate.php`, `OrderEmail.php` | `EmailTemplateManager` (CRUD tabella `email_template`); `OrderEmailManager` (ricerca ordini per filtri, merge segnaposto, log invii in `order_email_log`, `buildHtmlBody()` → helper condiviso). Usati dai tab "Email Ordini"/"Template Email" di site_utils. |
| `inc/functions.php` | Helper globali (`esc`, `esc_html`, `send_mail`, …) + **`email_text_to_html()` / `email_html_document()`**: testo semplice → HTML email con URL cliccabili (escape prima, link dopo). |
| `NewsManager.php`, `DownloadManager.php`, `ActivityLog.php`, `Email.php` | News, downloads, user activity logging, email helper. |
| `Encryption.php` | AES-256-GCM (used to encrypt IBANs). |
| `SepaCbiExport.php` | **Distinte SEPA — classe pura** (nessun accesso al DB): costruisce l'XML `CBIPaymentRequest.00.04.01` (`buildXml()`) con `DOMDocument` e offre gli helper `ibanIsValid()` (mod 97 + lunghezza per paese), `isSepaCountry()`, `ibanMask()`, `abiFromIban()`, `toSepaCharset()` (traslitterazione al set SEPA), `buildRemittance()` (causale con `{anno}`/`{pratiche}`, troncata a 140 caratteri su pratica intera), `nextBusinessDay()`, `validate()` (XSD CBI in `classes/xsd/`, se assente la validazione è saltata). |
| `SepaBatch.php` | `SepaBatchManager` (DBManager): distinte SEPA dei rimborsi venditori. `getCandidates($year)` (idonei/esclusi con motivo), `getDebtorSettings()`/`saveDebtorSettings()`/`saveTemplate()` (dati ordinante e causale in `site_settings`), `createBatch()`/`markBatchPaid()`/`discardBatch()` (scrivono **sulla propria connessione PDO** `$this->db->pdo`, in transazione — non chiamare da qui metodi di scrittura di altri manager, es. `SellerRefundManager::recordPayment()`, perché finirebbero fuori transazione), `getBatchesForYear()`, `getBatchItems()`/`getBatchesForRefund()` (con flag `superseded`). Vedi `05-domain-workflows.md` §C per le regole. |
| `Upgrade.php` | In-app upgrade/maintenance helpers. |
| `utilities/BookLookup.php` | ISBN lookup + `downloadCover($isbn,$path)` (Libraccio: `https://www.libraccio.it/images/<isbn>_0_500_0_75.jpg`, kept only if ≥1000 bytes). |
| `utilities/ImageUtilities.php` | `wallpaper()` (resize) + `thumbnail()` generation. |
| `utilities/PdfUtilities.php` | FPDF docs: `printOrderInvoice()`, `printSalesTransactionReceipt()`. |
| `utilities/UrlUtilities.php`, `utilities/Utilities.php` | URL building, misc helpers (guid, etc.). |

## Key admin pages (`admin/pages/`)
- **Sales (current):** `sales-transactions.php` (dashboard), `sales-transaction-new.php`
  (create sale), `sales-transaction-view.php` (detail + refunds),
  `sales-transaction-receipt.php` (confirmation → PDF), `sales-items-search.php`
  (Ricerca dettagliata: item-level search, one row per copy sold, filters on book/
  pratica/seller/transaction; linked from the Filtri card). Help: `help-sales-transactions.php`.
- **Pratiche (acceptance/pickup):** `orders-list.php`, `process-order.php`,
  `libri_per_pratica.php`, `libri_per_pratica_item.php`.
- **Seller payouts/closing:** `seller-refunds.php` (+ "Applica Preferenze dal Profilo"),
  `seller-refund-view.php`, `seller-refund-report.php`, `seller-refund-newsletter.php`
  (filters on the user profile: IBAN / Donazione / "Da contattare").
  `seller-orders-report.php` ("Riepilogo Pratiche"): tutte le pratiche con libri, lette da
  `order_item.status`, indipendenti da `seller_refund` e da `sales_transaction`.
  Seller-facing counterpart: `payment-preference.php` at the web root (token link from the
  newsletter) — IBAN on file locks the choice to bonifico, a given donation cannot be undone.
  `seller-refund-sepa.php` ("Distinte SEPA", linkata da `seller-refunds.php`): genera il file
  XML dei bonifici multipli, mostra idonei/esclusi, storico distinte con "Segna come pagati"/
  "Scarta". `sepa-selftest.php` (`admin/?page=sepa-selftest`): self-test di sola lettura delle
  parti pure (IBAN, charset, causale, XML) + verifica che la migrazione `202610010001` sia
  applicata. `SellerRefundManager::sqlIsRealSeller()` è ora **public** (prima privata): usata
  anche da `SepaBatchManager::getCandidates()`.
  **Ogni nuova pagina admin va aggiunta a `$allowedPages` in `admin/index.php`** (whitelist
  anti-LFI): se manca, il router ricade silenziosamente su `dashboard`.
- **Catalog:** `product.php` (add/edit incl. image upload + `nascosto`/`fl_esaurimento`),
  `products-list.php` (list + columns + CSV/Excel export + quick filters),
  `category.php`, `category-list.php`, `import-libri.php` (ISBN/CSV import + covers).
- **Deprecated old sale flow (kept but removed from menu):** `libri_da_vendere.php`,
  `calcolo_vendita.php`, `incasso_vendita.php`, `libri_venduti.php`.
- **Other:** `dashboard.php`, `users-list.php`/`user.php`, `news-management.php`,
  `download-management.php`, `activity-logs.php`. `user.php` edits the full user record
  (account, studente, IBAN, donazione, consensi read-only) via `UserManager::adminUpdate()`/
  `getAdminRow()` — it used to call the inherited `DBManager::update()`, which casts the
  whole `User` object and silently wiped student_*/consent/`donate_books` on every admin
  save (see `context/06-conventions-and-gotchas.md` §2); fixed by the dedicated methods
  above. **`site_utils.php`** has five tabs:
  Email di Test, Esecuzione SQL, Impostazioni, Email Ordini, Template Email; the last
  two are partials `site_utils_email_orders.php` / `site_utils_email_templates.php`
  included by `site_utils.php` (not routed pages, not in the admin whitelist).
- Files suffixed `_old` / `- senzavolumi` / `process-order2` are legacy backups — ignore.

## APIs (`api/admin/`)
| Endpoint | Purpose | Gate |
|----------|---------|------|
| `upload.php` | Product image upload (+ thumbnail) | admin |
| `delete.php` | Delete image / temp images | admin |
| `categories.php` | Subcategory lookups | admin |
| `import-libri.php` | Bulk import (create products, fetch covers); update-existing mode does NOT touch images | admin |
| `products-export.php` | CSV/Excel export of the books list (incl. image-file column) | admin/pwuser |
| `send-order-email.php` | POST, CSRF ajax; send/preview one mail-merge email per order; logs to `order_email_log` | admin |

| `sepa-batch-download.php` | POST, CSRF; crea la distinta (`SepaBatchManager::createBatch()`) e la restituisce come download XML (`Content-Disposition: attachment`) | admin/pwuser |

Standalone (not under `api/`): `shop/invoices/print-invoice.php` (old pratica receipt) and
`shop/invoices/print-sales-receipt.php` (sales-transaction PDF receipt).
**Trap evitata:** `admin/index.php` emette l'header/layout HTML prima di includere la pagina,
quindi una pagina admin non può impostare `Content-Disposition`/scrivere un binario — per
questo il download della distinta SEPA è un endpoint standalone in `api/admin/`, non parte
di `seller-refund-sepa.php`.
