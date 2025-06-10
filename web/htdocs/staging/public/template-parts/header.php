<?php
// Prevent from direct access
if (! defined('ROOT_URL')) {
  die;
}

global $loggedInUser;
//var_dump($loggedInUser); die;
$urlUtilities = new UrlUtilities('public');

?>

<!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  
  <title><?php echo SITE_NAME; ?></title>

  <meta property="og:url" content="<?php echo ROOT_URL ?>shop/category12.html">
  <meta property="og:image" content="<?php echo ROOT_URL ?>images/logo.jpg">
	<meta property="og:description" content="Test descrizione">
	<meta property="og:title" content="PHP Ecommerce">

  <link rel="stylesheet" href="<?php echo ROOT_URL; ?>assets/css/bootstrap.css">
  <link rel="stylesheet" href="<?php echo ROOT_URL; ?>assets/css/style.css">
  <link rel="stylesheet" href="//use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
  
  <script src="<?php echo ROOT_URL; ?>assets/js/jquery.js"></script>
  <script src="<?php echo ROOT_URL; ?>assets/js/popper.js"></script>
  <script src="<?php echo ROOT_URL; ?>assets/js/bootstrap.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote-bs4.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.16/dist/summernote-bs4.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.4.0/bootbox.min.js"></script>

  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap4.min.css">

  <script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" ></script>
  <script src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js" ></script>
  <script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js" ></script>
  <script src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap4.min.js" ></script>

  <script>
  var rootUrl = '<?php echo ROOT_URL ?>';
  </script>

  <script src="<?php echo ROOT_URL; ?>assets/js/main.js"></script>

<!-- Facebook SDK -->

<!-- Facebook SDK -->
</head>

<body>
  <nav class="navbar navbar-expand-md navbar-dark bg-info">
    <div class="container">
    <a class="navbar-brand height=20%" href="<?php echo ROOT_URL; ?>index.php"><img src=<?php echo ROOT_URL . "images/logo_comitato.png" ?> title="Home"></a>

      <a class="cart-smartphone nav-link btn text-light" href="<?php echo ROOT_URL; ?>shop/?page=cart">
        <i class="fas fa-shopping-cart"></i>
        <span class="badge badge-primary badge-pill js-totCartItems"></span>
      </a>

      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarsExampleDefault">
        <ul class="navbar-nav mr-auto">
          <li class="nav-item">
            <a class="nav-link" href="<?php echo $urlUtilities->static('about'); ?>">Chi Siamo</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo $urlUtilities->static('services'); ?>">Dove siamo</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo $urlUtilities->static('contacts'); ?>">Contatti</a>
          </li>

          <!-- 20250404: Aggiunti menu News e Download-->
          <li class="nav-item">
            <a class="nav-link" href="<?php echo ROOT_URL; ?>public?page=news">News</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo ROOT_URL; ?>public?page=downloads">Downloads</a>
          </li>

        </ul>
       
        <ul class="navbar-nav ml-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Mercatino</a>
            <div class="dropdown-menu" aria-labelledby="dropdown01">
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>shop/?page=products-list">Lista Libri Adottati dal Liceo</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>public?page=libri_da_vendere">Libri disponibili per acquisto</a>
              </div>
          </li>
        </ul>

        <ul class="cart-desktop navbar-nav ml-auto">
          <li class="nav-item">
            <a class="nav-link" href="<?php echo ROOT_URL; ?>shop/?page=cart">
              <i class="fas fa-shopping-cart"></i>
              <span class="badge badge-primary badge-pill js-totCartItems"></span>
            </a>
          </li>
        </ul>

        <?php if ($loggedInUser && $loggedInUser->user_type == 'admin') : ?>

          <ul class="navbar-nav ml-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Amministrazione</a>
            <div class="dropdown-menu" aria-labelledby="dropdown01">
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=dashboard">Cruscotto</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=orders-list">Gestione Pratiche</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=libri_da_vendere">Libri da Vendere</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=calcolo_vendita">Riepilogo Vendita</a>
              <div class="dropdown-divider"></div>              
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=incasso_vendita">Incasso Vendita</a>
              <div class="dropdown-divider"></div>              
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=libri_venduti">Libri Venduti</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=libri_per_pratica">Libri per Pratica</a>
              <div class="dropdown-divider"></div>           
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=category-list">Gestione Materie</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=product">Aggiungi Libro</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=products-list">Lista Libri</a>
              <div class="dropdown-divider"></div>
            <!--  <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=shipment">Aggiungi Spedizione</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=shipment-list">Lista Spedizioni</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=special-treatment">Aggiungi Trattamento Speciale</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=special-treatments-list">Lista Trattamenti Speciali</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=profile">Aggiungi Profilo</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=profiles-list">Lista Profili</a>
              <div class="dropdown-divider"></div>-->
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=user">Aggiungi Utente</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=users-list">Lista Utenti</a>
              <div class="dropdown-divider"></div>
          <!--<a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=upgrade">Aggiornamento DB</a>
              <div class="dropdown-divider"></div>-->
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>auth?page=logout">Logout</a>
            </div>
          </li>
        </ul>
        <?php endif; ?>

        <?php if ($loggedInUser && $loggedInUser->user_type == 'pwuser') : ?>
        <ul class="navbar-nav ml-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Gestione</a>
            <div class="dropdown-menu" aria-labelledby="dropdown01">

              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=orders-list">Gestione Pratiche</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=libri_da_vendere">Libri da Vendere</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=calcolo_vendita">Riepilogo Vendita</a>
              <div class="dropdown-divider"></div>              
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=incasso_vendita">Incasso Vendita</a>
              <div class="dropdown-divider"></div>              
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=libri_venduti">Libri Venduti</a>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>admin/?page=libri_per_pratica">Libri per Pratica</a>
            </div>
          </li>
        </ul>
        <?php endif; ?>

        <?php if ($loggedInUser) : ?>
        <ul class="navbar-nav ml-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo $loggedInUser->email; ?></a>
            <div class="dropdown-menu" aria-labelledby="dropdown01">
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>user?page=dashboard">Cruscotto</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>user?page=profile">Profilo</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>shop/?page=my-orders">Le mie Pratiche</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>user?page=libri_da_vendere">Libri Disponibili per Acquisto</a>
              <div class="dropdown-divider"></div>              
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>auth?page=logout">Logout</a>
            </div>
          </li>
        </ul>
        <?php endif; ?>

        <?php if (!$loggedInUser) : ?>
        <ul class="navbar-nav ml-auto">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Area Riservata</a>
            <div class="dropdown-menu" aria-labelledby="dropdown01">
              <a class="dropdown-item" href="<?php echo ROOT_URL; ?>auth/index.php?page=login">Login / Registrazione</a>
            </div>
          </li>
        </ul>
        <?php endif; ?>

        <div id="fb-root"></div>
        <!-- Your share button code -->
        <div class="fb-share-button" data-href="<?php echo ROOT_URL ?>shop/category12.html" data-layout="button_count"></div> 

        <form class="form-inline mt-2 mt-md-0" autocomplete="off">
          <input id="search" class="form-control mr-sm-2" type="text" placeholder="Cerca" aria-label="Search">
          <div class="live-search">
            <div class="overlay"></div>
            <div id="suggestions">
              <div class="my-3 p-3 bg-white rounded shadow-sm results">
                <h6 class="border-bottom border-gray pb-2 mb-0">Risultati della ricerca...</h6>            
              </div>  
            </div>
          </div>
        </form>
    
      </div>
    </div>
  </nav>