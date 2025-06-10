<?php

if (! defined('ROOT_URL')) {
  include_once '../../inc/init.php';
}

// Prevent from direct access
if (! defined('ROOT_URL')) {
  die;
}

global $loggedInUser;
if (!$loggedInUser) {
  echo "<script>location.href='".ROOT_URL."auth?page=login&msg=login_for_checkout';</script>";
  exit;
}

$cartMgr = new CartManager();
$orderMgr = new OrderManager();

// Profilo pagamento Ritardato
if (isset($_POST['pay'])) {

 // $pm = new ProfileManager();
 // $delayedPayments = $pm->GetUserDelayedPayments();

 // if (count($delayedPayments) == 0) {
 //   echo "<script>location.href='".ROOT_URL."public';</script>";
 //   exit;
 // }

 // $hasPayment = false;
 // $paymentMethodId = esc($_POST['paymentMethod']); 
 // foreach($delayedPayments as $p){
 //   if ($p->id == $paymentMethodId) {
 //     $hasPayment = true;
 //     break;
 //  }
 // }

 // if (!$hasPayment) {
 //   echo "<script>location.href='".ROOT_URL."public';</script>";
 //   exit;
 // }

  // Qui sono sicuro che l'utente ha la facoltà del pagamento ritardato scelto, quindi creo l'ordine
  
  $cartId = $cartMgr->getCurrentCartId();
  if ($cartMgr->isEmptyCart($cartId)){
    die('cart is empty');
  }

  $orderId = $orderMgr->createOrderFromCart($cartId, $loggedInUser->id);
  $paymentCode = NULL;
  $paymentStatus = NULL;
  $status = 'inviata';
  $paymentMethodId = isset($_POST['paymentMethod']) ? esc($_POST['paymentMethod']) : '';
  $paymentMethod = $paymentMethodId;
  $orderMgr->SavePaymentDetails($orderId, $paymentCode, $paymentStatus, $paymentMethod, $status);

  echo "<script>location.href='".ROOT_URL."shop/?page=checkout&orderId=".$orderId."&success=true';</script>";
  exit;
}

global $alertMsg;
$error = $_GET['success'] != "true";

$orderId =  (int) $_GET['orderId'];
$order = $orderMgr->get($orderId);
if (!$order || $loggedInUser->id != $order->user_id) {
  echo "<script>location.href='".ROOT_URL."public';</script>";
  exit;
}

if ($order->is_email_sent){
  echo '<h1>Richiesta di Vendita già elaborata.</h1>';
  echo '<p>Puoi visualizzare i dettagli oppure tornare alla home...</p>';
  echo '<a class="back underline" href="'.ROOT_URL.'shop/?page=view-order&id='.$orderId .'">Visualizza &raquo;</a><br>';
  echo '<a class="back underline" href="'.ROOT_URL.'">&laquo; Torna alla Home</a>';
  exit;
}

$address = $orderMgr->getUserAddress($loggedInUser->id);
$orderItems = $orderMgr->getOrderItems($orderId);
$orderTotal = $orderMgr->getOrderTotal($orderId)[0];

$br = "\r\n";
$to = $loggedInUser->email;
$subject = "Richiesta N. " . $orderId;
$message = "" . $br ;

$headers = "From: Mercatino Comitato Genitori <mercatino@comitatogenitoridavtv.it>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

$style = "style='border: 1px solid black; border-collapse: collapse;'";

if ($error == false) {
  $message = "<h2>Grazie per la richiesta di vendita dei suoi libri</h2>" . $br ;
} else {
  $message = "<h2>Si è verificato un errore durante la procedura di invio.</h2>" . $br ;
  $message .= "<p>La richiesta è stata annullata.</p>" . $br ;
}

$br = "<br>";
$message.= $br . "<h3>Riepilogo Richiesta:</h3>";

$mailBody = "<table $style><tr><th $style>Prodotto</th><th $style >Prezzo Unitario</th><th $style >N. Pezzi</th><th $style >Importo</th></tr>";
foreach($orderItems as $item)
{
  $mailBody .= "<tr><td $style>".$item['product_name']."</td><td $style>".$item['single_price']."</td><td $style>".$item['quantity']."</td><td $style>".$item['total_price']."</td></tr>";
}
//$mailBody .= "<tr><td $style colspan='4'>Spedizione: ". $orderTotal['shipment_name'] ." (". $orderTotal['shipment_price'] . " €)</td></tr>";
$mailBody .= "<tr><td $style colspan='4'>Totale €". (number_format((float)  ($orderTotal['total'] + $orderTotal['shipment_price']), 2, '.', '')) . "</td></tr>";
$mailBody .= "</table>";

$message .= $mailBody . $br ;

$parameters = "-f mercatino@comitatogenitoridavtv.it";

if ($error == false) {
  $message.= $br . "<h3>Indirizzo di consegna libri:</h3>";

  //$txt.= $br . "<h3>Indirizzo di spedizione:</h3>";

  //$shippingAddressStr = "<strong>Indirizzo: </strong>" . $address['street'] . $br;
  //$shippingAddressStr .= "<strong>Città: </strong>" . $address['city'] . $br;
  //$shippingAddressStr .= "<strong>CAP: </strong>" . $address['cap'] . $br;
  $shippingAddressStr = "<strong>Indirizzo: Via de Coubertin, 4 </strong>" . $br;
  $shippingAddressStr .= "<strong>Città: Treviso </strong>" . $br;
  $message .= $shippingAddressStr . $br;
  $message .= $br . "Segui le info sul nostro sito per le date in cui poter consegnare i libri da mettere in vendita.";
}

mail($to,$subject,$message,$headers,$parameters);
$order->is_email_sent = 1;
$orderMgr->update($order, $orderId);

$style="";
$htmlBody = "<table class='table table-bordered' $style><tr><th $style>Titolo</th><th $style >Prezzo Unitario</th><th $style >N. Pezzi</th><th $style >Importo</th></tr>";
foreach($orderItems as $item)
{
  $htmlBody .= "<tr><td $style>".$item['product_name']."</td><td $style>€ ".$item['single_price']."</td><td $style>".$item['quantity']."</td><td $style>€ ".$item['total_price']."</td></tr>";
}
//$htmlBody .= "<tr><td $style colspan='4'>Spedizione: ". $orderTotal['shipment_name'] ." (". $orderTotal['shipment_price'] . " €)</td></tr>";
$htmlBody .= "<tr><td $style colspan='4'>Totale €". (number_format((float)  ($orderTotal['total'] + $orderTotal['shipment_price']), 2, '.', ''))  . "</td></tr>";
$htmlBody .= "</table>";



?>

<?php if ($error == false) : ?>
<h1>Grazie per aver inviato dei libri da vendere</h1>
<p class="lead">Di seguito un riepilogo. Riceverà una mail con i dettagli della richiesta</p>
<a class="back underline" href="<?php echo ROOT_URL . "shop/?page=view-order&id=$orderId" ?>">Visualizza Richiesta &raquo;</a><br>

<br>
<?php  else : ?>
<h1>Si è verificato un errore durante la procedura di invio.</h1>
<p class="lead">Riceverà una mail con dettagli.</p>
<br>
<?php endif ?>

<?php echo $htmlBody; ?>

<a class="back underline" href="<?php echo ROOT_URL; ?>">&laquo; Torna alla Home</a>