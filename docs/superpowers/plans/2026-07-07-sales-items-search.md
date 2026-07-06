# Ricerca dettagliata vendite — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Item-level search page over sales transactions (one row per copy sold) filtering on book, pratica, seller and transaction fields, reached via a "Ricerca dettagliata" button on the sales list.

**Architecture:** New read-only admin page `sales-items-search` (GET form, no CSRF needed) backed by two new methods on `SalesTransactionManager` that join `sales_transaction_item → sales_transaction / order_item → orders → product` plus `user` twice (seller, operator), with a shared dynamic-WHERE builder. Spec: `docs/superpowers/specs/2026-07-06-sales-items-search-design.md`.

**Tech Stack:** Plain PHP (no framework), Bootstrap 4 markup, parameterized queries via `DBManager` (`$this->db->prepare($query, $params)`).

**Deviation from spec (deliberate):** the spec lists three manager methods; the plan implements two — `searchSoldItemsTotals()` already returns `item_count`, so a separate `searchSoldItemsCount()` would duplicate the same query (DRY).

## Global Constraints

- **Dual-tree:** every changed file must be mirrored to `web/htdocs/staging/<same path>`. All target files are currently **identical** between trees (verified), so after editing the main tree, copy the file over the staging one and verify with `git diff --no-index`.
- **Commit messages in Italian, NO mention of Claude/AI, NO Co-Authored-By trailer.**
- **UI text in Italian, informal "tu".**
- Output-escape every echoed value with `esc_html()`; cast ids/pratica to `int`, prices to `float`. Raw trimmed strings go into parameterized queries (PDO placeholders — safe); `esc()` (which does `mysqli_real_escape_string`+`htmlspecialchars`) must NOT be applied to search text or it corrupts apostrophes/accents in LIKE terms.
- **No test suite; `php` often not on PATH.** Verify each task with `php -l` when available (guard the call), otherwise by careful re-read. Never claim "tested".
- No DB migration: feature is read-only over existing tables.
- Column names are `p.ISBN` and `o.numPratica` (exact casing as used elsewhere in the codebase).

---

### Task 1: Search methods on `SalesTransactionManager`

**Files:**
- Modify: `web/htdocs/classes/SalesTransaction.php` (insert new methods right BEFORE the docblock `/** Check if a transaction has been refunded` at ~line 472, i.e. after the closing `}` of `getTransactionsCount()`)
- Mirror: `web/htdocs/staging/classes/SalesTransaction.php`

**Interfaces:**
- Consumes: `DBManager::$db->prepare($query, $params)` (returns array of assoc arrays).
- Produces (used by Task 2):
  - `searchSoldItems(array $filters, $offset = 0, $limit = 50)` → array of objects with properties: `item_id`, `price`, `item_refunded_at`, `transaction_id`, `sold_at`, `payment_method`, `description`, `transaction_refunded_at`, `product_name`, `isbn`, `pratica`, `seller_first_name`, `seller_last_name`, `operator_first_name`, `operator_last_name`.
  - `searchSoldItemsTotals(array $filters)` → `['item_count' => int, 'active_total' => float, 'refunded_total' => float]`.
  - `$filters` keys (all optional; empty string = ignore): `isbn`, `title`, `author`, `publisher`, `numpratica`, `seller`, `transaction_id`, `payment_method`, `date_from`, `date_to`, `operator`, `description`, `status` (`''`|`'active'`|`'refunded'`), `price_min`, `price_max`.

- [ ] **Step 1: Insert the three methods (two public + two private helpers) in `web/htdocs/classes/SalesTransaction.php`**

Insert this block immediately before the docblock of `isRefunded()`:

