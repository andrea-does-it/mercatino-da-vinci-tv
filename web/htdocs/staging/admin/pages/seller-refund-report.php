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

  // Filtri: stessi della pagina newsletter (+ stato del rimborso)
  $newsletterFilter = isset($_GET['newsletter']) ? $_GET['newsletter'] : '';
  $preferenceFilter = isset($_GET['preference']) ? $_GET['preference'] : '';
  $ibanFilter = isset($_GET['iban']) ? $_GET['iban'] : '';
  $donationFilter = isset($_GET['donation']) ? $_GET['donation'] : '';
  $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
  $toContactOnly = isset($_GET['tocontact']) && $_GET['tocontact'] === '1';

  $activeFilters = ($newsletterFilter !== '' || $preferenceFilter !== '' || $ibanFilter !== ''
    || $donationFilter !== '' || $statusFilter !== '' || $toContactOnly);

  // Get available years
  $availableYears = $sellerRefundMgr->getAvailableYears();
  if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    rsort($availableYears);
  }

  // Get report data
  $reportData = $sellerRefundMgr->getReportData($selectedYear, [
    'newsletter' => $newsletterFilter,
    'preference' => $preferenceFilter,
    'iban' => $ibanFilter,
    'donation' => $donationFilter,
    'status' => $statusFilter,
    'to_contact' => $toContactOnly
  ]);
?>

<!-- Print/screen styles.
     SINGLE SOURCE OF TRUTH: sizes come from CSS custom properties that
     setLayout()/setFontSize() update. Do NOT duplicate these rules in JS --
     they used to be rebuilt as a template literal there, which meant every
     rule had to be edited twice. -->
<style>
  :root {
    --report-font-size: 8pt;
    --report-cell-padding: 3px 4px;
    --report-title-font-size: 12pt;
    --report-screen-font-size: 0.85rem;
  }

  @media print {
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

    #reportTable {
      font-size: var(--report-font-size) !important;
      width: 100% !important;
    }

    #reportTable th,
    #reportTable td {
      padding: var(--report-cell-padding) !important;
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
      font-size: var(--report-title-font-size);
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

  /* Screen styles: same chosen size, so what you see is what prints */
  #reportTable {
    font-size: var(--report-screen-font-size);
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
    min-width: 110px;
  }
  .layout-btn.active {
    box-shadow: 0 0 0 3px rgba(0,123,255,0.5);
  }

  /* "Libri da Rendere": the cell renders both the grouped list and the plain
     count; the active format decides which one is shown. innerText ignores
     display:none, so the CSV export follows the chosen format automatically. */
  .books-count { display: none; }
  #reportTable.report-compact .books-list { display: none; }
  #reportTable.report-compact .books-count { display: inline; }
  #reportTable.report-compact .books-cell {
    text-align: center;
    font-weight: bold;
  }

  /* "A4 Sintetico": l'IBAN non serve nel riepilogo (la colonna Pagamento dice
     già bonifico/contanti) e toglierlo libera larghezza. La classe è sia sul
     <th> sia sul <td>, così intestazione e celle spariscono insieme. */
  #reportTable.report-compact .iban-col { display: none; }
</style>

<!-- Only the @page rule lives here: `size` cannot read a CSS custom property,
     so setLayout() rewrites this one declaration. Kept with a working default
     so printing behaves correctly even before any button is clicked. -->
<style id="pageRule">@media print { @page { size: a4 landscape; margin: 10mm; } }</style>

<h1 class="no-print">Report Rimborsi Venditori - <?php echo $selectedYear; ?></h1>
<div class="print-title">Report Rimborsi Venditori - <?php echo $selectedYear; ?><?php
  // Sulla carta un report filtrato è indistinguibile da quello completo:
  // va detto esplicitamente anche nella stampa.
  if ($activeFilters) {
    echo ' (elenco filtrato: ' . count($reportData) . ' venditori)';
  }
?></div>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mb-3 no-print">
  <i class="fas fa-arrow-left"></i> Torna alla gestione rimborsi
</a>

<div class="alert alert-secondary no-print">
  <i class="fas fa-store"></i>
  La <strong>pratica <?php echo SellerRefundManager::BOOKSHOP_PRATICA; ?></strong> (libri di propriet&agrave;
  del mercatino) &egrave; esclusa da questo report: quelle vendite sono incasso netto del Comitato e non
  vengono rimborsate a nessun venditore.
