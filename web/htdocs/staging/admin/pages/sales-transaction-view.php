<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($transactionId == 0) {
    echo "<script>window.location.href = '" . ROOT_URL . "admin/?page=sales-transactions&msg=not_found';</script>";
    exit;
  }

  $salesMgr = new SalesTransactionManager();
  $paymentMethods = SalesTransactionManager::getPaymentMethods();

  // Handle item refund
  if (isset($_POST['refund_item']) && isset($_POST['item_id'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $itemId = (int)$_POST['item_id'];
      $refundNotes = isset($_POST['refund_notes']) ? trim($_POST['refund_notes']) : '';
      if ($salesMgr->refundItem($itemId, $refundNotes)) {
        $alertMsg = 'order_quantity_resored';
      } else {
        $alertMsg = 'err';
      }
    }
  }

  // Handle full transaction refund
  if (isset($_POST['refund_transaction'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $refundNotes = isset($_POST['refund_notes']) ? trim($_POST['refund_notes']) : '';
      if ($salesMgr->refundTransaction($transactionId, $refundNotes)) {
        echo "<script>window.location.href = '" . ROOT_URL . "admin/?page=sales-transactions&msg=order_quantity_resored';</script>";
        exit;
      } else {
        $alertMsg = 'err';
      }
    }
  }

  // Get transaction with items
  $transaction = $salesMgr->getTransactionWithItems($transactionId);

  if (!$transaction) {
    echo "<script>window.location.href = '" . ROOT_URL . "admin/?page=sales-transactions&msg=not_found';</script>";
    exit;
  }
?>

<h1>Dettaglio Vendita #<?php echo esc_html($transaction->id); ?></h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Torna all'elenco
</a>

<div class="row">
  <div class="col-md-6">
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-info-circle"></i> Informazioni Transazione
      </div>
      <div class="card-body">
        <table class="table table-borderless">
          <tr>
            <th style="width: 40%;">ID Transazione:</th>
            <td><?php echo esc_html($transaction->id); ?></td>
          </tr>
          <tr>
            <th>Data/Ora:</th>
            <td><?php echo date('d/m/Y H:i:s', strtotime($transaction->created_at)); ?></td>
          </tr>
          <tr>
            <th>Metodo Pagamento:</th>
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
              <span class="badge <?php echo $badgeClass; ?> badge-lg">
                <?php echo esc_html($paymentMethods[$transaction->payment_method] ?? $transaction->payment_method); ?>
              </span>
            </td>
          </tr>
          <tr>
            <th>Descrizione:</th>
            <td><?php echo esc_html($transaction->description ?: '-'); ?></td>
          </tr>
          <tr>
            <th>Operatore:</th>
            <td>
              <?php
                $userMgr = new UserManager();
                if ($transaction->operator_id) {
                  $operator = $userMgr->get($transaction->operator_id);
                  echo esc_html($operator->first_name . ' ' . $operator->last_name);
                } else {
                  echo '<span class="text-muted">-</span>';
                }
              ?>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-4 bg-light">
      <div class="card-body text-center">
        <h5 class="card-title">Totale Vendita</h5>
        <h1 class="text-success">&euro; <?php echo number_format($transaction->total_amount, 2, ',', '.'); ?></h1>
        <p class="text-muted"><?php echo count($transaction->items); ?> articoli</p>
      </div>
    </div>
  </div>
</div>

<!-- Items List -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-book"></i> Articoli Venduti
  </div>
  <div class="card-body">
    <?php if (count($transaction->items) > 0): ?>
    <table class="table table-hover">
      <thead class="thead-light">
        <tr>
          <th>#</th>
          <th>Pratica</th>
          <th>Titolo</th>
          <th>ISBN</th>
          <th>Venditore</th>
          <th class="text-right">Prezzo Base</th>
          <th class="text-right">Prezzo Vendita</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php $count = 0; foreach ($transaction->items as $item): $count++; ?>
          <tr>
            <td><?php echo $count; ?></td>
            <td><span class="badge badge-secondary"><?php echo esc_html($item->pratica); ?></span></td>
            <td>
              <?php echo esc_html($item->product_name); ?>
              <?php if (isset($item->nota_volumi) && $item->nota_volumi): ?>
                <br><small class="text-muted"><?php echo esc_html($item->nota_volumi); ?></small>
              <?php endif; ?>
            </td>
            <td><code><?php echo esc_html($item->isbn); ?></code></td>
            <td><small><?php echo esc_html($item->seller_last_name . ' ' . $item->seller_first_name); ?></small></td>
            <td class="text-right text-muted">&euro; <?php echo number_format($item->original_price, 2, ',', '.'); ?></td>
            <td class="text-right"><strong>&euro; <?php echo number_format($item->price, 2, ',', '.'); ?></strong></td>
            <td>
              <button type="button" class="btn btn-sm btn-warning" title="Rimborsa"
                      onclick="openRefundModal('item', <?php echo $item->id; ?>, '<?php echo esc_html(addslashes($item->product_name)); ?>')">
                <i class="fas fa-undo"></i> Rimborsa
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-primary">
          <th colspan="5" class="text-right">TOTALE:</th>
          <th class="text-right text-muted">
            &euro; <?php
              $baseTotal = 0;
              foreach ($transaction->items as $item) {
                $baseTotal += (float)$item->original_price;
              }
              echo number_format($baseTotal, 2, ',', '.');
            ?>
          </th>
          <th class="text-right">&euro; <?php echo number_format($transaction->total_amount, 2, ',', '.'); ?></th>
          <th></th>
        </tr>
        <tr class="table-info">
          <th colspan="5" class="text-right">Margine Comitato:</th>
          <th colspan="2" class="text-right">
            &euro; <?php echo number_format($transaction->total_amount - $baseTotal, 2, ',', '.'); ?>
          </th>
          <th></th>
        </tr>
      </tfoot>
    </table>
    <?php else: ?>
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Nessun articolo in questa transazione. La transazione verrà eliminata automaticamente.
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Actions -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-cogs"></i> Azioni
  </div>
  <div class="card-body">
    <?php if (count($transaction->items) > 0): ?>
    <button type="button" class="btn btn-danger" onclick="openRefundModal('transaction', 0, 'TUTTA la vendita #<?php echo esc_html($transaction->id); ?>')">
      <i class="fas fa-undo"></i> Rimborsa Tutta la Vendita
    </button>
    <?php endif; ?>
    <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-new" class="btn btn-success">
      <i class="fas fa-plus"></i> Nuova Vendita
    </a>
  </div>
</div>

<div class="alert alert-info mt-4">
  <i class="fas fa-info-circle"></i>
  <strong>Nota:</strong> Quando un articolo viene rimborsato, il libro torna automaticamente nello stato "da vendere" e sar&agrave; nuovamente disponibile per la vendita nella pagina "Libri da Vendere".
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" id="refundForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="item_id" id="refundItemId" value="">
        <input type="hidden" name="refund_item" id="refundItemFlag" value="">
        <input type="hidden" name="refund_transaction" id="refundTransactionFlag" value="" disabled>

        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fas fa-undo"></i> Conferma Rimborso</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <p id="refundMessage"></p>
          <div class="form-group">
            <label for="refundNotes"><strong>Nota di rimborso</strong> <small class="text-muted">(facoltativa)</small></label>
            <textarea name="refund_notes" id="refundNotes" class="form-control" rows="3"
                      placeholder="Motivo del rimborso..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-warning"><i class="fas fa-undo"></i> Conferma Rimborso</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openRefundModal(type, itemId, label) {
  // Reset
  document.getElementById('refundNotes').value = '';
  document.getElementById('refundItemId').value = '';
  document.getElementById('refundItemFlag').disabled = true;
  document.getElementById('refundTransactionFlag').disabled = true;

  if (type === 'item') {
    document.getElementById('refundItemId').value = itemId;
    document.getElementById('refundItemFlag').disabled = false;
    document.getElementById('refundMessage').innerHTML =
      'Vuoi rimborsare <strong>' + label + '</strong>?<br>' +
      '<small class="text-muted">Il libro torner&agrave; disponibile per la vendita.</small>';
  } else {
    document.getElementById('refundTransactionFlag').disabled = false;
    document.getElementById('refundMessage').innerHTML =
      'Sei sicuro di voler rimborsare <strong>' + label + '</strong>?<br>' +
      '<small class="text-danger">Tutti i libri torneranno disponibili per la vendita.</small>';
  }

  $('#refundModal').modal('show');
}
</script>