```php
    /**
     * FROM clause shared by the detailed sold-items search queries.
     * One row per sales_transaction_item (= one copy sold).
     */
    private function soldItemsFromClause() {
        return "
            FROM sales_transaction_item sai
            INNER JOIN sales_transaction st ON sai.sales_transaction_id = st.id
            INNER JOIN order_item oi ON sai.order_item_id = oi.id
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN product p ON oi.product_id = p.id
            LEFT JOIN user seller ON o.user_id = seller.id
            LEFT JOIN user op ON st.operator_id = op.id
        ";
    }

    /**
     * Build the WHERE clause for the detailed sold-items search.
     * All filter keys are optional; empty string means "no condition".
     * @param array $filters see searchSoldItems()
     * @param array $params output: query parameters, appended in clause order
     * @return string WHERE clause, or '' when no filter is set
     */
    private function buildSoldItemsWhere($filters, &$params) {
        $conditions = [];

        $likeColumns = [
            'isbn'        => 'p.ISBN',
            'title'       => 'p.name',
            'author'      => 'p.autori',
            'publisher'   => 'p.editore',
            'description' => 'st.description',
        ];
        foreach ($likeColumns as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $conditions[] = "$column LIKE ?";
                $params[] = '%' . $filters[$key] . '%';
            }
        }

        if (isset($filters['numpratica']) && $filters['numpratica'] !== '') {
            $conditions[] = "o.numPratica = ?";
            $params[] = (int)$filters['numpratica'];
        }

        if (isset($filters['seller']) && $filters['seller'] !== '') {
            $conditions[] = "(CONCAT(seller.first_name, ' ', seller.last_name) LIKE ? OR seller.email LIKE ?)";
            $term = '%' . $filters['seller'] . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if (isset($filters['transaction_id']) && $filters['transaction_id'] !== '') {
            $conditions[] = "st.id = ?";
            $params[] = (int)$filters['transaction_id'];
        }

        if (isset($filters['payment_method']) && $filters['payment_method'] !== '') {
            $conditions[] = "st.payment_method = ?";
            $params[] = $filters['payment_method'];
        }

        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $conditions[] = "DATE(st.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $conditions[] = "DATE(st.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (isset($filters['operator']) && $filters['operator'] !== '') {
            $conditions[] = "CONCAT(op.first_name, ' ', op.last_name) LIKE ?";
            $params[] = '%' . $filters['operator'] . '%';
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $conditions[] = "sai.refunded_at IS NULL AND st.refunded_at IS NULL";
            } elseif ($filters['status'] === 'refunded') {
                $conditions[] = "(sai.refunded_at IS NOT NULL OR st.refunded_at IS NOT NULL)";
            }
        }

        if (isset($filters['price_min']) && $filters['price_min'] !== '') {
            $conditions[] = "sai.price >= ?";
            $params[] = (float)$filters['price_min'];
        }

        if (isset($filters['price_max']) && $filters['price_max'] !== '') {
            $conditions[] = "sai.price <= ?";
            $params[] = (float)$filters['price_max'];
        }

        return count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';
    }

    /**
     * Detailed search over sold items: one row per copy sold, with book,
     * pratica, seller, operator and transaction data.
     * @param array $filters optional keys: isbn, title, author, publisher,
     *   numpratica, seller, transaction_id, payment_method, date_from, date_to,
     *   operator, description, status ('active'|'refunded'), price_min, price_max
     * @param int $offset
     * @param int $limit
     * @return array of objects
     */
    public function searchSoldItems($filters, $offset = 0, $limit = 50) {
        $params = [];
        $whereClause = $this->buildSoldItemsWhere($filters, $params);
        $params[] = (int)$offset;
        $params[] = (int)$limit;

        $query = "
            SELECT
                sai.id as item_id,
                sai.price,
                sai.refunded_at as item_refunded_at,
                st.id as transaction_id,
                st.created_at as sold_at,
                st.payment_method,
                st.description,
                st.refunded_at as transaction_refunded_at,
                p.name as product_name,
                p.ISBN as isbn,
                o.numPratica as pratica,
                seller.first_name as seller_first_name,
                seller.last_name as seller_last_name,
                op.first_name as operator_first_name,
                op.last_name as operator_last_name
            " . $this->soldItemsFromClause() . "
            $whereClause
            ORDER BY st.created_at DESC, sai.id DESC
            LIMIT ?, ?
        ";

        $results = $this->db->prepare($query, $params);
        $items = [];
        foreach ($results as $result) {
            $items[] = (object)$result;
        }
        return $items;
    }

    /**
     * Count and totals for the detailed sold-items search (same filters).
     * An item counts as refunded when either the item or its whole
     * transaction has been refunded.
     * @param array $filters see searchSoldItems()
     * @return array ['item_count' => int, 'active_total' => float, 'refunded_total' => float]
     */
    public function searchSoldItemsTotals($filters) {
        $params = [];
        $whereClause = $this->buildSoldItemsWhere($filters, $params);

        $query = "
            SELECT
                COUNT(*) as item_count,
                COALESCE(SUM(CASE WHEN sai.refunded_at IS NULL AND st.refunded_at IS NULL THEN sai.price ELSE 0 END), 0) as active_total,
                COALESCE(SUM(CASE WHEN sai.refunded_at IS NOT NULL OR st.refunded_at IS NOT NULL THEN sai.price ELSE 0 END), 0) as refunded_total
            " . $this->soldItemsFromClause() . "
            $whereClause
        ";

        $results = $this->db->prepare($query, $params);
        return [
            'item_count'     => isset($results[0]['item_count']) ? (int)$results[0]['item_count'] : 0,
            'active_total'   => isset($results[0]['active_total']) ? (float)$results[0]['active_total'] : 0.0,
            'refunded_total' => isset($results[0]['refunded_total']) ? (float)$results[0]['refunded_total'] : 0.0,
        ];
    }
```

