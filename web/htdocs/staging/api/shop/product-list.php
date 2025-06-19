<?php
require_once '../../inc/init.php'; 

if (! defined('ROOT_URL')) {
  die;
}

$productId = trim($_POST['id']);

// Validate the product before adding to cart
$pm = new ProductManager();
$product = $pm->get($productId);

if (!$product || $product->fl_esaurimento == 1) {
  $result = ['result' => 'error', 'message' => 'Libro non caricabile - prodotto non disponibile'];
  header('Content-type: application/json');
  echo json_encode($result);
  exit;
}

$cm = new CartManager();
$cartId = $cm->getCurrentCartId(); 
$success = $cm->addToCart($productId, $cartId);

if ($success) {
  $result = ['result' => 'success', 'message' => 'Aggiunto al carrello'];
} else {
  $result = ['result' => 'error', 'message' => 'Impossibile aggiungere il prodotto al carrello'];
}
 
header('Content-type: application/json');
echo json_encode($result);