</div>

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

      <div class="form-group mr-3">
        <label for="newsletter" class="mr-2">Newsletter:</label>
        <select name="newsletter" id="newsletter" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="sent" <?php echo $newsletterFilter === 'sent' ? 'selected' : ''; ?>>Inviate</option>
          <option value="not_sent" <?php echo $newsletterFilter === 'not_sent' ? 'selected' : ''; ?>>Non inviate</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="preference" class="mr-2">Preferenza:</label>
        <select name="preference" id="preference" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="set" <?php echo $preferenceFilter === 'set' ? 'selected' : ''; ?>>Impostata</option>
          <option value="not_set" <?php echo $preferenceFilter === 'not_set' ? 'selected' : ''; ?>>Non impostata</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="status" class="mr-2">Stato:</label>
        <select name="status" id="status" class="form-control" onchange="this.form.submit()">
          <option value="">Tutti</option>
          <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>In attesa</option>
          <option value="partial" <?php echo $statusFilter === 'partial' ? 'selected' : ''; ?>>Parziale</option>
          <option value="xmlsaved" <?php echo $statusFilter === 'xmlsaved' ? 'selected' : ''; ?>>Distinta generata</option>
          <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completato</option>
          <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Annullato</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="iban" class="mr-2">IBAN:</label>
        <select name="iban" id="iban" class="form-control" onchange="this.form.submit()">
          <option value="">Tutti</option>
          <option value="with" <?php echo $ibanFilter === 'with' ? 'selected' : ''; ?>>Con IBAN</option>
          <option value="without" <?php echo $ibanFilter === 'without' ? 'selected' : ''; ?>>Senza IBAN</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="donation" class="mr-2">Donazione:</label>
        <select name="donation" id="donation" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="yes" <?php echo $donationFilter === 'yes' ? 'selected' : ''; ?>>Dona</option>
          <option value="no" <?php echo $donationFilter === 'no' ? 'selected' : ''; ?>>Non dona</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <div class="form-check">
          <input type="checkbox" class="form-check-input" name="tocontact" id="tocontact" value="1"
                 <?php echo $toContactOnly ? 'checked' : ''; ?> onchange="this.form.submit()">
          <label class="form-check-label" for="tocontact" title="Venditori senza IBAN oppure che non hanno scelto di donare i libri invenduti">
            <strong>Da contattare</strong>
          </label>
        </div>
      </div>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-report&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mr-3">
        <i class="fas fa-sync"></i> Reset
      </a>

      <button type="button" class="btn btn-success" onclick="exportToExcel()">
        <i class="fas fa-file-excel"></i> Esporta Excel
      </button>
    </form>

    <?php if ($activeFilters): ?>
      <div class="alert alert-warning py-2 mb-3">
        <i class="fas fa-filter"></i> Il report è <strong>filtrato</strong>: mostra
        <?php echo count($reportData); ?> venditori, non l'elenco completo dell'anno.
      </div>
    <?php endif; ?>

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
        <button type="button" class="btn btn-outline-primary layout-btn" id="btnA4C" onclick="setLayout('A4C')"
                title="A4 landscape: la colonna 'Libri da Rendere' mostra solo il numero di libri invenduti">
          <i class="fas fa-list-ol"></i> A4 Sintetico
        </button>
      </div>

      <div class="form-inline mr-4">
        <label for="fontSize" class="mr-2"><strong>Carattere:</strong></label>
        <select id="fontSize" class="form-control" onchange="setFontSize(this.value)">
          <?php foreach ([6, 7, 8, 9, 10, 11, 12, 14] as $pt): ?>
            <option value="<?php echo $pt; ?>"><?php echo $pt; ?> pt</option>
          <?php endforeach; ?>
        </select>
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
            <th class="iban-col">IBAN</th>
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
              <td class="iban-col">
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
              <td class="books-cell">
                <?php if (count($row->books_to_sell) > 0): ?>
                  <?php // Elenco completo (formati A4 / A3) ?>
                  <small class="books-list">
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
                  <?php // Solo il numero (formato A4 Sintetico) ?>
                  <span class="books-count"><?php echo count($row->books_to_sell); ?></span>
                <?php else: ?>
                  <span class="text-muted books-list">-</span>
                  <span class="books-count">0</span>
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

