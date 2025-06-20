<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  if (!isset($_GET['id'])){
    echo "<script>location.href='".ROOT_URL."admin/?page=orders-list&msg=not_found';</script>";
    exit;
  }

  $orderId = esc($_GET['id']);

  $orderMgr = new OrderManager();
  $praticaMgr = new PraticaManager();
  $orderItems = $orderMgr->getOrderItems($orderId);
  $orderItemsAccettare = $orderMgr->getOrderItemsAccettare($orderId);
  $orderItemsVendere = $orderMgr->getOrderItemsVendere($orderId);
  $orderItemsEliminato = $orderMgr->getOrderItemsEliminato($orderId);
  $orderItemsVenduto = $orderMgr->getOrderItemsVenduto($orderId);
  $orderTotal = $orderMgr->getOrderTotal($orderId)[0];
  $orderTotalAccettare = $orderMgr->getOrderTotalAccettare($orderId)[0];
  $orderTotalVendere = $orderMgr->getOrderTotalVendere($orderId)[0];
  $orderTotalVenduto = $orderMgr->getOrderTotalVenduto($orderId)[0];
  $address = $orderMgr->getUserAddress($orderTotal['user_id']);
  $email = $orderMgr->getEmailAndName($orderId)['email'];
  $first_name = $orderMgr->getEmailAndName($orderId)['first_name'];
  $last_name = $orderMgr->getEmailAndName($orderId)['last_name'];
  $status = $orderItems[0]['order_status'];
  $oi_status = $orderItems[0]['order_item_status'];
  $pratica1 = $orderItems[0]['pratica'];

  $order1 = $orderMgr->get($orderId);

  // Handle "Elimina" button
  if (isset($_POST['delete'])) {
    $id = trim($_POST['item_id']);
    $status = 'eliminato';
    $orderMgr->updateStatusItem($id, $status);
    $alertMsg = 'deleted';
    echo "<script>location.href='".ROOT_URL."admin?page=process-order&id=".$orderId."&msg=".$alertMsg."';</script>";
    exit;
  }

  // Handle "Accetta" button
  if (isset($_POST['vendere'])) {
    $id = trim($_POST['item_id']);
    $status = 'vendere';
    $orderMgr->updateStatusItem($id, $status);
    $alertMsg = 'accepted';
    echo "<script>location.href='".ROOT_URL."admin?page=process-order&id=".$orderId."&msg=".$alertMsg."';</script>";
    exit;
  }

  // Handle "Ripristina" button
  if (isset($_POST['ripristina'])) {
    $id = trim($_POST['item_id']);
    $status = 'accettare';
    $orderMgr->updateStatusItem($id, $status);
    
    // If order was rejected (eliminato), reopen it for processing
    if ($orderItems[0]['order_status'] == 'eliminato') {
      $orderMgr->updateStatus($orderId, 'inviata');
    }
    
    $alertMsg = 'restored';
    echo "<script>location.href='".ROOT_URL."admin?page=process-order&id=".$orderId."&msg=".$alertMsg."';</script>";
    exit;
  }

  // Handle "Termina accettazione" button
  if (isset($_POST['termina_accettazione'])) {
    // Check if all items are in acceptable statuses (vendere or eliminato)
    $orderItems = $orderMgr->getOrderItems($orderId);
    $allItemsProcessed = true;
    $hasAcceptedItems = false;
    $allItemsRejected = true;
    
    foreach ($orderItems as $item) {
      $itemStatus = $item['order_item_status'];
      if ($itemStatus != 'vendere' && $itemStatus != 'eliminato') {
        $allItemsProcessed = false;
        break;
      }
      if ($itemStatus == 'vendere') {
        $hasAcceptedItems = true;
        $allItemsRejected = false;
      }
      if ($itemStatus != 'eliminato') {
        $allItemsRejected = false;
      }
    }
    
    if ($allItemsProcessed) {
      if ($allItemsRejected) {
        // All items were rejected - set order status to 'eliminato'
        $newOrderStatus = 'eliminato';
        $orderMgr->updateStatus($orderId, $newOrderStatus);
        
        // Redirect back to orders list with rejection message
        echo "<script>location.href='".ROOT_URL."admin/?page=orders-list&msg=order_rejected';</script>";
        exit;
      } else {
        // Some items were accepted - proceed with normal acceptance flow
        $newOrderStatus = 'accettata';
        $orderMgr->updateStatus($orderId, $newOrderStatus);
        
        // Generate and update pratica number
        $praticaMgr = new PraticaManager();
        $praticaMgr->updatePratica();
        $praticaPrec = $praticaMgr->GetnumPratica()[0];
        $pratica = $praticaPrec['numPratica'];
        $orderMgr->updatenumPratica($orderId, $pratica);
        
        // Get updated data
        $updatedOrderItems = $orderMgr->getOrderItems($orderId);
        $updatedOrderTotal = $orderMgr->getOrderTotal($orderId)[0];
        $updatedPratica = $updatedOrderItems[0]['pratica'];
        
        // Send acceptance email
        $orderMgr->sendAcceptanceEmail(
          $orderId,
          $email,
          $first_name,
          $last_name,
          $updatedPratica,
          $updatedOrderItems,
          $updatedOrderTotal
        );
        
        // Redirect to print-label page
        echo "<script>location.href='".ROOT_URL."admin/print-label.php?pratica=".$updatedPratica."&autoprint=1&source=acceptance';</script>";
        exit;
      }
    }
  }

  if (isset($_POST['venduto'])) {
    $id = trim($_POST['item_id']);
    $status = 'venduto';
    $orderMgr->updateStatusItem($id, $status);
    $alertMsg = 'sold';
  }

  //var_dump($orderTotal);die;
  if (count($orderItems) == 0) {
    echo "<script>location.href='".ROOT_URL."admin/?page=orders-list&msg=order_empty';</script>";
    exit;
  }

  if ($status == 'inviata' AND (isset($_POST['accettata_order']) OR isset($_GET['accettata_order']))) {
    // Update status
    $status = 'accettata';
    $orderMgr->updateStatus($orderId, $status);
    
    // Generate and update pratica number
    $praticaMgr = new PraticaManager();
    $praticaMgr->updatePratica();
    $praticaPrec = $praticaMgr->GetnumPratica()[0];
    $pratica = $praticaPrec['numPratica'];
    $orderMgr->updatenumPratica($orderId, $pratica);
    
    // Get updated data
    $updatedOrderItems = $orderMgr->getOrderItems($orderId);
    $updatedOrderTotal = $orderMgr->getOrderTotal($orderId)[0];
    $updatedPratica = $updatedOrderItems[0]['pratica'];
    
    // Send acceptance email
    $orderMgr->sendAcceptanceEmail(
        $orderId, 
        $email, 
        $first_name, 
        $last_name, 
        $updatedPratica, 
        $updatedOrderItems, 
        $updatedOrderTotal
    );
    
    $alertMsg = 'order_ready';
  }

  else if ($status == 'accettata' AND (isset($_POST['chiusa_order']) OR isset($_GET['chiusa_order']))){
    $status = 'chiusa';
    $orderMgr->updateStatus($orderId, $status);
  }

  else if ($status == 'inviata' AND (isset($_POST['annullata_order']) OR isset($_GET['annullata_order']))) {
    $status = 'annullata';
    $orderMgr->updateStatus($orderId, $status);
  }

  else if ($status == 'annullata' AND (isset($_POST['ripristina_order']) OR isset($_GET['ripristina_order']))){
    $status = 'inviata';
    $orderMgr->updateStatus($orderId, $status);
  }

  $count = 0;

  // Check if all items are processed (vendere or eliminato) to enable "Termina accettazione"
  $allItemsProcessed = true;
  $hasAcceptedItems = false;
  $allItemsRejected = true;
  
  foreach ($orderItems as $item) {
    $itemStatus = $item['order_item_status'];
    if ($itemStatus != 'vendere' && $itemStatus != 'eliminato') {
      $allItemsProcessed = false;
      break;
    }
    if ($itemStatus == 'vendere') {
      $hasAcceptedItems = true;
      $allItemsRejected = false;
    }
    if ($itemStatus != 'eliminato') {
      $allItemsRejected = false;
    }
  }
