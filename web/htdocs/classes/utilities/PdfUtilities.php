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

}

