<?php
require_once '../../inc/init.php'; 

if (! defined('ROOT_URL')) {
  die;
}

$productId = trim($_POST['id']);
$cm = new CartManager();
$cartId = $cm->getCurrentCartId(); 
$cm->addToCart($productId, $cartId);

$result = ['result' => 'success', 'message' => 'Aggiunto al carrello'];
 
header('Content-type: application/json');
echo json_encode($result);