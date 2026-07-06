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

    // Explicit, comfortable margins so headings never clip on long site names.
    $this->SetMargins(15, 12, 15);
    $this->AddPage('P');
    // Usable content width (page width minus left/right margins). The items table
    // column widths below (20 + 120 + 40) sum to this value.
    $usable = $this->w - $this->lMargin - $this->rMargin;

    // Heading on two lines; the site name wraps (MultiCell) instead of overflowing.
    $this->SetFont('Arial', 'B', 14);
    $this->MultiCell($usable, 8, $conv(SITE_NAME), 0, 'C');
    $this->SetFont('Arial', 'B', 13);
    $this->Cell($usable, 9, $conv('Ricevuta vendita N. ' . $transaction->id), 0, 1, 'C');

    if (!empty($transaction->refunded_at)) {
      $this->SetFont('Arial', 'B', 12);
      $this->Cell($usable, 8, $conv('VENDITA RIMBORSATA'), 0, 1, 'C');
    }
    $this->Ln(3);

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

    // Items — the title is rendered with MultiCell so long titles wrap inside the
    // 120mm column instead of overflowing into the Prezzo column (FPDF Cell does not
    // wrap). Pratica and Prezzo are drawn at the row's full computed height so the
    // three columns stay aligned.
    $this->SetFont('Arial', '', 11);
    $lineH = 7;
    foreach ($items as $row) {
      $title   = $conv($row->product_name);
      $nbLines = max(1, $this->NbLines(120, $title));
      $rowH    = $nbLines * $lineH;

      // If the row would cross the page bottom, start a new page and re-print the header.
      if ($this->GetY() + $rowH > $this->PageBreakTrigger) {
        $this->AddPage($this->CurOrientation);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(20, 8, $conv('Pratica'), 1, 0, 'C');
        $this->Cell(120, 8, $conv('Titolo'), 1, 0, 'L');
        $this->Cell(40, 8, $conv('Prezzo'), 1, 1, 'C');
        $this->SetFont('Arial', '', 11);
      }

      $x = $this->GetX();
      $y = $this->GetY();

      $this->Cell(20, $rowH, $conv($row->pratica), 1, 0, 'C');
      $this->MultiCell(120, $lineH, $title, 1, 'L');
      $this->SetXY($x + 140, $y);
      $this->Cell(40, $rowH, $eur . number_format((float)$row->price, 2, ',', '.'), 1, 0, 'R');
      $this->SetXY($x, $y + $rowH);
    }

    // Total
    $this->SetFont('Arial', 'B', 12);
    $this->Cell(140, 9, $conv('Totale incassato'), 1, 0, 'R');
    $this->Cell(40, 9, $eur . number_format((float)$transaction->total_amount, 2, ',', '.'), 1, 1, 'R');

    $this->Output();
  }

  /**
   * Number of lines a string takes inside a MultiCell of the given width,
   * replicating FPDF's word-wrap logic. Used to compute row heights so the
   * other columns in the row can be drawn at the same height.
   */
  protected function NbLines($w, $txt) {
    $cw = &$this->CurrentFont['cw'];
    if ($w == 0) {
      $w = $this->w - $this->rMargin - $this->x;
    }
    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
    $s = str_replace("\r", '', (string)$txt);
    $nb = strlen($s);
    if ($nb > 0 && $s[$nb - 1] == "\n") {
      $nb--;
    }
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $nl = 1;
    while ($i < $nb) {
      $c = $s[$i];
      if ($c == "\n") {
        $i++;
        $sep = -1;
        $j = $i;
        $l = 0;
        $nl++;
        continue;
      }
      if ($c == ' ') {
        $sep = $i;
      }
      $l += $cw[$c];
      if ($l > $wmax) {
        if ($sep == -1) {
          if ($i == $j) {
            $i++;
          }
        } else {
          $i = $sep + 1;
        }
        $sep = -1;
        $j = $i;
        $l = 0;
        $nl++;
      } else {
        $i++;
      }
    }
    return $nl;
  }

}

