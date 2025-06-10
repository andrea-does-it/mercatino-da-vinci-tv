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
  
  if ($product->id == 0) {
    echo "<script>location.href='".ROOT_URL."shop/?page=products-list&msg=not_found';</script>";
    exit;
  }
?>
<a class="back underline" href="<?php echo ROOT_URL; ?>shop/?page=products-list">&laquo; Lista Libri Adottati</a>

<div class="jumbotron">
  <h1 class="display-5"><?php echo esc_html($product->name); ?></h1>
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

  <p class="lead <?php echo $lineThrough ?>"> Codice ISBN: <?php echo esc_html($product->ISBN); ?></p>
  <p class="lead <?php echo $lineThrough ?>"> Autori: <?php echo esc_html($product->autori); ?></p>
  <p class="lead <?php echo $lineThrough ?>"> Editore: <?php echo esc_html($product->editore); ?></p>
  <p class="lead p-3">
    <form method="post" action="<?php echo ROOT_URL; ?>shop/?page=products-list">
      <input type="hidden" name="id" value="<?php echo esc_html($product->id); ?>">
      <input data-id="<?php echo esc_html($product->id); ?>" name="add_to_cart" type="submit" class="btn btn-primary right" value="Aggiungi Libro da Vendere">
    </form>   
  </p>
</div>

<script>
    $(document).ready(function(){
      countdown($('.countdown'));
    });
</script>