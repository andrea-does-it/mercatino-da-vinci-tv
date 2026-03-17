<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  global $loggedInUser;

  // Ensure only admins can access this page
  if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
    echo "<script>location.href='".ROOT_URL."admin/?page=dashboard&msg=forbidden';</script>";
    exit;
  }

  // ── Determine active tab ────────────────────────────────────────────────────
  $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'email';
  if (!in_array($activeTab, ['email', 'sql', 'settings'], true)) {
    $activeTab = 'email';
  }

  // ── EMAIL HANDLER ───────────────────────────────────────────────────────────
  $emailSent = false;
  $emailError = '';
  $to = '';
  $subject = '';
  $body = '';

  if (isset($_POST['send_test_email'])) {
    $activeTab = 'email';
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $to = isset($_POST['to']) ? trim($_POST['to']) : '';
      $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
      $body = isset($_POST['body']) ? $_POST['body'] : '';

      if ($to === '' || $subject === '') {
        $emailError = 'Destinatario e Oggetto sono obbligatori.';
      } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $emailError = 'Indirizzo email destinatario non valido.';
      } else {
        $htmlBody = nl2br(htmlspecialchars($body));
        $smtpError = '';
        $result = send_mail($to, $subject, $htmlBody, $smtpError, true);

        if ($result) {
          $emailSent = true;
        } else {
          $emailError = $smtpError;
        }
      }
    }
  }

  // ── SETTINGS HANDLER ────────────────────────────────────────────────────────
  $settingsSaved = false;
  $settingsError = '';

  if (isset($_POST['save_settings'])) {
    $activeTab = 'settings';
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      try {
        $keys = $_POST['setting_key'] ?? [];
        $values = $_POST['setting_value'] ?? [];
        $descriptions = $_POST['setting_description'] ?? [];

        foreach ($keys as $i => $key) {
          $val = trim($values[$i] ?? '');
          $desc = trim($descriptions[$i] ?? '');
          SiteSettings::set($key, $val, $desc);
        }
        $settingsSaved = true;
      } catch (Exception $e) {
        $settingsError = $e->getMessage();
      }
    }
  }

  // ── SQL HANDLER ─────────────────────────────────────────────────────────────
  $sqlQuery    = '';
  $sqlError    = '';
  $sqlSuccess  = '';
  $sqlIsSelect = false;
  $sqlColumns  = [];
  $sqlRows     = [];

  // ── DB SCHEMA (for treeview) – uses global mysqli $conn directly ─────────
  $dbSchema = [];
  global $conn;
  if ($conn) {
    $schemaResult = mysqli_query($conn,
      "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
       ORDER BY TABLE_NAME, ORDINAL_POSITION"
    );
    if ($schemaResult) {
      while ($col = mysqli_fetch_assoc($schemaResult)) {
        $dbSchema[$col['TABLE_NAME']][] = $col;
      }
      mysqli_free_result($schemaResult);
    }
  }

  if (isset($_POST['execute_sql'])) {
    $activeTab = 'sql';
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $sqlQuery = trim($_POST['sql_query'] ?? '');

      if ($sqlQuery === '') {
        $sqlError = 'Inserire una query SQL.';
      } else {
        $db = new DB();
        $firstWord = strtoupper(strtok(ltrim($sqlQuery), " \t\n\r"));
        $sqlIsSelect = in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'], true);

        try {
          if ($sqlIsSelect) {
            $stmt = $db->pdo->query($sqlQuery);
            $sqlRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($sqlRows)) {
              $sqlColumns = array_keys($sqlRows[0]);
            }
            $sqlSuccess = 'Query eseguita: ' . count($sqlRows) . ' righe trovate.';
          } else {
            $affected = $db->pdo->exec($sqlQuery);
            $sqlSuccess = 'Query eseguita con successo. Righe interessate: ' . ($affected !== false ? $affected : 0);
          }
        } catch (PDOException $e) {
          $sqlError = $e->getMessage();
        }
      }
    }
  }
