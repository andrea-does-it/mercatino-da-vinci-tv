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
