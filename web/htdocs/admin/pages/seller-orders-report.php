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

  // Un venditore per riga, con l'elenco dei libri da rendere
  $sellers = $sellerRefundMgr->getOrdersOverview($selectedYear);

  // Totali generali
  $grand = ['sellers' => count($sellers), 'sold_books' => 0, 'sold_amount' => 0.00,
            'to_return' => 0, 'donated' => 0];
  foreach ($sellers as $seller) {
    $grand['sold_books'] += $seller['sold_books'];
    $grand['sold_amount'] += $seller['sold_amount'];
    $grand['to_return'] += $seller['to_return'];
    $grand['donated'] += $seller['donated'];
  }
?>

<style>
  :root {
    --orders-font-size: 9pt;
    --orders-books-font-size: 7pt;
    --orders-screen-font-size: 0.85rem;
    --orders-books-screen-font-size: 0.75rem;
  }

  @media print {
    .no-print, .btn, .card-header, form, nav, .navbar, .sidebar, footer {
      display: none !important;
    }
    .container, .container-fluid, .main-content {
      max-width: 100% !important;
      width: 100% !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    #ordersTable {
      font-size: var(--orders-font-size) !important;
      width: 100% !important;
      /* separate, NON collapse: vedi il commento sui bordi qui sotto */
      border-collapse: separate !important;
      border-spacing: 0 !important;
      border-top: 1px solid #000 !important;
      border-left: 1px solid #000 !important;
    }
    /* Griglia: `border-collapse: separate` di proposito.
       In stampa, con `collapse`, i separatori verticali sparivano e la linea
       orizzontale risultava INTERROTTA proprio in corrispondenza degli incroci:
       il sintomo tipico dell'algoritmo di collapse, che agli angoli mette in
       competizione i bordi delle celle adiacenti (e di riga, gruppo e tabella)
       e ne fa vincere uno solo -- lì vinceva un bordo chiaro/invisibile.
       Non è un problema di specificità: con `separate` ogni cella disegna i
       propri bordi e non c'è nessun arbitraggio agli angoli.
       Per non avere linee doppie: le celle disegnano solo destra e sotto, la
       tabella chiude sopra e a sinistra (border-spacing: 0).
       La tabella inoltre NON usa la classe `table-bordered`: nel blocco
       @media print di Bootstrap quella classe dichiara un `border` !important
       che rimetterebbe in gioco tutti e quattro i lati. */
    #ordersTable thead tr th,
    #ordersTable tbody tr td {
      padding: 3px 5px !important;
      border: 0 !important;
      border-right: 1px solid #000 !important;
      border-bottom: 1px solid #000 !important;
    }
    #ordersTable thead {
      background-color: #f0f0f0 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    /* Una riga per venditore non deve spezzarsi fra due pagine */
    #ordersTable tr { page-break-inside: avoid; }
    #ordersTable .books-cell { font-size: var(--orders-books-font-size) !important; }
    .print-title {
      display: block !important;
      font-size: 12pt;
      font-weight: bold;
      margin-bottom: 8px;
      text-align: center;
    }
  }

  /* Stessa tecnica della stampa (separate + destra/sotto), così schermo e PDF
     non possono divergere. */
  #ordersTable {
    font-size: var(--orders-screen-font-size);
    border-collapse: separate;
    border-spacing: 0;
    border-top: 1px solid #6c757d;
    border-left: 1px solid #6c757d;
  }
  #ordersTable thead tr th,
  #ordersTable tbody tr td {
    border: 0;
    border-right: 1px solid #6c757d;
    border-bottom: 1px solid #6c757d;
  }
  #ordersTable th { vertical-align: middle; }
  #ordersTable td { vertical-align: top; }
  #ordersTable .books-cell {
    font-size: var(--orders-books-screen-font-size);
    line-height: 1.25;
    white-space: nowrap;
  }
  .print-title { display: none; }

  /* Colonne da compilare a mano: servono larghezza e altezza per scrivere */
  #ordersTable .handwritten {
    min-width: 110px;
  }
  #ordersTable tbody tr { height: 2.2rem; }
</style>

<!-- @page separato: `size` non può leggere una variabile CSS -->
<style id="ordersPageRule">@media print { @page { size: a3 landscape; margin: 8mm; } }</style>

<h1 class="no-print">Riepilogo Pratiche - <?php echo $selectedYear; ?></h1>
<div class="print-title">Ritiro libri e rimborsi - <?php echo $selectedYear; ?></div>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mb-3 no-print">
  <i class="fas fa-arrow-left"></i> Torna alla gestione rimborsi
</a>

<div class="alert alert-secondary no-print">
  <i class="fas fa-info-circle"></i>
  Foglio di ritiro: una riga per venditore, con le colonne di destra da compilare a mano al banco.
  Le cifre sono lette direttamente dallo stato dei libri (<code>vendere</code> / <code>venduto</code>),
  quindi compaiono anche venditori senza record di rimborso e anche vendite non registrate in
  <em>Gestione Vendite</em>. Sono esclusi i venditori che non devono ritirare n&eacute; denaro n&eacute;
  libri, e la <strong>pratica <?php echo SellerRefundManager::BOOKSHOP_PRATICA; ?></strong>
  (libri del mercatino).
</div>

