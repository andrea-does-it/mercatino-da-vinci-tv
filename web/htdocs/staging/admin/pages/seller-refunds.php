<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $sellerRefundMgr = new SellerRefundManager();

  // Default to current year
  $currentYear = (int)date('Y');
  $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

  // Get filters
  $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
  $preferenceFilter = isset($_GET['preference']) ? $_GET['preference'] : '';

  // Handle actions
  if (isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      switch ($_POST['action']) {
        case 'create_records':
          // Create refund records for all sellers who sold books this year
          $count = $sellerRefundMgr->createRecordsForYear($selectedYear);
          $alertMsg = $count > 0 ? 'records_created' : 'no_records_to_create';
          break;

        case 'generate_token':
          // Generate token for a single seller
          if (isset($_POST['refund_id'])) {
            $token = $sellerRefundMgr->generatePreferenceToken((int)$_POST['refund_id']);
            if ($token) {
              $alertMsg = 'token_generated';
            }
          }
          break;

        case 'recalculate':
          // Recalculate amount owed for a seller
          if (isset($_POST['refund_id'])) {
            $sellerRefundMgr->recalculateAmountOwed((int)$_POST['refund_id']);
            $alertMsg = 'amount_recalculated';
          }
          break;
      }
    }
  }

  // Get available years
  $availableYears = $sellerRefundMgr->getAvailableYears();
  if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    rsort($availableYears);
  }

  // Get refunds for selected year
  $refunds = $sellerRefundMgr->getRefundsForYear($selectedYear, $statusFilter, $preferenceFilter);

  // Get summary
  $summary = $sellerRefundMgr->getYearSummary($selectedYear);

  // Get sellers without refund records
  $sellersWithoutRecords = $sellerRefundMgr->getSellersWithoutRefundRecord($selectedYear);

  // Alert messages
  $alertMessages = [
    'records_created' => ['type' => 'success', 'text' => 'Record di rimborso creati con successo.'],
    'no_records_to_create' => ['type' => 'info', 'text' => 'Nessun nuovo record da creare.'],
    'token_generated' => ['type' => 'success', 'text' => 'Link di preferenza generato con successo.'],
    'amount_recalculated' => ['type' => 'success', 'text' => 'Importo ricalcolato con successo.'],
  ];
?>

<h1>Gestione Rimborsi Venditori - <?php echo $selectedYear; ?></h1>

<?php if ($alertMsg && isset($alertMessages[$alertMsg])): ?>
  <div class="alert alert-<?php echo $alertMessages[$alertMsg]['type']; ?> alert-dismissible fade show">
    <?php echo $alertMessages[$alertMsg]['text']; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
  </div>
<?php endif; ?>

<!-- Year selector and filters -->
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="form-inline">
      <input type="hidden" name="page" value="seller-refunds">

      <div class="form-group mr-3">
        <label for="year" class="mr-2">Anno:</label>
        <select name="year" id="year" class="form-control" onchange="this.form.submit()">
          <?php foreach ($availableYears as $year): ?>
            <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
              <?php echo $year; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="status" class="mr-2">Stato:</label>
        <select name="status" id="status" class="form-control" onchange="this.form.submit()">
          <option value="">Tutti</option>
          <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>In attesa</option>
          <option value="partial" <?php echo $statusFilter === 'partial' ? 'selected' : ''; ?>>Parziale</option>
          <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completato</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="preference" class="mr-2">Preferenza:</label>
        <select name="preference" id="preference" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="cash" <?php echo $preferenceFilter === 'cash' ? 'selected' : ''; ?>>Contanti</option>
          <option value="wire_transfer" <?php echo $preferenceFilter === 'wire_transfer' ? 'selected' : ''; ?>>Bonifico</option>
        </select>
      </div>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary">
        <i class="fas fa-sync"></i> Reset
      </a>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-newsletter&year=<?php echo $selectedYear; ?>" class="btn btn-info ml-3">
        <i class="fas fa-envelope"></i> Gestione Newsletter
      </a>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-report&year=<?php echo $selectedYear; ?>" class="btn btn-success ml-2">
        <i class="fas fa-file-excel"></i> Report
      </a>
    </form>
  </div>
</div>

