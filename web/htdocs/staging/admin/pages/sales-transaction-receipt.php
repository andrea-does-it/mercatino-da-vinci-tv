<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Access control: reachable only via admin/index.php (admin/pwuser only).
  global $loggedInUser;

  $salesMgr = new SalesTransactionManager();
  $transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $transaction = $salesMgr->getTransactionWithItems($transactionId);

  if (!$transaction) {
    echo '<div class="alert alert-danger">Transazione non trovata.</div>';
    return;
  }

  $operatorName = $salesMgr->getOperatorName($transaction->operator_id);
  $items = $transaction->items;
  $pdfUrl = ROOT_URL . 'shop/invoices/print-sales-receipt.php?id=' . (int)$transaction->id;
?>

<div class="d-print-none mb-3">
  <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary">
    <i class="fas fa-arrow-left"></i> Torna all'elenco
  </a>
  <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-view&id=<?php echo (int)$transaction->id; ?>" class="btn btn-outline-secondary">
    <i class="fas fa-eye"></i> Dettaglio vendita
  </a>
  <button type="button" class="btn btn-primary" onclick="window.print();">
    <i class="fas fa-print"></i> Stampa
  </button>
  <a href="<?php echo esc_html($pdfUrl); ?>" target="_blank" class="btn btn-outline-primary">
    <i class="fas fa-file-pdf"></i> Scarica PDF
  </a>
</div>

<div class="card">
  <div class="card-body">
    <h2 class="text-center"><?php echo esc_html(SITE_NAME); ?> - Ricevuta vendita N. <?php echo (int)$transaction->id; ?></h2>
    <?php if (!empty($transaction->refunded_at)): ?>
      <p class="text-center text-danger font-weight-bold">VENDITA RIMBORSATA</p>
    <?php endif; ?>

    <p>
      <strong>Data:</strong> <?php echo $transaction->created_at ? date('d/m/Y H:i', strtotime($transaction->created_at)) : ''; ?><br>
      <strong>Pagamento:</strong> <?php echo esc_html($transaction->payment_method); ?><br>
      <?php if ($operatorName !== ''): ?><strong>Operatore:</strong> <?php echo esc_html($operatorName); ?><br><?php endif; ?>
      <?php if (!empty($transaction->description)): ?><strong>Cliente/Note:</strong> <?php echo esc_html($transaction->description); ?><?php endif; ?>
    </p>

    <table class="table table-bordered">
      <thead>
        <tr><th>Pratica</th><th>Titolo</th><th class="text-right">Prezzo</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $row): ?>
          <tr>
            <td><?php echo esc_html($row->pratica); ?></td>
            <td><?php echo esc_html($row->product_name); ?></td>
            <td class="text-right">€ <?php echo number_format((float)$row->price, 2, ',', '.'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-right">Totale incassato</th>
          <th class="text-right">€ <?php echo number_format((float)$transaction->total_amount, 2, ',', '.'); ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
