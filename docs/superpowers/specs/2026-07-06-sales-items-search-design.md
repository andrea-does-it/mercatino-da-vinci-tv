# Ricerca dettagliata vendite — Design

**Data:** 2026-07-06
**Stato:** approvato

## Obiettivo

Oggi la pagina *Gestione Vendite* (`admin/?page=sales-transactions`) filtra solo sui campi
di testata (`payment_method`, intervallo date). Serve una ricerca che filtri anche sui dati
di **dettaglio** — libro venduto, pratica, venditore — e che restituisca **una riga per
copia venduta**, equivalente a:

```sql
SELECT ...
FROM product p
INNER JOIN order_item oi          ON oi.product_id = p.id
INNER JOIN orders o               ON oi.order_id = o.id
INNER JOIN sales_transaction_item sai ON sai.order_item_id = oi.id
INNER JOIN sales_transaction st   ON sai.sales_transaction_id = st.id
WHERE p.ISBN = '...' AND o.numPratica = ...
```

## Decisioni prese

- **Righe risultato = libri venduti** (item-level), non transazioni.
- **Pagina dedicata** `admin/?page=sales-items-search`, non modal né pannello a scomparsa.
- **Bottone di accesso** "Ricerca dettagliata" nell'header della card **Filtri** di
  `sales-transactions.php`, allineato a destra (`btn-sm btn-outline-primary float-right`,
  icona `fa-search-plus`). Non accanto a "Nuova Vendita" (che è un'azione, non una ricerca).
- Sola lettura: **nessuna migrazione DB**.

## Componenti

### 1. Nuova pagina `web/htdocs/admin/pages/sales-items-search.php`
- Aggiunta alla whitelist `$allowedPages` di `web/htdocs/admin/index.php`.
- Titolo: **"Ricerca dettagliata vendite"**; link "← Gestione Vendite" per tornare alla lista.
- Form **GET** (nessun CSRF necessario: sola lettura), campi raggruppati:
  - **Libro:** ISBN (LIKE parziale), Titolo (`product.name`, LIKE), Autori (`autori`, LIKE),
    Editore (`editore`, LIKE)
  - **Pratica:** Numero pratica (`orders.numPratica`, esatto, int), Venditore
    (LIKE su `CONCAT(first_name,' ',last_name)` e `email` dell'utente `orders.user_id`)
  - **Vendita:** ID vendita (esatto, int), Metodo pagamento (select), Data dal/al
    (`DATE(st.created_at)`), Operatore (LIKE su nome/cognome di `st.operator_id`),
    Descrizione (`st.description`, LIKE), Stato (tutte / solo attive / solo rimborsate),
    Prezzo min/max (`sai.price`)
- Bottoni: **Cerca** + **Reset** (come lo stile della card Filtri esistente).
- Tutti gli input riletti con `esc()`, id/pratica cast a `int`, prezzi a `float`.

### 2. Tabella risultati
Colonne: Data/ora vendita, Vendita # (link a `sales-transaction-view&id=`), Libro (titolo),
ISBN, Pratica (numPratica), Venditore, Prezzo (€), Metodo pagamento (badge come lista
vendite), Stato (badge Attiva/Rimborsata), Azioni.

- **Azioni:** bottone occhio (`btn-sm btn-info`, icona `fa-eye`) che apre
  `admin/?page=sales-transaction-view&id=<sales_transaction_id>` — dal dettaglio di ogni
  riga si arriva sempre alla scheda completa della vendita (stesso pattern della lista
  vendite). Anche il numero in colonna "Vendita #" è linkato alla stessa pagina.

- Un item è **rimborsato** se `sai.refunded_at IS NOT NULL OR st.refunded_at IS NOT NULL`
  (rimborso parziale o totale); riga evidenziata `table-danger` come nella lista vendite.
- Sotto la tabella (o in una card riassuntiva): **N. copie trovate** e **totale €**
  (somma `sai.price` delle sole righe attive, indicando a parte il totale rimborsato).
- Paginazione a **50 righe**, stile identico a `sales-transactions.php`, con i parametri
  di filtro propagati nei link.
- Senza alcun filtro impostato la pagina mostra comunque i libri venduti più recenti
  (nessun filtro ≠ nessuna query): utile come registro cronologico delle copie vendute.
- Nessun risultato → alert info "Nessun libro trovato con i filtri selezionati."

### 3. Metodi in `SalesTransactionManager` (`classes/SalesTransaction.php`)
Stile identico a `getTransactionsPaginated` (WHERE dinamico, query parametrizzate):

- `searchSoldItems(array $filters, $offset, $limit)` → righe item-level con i join sopra
  più `LEFT JOIN user seller ON o.user_id = seller.id` e
  `LEFT JOIN user op ON st.operator_id = op.id`; `ORDER BY st.created_at DESC, sai.id DESC`.
- `searchSoldItemsCount(array $filters)` → `COUNT(*)` con gli stessi filtri.
- `searchSoldItemsTotals(array $filters)` → somma prezzi (attivi e rimborsati) per il
  riepilogo.

`$filters` è un array associativo con chiavi opzionali:
`isbn, title, author, publisher, numpratica, seller, transaction_id, payment_method,
date_from, date_to, operator, description, status, price_min, price_max`.
Ogni chiave assente/vuota non genera condizioni. Il filtro `status`:
`active` → `sai.refunded_at IS NULL AND st.refunded_at IS NULL`;
`refunded` → `(sai.refunded_at IS NOT NULL OR st.refunded_at IS NOT NULL)`.

### 4. Bottone in `sales-transactions.php`
Nell'header della card Filtri:

```html
<div class="card-header">
  <i class="fas fa-filter"></i> Filtri
  <a href="...admin/?page=sales-items-search"
     class="btn btn-sm btn-outline-primary float-right">
    <i class="fas fa-search-plus"></i> Ricerca dettagliata
  </a>
</div>
```

## Dual-tree e documentazione
- Ogni file modificato/creato va replicato in `web/htdocs/staging/<path>` (verificando le
  differenze tra i due alberi prima di copiare).
- Aggiornare `context/03-codebase-map.md` (nuova pagina admin) e
  `context/05-domain-workflows.md` (workflow vendite: ricerca dettagliata);
  snapshot in `context/INDEX.md` se necessario.

## Fuori scope (YAGNI)
- Export CSV/PDF dei risultati.
- Rimborso del singolo item direttamente dai risultati (si passa dalla vista vendita).
- Ricerca su vendite legacy (`order_item1`).

## Test / verifica
Nessuna test suite: verifica manuale su staging — ricerca per ISBN, per pratica,
combinazioni (ISBN + pratica come nella query di esempio), stato rimborsata,
paginazione con filtri attivi, pagina senza filtri (mostra le vendite più recenti).
