<?php

  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  $orderMgr = new OrderManager();
  //$cm = new CartManager1();  

  if (isset($_POST['vendere'])) {

    $id = trim($_POST['item_id']);
    $status = 'vendere';
    $orderMgr->updateStatusItem2($id, $status);
    $alertMsg = 'deleted';
  }

  if (isset($_POST['venduto'])) {

    $productId = trim($_POST['item_id']);
    $status = 'venduto';
    $orderMgr->updateStatusItem($productId, $status);
    $alertMsg = 'deleted';
  }  

  $orderItems2 = $orderMgr->getOrderItems2();
  $orderTotalVenduto = $orderMgr->getOrderTotalVenduto1()[0];
  $orderTotalVendutoPerData = $orderMgr->getOrderTotalVendutoPerData()[0]

 
?>

<h1>Totale Incassi</h1>

<hr class="m-3">

<table class="table table-bordered">
    <tr>
       <th><h4 class="inline right text-info font-weight-bold">Totale Importo Incasso Giornaliero</h4></th>
       <th><h4 class="inline right text-danger font-weight-bold">Totale Importo Incasso Mercatino</h4></th>
    </tr>
    <tr>
       <td><h4 class="inline right text-info font-weight-bold"><?php echo esc_html($orderTotalVendutoPerData['total']); ?> €</h4></td>
       <td><h4 class="inline right text-danger font-weight-bold"><?php echo esc_html($orderTotalVenduto['total']); ?> €</h4></td>
    </tr>
</table>