- [ ] **Step 2: Syntax-check if PHP is available**

Run (PowerShell):
```powershell
if (Get-Command php -ErrorAction SilentlyContinue) { php -l web/htdocs/classes/SalesTransaction.php } else { "php not on PATH - verified by re-read" }
```
Expected: `No syntax errors detected` (or the fallback message; in that case re-read the inserted block checking braces/semicolons).

- [ ] **Step 3: Mirror to staging**

Run (PowerShell):
```powershell
Copy-Item web/htdocs/classes/SalesTransaction.php web/htdocs/staging/classes/SalesTransaction.php -Force
git diff --no-index web/htdocs/classes/SalesTransaction.php web/htdocs/staging/classes/SalesTransaction.php
```
Expected: no diff output (exit 0). (Blind copy is safe: files verified identical before this work.)

- [ ] **Step 4: Commit**

```powershell
git add web/htdocs/classes/SalesTransaction.php web/htdocs/staging/classes/SalesTransaction.php
git commit -m "Vendite: metodi di ricerca dettagliata per libro venduto (searchSoldItems)"
```

---

### Task 2: Page `sales-items-search.php` + routing whitelist

**Files:**
- Create: `web/htdocs/admin/pages/sales-items-search.php`
- Modify: `web/htdocs/admin/index.php:25` (whitelist `$allowedPages`)
- Mirror both: `web/htdocs/staging/admin/pages/sales-items-search.php`, `web/htdocs/staging/admin/index.php`

**Interfaces:**
- Consumes: `SalesTransactionManager::searchSoldItems($filters, $offset, $limit)` and `searchSoldItemsTotals($filters)` from Task 1 (exact filter keys and returned property names listed there); `SalesTransactionManager::getPaymentMethods()`; globals `ROOT_URL`, helpers `esc_html()`.
- Produces: admin page reachable at `admin/?page=sales-items-search` (used by Task 3's button). Pagination uses GET param `p` (the `page` param is the router's).

- [ ] **Step 1: Add `'sales-items-search'` to the whitelist in `web/htdocs/admin/index.php`**

Change line 25 from:
```php
    'products-list', 'profile', 'profiles-list', 'sales-transactions',
```
to:
```php
    'products-list', 'profile', 'profiles-list', 'sales-items-search', 'sales-transactions',
```

- [ ] **Step 2: Create `web/htdocs/admin/pages/sales-items-search.php` with this exact content**

