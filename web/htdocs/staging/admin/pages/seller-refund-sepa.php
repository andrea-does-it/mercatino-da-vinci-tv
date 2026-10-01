<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;
  global $alertMsg;

  $sepaMgr = new SepaBatchManager();
  $currentYear = (int)date('Y');
  $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
  $pageUrl = ROOT_URL . 'admin/?page=seller-refund-sepa&year=' . $selectedYear;

  $installed = $sepaMgr->isInstalled();
  $errorText = '';
  $infoText = '';
  $warningText = '';
  $csrfFailed = false;

  if ($installed && isset($_POST['action'])) {
    if (!CSRF::validateToken()) {
      $csrfFailed = true;
    } else {
      try {
        switch ($_POST['action']) {
          case 'save_debtor':
            $errors = $sepaMgr->saveDebtorSettings([
              'name' => $_POST['debtor_name'] ?? '',
              'iban' => $_POST['debtor_iban'] ?? '',
              'cuc' => $_POST['debtor_cuc'] ?? '',
              'country' => $_POST['debtor_country'] ?? 'IT',
              'town' => $_POST['debtor_town'] ?? '',
              'default_creditor_town' => $_POST['default_creditor_town'] ?? '',
              'category_purpose' => $_POST['category_purpose'] ?? '',
            ]);
            if ($errors) {
              $errorText = implode(' ', $errors);
            } else {
              log_activity($loggedInUser->id, 'admin_sepa_debtor_updated', 'dati ordinante aggiornati');
              $infoText = 'Dati ordinante salvati.';
            }
            break;

          case 'mark_paid':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $res = $sepaMgr->markBatchPaid($batchId, (string)($_POST['payment_date'] ?? ''), (int)$loggedInUser->id);
            $paidBatch = $sepaMgr->getBatch($batchId);
            $paidBatchTotal = number_format($paidBatch ? (float)$paidBatch->total_amount : 0, 2, '.', '');
            log_activity($loggedInUser->id, 'admin_sepa_batch_paid',
              'batch: ' . $batchId . ', pagati: ' . $res['paid'] . ', saltati: ' . $res['skipped']
              . ', totale: ' . $paidBatchTotal);
            $infoText = 'Distinta #' . $batchId . ' segnata come pagata: ' . $res['paid'] . ' pagamenti registrati'
              . ($res['skipped'] ? ', ' . $res['skipped'] . ' saltati (in una distinta più recente o già saldati).' : '.');
            if (!empty($res['skipped_settled'])) {
              $warningText = $res['skipped_settled'] . ' rimborsi erano già stati saldati a mano e vanno controllati'
                . ' con l\'estratto conto (possibile doppio pagamento).';
            }
            break;

          case 'discard':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $sepaMgr->discardBatch($batchId, (int)$loggedInUser->id);
            $discardedBatch = $sepaMgr->getBatch($batchId);
            $discardedBatchTotal = number_format($discardedBatch ? (float)$discardedBatch->total_amount : 0, 2, '.', '');
            log_activity($loggedInUser->id, 'admin_sepa_batch_discarded', 'batch: ' . $batchId . ', totale: ' . $discardedBatchTotal);
            $infoText = 'Distinta #' . $batchId . ' scartata.';
            break;
        }
      } catch (SepaException $e) {
        $errorText = $e->getMessage();
      }
    }
  }

  if (isset($_GET['msg']) && $_GET['msg'] === 'sepa_error' && !empty($_SESSION['sepa_error'])) {
    $errorText = $_SESSION['sepa_error'];
    unset($_SESSION['sepa_error']);
  }
  if (isset($_GET['msg']) && $_GET['msg'] === 'sepa_generated') {
    $infoText = 'Distinta generata e scaricata. Caricala su UniCredit e, quando i bonifici sono eseguiti, usa "Segna come pagati".';
  }

  $debtor = $installed ? $sepaMgr->getDebtorSettings() : null;
  $debtorProblems = $installed ? $sepaMgr->debtorProblems($debtor) : [];
  $candidates = $installed ? $sepaMgr->getCandidates($selectedYear) : ['eligible' => [], 'excluded' => []];
  $batches = $installed ? $sepaMgr->getBatchesForYear($selectedYear) : [];
  $defaultExecDate = SepaCbiExport::nextBusinessDay(new DateTime());

  $statusText = ['pending' => 'In attesa', 'partial' => 'Parziale', 'xmlsaved' => 'Distinta generata'];
  $statusBadge = ['pending' => 'badge-warning', 'partial' => 'badge-info', 'xmlsaved' => 'badge-primary'];
  $batchStatusText = ['generated' => 'Generata', 'paid' => 'Pagata', 'discarded' => 'Scartata'];
  $batchStatusBadge = ['generated' => 'badge-primary', 'paid' => 'badge-success', 'discarded' => 'badge-secondary'];
  $euro = function ($v) { return '&euro; ' . number_format((float)$v, 2, ',', '.'); };
