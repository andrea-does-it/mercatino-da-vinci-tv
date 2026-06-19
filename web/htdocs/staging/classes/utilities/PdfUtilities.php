<?php

use Fpdf\Fpdf;

class PdfUtilities extends Fpdf {

  public function printOrderInvoice($orderId, $orderItems, $orderTotal, $first_name, $email, $last_name, $pratica1) {

    $data = $orderItems;

    // Column headings
    $header = array('ID', 'Titolo', iconv('UTF-8', 'windows-1252', 'ISBN' ), 'Prezzo');
    $w = array(0, 205, 45, 25);
    // Data loading

    $this->SetFont('Arial','B',20);

    $this->AddPage('L');
    $this->Cell(275,20, SITE_NAME . ' - Mercatino - Pratica N.' . $pratica1, '', 0, 'C');
    $this->Ln();

    $this->setFont('Arial', 'B', 12);
    for($i=1; $i<count($header); $i++)  {
      $this->Cell($w[$i], 10, $header[$i], 1, 0, 'C');
    }
    $this->Ln();
    // Data
    $this->setFont('Arial', '', 12);
    foreach($data as $row)
    {
      $eur = iconv('UTF-8', 'windows-1252', '€ ' );
      $row['product_name'] = iconv('UTF-8', 'windows-1252', $row['product_name']);
      $row['product_ISBN'] = iconv('UTF-8', 'windows-1252', $row['product_ISBN']);

      $this->Cell($w[1],10 ,utf8_encode($row['product_name']),'LR', 0, 'L');
      $this->Cell($w[2],10 ,utf8_encode($row['product_ISBN']),'LR', 0, 'C');
      $this->Cell($w[3],10 , $eur . number_format($row['single_price'], 2, ',', '.'),'LR',0,'C');

      $this->Ln();
    }
    // Closing line
    $this->Cell(array_sum($w),0,'','T');
    $this->Ln();



    $this->SetFont('Arial','B',18);

    // Cliente
    $this->Ln();
    $this->Cell(160,20,'Dettagli Venditore:', '', 0, 'C');
    $this->Ln();


    $this->SetFont('Arial','B',12);
    $this->Cell(70, 10 , 'Nominativo:',1,0,'R');
    $this->SetFont('Arial','',12);
    $nominativo = esc_html($last_name) . '  ' . esc_html($first_name);
    $this->Cell(110, 10 , iconv('UTF-8', 'windows-1252', $nominativo) ,1,0,'C');
    $this->Ln();

    $this->SetFont('Arial','B',12);
    $this->Cell(70, 10 , 'Email:',1,0,'R');
    $this->SetFont('Arial','',12);
    $this->Cell(110, 10 , iconv('UTF-8', 'windows-1252', $email) ,1,0,'C');
    $this->Ln();


    $this->Cell(180 ,0,'','T');
    $this->Ln();

    $this->Output();
  }

  public function printSalesTransactionReceipt($transaction, $items, $operatorName = '') {
    $conv = function ($s) { return iconv('UTF-8', 'windows-1252', (string)$s); };
    $eur  = $conv('€ ');

    $this->AddPage('P');

    // Heading
    $this->SetFont('Arial', 'B', 18);
    $this->Cell(0, 12, $conv(SITE_NAME . ' - Ricevuta vendita N. ' . $transaction->id), 0, 1, 'C');

    if (!empty($transaction->refunded_at)) {
      $this->SetFont('Arial', 'B', 12);
      $this->Cell(0, 8, $conv('VENDITA RIMBORSATA'), 0, 1, 'C');
    }
    $this->Ln(2);

    // Meta line
    $this->SetFont('Arial', '', 11);
    $date = $transaction->created_at ? date('d/m/Y H:i', strtotime($transaction->created_at)) : '';
    $this->Cell(0, 7, $conv('Data: ' . $date), 0, 1, 'L');
    $this->Cell(0, 7, $conv('Pagamento: ' . $transaction->payment_method), 0, 1, 'L');
    if ($operatorName !== '') {
      $this->Cell(0, 7, $conv('Operatore: ' . $operatorName), 0, 1, 'L');
    }
    if (!empty($transaction->description)) {
      $this->Cell(0, 7, $conv('Cliente/Note: ' . $transaction->description), 0, 1, 'L');
    }
    $this->Ln(2);

    // Items table header
    $this->SetFont('Arial', 'B', 11);
    $this->Cell(20, 8, $conv('Pratica'), 1, 0, 'C');
    $this->Cell(120, 8, $conv('Titolo'), 1, 0, 'L');
    $this->Cell(40, 8, $conv('Prezzo'), 1, 1, 'C');

    // Items
    $this->SetFont('Arial', '', 11);
    foreach ($items as $row) {
      $this->Cell(20, 8, $conv($row->pratica), 1, 0, 'C');
      $this->Cell(120, 8, $conv($row->product_name), 1, 0, 'L');
      $this->Cell(40, 8, $eur . number_format((float)$row->price, 2, ',', '.'), 1, 1, 'R');
    }

    // Total
    $this->SetFont('Arial', 'B', 12);
    $this->Cell(140, 9, $conv('Totale incassato'), 1, 0, 'R');
    $this->Cell(40, 9, $eur . number_format((float)$transaction->total_amount, 2, ',', '.'), 1, 1, 'R');

    $this->Output();
  }

}

