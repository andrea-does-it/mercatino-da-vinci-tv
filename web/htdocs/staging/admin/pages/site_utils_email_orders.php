<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Tab "Email Ordini" di site_utils: filtra ordini, seleziona, componi con
  // segnaposto e invia (una richiesta AJAX per ordine, con barra di progresso).

  $orderEmailMgr = new OrderEmailManager();
  $emailOrderTemplates = (new EmailTemplateManager())->getAllOrdered();

  // ── Filtri (GET, così la lista è ricaricabile/linkabile) ──
  $filterMode    = isset($_GET['filter_mode']) ? $_GET['filter_mode'] : '';
  $filterStatus  = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'inviata';
  $filterYear    = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : (int)date('Y');
  $filterBook    = isset($_GET['filter_book']) ? trim($_GET['filter_book']) : '';
  $filterSql     = isset($_GET['filter_sql']) ? trim($_GET['filter_sql']) : '';
  $filterNumbers = isset($_GET['filter_numbers']) ? trim($_GET['filter_numbers']) : '';

  $filterError = '';
  $emailOrders = null;   // null = nessuna ricerca ancora eseguita

  if ($filterMode !== '') {
    $ids = array();
    switch ($filterMode) {
      case 'status_year':
        $ids = $orderEmailMgr->findIdsByStatusYear($filterStatus, $filterYear);
        break;
      case 'book':
        if ($filterBook === '') {
          $filterError = 'Inserisci titolo o ISBN del libro.';
        } else {
          $ids = $orderEmailMgr->findIdsByBook($filterBook);
        }
        break;
      case 'sql':
        if ($filterSql === '') {
          $filterError = 'Inserisci una query SELECT che restituisca gli id ordine nella prima colonna.';
        } else {
          $ids = $orderEmailMgr->findIdsBySql($filterSql, $filterError);
        }
        break;
      case 'paste':
        if ($filterNumbers === '') {
          $filterError = 'Incolla almeno un numero di pratica o id ordine.';
        } else {
          $ids = $orderEmailMgr->findIdsByNumbers($filterNumbers);
        }
        break;
      default:
        $filterError = 'Modalità di filtro non valida.';
    }
    if ($filterError === '') {
      $emailOrders = $orderEmailMgr->getOrdersForList($ids);
    }
  }

  $orderStatuses = array('inviata', 'accettata', 'chiusa', 'annullata');
  $yearNow = (int)date('Y');

  // Template come JSON per il dropdown (i campi restano modificabili).
  $tplJson = array();
  foreach ($emailOrderTemplates as $t) {
    $tplJson[] = array(
      'id'      => (int)$t->id,
      'name'    => $t->name,
      'subject' => $t->subject,
      'body'    => $t->body,
    );
  }
?>

<p class="text-muted">
  Filtra gli ordini (pratiche), seleziona i destinatari, componi l'email con i segnaposti
  e invia: <strong>una email per ogni ordine selezionato</strong>, al venditore della pratica.
</p>

