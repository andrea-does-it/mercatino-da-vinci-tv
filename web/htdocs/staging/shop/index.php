<?php
require_once '../inc/init.php';

// Whitelist of allowed pages to prevent LFI attacks
$allowedPages = [
    'cart', 'checkout', 'index', 'my-orders', 'my-orders_old',
    'products-list', 'products-list_old', 'products-list-test',
    'view-order', 'view-order_old', 'view-product', 'view-product_old'
];

$page = 'products-list';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
}

// Validate page against whitelist
if (!in_array($page, $allowedPages, true)) {
    $page = 'products-list';
}
?>
<?php include ROOT_PATH . 'public/template-parts/header.php'; ?>
<div class="main-content container mt-5">
  <div class="row">
    <div class="col-lg-3 big-screen">
      <?php include ROOT_PATH . 'public/template-parts/sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="main">
        <?php include ROOT_PATH . 'inc/alert-message.php'; ?>
        <?php include "pages/$page.php"; ?>
      </div>
    </div>
  </div>

</div>
<?php include ROOT_PATH . 'public/template-parts/footer.php'; ?>