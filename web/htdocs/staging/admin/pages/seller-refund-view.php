<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $refundId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($refundId == 0) {
    echo "<script>window.location.href = '" . ROOT_URL . "admin/?page=seller-refunds';</script>";
    exit;
  }

  $sellerRefundMgr = new SellerRefundManager();
  $userMgr = new UserManager();

  // Handle form submissions
  if (isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      switch ($_POST['action']) {
        case 'record_payment':
          $amount = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0;
          $method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
          $date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
          $reference = isset($_POST['payment_reference']) ? trim($_POST['payment_reference']) : null;
          $notes = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : null;

          if ($amount > 0) {
            $sellerRefundMgr->recordPayment($refundId, $amount, $method, $date, $reference, $notes, $loggedInUser->id);
            $alertMsg = 'payment_recorded';
          } else {
            $alertMsg = 'invalid_amount';
          }
          break;

        case 'update_comments':
          $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
          $envelopePrepared = isset($_POST['envelope_prepared']) ? 1 : 0;
          $sellerRefundMgr->updateCommentsAndEnvelope($refundId, $comments, $envelopePrepared);
          $alertMsg = 'comments_updated';
          break;

        case 'generate_token':
          $token = $sellerRefundMgr->generatePreferenceToken($refundId);
          if ($token) {
            $alertMsg = 'token_generated';
          }
          break;

        case 'recalculate':
          $sellerRefundMgr->recalculateAmountOwed($refundId);
          $alertMsg = 'amount_recalculated';
          break;
      }
    }
  }

  // Get refund details
  $refund = $sellerRefundMgr->getById($refundId);
  if (!$refund) {
    echo "<script>window.location.href = '" . ROOT_URL . "admin/?page=seller-refunds';</script>";
    exit;
  }

  // Get user details
  $user = $userMgr->get($refund->user_id);
  $userIban = $userMgr->getIBAN($refund->user_id);

  // Get payment history
  $payments = $sellerRefundMgr->getPaymentHistory($refundId);

  // Calculate remaining
  $remaining = (float)$refund->amount_owed - (float)$refund->amount_paid;

  // Alert messages
  $alertMessages = [
    'payment_recorded' => ['type' => 'success', 'text' => 'Pagamento registrato con successo.'],
    'invalid_amount' => ['type' => 'danger', 'text' => 'Importo non valido.'],
    'comments_updated' => ['type' => 'success', 'text' => 'Note aggiornate con successo.'],
    'token_generated' => ['type' => 'success', 'text' => 'Link di preferenza generato con successo.'],
    'amount_recalculated' => ['type' => 'success', 'text' => 'Importo ricalcolato con successo.'],
  ];

  // Get landing page URL
  $landingUrl = '';
  if ($refund->preference_token && strtotime($refund->preference_token_expires) > time()) {
    $landingUrl = ROOT_URL . 'payment-preference?token=' . urlencode($refund->preference_token);
  }
?>

<h1>Dettaglio Rimborso - <?php echo esc_html($user->last_name . ' ' . $user->first_name); ?></h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $refund->year; ?>" class="btn btn-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Torna all'elenco
</a>

<?php if ($alertMsg && isset($alertMessages[$alertMsg])): ?>
  <div class="alert alert-<?php echo $alertMessages[$alertMsg]['type']; ?> alert-dismissible fade show">
    <?php echo $alertMessages[$alertMsg]['text']; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
  </div>
<?php endif; ?>

