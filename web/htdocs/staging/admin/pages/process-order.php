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
  $orderItemsAccettare1 = $orderMgr->getOrderItemsAccettare1($orderId);
  $orderItemsVendere = $orderMgr->getOrderItemsVendere($orderId);
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


  if (isset($_POST['delete'])) {

    $id = trim($_POST['item_id']);
    $status = 'eliminato';
    $orderMgr->updateStatusItem($id, $status);
    $alertMsg = 'deleted';
  }

  if (isset($_POST['vendere'])) {
    $id = trim($_POST['item_id']);
    $status = 'vendere';
    $orderMgr->updateStatusItem($id, $status);
    
    // Check if all items are now accepted (vendere, venduto, or eliminato)
    $orderItems = $orderMgr->getOrderItems($orderId);
    $orderStatus = $orderItems[0]['order_status'];
    
    // Check if all items are now in acceptable statuses
    $allItemsAccepted = true;
    foreach ($orderItems as $item) {
        $itemStatus = $item['order_item_status'];
        // error_log("Checking item ".$item['order_item_id']." with status: ".$itemStatus);
        
        if ($itemStatus != 'vendere' && $itemStatus != 'venduto' && $itemStatus != 'eliminato') {
            $allItemsAccepted = false;
            // error_log("Found item not accepted: ".$item['order_item_id']);
            break;
        }
    }
    
    // error_log("All items accepted: ".($allItemsAccepted ? "true" : "false"));
    
    // If all items are accepted and order status is still 'inviata'
    if ($allItemsAccepted && $orderStatus == 'inviata') {
        // error_log("Updating order to accettata");
        
        // Update order status to 'accettata'
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
        
        // Redirect to print-label page with pratica number and auto-print
        // error_log("Redirecting to print-label with pratica: ".$updatedPratica);        
        // echo "<script>location.href='".ROOT_URL."admin/print-label.php?pratica=".$updatedPratica."&autoprint=1';</script>";      
        echo "<script>location.href='".ROOT_URL."admin/print-label.php?pratica=".$updatedPratica."&autoprint=1&source=acceptance';</script>";
        

        exit;
    } else {
        // error_log("Not all conditions met for order update. AllItemsAccepted: ".($allItemsAccepted ? "true" : "false").", OrderStatus: $orderStatus");
        
        // Refresh the current page to show updated item status
        $alertMsg = 'updated';
        echo "<script>location.href='".ROOT_URL."admin?page=process-order&id=".$orderId."&msg=".$alertMsg."';</script>";
        exit;
    }
  }

  if (isset($_POST['venduto'])) {

    $id = trim($_POST['item_id']);
    $status = 'venduto';
    $orderMgr->updateStatusItem($id, $status);
    $alertMsg = 'deleted';
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

//  $orderMgr->RestoreOrderQuantity($orderId);
//  $alertMsg = 'order_quantity_resored';
//}

//$isRestored = false;
//if ($status == 'canceled') {
//  $o = $orderMgr->get($orderId);
//  $isRestored = $o->is_restored;
}

else if ($status == 'annullata' AND (isset($_POST['ripristina_order']) OR isset($_GET['ripristina_order']))){
   
  $status = 'inviata';
  $orderMgr->updateStatus($orderId, $status);

}

$count = 0;
?>

<a href="<?php echo ROOT_URL . 'admin/?page=orders-list'; ?>" class="back underline d-block">&laquo; Lista Pratiche</a>
<?php
$pratica = $orderItems[0]['pratica'];
?>


<h1 class="mb-4 d-inline">Pratica n. <?php echo esc_html($pratica1); ?></h1>
<div class="pdfDiv float-right mr-5 d-inline">
  <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($orderId); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0">
    <i class="fas fa-file-pdf fa-2x"></i>
  </a>
</div>

<hr class="m-3">