```php
<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Access control: reachable only via admin/index.php, which restricts to
  // user_type 'admin' or 'pwuser'.

  $salesMgr = new SalesTransactionManager();
  $paymentMethods = SalesTransactionManager::getPaymentMethods();

  // Read-only search: GET only, no CSRF. Raw trimmed values feed parameterized
  // queries; esc_html() is applied on output.
  $filters = [
    'isbn'           => isset($_GET['isbn']) ? trim($_GET['isbn']) : '',
    'title'          => isset($_GET['title']) ? trim($_GET['title']) : '',
    'author'         => isset($_GET['author']) ? trim($_GET['author']) : '',
    'publisher'      => isset($_GET['publisher']) ? trim($_GET['publisher']) : '',
    'numpratica'     => (isset($_GET['numpratica']) && $_GET['numpratica'] !== '') ? (int)$_GET['numpratica'] : '',
    'seller'         => isset($_GET['seller']) ? trim($_GET['seller']) : '',
    'transaction_id' => (isset($_GET['transaction_id']) && $_GET['transaction_id'] !== '') ? (int)$_GET['transaction_id'] : '',
    'payment_method' => (isset($_GET['payment_method']) && isset($paymentMethods[$_GET['payment_method']])) ? $_GET['payment_method'] : '',
    'date_from'      => isset($_GET['date_from']) ? trim($_GET['date_from']) : '',
    'date_to'        => isset($_GET['date_to']) ? trim($_GET['date_to']) : '',
    'operator'       => isset($_GET['operator']) ? trim($_GET['operator']) : '',
    'description'    => isset($_GET['description']) ? trim($_GET['description']) : '',
    'status'         => (isset($_GET['status']) && in_array($_GET['status'], ['active', 'refunded'], true)) ? $_GET['status'] : '',
    'price_min'      => (isset($_GET['price_min']) && $_GET['price_min'] !== '') ? (float)$_GET['price_min'] : '',
    'price_max'      => (isset($_GET['price_max']) && $_GET['price_max'] !== '') ? (float)$_GET['price_max'] : '',
  ];

  $pageNum = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
  $perPage = 50;
  $offset = ($pageNum - 1) * $perPage;

  $items = $salesMgr->searchSoldItems($filters, $offset, $perPage);
  $totals = $salesMgr->searchSoldItemsTotals($filters);
  $totalItems = $totals['item_count'];
  $totalPages = (int)ceil($totalItems / $perPage);

  // Query string of the active filters, reused by the pagination links.
  $activeFilters = array_filter($filters, function ($v) { return $v !== ''; });
  $baseQuery = http_build_query(array_merge(['page' => 'sales-items-search'], $activeFilters));
?>

<h1>Ricerca dettagliata vendite</h1>

<div class="row mb-4">
  <div class="col-md-6">
    <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Gestione Vendite
    </a>
  </div>
</div>

<!-- Search form -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-search-plus"></i> Filtri di ricerca
  </div>
  <div class="card-body">
    <form method="get">
      <input type="hidden" name="page" value="sales-items-search">

      <h6 class="text-muted text-uppercase">Libro</h6>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label for="isbn">ISBN</label>
          <input type="text" name="isbn" id="isbn" class="form-control" value="<?php echo esc_html($filters['isbn']); ?>">
        </div>
        <div class="form-group col-md-3">
          <label for="title">Titolo</label>
          <input type="text" name="title" id="title" class="form-control" value="<?php echo esc_html($filters['title']); ?>">
        </div>
        <div class="form-group col-md-3">
          <label for="author">Autori</label>
          <input type="text" name="author" id="author" class="form-control" value="<?php echo esc_html($filters['author']); ?>">
        </div>
        <div class="form-group col-md-3">
          <label for="publisher">Editore</label>
          <input type="text" name="publisher" id="publisher" class="form-control" value="<?php echo esc_html($filters['publisher']); ?>">
        </div>
      </div>

      <h6 class="text-muted text-uppercase">Pratica</h6>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label for="numpratica">Numero pratica</label>
          <input type="number" min="1" name="numpratica" id="numpratica" class="form-control" value="<?php echo esc_html($filters['numpratica']); ?>">
        </div>
        <div class="form-group col-md-5">
          <label for="seller">Venditore (nome o email)</label>
          <input type="text" name="seller" id="seller" class="form-control" value="<?php echo esc_html($filters['seller']); ?>">
        </div>
      </div>

      <h6 class="text-muted text-uppercase">Vendita</h6>
      <div class="form-row">
        <div class="form-group col-md-2">
          <label for="transaction_id">ID vendita</label>
          <input type="number" min="1" name="transaction_id" id="transaction_id" class="form-control" value="<?php echo esc_html($filters['transaction_id']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label for="payment_method">Metodo pagamento</label>
          <select name="payment_method" id="payment_method" class="form-control">
            <option value="">-- Tutti --</option>
            <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
              <option value="<?php echo esc_html($methodKey); ?>" <?php echo $filters['payment_method'] == $methodKey ? 'selected' : ''; ?>>
                <?php echo esc_html($methodLabel); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label for="date_from">Dal</label>
          <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo esc_html($filters['date_from']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label for="date_to">Al</label>
          <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo esc_html($filters['date_to']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label for="status">Stato</label>
          <select name="status" id="status" class="form-control">
            <option value="">-- Tutte --</option>
            <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Solo attive</option>
            <option value="refunded" <?php echo $filters['status'] === 'refunded' ? 'selected' : ''; ?>>Solo rimborsate</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label for="operator">Operatore</label>
          <input type="text" name="operator" id="operator" class="form-control" value="<?php echo esc_html($filters['operator']); ?>">
        </div>
        <div class="form-group col-md-3">
          <label for="description">Descrizione</label>
          <input type="text" name="description" id="description" class="form-control" value="<?php echo esc_html($filters['description']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label for="price_min">Prezzo min (&euro;)</label>
          <input type="number" step="0.01" min="0" name="price_min" id="price_min" class="form-control" value="<?php echo esc_html($filters['price_min']); ?>">
        </div>
        <div class="form-group col-md-2">
          <label for="price_max">Prezzo max (&euro;)</label>
          <input type="number" step="0.01" min="0" name="price_max" id="price_max" class="form-control" value="<?php echo esc_html($filters['price_max']); ?>">
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cerca</button>
      <a href="<?php echo ROOT_URL; ?>admin/?page=sales-items-search" class="btn btn-secondary">Reset</a>
    </form>
  </div>
</div>

<!-- Results summary -->
<div class="card mb-4">
  <div class="card-header bg-info text-white">
    <i class="fas fa-calculator"></i> Risultati
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-4">
        <h5><?php echo $totalItems; ?> <?php echo $totalItems == 1 ? 'copia trovata' : 'copie trovate'; ?></h5>
      </div>
      <div class="col-md-4">
        <h5>Totale attivo: <strong class="text-success">&euro; <?php echo number_format($totals['active_total'], 2, ',', '.'); ?></strong></h5>
      </div>
      <?php if ($totals['refunded_total'] > 0): ?>
        <div class="col-md-4">
          <h5>Rimborsato: <strong class="text-danger">&euro; <?php echo number_format($totals['refunded_total'], 2, ',', '.'); ?></strong></h5>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Results table -->
<?php if (count($items) > 0): ?>
<div class="table-responsive">
  <table class="table table-hover table-bordered">
    <thead class="thead-light">
      <tr>
        <th>Data/Ora</th>
        <th>Vendita #</th>
        <th>Libro</th>
        <th>ISBN</th>
        <th>Pratica</th>
        <th>Venditore</th>
        <th>Prezzo</th>
        <th>Metodo</th>
        <th>Stato</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): $itemRefunded = !empty($item->item_refunded_at) || !empty($item->transaction_refunded_at); ?>
        <tr class="<?php echo $itemRefunded ? 'table-danger' : ''; ?>">
          <td><?php echo date('d/m/Y H:i', strtotime($item->sold_at)); ?></td>
          <td>
            <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-view&id=<?php echo (int)$item->transaction_id; ?>">
              #<?php echo (int)$item->transaction_id; ?>
            </a>
          </td>
          <td><?php echo esc_html($item->product_name); ?></td>
          <td><?php echo esc_html($item->isbn); ?></td>
          <td><?php echo (int)$item->pratica; ?></td>
          <td>
            <?php if ($item->seller_first_name): ?>
              <?php echo esc_html($item->seller_first_name . ' ' . $item->seller_last_name); ?>
            <?php else: ?>
              <span class="text-muted">-</span>
            <?php endif; ?>
          </td>
          <td class="text-right">&euro; <?php echo number_format($item->price, 2, ',', '.'); ?></td>
          <td>
            <?php
              $badgeClass = 'badge-secondary';
              switch ($item->payment_method) {
                case 'cash': $badgeClass = 'badge-success'; break;
                case 'POS': $badgeClass = 'badge-primary'; break;
                case 'satispay': $badgeClass = 'badge-warning'; break;
                case 'paypal': $badgeClass = 'badge-info'; break;
              }
            ?>
            <span class="badge <?php echo $badgeClass; ?>">
              <?php echo esc_html($paymentMethods[$item->payment_method] ?? $item->payment_method); ?>
            </span>
          </td>
          <td>
            <?php if ($itemRefunded): ?>
              <span class="badge badge-danger">Rimborsata</span>
            <?php else: ?>
              <span class="badge badge-success">Attiva</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-view&id=<?php echo (int)$item->transaction_id; ?>" class="btn btn-sm btn-info" title="Vedi vendita">
              <i class="fas fa-eye"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php if ($pageNum > 1): ?>
      <li class="page-item">
        <a class="page-link" href="<?php echo ROOT_URL; ?>admin/?<?php echo $baseQuery; ?>&p=<?php echo $pageNum - 1; ?>">&laquo; Precedente</a>
      </li>
    <?php endif; ?>

    <?php for ($i = max(1, $pageNum - 2); $i <= min($totalPages, $pageNum + 2); $i++): ?>
      <li class="page-item <?php echo $i == $pageNum ? 'active' : ''; ?>">
        <a class="page-link" href="<?php echo ROOT_URL; ?>admin/?<?php echo $baseQuery; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
      </li>
    <?php endfor; ?>

    <?php if ($pageNum < $totalPages): ?>
      <li class="page-item">
        <a class="page-link" href="<?php echo ROOT_URL; ?>admin/?<?php echo $baseQuery; ?>&p=<?php echo $pageNum + 1; ?>">Successiva &raquo;</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>

<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessun libro trovato con i filtri selezionati.
  </div>
<?php endif; ?>
```