<!-- ── Card filtri ── -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-filter mr-1"></i> Filtro ordini</div>
  <div class="card-body">
    <?php if ($filterError): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo esc_html($filterError); ?></div>
    <?php endif; ?>
    <form method="get" action="<?php echo ROOT_URL; ?>admin/">
      <input type="hidden" name="page" value="site_utils">
      <input type="hidden" name="tab" value="email_orders">

      <div class="form-group">
        <label for="filter_mode">Modalit&agrave;</label>
        <select name="filter_mode" id="filter_mode" class="form-control" onchange="eoToggleFilterFields()">
          <option value="status_year" <?php echo $filterMode === 'status_year' || $filterMode === '' ? 'selected' : ''; ?>>Stato + anno</option>
          <option value="book" <?php echo $filterMode === 'book' ? 'selected' : ''; ?>>Ordini che contengono un libro</option>
          <option value="paste" <?php echo $filterMode === 'paste' ? 'selected' : ''; ?>>Elenco pratiche / id ordine</option>
          <option value="sql" <?php echo $filterMode === 'sql' ? 'selected' : ''; ?>>Query SQL personalizzata</option>
        </select>
      </div>

      <div id="eo-filter-status_year" class="form-row eo-filter-fields">
        <div class="form-group col-md-4">
          <label for="filter_status">Stato pratica</label>
          <select name="filter_status" id="filter_status" class="form-control">
            <option value="">Tutti (tranne eliminate)</option>
            <?php foreach ($orderStatuses as $st): ?>
              <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>>
                <?php echo $st; ?><?php echo $st === 'inviata' ? ' (non ancora consegnata)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label for="filter_year">Anno</label>
          <select name="filter_year" id="filter_year" class="form-control">
            <option value="0" <?php echo $filterYear === 0 ? 'selected' : ''; ?>>Tutti</option>
            <?php for ($y = $yearNow; $y >= $yearNow - 5; $y--): ?>
              <option value="<?php echo $y; ?>" <?php echo $filterYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>

      <div id="eo-filter-book" class="form-group eo-filter-fields" style="display:none;">
        <label for="filter_book">Titolo o ISBN del libro</label>
        <input type="text" name="filter_book" id="filter_book" class="form-control"
               value="<?php echo esc_html($filterBook); ?>" placeholder="Es. Matematica.blu oppure 9788808...">
        <small class="form-text text-muted">Trova gli ordini con almeno un libro corrispondente (esclusi i libri eliminati dalla pratica).</small>
      </div>

      <div id="eo-filter-paste" class="form-group eo-filter-fields" style="display:none;">
        <label for="filter_numbers">Numeri pratica o id ordine</label>
        <textarea name="filter_numbers" id="filter_numbers" class="form-control" rows="3"
                  placeholder="Es. 101, 102, 103 (separati da virgola, spazio o a-capo)"><?php echo esc_html($filterNumbers); ?></textarea>
        <small class="form-text text-muted">Ogni numero viene cercato prima come numero pratica, poi come id ordine.</small>
      </div>

      <div id="eo-filter-sql" class="form-group eo-filter-fields" style="display:none;">
        <label for="filter_sql">Query SELECT</label>
        <textarea name="filter_sql" id="filter_sql" class="form-control" rows="4"
                  style="font-family: monospace; font-size: 0.9em;"
                  placeholder="SELECT o.id FROM orders o WHERE ..."><?php echo esc_html($filterSql); ?></textarea>
        <small class="form-text text-muted">Solo SELECT; la <strong>prima colonna</strong> deve contenere gli id della tabella <code>orders</code>. Gli id inesistenti vengono ignorati.</small>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Carica ordini</button>
    </form>
  </div>
</div>

<?php if ($emailOrders !== null && empty($emailOrders)): ?>
  <div class="alert alert-info"><i class="fas fa-info-circle"></i> Nessun ordine trovato con i filtri selezionati.</div>
<?php endif; ?>

<?php if (!empty($emailOrders)): ?>

