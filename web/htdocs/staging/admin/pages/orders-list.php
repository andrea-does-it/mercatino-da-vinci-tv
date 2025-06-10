<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  $error = false;
  global $loggedInUser;
  //var_dump($loggedInUser); die;
  global $alertMsg;

  $userId = $loggedInUser->id;
  $orderMgr = new OrderManager();

  if (!$loggedInUser) {
    echo "<script>location.href='".ROOT_URL."auth?page=login';</script>";
    exit;
  }

  
  //$status = 'payed';
  //$payedOrders = $orderMgr->getAllOrders($status);

  $status = 'chiusa';
  $chiusaOrders = $orderMgr->getAllOrders($status);

  $status = 'inviata';
  $inviataOrders = $orderMgr->getAllOrders($status);

  $status = 'accettata';
  $accettataOrders = $orderMgr->getAllOrders($status);

  //$status = 'shipped';
  //$shippedOrders = $orderMgr->getAllOrders($status);

  //$status = 'pending';
  //$pendingOrders = $orderMgr->getAllOrders($status);

  $status = 'annullata';
  $canceledOrders = $orderMgr->getAllOrders($status);

  $count = 0;
?>

<h1 cass="mb-4">Tutte le Pratiche</h1>

<?php if (count($chiusaOrders) > 0) :  ?>
  <h4 class="mb-3 font-weight-bold text-info">Pratiche Chiuse</h4>
  <table class="table table-bordered">
  <thead>
      <tr>
        <th class="big-screen">#</th>
        <th class="big-screen">Num. Pratica</th>
        <th>Data Chiusura</th>
        <th>Venditore</th>
        <th>Link</th>
        <th class="text-center">PDF</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($chiusaOrders as $order) : $count++; ?>
      <tr>
        <td class="big-screen"><?php echo $count; ?></td>
        <td class="big-screen"><?php echo esc_html($order['pratica']); ?></td>
        <td><?php echo esc_html($order['shipped_date']); ?></td>
        <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
        
        <td>
          <a class="underline" href="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
        </td>
        <td class="text-center">
          <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
        </td>
      </tr>
    <?php endforeach; $count=0; ?>
    </tbody>
</table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono Pratiche Chiuse.</p>
<?php endif; ?>

<hr>

<?php if (count($accettataOrders) > 0) : ?>
  <h4 class="mb-3 font-weight-bold text-info">Pratiche Accettate</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="big-screen">#</th>
        <th class="big-screen">Num. Pratica</th>
        <th class="big-screen">Data Accettazione</th>
        <th>Venditore</th>
        <th>Link</th>
        <th class="text-center">PDF</th>
        <th class="text-center">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($accettataOrders as $order) : $count++; ?>
        <tr>
          <td class="big-screen"><?php echo $count; ?></td>
          <td class="big-screen"><?php echo esc_html($order['pratica']); ?></td>
          <td class="big-screen"><?php echo esc_html($order['shipped_date']); ?></td>
          <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
          <td>
            <a class="underline" href="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
          </td>
          <td class="text-center">
            <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
          </td>
          <td>
            <form method="post" action="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>&chiusa_order=1" class="inline right">
            <input onclick="return confirm('Confermi che la Pratrica n. <?php echo esc_html($order['pratica']); ?> è stata chiiusa?');" name="chiusa_order" type="submit" class="btn btn-sm btn-warning m-0" value="Chiudi &raquo;">
            </form>  
          </td>
        </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono Pratiche Accettate.</p>
<?php endif; ?>

<hr>

<?php if (count($inviataOrders) > 0) : ?>
  <!--<h4 class="mb-3">Ordini Pagamento Postumo</h4>-->
  <h4 class="mb-3 font-weight-bold text-info">Pratiche da Accettare</h4>
  <h4 class="mb-3 font-weight-bold text-danger">ATTENZIONE: cercare il nome dell'utente su "Search"; cliccare su "Vedi" per visualizzare la lista dei libri inseriti</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="big-screen">#</th>
        <th class="big-screen text-center">Num. Invio</th>
        <th>Data</th>
        <th>Venditore</th>
        <th>Link</th>
        <th class="text-center">PDF</th>
        <th class="text-center">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($inviataOrders as $order) : $count++; ?>
        <tr>
          <td class="big-screen"><?php echo $count; ?></td>
          <td class="big-screen text-center"><?php echo esc_html($order['order_id']); ?></td>
          <td><?php echo esc_html($order['created_date']); ?></td>
          <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
          <td>
            <a class="underline" href="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
          </td>
          <td class="text-center">
            <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
          </td>
          <td>
            <form method="post" action="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>&accettata_order=1" class="inline right">
            <input onclick="return confirm('Confermi che la Richiesta con invio n. <?php echo esc_html($order['order_id']); ?> deve essere accettata?');" name="accettata_order" type="submit" class="btn btn-sm btn-info m-0" value="Accetta la Richiesta &raquo;">
           </form>  
          <!--<form method="post" action="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>&annullata_order=1" class="inline left">
            <input onclick="return confirm('Confermi che la Richiesta con invio n. <?php echo esc_html($order['order_id']); ?> deve essere annullata?');" name="annullata_order" type="submit" class="btn btn-sm btn-danger m-0" value="Elimina &raquo;">
            </form>--> 
          </td>
        </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono Pratiche da Accettare.</p>
<?php endif; ?>


<!--<hr>

<?php if (count($canceledOrders) > 0) :  ?>
  <h4 class="mb-3 font-weight-bold text-info">Pratiche Annullate</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="big-screen">#</th>
        <th class="big-screen">Num. Invio</th>
        <th>Data Invio</th>
        <th>Venditore</th>
        <th>Link</th>
        <th>Azioni</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($canceledOrders as $order) : $count++; ?>
      <tr class="text-danger">
        <td class="big-screen"><?php echo $count; ?></td>
        <td class="big-screen"><?php echo esc_html($order['order_id']); ?></td>
        <td><?php echo esc_html($order['created_date']); ?></td>
        <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
        <td>
          <a class="underline" href="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
        </td>
        <td> 
        <form method="post" action="<?php echo ROOT_URL . 'admin/?page=process-order&id=' . esc_html($order['order_id']); ?>&ripristina_order=1" class="inline right">
            <input onclick="return confirm('Confermi che la Pratica n. <?php echo esc_html($order['order_id']); ?> deve essere ripristinata?');" name="ripristina_order" type="submit" class="btn btn-sm btn-info m-0" value="Ripristina &raquo;">
            </form>  
        </td>
      </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
</table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono Pratiche Annullate.</p>
<?php endif; ?>-->

<hr>

<script>
 $(document).ready(function() {
    $('table.table').DataTable({
      bLengthChange: false,
      pageLength: 5
    });
} );
</script>