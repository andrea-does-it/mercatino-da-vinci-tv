<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $sellerRefundMgr = new SellerRefundManager();

  // Default to current year
  $currentYear = (int)date('Y');
  $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

  // Get filters
  $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
  $preferenceFilter = isset($_GET['preference']) ? $_GET['preference'] : '';

  // Handle actions
  if (isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      switch ($_POST['action']) {
        case 'create_records':
          // Create refund records for all sellers who sold books this year
          $count = $sellerRefundMgr->createRecordsForYear($selectedYear);
          log_activity($loggedInUser->id, 'admin_refund_records_created', 'year: ' . $selectedYear . ', count: ' . $count);
          $alertMsg = $count > 0 ? 'records_created' : 'no_records_to_create';
          break;

        case 'apply_user_defaults':
          // Fill the missing preferences of existing records from the user profile
          $applied = $sellerRefundMgr->applyUserDefaultsToYear($selectedYear);
          log_activity($loggedInUser->id, 'admin_refund_user_defaults_applied',
            'year: ' . $selectedYear . ', pagamento: ' . $applied['payment'] . ', donazione: ' . $applied['donation']);
          $_SESSION['defaults_payment_count'] = $applied['payment'];
          $_SESSION['defaults_donation_count'] = $applied['donation'];
          $alertMsg = ($applied['payment'] + $applied['donation']) > 0 ? 'defaults_applied' : 'no_defaults_to_apply';
          break;

        case 'recalculate':
          // Recalculate amount owed for a seller
          if (isset($_POST['refund_id'])) {
            $sellerRefundMgr->recalculateAmountOwed((int)$_POST['refund_id']);
            log_activity($loggedInUser->id, 'admin_refund_recalc', 'refund_id: ' . (int)$_POST['refund_id']);
            $alertMsg = 'amount_recalculated';
          }
          break;
      }
    }
  }

  // Get available years
  $availableYears = $sellerRefundMgr->getAvailableYears();
  if (!in_array($currentYear, $availableYears)) {
    $availableYears[] = $currentYear;
    rsort($availableYears);
  }

  // Get refunds for selected year
  $refunds = $sellerRefundMgr->getRefundsForYear($selectedYear, $statusFilter, $preferenceFilter);

  // Get summary
  $summary = $sellerRefundMgr->getYearSummary($selectedYear);

  // Stima rimborsi per modalità, calcolata dai libri venduti (indipendente dai record)
  $refundEstimate = $sellerRefundMgr->getRefundEstimateByIban($selectedYear);

  // Ricavo del Comitato: vendite della pratica 100 + ricarico sulle altre pratiche
  $bookshopIncome = $sellerRefundMgr->getBookshopIncome($selectedYear);

  // Get sellers without refund records
  $sellersWithoutRecords = $sellerRefundMgr->getSellersWithoutRefundRecord($selectedYear);

  // Record esistenti che possono ancora ereditare una preferenza dal profilo
  $recordsNeedingDefaults = $sellerRefundMgr->countRecordsNeedingUserDefaults($selectedYear);

  // Alert messages
  $alertMessages = [
    'records_created' => ['type' => 'success', 'text' => 'Record di rimborso creati con successo.'],
    'no_records_to_create' => ['type' => 'info', 'text' => 'Nessun nuovo record da creare.'],
    'amount_recalculated' => ['type' => 'success', 'text' => 'Importo ricalcolato con successo.'],
    'defaults_applied' => ['type' => 'success', 'text' => 'Preferenze dal profilo applicate: '
      . (isset($_SESSION['defaults_payment_count']) ? (int)$_SESSION['defaults_payment_count'] : 0) . ' modalità di pagamento, '
      . (isset($_SESSION['defaults_donation_count']) ? (int)$_SESSION['defaults_donation_count'] : 0) . ' preferenze di donazione.'],
    'no_defaults_to_apply' => ['type' => 'info', 'text' => 'Nessun record da aggiornare: tutte le preferenze sono già impostate.'],
  ];
  // Clear session counts after reading
  unset($_SESSION['defaults_payment_count'], $_SESSION['defaults_donation_count']);
?>

