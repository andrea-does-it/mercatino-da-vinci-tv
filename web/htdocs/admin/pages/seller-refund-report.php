<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;

  $sellerRefundMgr = new SellerRefundManager();

  // Default to current year
  $currentYear = (int)date('Y');
  $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

  // Get available years
  $availableYears = $sellerRefundMgr->getAvailableYears();
  if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    rsort($availableYears);
  }

  // Get report data
  $reportData = $sellerRefundMgr->getReportData($selectedYear);
?>

<!-- Print styles for landscape layouts -->
<style id="printStyles">
  @media print {
    @page {
      size: A4 landscape;
      margin: 10mm;
    }

    /* Hide non-printable elements */
    .no-print,
    .btn,
    .card-header,
    form,
    nav,
    .navbar,
    .sidebar,
    footer {
      display: none !important;
    }

    /* Reset container width for print */
    .container,
    .container-fluid,
    .main-content {
      max-width: 100% !important;
      width: 100% !important;
      padding: 0 !important;
      margin: 0 !important;
    }

    /* Table styling for print - A4 default */
    #reportTable {
      font-size: 8pt !important;
      width: 100% !important;
    }

    #reportTable th,
    #reportTable td {
      padding: 3px 4px !important;
      border: 1px solid #000 !important;
    }

    #reportTable thead {
      background-color: #f0f0f0 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    /* Keep rows together */
    #reportTable tr {
      page-break-inside: avoid;
    }

    /* Print title */
    .print-title {
      display: block !important;
      font-size: 12pt;
      font-weight: bold;
      margin-bottom: 8px;
      text-align: center;
    }

    /* Badge colors for print */
    .badge-success {
      background-color: #28a745 !important;
      color: white !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .badge-primary {
      background-color: #007bff !important;
      color: white !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .badge-warning {
      background-color: #ffc107 !important;
      color: black !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .text-success { color: #28a745 !important; }
    .text-danger { color: #dc3545 !important; }
  }

  /* Screen styles for larger table */
  #reportTable {
    font-size: 0.85rem;
  }

  #reportTable th {
    white-space: nowrap;
    vertical-align: middle;
  }

  #reportTable td {
    vertical-align: top;
  }

  .print-title {
    display: none;
  }

  /* Layout size buttons */
  .layout-btn {
    min-width: 120px;
  }
  .layout-btn.active {
    box-shadow: 0 0 0 3px rgba(0,123,255,0.5);
  }
</style>

<h1 class="no-print">Report Rimborsi Venditori - <?php echo $selectedYear; ?></h1>
<div class="print-title">Report Rimborsi Venditori - <?php echo $selectedYear; ?></div>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mb-3 no-print">
  <i class="fas fa-arrow-left"></i> Torna alla gestione rimborsi
</a>

<!-- Year selector and controls -->
<div class="card mb-4 no-print">
  <div class="card-body">
    <form method="get" class="form-inline mb-3">
      <input type="hidden" name="page" value="seller-refund-report">

      <div class="form-group mr-3">
        <label for="year" class="mr-2">Anno:</label>
        <select name="year" id="year" class="form-control" onchange="this.form.submit()">
          <?php foreach ($availableYears as $year): ?>
            <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
              <?php echo $year; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="button" class="btn btn-success ml-3" onclick="exportToExcel()">
        <i class="fas fa-file-excel"></i> Esporta Excel
      </button>
    </form>

    <hr class="my-3">

    <!-- Layout size selection -->
    <div class="d-flex align-items-center flex-wrap">
      <span class="mr-3"><strong>Formato:</strong></span>

      <div class="btn-group mr-4" role="group">
        <button type="button" class="btn btn-outline-primary layout-btn active" id="btnA4" onclick="setLayout('A4')">
          <i class="fas fa-file"></i> A4 Landscape
        </button>
        <button type="button" class="btn btn-outline-primary layout-btn" id="btnA3" onclick="setLayout('A3')">
          <i class="fas fa-file-alt"></i> A3 Landscape
        </button>
      </div>

      <button type="button" class="btn btn-danger mr-2" onclick="exportToPDF()">
        <i class="fas fa-file-pdf"></i> Stampa / Esporta PDF
      </button>
    </div>
  </div>
</div>

<!-- Report table -->
<?php if (count($reportData) > 0): ?>
<div class="card">
  <div class="card-header no-print">
    <i class="fas fa-table"></i> Report Dettagliato (<?php echo count($reportData); ?> venditori)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm mb-0" id="reportTable">
        <thead class="thead-light">
          <tr>
            <th>Cognome e Nome</th>
            <th>Email</th>
            <th>Donazione</th>
            <th>IBAN</th>
            <th>Pagamento</th>
            <th>Pratiche Vendute</th>
            <th class="text-right">Totale Dovuto</th>
            <th>Libri da Rendere</th>
            <th>Note</th>
            <th>Busta Pronta</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reportData as $row): ?>
            <tr>
              <td>
                <strong><?php echo esc_html($row->last_name . ' ' . $row->first_name); ?></strong>
              </td>
              <td>
                <small><?php echo esc_html($row->email); ?></small>
              </td>
              <td class="text-center">
                <?php if ($row->donate_unsold === '1' || $row->donate_unsold === 1): ?>
                  <span class="text-success">Si</span>
                <?php elseif ($row->donate_unsold === '0' || $row->donate_unsold === 0): ?>
                  <span class="text-muted">No</span>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($row->iban): ?>
                  <code style="font-size: 0.75rem;"><?php echo esc_html($row->iban); ?></code>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($row->payment_preference === 'cash'): ?>
                  <span class="badge badge-success">Contanti</span>
                <?php elseif ($row->payment_preference === 'wire_transfer'): ?>
                  <span class="badge badge-primary">Bonifico</span>
                <?php else: ?>
                  <span class="badge badge-warning">?</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (count($row->sold_praticas) > 0): ?>
                  <?php echo esc_html(implode('/', $row->sold_praticas)); ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                <?php if ($row->amount_owed > 0): ?>
                  <?php echo number_format($row->amount_owed, 2, ',', '.'); ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (count($row->books_to_sell) > 0): ?>
                  <small>
                    <?php
                      // Group books by pratica
                      $booksByPratica = [];
                      foreach ($row->books_to_sell as $book) {
                        $parts = explode('/', $book, 2);
                        $pratica = $parts[0];
                        $title = isset($parts[1]) ? $parts[1] : $book;
                        if (!isset($booksByPratica[$pratica])) {
                          $booksByPratica[$pratica] = [];
                        }
                        $booksByPratica[$pratica][] = $title;
                      }

                      $output = [];
                      foreach ($booksByPratica as $pratica => $titles) {
                        $output[] = $pratica . '/' . implode("\n" . $pratica . '/', $titles);
                      }
                      echo nl2br(esc_html(implode("\n", $output)));
                    ?>
                  </small>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $notes = [];
                  if ($row->seller_notes) {
                    $notes[] = $row->seller_notes;
                  }
                  if ($row->comments) {
                    $notes[] = '[Admin] ' . $row->comments;
                  }
                  if (count($notes) > 0):
                ?>
                  <small style="white-space: pre-wrap; max-width: 200px; display: block;"><?php echo nl2br(esc_html(implode("\n", $notes))); ?></small>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($row->payment_preference === 'cash'): ?>
                  <?php if ($row->envelope_prepared): ?>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                  <?php else: ?>
                    <span class="text-danger"><i class="fas fa-times"></i></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessun record di rimborso trovato per l'anno <?php echo $selectedYear; ?>.
  </div>
