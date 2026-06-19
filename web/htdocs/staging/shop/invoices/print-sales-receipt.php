<?php

require_once '../../inc/init.php';

global $loggedInUser;

if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
  echo 'Forbidden';
  exit;
}

$transactionId = (int)esc($_GET['id']);
$salesMgr = new SalesTransactionManager();
$transaction = $salesMgr->getTransactionWithItems($transactionId);

if (!$transaction) {
  echo 'Not found';
  exit;
}

$operatorName = $salesMgr->getOperatorName($transaction->operator_id);

$pdf = new PdfUtilities();
$pdf->printSalesTransactionReceipt($transaction, $transaction->items, $operatorName);
