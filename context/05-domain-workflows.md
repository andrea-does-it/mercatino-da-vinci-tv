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
- **Pratica 100 = the bookshop's own stock.** `SellerRefundManager::BOOKSHOP_PRATICA` (=100).
  Books under this pratica belong to the mercatino itself (loaded by
  `sql/202606200001_load_pratica_100_libri_contati.sql`), so selling them is **net income
  for the Comitato and is never owed to a seller**. The whole `SellerRefundManager` excludes
  it, in two layers:
  - *amounts* — `AND o.numPratica <> 100` on every query that sums `single_price`
    (`calculateAmountOwed()`, `getSellersWithoutRefundRecord()`, `getRefundEstimateByIban()`)
    and on the `pratica_numbers`/`pratica_count` display subqueries and the unsold-book
    counts (`userHasUnsoldBooks()`, `getUnsoldBooksCount()`, `getReportData()`);
  - *rows* — `sqlIsRealSeller('sr')` emits an `EXISTS` requiring the seller to own at least
    one pratica other than 100, applied to `getRefundsForYear()`, `getYearSummary()`,
    `getNewsletterStats()`, `getSellersForNewsletter()`, `getReportData()` and the two
    profile-default backfill queries. This is what keeps the bookshop out of the lists even
    if a `seller_refund` row already exists for it — those queries read `seller_refund`
    directly and never see a pratica number.
  **Sales views deliberately still include pratica 100** (`SalesTransaction.php`, Gestione
  Vendite, Ricerca dettagliata, incassi): that money really was taken in. If you add a new
  refund query, it must carry one of the two conditions above.
- `seller-refunds.php` lists per-seller payout records for a year; `createRecordsForYear()`
  / `getOrCreateForUserYear()` create them.
- **Profile defaults on creation.** `createRecordsForYear()` seeds each new record from the
  seller's profile: `donate_unsold` from `user.donate_books`, and `payment_preference` =
  `wire_transfer` when the user has an IBAN, `cash` otherwise. `preference_set_at` stays
  NULL on purpose — it marks a preference the *seller* set from the landing page, not an
  admin default. Consequence: the "Preferenza: Non impostata" filter / "Senza Preferenza"
  stat are now near-empty for new records; use the IBAN / Donazione / "Da contattare"
  filters to find who still owes an answer.
- **Backfill for existing records.** `applyUserDefaultsToYear($year)` (button *"Applica
  Preferenze dal Profilo (N)"* on `seller-refunds.php`, count from
  `countRecordsNeedingUserDefaults()`) applies the same defaults to rows created earlier.
  It updates **only** rows whose column is still NULL, so a preference the seller already
  expressed is never overwritten. Note `createRecordsForYear()` always writes a
  `donate_unsold` (0 or 1), so in practice only `getOrCreateForUserYear()` rows have it NULL.