?>

<h1>Distinte SEPA - Rimborsi <?php echo $selectedYear; ?></h1>

<a href="<?php echo ROOT_URL; ?>admin/?page=seller-refunds&year=<?php echo $selectedYear; ?>" class="btn btn-secondary mb-3">
  <i class="fas fa-arrow-left"></i> Torna ai rimborsi
</a>

<form method="get" class="form-inline mb-3">
  <input type="hidden" name="page" value="seller-refund-sepa">
  <label class="mr-2" for="year">Anno</label>
  <select name="year" id="year" class="form-control" onchange="this.form.submit()">
    <?php for ($y = $currentYear; $y >= $currentYear - 3; $y--): ?>
      <option value="<?php echo $y; ?>" <?php echo $y === $selectedYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
    <?php endfor; ?>
  </select>
</form>

<?php if ($csrfFailed): ?>
  <div class="alert alert-danger">Sessione scaduta o richiesta non valida: ricarica la pagina e riprova.</div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
  <div class="alert alert-danger"><?php echo esc_html($errorText); ?></div>
<?php endif; ?>
<?php if ($infoText !== ''): ?>
  <div class="alert alert-success"><?php echo esc_html($infoText); ?></div>
<?php endif; ?>
<?php if ($warningText !== ''): ?>
  <div class="alert alert-warning"><?php echo esc_html($warningText); ?></div>
<?php endif; ?>

<?php if (!$installed): ?>
  <div class="alert alert-warning">
    Le tabelle delle distinte non esistono su questo database (o manca la colonna della
    località): vanno applicate a mano le migrazioni
    <code>sql/202610010001_sepa_distinte.sql</code> e <code>sql/202610020001_sepa_localita.sql</code>.
  </div>
<?php else: ?>

<!-- Dati ordinante -->
<div class="card mb-4">
  <div class="card-header" data-toggle="collapse" data-target="#debtorBox" style="cursor:pointer;">
    <i class="fas fa-university"></i> Dati ordinante (conto del Comitato)
    <?php if ($debtorProblems): ?>
      <span class="badge badge-danger ml-2">Da completare</span>
    <?php else: ?>
      <span class="ml-2 text-muted"><?php echo esc_html($debtor['name'] . ' - ' . SepaCbiExport::ibanMask($debtor['iban'])); ?></span>
    <?php endif; ?>
  </div>
  <div id="debtorBox" class="collapse <?php echo $debtorProblems ? 'show' : ''; ?>">
    <div class="card-body">
      <?php if ($debtorProblems): ?>
        <div class="alert alert-danger">
          Prima di generare una distinta completa questi dati:
          <ul class="mb-0"><?php foreach ($debtorProblems as $p): ?><li><?php echo esc_html($p); ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_html($pageUrl); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_debtor">
        <div class="form-row">
          <div class="form-group col-md-5">
            <label for="debtor_name">Intestatario</label>
            <input type="text" class="form-control" id="debtor_name" name="debtor_name" maxlength="70" value="<?php echo esc_html($debtor['name']); ?>">
          </div>
          <div class="form-group col-md-4">
            <label for="debtor_iban">IBAN</label>
            <input type="text" class="form-control" id="debtor_iban" name="debtor_iban" maxlength="34" value="<?php echo esc_html($debtor['iban']); ?>">
          </div>
          <div class="form-group col-md-2">
            <label for="debtor_cuc">CUC (facoltativo)</label>
            <input type="text" class="form-control" id="debtor_cuc" name="debtor_cuc" maxlength="8" value="<?php echo esc_html($debtor['cuc']); ?>">
          </div>
          <div class="form-group col-md-1">
            <label for="debtor_country">Paese</label>
            <input type="text" class="form-control" id="debtor_country" name="debtor_country" maxlength="2" value="<?php echo esc_html($debtor['country']); ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label for="debtor_town">Località</label>
            <input type="text" class="form-control" id="debtor_town" name="debtor_town" maxlength="35" value="<?php echo esc_html($debtor['town']); ?>">
          </div>
          <div class="form-group col-md-6">
            <label for="default_creditor_town">Località predefinita beneficiari</label>
            <input type="text" class="form-control" id="default_creditor_town" name="default_creditor_town" maxlength="35" value="<?php echo esc_html($debtor['default_creditor_town']); ?>">
            <small class="form-text text-muted">usata per i venditori senza località; puoi impostarla per singolo venditore nella pagina del rimborso</small>
          </div>
          <div class="form-group col-md-3">
            <label for="category_purpose">Category Purpose</label>
            <input type="text" class="form-control" id="category_purpose" name="category_purpose" maxlength="4" value="<?php echo esc_html($debtor['category_purpose']); ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salva dati ordinante</button>
      </form>
    </div>
  </div>
