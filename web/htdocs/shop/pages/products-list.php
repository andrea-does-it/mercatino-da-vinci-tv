<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  //20250404: modifiche per paginazione

  $cm = new CartManager();
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $cm->ResetExpiredCarts();
  }

  if (isset($_POST['add_to_cart'])) {
    $productId = trim($_POST['id']);

    if (!is_numeric($productId)) {
      die('productId must be numeric...'); // prevent sql injection
    }

    // Check if product is marked as out of stock before adding to cart
    $pm = new ProductManager();
    $product = $pm->get($productId);
    
    if ($product->fl_esaurimento == 1) {
      $alertMsg = 'product_unavailable';
      echo "<script>location.href='".ROOT_URL."shop/?page=products-list&msg=$alertMsg';</script>";
      exit;
    }

    $cartId = $cm->getCurrentCartId();
    $cm->addToCart($productId, $cartId);

    $alertMsg = 'add_to_cart';
    echo "<script>location.href='".ROOT_URL."shop/?page=products-list&msg=$alertMsg';</script>";
    exit;
  }

  $categoryId = isset($_GET['id']) ? trim($_GET['id']) : 0;
  if (!is_numeric($categoryId)) {
    die('categoryId must be numeric...'); // prevent sql injection
  }
  
  // Pagination parameters
  $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
  $itemsPerPage = 12; // Can be adjusted based on preference
  $offset = ($page - 1) * $itemsPerPage;
  
  $pm = new ProductManager();
  
  // Get total number of products for pagination
  $totalProducts = $pm->GetProductsCount($categoryId);
  $totalPages = ceil($totalProducts / $itemsPerPage);
  
  // Get only products for current page
  $products = $pm->GetProductsPaginated($categoryId, $offset, $itemsPerPage);
?>

<h1>Lista Libri Adottati</h1>