<?php //if ($status == 'inviata' AND $oi_status != 'NULL') : ?>
<?php if (count($orderItemsAccettare) > 0) : ?>
<h4 class="mb-3 font-weight-bold text-info">Libri da Accettare</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th>Cod. ISBN</th>
      <th>Prezzo</th>
      <th>Azioni</th>
    </tr>
  <?php foreach ($orderItemsAccettare as $item) : $count++; ?>
  
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['total_price']); ?> €</td>
      <td>
      <form method="post" class="right">
            <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
            <input name="delete" onclick="return confirm('Procedere ad eliminare il libro dalla Pratica?') ReloadLocation();" type="submit" class="btn btn-outline-danger btn-sm" value="Elimina">
          </form>
      <form method="post" class="left">
            <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
            <input name="vendere" onclick="return confirm('Procedere ad accettare il libro nella Pratica?') ReloadLocation();" type="submit" class="btn btn-outline-success btn-sm" value="Accetta">
          </form>
     
      </td>

    </tr>
  <?php endforeach; $count=0; ?>

  <?php
  $statusLbl = [
    'inviata'   => 'Inviata al database',
    'annullata'  => 'Annullata',
    'vendere'   => 'Da Vendere',
    'accettata'   => 'Accettata nel Database',
    'chiusa'   => 'Pronta per il ritiro',
    'pickup'   => 'Ritirato',  
  ];

  $cssClass = [
    'inviata'   => 'secondary',
    'annullata'  => 'danger',
    'vendere'   => 'success',
    'accettata'   => 'info',
    'chiusa'     => 'primary',
  ];
  ?>
 <!-- <tr> 
    <th colspan="100%">
      <h4 class="inline right font-weight-bold">Totale <?php echo (number_format((float)  ($orderTotalAccettare['total'] + $orderTotal['shipment_price']), 2, '.', ''));  ?> €</h4>
      <?php if ($status == 'payed') : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi spedizione ordine n. #<?php echo esc_html($orderId); ?> ?');" name="ship_order" type="submit" class="btn btn-primary m-0" value="Spedisci Ordine">
      </form>
      <?php endif; ?>
      <?php if ($status == 'canceled' && !$isRestored ) : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi ripristino prodotti ordine n. #<?php echo esc_html($orderId); ?> ?');" name="restore_order" type="submit" class="btn btn-danger m-0" value="Ripristina prodotti">
      </form>
      <?php endif; ?>
    </th>
  </tr>
<tr> 
    <th colspan="100%">
      <p class='text-danger font-weight-bold'>Note: <?php echo $orderTotal['note']; ?> </p>
    </th>
  </tr>
  <tr> 
    <th colspan="100%">
      <p class="lead">Spedizione: <?php echo $orderTotal['shipment_name']; ?> (<?php echo $orderTotal['shipment_price']; ?> €)</p>
    </th>
  </tr>

  <tr> 
    <th colspan="100%">
      <h4 class="inline right"><span class="badge badge-<?php echo $cssClass[$status] ?> badge-pill">Pratica <?php echo $statusLbl[$status] ?></span></h4>
    </th>
  </tr>-->

  <tr>  
    <th colspan="100%">
     <h4 class="inline right text-danger font-weight-bold">!!!  PER TERMINARE L'ACCETTAZIONE  !!! ricordarsi di accettare la Richiesta tornando alla pagina precedente e cliccando, nell'area Pratiche da Accettare, sul pulsante Accetta la Richiesta</h4>
    </th>
  </tr> 

</table>
<?php endif; ?>


<?php //if ($status == 'inviata' AND $oi_status != 'NULL') : ?>
<?php if (count($orderItemsAccettare1) > 0) : ?>
<h4 class="mb-3 font-weight-bold text-success">Libri Accettati</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th class="big-screen">Cod. ISBN</th>
      <th>Prezzo</th>
      <th>Status</th>
    </tr>
  <?php foreach ($orderItemsAccettare1 as $item) : $count++; ?>
  
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td class="big-screen"><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['total_price']); ?> €</td>
      <td><?php echo esc_html($item['order_item_status']); ?></td>
    </tr>
  <?php endforeach; $count=0; ?>

  <?php
  $statusLbl = [
    'inviata'   => 'Inviata al database',
    'annullata'  => 'Annullata',
    'vendere'   => 'Da Vendere',
    'accettata'   => 'Accettata nel Database',
    'chiusa'   => 'Pronta per il ritiro',
    'pickup'   => 'Ritirato',  
  ];

  $cssClass = [
    'inviata'   => 'secondary',
    'annullata'  => 'danger',
    'vendere'   => 'success',
    'accettata'   => 'info',
    'chiusa'     => 'primary',
  ];
  ?>
 <!-- <tr> 
    <th colspan="100%">
      <h4 class="inline right font-weight-bold">Totale <?php echo (number_format((float)  ($orderTotalAccettare['total'] + $orderTotal['shipment_price']), 2, '.', ''));  ?> €</h4>
      <?php if ($status == 'payed') : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi spedizione ordine n. #<?php echo esc_html($orderId); ?> ?');" name="ship_order" type="submit" class="btn btn-primary m-0" value="Spedisci Ordine">
      </form>
      <?php endif; ?>
      <?php if ($status == 'canceled' && !$isRestored ) : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi ripristino prodotti ordine n. #<?php echo esc_html($orderId); ?> ?');" name="restore_order" type="submit" class="btn btn-danger m-0" value="Ripristina prodotti">
      </form>
      <?php endif; ?>
    </th>
  </tr>