<div class="row">
  <!-- Left column: Seller info and refund details -->
  <div class="col-md-6">
    <!-- Seller Information -->
    <div class="card mb-4">
      <div class="card-header bg-info text-white">
        <i class="fas fa-user"></i> Informazioni Venditore
      </div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr>
            <th style="width: 35%;">Nome:</th>
            <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
          </tr>
          <tr>
            <th>Email:</th>
            <td><a href="mailto:<?php echo esc_html($user->email); ?>"><?php echo esc_html($user->email); ?></a></td>
          </tr>
          <tr>
            <th>Anno:</th>
            <td><strong><?php echo esc_html($refund->year); ?></strong></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Payment Preference -->
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-credit-card"></i> Preferenza Pagamento
      </div>
      <div class="card-body">
        <?php if ($refund->payment_preference): ?>
          <div class="alert <?php echo $refund->payment_preference === 'cash' ? 'alert-success' : 'alert-primary'; ?>">
            <strong>
              <?php if ($refund->payment_preference === 'cash'): ?>
                <i class="fas fa-money-bill-alt"></i> Contanti
              <?php else: ?>
                <i class="fas fa-university"></i> Bonifico Bancario
              <?php endif; ?>
            </strong>
            <br>
            <small>Impostata il <?php echo date('d/m/Y H:i', strtotime($refund->preference_set_at)); ?></small>
          </div>

          <?php if ($refund->payment_preference === 'wire_transfer' && $userIban): ?>
            <table class="table table-sm">
              <tr>
                <th>IBAN:</th>
                <td><code><?php echo esc_html($userIban['iban_formatted']); ?></code></td>
              </tr>
              <?php if ($userIban['iban_owner_name']): ?>
                <tr>
                  <th>Intestatario:</th>
                  <td><?php echo esc_html($userIban['iban_owner_name']); ?></td>
                </tr>
              <?php endif; ?>
            </table>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Preferenza non ancora impostata
          </div>
        <?php endif; ?>

        <!-- Generate/Show link -->
        <div class="mt-3">
          <?php if ($landingUrl): ?>
            <div class="input-group mb-2">
              <input type="text" class="form-control form-control-sm" value="<?php echo esc_html($landingUrl); ?>" id="landingUrl" readonly>
              <div class="input-group-append">
                <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard()">
                  <i class="fas fa-copy"></i>
                </button>
              </div>
            </div>
            <small class="text-muted">Scade il <?php echo date('d/m/Y', strtotime($refund->preference_token_expires)); ?></small>
          <?php else: ?>
            <form method="post">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="generate_token">
              <button type="submit" class="btn btn-sm btn-info">
                <i class="fas fa-link"></i> Genera Link per Newsletter
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Seller Notes (from landing page) -->
    <?php if ($refund->seller_notes): ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <i class="fas fa-comment"></i> Note dal Venditore
      </div>
      <div class="card-body">
        <p class="mb-0"><?php echo nl2br(esc_html($refund->seller_notes)); ?></p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Donate Unsold Books -->
    <?php if ($refund->donate_unsold !== null): ?>
    <div class="card mb-4">
      <div class="card-header bg-light">
        <i class="fas fa-heart"></i> Donazione Libri Invenduti
      </div>
      <div class="card-body">
        <?php if ($refund->donate_unsold): ?>
          <span class="badge badge-success"><i class="fas fa-check"></i> Il venditore desidera donare i libri invenduti</span>
          <?php if ($refund->donate_unsold_set_at): ?>
            <br><small class="text-muted">Impostato il <?php echo date('d/m/Y H:i', strtotime($refund->donate_unsold_set_at)); ?></small>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge badge-secondary"><i class="fas fa-times"></i> Il venditore NON desidera donare i libri invenduti</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Admin Comments -->
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-sticky-note"></i> Note Amministrative
      </div>
      <div class="card-body">
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="update_comments">
          <div class="form-group">
            <textarea name="comments" class="form-control" rows="3" placeholder="Note amministrative..."><?php echo esc_html($refund->comments); ?></textarea>
          </div>
          <?php if ($refund->payment_preference === 'cash'): ?>
          <div class="form-group">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="envelope_prepared" name="envelope_prepared" value="1"
                     <?php echo $refund->envelope_prepared ? 'checked' : ''; ?>>
              <label class="custom-control-label" for="envelope_prepared">
                <strong><i class="fas fa-envelope"></i> Busta preparata</strong>
              </label>
            </div>
            <small class="text-muted">Indica se la busta con il denaro contante è stata preparata</small>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-save"></i> Salva Note
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right column: Amounts and payments -->
  <div class="col-md-6">
    <!-- Amount Summary -->
    <div class="card mb-4">
      <div class="card-header bg-success text-white">
        <i class="fas fa-euro-sign"></i> Riepilogo Importi
      </div>
      <div class="card-body">
        <div class="row text-center mb-3">
          <div class="col-4">
            <h4 class="text-primary">&euro; <?php echo number_format((float)$refund->amount_owed, 2, ',', '.'); ?></h4>
            <small class="text-muted">Dovuto</small>
          </div>
          <div class="col-4">
            <h4 class="text-success">&euro; <?php echo number_format((float)$refund->amount_paid, 2, ',', '.'); ?></h4>
            <small class="text-muted">Pagato</small>
          </div>
          <div class="col-4">
            <h4 class="<?php echo $remaining > 0 ? 'text-danger' : 'text-success'; ?>">
              &euro; <?php echo number_format($remaining, 2, ',', '.'); ?>
            </h4>
            <small class="text-muted">Residuo</small>
          </div>
        </div>

        <div class="text-center">
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
          <span class="badge <?php echo $statusBadge[$refund->status] ?? 'badge-secondary'; ?> badge-lg" style="font-size: 1rem; padding: 0.5rem 1rem;">
            <?php echo $statusText[$refund->status] ?? $refund->status; ?>
          </span>
        </div>

        <hr>

        <form method="post" class="d-inline">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="recalculate">
          <button type="submit" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-calculator"></i> Ricalcola Importo Dovuto
          </button>
        </form>
      </div>
    </div>

    <!-- Record Payment -->
    <?php if ($remaining > 0): ?>
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <i class="fas fa-plus"></i> Registra Pagamento
      </div>
      <div class="card-body">
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="record_payment">

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="payment_amount">Importo *</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">&euro;</span>
                  </div>
                  <input type="number" step="0.01" min="0.01" max="<?php echo $remaining; ?>" name="payment_amount" id="payment_amount"
                         class="form-control" value="<?php echo number_format($remaining, 2, '.', ''); ?>" required>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="payment_method">Metodo *</label>
                <select name="payment_method" id="payment_method" class="form-control" required>
                  <option value="cash" <?php echo $refund->payment_preference === 'cash' ? 'selected' : ''; ?>>Contanti</option>
                  <option value="wire_transfer" <?php echo $refund->payment_preference === 'wire_transfer' ? 'selected' : ''; ?>>Bonifico</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="payment_date">Data *</label>
                <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="payment_reference">Riferimento</label>
                <input type="text" name="payment_reference" id="payment_reference" class="form-control" placeholder="CRO o n. ricevuta">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="payment_notes">Note</label>
            <input type="text" name="payment_notes" id="payment_notes" class="form-control" placeholder="Note opzionali">
          </div>

          <button type="submit" class="btn btn-success btn-block">
            <i class="fas fa-check"></i> Registra Pagamento
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Payment History -->
    <?php if (count($payments) > 0): ?>
    <div class="card">
      <div class="card-header">
        <i class="fas fa-history"></i> Storico Pagamenti
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light">
            <tr>
              <th>Data</th>
              <th>Metodo</th>
              <th class="text-right">Importo</th>
              <th>Rif.</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
              <tr>
                <td><?php echo date('d/m/Y', strtotime($payment->payment_date)); ?></td>
                <td>
                  <?php if ($payment->payment_method === 'cash'): ?>
                    <span class="badge badge-success">Contanti</span>
                  <?php else: ?>
                    <span class="badge badge-primary">Bonifico</span>
                  <?php endif; ?>
                </td>
                <td class="text-right">&euro; <?php echo number_format((float)$payment->amount, 2, ',', '.'); ?></td>
                <td>
                  <?php if ($payment->reference): ?>
                    <small><?php echo esc_html($payment->reference); ?></small>
                  <?php endif; ?>
                  <?php if ($payment->notes): ?>
                    <br><small class="text-muted"><?php echo esc_html($payment->notes); ?></small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function copyToClipboard() {
  var copyText = document.getElementById("landingUrl");
  copyText.select();
  copyText.setSelectionRange(0, 99999);
  document.execCommand("copy");
  alert("Link copiato negli appunti!");
}
</script>
