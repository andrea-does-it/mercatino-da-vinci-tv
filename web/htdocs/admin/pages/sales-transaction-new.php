<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $salesMgr = new SalesTransactionManager();
  $paymentMethods = SalesTransactionManager::getPaymentMethods();

  $errors = false;
  $paymentMethod = isset($_POST['payment_method']) ? esc($_POST['payment_method']) : 'cash';
  $description = isset($_POST['description']) ? esc($_POST['description']) : '';
  $search = isset($_GET['search']) ? esc($_GET['search']) : '';

  // Handle form submission
  if (isset($_POST['save_transaction'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
      $errors = true;
    } else {
      // Collect order_item IDs from POST
      $orderItemIds = [];
      if (isset($_POST['order_item_ids']) && is_array($_POST['order_item_ids'])) {
        foreach ($_POST['order_item_ids'] as $itemId) {
          $itemId = (int)$itemId;
          if ($itemId > 0) {
            $orderItemIds[] = $itemId;
          }
        }
      }

      if (count($orderItemIds) == 0) {
        $alertMsg = 'order_empty';
        $errors = true;
      }

      if (!$errors) {
        $transactionId = $salesMgr->createTransaction(
          $paymentMethod,
          $description !== '' ? $description : null,
          $loggedInUser->id,
          $orderItemIds
        );

        if ($transactionId) {
          log_activity($loggedInUser->id, 'admin_sale_created', 'transaction_id: ' . $transactionId);
          // Redirect to view page
          $redirectUrl = ROOT_URL . 'admin/?page=sales-transaction-view&id=' . $transactionId . '&msg=created';
          echo "<script>window.location.href = '" . esc_html($redirectUrl) . "';</script>";
          exit;
        } else {
          $alertMsg = 'err';
          $errors = true;
        }
      }
    }
  }

  // Get available books for sale
  $availableBooks = $salesMgr->getAvailableBooksForSale($search);
?>

<h1>Nuova Vendita</h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=sales-transactions" class="btn btn-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Torna all'elenco
</a>

<div class="row">
  <!-- Left side: Book search and selection -->
  <div class="col-md-7">
    <div class="card mb-4">
      <div class="card-header bg-info text-white">
        <i class="fas fa-search"></i> Cerca Libri Disponibili
      </div>
      <div class="card-body">
        <!-- Search form -->
        <form method="get" class="mb-3">
          <input type="hidden" name="page" value="sales-transaction-new">
          <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Cerca per ISBN, titolo o pratica..." value="<?php echo esc_html($search); ?>">
            <div class="input-group-append">
              <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cerca</button>
              <?php if ($search): ?>
                <a href="<?php echo ROOT_URL; ?>admin/?page=sales-transaction-new" class="btn btn-secondary">Reset</a>
              <?php endif; ?>
            </div>
          </div>
        </form>

        <?php if (count($availableBooks) > 0): ?>
        <p class="text-muted"><small>Trovati <?php echo count($availableBooks); ?> libri disponibili. Clicca su un libro per aggiungerlo al carrello.</small></p>

        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
          <table class="table table-sm table-hover" id="availableBooksTable">
            <thead class="thead-light sticky-top">
              <tr>
                <th>Pratica</th>
                <th>Titolo</th>
                <th>ISBN</th>
                <th>Venditore</th>
                <th class="text-right">Prezzo</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($availableBooks as $book): ?>
                <tr id="available-<?php echo $book->order_item_id; ?>">
                  <td><span class="badge badge-secondary"><?php echo esc_html($book->pratica); ?></span></td>
                  <td>
                    <?php echo esc_html($book->product_name); ?>
                    <?php if ($book->nota_volumi): ?>
                      <br><small class="text-muted"><?php echo esc_html($book->nota_volumi); ?></small>
                    <?php endif; ?>
                  </td>
                  <td><code><?php echo esc_html($book->isbn); ?></code></td>
                  <td><small><?php echo esc_html($book->seller_last_name . ' ' . $book->seller_first_name); ?></small></td>
                  <td class="text-right"><strong>&euro; <?php echo number_format($book->sale_price, 2, ',', '.'); ?></strong></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-success add-to-cart"
                            data-id="<?php echo $book->order_item_id; ?>"
                            data-name="<?php echo esc_html($book->product_name); ?>"
                            data-isbn="<?php echo esc_html($book->isbn); ?>"
                            data-pratica="<?php echo esc_html($book->pratica); ?>"
                            data-price="<?php echo $book->sale_price; ?>">
                      <i class="fas fa-plus"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            <?php if ($search): ?>
              Nessun libro trovato per "<?php echo esc_html($search); ?>".
            <?php else: ?>
              Nessun libro disponibile per la vendita.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right side: Cart and transaction details -->
  <div class="col-md-5">
    <form method="post" id="salesForm">
      <?php csrf_field(); ?>

      <div class="card mb-4">
        <div class="card-header bg-success text-white">
          <i class="fas fa-cash-register"></i> Dettagli Vendita
        </div>
        <div class="card-body">
          <div class="form-group">
            <label for="payment_method">Metodo di Pagamento *</label>
            <select name="payment_method" id="payment_method" class="form-control" required>
              <?php foreach ($paymentMethods as $methodKey => $methodLabel): ?>
                <option value="<?php echo esc_html($methodKey); ?>" <?php echo $paymentMethod == $methodKey ? 'selected' : ''; ?>>
                  <?php echo esc_html($methodLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="description">Descrizione (nome cliente o note)</label>
            <input type="text" name="description" id="description" class="form-control" value="<?php echo esc_html($description); ?>" placeholder="Es. Mario Rossi">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header bg-primary text-white">
          <i class="fas fa-shopping-cart"></i> Carrello Vendita
          <span class="badge badge-light float-right" id="cartCount">0</span>
        </div>
        <div class="card-body" id="cartBody">
          <div id="emptyCartMessage" class="text-center text-muted py-4">
            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
            <p>Il carrello è vuoto.<br>Aggiungi libri dalla lista a sinistra.</p>
          </div>
          <div id="cartItems" style="display: none;">
            <table class="table table-sm" id="cartTable">
              <thead>
                <tr>
                  <th>Libro</th>
                  <th class="text-right">Prezzo</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Totale:</h5>
            <h4 class="mb-0 text-success" id="cartTotal">&euro; 0,00</h4>
          </div>
        </div>
      </div>

      <button type="submit" name="save_transaction" class="btn btn-success btn-lg btn-block" id="submitBtn" disabled>
        <i class="fas fa-check"></i> Conferma Vendita
      </button>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  let cart = [];

  function updateCartDisplay() {
    const cartCount = cart.length;
    $('#cartCount').text(cartCount);

    if (cartCount === 0) {
      $('#emptyCartMessage').show();
      $('#cartItems').hide();
      $('#submitBtn').prop('disabled', true);
    } else {
      $('#emptyCartMessage').hide();
      $('#cartItems').show();
      $('#submitBtn').prop('disabled', false);

      let total = 0;
      let html = '';
      cart.forEach(function(item, index) {
        total += item.price;
        html += `
          <tr data-cart-index="${index}">
            <td>
              <strong>${escapeHtml(item.name)}</strong><br>
              <small class="text-muted">Pratica: ${escapeHtml(item.pratica)} | ISBN: ${escapeHtml(item.isbn)}</small>
              <input type="hidden" name="order_item_ids[]" value="${item.id}">
            </td>
            <td class="text-right">&euro; ${item.price.toFixed(2).replace('.', ',')}</td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-danger remove-from-cart" data-index="${index}">
                <i class="fas fa-times"></i>
              </button>
            </td>
          </tr>
        `;
      });
      $('#cartTable tbody').html(html);
      $('#cartTotal').text('€ ' + total.toFixed(2).replace('.', ','));
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
  }

  // Add to cart
  $(document).on('click', '.add-to-cart', function() {
    const id = $(this).data('id');

    // Check if already in cart
    const exists = cart.find(item => item.id === id);
    if (exists) {
      alert('Questo libro è già nel carrello!');
      return;
    }

    const item = {
      id: id,
      name: $(this).data('name'),
      isbn: $(this).data('isbn'),
      pratica: $(this).data('pratica'),
      price: parseFloat($(this).data('price'))
    };

    cart.push(item);

    // Hide the row from available list
    $('#available-' + id).addClass('table-success').find('.add-to-cart').prop('disabled', true).removeClass('btn-success').addClass('btn-secondary').html('<i class="fas fa-check"></i>');

    updateCartDisplay();
  });

  // Remove from cart
  $(document).on('click', '.remove-from-cart', function() {
    const index = $(this).data('index');
    const item = cart[index];

    // Re-enable the row in available list
    $('#available-' + item.id).removeClass('table-success').find('.add-to-cart').prop('disabled', false).removeClass('btn-secondary').addClass('btn-success').html('<i class="fas fa-plus"></i>');

    cart.splice(index, 1);
    updateCartDisplay();
  });

  // Initialize DataTable for available books
  if ($('#availableBooksTable tbody tr').length > 0) {
    $('#availableBooksTable').DataTable({
      paging: false,
      info: false,
      order: [[1, 'asc']],
      language: {
        search: "Filtra:",
        zeroRecords: "Nessun libro trovato"
      }
    });
  }
});
</script>

<style>
#availableBooksTable thead th {
  position: sticky;
  top: 0;
  background: #f8f9fa;
  z-index: 1;
}
</style>