// Layout configurations.
// Only these values change between formats: the CSS rules themselves live once
// in the <style> block above and read them through custom properties.
var layoutConfig = {
  'A4': {
    label: 'A4 Landscape',
    pageSize: 'a4',
    fontSize: 8,
    titleFontSize: '12pt',
    cellPadding: '3px 4px',
    margin: '10mm',
    compact: false
  },
  'A3': {
    label: 'A3 Landscape',
    pageSize: 'a3',
    fontSize: 10,
    titleFontSize: '14pt',
    cellPadding: '5px 8px',
    margin: '10mm',
    compact: false
  },
  'A4C': {
    label: 'A4 Sintetico',
    pageSize: 'a4',
    fontSize: 9,
    titleFontSize: '12pt',
    cellPadding: '3px 6px',
    margin: '10mm',
    compact: true
  }
};

// Only the @page rule needs to be rewritten: `size` cannot read a CSS variable.
function applyPageRule(config) {
  var styleEl = document.getElementById('pageRule');
  styleEl.textContent =
    '@media print { @page { size: ' + config.pageSize + ' landscape; margin: ' + config.margin + '; } }';
}

// Format and font survive a filter change (each one reloads the page).
// sessionStorage can throw in private mode, so every access is guarded.
function rememberChoice(key, value) {
  try {
    sessionStorage.setItem(key, value);
  } catch (e) {
    // Not being able to remember the choice must not break the page.
  }
}

function recallChoice(key) {
  try {
    return sessionStorage.getItem(key);
  } catch (e) {
    return null;
  }
}

function setFontSize(pt) {
  var root = document.documentElement;
  root.style.setProperty('--report-font-size', pt + 'pt');
  // Screen preview uses the same size, so what you see is what prints.
  root.style.setProperty('--report-screen-font-size', pt + 'pt');
  document.getElementById('fontSize').value = String(pt);
  rememberChoice('refundReportFontSize', String(pt));
}

function setLayout(layout) {
  currentLayout = layout;
  var config = layoutConfig[layout];

  // Update button states
  document.getElementById('btnA4').classList.toggle('active', layout === 'A4');
  document.getElementById('btnA3').classList.toggle('active', layout === 'A3');
  document.getElementById('btnA4C').classList.toggle('active', layout === 'A4C');

  var root = document.documentElement;
  root.style.setProperty('--report-cell-padding', config.cellPadding);
  root.style.setProperty('--report-title-font-size', config.titleFontSize);

  // "Libri da Rendere": full list vs plain count
  var table = document.getElementById('reportTable');
  if (table) {
    table.classList.toggle('report-compact', config.compact);
  }

  applyPageRule(config);
  rememberChoice('refundReportLayout', layout);

  // Switching format restores that format's default size; it stays overridable.
  setFontSize(config.fontSize);
}

// A column hidden by the active format (e.g. IBAN in "A4 Sintetico") must be
// left out of the CSV entirely, not exported as an empty column. The same class
// hides the <th> and its <td>s, so header and data stay aligned.
function isHiddenCell(cell) {
  return window.getComputedStyle(cell).display === 'none';
}

function exportToExcel() {
  // Create CSV content
  var table = document.getElementById('reportTable');
  var csv = [];

  // Headers
  var headers = [];
  var headerCells = table.querySelectorAll('thead th');
  headerCells.forEach(function(cell) {
    if (isHiddenCell(cell)) { return; }
    headers.push('"' + cell.innerText.replace(/"/g, '""') + '"');
  });
  csv.push(headers.join(';'));

  // Data rows
  var rows = table.querySelectorAll('tbody tr');
  rows.forEach(function(row) {
    var rowData = [];
    var cells = row.querySelectorAll('td');
    cells.forEach(function(cell) {
      if (isHiddenCell(cell)) { return; }
      // innerText ignores display:none, so a cell showing only the book count
      // exports the count, matching what is on screen.
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
  var config = layoutConfig[currentLayout];
  alert('Per esportare in PDF:\n\n1. Nella finestra di stampa, seleziona "Salva come PDF" o "Microsoft Print to PDF" come stampante\n2. Il formato pagina (' + config.label + ', carattere ' + document.getElementById('fontSize').value + 'pt) è già impostato\n3. Clicca "Salva" per generare il PDF');
  window.print();
}

// Apply the layout on load, so the buttons, the font selector, the @page rule
// and the table class all start in sync. Restores the previous choice, so
// changing a filter (which reloads the page) does not reset the format.
document.addEventListener('DOMContentLoaded', function() {
  var savedLayout = recallChoice('refundReportLayout');
  setLayout(layoutConfig[savedLayout] ? savedLayout : currentLayout);

  // After setLayout(), which has applied the format's default size.
  var savedFont = recallChoice('refundReportFontSize');
  if (savedFont) {
    setFontSize(savedFont);
  }
});
</script>