<!-- ── Card ordini trovati ── -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-inbox mr-1"></i> Ordini trovati (<?php echo count($emailOrders); ?>)</span>
    <span class="text-muted" id="eoSelectedCount">0 selezionati</span>
  </div>
  <div class="card-body py-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="eoSelectAll(true)">
      <i class="fas fa-check-square"></i> Seleziona tutti
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="eoSelectNeverEmailed()">
      <i class="fas fa-envelope-open"></i> Seleziona mai contattati
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="eoSelectAll(false)">
      <i class="fas fa-square"></i> Deseleziona
    </button>
  </div>
  <div class="card-body p-0" style="overflow-x: auto;">
    <table class="table table-hover table-sm mb-0" id="eoOrdersTable">
      <thead class="thead-light">
        <tr>
          <th style="width: 40px;"><input type="checkbox" id="eoSelectAllCheckbox" onchange="eoSelectAll(this.checked)"></th>
          <th>Pratica</th>
          <th>Venditore</th>
          <th>Email</th>
          <th>Stato</th>
          <th>Libri</th>
          <th>Ultima email</th>
          <th style="width: 60px;">Esito</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($emailOrders as $eo): ?>
          <tr id="eo-row-<?php echo (int)$eo->id; ?>">
            <td>
              <input type="checkbox" class="eo-order-checkbox" value="<?php echo (int)$eo->id; ?>"
                     data-emailed="<?php echo $eo->last_email_at ? '1' : '0'; ?>"
                     onchange="eoUpdateSelectedCount()">
            </td>
            <td><strong><?php echo (int)$eo->numPratica > 0 ? (int)$eo->numPratica : '-'; ?></strong></td>
            <td><?php echo esc_html($eo->last_name . ' ' . $eo->first_name); ?></td>
            <td><small><?php echo esc_html($eo->email); ?></small></td>
            <td><span class="badge badge-secondary"><?php echo esc_html($eo->status); ?></span></td>
            <td><?php echo (int)$eo->num_libri; ?></td>
            <td>
              <?php if ($eo->last_email_at): ?>
                <span class="badge badge-warning" title="Oggetto: <?php echo esc_html($eo->last_email_subject); ?>">
                  <i class="fas fa-envelope"></i> <?php echo date('d/m/Y H:i', strtotime($eo->last_email_at)); ?>
                </span>
              <?php else: ?>
                <small class="text-muted">mai</small>
              <?php endif; ?>
            </td>
            <td class="eo-result"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Card composizione ── -->
<div class="card mb-4">
  <div class="card-header"><i class="fas fa-pen mr-1"></i> Componi email</div>
  <div class="card-body">
    <div class="form-group">
      <label for="eoTemplateSelect">Template</label>
      <div class="input-group">
        <select id="eoTemplateSelect" class="form-control" onchange="eoApplyTemplate()">
          <option value="0">— Nessun template (scrivi a mano) —</option>
          <?php foreach ($emailOrderTemplates as $t): ?>
            <option value="<?php echo (int)$t->id; ?>"><?php echo esc_html($t->name); ?></option>
          <?php endforeach; ?>
        </select>
        <div class="input-group-append">
          <a href="<?php echo ROOT_URL; ?>admin/?page=site_utils&tab=email_templates" class="btn btn-outline-secondary">
            <i class="fas fa-cog"></i> Gestisci template
          </a>
        </div>
      </div>
      <small class="form-text text-muted">Selezionando un template, oggetto e corpo vengono precompilati ma restano modificabili.</small>
    </div>

    <div class="form-group">
      <label for="eoSubject">Oggetto <span class="text-danger">*</span></label>
      <input type="text" id="eoSubject" class="form-control" placeholder="Es. Mercatino: pratica {num_pratica}">
    </div>

    <div class="form-group">
      <label for="eoBody">Corpo (testo semplice) <span class="text-danger">*</span></label>
      <textarea id="eoBody" class="form-control" rows="10" placeholder="Ciao {nome},&#10;..."></textarea>
    </div>

    <details class="mb-3">
      <summary class="text-muted" style="cursor:pointer;"><i class="fas fa-tags"></i> Segnaposto disponibili</summary>
      <table class="table table-sm mt-2 mb-0">
        <tbody>
          <?php foreach (OrderEmailManager::PLACEHOLDERS as $ph => $desc): ?>
            <tr>
              <td style="width: 140px;"><code><?php echo esc_html($ph); ?></code></td>
              <td><small><?php echo esc_html($desc); ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>

    <button type="button" class="btn btn-outline-info" id="eoPreviewBtn" onclick="eoPreview()">
      <i class="fas fa-eye"></i> Anteprima (primo selezionato)
    </button>
    <button type="button" class="btn btn-primary" id="eoSendBtn" onclick="eoSendSelected()">
      <i class="fas fa-paper-plane"></i> Invia alle selezionate
    </button>

    <!-- Progresso invio -->
    <div id="eoProgressWrap" class="mt-3" style="display:none;">
      <div class="progress" style="height: 24px;">
        <div id="eoProgressBar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
      </div>
      <div class="mt-2" id="eoProgressText"></div>
      <div class="mt-2 text-danger" id="eoProgressErrors" style="white-space: pre-wrap; font-size: 0.85em;"></div>
    </div>
  </div>