<?php endif; ?>

<script>
// Current layout setting
var currentLayout = 'A4';

// Layout configurations
var layoutConfig = {
  'A4': {
    pageSize: 'a4',
    fontSize: '8pt',
    titleFontSize: '12pt',
    cellPadding: '3px 4px',
    margin: '10mm'
  },
  'A3': {
    pageSize: 'a3',
    fontSize: '10pt',
    titleFontSize: '14pt',
    cellPadding: '5px 8px',
    margin: '10mm'
  }
};

function setLayout(layout) {
  currentLayout = layout;

  // Update button states
  document.getElementById('btnA4').classList.toggle('active', layout === 'A4');
  document.getElementById('btnA3').classList.toggle('active', layout === 'A3');

  // Update print styles dynamically
  var config = layoutConfig[layout];
  var styleEl = document.getElementById('printStyles');

  var newStyles = `
    @media print {
      @page {
        size: ${config.pageSize} landscape;
        margin: ${config.margin};
      }

      .no-print,
      .btn,
      .card-header,
      form,
      nav,
      .navbar,
      .sidebar,
      footer {
        display: none !important;
      }

      .container,
      .container-fluid,
      .main-content {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      #reportTable {
        font-size: ${config.fontSize} !important;
        width: 100% !important;
      }

      #reportTable th,
      #reportTable td {
        padding: ${config.cellPadding} !important;
        border: 1px solid #000 !important;
      }

      #reportTable thead {
        background-color: #f0f0f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      #reportTable tr {
        page-break-inside: avoid;
      }

      .print-title {
        display: block !important;
        font-size: ${config.titleFontSize};
        font-weight: bold;
        margin-bottom: 8px;
        text-align: center;
      }

      .badge-success {
        background-color: #28a745 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .badge-primary {
        background-color: #007bff !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .badge-warning {
        background-color: #ffc107 !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }

      .text-success { color: #28a745 !important; }
      .text-danger { color: #dc3545 !important; }
    }

    #reportTable {
      font-size: 0.85rem;
    }

    #reportTable th {
      white-space: nowrap;
      vertical-align: middle;
    }

    #reportTable td {
      vertical-align: top;
    }

    .print-title {
      display: none;
    }

    .layout-btn {
      min-width: 120px;
    }
    .layout-btn.active {
      box-shadow: 0 0 0 3px rgba(0,123,255,0.5);
    }
  `;

  styleEl.textContent = newStyles;
}