- [ ] **Step 3: Syntax-check if PHP is available**

```powershell
if (Get-Command php -ErrorAction SilentlyContinue) { php -l web/htdocs/admin/pages/sales-items-search.php } else { "php not on PATH - verified by re-read" }
```
Expected: `No syntax errors detected` (or fallback; then re-read checking PHP open/close tag pairing in the alternative-syntax blocks).

- [ ] **Step 4: Mirror both files to staging**

```powershell
Copy-Item web/htdocs/admin/pages/sales-items-search.php web/htdocs/staging/admin/pages/sales-items-search.php -Force
Copy-Item web/htdocs/admin/index.php web/htdocs/staging/admin/index.php -Force
git diff --no-index web/htdocs/admin/index.php web/htdocs/staging/admin/index.php
```
Expected: no diff (files verified identical before this work, so full copy of index.php is safe).

- [ ] **Step 5: Commit**

```powershell
git add web/htdocs/admin/pages/sales-items-search.php web/htdocs/admin/index.php web/htdocs/staging/admin/pages/sales-items-search.php web/htdocs/staging/admin/index.php
git commit -m "Vendite: pagina Ricerca dettagliata per libro, pratica e venditore"
```

---

### Task 3: "Ricerca dettagliata" button on the sales list

**Files:**
- Modify: `web/htdocs/admin/pages/sales-transactions.php:97-100` (Filtri card header)
- Mirror: `web/htdocs/staging/admin/pages/sales-transactions.php`

