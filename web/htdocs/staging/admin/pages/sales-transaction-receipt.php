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

  // The receipt exists in a single format only: the PDF (shop/invoices/print-sales-receipt.php).
  // This page is just a confirmation/landing that links to it, so there is no second
  // on-screen layout that could diverge from the PDF.
  $justCreated = isset($_GET['msg']) && $_GET['msg'] === 'created';
  $pdfUrl = ROOT_URL . 'shop/invoices/print-sales-receipt.php?id=' . (int)$transaction->id;
?>

<?php if ($justCreated): ?>
  <div class="alert alert-success">
    <i class="fas fa-check-circle"></i> Vendita registrata correttamente.
  </div>
  <!-- La vendita e' stata creata: svuota il carrello persistito della pagina "Nuova Vendita". -->
  <script>try { sessionStorage.removeItem('salesNewCart'); } catch (e) {}</script>
<?php endif; ?>

<div class="card">
  <div class="card-body text-center">
    <h2>Ricevuta vendita N. <?php echo (int)$transaction->id; ?></h2>
    <p class="lead mb-1">Totale incassato: <strong>&euro; <?php echo number_format((float)$transaction->total_amount, 2, ',', '.'); ?></strong></p>
    <?php if (!empty($transaction->refunded_at)): ?>
      <p class="text-danger font-weight-bold">VENDITA RIMBORSATA</p>
    <?php endif; ?>
    <p class="text-muted">La ricevuta &egrave; disponibile in un unico formato (PDF), da cui &egrave; possibile anche stamparla.</p>
    <a href="<?php echo esc_html($pdfUrl); ?>" target="_blank" class="btn btn-primary btn-lg">
      <i class="fas fa-file-pdf"></i> Apri ricevuta PDF
    </a>
  </div>
</div>

<div class="mt-3">
  <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary">
    <i class="fas fa-arrow-left"></i> Torna all'elenco
  </a>
  <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-view&id=<?php echo (int)$transaction->id; ?>" class="btn btn-outline-secondary">
    <i class="fas fa-eye"></i> Dettaglio vendita
  </a>
</div>
