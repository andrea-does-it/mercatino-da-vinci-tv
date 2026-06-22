<?php
// Prevent from direct access
if (! defined('ROOT_URL')) {
  die;
}

$cm = new CartManager();
$cat= new CategoryManager();
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
  $cm->ResetExpiredCarts();
}

global $alertMsg;
$mgr = new ProductManager();

if (isset($_POST['delete'])) {

  $id = trim($_POST['id']);
  $mgr->DeleteProduct($id);
  $alertMsg = 'deleted';
}

$products = $mgr->getAll();
?>

<a href="<?php echo ROOT_URL . 'admin/?page=product'; ?>" class="btn btn-primary mb-3">Aggiungi Libro</a>
<a href="<?php echo ROOT_URL; ?>api/admin/products-export.php?format=csv" class="btn btn-outline-success mb-3"><i class="fas fa-file-csv"></i> Esporta CSV</a>
<a href="<?php echo ROOT_URL; ?>api/admin/products-export.php?format=xls" class="btn btn-outline-success mb-3"><i class="fas fa-file-excel"></i> Esporta Excel</a>

<h1>Elenco Libri Adottati</h1>

<?php if (count($products) > 0) : ?>
<div class="form-inline mb-3">
  <label class="mr-2 font-weight-bold" for="filterEsaurimento">Esaurimento:</label>
  <select id="filterEsaurimento" class="form-control mr-4">
    <option value="">Tutti</option>
    <option value="In esaurimento">In esaurimento</option>
    <option value="No">No</option>
  </select>
  <label class="mr-2 font-weight-bold" for="filterNascosto">Nascosto:</label>
  <select id="filterNascosto" class="form-control">
    <option value="">Tutti</option>
    <option value="Nascosto">Nascosto</option>
    <option value="Visibile">Visibile</option>
  </select>
</div>
<table id="table" class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Immagini</th>
      <th scope="col" class="big-screen">Titolo</th>
      <th scope="col" class="big-screen">Cod ISBN</th>
      <th scope="col">Prezzo</th>
      <th scope="col" class="big-screen">Note Volumi</th>
      <th scope="col">Esaurimento</th>
      <th scope="col">Nascosto</th>
      <th scope="col" class="right">Azioni</th>
    </tr>
  </thead>
  <tbody>

    <?php $x=0;
    foreach ($products as $product){
      echo '<tr><td>';
      $proimg = $mgr->GetProductWithImages($product->id); 

      if ($proimg->images ) {
      echo '<div id="thumbnail'.$x.'" class="thumbnail carousel slide" data-ride="carousel" data-interval="false" >
        <div class="carousel-inner">';
         
        $active = 'active';
            foreach ($proimg->images as $image){
            echo '<div class="carousel-item '.$active.'">';
            echo '<img class="thumbnail" src="'.ROOT_URL . '/images/' . $proimg->id . '/' . $image->id . '_thumbnail.' . $image->image_extension.'" >
                  </div>';
            $active='';

            } echo'</div><a class="carousel-control-prev" href="#thumbnail'.$x.'" role="button" data-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#thumbnail'.$x.'" role="button" data-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="sr-only">Next</span>
            </a>
          </div>';
                 $x++;
          }else{
                echo '<img src="../images/noimage.jpg" class="img-fluid thumbnail" />';
          }//END IF
   
     
      echo '</td>'; ?>
     
        <td><?php echo esc_html($product->name); ?></td>
 <!--       <td class="big-screen"><?php 
                                $category=$cat->GetCategory($product->category_id);echo esc_html($category->name); ?></td>-->
        <td class="big-screen"><?php echo esc_html($product->ISBN); ?></td>
        <td>€ <?php echo esc_html($product->price); ?></td>
        <td class="big-screen"><?php echo esc_html($product->nota_volumi); ?></td>
        <td><?php echo ($product->fl_esaurimento == 1) ? '<span class="badge badge-warning">In esaurimento</span>' : '<span class="badge badge-light">No</span>'; ?></td>
        <td><?php echo ($product->nascosto == 1) ? '<span class="badge badge-secondary">Nascosto</span>' : '<span class="badge badge-success">Visibile</span>'; ?></td>
        <td class="col-lg-2">
          <form method="post" class="right">
            <input type="hidden" name="id" value="<?php echo esc_html($product->id); ?>">
            <input name="delete" onclick="return confirm('Procedere ad eliminare?');" type="submit" class="btn btn-outline-danger btn-sm" value="Elimina">
          </form>
          <a class="right btn btn-outline-secondary btn-sm" href="<?php echo ROOT_URL . 'admin/?page=product'; ?>&id=<?php echo esc_html($product->id); ?>">Vedi</a>
        </td>
    </tr>
    <?php   } ?>
  </tbody>
</table>
<?php else : ?>
  <p>Nessun Prodotto presente...</p>
<?php endif ; ?>

<script>
 $(document).ready(function() {
    var table = $('#table').DataTable({
      bLengthChange: false,
      pageLength: 10
    });
    $('.dataTables_scrollBody').css('height', '400px');

    // Filtri rapidi sulle colonne Esaurimento (indice 5) e Nascosto (indice 6).
    $('#filterEsaurimento').on('change', function() {
      table.column(5).search(this.value).draw();
    });
    $('#filterNascosto').on('change', function() {
      table.column(6).search(this.value).draw();
    });
} );
</script>