</div>

<!-- ── Modal anteprima ── -->
<div class="modal fade" id="eoPreviewModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Anteprima email</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong>A:</strong> <span id="eoPreviewTo"></span></div>
        <div class="mb-2"><strong>Oggetto:</strong> <span id="eoPreviewSubject"></span></div>
        <hr>
        <div class="bg-light p-3" id="eoPreviewBody" style="white-space: pre-wrap; font-size: 0.9rem;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Chiudi</button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
// Dati per il tab Email Ordini (prefisso eo- per evitare collisioni con gli altri tab).
var eoTemplates = <?php echo json_encode($tplJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var eoCsrfToken = '<?php echo CSRF::getTokenForAjax(); ?>';
var eoEndpoint = '<?php echo ROOT_URL; ?>api/admin/send-order-email.php';
var eoSending = false;

function eoToggleFilterFields() {
  var mode = document.getElementById('filter_mode').value;
  document.querySelectorAll('.eo-filter-fields').forEach(function(el) {
    el.style.display = 'none';
  });
  var active = document.getElementById('eo-filter-' + mode);
  if (active) {
    active.style.display = '';
  }
}

function eoCheckboxes() {
  return Array.prototype.slice.call(document.querySelectorAll('.eo-order-checkbox'));
}

function eoSelectAll(checked) {
  eoCheckboxes().forEach(function(cb) { cb.checked = checked; });
  var master = document.getElementById('eoSelectAllCheckbox');
  if (master) master.checked = checked;
  eoUpdateSelectedCount();
}

function eoSelectNeverEmailed() {
  eoCheckboxes().forEach(function(cb) { cb.checked = cb.dataset.emailed === '0'; });
  eoUpdateSelectedCount();
}

function eoUpdateSelectedCount() {
  var count = eoCheckboxes().filter(function(cb) { return cb.checked; }).length;
  var el = document.getElementById('eoSelectedCount');
  if (el) el.textContent = count + ' selezionati';
}

function eoApplyTemplate() {
  var id = parseInt(document.getElementById('eoTemplateSelect').value, 10);
  if (!id) return;
  for (var i = 0; i < eoTemplates.length; i++) {
    if (eoTemplates[i].id === id) {
      document.getElementById('eoSubject').value = eoTemplates[i].subject;
      document.getElementById('eoBody').value = eoTemplates[i].body;
      return;
    }
  }
}

function eoComposeData(orderId, preview) {
  return {
    order_id: orderId,
    subject: document.getElementById('eoSubject').value,
    body: document.getElementById('eoBody').value,
    template_id: document.getElementById('eoTemplateSelect').value,
    csrf_token: eoCsrfToken,
    preview: preview ? 1 : 0
  };
}

function eoValidateCompose() {
  if (document.getElementById('eoSubject').value.trim() === '' ||
      document.getElementById('eoBody').value.trim() === '') {
    alert('Compila oggetto e corpo prima di continuare.');
    return false;
  }
  return true;
}

function eoSelectedIds() {
  return eoCheckboxes().filter(function(cb) { return cb.checked; })
                       .map(function(cb) { return parseInt(cb.value, 10); });
}

function eoPreview() {
  if (!eoValidateCompose()) return;
  var ids = eoSelectedIds();
  if (ids.length === 0) {
    alert('Seleziona almeno un ordine per vedere l\'anteprima.');
    return;
  }
  $.post(eoEndpoint, eoComposeData(ids[0], true))
    .done(function(resp) {
      if (resp && resp.ok) {
        $('#eoPreviewTo').text(resp.to);
        $('#eoPreviewSubject').text(resp.subject);
        $('#eoPreviewBody').text(resp.body);
        $('#eoPreviewModal').modal('show');
      } else {
        alert('Errore anteprima: ' + (resp && resp.error ? resp.error : 'risposta non valida'));
      }
    })
    .fail(function() {
      alert('Errore di rete durante l\'anteprima.');
    });
}

function eoSendSelected() {
  if (eoSending) return;
  if (!eoValidateCompose()) return;
  var ids = eoSelectedIds();
  if (ids.length === 0) {
    alert('Seleziona almeno un ordine.');
    return;
  }
  var alreadyEmailed = eoCheckboxes().filter(function(cb) {
    return cb.checked && cb.dataset.emailed === '1';
  }).length;
  var msg = 'Inviare ' + ids.length + ' email (una per ordine selezionato)?';
  if (alreadyEmailed > 0) {
    msg += '\n\nAttenzione: ' + alreadyEmailed + ' di questi ordini hanno già ricevuto un\'email.';
  }
  if (!confirm(msg)) return;

  eoSending = true;
  document.getElementById('eoSendBtn').disabled = true;
  document.getElementById('eoPreviewBtn').disabled = true;
  document.getElementById('eoProgressWrap').style.display = '';
  document.getElementById('eoProgressErrors').textContent = '';

  var sent = 0, failed = 0;
  var errors = [];

  function eoUpdateProgress() {
    var done = sent + failed;
    var pct = Math.round(done * 100 / ids.length);
    var bar = document.getElementById('eoProgressBar');
    bar.style.width = pct + '%';
    bar.textContent = pct + '%';
    bar.className = 'progress-bar' + (failed > 0 ? ' bg-warning' : ' bg-success');
    document.getElementById('eoProgressText').textContent =
      done + '/' + ids.length + ' elaborate — ' + sent + ' inviate, ' + failed + ' errori';
    document.getElementById('eoProgressErrors').textContent = errors.join('\n');
  }

  function eoMarkRow(orderId, ok, error) {
    var row = document.getElementById('eo-row-' + orderId);
    if (!row) return;
    var cell = row.querySelector('.eo-result');
    // Costruzione via DOM: il testo dell'errore (es. messaggio SMTP) non deve
    // essere concatenato come HTML.
    var badge = document.createElement('span');
    badge.className = ok ? 'badge badge-success' : 'badge badge-danger';
    if (!ok) badge.title = String(error || '');
    var icon = document.createElement('i');
    icon.className = ok ? 'fas fa-check' : 'fas fa-times';
    badge.appendChild(icon);
    cell.innerHTML = '';
    cell.appendChild(badge);
  }

  function eoSendNext(i) {
    if (i >= ids.length) {
      eoSending = false;
      document.getElementById('eoSendBtn').disabled = false;
      document.getElementById('eoPreviewBtn').disabled = false;
      return;
    }
    $.post(eoEndpoint, eoComposeData(ids[i], false))
      .done(function(resp) {
        if (resp && resp.ok) {
          sent++;
          eoMarkRow(ids[i], true);
        } else {
          failed++;
          var err = resp && resp.error ? resp.error : 'risposta non valida';
          errors.push('Ordine ' + ids[i] + ': ' + err);
          eoMarkRow(ids[i], false, err);
        }
      })
      .fail(function() {
        failed++;
        errors.push('Ordine ' + ids[i] + ': errore di rete');
        eoMarkRow(ids[i], false, 'errore di rete');
      })
      .always(function() {
        eoUpdateProgress();
        eoSendNext(i + 1);
      });
  }

  eoUpdateProgress();
  eoSendNext(0);
}

// Mostra i campi filtro corretti al caricamento (anche dopo un submit).
eoToggleFilterFields();

// DataTable per ricerca/ordinamento se la lista è lunga. paging:false è
// necessario: con la paginazione le righe non visibili escono dal DOM e le
// checkbox selezionate andrebbero perse dal loop di invio.
$(document).ready(function() {
  if ($('#eoOrdersTable tbody tr').length > 10) {
    $('#eoOrdersTable').DataTable({
      paging: false,
      order: [[1, 'asc']],
      columnDefs: [
        { orderable: false, targets: [0, 7] }
      ],
      language: {
        url: '//cdn.datatables.net/plug-ins/1.10.25/i18n/Italian.json'
      }
    });
  }
});
</script>
