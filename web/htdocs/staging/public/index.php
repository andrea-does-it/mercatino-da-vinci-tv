<?php
require_once '../inc/init.php';

// Whitelist of allowed pages to prevent LFI attacks
$allowedPages = [
    'homepage', 'about', 'about_old', 'contacts', 'contacts_old',
    'downloads', 'homepage_old', 'homepage2', 'libri_da_vendere',
    'libri_vecchi_accettati', 'news', 'services', 'services_old'
];

$page = isset($_GET["page"]) ? $_GET["page"] : 'homepage';

// Validate page against whitelist
if (!in_array($page, $allowedPages, true)) {
    $page = 'homepage';
}
?>
<?php include ROOT_PATH . 'public/template-parts/header.php'; ?>
<div class="main-content container mt-5">
  <div class="row">
    <!--<div class="col-lg-3 big-screen">
      <?php include ROOT_PATH . 'public/template-parts/sidebar.php'; ?>
    </div>-->
    <div class="col-lg">
      <div class="main">
      <?php include "pages/$page.php"; ?>
      </div>
    </div>
  </div>

</div>
<?php include ROOT_PATH . 'public/template-parts/footer.php'; ?>