?>

<h1>Utilit&agrave; Sito</h1>

<!-- ── TABS ─────────────────────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mt-4" role="tablist">
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'email' ? 'active' : ''; ?>"
       data-toggle="tab" href="#tab-email" role="tab">
      <i class="fas fa-envelope mr-1"></i> Email di Test
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'sql' ? 'active' : ''; ?>"
       data-toggle="tab" href="#tab-sql" role="tab">
      <i class="fas fa-database mr-1"></i> Esecuzione SQL
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?php echo $activeTab === 'settings' ? 'active' : ''; ?>"
       data-toggle="tab" href="#tab-settings" role="tab">
      <i class="fas fa-cog mr-1"></i> Impostazioni
    </a>
  </li>
</ul>

<div class="tab-content mt-4">

  <!-- ══ TAB: EMAIL ══════════════════════════════════════════════════════════ -->
  <div class="tab-pane fade <?php echo $activeTab === 'email' ? 'show active' : ''; ?>" id="tab-email" role="tabpanel">

    <p class="text-muted">Mittente: <strong>mercatino@comitatogenitoridavtv.it</strong> (via SMTP <?php echo esc_html(SMTP_HOST); ?>)</p>

    <?php if ($emailSent): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Email inviata con successo a <strong><?php echo esc_html($to); ?></strong>
      </div>
    <?php endif; ?>

    <?php if ($emailError): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>Errore SMTP:</strong>
        <pre style="white-space: pre-wrap; font-size: 0.85em; margin-top: 10px;"><?php echo esc_html($emailError); ?></pre>
      </div>
    <?php endif; ?>

    <form method="post">
      <?php csrf_field(); ?>
      <div class="form-group">
        <label for="to">Destinatario <span class="text-danger">*</span></label>
        <input name="to" id="to" type="email" class="form-control" value="<?php echo esc_html($to); ?>" required placeholder="nome@esempio.it">
      </div>
      <div class="form-group">
        <label for="subject">Oggetto <span class="text-danger">*</span></label>
        <input name="subject" id="subject" type="text" class="form-control" value="<?php echo esc_html($subject); ?>" required placeholder="Oggetto dell email">
      </div>
      <div class="form-group">
        <label for="body">Corpo del messaggio</label>
        <textarea name="body" id="body" class="form-control" rows="8" placeholder="Scrivi il contenuto..."><?php echo esc_html($body); ?></textarea>
        <small class="form-text text-muted">Il testo viene inviato come HTML (gli a-capo vengono convertiti automaticamente).</small>
      </div>
      <button type="submit" name="send_test_email" class="btn btn-primary">
        <i class="fas fa-paper-plane"></i> Invia Email di Test
      </button>
    </form>

  </div>

  <!-- ══ TAB: SQL ════════════════════════════════════════════════════════════ -->
  <div class="tab-pane fade <?php echo $activeTab === 'sql' ? 'show active' : ''; ?>" id="tab-sql" role="tabpanel">

    <p class="text-muted">Esegui query SQL direttamente sul database <strong><?php echo esc_html(DB_NAME); ?></strong>.</p>

    <?php if ($sqlSuccess): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo esc_html($sqlSuccess); ?>
      </div>
    <?php endif; ?>

    <?php if ($sqlError): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>Errore SQL:</strong>
        <pre style="white-space: pre-wrap; font-size: 0.85em; margin-top: 10px;"><?php echo esc_html($sqlError); ?></pre>
      </div>
    <?php endif; ?>

    <!-- ── Query form ── -->
    <form method="post">
      <?php csrf_field(); ?>
      <div class="form-group">
        <label for="sql_query">Query SQL</label>
        <textarea name="sql_query" id="sql_query" class="form-control" rows="8"
                  placeholder="SELECT * FROM user LIMIT 10;"
                  style="font-family: monospace; font-size: 0.9em;"><?php echo esc_html($sqlQuery); ?></textarea>
        <small class="form-text text-muted">SELECT, SHOW, DESCRIBE restituiscono risultati in griglia. INSERT, UPDATE, DELETE mostrano righe interessate.</small>
      </div>
      <button type="submit" name="execute_sql" class="btn btn-warning">
        <i class="fas fa-play"></i> Esegui Query
      </button>
    </form>

    <!-- ── Schema treeview (collapsible) ── -->
    <?php if (!empty($dbSchema)): ?>
    <div class="mt-3">
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSchemaPanel(this)">
        <i class="fas fa-sitemap mr-1"></i> Schema DB
        <small class="text-muted ml-1">(<?php echo count($dbSchema); ?> tabelle &mdash; clic su un nome per copiarlo)</small>
      </button>
      <div id="schemaPanel" style="display:none;" class="mt-2">
        <div class="card">
          <div class="card-body p-1" style="max-height: 380px; overflow-y: auto;">
            <?php foreach ($dbSchema as $tableName => $columns): ?>
            <div>
              <div class="d-flex align-items-center px-2 py-1 schema-table-header"
                   style="cursor:pointer; user-select:none;"
                   onclick="toggleTableCols(this)">
                <i class="fas fa-chevron-right schema-chevron mr-1" style="font-size:0.65em; color:#6c757d; transition:transform 0.15s;"></i>
                <i class="fas fa-table mr-1" style="font-size:0.75em; color:#007bff;"></i>
                <code class="schema-copy" data-copy="<?php echo htmlspecialchars($tableName); ?>"
                      style="font-size:0.85em; cursor:pointer;"
                      title="Copia nome tabella"
                      onclick="schemaCopy(event, this)"><?php echo esc_html($tableName); ?></code>
                <small class="text-muted ml-2" style="font-size:0.7em;">(<?php echo count($columns); ?> colonne)</small>
              </div>
              <div class="schema-cols" style="display:none;">
                <?php foreach ($columns as $col): ?>
                <?php
                  $colIcon = 'fa-columns'; $colColor = '#6c757d';
                  if ($col['COLUMN_KEY'] === 'PRI') { $colIcon = 'fa-key';  $colColor = '#ffc107'; }
                  elseif ($col['COLUMN_KEY'] === 'MUL') { $colIcon = 'fa-link'; $colColor = '#17a2b8'; }
                ?>
                <div class="d-flex align-items-center py-0" style="padding-left:32px; border-left:2px solid #dee2e6; margin-left:16px;">
                  <i class="fas <?php echo htmlspecialchars($colIcon); ?> mr-1" style="font-size:0.65em; color:<?php echo htmlspecialchars($colColor); ?>;"></i>
                  <code class="schema-copy" data-copy="<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>"
                        style="font-size:0.8em; cursor:pointer; white-space:nowrap;"
                        title="<?php echo htmlspecialchars($col['COLUMN_TYPE'] . ($col['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL')); ?>"
                        onclick="schemaCopy(event, this)"><?php echo esc_html($col['COLUMN_NAME']); ?></code>
                  <small class="text-muted ml-1" style="font-size:0.65em;"><?php echo esc_html($col['COLUMN_TYPE']); ?></small>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <style>
    .schema-table-header:hover { background: #f8f9fa; }
    .schema-copy:hover { color: #007bff !important; text-decoration: underline; }
    </style>
    <script>
    function toggleSchemaPanel(btn) {
      var panel = document.getElementById('schemaPanel');
      panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }
    function toggleTableCols(header) {
      var cols = header.nextElementSibling;
      var chevron = header.querySelector('.schema-chevron');
      if (cols.style.display === 'none') {
        cols.style.display = 'block';
        chevron.style.transform = 'rotate(90deg)';
      } else {
        cols.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
      }
    }
    function schemaCopy(e, el) {
      e.stopPropagation();
      var text = el.dataset.copy;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() { flashCopied(el); });
      } else {
        // Fallback for HTTP contexts
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus(); ta.select();
        try { document.execCommand('copy'); flashCopied(el); } catch(err) {}
        document.body.removeChild(ta);
      }
    }
    function flashCopied(el) {
      var orig = el.style.color;
      el.style.color = '#28a745';
      setTimeout(function() { el.style.color = orig; }, 800);
    }
    </script>
    <?php endif; ?>

    <?php if ($sqlIsSelect && !empty($sqlRows)): ?>
    <!-- DataTables Buttons extension (CSV/Excel export) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.5/css/buttons.bootstrap4.min.css">
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>

    <div class="card mt-4 mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-table mr-1"></i> Risultati (<?php echo count($sqlRows); ?> righe)</strong>
      </div>
      <div class="card-body p-0" style="overflow-x: auto;">
        <table id="sqlResultTable" class="table table-striped table-bordered table-sm mb-0" style="font-size: 0.85em;">
          <thead class="thead-dark">
            <tr>
              <?php foreach ($sqlColumns as $col): ?>
                <th><?php echo esc_html($col); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sqlRows as $row): ?>
              <tr>
                <?php foreach ($sqlColumns as $col): ?>
                  <td><?php echo esc_html($row[$col] ?? ''); ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    $(document).ready(function() {
      $('#sqlResultTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
          {
            extend: 'csvHtml5',
            text: '<i class="fas fa-file-csv"></i> CSV',
            className: 'btn btn-sm btn-outline-success',
            title: 'query_result'
          },
          {
            extend: 'excelHtml5',
            text: '<i class="fas fa-file-excel"></i> Excel',
            className: 'btn btn-sm btn-outline-success',
            title: 'query_result'
          }
        ],
        pageLength: 50,
        language: {
          search: 'Filtra:',
          lengthMenu: 'Mostra _MENU_ righe',
          info: 'Righe _START_-_END_ di _TOTAL_',
          paginate: { previous: '&laquo;', next: '&raquo;' },
          emptyTable: 'Nessun risultato',
          zeroRecords: 'Nessuna riga corrispondente'
        },
        order: []
      });
    });
    </script>
    <?php endif; ?>

  </div>

  <!-- ══ TAB: SETTINGS ═══════════════════════════════════════════════════════ -->
  <div class="tab-pane fade <?php echo $activeTab === 'settings' ? 'show active' : ''; ?>" id="tab-settings" role="tabpanel">

    <p class="text-muted">Parametri di configurazione del mercatino (tabella <code>site_settings</code>).</p>

    <?php if ($settingsSaved): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Impostazioni salvate con successo.
      </div>
    <?php endif; ?>

    <?php if ($settingsError): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>Errore:</strong> <?php echo esc_html($settingsError); ?>
      </div>
    <?php endif; ?>

    <?php $allSettings = SiteSettings::getAll(); ?>

    <form method="post">
      <?php csrf_field(); ?>

      <table class="table table-bordered table-sm">
        <thead class="thead-light">
          <tr>
            <th style="width: 25%;">Chiave</th>
            <th style="width: 20%;">Valore</th>
            <th style="width: 55%;">Descrizione</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allSettings as $setting): ?>
          <tr>
            <td class="align-middle">
              <code><?php echo esc_html($setting['setting_key']); ?></code>
              <input type="hidden" name="setting_key[]" value="<?php echo esc_html($setting['setting_key']); ?>">
            </td>
            <td>
              <input type="text" name="setting_value[]" class="form-control form-control-sm"
                     value="<?php echo esc_html($setting['setting_value']); ?>">
            </td>
            <td>
              <input type="text" name="setting_description[]" class="form-control form-control-sm"
                     value="<?php echo esc_html($setting['description'] ?? ''); ?>">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <button type="submit" name="save_settings" class="btn btn-primary">
        <i class="fas fa-save"></i> Salva Impostazioni
      </button>
    </form>

  </div>

</div>
