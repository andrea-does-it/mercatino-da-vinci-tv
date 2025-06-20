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

  
  $status = 'accettata';
  $accettataOrders = $orderMgr->getAllOrders($status);

  $count = 0;
?>

<h1 cass="mb-4">Tutte le Pratiche</h1>


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
        <th class="text-center">Etichetta</th>
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
            <a class="underline" href="<?php echo ROOT_URL . 'admin/?page=libri_per_pratica_item&id=' . esc_html($order['order_id']); ?>">Vedi &raquo;</a>
          </td>
          <td class="text-center">
            <a target="_blank" href="<?php echo ROOT_URL . 'shop/invoices/print-invoice.php?orderId=' . esc_html($order['order_id']); ?>" title="stampa PDF" class="btn btn-lg btn-link p-0"><i class="fas fa-file-pdf"></i></a>
          </td>
          <td class="text-center">
            <a href="<?php echo ROOT_URL . 'admin/print-label.php?pratica=' . esc_html($order['pratica']) . '&autoprint=1&source=libri_per_pratica'; ?>" title="Stampa Etichetta con Barcode" class="btn btn-sm btn-success">
              <i class="fas fa-barcode"></i> Etichetta
            </a>
          </td>
        </tr>
      <?php endforeach; $count=0; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class='text-danger font-weight-bold'>Non ci sono Pratiche Accettate.</p>
<?php endif; ?>


<script>
 $(document).ready(function() {
    $('table.table').DataTable({
      bLengthChange: false,
      pageLength: 20
    });
} );
</script>