</div>

<!-- Nuova distinta -->
<div class="card mb-4">
  <div class="card-header bg-primary text-white"><i class="fas fa-file-code"></i> Nuova distinta</div>
  <div class="card-body">
    <?php if (!$candidates['eligible']): ?>
      <p class="mb-0">Nessun rimborso con bonifico da pagare per il <?php echo $selectedYear; ?>.</p>
    <?php else: ?>
    <form method="post" action="<?php echo ROOT_URL; ?>api/admin/sepa-batch-download.php" id="sepaForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
      <input type="hidden" name="download_token" id="download_token" value="">
      <div class="form-row">
        <div class="form-group col-md-3">
          <label for="execution_date">Data esecuzione</label>
          <input type="date" class="form-control" id="execution_date" name="execution_date"
                 min="<?php echo date('Y-m-d'); ?>" value="<?php echo $defaultExecDate; ?>" required>
        </div>
        <div class="form-group col-md-9">
          <label for="template">Causale <small class="text-muted">(segnaposto {anno} e {pratiche}; max 140 caratteri)</small></label>
          <input type="text" class="form-control" id="template" name="template" maxlength="140" value="<?php echo esc_html($debtor['template']); ?>">
        </div>
      </div>

      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th><input type="checkbox" id="checkAll" title="Seleziona tutti"></th>
            <th>Venditore</th><th>Pratiche</th><th>Beneficiario</th><th>IBAN</th>
            <th class="text-right">Importo</th><th>Stato</th><th>Causale</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($candidates['eligible'] as $c): ?>
          <tr>
            <td><input type="checkbox" class="refund-cb" name="refund_ids[]" value="<?php echo (int)$c->id; ?>"
                       data-amount="<?php echo number_format($c->due, 2, '.', ''); ?>"
                       <?php echo $c->last_batch_id !== null ? 'data-in-batch="1"' : ''; ?>
                       <?php echo $c->last_batch_id === null ? 'checked' : ''; ?>></td>
            <td>
              <a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo (int)$c->id; ?>">
                <?php echo esc_html($c->last_name . ' ' . $c->first_name); ?>
              </a>
              <?php if ($c->last_batch_id !== null): ?>
                <br><small class="text-muted">in distinta #<?php echo (int)$c->last_batch_id; ?>
                  <?php echo $c->last_batch_date ? 'del ' . date('d/m', strtotime($c->last_batch_date)) : ''; ?></small>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html($c->pratica_numbers); ?></td>
            <td><?php echo esc_html($c->beneficiary_name); ?>
              <br><small class="text-muted"><?php echo esc_html($c->beneficiary_town); ?><?php echo $c->town_is_default ? ' (predefinita)' : ''; ?></small>
            </td>
            <td><code><?php echo esc_html($c->iban_masked); ?></code></td>
            <td class="text-right"><?php echo $euro($c->due); ?></td>
            <td><span class="badge <?php echo $statusBadge[$c->status] ?? 'badge-secondary'; ?>"><?php echo $statusText[$c->status] ?? esc_html($c->status); ?></span></td>
            <td class="remittance-preview" data-year="<?php echo $selectedYear; ?>" data-praticas="<?php echo esc_html($c->pratica_numbers); ?>"><small></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <p class="lead" id="sepaTotals"></p>
      <button type="submit" class="btn btn-success" id="sepaSubmit" <?php echo $debtorProblems ? 'disabled' : ''; ?>>
        <i class="fas fa-download"></i> Genera distinta XML
      </button>
      <?php if ($debtorProblems): ?><small class="text-danger ml-2">Completa prima i dati ordinante.</small><?php endif; ?>
      <small id="sepaDownloadHint" class="text-muted ml-2 d-none">Se il download non è partito, ricarica la pagina e controlla lo storico prima di rigenerare.</small>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Esclusi -->