- `seller-refund-report.php` is the **closing situation report** (sold vs unsold per seller,
  amounts owed, payment preference, and a **"Donazione"** column from `donate_unsold`).
  - **Filtri**: gli stessi della pagina newsletter (Newsletter / Preferenza / IBAN /
    Donazione / "Da contattare") **più Stato** del rimborso. Le condizioni SQL vengono da
    `SellerRefundManager::sellerFilterConditions()`, condiviso con
    `getSellersForNewsletter()`, così le due pagine non possono divergere;
    `getReportData($year, $filters)` le applica. Quando un filtro è attivo il titolo di
    stampa riporta "(elenco filtrato: N venditori)": su carta un report filtrato sarebbe
    altrimenti indistinguibile da quello completo.
  - **Formati di stampa**: `A4 Landscape`, `A3 Landscape` e `A4 Sintetico` (A4 landscape in
    cui "Libri da Rendere" mostra **solo il numero** di libri invenduti e la colonna
    **IBAN è nascosta** — intestazione compresa, via classe `.iban-col` su `<th>` e `<td>`:
    il codice non serve nel riepilogo e la colonna Pagamento dice già bonifico/contanti).
    La cella dei libri contiene entrambe le versioni (`.books-list` / `.books-count`) e il
    formato ne mostra una via classe `.report-compact` sulla tabella.
    **Anche l'export Excel segue il formato scelto**: `innerText` ignora `display:none`
    (quindi esporta il numero invece dell'elenco) e `exportToExcel()` salta del tutto le
    celle con `display:none` calcolato, così una colonna nascosta non diventa una colonna
    vuota nel CSV.
  - **Dimensione carattere** selezionabile (6–14pt), applicata sia a schermo sia in stampa;
    cambiando formato torna al default di quel formato (A4 8pt, A3 10pt, Sintetico 9pt).
  - Misure e formato vivono in **CSS custom properties** (`--report-font-size`,
    `--report-cell-padding`, …) definite una sola volta nel blocco `<style>`; il JS aggiorna
    solo le variabili. L'unica regola riscritta da JS è `@page { size }` (in `<style
    id="pageRule">`), perché `size` non può leggere una variabile CSS. **Non duplicare le
    regole CSS nel JS**: prima `setLayout()` rigenerava l'intero foglio di stile come
    template literal e ogni regola andava modificata in due posti.
    La scelta di formato/carattere è salvata in `sessionStorage` perché cambiare un filtro
    ricarica la pagina.
- `seller-refund-view.php` records payments and shows the donation preference.
- `seller-refunds.php` also shows a read-only **"Stima Rimborsi per Modalità"** card:
  `getRefundEstimateByIban($year)` splits the year's payout between *Bonifico* (seller has
  `user.iban`) and *Contanti* (no IBAN). It is computed live from the sold books — same
  seller set and amount as `getSellersWithoutRefundRecord()` — so it works **before** any
  `seller_refund` record exists, ignores the page filters, and ignores `amount_paid`.
  IBAN presence is tested in SQL (`iban <> ''`), so the encrypted value is never decrypted.
- **Contanti da Rimborsare** (card su `seller-refunds.php`): `getYearSummary()` espone
  `cash_outstanding` = `SUM(amount_owed - amount_paid)` sui record con preferenza `cash`,
  più `cash_outstanding_sellers` (quanti hanno ancora un residuo). È il contante ancora da
  consegnare, diverso dalla card "Stima Rimborsi per Modalità" che è una proiezione basata
  sulla presenza dell'IBAN e non guarda i pagamenti già fatti.
- **`seller-orders-report.php` — "Riepilogo Pratiche"** (link da `seller-refunds.php`;
  **va aggiunto a `$allowedPages` in `admin/index.php`**, altrimenti il router ricade su
  dashboard). `getOrdersOverview($year)` legge direttamente `order_item.status`, quindi è
  **indipendente sia da `seller_refund` sia da `sales_transaction`**: mostra ogni pratica
  con libri anche se il venditore non ha un record di rimborso e anche se la vendita non è
  stata registrata in Gestione Vendite — serve proprio a riscontrare le altre pagine.
  È il **foglio di ritiro** stampato: **una riga per venditore**, in **A3 landscape**, con
  le colonne compilate in automatico (Nome Venditore, Lista pratiche, Libri da rendere,
  Totale dovuto, Contanti/Bonifico, Dona i libri) e quelle da riempire a mano al banco
  (Nome delegato, Tipo documento, Numero e data rilascio, Data ritiro, Firma).
  *Libri da rendere* elenca **un libro per riga** come `pratica/titolo`, ordinati per
  pratica e poi titolo; per chi dona, i titoli sono elencati con la nota che restano al
  Comitato. Due selettori di dimensione carattere separati (tabella ed elenco libri),
  perché l'elenco è il contenuto più fitto.
  *Contanti/Bonifico* usa la preferenza salvata sul record di rimborso; se non c'è, ricade
  sulla stessa regola di `createRecordsForYear()` (IBAN presente → bonifico), così il foglio
  è utilizzabile anche prima di creare i record.
  Compaiono solo i venditori che devono ritirare **denaro o libri**: chi non ha nulla da
  ritirare occuperebbe solo una riga stampata.
  Venduti per `YEAR(order_item.updated_at)` (stessa base del resto della sezione); gli
  invenduti sono quelli **attualmente** a scaffale, quindi non hanno anno. Il conteggio
  distingue *da restituire* e *donati* secondo `COALESCE(seller_refund.donate_unsold,
  user.donate_books, 0)`: i libri donati non tornano indietro.
  Pratica 100 esclusa come ovunque.
- **Ricavo del Comitato** (card su `seller-refunds.php`, `getBookshopIncome($year)`):
  incasso della pratica 100 (**intero**, i libri sono del mercatino) + **ricarico**
  trattenuto sui libri delle altre pratiche, con dettaglio espandibile per pratica.
  Calcolato dal **registro vendite**, non dalle impostazioni correnti: il ricarico di ogni
  libro è `sales_transaction_item.price - order_item.single_price`, cioè il margine
  effettivamente preso al momento della vendita — modificare `SiteSettings::totalMarkup()`
  non riscrive quindi gli anni passati. Esclude transazioni e singole righe rimborsate
  (`refunded_at IS NULL` su entrambe); l'anno è quello di `sales_transaction.created_at`.
  Una riga di **riconciliazione** segnala i libri `venduto` dell'anno non coperti da alcuna
  vendita registrata (tipicamente venduti con la vecchia procedura): non sono conteggiati,
  perché per loro non esiste un prezzo di vendita da cui ricavare il ricarico.
- **donate_books**: a standing profile preference (set in `user/pages/privacy.php` and at
  registration) meaning "donate my unsold books instead of taking them back"; it defaults
  the per-year `donate_unsold` (admin can still override per year).
- **`payment-preference.php` (public landing page, token-authenticated).** Two one-way locks,
  both enforced **server-side** — a disabled input submits nothing, so the posted values can
  never be trusted to carry them, and a forged POST must not bypass them either:
  - *IBAN on file* (`UserManager::getIBAN()` non-empty) ⇒ `$hasIban`: both radios render
    `disabled` (visible, Bonifico pre-selected, Contanti greyed via `.option-locked`) and the
    handler forces `payment_preference = 'wire_transfer'`. The seller may still edit the IBAN
    and the account holder; the fields are now **prefilled with the decrypted IBAN**
    (previously the input only echoed `$_POST`, so it loaded empty). Consequence: a seller
    with an IBAN whose saved preference was `cash` is flipped to `wire_transfer` on their
    next submit.
  - *Donation already given* (`donate_unsold` truthy) ⇒ `$donationLocked`: the checkbox
    renders checked + `disabled` and the handler forces `donateUnsold = 1`. **Undoing a
    donation requires a manual SQL UPDATE** — `seller-refund-view.php` only displays it, there
    is no admin control for it.
  Ogni invio del modulo è tracciato in `user_activity_log` (pagina admin "Registro Attività"):
  `payment_preference_set` in caso di salvataggio, `payment_preference_error` con il codice
  errore altrimenti. L'azione è attribuita a `$refund->user_id` — la pagina è autenticata dal
  token e non ha `$loggedInUser`. Il dettaglio contiene solo contesto non personale
  (anno, refund_id, preferenza, donazione, `iban: fornito|no`): **mai il valore dell'IBAN**.
- `setPaymentPreference()` mirrors a **given** donation onto `user.donate_books`
  (`updateDonateBooks()`), so the profile-based "Da contattare" newsletter filter sees the
  answer the seller gave on the landing page. Only the "yes" is propagated: writing a 0 back
  could undo a preference the seller set in their own profile after the record was created.
- `seller-refund-newsletter.php` sends the "tell us how you want to be paid" email.
  `getSellersForNewsletter($year, $newsletter, $preference, $iban, $donation, $toContactOnly)`
  filters on both `seller_refund` (newsletter sent / preference set) and the **user profile**:
  `iban` = `with`/`without`, `donation` = `yes`/`no`, plus a **"Da contattare"** checkbox that
  ORs the two negatives (no IBAN **or** no donation choice) — the intended send list, so the
  committee only mails sellers who still owe an answer. The query returns a `has_iban` flag
  and `donate_books`, shown as two table columns; the encrypted IBAN itself is never selected.
  `getNewsletterStats()` adds `no_iban_count`, `no_donation_count`, `to_contact_count`.
  The two profile conditions live in one place — `SellerRefundManager::SQL_HAS_IBAN` /
  `SQL_DONATES` (both assume the user table is aliased `u`) — reused by the filters, the
  stats, the creation defaults and the backfill so they cannot drift apart.

### Distinte SEPA (pagamento dei rimborsi con bonifico)
`seller-refund-sepa.php` ("Distinte SEPA", `admin/?page=seller-refund-sepa`, linkata da
`seller-refunds.php`) genera il file XML di bonifici multipli (SEPA CBI 04.01) da caricare
sull'home banking UniCredit del Comitato e tiene lo storico di chi è stato pagato con quale
file. Self-test di sola lettura: `admin/?page=sepa-selftest`.
- **Idonei**: `payment_preference = 'wire_transfer'`, stato `pending`/`partial`/`xmlsaved`,
  residuo (`amount_owed - amount_paid`) > 0, `sqlIsRealSeller()` (pratica 100 esclusa come nel
  resto della sezione). Esclusi con motivo: IBAN assente/non decifrabile/checksum errato, paese
  fuori area SEPA, IBAN SEPA ma **fuori dallo SEE** (`SepaCbiExport::isEeaCountry()`: EU27 +
  Islanda/Liechtenstein/Norvegia — servirebbero BIC e indirizzo del beneficiario, non gestiti:
  "paga a mano"), beneficiario mancante, **località beneficiario mancante** (solo se il
  venditore non ha né `user.iban_town` né esiste una località predefinita impostata).
- **Esito del primo caricamento reale su UniCredit (2026-10)**: il file è stato scartato
  (vedi addendum nello spec) perché mancava la coppia Località/Paese sia per l'ordinante sia
  per ogni beneficiario e perché il tag `CtgyPurp` era assente; il CUC inviato è stato
  comunque sostituito dalla banca (solo warning). Di conseguenza, dalla migrazione
  `202610020001`:
  - **Località ordinante**: setting `sepa_debtor_town` (campo "Località" nella pagina),
    obbligatoria, finisce in `Dbtr/PstlAdr/TwnNm` (prima di `Ctry`).
  - **Località beneficiario**: `user.iban_town` se presente, altrimenti il setting
    `sepa_default_creditor_town` ("Località predefinita beneficiari"); editabile per singolo
    venditore da `seller-refund-view.php` (`SepaBatchManager::saveBeneficiaryTown()`/
    `getBeneficiaryTown()`); finisce in `Cdtr/PstlAdr/TwnNm` (prima di `Ctry`) di ogni bonifico.
    Un rimborso senza alcuna delle due diventa escluso (`Località mancante`, vedi sopra).
  - **Category Purpose**: setting `sepa_category_purpose` (default `SUPP`, 4 lettere),
    obbligatorio, finisce in `CdtTrfTxInf/PmtTpInf/CtgyPurp/Cd` **di ogni bonifico** (subito
    dopo `PmtId`, prima di `Amt`) — non in `PmtInf/PmtTpInf`, che contiene solo
    `InstrPrty`/`SvcLvl` (vedi esito secondo caricamento sotto).
  - **CUC ora facoltativo**: la banca lo sostituisce comunque. Se valorizzato deve rispettare
    `/^[A-Z0-9]{1,8}$/` e viene inviato in `InitgPty/Id/OrgId/Othr`; se vuoto quel blocco
    (`Id`) è **omesso del tutto** da `InitgPty` (resta solo `Nm`).
- **Generazione** (`SepaBatchManager::createBatch()`) → i rimborsi inclusi passano a
  `xmlsaved` ("Distinta generata"); l'XML viene restituito in download e **non è mai salvato**
  su disco né in DB (solo IBAN mascherato in `sepa_batch_item`). **Rigenerazione** ammessa
  anche per rimborsi già `xmlsaved` (es. per correggere un IBAN): un rimborso può comparire in
  più batch, nuovo `MsgId` ogni volta.
- **"Segna come pagati"** (`markBatchPaid()`) registra il pagamento (`wire_transfer`,
  riferimento = `MsgId` della distinta) e porta lo stato a `completed` (o resta `partial` se
  nel frattempo `amount_owed` è cresciuto). **Rifiuta con un errore** se uno dei rimborsi del
  batch è anche in un batch **più recente e ancora `generated`**: l'operatore deve prima
  scartare quel batch più recente, oppure segnarlo come pagato — evita di pagare qui un
  rimborso che l'altra distinta sta per gestire a sua volta. Le righe già "superate" da un
  batch più recente **già `paid`** vengono invece saltate silenziosamente (contate come
  "saltati" nel messaggio di esito). Sono saltate anche le righe il cui rimborso risulta già
  `completed`/`cancelled` (es. pagato a mano nel frattempo): queste si contano a parte come
  `skipped_settled` (`markBatchPaid()` restituisce `['paid', 'skipped', 'skipped_settled']`,
  con `skipped` come totale complessivo per compatibilità), e la pagina mostra un avviso
  giallo a parte ("N rimborsi erano già stati saldati a mano...") perché vanno controllati con
  l'estratto conto per un possibile doppio pagamento.
- **`seller-refund-view.php`**: se il rimborso aperto compare in una distinta ancora
  `generated` (non pagata né scartata), il form manuale "Registra Pagamento" mostra un avviso
  rosso (con link alla distinta più recente in quello stato) per evitare di pagarlo due volte,
  a mano e poi via distinta o viceversa.
- **"Scarta"** (`discardBatch()`) riporta a `pending`/`partial` i rimborsi ancora `xmlsaved`
  del batch, a meno che non siano anche in un altro batch `generated`.
- **Selezione nella pagina "Distinte SEPA"**: "Seleziona tutti" spunta/toglie solo i rimborsi
  mai inclusi in una distinta (`data-in-batch` assente); quelli già in una distinta vanno
  spuntati/tolti singolarmente. Se all'invio del form uno di questi resta spuntato, il JS chiede
  conferma prima di generare (rischio di doppio pagamento se quella distinta è già stata
  caricata in banca).
- `createBatch()`, `markBatchPaid()` e `discardBatch()` lavorano dentro una transazione sulla
  connessione PDO di `SepaBatchManager` e, appena dentro, **rilockano con `SELECT … FOR
  UPDATE`** e ricontrollano stato/importi di batch e rimborsi coinvolti — protezione contro
  doppio submit o modifiche concorrenti (es. un pagamento registrato a mano nel frattempo).
- La forma di `ReqdExctnDt` nella 04.01 (`EXEC_DATE_NESTED` in `SepaCbiExport`, `true` →
  `<ReqdExctnDt><Dt>…</Dt></ReqdExctnDt>`): forma annidata indicata dalla documentazione
  CBI 2023 e **verificata il 2026-10-02**: il terzo caricamento UniCredit (con `CtgyPurp`
  per transazione) ha superato la validazione del formato. L'XSD CBI, se
  procurato, va in `classes/xsd/CBIPaymentRequest.00.04.01.xsd`: la validazione di schema
  viene **saltata** (non bloccata) quando il file manca.
- **Esito secondo caricamento UniCredit (2026-10, validazione XSD)**: il file con le
  correzioni di cui sopra (località, Category Purpose) è stato respinto dalla validazione
  contro l'XSD ufficiale CBI con `cvc-complex-type.2.4.a` su `CtgyPurp`: nello schema 04.01
  quel tag non è ammesso in `PmtInf/PmtTpInf` (che accetta solo `InstrPrty`/`SvcLvl`, poi
  opzionalmente `LclInstrm`) — va in `CdtTrfTxInf/PmtTpInf` di ogni bonifico, come conferma
  anche l'esempio ufficiale CBI SCT. Corretto spostando `CtgyPurp/Cd` a livello di
  transazione (dopo `PmtId`, prima di `Amt`); `PmtInf/PmtTpInf` ora contiene solo
  `InstrPrty`/`SvcLvl`. **`ReqdExctnDt` resta non verificato**: `CtgyPurp` viene prima di
  `ReqdExctnDt` nel documento, quindi la validazione si è fermata senza dire nulla sulla
  forma (annidata o no) di `ReqdExctnDt`; la forma annidata è indicata solo dalla
  documentazione CBI 2023 (SCT tracciato flusso new 2023), punto aperto ancora da chiudere
  con un caricamento UniCredit.
- Ogni azione (generazione, pagati, scarta, dati ordinante, località beneficiario) va in
  `user_activity_log` (`admin_sepa_batch_created`/`admin_sepa_batch_paid`/
  `admin_sepa_batch_discarded`/`admin_sepa_debtor_updated`/`admin_sepa_town_updated`) con id
  batch/conteggi/totale (o `user_id` per la località), **mai un IBAN in chiaro**.

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
  "check your SPAM folder" notice. Every send is recorded in the **activity log**
  (`user_activity_log`) by `send_mail()` itself via `log_email_activity()`: action
  `email_sent`/`email_failed`, detail `a: <dest>; smtp: <host>[; errore: …]; oggetto: <subject>`
  (subject last so the 500-char truncation never drops recipient/server).

## F. Email massive agli ordini (mail merge) — site_utils
Da `admin/?page=site_utils&tab=email_orders` l'admin filtra gli ordini (stato+anno,
libro contenuto, elenco pratiche incollato, o SELECT libera che restituisce id ordine),
seleziona le righe (una email per ordine, al venditore), sceglie un template dal tab
"Template Email" o scrive oggetto/corpo a mano con segnaposto, vede l'anteprima e invia.
L'invio è sequenziale via AJAX (`api/admin/send-order-email.php`, una richiesta per
ordine) con barra di progresso; ogni invio riuscito è registrato in `order_email_log`
e la lista mostra un badge "già inviata" (avviso, non bloccante). Il merge avviene sul
testo semplice PRIMA dell'escape, quindi i dati non possono iniettare HTML.

### Corpo HTML delle email (helper condiviso)
`inc/functions.php` espone `email_text_to_html($plainText)` (frammento) e
`email_html_document($plainText)` (documento completo con la shell `<html>`), usati da
`OrderEmailManager::buildHtmlBody()` e da `SellerRefundManager::buildNewsletterHtmlBody()`.
**Ordine obbligatorio: merge → `esc_html()` → link → `nl2br()`.** I link vengono creati sul
testo *già escapato*: un URL non può quindi rompere l'attributo `href` (virgolette e `&`
sono già entità) e i valori merged non possono iniettare HTML. La regex `https?://[^\s<]+`
esclude dal link la punteggiatura finale di frase (`.,:!?)]}`) ma **non** il `;`, perché
troncarlo spezzerebbe un `&amp;` in coda all'URL.
Prima di questo helper il corpo veniva costruito a mano in due punti duplicati di
`seller-refund-newsletter.php` e l'URL restava testo semplice (non cliccabile).
L'anteprima email della pagina newsletter mostra ora lo stesso HTML dell'invio, quindi il
suo output è **già escapato** e non va ri-passato in `esc_html()`.