<tr> 
    <th colspan="100%">
      <p class='text-danger font-weight-bold'>Note: <?php echo $orderTotal['note']; ?> </p>
    </th>
  </tr>
  <tr> 
    <th colspan="100%">
      <p class="lead">Spedizione: <?php echo $orderTotal['shipment_name']; ?> (<?php echo $orderTotal['shipment_price']; ?> €)</p>
    </th>
  </tr>

  <tr> 
    <th colspan="100%">
      <h4 class="inline right"><span class="badge badge-<?php echo $cssClass[$status] ?> badge-pill">Pratica <?php echo $statusLbl[$status] ?></span></h4>
    </th>
  </tr>-->

  <tr>  
    <th colspan="100%">
     <h4 class="inline right text-danger font-weight-bold">!!!  PER TERMINARE L'ACCETTAZIONE  !!! ricordarsi di accettare la Richiesta tornando alla pagina precedente e cliccando, nell'area Pratiche da Accettare, sul pulsante Accetta la Richiesta</h4>
    </th>
  </tr> 

</table>
<?php endif; ?>

  <?php //if ($status == 'accettata' AND $oi_status == 'vendere') : ?>
  <?php if (count($orderItemsVendere) > 0) : ?>
  <h4 class="mb-3 font-weight-bold text-info">Libri da Vendere</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th class="big-screen">Cod. ISBN</th>
      <th>Prezzo di vendita</th>
    </tr>
  <?php foreach ($orderItemsVendere as $item) : $count++; ?>
  
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td class="big-screen"><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['total_price']+2); ?> €</td>
    </tr>
  <?php endforeach; $count=0; ?>


<?php
  $statusLbl = [
    'inviata'   => 'Inviata al database',
    'annullata'  => 'Annullata',
    'vendere'   => 'Da Vendere',
    'accettata'   => 'Accettata nel Database',
    'chiusa'   => 'Pronta per il ritiro',
    'pickup'   => 'Ritirato',  
  ];

  $cssClass = [
    'inviata'   => 'secondary',
    'annullata'  => 'danger',
    'vendere'   => 'success',
    'accettata'   => 'info',
    'chiusa'     => 'primary',
  ];
  ?>
  <!--<tr> 
    <th colspan="100%">
      <h4 class="inline right font-weight-bold">Totale <?php echo (number_format((float)  ($orderTotalVendere['total'] + $orderTotal['shipment_price']), 2, '.', ''));  ?> €</h4>
      <?php if ($status == 'payed') : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi spedizione ordine n. #<?php echo esc_html($orderId); ?> ?');" name="ship_order" type="submit" class="btn btn-primary m-0" value="Spedisci Ordine">
      </form>
      <?php endif; ?>
      <?php if ($status == 'canceled' && !$isRestored ) : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi ripristino prodotti ordine n. #<?php echo esc_html($orderId); ?> ?');" name="restore_order" type="submit" class="btn btn-danger m-0" value="Ripristina prodotti">
      </form>
      <?php endif; ?>
    </th>
  </tr>-->
<!--<tr> 
    <th colspan="100%">
      <p class='text-danger font-weight-bold'>Note: <?php echo $orderTotal['note']; ?> </p>
    </th>
  </tr>
  <tr> 
    <th colspan="100%">
      <p class="lead">Spedizione: <?php echo $orderTotal['shipment_name']; ?> (<?php echo $orderTotal['shipment_price']; ?> €)</p>
    </th>
  </tr>

  <tr> 
    <th colspan="100%">
      <h4 class="inline right"><span class="badge badge-<?php echo $cssClass[$status] ?> badge-pill">Pratica <?php echo $statusLbl[$status] ?></span></h4>
    </th>
  </tr>-->
