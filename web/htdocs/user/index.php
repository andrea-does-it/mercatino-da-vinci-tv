<?php
require_once '../inc/init.php';

global $loggedInUser;

if (!$loggedInUser) {
    header('Location: ' . ROOT_URL . 'auth?page=login');
    exit;
}

// Whitelist of allowed pages to prevent LFI attacks
$allowedPages = ['dashboard', 'index', 'libri_da_vendere', 'profile', 'privacy'];

$page = 'profile';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
}

// Validate page against whitelist
if (!in_array($page, $allowedPages, true)) {
    $page = 'profile';
}
?>
<?php include ROOT_PATH . 'public/template-parts/header.php'; ?>
<div class="container mt-5">
  <div class="row">
    <div class="col-md-3 big-screen">
      <?php include ROOT_PATH . 'public/template-parts/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
      <div class="main">
      <?php include "pages/$page.php"; ?>
      <?php include ROOT_PATH . 'inc/alert-message.php'; ?>
      </div>
    </div>
  </div>

</div>
<?php include ROOT_PATH . 'public/template-parts/footer.php'; ?>