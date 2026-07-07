<?php

// Invio (o anteprima) di una singola email mail-merge a un ordine.
// Chiamato in sequenza, un ordine per richiesta, dal tab "Email Ordini" di site_utils.

require_once '../../inc/init.php';

if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
  header('Content-Type: application/json');
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Accesso negato']);
  exit;
}

CSRF::validateAjaxOrDie();

header('Content-Type: application/json');

$orderId    = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$subject    = trim(isset($_POST['subject']) ? $_POST['subject'] : '');
$body       = isset($_POST['body']) ? $_POST['body'] : '';
$templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
$isPreview  = !empty($_POST['preview']);

if ($orderId <= 0 || $subject === '' || trim($body) === '') {
  echo json_encode(['ok' => false, 'error' => 'Parametri mancanti: ordine, oggetto e corpo sono obbligatori.']);
  exit;
}

$orderEmailMgr = new OrderEmailManager();
$order = $orderEmailMgr->getOrderForEmail($orderId);

if (!$order) {
  echo json_encode(['ok' => false, 'error' => "Ordine $orderId non trovato."]);
  exit;
}

if (!filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok' => false, 'error' => 'Email del venditore mancante o non valida.']);
  exit;
}

$mergedSubject = $orderEmailMgr->mergeText($subject, $order);
$mergedBody    = $orderEmailMgr->mergeText($body, $order);

if ($isPreview) {
  echo json_encode([
    'ok'      => true,
    'to'      => $order->email,
    'subject' => $mergedSubject,
    'body'    => $mergedBody,
  ]);
  exit;
}

$htmlBody  = $orderEmailMgr->buildHtmlBody($mergedBody);
$smtpError = '';
$sent = send_mail($order->email, $mergedSubject, $htmlBody, $smtpError);

if ($sent) {
  $orderEmailMgr->logSend($orderId, $templateId, $order->email, $mergedSubject, $loggedInUser->id);
  echo json_encode(['ok' => true]);
} else {
  echo json_encode(['ok' => false, 'error' => $smtpError !== '' && $smtpError !== null ? $smtpError : 'Invio fallito (errore SMTP).']);
}
