<?php

  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  //global $loggedInUser;
  $orderMgr = new OrderManager();
  //$cm = new CartManager1();  

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

  $orderItems4 = $orderMgr->getOrderItems4();

  $count = 0;

?>

<h1>Elenco Libri da Vendere</h1>

<?php if (count($orderItems4) > 0) : ?>
<table id="table" class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Titolo</th>
      <th scope="col">Cod. ISBN</th>      
      <th scope="col" >Prezzo Vendita</th>
      <th scope="col">Quantità Disponibile</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($orderItems4 as $item) : $count++; ?>
    <tr>
    <td name="titolo"><?php echo esc_html($item['product_name']); ?></td>
    <td name="ISBN"><?php echo esc_html($item['product_ISBN']); ?></td>
    <td name="prezzo"><?php echo esc_html($item['total_price']+2); ?> €</td>
    <td name="quantità"><?php echo esc_html($item['quantita']);?></td>
    </tr>
    <?php endforeach ; ?>
  </tbody>
</table>
<?php else : ?>
  <p>Nessun Libro da Vendere presente...</p>
<?php endif ; ?>

<script>
 $(document).ready(function() {
    $('#table').DataTable({
      bLengthChange: false
    });
} );
</script>

