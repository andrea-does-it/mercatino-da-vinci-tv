<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  // Tab "Template Email" di site_utils: gestione dei template per "Email Ordini".

  $templateMgr = new EmailTemplateManager();

  $tplSaved   = false;
  $tplDeleted = false;
  $tplError   = '';

  if (isset($_POST['save_template'])) {
    if (!CSRF::validateToken()) {
      $tplError = 'Sessione scaduta: ricarica la pagina e riprova.';
    } else {
      $tplId      = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
      $tplName    = trim(isset($_POST['template_name']) ? $_POST['template_name'] : '');
      $tplSubject = trim(isset($_POST['template_subject']) ? $_POST['template_subject'] : '');
      $tplBody    = isset($_POST['template_body']) ? $_POST['template_body'] : '';
      if ($tplName === '' || $tplSubject === '' || trim($tplBody) === '') {
        $tplError = 'Nome, oggetto e corpo sono obbligatori.';
      } else {
        $templateMgr->save($tplId, $tplName, $tplSubject, $tplBody);
        $tplSaved = true;
      }
    }
  }

  if (isset($_POST['delete_template'])) {
    if (!CSRF::validateToken()) {
      $tplError = 'Sessione scaduta: ricarica la pagina e riprova.';
    } else {
      $templateMgr->delete((int)(isset($_POST['template_id']) ? $_POST['template_id'] : 0));
      $tplDeleted = true;
    }
  }

  // Template in modifica (?edit=<id>) — ignorato dopo un salvataggio riuscito.
  $editTpl = null;
  if (isset($_GET['edit']) && !$tplSaved) {
    $candidate = $templateMgr->get((int)$_GET['edit']);
    if (isset($candidate->id) && (int)$candidate->id > 0) {
      $editTpl = $candidate;
    }
  }

  $templates = $templateMgr->getAllOrdered();
  $tplTabUrl = ROOT_URL . 'admin/?page=site_utils&tab=email_templates';
?>

<p class="text-muted">
  Template riutilizzabili per il tab <strong>Email Ordini</strong>: oggetto e corpo
  supportano i segnaposti elencati sotto.
</p>

<?php if ($tplSaved): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle"></i> Template salvato.</div>
<?php endif; ?>
<?php if ($tplDeleted): ?>
  <div class="alert alert-info"><i class="fas fa-trash"></i> Template eliminato.</div>
<?php endif; ?>
<?php if ($tplError): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo esc_html($tplError); ?></div>
<?php endif; ?>

<div class="row">
  <!-- ── Elenco template ── -->
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-list mr-1"></i> Template esistenti (<?php echo count($templates); ?>)</div>
      <div class="card-body p-0">
        <?php if (empty($templates)): ?>
          <p class="text-muted p-3 mb-0">Nessun template: crea il primo con il modulo a fianco.</p>
        <?php else: ?>
          <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th>Nome</th>
                <th>Oggetto</th>
                <th>Aggiornato</th>
                <th style="width: 90px;">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($templates as $tpl): ?>
                <tr>
                  <td class="align-middle"><strong><?php echo esc_html($tpl->name); ?></strong></td>
                  <td class="align-middle"><small><?php echo esc_html($tpl->subject); ?></small></td>
                  <td class="align-middle"><small class="text-muted"><?php echo date('d/m/Y', strtotime($tpl->updated_at)); ?></small></td>
                  <td class="align-middle">
                    <a href="<?php echo $tplTabUrl; ?>&edit=<?php echo (int)$tpl->id; ?>"
                       class="btn btn-sm btn-outline-primary" title="Modifica">
                      <i class="fas fa-pen"></i>
                    </a>
                    <?php /* Messaggio generico: il nome dentro la stringa JS romperebbe l'attributo se contiene apostrofi */ ?>
                    <form method="post" class="d-inline"
                          onsubmit="return confirm('Eliminare questo template?');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="template_id" value="<?php echo (int)$tpl->id; ?>">
                      <button type="submit" name="delete_template" class="btn btn-sm btn-outline-danger" title="Elimina">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Crea / modifica ── -->
  <div class="col-lg-6">
    <div class="card mb-4">
      <div class="card-header">
        <?php if ($editTpl): ?>
          <i class="fas fa-pen mr-1"></i> Modifica template: <?php echo esc_html($editTpl->name); ?>
        <?php else: ?>
          <i class="fas fa-plus mr-1"></i> Nuovo template
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="template_id" value="<?php echo $editTpl ? (int)$editTpl->id : 0; ?>">
          <div class="form-group">
            <label for="template_name">Nome <span class="text-danger">*</span></label>
            <input type="text" name="template_name" id="template_name" class="form-control" required
                   maxlength="100" value="<?php echo $editTpl ? esc_html($editTpl->name) : ''; ?>"
                   placeholder="Es. Sollecito consegna libri">
          </div>
          <div class="form-group">
            <label for="template_subject">Oggetto <span class="text-danger">*</span></label>
            <input type="text" name="template_subject" id="template_subject" class="form-control" required
                   maxlength="255" value="<?php echo $editTpl ? esc_html($editTpl->subject) : ''; ?>"
                   placeholder="Es. Mercatino: pratica {num_pratica} in attesa di consegna">
          </div>
          <div class="form-group">
            <label for="template_body">Corpo (testo semplice) <span class="text-danger">*</span></label>
            <textarea name="template_body" id="template_body" class="form-control" rows="10" required
                      placeholder="Ciao {nome},&#10;..."><?php echo $editTpl ? esc_html($editTpl->body) : ''; ?></textarea>
            <small class="form-text text-muted">Gli a-capo vengono convertiti automaticamente in HTML all'invio.</small>
          </div>
          <button type="submit" name="save_template" class="btn btn-primary">
            <i class="fas fa-save"></i> <?php echo $editTpl ? 'Salva modifiche' : 'Crea template'; ?>
          </button>
          <?php if ($editTpl): ?>
            <a href="<?php echo $tplTabUrl; ?>" class="btn btn-secondary">Annulla</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- ── Legenda segnaposto ── -->
    <div class="card">
      <div class="card-header bg-light"><i class="fas fa-tags mr-1"></i> Segnaposti disponibili</div>
      <div class="card-body py-2">
        <table class="table table-sm mb-0">
          <tbody>
            <?php foreach (OrderEmailManager::PLACEHOLDERS as $ph => $desc): ?>
              <tr>
                <td style="width: 140px;"><code><?php echo esc_html($ph); ?></code></td>
                <td><small><?php echo esc_html($desc); ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
