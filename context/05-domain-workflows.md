# 05 — Domain Workflows

## A. Selling: the *pratica* (acceptance) flow
1. A seller builds a cart of books they want to sell and submits it at **checkout**
   (`shop/pages/checkout.php`). This creates an `orders` row (the *pratica*) with one
   `order_item` per book, `status = accettare`, and sends a **confirmation email**
   ("Grazie per la richiesta di vendita dei tuoi libri…").
2. An admin opens the pratica in **`process-order.php`** and, per item, accepts
   (`status = vendere`) or rejects (`eliminato`).
3. Admin clicks **"Termina accettazione"**: order → `accettata`, a `numPratica` is assigned
   (via `PraticaManager`), an **acceptance email** is sent ("La tua Richiesta è stata
   accettata e ti è stato assegnato il numero di Pratica …"), and a label/receipt can be
   printed (`shop/invoices/print-invoice.php`).
4. Accepted books (`vendere`) are now sellable at the mercatino.

`order_item.status` lifecycle: `accettare` → `vendere` → `venduto` (or `eliminato`).

**Hidden-book guard (nascosto):** a book with `product.nascosto = 1` is not sellable, so it
must not be accepted. `process-order.php` enforces this in depth: the "Libri da Accettare"
row shows a red "Libro nascosto — non vendibile" badge and hides the **Accetta** button
(only **Elimina** remains); the `vendere` POST handler refuses the transition server-side
(`hidden_not_acceptable` alert); and **Termina accettazione** is blocked if any `vendere`
item is hidden (`hidden_in_accepted` alert), catching the case where a book is hidden after
its item was accepted. `getOrderItems`/`getOrderItemsAccettare` expose `product_nascosto`
for this. (Sale-side lists already exclude hidden books — see §B and §D.)

## B. Selling at the till: **sales transactions** (current system)
Replaces the old "mark each book venduto" flow.
1. Operator opens **`sales-transaction-new.php`**, searches available books
   (`order_item.status = vendere`), adds them to a client-side cart (persisted in
   `sessionStorage` so a search reload doesn't clear it), picks a **payment method**
   (cash / POS / Satispay — PayPal removed from the dropdown), optional customer note.
2. Submit → `SalesTransactionManager::createTransaction()`:
   - sale price per item = `order_item.single_price + SiteSettings::totalMarkup()`
   - creates `sales_transaction` (+ `_item` rows), sets each `order_item.status = venduto`,
     calls `calcolaVendita()`.
3. Redirects to **`sales-transaction-receipt.php`** — a confirmation page whose single
   output is the **PDF receipt** (`shop/invoices/print-sales-receipt.php`,
   `PdfUtilities::printSalesTransactionReceipt`). The on-screen receipt table and the old
   `window.print()` were removed to avoid two divergent layouts.
4. **Refunds** (`sales-transaction-view.php`) are soft-deletes: mark
   `sales_transaction(_item).refunded_at/by/notes`, restore `order_item.status = vendere`
   (book becomes available again — register a new sale from "Nuova Vendita").

**Detailed search** (`sales-items-search.php`, "Ricerca dettagliata" button in the Filtri
card of `sales-transactions.php`): item-level search — one row per copy sold — joining
`sales_transaction_item → order_item → orders → product` (+ seller/operator users).
Filters on book (ISBN/titolo/autori/editore), pratica (numPratica/venditore) and
transaction (id/metodo/date/operatore/descrizione/stato/prezzo). Backed by
`SalesTransactionManager::searchSoldItems()` / `searchSoldItemsTotals()`; an item counts
as refunded when either the item or its whole transaction is refunded.

Access: sales pages are admin/pwuser (gated by `admin/index.php`); there is **no special
"mercatino" user type** — sellers are `regular` and never reach these pages.

### Old sale flow (deprecated, kept for reference)
`libri_da_vendere.php` (manual venduto), `calcolo_vendita.php`, `incasso_vendita.php`,
`libri_venduti.php` — removed from the menu, banners added; "Gestione Vendite"
(`sales-transactions`) is the single entry point now.

## C. End of mercatino: seller refunds / closing report
- `seller-refunds.php` lists per-seller payout records for a year; `createRecordsForYear()`
  / `getOrCreateForUserYear()` create them, seeding `donate_unsold` from the seller's
  profile `user.donate_books`.
- `seller-refund-report.php` is the **closing situation report** (sold vs unsold per seller,
  amounts owed, payment preference, and a **"Donazione"** column from `donate_unsold`).
- `seller-refund-view.php` records payments and shows the donation preference.
- **donate_books**: a standing profile preference (set in `user/pages/privacy.php` and at
  registration) meaning "donate my unsold books instead of taking them back"; it defaults
  the per-year `donate_unsold` (admin can still override per year).

## D. Products & images
- Catalog = adopted schoolbooks. Add/edit in `product.php`; list in `products-list.php`
  (columns incl. Note Volumi / Esaurimento / Nascosto; CSV+Excel export; quick filters).
- `nascosto = 1` hides a product from the shop **and from the whole sales chain**: it is
  excluded from `shop/products-list`, the public `libri_da_vendere` list
  (`OrderManager::getOrderItems4`), and the till's available-books query
  (`SalesTransactionManager::getAvailableBooksForSale`), and it cannot be accepted in the
  inbound flow (see §A, hidden-book guard). `fl_esaurimento` flags low stock.
- **Images**: three coordinated pieces — a `product_images` row, the file
  `images/<product_id>/<image_id>.jpg`, and a generated thumbnail. The supported way to add
  one is the upload control on `product.php` → `api/admin/upload.php` (creates all three).
- **Import** (`import-libri.php` + `api/admin/import-libri.php`): create products from
  ISBN/CSV and auto-fetch covers via `BookLookup::downloadCover` (Libraccio). Covers are
  only fetched when **creating** a product; "update existing" does not touch images.

## E. Buying (shop), auth, emails
- **Shop**: public catalog (`shop/pages/products-list.php`, buyer prices = seller price +
  buyer markup), cart, checkout. Cart/registration availability is gated by
  `SiteSettings::cartEnabled()` / `registrationsEnabled()` (marketplace toggles). The
  shipping-method field on the cart is hidden (mercatino has no shipping).
- **Catalog search** matches **titolo/ISBN/materia/autori** via one shared helper,
  `ProductManager::_shopSearchClause()`. Two entry points use it:
  - The `products-list.php` search box submits a GET `?search=` and filters server-side over
    the whole catalog (`GetProductsCount`/`GetProductsPaginated` `$search` arg); pagination
    links preserve `search`. (It used to be client-side JS over only the 12 cards on the
    current page, so off-page books were "not found".)
  - The navbar live-search (`#search` in `template-parts/header*.php` → `main.js` →
    `api/shop/search-products.php` → `SearchProducts` → `_getProductsQuery`, 5 suggestions).
- **Auth**: `auth/pages/register.php` (creates `regular` users; optional newsletter &
  donate-books opt-ins), login, password reset. User types: `regular`/`admin`/`pwuser`.
- **Emails**: built as inline HTML and sent via `send_mail()` (PHPMailer/SMTP). Main ones:
  order-submission confirmation (`checkout.php`) and pratica acceptance
  (`Cart.php::sendAcceptanceEmail`). UI/email copy is Italian, "tu" form, and includes a
  "check your SPAM folder" notice.

## F. Email massive agli ordini (mail merge) — site_utils
Da `admin/?page=site_utils&tab=email_orders` l'admin filtra gli ordini (stato+anno,
libro contenuto, elenco pratiche incollato, o SELECT libera che restituisce id ordine),
seleziona le righe (una email per ordine, al venditore), sceglie un template dal tab
"Template Email" o scrive oggetto/corpo a mano con segnaposto, vede l'anteprima e invia.
L'invio è sequenziale via AJAX (`api/admin/send-order-email.php`, una richiesta per
ordine) con barra di progresso; ogni invio riuscito è registrato in `order_email_log`
e la lista mostra un badge "già inviata" (avviso, non bloccante). Il merge avviene sul
testo semplice PRIMA dell'escape (`nl2br(esc_html())` + shell HTML come la newsletter
rimborsi), quindi i dati non possono iniettare HTML.
