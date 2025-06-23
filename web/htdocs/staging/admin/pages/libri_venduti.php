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
    
    // Get the product_id from the order_item before updating status
    $orderItemDetails = $orderMgr->getOrderItemById($id);
    $product_id = $orderItemDetails['product_id'];
    
    // Update the status to 'vendere'
    $orderMgr->updateStatusItem2($id, $status);
    
    // Remove the first row from order_item1 with this product_id
    $orderMgr->removeFirstOrderItem1($product_id);
    
    $alertMsg = 'deleted';
  }

  if (isset($_POST['venduto'])) {

    $productId = trim($_POST['item_id']);
    $status = 'venduto';
    $orderMgr->updateStatusItem($productId, $status);
    $alertMsg = 'deleted';
  }  

  $orderItems2 = $orderMgr->getOrderItems2();

  $count = 0;

?>

<h1>Elenco Libri Venduti</h1>

<?php if (count($orderItems2) > 0) : ?>

<head> 
  <meta charset="UTF-8"> 
</head> 
<table id="table" class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Pratica</th>
      <th scope="col">Venditore</th>      
      <th scope="col">Titolo</th>
      <th scope="col">Quantità</th>
      <th scope="col">Cod. ISBN</th>
      <th scope="col">Volumi</th>      
      <th scope="col">Prezzo Vendita</th>
      <th scope="col">Data vendita</th>
      <th scope="col">Azioni</th>

    </tr>
  </thead>
  <tbody>
    <?php foreach ($orderItems2 as $item) : $count++; ?>
    <tr>
    <td><?php echo esc_html($item['pratica']); ?></td>
    <td><?php echo esc_html($item['last_name']); ?>   <?php echo esc_html($item['first_name']); ?></td>
    <td><?php echo esc_html($item['product_name']); ?></td>
    <td><?php echo esc_html($item['quantity']); ?></td>
    <td><?php echo esc_html($item['product_ISBN']); ?></td>
    <td><?php echo esc_html($item['product_nota_volumi']); ?></td>
    <td><?php echo esc_html($item['total_price']+2); ?> €</td>
    <td><?php echo esc_html($item['data_vendita']); ?></td>
    <td>
    <form method="post" class="left">
    <input type="hidden" name="item_id" value="<?php echo esc_html($item['order_item_id']); ?>">
            <input name="vendere" onclick="return confirm('Ripristinare il libro allo stato da vendere?');" type="submit" class="btn btn-outline-info btn-sm" value="Rimetti in vendita">
          </form>
      <?php //endif; ?>
     
      </td>
    </tr>
    <?php endforeach ; ?>
  </tbody>
</table>
<?php else : ?>
  <p>Nessun Libro Venduto presente...</p>
<?php endif ; ?>

<script>
 $(document).ready(function() {
    $('#table').DataTable({
      bLengthChange: false
    });
} );
</script>