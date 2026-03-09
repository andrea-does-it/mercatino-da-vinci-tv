<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $salesMgr = new SalesTransactionManager();
  $paymentMethods = SalesTransactionManager::getPaymentMethods();

  // Handle delete action
  if (isset($_POST['delete_transaction']) && isset($_POST['transaction_id'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $transactionId = (int)$_POST['transaction_id'];
      if ($salesMgr->deleteTransaction($transactionId)) {
        $alertMsg = 'deleted';
      } else {
        $alertMsg = 'err';
      }
    }
  }

  // Filter parameters
  $filterPaymentMethod = isset($_GET['payment_method']) ? esc($_GET['payment_method']) : '';
  $filterDateFrom = isset($_GET['date_from']) ? esc($_GET['date_from']) : '';
  $filterDateTo = isset($_GET['date_to']) ? esc($_GET['date_to']) : '';
  $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
  $perPage = 20;
  $offset = ($page - 1) * $perPage;

  // Get transactions
  $transactions = $salesMgr->getTransactionsPaginated($offset, $perPage, $filterPaymentMethod, $filterDateFrom, $filterDateTo);
  $totalTransactions = $salesMgr->getTransactionsCount($filterPaymentMethod, $filterDateFrom, $filterDateTo);
  $totalPages = ceil($totalTransactions / $perPage);

  // Get totals for the filtered period
  $totals = $salesMgr->getSalesTotals($filterDateFrom, $filterDateTo, $filterPaymentMethod);

  // Get today's summary
  $todaySummary = $salesMgr->getTodaySummary();
?>

<h1>Gestione Vendite
  <a href="<?php echo ROOT_URL; ?>admin/?page=help-sales-transactions" class="btn btn-sm btn-outline-info ml-2" title="Guida">
    <i class="fas fa-question-circle"></i> Guida
  </a>
</h1>

<div class="row mb-4">
  <div class="col-md-6">
    <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-new" class="btn btn-success">
      <i class="fas fa-plus"></i> Nuova Vendita
    </a>
  </div>
</div>

<!-- Today's Summary -->
<div class="card mb-4">
  <div class="card-header bg-primary text-white">
    <i class="fas fa-chart-line"></i> Riepilogo Giornaliero (<?php echo date('d/m/Y'); ?>)
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-3">
        <div class="text-center">
          <h5>Totale Vendite Oggi</h5>
          <h3 class="text-success">&euro; <?php echo number_format($todaySummary['grand_total'], 2, ',', '.'); ?></h3>
          <small class="text-muted"><?php echo $todaySummary['transaction_count']; ?> transazioni</small>
        </div>
      </div>
      <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
        <div class="col-md-2">
          <div class="text-center">
            <h6><?php echo esc_html($methodLabel); ?></h6>
            <p class="mb-0">
              &euro; <?php echo isset($todaySummary['by_method'][$methodKey]) ? number_format($todaySummary['by_method'][$methodKey]['amount'], 2, ',', '.') : '0,00'; ?>
            </p>
            <small class="text-muted">
              <?php echo isset($todaySummary['by_method'][$methodKey]) ? $todaySummary['by_method'][$methodKey]['count'] : 0; ?> transazioni
            </small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-filter"></i> Filtri
  </div>
  <div class="card-body">
    <form method="get" class="form-inline">
      <input type="hidden" name="page" value="sales-transactions">
      <div class="form-group mr-3 mb-2">
        <label for="payment_method" class="mr-2">Metodo pagamento:</label>
        <select name="payment_method" id="payment_method" class="form-control">
          <option value="">-- Tutti --</option>
          <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
            <option value="<?php echo esc_html($methodKey); ?>" <?php echo $filterPaymentMethod == $methodKey ? 'selected' : ''; ?>>
              <?php echo esc_html($methodLabel); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mr-3 mb-2">
        <label for="date_from" class="mr-2">Dal:</label>
        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo esc_html($filterDateFrom); ?>">
      </div>
      <div class="form-group mr-3 mb-2">
        <label for="date_to" class="mr-2">Al:</label>
        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo esc_html($filterDateTo); ?>">
      </div>
      <button type="submit" class="btn btn-primary mb-2 mr-2"><i class="fas fa-search"></i> Filtra</button>
      <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary mb-2">Reset</a>
    </form>
  </div>
</div>

<!-- Filtered Totals (if filters are applied) -->
<?php if ($filterPaymentMethod || $filterDateFrom || $filterDateTo): ?>
<div class="card mb-4">
  <div class="card-header bg-info text-white">
    <i class="fas fa-calculator"></i> Totali Periodo Filtrato
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-4">
        <h5>Totale: <strong class="text-success">&euro; <?php echo number_format($totals['grand_total'], 2, ',', '.'); ?></strong></h5>
        <p class="text-muted"><?php echo $totals['transaction_count']; ?> transazioni</p>
      </div>
      <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
        <?php if (isset($totals['by_method'][$methodKey])): ?>
          <div class="col-md-2">
            <h6><?php echo esc_html($methodLabel); ?></h6>
            <p>&euro; <?php echo number_format($totals['by_method'][$methodKey]['amount'], 2, ',', '.'); ?></p>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Transactions List -->
<?php if (count($transactions) > 0): ?>
<table id="salesTable" class="table table-hover table-bordered">
  <thead class="thead-light">
    <tr>
      <th>#</th>
      <th>Data/Ora</th>
      <th>Metodo Pagamento</th>
      <th>Descrizione</th>
      <th>N. Articoli</th>
      <th>Totale</th>
      <th>Operatore</th>
      <th>Azioni</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($transactions as $transaction): ?>
      <tr>
        <td><?php echo esc_html($transaction->id); ?></td>
        <td><?php echo date('d/m/Y H:i', strtotime($transaction->created_at)); ?></td>
        <td>
          <?php
            $badgeClass = 'badge-secondary';
            switch ($transaction->payment_method) {
              case 'cash': $badgeClass = 'badge-success'; break;
              case 'POS': $badgeClass = 'badge-primary'; break;
              case 'satispay': $badgeClass = 'badge-warning'; break;
              case 'paypal': $badgeClass = 'badge-info'; break;
            }
          ?>
          <span class="badge <?php echo $badgeClass; ?>">
            <?php echo esc_html($paymentMethods[$transaction->payment_method] ?? $transaction->payment_method); ?>
          </span>
        </td>
        <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
        <td class="text-center"><?php echo esc_html($transaction->item_count); ?></td>
        <td class="text-right"><strong>&euro; <?php echo number_format($transaction->total_amount, 2, ',', '.'); ?></strong></td>
        <td>
          <?php if ($transaction->operator_first_name): ?>
            <?php echo esc_html($transaction->operator_first_name . ' ' . $transaction->operator_last_name); ?>
          <?php else: ?>
            <span class="text-muted">-</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-view&id=<?php echo $transaction->id; ?>" class="btn btn-sm btn-info" title="Visualizza">
            <i class="fas fa-eye"></i>
          </a>
          <form method="post" class="d-inline">
            <?php csrf_field(); ?>
            <input type="hidden" name="transaction_id" value="<?php echo $transaction->id; ?>">
            <button type="submit" name="delete_transaction" class="btn btn-sm btn-danger" title="Elimina" onclick="return confirm('Sei sicuro di voler eliminare questa vendita?');">
              <i class="fas fa-trash"></i>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions&p=<?php echo $page - 1; ?>&payment_method=<?php echo urlencode($filterPaymentMethod); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">
          &laquo; Precedente
        </a>
      </li>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
        <a class="page-link" href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions&p=<?php echo $i; ?>&payment_method=<?php echo urlencode($filterPaymentMethod); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">
          <?php echo $i; ?>
        </a>
      </li>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <li class="page-item">
        <a class="page-link" href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions&p=<?php echo $page + 1; ?>&payment_method=<?php echo urlencode($filterPaymentMethod); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">
          Successiva &raquo;
        </a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>

<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessuna vendita trovata.
  </div>
<?php endif; ?>
