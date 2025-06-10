<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  if (!$loggedInUser) {
    echo "<script>location.href='".ROOT_URL."auth?page=login';</script>";
    exit;
  }


  if (!isset($_GET['id'])){
    echo "<script>location.href='".ROOT_URL."shop/?page=my-orders&msg=not_found';</script>";
    exit;
  }

  $orderId = esc($_GET['id']);
  $orderMgr = new OrderManager();
  
  $order = $orderMgr->get($orderId);
  if ($loggedInUser->user_type != 'admin' && $order->user_id != $loggedInUser->id) {
    echo "<script>location.href='".ROOT_URL."shop/?page=my-orders&msg=forbidden';</script>";
    exit;
  }

  $orderItems = $orderMgr->getOrderItems($orderId);
  $orderTotal = $orderMgr->getOrderTotalVenduto($orderId)[0];

  $pratica = $orderItems[0]['pratica'];
  $status = $orderItems[0]['order_status'];

  //var_dump($orderTotal);die;
  if (count($orderItems) == 0) {
    echo "<script>location.href='".ROOT_URL."admin/?page=orders-list&msg=order_empty';</script>";
    exit;
  }
  $count = 0;
?>

<a href="<?php echo ROOT_URL . 'shop/?page=my-orders'; ?>" class="back underline d-block">&laquo; Le mie pratiche</a>

<h1 class="mb-4 d-inline">Pratica n. <?php echo esc_html($pratica); ?></h1>
<div class="pdfDiv float-right d-inline mr-5">
  <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($orderId); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0">
    <i class="fas fa-file-pdf fa-2x"></i>
  </a>
</div>
  <table class="table table-bordered">
    <tr>
      <th class="big-screen">#</th>
      <th>Titolo</th>
      <th>Cod. ISBN</th>
      <th class="big-screen">Prezzo</th>
      <th>Status</th>
    </tr>
  <?php foreach ($orderItems as $item) : $count++; ?>
  
    <tr>
      <td class="big-screen"><?php echo $count; ?></td>
      <td><?php echo esc_html($item['product_name']); ?></td>
      <td class="big-screen"><?php echo esc_html($item['product_ISBN']); ?></td>
      <td><?php echo esc_html($item['total_price']); ?></td>
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
  <tr> 
    <th colspan="100%">
      <h4 class="inline">Totale Importo Libri Venduti            <?php echo (number_format((float)  ($orderTotal['total_vend'] + $orderTotal['shipment_price']), 2, '.', '')); ?>  €</h4>
      <h4 class="inline right"><span class="badge badge-<?php echo $cssClass[$status] ?> badge-pill">Pratica <?php echo $statusLbl[$status] ?></span></h4>
    </th>
  </tr>
</table>