</table>
<?php endif; ?>

  <?php //if ($status == 'accettata' AND $oi_status == 'venduto') : ?>
  <?php if (count($orderItemsVenduto) > 0) : ?>
  <h4 class="mb-3 font-weight-bold text-info">Libri Venduti</h4>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Quantità</th>
      <th class="big-screen">Cod. ISBN</th>
      <th>Prezzo di vendita</th>
    </tr>
  <?php foreach ($orderItemsVenduto as $item) : $count++; ?>
  
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td><?php echo esc_html($item['quantity']); ?></td>
      <td class="big-screen"><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['total_price']+2); ?> €</td>
    </tr>
  <?php endforeach; $count=0; ?>


<?php
  $statusLbl = [
    'inviata'   => 'Inviata al database',
    'annullata'  => 'Annullata',
    'vendere'   => 'Da Vendere',
    'accettata'   => 'Accettata nel Database',
    'chiusa'   => 'Pronta per il ritiro',
    'pickup'   => 'Ritirato',  
  ];

  $cssClass = [
    'inviata'   => 'secondary',
    'annullata'  => 'danger',
    'vendere'   => 'success',
    'accettata'   => 'info',
    'chiusa'     => 'primary',
  ];
  ?>
 <!-- <tr> 
    <th colspan="100%">
      <h4 class="inline right font-weight-bold">Totale <?php echo (number_format((float)  ($orderTotalVenduto['total'] + $orderTotal['shipment_price']), 2, '.', ''));  ?> €</h4>
      <?php if ($status == 'payed') : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi spedizione ordine n. #<?php echo esc_html($orderId); ?> ?');" name="ship_order" type="submit" class="btn btn-primary m-0" value="Spedisci Ordine">
      </form>
      <?php endif; ?>
      <?php if ($status == 'canceled' && !$isRestored ) : ?>
      <hr>
      <form method="post" class="inline right">
        <input onclick="return confirm('Confermi ripristino prodotti ordine n. #<?php echo esc_html($orderId); ?> ?');" name="restore_order" type="submit" class="btn btn-danger m-0" value="Ripristina prodotti">
      </form>
      <?php endif; ?>
    </th>
  </tr>
<tr> 
    <th colspan="100%">
      <p class='text-danger font-weight-bold'>Note: <?php echo $orderTotal['note']; ?> </p>
    </th>
  </tr>
  <tr> 
    <th colspan="100%">
      <p class="lead">Spedizione: <?php echo $orderTotal['shipment_name']; ?> (<?php echo $orderTotal['shipment_price']; ?> €)</p>
    </th>
  </tr>

  <tr> 
    <th colspan="100%">
      <h4 class="inline right"><span class="badge badge-<?php echo $cssClass[$status] ?> badge-pill">Pratica <?php echo $statusLbl[$status] ?></span></h4>
    </th>
  </tr>-->
</table>
<?php endif; ?>

<hr class="m-3">

<table class="table table-bordered">
    <tr>
      <th><h4 class="inline right text-info font-weight-bold">Totale Da Vendere</h4></th>
      <th><h4 class="inline right text-success font-weight-bold">Totale Venduto</h4></th>
      <th><h4 class="inline right text-warning font-weight-bold">Totale per Comitato</h4></th>
      <th><h4 class="inline right text-danger font-weight-bold">Totale per Venditore</h4></th>
    </tr>
    <tr>
      <td><h4 class="inline right text-info font-weight-bold"><?php echo esc_html($orderTotalVendere['total']); ?> €</h4></td>
      <td><h4 class="inline right text-success font-weight-bold"><?php echo esc_html($orderTotalVenduto['total']); ?> €</h4></td>
      <td><h4 class="inline right text-warning font-weight-bold"><?php echo esc_html($orderTotalVenduto['total_com']); ?> €</h4></td>
      <td><h4 class="inline right text-danger font-weight-bold"><?php echo esc_html($orderTotalVenduto['total_vend']); ?> €</h4></td>
    </tr>
</table>

<hr class="m-3">

<?php if ($address) : ?>
  <h4 class="text-warning font-weight-bold">Dettagli Venditore</h4>

  <ul class="list-group">
    <li class="list-group-item">
      <strong>Nominativo: </strong><br>
      <?php echo esc_html($last_name); ?>   <?php echo esc_html($first_name); ?>
    </li>
    <li class="list-group-item">
      <strong>Email: </strong><br>
      <?php echo esc_html($email); ?>
    </li>
    <li class="list-group-item">
      <strong>Indirizzo: </strong><br>
      <?php echo esc_html($address['street']); ?> - <?php echo esc_html($address['city']); ?> (<?php echo esc_html($address['cap']); ?>)
    </li>
  </ul>
<?php endif; ?>

