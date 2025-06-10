<?php

  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  $orderMgr = new OrderManager();
  //$cm = new CartManager1();  

//  if (isset($_POST['Azzera'])) {

//    $id = trim($_POST['item_id']);
//    $status = 'eliminato';
//    $orderMgr->updateStatusItem3($id, $status);
//    $alertMsg = 'deleted';
//  }

  if (isset($_POST['Concludi'])) {

    $id = trim($_POST['item_id']);
    $orderMgr->removeOrderItem();
    $alertMsg = 'deleted';
  }
  
  if (isset($_POST['vendere'])) {

    $id = trim($_POST['item_id']);
    $status = 'vendere';
    $orderMgr->updateStatusItem($id, $status);
    $alertMsg = 'deleted';
  }

  if (isset($_POST['venduto'])) {

    $productId = trim($_POST['item_id']);
    $status = 'venduto';
    $orderMgr->updateStatusItem1($productId, $status);
    $orderMgr->calcolaVendita($productId, $status);
    $alertMsg = 'deleted';
  }  

  $orderItems1 = $orderMgr->getOrderItems3();
  $orderTotalVendita = $orderMgr->getOrderTotalVendita()[0];

  $count = 0;

?>

<h1>Riepilogo Vendita</h1>

<?php if (count($orderItems1) > 0) : ?>
<table id="table" class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Titolo</th>
      <th scope="col">Quantità</th>
      <th scope="col">Cod. ISBN</th>      
      <th scope="col" >Prezzo</th>
      <th scope="col" >Azioni</th>

    </tr>
  </thead>
  <tbody>
    <?php foreach ($orderItems1 as $item) : $count++; ?>
    <tr>
      <td name="titolo"><?php echo esc_html($item['product_name']); ?></td>
      <td name="quantità"><?php echo esc_html($item['quantity']); ?></td>
      <td name="ISBN"><?php echo esc_html($item['product_ISBN']); ?></td>
      <td name="prezzo"><?php echo esc_html($item['total_price']); ?> €</td>
    </tr>
    <?php endforeach ; ?>
  </tbody>
</table>
<?php else : ?>
  <p>Nessun Libro da Vendere presente...</p>
<?php endif ; ?>


<hr class="m-3">

<table class="table table-bordered">
    <tr>
       <th><h4 class="inline right text-info font-weight-bold">Totale Importo Vendita</h4></th>
    </tr>
    <tr>
       <td><h4 class="inline right text-info font-weight-bold"><?php echo esc_html($orderTotalVendita['total']); ?> €</h4></td>
    </tr>
</table>

<table class="table table-bordered">
    <tr>
     <form method="post" class="right">
      <input  type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
      <input class="right" name="Concludi" onclick="return confirm('Procedere con la vendita del libro?') ReloadLocation();" type="submit" class="btn btn-outline-danger btn-block" value="Concludi Vendita">
     </form>
    </tr>
</table>

<script>
 $(document).ready(function() {
    $('#table').DataTable({
      bLengthChange: false
    });
} );
</script>