**Interfaces:**
- Consumes: route `admin/?page=sales-items-search` created in Task 2.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Edit the Filtri card header**

In `web/htdocs/admin/pages/sales-transactions.php`, change:
```php
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-filter"></i> Filtri
  </div>
```
to:
```php
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-filter"></i> Filtri
    <a href="<?php echo ROOT_URL; ?>admin/?page=sales-items-search" class="btn btn-sm btn-outline-primary float-right">
      <i class="fas fa-search-plus"></i> Ricerca dettagliata
    </a>
  </div>
```

- [ ] **Step 2: Mirror to staging**

```powershell
Copy-Item web/htdocs/admin/pages/sales-transactions.php web/htdocs/staging/admin/pages/sales-transactions.php -Force
git diff --no-index web/htdocs/admin/pages/sales-transactions.php web/htdocs/staging/admin/pages/sales-transactions.php
```
Expected: no diff.

- [ ] **Step 3: Commit**

```powershell
git add web/htdocs/admin/pages/sales-transactions.php web/htdocs/staging/admin/pages/sales-transactions.php
git commit -m "Gestione Vendite: bottone Ricerca dettagliata nella card Filtri"
```

---

### Task 4: Knowledge-base update

**Files:**
- Modify: `context/03-codebase-map.md:42-44` (admin sales pages list)
- Modify: `context/05-domain-workflows.md` (§B, sales-transactions workflow)