<div class="alert alert-secondary">
  <i class="fas fa-store"></i>
  I libri della <strong>pratica <?php echo SellerRefundManager::BOOKSHOP_PRATICA; ?></strong> sono di
  propriet&agrave; del mercatino: il loro incasso &egrave; guadagno netto del Comitato e non viene mai
  conteggiato nei rimborsi. Sono esclusi da importi, elenchi, newsletter e report di questa sezione
  (restano invece nelle vendite, dove l'incasso &egrave; reale).
</div>

<h1>Gestione Rimborsi Venditori - <?php echo $selectedYear; ?>
  <a href="<?php echo ROOT_URL; ?>admin/?page=help-seller-refunds" class="btn btn-sm btn-outline-info ml-2" title="Guida">
    <i class="fas fa-question-circle"></i> Guida
  </a>
</h1>

<?php if ($alertMsg && isset($alertMessages[$alertMsg])): ?>
  <div class="alert alert-<?php echo $alertMessages[$alertMsg]['type']; ?> alert-dismissible fade show">
    <?php echo $alertMessages[$alertMsg]['text']; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
  </div>
<?php endif; ?>

<!-- Year selector and filters -->
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="form-inline">
      <input type="hidden" name="page" value="seller-refunds">

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
        <label for="status" class="mr-2">Stato:</label>
        <select name="status" id="status" class="form-control" onchange="this.form.submit()">
          <option value="">Tutti</option>
          <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>In attesa</option>
          <option value="partial" <?php echo $statusFilter === 'partial' ? 'selected' : ''; ?>>Parziale</option>
          <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completato</option>
        </select>
      </div>

      <div class="form-group mr-3">
        <label for="preference" class="mr-2">Preferenza:</label>
        <select name="preference" id="preference" class="form-control" onchange="this.form.submit()">
          <option value="">Tutte</option>
          <option value="cash" <?php echo $preferenceFilter === 'cash' ? 'selected' : ''; ?>>Contanti</option>
          <option value="wire_transfer" <?php echo $preferenceFilter === 'wire_transfer' ? 'selected' : ''; ?>>Bonifico</option>
        </select>
      </div>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary">
        <i class="fas fa-sync"></i> Reset
      </a>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-newsletter&year=<?php echo $selectedYear; ?>" class="btn btn-info ml-3">
        <i class="fas fa-envelope"></i> Gestione Newsletter
      </a>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-report&year=<?php echo $selectedYear; ?>" class="btn btn-success ml-2">
        <i class="fas fa-file-excel"></i> Report
      </a>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-orders-report&year=<?php echo $selectedYear; ?>" class="btn btn-outline-dark ml-2"
         title="Tutte le pratiche con libri, anche senza record di rimborso o vendita registrata">
        <i class="fas fa-boxes"></i> Riepilogo Pratiche
      </a>

      <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-sepa&year=<?php echo $selectedYear; ?>" class="btn btn-outline-primary ml-2"
         title="Genera il file XML dei bonifici da caricare in banca">
        <i class="fas fa-university"></i> Distinte SEPA
      </a>
    </form>
  </div>
</div>

<!-- Stima rimborsi per modalità (IBAN presente o assente) -->
<?php
  $estWire = $refundEstimate['wire'];
  $estCash = $refundEstimate['cash'];
  $estSellers = $estWire['sellers'] + $estCash['sellers'];
  $estTotal = $estWire['total_owed'] + $estCash['total_owed'];
?>
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-calculator"></i> Stima Rimborsi per Modalit&agrave; - <?php echo $selectedYear; ?>
  </div>
  <div class="card-body">
    <table class="table table-sm mb-2">
      <thead class="thead-light">
        <tr>
          <th>Modalit&agrave;</th>
          <th class="text-right">Venditori</th>
          <th class="text-right">Importo</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><span class="badge badge-primary"><i class="fas fa-university"></i> Bonifico</span> <small class="text-muted">IBAN presente</small></td>
          <td class="text-right"><?php echo $estWire['sellers']; ?></td>
          <td class="text-right">&euro; <?php echo number_format($estWire['total_owed'], 2, ',', '.'); ?></td>
        </tr>
        <tr>
          <td><span class="badge badge-success"><i class="fas fa-money-bill-alt"></i> Contanti</span> <small class="text-muted">IBAN assente</small></td>
          <td class="text-right"><?php echo $estCash['sellers']; ?></td>
          <td class="text-right">&euro; <?php echo number_format($estCash['total_owed'], 2, ',', '.'); ?></td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="font-weight-bold">
          <td>Totale</td>
          <td class="text-right"><?php echo $estSellers; ?></td>
          <td class="text-right">&euro; <?php echo number_format($estTotal, 2, ',', '.'); ?></td>
        </tr>
      </tfoot>
    </table>
    <small class="text-muted">
      <i class="fas fa-info-circle"></i>
      Stima calcolata sui libri venduti nell'anno, in base alla presenza dell'IBAN nella scheda del venditore.
      Non tiene conto dei record di rimborso n&eacute; di quanto gi&agrave; pagato, e non risente dei filtri qui sopra.
    </small>
  </div>
</div>

<!-- Ricavo del Comitato -->
<div class="card mb-4">
  <div class="card-header">
    <i class="fas fa-piggy-bank"></i> Ricavo del Comitato - <?php echo $selectedYear; ?>
  </div>
  <div class="card-body">
    <table class="table table-sm mb-2">
      <thead class="thead-light">
        <tr>
          <th>Voce</th>
          <th class="text-right">Libri</th>
          <th class="text-right">Importo</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <span class="badge badge-dark"><i class="fas fa-store"></i> Pratica <?php echo SellerRefundManager::BOOKSHOP_PRATICA; ?></span>
            <small class="text-muted">libri di propriet&agrave; del mercatino: incasso interamente del Comitato</small>
          </td>
          <td class="text-right"><?php echo (int)$bookshopIncome['bookshop']['books']; ?></td>
          <td class="text-right">&euro; <?php echo number_format((float)$bookshopIncome['bookshop']['gross'], 2, ',', '.'); ?></td>
        </tr>
        <tr>
          <td>
            <span class="badge badge-info"><i class="fas fa-percent"></i> Ricarico</span>
            <small class="text-muted">quota trattenuta (trattenuta venditore + ricarico acquirente) sui libri delle altre pratiche</small>
          </td>
          <td class="text-right"><?php echo (int)$bookshopIncome['overhead']['books']; ?></td>
          <td class="text-right">&euro; <?php echo number_format((float)$bookshopIncome['overhead']['total'], 2, ',', '.'); ?></td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="font-weight-bold">
          <td>Totale ricavo</td>
          <td class="text-right"><?php echo (int)$bookshopIncome['bookshop']['books'] + (int)$bookshopIncome['overhead']['books']; ?></td>
          <td class="text-right">&euro; <?php echo number_format((float)$bookshopIncome['total'], 2, ',', '.'); ?></td>
        </tr>
      </tfoot>
    </table>

    <?php if (count($bookshopIncome['by_pratica']) > 0): ?>
      <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-toggle="collapse" data-target="#incomeDetail">
        <i class="fas fa-list"></i> Dettaglio ricarico per pratica (<?php echo count($bookshopIncome['by_pratica']); ?>)
      </button>
      <div class="collapse" id="incomeDetail">
        <table class="table table-sm table-bordered mb-2">
          <thead class="thead-light">
            <tr>
              <th>Pratica</th>
              <th class="text-right">Libri venduti</th>
              <th class="text-right">Incassato</th>
              <th class="text-right">Ricarico al Comitato</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bookshopIncome['by_pratica'] as $detail): ?>
              <tr>
                <td><?php echo (int)$detail['pratica']; ?></td>
                <td class="text-right"><?php echo (int)$detail['books']; ?></td>
                <td class="text-right">&euro; <?php echo number_format((float)$detail['gross'], 2, ',', '.'); ?></td>
                <td class="text-right">&euro; <?php echo number_format((float)$detail['overhead'], 2, ',', '.'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ((int)$bookshopIncome['unlinked']['books'] > 0): ?>
      <div class="alert alert-warning py-2 mb-2">
        <i class="fas fa-exclamation-triangle"></i>
        <strong><?php echo (int)$bookshopIncome['unlinked']['books']; ?></strong> libri risultano venduti
        nel <?php echo $selectedYear; ?> ma non sono collegati a nessuna vendita registrata
        (quota venditore &euro; <?php echo number_format((float)$bookshopIncome['unlinked']['amount'], 2, ',', '.'); ?>).
        <strong>Non sono conteggiati</strong> negli importi qui sopra: per loro non esiste un prezzo di
        vendita registrato da cui ricavare il ricarico. Di solito sono libri segnati "venduto" con la
        vecchia procedura, prima della Gestione Vendite.
      </div>
    <?php endif; ?>

    <small class="text-muted">
      <i class="fas fa-info-circle"></i>
      Calcolato sulle vendite registrate nell'anno (<em>Gestione Vendite</em>), escluse quelle rimborsate.
      Il ricarico di ogni libro &egrave; la differenza fra il prezzo effettivamente incassato e la quota del
      venditore, quindi gli anni passati non cambiano se modifichi le impostazioni di ricarico.
      Non risente dei filtri qui sopra.
    </small>
  </div>
</div>

<!-- Summary cards -->
<?php if ($summary): ?>
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card bg-primary text-white">
      <div class="card-body text-center">
        <h3><?php echo (int)$summary->total_sellers; ?></h3>
        <small>Venditori Totali</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-warning">
      <div class="card-body text-center">
        <h3>&euro; <?php echo number_format((float)$summary->total_owed - (float)$summary->total_paid, 2, ',', '.'); ?></h3>
        <small>Da Pagare</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-success text-white">
      <div class="card-body text-center">
        <h3>&euro; <?php echo number_format((float)$summary->total_paid, 2, ',', '.'); ?></h3>
        <small>Già Pagato</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card bg-info text-white">
      <div class="card-body text-center">
        <h3><?php echo (int)$summary->no_preference_count; ?></h3>
        <small>Senza Preferenza</small>
      </div>
    </div>
  </div>
</div>
<div class="row mb-4">
  <div class="col-md-3">
    <div class="card bg-dark text-white">
      <div class="card-body text-center">
        <h3>&euro; <?php echo number_format((float)$summary->cash_outstanding, 2, ',', '.'); ?></h3>
        <small>
          Contanti da Rimborsare
          <?php if ((int)$summary->cash_outstanding_sellers > 0): ?>
            <br><?php echo (int)$summary->cash_outstanding_sellers; ?> venditori
          <?php endif; ?>
        </small>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Action buttons -->
<div class="mb-4">
  <?php if (count($sellersWithoutRecords) > 0): ?>
    <form method="post" class="d-inline">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="create_records">
      <button type="submit" class="btn btn-success" onclick="return confirm('Creare <?php echo count($sellersWithoutRecords); ?> nuovi record di rimborso?');">
        <i class="fas fa-plus"></i> Crea Record per <?php echo count($sellersWithoutRecords); ?> Venditori
      </button>
    </form>
  <?php endif; ?>

  <?php if ($recordsNeedingDefaults > 0): ?>
    <form method="post" class="d-inline">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="apply_user_defaults">
      <button type="submit" class="btn btn-outline-primary"
              title="Imposta la modalità di pagamento (bonifico se c'è l'IBAN, altrimenti contanti) e la preferenza di donazione leggendole dal profilo del venditore. Le preferenze già espresse non vengono toccate."
              onclick="return confirm('Applicare le preferenze dal profilo a <?php echo (int)$recordsNeedingDefaults; ?> record?\n\nLe preferenze già espresse dai venditori non verranno modificate.');">
        <i class="fas fa-user-check"></i> Applica Preferenze dal Profilo (<?php echo (int)$recordsNeedingDefaults; ?>)
      </button>
    </form>
  <?php endif; ?>
</div>

<!-- Refunds table -->
<?php if (count($refunds) > 0): ?>
<div class="card">
  <div class="card-header">
    <i class="fas fa-list"></i> Elenco Rimborsi (<?php echo count($refunds); ?>)
  </div>
  <div class="card-body">
    <table class="table table-hover" id="refundsTable">
      <thead class="thead-light">
        <tr>
          <th>Venditore</th>
          <th>Email</th>
          <th>Pratiche</th>
          <th class="text-right">Dovuto</th>
          <th class="text-right">Pagato</th>
          <th>Preferenza</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($refunds as $refund): ?>
          <tr>
            <td>
              <strong><?php echo esc_html($refund->last_name . ' ' . $refund->first_name); ?></strong>
            </td>
            <td><small><?php echo esc_html($refund->email); ?></small></td>
            <td><span class="badge badge-secondary"><?php echo (int)$refund->pratica_count; ?></span></td>
            <td class="text-right">&euro; <?php echo number_format((float)$refund->amount_owed, 2, ',', '.'); ?></td>
            <td class="text-right">
              <?php if ((float)$refund->amount_paid > 0): ?>
                <span class="text-success">&euro; <?php echo number_format((float)$refund->amount_paid, 2, ',', '.'); ?></span>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($refund->payment_preference === 'cash'): ?>
                <span class="badge badge-success"><i class="fas fa-money-bill-alt"></i> Contanti</span>
              <?php elseif ($refund->payment_preference === 'wire_transfer'): ?>
                <span class="badge badge-primary"><i class="fas fa-university"></i> Bonifico</span>
              <?php else: ?>
                <span class="badge badge-warning"><i class="fas fa-question"></i> Non impostata</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $statusBadge = [
                  'pending' => 'badge-warning',
                  'partial' => 'badge-info',
                  'completed' => 'badge-success',
                  'cancelled' => 'badge-secondary'
                ];
                $statusText = [
                  'pending' => 'In attesa',
                  'partial' => 'Parziale',
                  'completed' => 'Completato',
                  'cancelled' => 'Annullato'
                ];
              ?>
              <span class="badge <?php echo $statusBadge[$refund->status] ?? 'badge-secondary'; ?>">
                <?php echo $statusText[$refund->status] ?? $refund->status; ?>
              </span>
            </td>
            <td>
              <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo $refund->id; ?>" class="btn btn-sm btn-primary" title="Dettaglio">
                <i class="fas fa-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i> Nessun record di rimborso trovato per l'anno <?php echo $selectedYear; ?>.
    <?php if (count($sellersWithoutRecords) > 0): ?>
      Ci sono <?php echo count($sellersWithoutRecords); ?> venditori con libri venduti. Usa il pulsante sopra per creare i record.
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
$(document).ready(function() {
  if ($('#refundsTable tbody tr').length > 0) {
    $('#refundsTable').DataTable({
      pageLength: 25,
      order: [[0, 'asc']],
      language: {
        url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Italian.json'
      }
    });
  }
});
</script>