<?php if ($candidates['excluded']): ?>
<div class="card mb-4">
  <div class="card-header bg-warning"><i class="fas fa-exclamation-triangle"></i> Bonifici non includibili (<?php echo count($candidates['excluded']); ?>)</div>
  <div class="card-body">
    <table class="table table-sm">
      <thead><tr><th>Venditore</th><th>Pratiche</th><th>IBAN</th><th class="text-right">Importo</th><th>Motivo</th></tr></thead>
      <tbody>
      <?php foreach ($candidates['excluded'] as $c): ?>
        <tr>
          <td><a href="<?php echo ROOT_URL; ?>admin/?page=seller-refund-view&id=<?php echo (int)$c->id; ?>"><?php echo esc_html($c->last_name . ' ' . $c->first_name); ?></a></td>
          <td><?php echo esc_html($c->pratica_numbers); ?></td>
          <td><code><?php echo esc_html($c->iban_masked); ?></code></td>
          <td class="text-right"><?php echo $euro($c->due); ?></td>
          <td><?php echo esc_html($c->reason); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Storico -->
<div class="card mb-4">
  <div class="card-header bg-dark text-white"><i class="fas fa-history"></i> Storico distinte <?php echo $selectedYear; ?></div>
  <div class="card-body">
    <?php if (!$batches): ?>
      <p class="mb-0">Nessuna distinta generata per il <?php echo $selectedYear; ?>.</p>
    <?php else: ?>
    <table class="table table-sm">
      <thead><tr><th>#</th><th>Generata</th><th>Esecuzione</th><th class="text-right">Bonifici</th><th class="text-right">Totale</th><th>Stato</th><th>Admin</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($batches as $b): ?>
        <tr>
          <td><a href="#" data-toggle="collapse" data-target="#items<?php echo (int)$b->id; ?>">#<?php echo (int)$b->id; ?></a></td>
          <td><?php echo date('d/m/Y H:i', strtotime($b->created_at)); ?></td>
          <td><?php echo date('d/m/Y', strtotime($b->execution_date)); ?></td>
          <td class="text-right"><?php echo (int)$b->tx_count; ?></td>
          <td class="text-right"><?php echo $euro($b->total_amount); ?></td>
          <td><span class="badge <?php echo $batchStatusBadge[$b->status]; ?>"><?php echo $batchStatusText[$b->status]; ?></span>
            <?php if ($b->status === 'paid' && $b->paid_at): ?><br><small><?php echo date('d/m/Y', strtotime($b->paid_at)); ?></small><?php endif; ?></td>
          <td><?php echo esc_html((string)$b->created_by_name); ?></td>
          <td class="text-nowrap">
            <?php if ($b->status === 'generated'): ?>
              <form method="post" action="<?php echo esc_html($pageUrl); ?>" class="form-inline d-inline"
                    onsubmit="return confirm('Registrare come pagati i bonifici della distinta #<?php echo (int)$b->id; ?>?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="batch_id" value="<?php echo (int)$b->id; ?>">
                <input type="date" name="payment_date" class="form-control form-control-sm mr-1" value="<?php echo esc_html($b->execution_date); ?>" required>
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Segna come pagati</button>
              </form>
              <form method="post" action="<?php echo esc_html($pageUrl); ?>" class="d-inline"
                    onsubmit="return confirm('Scartare la distinta #<?php echo (int)$b->id; ?>? Usalo solo se il file non è stato caricato o è stato rifiutato.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="discard">
                <input type="hidden" name="batch_id" value="<?php echo (int)$b->id; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> Scarta</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <tr class="collapse" id="items<?php echo (int)$b->id; ?>">
          <td colspan="8">
            <table class="table table-sm table-bordered mb-0">
              <thead><tr><th>Venditore</th><th>Beneficiario</th><th>IBAN</th><th class="text-right">Importo</th><th>Causale</th><th>EndToEndId</th></tr></thead>
              <tbody>
              <?php foreach ($sepaMgr->getBatchItems($b->id) as $it): ?>
                <tr class="<?php echo $it->superseded ? 'text-muted' : ''; ?>">
                  <td><?php echo esc_html($it->last_name . ' ' . $it->first_name); ?>
                    <?php if ($it->superseded): ?><span class="badge badge-light">superata</span><?php endif; ?></td>
                  <td><?php echo esc_html($it->beneficiary_name); ?></td>
                  <td><code><?php echo esc_html($it->iban_masked); ?></code></td>
                  <td class="text-right"><?php echo $euro($it->amount); ?></td>
                  <td><small><?php echo esc_html($it->remittance); ?></small></td>
                  <td><small><?php echo esc_html($it->end_to_end_id); ?></small></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('sepaForm');
  if (!form) { return; }
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.refund-cb'));
  var totals = document.getElementById('sepaTotals');
  var submit = document.getElementById('sepaSubmit');
  var tpl = document.getElementById('template');
  var debtorBlocked = submit.disabled;

  function fmt(n) { return n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function refresh() {
    var n = 0, sum = 0;
    boxes.forEach(function (b) { if (b.checked) { n++; sum += Math.round(parseFloat(b.dataset.amount) * 100); } });
    totals.textContent = n + ' bonifici selezionati – € ' + fmt(sum / 100);
    submit.disabled = debtorBlocked || n === 0;
  }

  // Anteprima indicativa (lato server si applicano traslitterazione e taglio a 140).
  // replaceAll via split/join: deve sostituire tutte le occorrenze, come lo
  // str_replace() lato server (SepaCbiExport::buildRemittance).
  function replaceAll(str, search, replacement) {
    return str.split(search).join(replacement);
  }
  function previews() {
    document.querySelectorAll('.remittance-preview').forEach(function (td) {
      var text = replaceAll(tpl.value, '{anno}', td.dataset.year);
      text = replaceAll(text, '{pratiche}', td.dataset.praticas);
      td.firstElementChild.textContent = text.slice(0, 140);
    });
  }

  boxes.forEach(function (b) { b.addEventListener('change', refresh); });
  document.getElementById('checkAll').addEventListener('change', function () {
    var on = this.checked;
    // I rimborsi già in una distinta si spuntano/tolgono solo uno per uno, mai da "Seleziona tutti".
    boxes.forEach(function (b) { if (b.dataset.inBatch !== '1') { b.checked = on; } });
    refresh();
  });
  tpl.addEventListener('input', previews);

  // Il download non ricarica la pagina: l'endpoint imposta il cookie sepa_dl
  // quando il file è pronto, e allora ricarichiamo per mostrare stati e storico.
  form.addEventListener('submit', function (e) {
    var inBatchSelected = boxes.some(function (b) { return b.checked && b.dataset.inBatch === '1'; });
    if (inBatchSelected && !window.confirm('Alcuni rimborsi selezionati sono già in una distinta: se anche quella è stata caricata in banca verranno pagati due volte. Continuare?')) {
      e.preventDefault();
      return;
    }
    var token = String(Date.now());
    document.getElementById('download_token').value = token;
    submit.disabled = true;
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (document.cookie.indexOf('sepa_dl=' + token) !== -1) {
        clearInterval(timer);
        document.cookie = 'sepa_dl=; Max-Age=0; path=/';
        window.location.href = '<?php echo $pageUrl; ?>&msg=sepa_generated';
      } else if (tries > 60) {
        clearInterval(timer);
        var hint = document.getElementById('sepaDownloadHint');
        if (hint) { hint.classList.remove('d-none'); }
      }
    }, 500);
  });

  refresh();
  previews();
})();
</script>

<?php endif; ?>
