<?php

require_once '../../inc/init.php'; 

global $loggedInUser;

if (!$loggedInUser) {
  echo 'Forbidden';
  exit;
}

$pdf = new PdfUtilities();

$orderId = esc($_GET['orderId']);
$orderMgr = new OrderManager();

$order = $orderMgr->get($orderId);
if (!isset($order->id) || ( $loggedInUser->user_type != 'admin' && $order->user_id != $loggedInUser->id)) {
  echo 'Forbidden';
  exit;
}

$orderItems = $orderMgr->getOrderItems($orderId);
$orderTotal = $orderMgr->getOrderTotal($orderId)[0];
$address = $orderMgr->getUserAddress($orderTotal['user_id']);
$email = $orderMgr->getEmailAndName($orderId)['email'];
$first_name = $orderMgr->getEmailAndName($orderId)['first_name'];
$last_name = $orderMgr->getEmailAndName($orderId)['last_name'];
$status = $orderItems[0]['order_status'];
$pratica1 = $orderItems[0]['pratica'];

$data = $orderItems;
$pdf->printOrderInvoice($orderId, $orderItems, $orderTotal, $first_name, $email, $last_name, $pratica1);