<!-- Summary cards -->
<?php if ($summary): ?>
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h3><?php echo (int)$summary->total_sellers; ?></h3>
        <small>Venditori Totali</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning">
      <div class="card-body text-center">
        <h3>&euro; <?php echo number_format((float)$summary->total_owed - (float)$summary->total_paid, 2, ',', '.'); ?></h3>
        <small>Da Pagare</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h3>&euro; <?php echo number_format((float)$summary->total_paid, 2, ',', '.'); ?></h3>
        <small>Già Pagato</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h3><?php echo (int)$summary->no_preference_count; ?></h3>
        <small>Senza Preferenza</small>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Action buttons -->
<div class="mb-4">
  <?php if (count($sellersWithoutRecords) > 0): ?>
    <form method="post" class="d-inline">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="create_records">
      <button type="submit" class="btn btn-success" onclick="return confirm('Creare <?php echo count($sellersWithoutRecords); ?> nuovi record di rimborso?');">
        <i class="fas fa-plus"></i> Crea Record per <?php echo count($sellersWithoutRecords); ?> Venditori
      </button>
    </form>
  <?php endif; ?>
</div>

<!-- Refunds table -->
<?php if (count($refunds) > 0): ?>
<div class="card">
  <div class="card-header">
    <i class="fas fa-list"></i> Elenco Rimborsi (<?php echo count($refunds); ?>)
  </div>
  <div class="card-body">
    <table class="table table-hover" id="refundsTable">
      <thead class="thead-light">
        <tr>
          <th>Venditore</th>
          <th>Email</th>
          <th>Pratiche</th>
          <th class="text-right">Dovuto</th>
          <th class="text-right">Pagato</th>
          <th>Preferenza</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($refunds as $refund): ?>
          <tr>
            <td>
              <strong><?php echo esc_html($refund->last_name . ' ' . $refund->first_name); ?></strong>
            </td>
            <td><small><?php echo esc_html($refund->email); ?></small></td>
            <td><span class="badge badge-secondary"><?php echo (int)$refund->pratica_count; ?></span></td>
            <td class="text-right">&euro; <?php echo number_format((float)$refund->amount_owed, 2, ',', '.'); ?></td>
            <td class="text-right">
              <?php if ((float)$refund->amount_paid > 0): ?>
                <span class="text-success">&euro; <?php echo number_format((float)$refund->amount_paid, 2, ',', '.'); ?></span>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($refund->payment_preference === 'cash'): ?>
                <span class="badge badge-success"><i class="fas fa-money-bill-alt"></i> Contanti</span>
              <?php elseif ($refund->payment_preference === 'wire_transfer'): ?>
                <span class="badge badge-primary"><i class="fas fa-university"></i> Bonifico</span>
              <?php else: ?>
                <span class="badge badge-warning"><i class="fas fa-question"></i> Non impostata</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $statusBadge = [
                  'pending' => 'badge-warning',
                  'partial' => 'badge-info',
                  'completed' => 'badge-success',
                  'cancelled' => 'badge-secondary'
                ];
                $statusText = [
                  'pending' => 'In attesa',
                  'partial' => 'Parziale',
                  'completed' => 'Completato',
                  'cancelled' => 'Annullato'
                ];
              ?>
              <span class="badge <?php echo $statusBadge[$refund->status] ?? 'badge-secondary'; ?>">
                <?php echo $statusText[$refund->status] ?? $refund->status; ?>
              </span>
            </td>
            <td>
              <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo $refund->id; ?>" class="btn btn-sm btn-primary" title="Dettaglio">
                <i class="fas fa-eye"></i>
              </a>
              <?php if (!$refund->preference_token || strtotime($refund->preference_token_expires) < time()): ?>
                <form method="post" class="d-inline">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="action" value="generate_token">
                  <input type="hidden" name="refund_id" value="<?php echo $refund->id; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-info" title="Genera Link">
                    <i class="fas fa-link"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessun record di rimborso trovato per l'anno <?php echo $selectedYear; ?>.
    <?php if (count($sellersWithoutRecords) > 0): ?>
      Ci sono <?php echo count($sellersWithoutRecords); ?> venditori con libri venduti. Usa il pulsante sopra per creare i record.
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
$(document).ready(function() {
  if ($('#refundsTable tbody tr').length > 0) {
    $('#refundsTable').DataTable({
      pageLength: 25,
      order: [[0, 'asc']],
      language: {
        url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Italian.json'
      }
    });
  }
});
</script>