?>

<a href="<?php echo ROOT_URL . 'admin/?page=orders-list'; ?>" class="back underline d-block">&laquo; Lista Pratiche</a>
<?php $pratica = $orderItems[0]['pratica']; ?>

<h1 class="mb-4 d-inline">Pratica n. <?php echo esc_html($pratica1); ?></h1>
<div class="pdfDiv float-right mr-5 d-inline">
  <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($orderId); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0">
    <i class="fas fa-file-pdf fa-2x"></i>
  </a>
</div>

<hr class="m-3">

<?php if (count($orderItemsAccettare) > 0) : ?>
<h4 class="mb-3 font-weight-bold text-info">Libri da Accettare</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Volumi</th>
      <th>Prezzo</th>
      <th>Azioni</th>
    </tr>
  <?php foreach ($orderItemsAccettare as $item) : $count++; ?>
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['product_nota_volumi']); ?></td>
      <td><?php echo esc_html($item['total_price']); ?> €</td>
      <td>
        <form method="post" class="right d-inline">
          <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
          <input name="delete" type="submit" class="btn btn-outline-danger btn-sm" value="Elimina">
        </form>
        <form method="post" class="left d-inline">
          <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
          <input name="vendere" type="submit" class="btn btn-outline-success btn-sm" value="Accetta">
        </form>
      </td>
    </tr>
  <?php endforeach; $count=0; ?>
  </table>