<?php if ($totalProducts > 0) : ?>
<p class="lead">Di seguito la lista dei libri adottati dal Liceo Scientifico Da Vinci - Treviso</p>
<p class="lead">Vecchie Edizioni accettate dai Dipartimenti <a class="underline" href="<?php echo ROOT_URL . 'public?page=libri_vecchi_accettati'; ?>">Clicca qui &raquo;</a></p>
<p class="lead text-danger font-weight-bold">ATTENZIONE:<br>  i prezzi indicati in questa pagina sono calcolati applicando il 50% del prezzo di copertina ATTUALE decurtati di 1€ (<a class="underline">prezzo che incasserà il VENDITORE)</a>.</p>
<p class="lead text-danger font-weight-bold">Nella pagina al link:  <a class="lead text-info font-weight-bold" href="<?php echo ROOT_URL . 'public?page=libri_da_vendere'; ?>"> Mercatino -> Libri disponibili per acquisto</a>, si trovano i libri messi in vendita e ATTUALMENTE presenti presso i locali della scuola; i prezzi mostrati sono stati calcolati applicando il 50% del prezzo di copertina ATTUALE aumentati di 1€ (<a class="underline">prezzo che pagherà l' ACQUIRENTE)</a>.</p>
<p class="lead text-info font-weight-bold">Puoi filtrare i libri disponibili selezionando la materia di riferimento nell'elenco a sinistra.</p>

<div class="row" id="products-container">
  <?php foreach($products as $product) : ?>
    <?php 
    $proimg = $pm->GetProductWithImages($product->id); 
    $isOutOfStock = ($product->fl_esaurimento == 1);
    $cardClass = $isOutOfStock ? 'border-warning' : '';
    ?>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
      <div class="card h-100 product-card <?php echo $cardClass; ?>">
        <div class="card-header d-flex justify-content-between">
          <span class="badge badge-pill badge-info"><?php echo $product->ISBN; ?></span>
          <span class="badge badge-pill badge-danger"><?php echo esc_html($product->price); ?> €</span>
        </div>
        
        <div class="card-body">
          <h5 class="card-title"><?php echo esc_html($product->name); ?></h5>
          
          <?php if ($proimg->images) : ?>
            <div id="carousel-<?php echo $product->id ?>" class="carousel slide" data-ride="carousel">
              <div class="carousel-inner">
                <?php foreach($proimg->images as $index => $image) : ?>  
                  <?php
                  $active = $index == 0 ? 'active' : '';
                  ?>
                  <div class="carousel-item <?php echo $active ?>">
                    <img src="<?php echo ROOT_URL ."images/" . $proimg->id . '/' . $image->id . '_thumbnail.' . $image->image_extension ?>" class="d-block w-70 mx-auto">
                  </div>
                <?php endforeach; ?>
              </div>
              <a class="carousel-control-prev" href="#carousel-<?php echo $product->id ?>" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
              </a>
              <a class="carousel-control-next" href="#carousel-<?php echo $product->id ?>" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
              </a>
            </div>
          <?php else : ?>
            <img src="<?php echo ROOT_URL ?>images/noimage.jpg" class="img-fluid thumbnail" />
          <?php endif; ?>
          
          <p class="card-text mt-2">
            <strong>Autori:</strong> <?php echo esc_html($product->autori); ?><br>
            <strong>Editore:</strong> <?php echo esc_html($product->editore); ?>
            <?php if (!empty($product->nota_volumi)) : ?>
              <br><strong>Volumi:</strong> <?php echo esc_html($product->nota_volumi); ?>
            <?php endif; ?>
          </p>

          <?php if ($isOutOfStock) : ?>
            <div class="alert alert-warning bg-warning text-dark p-2 mt-2 mb-2">
              <strong>Libro non caricabile</strong>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="card-footer">
          <div class="btn-group d-flex" role="group">
            <button class="btn btn-secondary btn-sm rounded-0" onclick="location.href='<?php echo $product->url; ?>'">Vedi</button>
            <?php if (!$isOutOfStock) : ?>
              <form method="post" class="flex-grow-1">
                <input type="hidden" name="id" value="<?php echo esc_html($product->id); ?>">
                <input data-id="<?php echo esc_html($product->id); ?>" name="add_to_cart" type="submit" class="btn btn-primary btn-sm btn-block rounded-0" value="Aggiungi Libro da Vendere">
              </form>
            <?php else : ?>
              <button class="btn btn-secondary btn-sm btn-block rounded-0 flex-grow-1" disabled>
                Non Disponibile
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Pagination Controls -->
<div class="pagination-container d-flex justify-content-center mt-4">
  <nav aria-label="Navigazione pagine">
    <ul class="pagination">
      <?php if ($page > 1) : ?>
        <li class="page-item">
          <a class="page-link" href="<?php echo ROOT_URL . 'shop/?page=products-list&id=' . $categoryId . '&paged=1'; ?>">&laquo;</a>
        </li>
        <li class="page-item">
          <a class="page-link" href="<?php echo ROOT_URL . 'shop/?page=products-list&id=' . $categoryId . '&paged=' . ($page - 1); ?>">&lsaquo;</a>
        </li>
      <?php else : ?>
        <li class="page-item disabled">
          <a class="page-link" href="#">&laquo;</a>
        </li>
        <li class="page-item disabled">
          <a class="page-link" href="#">&lsaquo;</a>
        </li>
      <?php endif; ?>
      
      <?php
      // Calculate range of page numbers to display
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      
      // Always show at least 5 pages if available
      if ($endPage - $startPage + 1 < 5) {
        if ($startPage == 1) {
          $endPage = min($totalPages, $startPage + 4);
        } else {
          $startPage = max(1, $endPage - 4);
        }
      }
      
      for ($i = $startPage; $i <= $endPage; $i++) : ?>
        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
          <a class="page-link" href="<?php echo ROOT_URL . 'shop/?page=products-list&id=' . $categoryId . '&paged=' . $i; ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>
      
      <?php if ($page < $totalPages) : ?>
        <li class="page-item">
          <a class="page-link" href="<?php echo ROOT_URL . 'shop/?page=products-list&id=' . $categoryId . '&paged=' . ($page + 1); ?>">&rsaquo;</a>
        </li>
        <li class="page-item">
          <a class="page-link" href="<?php echo ROOT_URL . 'shop/?page=products-list&id=' . $categoryId . '&paged=' . $totalPages; ?>">&raquo;</a>
        </li>
      <?php else : ?>
        <li class="page-item disabled">
          <a class="page-link" href="#">&rsaquo;</a>
        </li>
        <li class="page-item disabled">
          <a class="page-link" href="#">&raquo;</a>
        </li>
      <?php endif; ?>
    </ul>
  </nav>
</div>

<!-- Search functionality -->
<div class="search-container mt-4 mb-4">
  <div class="input-group">
    <input type="text" id="product-search" class="form-control" placeholder="Cerca un libro...">
    <div class="input-group-append">
      <button class="btn btn-primary" id="search-button">Cerca</button>
    </div>
  </div>
</div>

<?php else : ?>
  <p>Nessun libro disponibile...</p>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Handle add to cart functionality
  const addToCartButtons = document.querySelectorAll('input[name="add_to_cart"]');
  addToCartButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      // Update cart count in navbar (the quantity is handled server-side now)
      const cartBadge = document.querySelector('.js-totCartItems');
      if (cartBadge) {
        cartBadge.textContent = (parseInt(cartBadge.textContent) || 0) + 1;
      }
    });
  });
  
  // Search functionality
  const searchInput = document.getElementById('product-search');
  const searchButton = document.getElementById('search-button');
  
  if (searchButton && searchInput) {
    searchButton.addEventListener('click', function() {
      const searchTerm = searchInput.value.toLowerCase();
      const cards = document.querySelectorAll('.product-card');
      
      cards.forEach(card => {
        const title = card.querySelector('.card-title').textContent.toLowerCase();
        const isbn = card.querySelector('.badge-info').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || isbn.includes(searchTerm)) {
          card.closest('.col-lg-4').style.display = '';
        } else {
          card.closest('.col-lg-4').style.display = 'none';
        }
      });
    });
    
    searchInput.addEventListener('keyup', function(e) {
      if (e.key === 'Enter') {
        searchButton.click();
      }
    });
  }
});
</script>