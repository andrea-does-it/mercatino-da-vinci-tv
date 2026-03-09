<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
    die;
}

global $loggedInUser;
if (!$loggedInUser) {
    echo "<script>location.href='".ROOT_URL."auth?page=login';</script>";
    exit;
}

$userMgr = new UserManager();
$consent = $userMgr->getConsent($loggedInUser->id);

// Handle newsletter consent update
if (isset($_POST['update_newsletter'])) {
    if (!CSRF::validateToken()) {
        $alertMsg = 'csrf_error';
    } else {
        $newsletter = isset($_POST['newsletter_consent']) ? 1 : 0;
        $userMgr->updateNewsletterConsent($loggedInUser->id, $newsletter);
        log_activity($loggedInUser->id, 'newsletter_update', 'consent: ' . $newsletter);
        $alertMsg = 'consent_updated';
        $consent = $userMgr->getConsent($loggedInUser->id);
    }
}

// Data export is now handled by api/user/export-data.php

// Handle activity log deletion (GDPR Art. 17)
if (isset($_POST['delete_activity_logs'])) {
    if (!CSRF::validateToken()) {
        $alertMsg = 'csrf_error';
    } else {
        $actLog = new ActivityLog();
        $actLog->deleteUserLogs($loggedInUser->id);
        $alertMsg = 'activity_logs_deleted';
    }
}

// Handle account deletion request
if (isset($_POST['request_deletion'])) {
    if (!CSRF::validateToken()) {
        $alertMsg = 'csrf_error';
    } else {
        $confirm = isset($_POST['confirm_deletion']) ? true : false;
        if ($confirm) {
            // Perform actual deletion
            $userMgr->deleteAccount($loggedInUser->id);

            // Logout
            session_destroy();
            echo "<script>location.href='".ROOT_URL."public?page=homepage&msg=account_deleted';</script>";
            exit;
        } else {
            $alertMsg = 'confirm_deletion_required';
        }
    }
}
?>

<h1>Gestione Privacy</h1>
<p class="lead">Qui puoi gestire i tuoi dati personali e le tue preferenze di consenso.</p>

<hr class="mb-4">

<!-- Consent Status -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-check-circle"></i> Stato dei Consensi</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>Consenso Privacy</th>
                <td>
                    <?php if ($consent && $consent['privacy_consent']): ?>
                        <span class="badge badge-success">Accettato</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Non registrato</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small">
                    <?php if ($consent && $consent['privacy_consent_date']): ?>
                        Data: <?php echo date('d/m/Y H:i', strtotime($consent['privacy_consent_date'])); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Newsletter</th>
                <td>
                    <?php if ($consent && $consent['newsletter_consent']): ?>
                        <span class="badge badge-success">Iscritto</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Non iscritto</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small">
                    <?php if ($consent && $consent['newsletter_consent_date']): ?>
                        Data: <?php echo date('d/m/Y H:i', strtotime($consent['newsletter_consent_date'])); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- Newsletter Preferences -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-envelope"></i> Preferenze Newsletter</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <?php csrf_field(); ?>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="newsletter_consent" name="newsletter_consent"
                    <?php echo ($consent && $consent['newsletter_consent']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="newsletter_consent">
                    Desidero ricevere comunicazioni e newsletter dal Comitato Genitori
                </label>
            </div>
            <button type="submit" name="update_newsletter" class="btn btn-primary">
                <i class="fas fa-save"></i> Salva Preferenze
            </button>
        </form>
    </div>
</div>

<!-- Data Export -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-download"></i> Esporta i Tuoi Dati (Portabilità)</h5>
    </div>
    <div class="card-body">
        <p>
            In conformità all'Art. 20 del GDPR, hai il diritto di ricevere i tuoi dati personali in un formato strutturato.
            Clicca sul pulsante qui sotto per scaricare tutti i tuoi dati in formato JSON.
        </p>
        <form method="post" action="<?php echo ROOT_URL; ?>api/user/export-data.php">
            <?php csrf_field(); ?>
            <button type="submit" class="btn btn-info">
                <i class="fas fa-file-download"></i> Scarica i Miei Dati
            </button>
        </form>
    </div>
</div>

<!-- Activity Log -->
<?php
$actLog = new ActivityLog();
$recentLogs = $actLog->getUserLogs($loggedInUser->id, 20);
?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-history"></i> Registro Attività</h5>
    </div>
    <div class="card-body">
        <p>
            In conformità all'Art. 15 del GDPR, puoi visualizzare le tue ultime attività registrate dal sito.
            Le attività vengono conservate per 12 mesi e non contengono dati personali oltre al tuo identificativo utente.
        </p>

        <?php if (empty($recentLogs)): ?>
            <p class="text-muted">Nessuna attività registrata.</p>
        <?php else: ?>
            <table class="table table-sm table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>Data</th>
                        <th>Azione</th>
                        <th>Dettaglio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $entry): ?>
                    <tr>
                        <td class="text-nowrap"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($entry['created_at']))); ?></td>
                        <td><?php echo htmlspecialchars($entry['action']); ?></td>
                        <td class="text-muted small"><?php echo htmlspecialchars($entry['detail'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="mt-3">
            <form method="post" action="<?php echo ROOT_URL; ?>api/user/activity-logs-export.php" class="d-inline">
                <?php csrf_field(); ?>
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fas fa-file-download"></i> Scarica il Registro Attività (JSON)
                </button>
            </form>
            &nbsp;
            <form method="post" class="d-inline" onsubmit="return confirm('Sei sicuro di voler eliminare tutto il registro attività? Questa azione è irreversibile.');">
                <?php csrf_field(); ?>
                <button type="submit" name="delete_activity_logs" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-trash"></i> Elimina Registro Attività
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Account Deletion -->
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-trash-alt"></i> Cancella Account (Diritto all'Oblio)</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <strong>Attenzione!</strong> La cancellazione dell'account è irreversibile.
            <ul class="mb-0 mt-2">
                <li>Tutti i tuoi dati personali verranno eliminati</li>
                <li>Lo storico degli ordini verrà anonimizzato (per obblighi fiscali)</li>
                <li>Non potrai più accedere al tuo account</li>
                <li>Eventuali pratiche in corso dovranno essere gestite prima della cancellazione</li>
            </ul>
        </div>
        <form method="post" onsubmit="return confirmDeletion();">
            <?php csrf_field(); ?>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="confirm_deletion" name="confirm_deletion" required>
                <label class="form-check-label" for="confirm_deletion">
                    <strong>Confermo di voler cancellare definitivamente il mio account e tutti i miei dati</strong>
                </label>
            </div>
            <button type="submit" name="request_deletion" class="btn btn-danger">
                <i class="fas fa-exclamation-triangle"></i> Cancella il Mio Account
            </button>
        </form>
    </div>
</div>

<!-- Privacy Policy Link -->
<div class="card mb-4">
    <div class="card-body">
        <p class="mb-0">
            <i class="fas fa-info-circle text-info"></i>
            Per maggiori informazioni su come trattiamo i tuoi dati, consulta la nostra
            <a href="<?php echo ROOT_URL; ?>public?page=privacy" target="_blank">Informativa sulla Privacy</a>.
        </p>
    </div>
</div>

<script>
function confirmDeletion() {
    return confirm('Sei sicuro di voler cancellare definitivamente il tuo account?\n\nQuesta azione è IRREVERSIBILE.');
}
</script>
