<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  if (!$loggedInUser) {
    echo "<script>location.href='".ROOT_URL."auth?page=login&msg=login_for_checkout';</script>";
    exit;
  }

  global $loggedInUser;

  $userId = $loggedInUser->id;
  $orderMgr = new OrderManager();

  $status = 'chiusa';
  $chiusaOrders = $orderMgr->getAllOrders1($status, $userId);

  $status = 'inviata';
  $inviataOrders = $orderMgr->getAllOrders1($status, $userId);

  $status = 'accettata';
  $accettataOrders = $orderMgr->getAllOrders1($status, $userId);

  $status = 'annullata';
  $canceledOrders = $orderMgr->getAllOrders1($status, $userId);

  $count = 0;
?>

<h1 cass="mb-4">Le mie pratiche</h1>

<?php if (count($chiusaOrders) > 0) :  ?>
  <h4 class="mb-3 font-weight-bold text-info">Pratiche Chiuse</h4>
  <table class="table table-bordered">
  <thead>
    <tr>
      <th class="big-screen">#</th>
      <th>Num. Pratica</th>
      <th>Data Chiusura</th>
      <th>Venditore</th>
      <th>Link</th>
      <th class="text-center">PDF</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($chiusaOrders as $order) : $count++; ?>
  
    <tr class="text-primary">
    <td class="big-screen"><?php echo $count; ?></td>
        <td class="big-screen"><?php echo esc_html($order['pratica']); ?></td>
        <td><?php echo esc_html($order['shipped_date']); ?></td>
        <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
        <td>
          <a class="underline" href="<?php echo ROOT_URL . 'shop/?page=view-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
        </td>
        <td class="text-center">
          <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
        </td>
      </tr>
    <?php endforeach; $count=0; ?>
    </tbody>
</table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono pratiche chiuse.</p>
<?php endif; ?>

<hr>

<?php if (count($accettataOrders) > 0) : ?>
  <h4 class="mb-3 font-weight-bold text-info">Pratiche accettate</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="big-screen">#</th>
        <th class="big-screen">Num. Pratica</th>
        <th class="big-screen">Data Accettazione</th>
        <th>Venditore</th>
        <th>Link</th>
        <th class="text-center">PDF</th>
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
            <a class="underline" href="<?php echo ROOT_URL . 'shop/?page=view-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
          </td>
          <td class="text-center">
            <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
          </td>
        </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono pratiche accettate.</p>
<?php endif; ?>

<hr>

<?php if (count($inviataOrders) > 0) : ?>
  <!--<h4 class="mb-3">Ordini Pagamento Postumo</h4>-->
  <h4 class="mb-3 font-weight-bold text-info">Pratiche da accettare</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="big-screen">#</th>
        <th class="big-screen">Num. Invio</th>
        <th>Data Invio</th>
        <th>Venditore</th>
        <th>Link</th>
        <th class="text-center">PDF</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($inviataOrders as $order) : $count++; ?>
        <tr>
          <td class="big-screen"><?php echo $count; ?></td>
          <td class="big-screen"><?php echo esc_html($order['order_id']); ?></td>
          <td><?php echo esc_html($order['created_date']); ?></td>
          <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
          <td>
            <a class="underline" href="<?php echo ROOT_URL . 'shop/?page=view-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
          </td>
          <td class="text-center">
            <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
          </td>
        </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono pratiche da accettare.</p>
<?php endif; ?>

<hr>

<?php if (count($canceledOrders) > 0) : ?>
  <h4 class="mb-3 font-weight-bold text-info">Pratiche annullate</h4>
  <table class="table table-bordered">
    <thead>
      <tr>
        <th class="big-screen">#</th>
        <th>Num. Pratica</th>
        <th>Data Invio</th>
        <th>Venditore</th>
        <th>Link</th>
        <th class="text-center">PDF</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($canceledOrders as $order) : $count++; ?>
        <tr class="text-danger">
          <td class="big-screen"><?php echo $count; ?></td>
          <td><?php echo esc_html($order['order_id']); ?></td>
          <td><?php echo esc_html($order['created_date']); ?></td>
          <td><?php echo esc_html($order['user_surname'] . '  ' . $order['user_name']); ?></td>
          <td>
            <a class="underline" href="<?php echo ROOT_URL . 'shop/?page=view-order&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
          </td>
          <td class="text-center">
            <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
          </td>
        </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono pratiche annullate.</p>
<?php endif; ?>

<script>
 $(document).ready(function() {
    $('table.table').DataTable({
      bLengthChange: false,
      pageLength: 5
    });
} );
</script>