<?php endif; ?>

<?php if ($status == 'inviata') : ?>
<h4 class="mb-3 font-weight-bold text-success">Libri Accettati</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Volumi</th>
      <th>Prezzo di vendita</th>
      <th>Azioni</th>
    </tr>
  <?php if (count($orderItemsVendere) > 0) : ?>
    <?php foreach ($orderItemsVendere as $item) : $count++; ?>
      <tr>
        <td class="big-screen"><?php echo $count; ?></td>
        <td><?php echo esc_html($item['product_name']); ?></td>
        <td><?php echo esc_html($item['quantity']); ?></td>
        <td><?php echo esc_html($item['product_ISBN']); ?></td>
        <td><?php echo esc_html($item['product_nota_volumi']); ?></td>
        <td><?php echo esc_html($item['total_price']); ?> €</td>
        <td>
          <form method="post" class="d-inline">
            <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
            <input name="ripristina" onclick="return confirm('Ripristinare il libro allo stato da accettare?');" type="submit" class="btn btn-outline-warning btn-sm" value="Ripristina">
          </form>
        </td>
      </tr>
    <?php endforeach; $count=0; ?>
  <?php else : ?>
    <tr>
      <td colspan="7" class="text-center text-muted">Nessun libro accettato</td>
    </tr>
  <?php endif; ?>
  </table>

<h4 class="mb-3 font-weight-bold text-danger">Libri Eliminati</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Volumi</th>
      <th>Prezzo</th>
      <th>Azioni</th>
    </tr>
  <?php if (count($orderItemsEliminato) > 0) : ?>
    <?php foreach ($orderItemsEliminato as $item) : $count++; ?>
      <tr>
        <td class="big-screen"><?php echo $count; ?></td>
        <td><?php echo esc_html($item['product_name']); ?></td>
        <td><?php echo esc_html($item['quantity']); ?></td>
        <td><?php echo esc_html($item['product_ISBN']); ?></td>
        <td><?php echo esc_html($item['product_nota_volumi']); ?></td>
        <td><?php echo esc_html($item['total_price']); ?> €</td>
        <td>
          <form method="post" class="d-inline">
            <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
            <input name="ripristina" onclick="return confirm('Ripristinare il libro allo stato da accettare?');" type="submit" class="btn btn-outline-warning btn-sm" value="Ripristina">
          </form>
        </td>
      </tr>
    <?php endforeach; $count=0; ?>
  <?php else : ?>
    <tr>
      <td colspan="7" class="text-center text-muted">Nessun libro eliminato</td>
    </tr>
  <?php endif; ?>
  </table>

  <div class="mt-4 mb-4">
    <form method="post" class="text-center">
      <?php if ($allItemsRejected && $allItemsProcessed) : ?>
        <button name="termina_accettazione" 
                type="submit" 
                class="btn btn-danger btn-lg"
                onclick="return confirm('Tutti i libri sono stati eliminati. La pratica sarà rifiutata definitivamente. Continuare?');">
          Rifiuta pratica
        </button>
        <p class="text-muted text-center mt-2">
          <small>Tutti i libri sono stati eliminati. La pratica sarà contrassegnata come rifiutata.</small>
        </p>
      <?php else : ?>
        <button name="termina_accettazione" 
                type="submit" 
                class="btn btn-primary btn-lg <?php echo $allItemsProcessed ? '' : 'disabled'; ?>"
                <?php echo $allItemsProcessed ? '' : 'disabled'; ?>
                onclick="return confirm('Terminare l\'accettazione della pratica? Verrà inviata l\'email al cliente e sarà generata l\'etichetta.');">
          Termina accettazione
        </button>
        <?php if (!$allItemsProcessed) : ?>
          <p class="text-muted text-center mt-2">
            <small>Il pulsante sarà abilitato quando tutti i libri saranno stati accettati o eliminati.</small>
          </p>
        <?php else : ?>
          <p class="text-muted text-center mt-2">
            <small>La pratica sarà accettata e verrà inviata l'email di conferma al cliente.</small>
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

