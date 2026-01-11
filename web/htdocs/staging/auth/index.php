<?php
require_once '../inc/init.php';

global $loggedInUser;

// Whitelist of allowed pages to prevent LFI attacks
$allowedPages = [
    'forgot-password', 'index', 'login', 'logout',
    'register', 'reset-password', 'reset-password-request'
];

$page = isset($_GET["page"]) ? $_GET["page"] : 'login';

// Validate page against whitelist
if (!in_array($page, $allowedPages, true)) {
    $page = 'login';
}

if ($loggedInUser && $page != 'logout') {
    header('Location: ' . ROOT_URL);
    exit;
}
?>

<?php include 'template-parts/header.php'; ?>
<div class="container mt-5">
  <div class="row">
    <div class="col-md-6 ml-auto mr-auto login-box">
      <div class="main">
        <?php include ROOT_PATH . 'inc/alert-message.php'; ?>
        <a class="back underline" href="<?php echo ROOT_URL; ?>">&laquo; Torna alla Home</a>
        <br>
        <?php include "pages/$page.php"; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'template-parts/footer.php'; ?>