<!-- Controlli -->
<div class="card mb-4 no-print">
  <div class="card-body">
    <form method="get" class="form-inline">
      <input type="hidden" name="page" value="seller-orders-report">

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
        <label for="fontSize" class="mr-2">Carattere:</label>
        <select id="fontSize" class="form-control" onchange="setOrdersFontSize(this.value)">
          <?php foreach ([6, 7, 8, 9, 10, 11, 12] as $pt): ?>
            <option value="<?php echo $pt; ?>" <?php echo $pt === 9 ? 'selected' : ''; ?>><?php echo $pt; ?> pt</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="booksFontSize" class="mr-2">Carattere elenco libri:</label>
        <select id="booksFontSize" class="form-control" onchange="setBooksFontSize(this.value)">
          <?php foreach ([5, 6, 7, 8, 9, 10] as $pt): ?>
            <option value="<?php echo $pt; ?>" <?php echo $pt === 7 ? 'selected' : ''; ?>><?php echo $pt; ?> pt</option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="button" class="btn btn-success mr-2" onclick="exportOrdersToExcel()">
        <i class="fas fa-file-excel"></i> Esporta Excel
      </button>
      <button type="button" class="btn btn-danger" onclick="window.print()">
        <i class="fas fa-file-pdf"></i> Stampa / Esporta PDF (A3)
      </button>
    </form>
  </div>
</div>

<!-- Totali -->
<div class="row mb-4 no-print">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center py-2">
        <h4 class="mb-0"><?php echo (int)$grand['sellers']; ?></h4>
        <small>Venditori da servire</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning">
      <div class="card-body text-center py-2">
        <h4 class="mb-0">&euro; <?php echo number_format((float)$grand['sold_amount'], 2, ',', '.'); ?></h4>
        <small>Totale da Rimborsare (<?php echo (int)$grand['sold_books']; ?> libri venduti)</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-dark text-white">
      <div class="card-body text-center py-2">
        <h4 class="mb-0"><?php echo (int)$grand['to_return']; ?></h4>
        <small>Libri da Restituire</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center py-2">
        <h4 class="mb-0"><?php echo (int)$grand['donated']; ?></h4>
        <small>Libri Donati (non da restituire)</small>
      </div>
    </div>
  </div>
</div>

<?php if (count($sellers) > 0): ?>
<div class="card">
  <div class="card-header no-print">
    <i class="fas fa-table"></i> Foglio di ritiro (<?php echo count($sellers); ?> venditori)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <?php // NIENTE `table-bordered`: i bordi li definisce solo il CSS qui sopra.
            // Vedi il commento sui bordi nel blocco <style>. ?>
      <table class="table table-sm mb-0" id="ordersTable">
        <thead class="thead-light">
          <tr>
            <th>Nome Venditore</th>
            <th>Lista pratiche</th>
            <th>Libri da rendere</th>
            <th class="text-right">Totale dovuto</th>
            <th>Contanti/Bonifico</th>
            <th>Dona i libri</th>
            <th class="handwritten">Nome delegato (se diverso dal venditore)</th>
            <th class="handwritten">Tipo documento di identit&agrave;</th>
            <th class="handwritten">Numero e data rilascio</th>
            <th class="handwritten">Data ritiro libri o denaro</th>
            <th class="handwritten">Firma</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sellers as $seller): ?>
            <tr>
              <td><strong><?php echo esc_html($seller['last_name'] . ' ' . $seller['first_name']); ?></strong></td>
              <td><?php echo esc_html($seller['pratica_list']); ?></td>
              <td class="books-cell">
                <?php if (count($seller['books']) > 0): ?>
                  <?php
                    // Una riga per libro, "pratica/titolo", nell'ordine restituito
                    // dalla query (pratica, poi titolo).
                    $lines = [];
                    foreach ($seller['books'] as $book) {
                      $lines[] = $book['pratica'] . '/' . $book['title'];
                    }
                    echo nl2br(esc_html(implode("\n", $lines)));
                  ?>
                  <?php if ($seller['donates']): ?>
                    <br><span class="badge badge-success">donati: restano al Comitato</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-right">&euro; <?php echo number_format((float)$seller['sold_amount'], 2, ',', '.'); ?></td>
              <td><?php echo $seller['payment'] === 'wire_transfer' ? 'Bonifico' : 'Contanti'; ?></td>
              <td><?php echo $seller['donates'] ? 'S&igrave;' : 'No'; ?></td>
              <td class="handwritten"></td>
              <td class="handwritten"></td>
              <td class="handwritten"></td>
              <td class="handwritten"></td>
              <td class="handwritten"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessun venditore con denaro o libri da ritirare per l'anno <?php echo $selectedYear; ?>.
  </div>
<?php endif; ?>

<script>
function setOrdersFontSize(pt) {
  var root = document.documentElement;
  root.style.setProperty('--orders-font-size', pt + 'pt');
  root.style.setProperty('--orders-screen-font-size', pt + 'pt');
}

function setBooksFontSize(pt) {
  var root = document.documentElement;
  root.style.setProperty('--orders-books-font-size', pt + 'pt');
  root.style.setProperty('--orders-books-screen-font-size', pt + 'pt');
}

function exportOrdersToExcel() {
  var table = document.getElementById('ordersTable');
  if (!table) { return; }
  var csv = [];

  table.querySelectorAll('thead tr, tbody tr').forEach(function(row) {
    var rowData = [];
    row.querySelectorAll('th, td').forEach(function(cell) {
      // L'elenco libri resta multiriga dentro la cella: in CSV le righe si
      // separano con " | " per non rompere il record.
      var text = cell.innerText.trim().replace(/"/g, '""').replace(/\n/g, ' | ');
      rowData.push('"' + text + '"');
    });
    csv.push(rowData.join(';'));
  });

  var csvContent = '\uFEFF' + csv.join('\n'); // BOM for Excel UTF-8
  var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'foglio_ritiro_<?php echo $selectedYear; ?>.csv';
  link.click();
}

// Applica le dimensioni iniziali scelte nelle select
document.addEventListener('DOMContentLoaded', function() {
  setOrdersFontSize(document.getElementById('fontSize').value);
  setBooksFontSize(document.getElementById('booksFontSize').value);
});
</script>