<?php if ($status == 'eliminato') : ?>
<div class="alert alert-dark">
  <h5><i class="fas fa-times-circle"></i> Pratica Rifiutata</h5>
  <p>Questa pratica è stata rifiutata perché tutti i libri sono stati eliminati. Usa "Ripristina" per riaprire singoli libri se necessario.</p>
</div>

<h4 class="mb-3 font-weight-bold text-danger">Libri Eliminati</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Volumi</th>
      <th>Prezzo</th>
      <th>Azioni</th>
    </tr>
  <?php if (count($orderItemsEliminato) > 0) : ?>
    <?php foreach ($orderItemsEliminato as $item) : $count++; ?>
      <tr>
        <td class="big-screen"><?php echo $count; ?></td>
        <td><?php echo esc_html($item['product_name']); ?></td>
        <td><?php echo esc_html($item['quantity']); ?></td>
        <td><?php echo esc_html($item['product_ISBN']); ?></td>
        <td><?php echo esc_html($item['product_nota_volumi']); ?></td>
        <td><?php echo esc_html($item['total_price']); ?> €</td>
        <td>
          <form method="post" class="d-inline">
            <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
            <input name="ripristina" onclick="return confirm('Ripristinare il libro allo stato da accettare? Questo riaprirà la pratica per la lavorazione.');" type="submit" class="btn btn-outline-warning btn-sm" value="Ripristina">
          </form>
        </td>
      </tr>
    <?php endforeach; $count=0; ?>
  <?php endif; ?>
  </table>
<?php endif; ?>

<?php if ($status == 'accettata' && count($orderItemsVendere) > 0) : ?>
<h4 class="mb-3 font-weight-bold text-success">Libri Accettati</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Volumi</th>
      <th>Prezzo di vendita</th>
    </tr>
  <?php foreach ($orderItemsVendere as $item) : $count++; ?>
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['product_nota_volumi']); ?></td>
      <td><?php echo esc_html($item['total_price']); ?> €</td>
    </tr>
  <?php endforeach; $count=0; ?>
  </table>
<?php endif; ?>

<?php if (count($orderItemsVenduto) > 0) : ?>
<h4 class="mb-3 font-weight-bold text-primary">Libri Venduti</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Prezzo vendita</th>
    </tr>
  <?php foreach ($orderItemsVenduto as $item) : $count++; ?>
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['total_price']); ?> €</td>
    </tr>
  <?php endforeach; $count=0; ?>
  </table>
<?php endif; ?>

<?php
  $statusLbl = [
    'inviata'   => 'Inviata al database',
    'annullata'  => 'Annullata',
    'eliminato' => 'Eliminata (tutti i libri rifiutati)',
    'vendere'   => 'Da Vendere',
    'accettata'   => 'Accettata nel Database',
    'chiusa'   => 'Pronta per il ritiro',
    'pickup'   => 'Ritirato',  
  ];

  $cssClass = [
    'inviata'   => 'secondary',
    'annullata'  => 'danger',
    'eliminato' => 'dark',
    'vendere'   => 'success',
    'accettata'   => 'info',
    'chiusa'     => 'primary',
  ];
?>

<?php if ($status == 'inviata') : ?>
<div class="mt-4">
  <div class="alert alert-info">
    <h5>Stato Pratica: <?php echo $statusLbl[$status]; ?></h5>
    <p>Procedi con l'accettazione o eliminazione dei singoli libri. Quando tutti i libri saranno stati processati, potrai terminare l'accettazione.</p>
  </div>
</div>
<?php else : ?>
<div class="mt-4">
  <div class="alert alert-<?php echo $cssClass[$status]; ?>">
    <h5>Stato Pratica: <?php echo $statusLbl[$status]; ?></h5>
  </div>
</div>
<?php endif; ?>