<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  if (!isset($_GET['id'])) {
    Header('Location '. ROOT_URL);
    exit;
  } 

  $id = esc_html(trim($_GET['id']));

  $pm = new ProductManager();
  $product = $pm->GetProductWithImages($id);
  $lineThrough = $product->disc_price ? 'text-muted line-through' : '';
  $isOutOfStock = ($product->fl_esaurimento == 1);
  
  if ($product->id == 0) {
    echo "<script>location.href='".ROOT_URL."shop/?page=products-list&msg=not_found';</script>";
    exit;
  }

  // Handle add to cart form submission
  if (isset($_POST['add_to_cart'])) {
    if ($isOutOfStock) {
      $alertMsg = 'product_unavailable';
      echo "<script>location.href='".ROOT_URL."shop/?page=view-product&id=".$id."&msg=$alertMsg';</script>";
      exit;
    }

    $cm = new CartManager();
    $cartId = $cm->getCurrentCartId();
    $success = $cm->addToCart($id, $cartId);

    if ($success) {
      $alertMsg = 'add_to_cart';
    } else {
      $alertMsg = 'product_unavailable';
    }
    echo "<script>location.href='".ROOT_URL."shop/?page=view-product&id=".$id."&msg=$alertMsg';</script>";
    exit;
  }
?>
<a class="back underline" href="<?php echo ROOT_URL; ?>shop/?page=products-list">&laquo; Lista Libri Adottati</a>

<div class="jumbotron">
  <h1 class="display-5"><?php echo esc_html($product->name); ?></h1>
  
  <?php if (!empty($product->ISBN)) : ?>
    <p class="lead">
      <strong>Cod. ISBN:</strong> <?php echo esc_html($product->ISBN); ?>
    </p>
  <?php endif; ?>
  
  <?php if (!empty($product->autori)) : ?>
    <p class="lead">
      <strong>Autori:</strong> <?php echo esc_html($product->autori); ?>
    </p>
  <?php endif; ?>
  
  <?php if (!empty($product->editore)) : ?>
    <p class="lead">
      <strong>Editore:</strong> <?php echo esc_html($product->editore); ?>
    </p>
  <?php endif; ?>

  <?php if (!empty($product->nota_volumi)) : ?>
    <p class="lead">
      <strong>Volumi:</strong> <?php echo esc_html($product->nota_volumi); ?>
    </p>
  <?php endif; ?>

  <p class="lead <?php echo $lineThrough ?>">
    Prezzo: <?php echo esc_html($product->price); ?> €
  </p>
  
  <?php if ($product->disc_price): ?>
  <span class="lead badge-pill badge-warning">
    Prezzo Scontato: <?php echo esc_html($product->disc_price); ?> €
  </span>
  <br>
  <span data-inizio-sconto="<?php echo esc_html($product->data_inizio_sconto); ?>" data-fine-sconto="<?php echo esc_html($product->data_fine_sconto); ?>" class="countdown badge-pill badge-warning"></span>
  <?php endif; ?>

  <?php if ($isOutOfStock) : ?>
    <div class="alert alert-warning bg-warning text-dark p-3 mt-3">
      <strong>Libro non caricabile</strong> - Questo prodotto non è attualmente disponibile per l'aggiunta al carrello.
    </div>
  <?php endif; ?>

  <hr class="my-4">

<?php if ($product->images ) : ?>
  <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
    <ol class="carousel-indicators">
      <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
      <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
      <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
    </ol>
    <div class="carousel-inner">
      <?php $active = 'active'; ?>
      <?php foreach ($product->images as $image) : ?>
      <div class="carousel-item <?php echo $active ?>">
        <img src="<?php echo ROOT_URL . '/images/' . $product->id . '/' . $image->id . '.' . $image->image_extension ?>" class="d-block w-100" alt="...">
      </div>
      <?php $active = ''; ?>
      <?php endforeach ?>
    </div>
    <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="sr-only">Next</span>
    </a>
  </div>
  <hr class="my-4">
<?php endif ?>

  <p class="lead p-3">
    <?php if (!$isOutOfStock) : ?>
      <form method="post">
        <input data-id="<?php echo esc_html($product->id); ?>" name="add_to_cart" type="submit" class="btn btn-primary btn-lg" value="Aggiungi Libro da Vendere">
      </form>
    <?php else : ?>
      <button class="btn btn-secondary btn-lg" disabled>
        Non Disponibile
      </button>
    <?php endif; ?>
  </p>
</div>

<script>
    $(document).ready(function(){
      countdown($('.countdown'));
    });
</script>