function exportToExcel() {
  // Create CSV content
  var table = document.getElementById('reportTable');
  var csv = [];

  // Headers
  var headers = [];
  var headerCells = table.querySelectorAll('thead th');
  headerCells.forEach(function(cell) {
    headers.push('"' + cell.innerText.replace(/"/g, '""') + '"');
  });
  csv.push(headers.join(';'));

  // Data rows
  var rows = table.querySelectorAll('tbody tr');
  rows.forEach(function(row) {
    var rowData = [];
    var cells = row.querySelectorAll('td');
    cells.forEach(function(cell) {
      var text = cell.innerText.trim().replace(/"/g, '""').replace(/\n/g, ' ');
      rowData.push('"' + text + '"');
    });
    csv.push(rowData.join(';'));
  });

  // Download
  var csvContent = '\uFEFF' + csv.join('\n'); // BOM for Excel UTF-8
  var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'report_rimborsi_<?php echo $selectedYear; ?>.csv';
  link.click();
}

function exportToPDF() {
  // Use browser print dialog - user can choose "Save as PDF" as destination
  // This ensures the PDF matches the print layout exactly, including:
  // - Correct page size (A4 or A3 landscape)
  // - Page headers and footers
  // - Proper pagination
  alert('Per esportare in PDF:\n\n1. Nella finestra di stampa, seleziona "Salva come PDF" o "Microsoft Print to PDF" come stampante\n2. Il formato pagina (' + currentLayout + ' Landscape) è già impostato\n3. Clicca "Salva" per generare il PDF');
  window.print();
}
</script>