(Docs live only at repo root — do NOT mirror into `web/htdocs/`.)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing — docs only.

- [ ] **Step 1: Update `context/03-codebase-map.md`**

Change:
```markdown
- **Sales (current):** `sales-transactions.php` (dashboard), `sales-transaction-new.php`
  (create sale), `sales-transaction-view.php` (detail + refunds),
  `sales-transaction-receipt.php` (confirmation → PDF). Help: `help-sales-transactions.php`.
```
to:
```markdown
- **Sales (current):** `sales-transactions.php` (dashboard), `sales-transaction-new.php`
  (create sale), `sales-transaction-view.php` (detail + refunds),
  `sales-transaction-receipt.php` (confirmation → PDF), `sales-items-search.php`
  (Ricerca dettagliata: item-level search, one row per copy sold, filters on book/
  pratica/seller/transaction; linked from the Filtri card). Help: `help-sales-transactions.php`.
```

- [ ] **Step 2: Update `context/05-domain-workflows.md` §B**

In section "## B. Selling at the till", after the numbered list (the last numbered entry is `4. **Refunds** ...` ending with the restore-to-`vendere` sentence), add this paragraph:
```markdown
**Detailed search** (`sales-items-search.php`, "Ricerca dettagliata" button in the Filtri
card of `sales-transactions.php`): item-level search — one row per copy sold — joining
`sales_transaction_item → order_item → orders → product` (+ seller/operator users).
Filters on book (ISBN/titolo/autori/editore), pratica (numPratica/venditore) and
transaction (id/metodo/date/operatore/descrizione/stato/prezzo). Backed by
`SalesTransactionManager::searchSoldItems()` / `searchSoldItemsTotals()`; an item counts
as refunded when either the item or its whole transaction is refunded.
```

- [ ] **Step 3: Commit**

```powershell
git add context/03-codebase-map.md context/05-domain-workflows.md
git commit -m "Docs: ricerca dettagliata vendite nella knowledge base"
```

---

### Manual verification (after all tasks — on staging site with DB access)

No automated test suite exists. Checks to run in the browser on staging:

1. `admin/?page=sales-transactions` → the Filtri card header shows "Ricerca dettagliata" on the right; click navigates to the new page.
2. New page with no filters → most recent sold copies listed, summary card shows total count.
3. Filter by an ISBN known to be sold → only that book's copies.
4. Combine ISBN + numero pratica (the spec's example query) → intersection only.
5. Filter by venditore (partial surname) and by stato = "Solo rimborsate" → refunded rows are `table-danger` with badge "Rimborsata"; totals split active vs refunded.
6. Pagination: apply a broad filter with > 50 results, go to page 2 → filters preserved in links.
7. Eye button / Vendita # link → opens `sales-transaction-view&id=<id>` for that row's transaction.
8. `admin/?page=sales-items-search` typed directly while logged out or as `regular` user → redirected (router gate).
