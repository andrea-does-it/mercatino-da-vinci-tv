<?php
require_once '../inc/init.php';

global $loggedInUser;

if (!$loggedInUser) {
    header('Location: ' . ROOT_URL . 'auth?page=login');
    exit;
}

if ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser') {
    header('Location: ' . ROOT_URL . 'user?page=dashboard&msg=forbidden');
    exit;
}

// Whitelist of allowed pages to prevent LFI attacks
$allowedPages = [
    'calcolo_vendita', 'category', 'category-list', 'dashboard',
    'download-management', 'edit-news', 'email', 'emails-list',
    'generate-images-run', 'incasso_vendita', 'index', 'libri_da_vendere',
    'libri_per_pratica', 'libri_per_pratica_item', 'libri_venduti',
    'news-management', 'orders-list', 'orders-list_old', 'process-order',
    'process-order_old', 'process-order2', 'product', 'product_old',
    'products-list', 'profile', 'profiles-list', 'sales-transactions',
    'sales-transaction-new', 'sales-transaction-view', 'seller-refunds',
    'seller-refund-view', 'seller-refund-newsletter', 'seller-refund-report', 'shipment', 'shipment-list',
    'site_utils', 'special-treatment', 'special-treatments-list', 'upgrade', 'user', 'users-list'
];

$page = isset($_GET["page"]) ? $_GET["page"] : 'dashboard';

// Validate page against whitelist
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}
?>
<?php include ROOT_PATH . 'public/template-parts/header.php'; ?>
<div class="main-content container mt-5 newclass">
  <div class="row">

<!--<div class="col-lg-3 big-screen">
      <?php include ROOT_PATH . 'public/template-parts/sidebar.php'; ?>
    </div>-->

    <div class="col-lg">
      <div class="main">
        <!-- <?php if ($page != 'dashboard' AND $page != 'process-order') : ?>
          <a class="back underline" href="<?php echo ROOT_URL; ?>admin/?page=dashboard">&laquo; Torna al cruscotto</a>
          <br>
        <?php endif; ?> -->
        <?php include ROOT_PATH . 'inc/alert-message.php'; ?>
        <?php include "pages/$page.php"; ?>
      </div>
    </div>
  </div>

</div>
<?php include ROOT_PATH . 'public/template-parts/footer.php'; ?>