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
| `User.php` | `User` + `UserManager`: auth, `register()`, GDPR consent, IBAN (encrypted), `donate_books` preference, `updateDonateBooks`/`getDonateBooks`. |
| `Product.php` | `Product`, `ProductManager`, `ProductImage`, `ProductImageManager`. Columns include `price`, `prezzo_listino`, `nascosto`, `fl_esaurimento`, `nota_volumi`, `ISBN`. Shop queries filter `nascosto=0`. |
| `Cart.php` | `Cart`/`CartManager`, `Order`/`OrderManager`, `PraticaManager` (the `numPratica` counter). Manages `order_item` lifecycle and `sendAcceptanceEmail()`. |
| `SalesTransaction.php` | **New in-person sales**: `SalesTransaction`, `SalesTransactionItem`, their managers. `createTransaction()`, refunds (soft-delete), `getOperatorName()`, daily totals, payment methods. |
| `SellerRefund.php` | End-of-mercatino seller payouts + closing report data; per-year `donate_unsold` (seeded from `user.donate_books`). |
| `SiteSettings.php` | Configurable settings: pricing (`sellerDeduction`, `buyerMarkup`, `totalMarkup`) and toggles (`registrationsEnabled`, `cartEnabled`). |
| `CSRF.php` | Token generation/validation: `validateToken`, `validateAjaxOrDie`, `getTokenForAjax`, `tokenField` (via `csrf_field()`). |
| `Category.php`, `Shipment.php`, `Profile.php`, `SpecialTreatment.php` | Catalog categories, shipment methods, user profiles, special pricing treatments. |
| `NewsManager.php`, `DownloadManager.php`, `ActivityLog.php`, `Email.php` | News, downloads, user activity logging, email helper. |
| `Encryption.php` | AES-256-GCM (used to encrypt IBANs). |
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
- **Seller payouts/closing:** `seller-refunds.php`, `seller-refund-view.php`,
  `seller-refund-report.php`, `seller-refund-newsletter.php`.
- **Catalog:** `product.php` (add/edit incl. image upload + `nascosto`/`fl_esaurimento`),
  `products-list.php` (list + columns + CSV/Excel export + quick filters),
  `category.php`, `category-list.php`, `import-libri.php` (ISBN/CSV import + covers).
- **Deprecated old sale flow (kept but removed from menu):** `libri_da_vendere.php`,
  `calcolo_vendita.php`, `incasso_vendita.php`, `libri_venduti.php`.
- **Other:** `dashboard.php`, `users-list.php`/`user.php`, `news-management.php`,
  `download-management.php`, `activity-logs.php`, `site_utils.php`.
- Files suffixed `_old` / `- senzavolumi` / `process-order2` are legacy backups — ignore.

## APIs (`api/admin/`)
| Endpoint | Purpose | Gate |
|----------|---------|------|
| `upload.php` | Product image upload (+ thumbnail) | admin |
| `delete.php` | Delete image / temp images | admin |
| `categories.php` | Subcategory lookups | admin |
| `import-libri.php` | Bulk import (create products, fetch covers); update-existing mode does NOT touch images | admin |
| `products-export.php` | CSV/Excel export of the books list (incl. image-file column) | admin/pwuser |

Standalone (not under `api/`): `shop/invoices/print-invoice.php` (old pratica receipt) and
`shop/invoices/print-sales-receipt.php` (sales-transaction PDF receipt).
