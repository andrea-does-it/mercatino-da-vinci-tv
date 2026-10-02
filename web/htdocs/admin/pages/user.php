<?php
// Prevent from direct access
if (! defined('ROOT_URL')) {
  die;
}

CSRF::validateOrDie();

$mgr = new UserManager();

$pm = new ProfileManager();
$profiles = $pm->getAll();

$errors = [];

// La colonna iban_town (migrazione 202610020001) potrebbe non esistere ancora su
// questo DB: in quel caso il campo "Localita' beneficiario" va nascosto, non la
// SELECT fallirebbe con "Unknown column" e la pagina andrebbe in errore fatale.
$supportsIbanTown = $mgr->supportsIbanTown();

$lblAction = 'Aggiungi';
$submit = 'add';

// Formato leggibile (o un trattino lungo) per le date di sola lettura della scheda.
function admin_user_fmt_date($value) {
  if ($value === null || $value === '') {
    return "\u{2014}";
  }
  $ts = strtotime($value);
  return $ts ? date('d/m/Y H:i', $ts) : "\u{2014}";
}

// Campi di sola lettura non presenti nel form (consensi, date): sempre letti dal DB,
// anche quando il resto della pagina mostra i valori appena inviati (dopo un errore).
function admin_user_readonly_fields($mgr, $id) {
  $out = [
    'iban_updated_at' => null,
    'donate_books_date' => null,
    'privacy_consent' => null,
    'privacy_consent_date' => null,
    'newsletter_consent' => null,
    'newsletter_consent_date' => null,
    'deletion_requested' => null,
    'deletion_requested_date' => null,
  ];
  $row = $mgr->getAdminRow($id);
  if ($row) {
    $out['iban_updated_at'] = $row->iban_updated_at;
    $out['donate_books_date'] = $row->donate_books_date;
    $out['privacy_consent'] = $row->privacy_consent;
    $out['privacy_consent_date'] = $row->privacy_consent_date;
    $out['newsletter_consent'] = $row->newsletter_consent;
    $out['newsletter_consent_date'] = $row->newsletter_consent_date;
    $out['deletion_requested'] = $row->deletion_requested;
    $out['deletion_requested_date'] = $row->deletion_requested_date;
  }
  return $out;
}

$formData = [
  'id' => 0,
  'first_name' => '',
  'last_name' => '',
  'email' => '',
  'user_type' => '',
  'profile_id' => '',
  'student_first_name' => '',
  'student_last_name' => '',
  'student_class' => '',
  'iban' => '',
  'iban_owner_name' => '',
  'iban_town' => '',
  'iban_updated_at' => null,
  'donate_books' => 0,
  'donate_books_date' => null,
  'privacy_consent' => null,
  'privacy_consent_date' => null,
  'newsletter_consent' => null,
  'newsletter_consent_date' => null,
  'deletion_requested' => null,
  'deletion_requested_date' => null,
];

// Localita' predefinita dei beneficiari, solo come placeholder del campo "Localita'
// beneficiario": non deve rompere la pagina se la classe o la migrazione mancano.
$ibanTownPlaceholder = '';
if (class_exists('SepaBatchManager')) {
  try {
    $sepaBatchMgr = new SepaBatchManager();
    if ($sepaBatchMgr->isInstalled()) {
      $debtorSettings = $sepaBatchMgr->getDebtorSettings();
      $ibanTownPlaceholder = (string)($debtorSettings['default_creditor_town'] ?? '');
    }
  } catch (Exception $e) {
    $ibanTownPlaceholder = '';
  }
}

// Querystring param id: precarica i valori dal DB (un submit in errore, piu' sotto,
// li sovrascrive con quanto inviato dall'admin).
if (isset($_GET['id'])) {

  $id = trim($_GET['id']);
  $dbUser = $mgr->get($id);

  if ($dbUser && (int)($dbUser->id ?? 0) > 0) {

    $lblAction = 'Modifica';
    $submit    = 'update';

    $adminRow = $mgr->getAdminRow($id);
    $ibanInfo = $mgr->getIBAN($id);

    $formData['id'] = (int)$id;
    $formData['first_name'] = $dbUser->first_name;
    $formData['last_name'] = $dbUser->last_name;
    $formData['email'] = $dbUser->email;
    $formData['user_type'] = $dbUser->user_type;
    $formData['profile_id'] = $dbUser->profile_id;
    $formData['student_first_name'] = $adminRow->student_first_name;
    $formData['student_last_name'] = $adminRow->student_last_name;
    $formData['student_class'] = $adminRow->student_class;
    $formData['iban'] = $ibanInfo ? $mgr->formatIBAN($ibanInfo['iban']) : '';
    $formData['iban_owner_name'] = $adminRow->iban_owner_name;
    if ($supportsIbanTown) {
      $formData['iban_town'] = $adminRow->iban_town;
    }
    $formData['iban_updated_at'] = $adminRow->iban_updated_at;
    $formData['donate_books'] = (int)$adminRow->donate_books;
    $formData['donate_books_date'] = $adminRow->donate_books_date;
    $formData['privacy_consent'] = $adminRow->privacy_consent;
    $formData['privacy_consent_date'] = $adminRow->privacy_consent_date;
    $formData['newsletter_consent'] = $adminRow->newsletter_consent;
    $formData['newsletter_consent_date'] = $adminRow->newsletter_consent_date;
    $formData['deletion_requested'] = $adminRow->deletion_requested;
    $formData['deletion_requested_date'] = $adminRow->deletion_requested_date;
  } else {
    $errors[] = 'Utente non trovato.';
  }
}

// Submit add
if (isset($_POST['add'])) {

  $addData = [
    'first_name' => trim($_POST['first_name'] ?? ''),
    'last_name' => trim($_POST['last_name'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'user_type' => trim($_POST['user_type'] ?? ''),
    'profile_id' => trim($_POST['profile_id'] ?? ''),
    'student_first_name' => trim($_POST['student_first_name'] ?? ''),
    'student_last_name' => trim($_POST['student_last_name'] ?? ''),
    'student_class' => trim($_POST['student_class'] ?? ''),
    'iban_owner_name' => trim($_POST['iban_owner_name'] ?? ''),
    'iban_town' => trim($_POST['iban_town'] ?? ''),
    'donate_books' => isset($_POST['donate_books']) ? (int)$_POST['donate_books'] : 0,
    'iban' => $_POST['iban'] ?? '',
  ];

  // Stessa validazione di adminUpdate() (campi obbligatori, email, tipo utente,
  // classe, IBAN/cifratura), eseguita PRIMA di creare l'utente: cosi' un IBAN
  // invalido o un'email duplicata non creano comunque l'account.
  $addErrors = $mgr->validateAdminData($addData, 0);

  if ($addErrors) {
    $errors = $addErrors;
    $formData = array_merge($formData, $addData);
  } else {

    $newUserId = $mgr->createUser(
      new User(0, $addData['first_name'], $addData['last_name'], $addData['email'], $addData['user_type'], (int)$addData['profile_id']),
      null
    );

    if ($newUserId > 0) {
      log_activity($loggedInUser->id, 'admin_user_created', 'user_id: ' . $newUserId);

      $extraResult = $mgr->adminUpdate($newUserId, $addData);

      if ($extraResult['ok']) {
        echo "<script>location.href='".ROOT_URL."admin/?page=users-list&msg=created';</script>";
        exit;
      }

      // L'utente di base esiste gia': lo si segnala e si passa in modalita' modifica
      // mostrando quanto digitato, cosi' l'admin puo' correggere e salvare di nuovo.
      $errors = array_merge(["L'utente è stato creato, ma alcuni dati aggiuntivi non sono stati salvati:"], $extraResult['errors']);
      $lblAction = 'Modifica';
      $submit = 'update';
      $formData = array_merge($formData, $addData);
      $formData['id'] = $newUserId;
      $formData = array_merge($formData, admin_user_readonly_fields($mgr, $newUserId));
    } else {
      $errors[] = "Si è verificato un errore durante la creazione dell'utente.";
      $formData = array_merge($formData, $addData);
    }
  }
}

// Submit update
if (isset($_POST['update'])) {

  $postedId = trim($_POST['id'] ?? '');

  if ($postedId == '' || $postedId == '0') {
    $errors[] = 'Utente non valido.';
  } else {

    $updateData = [
      'first_name' => trim($_POST['first_name'] ?? ''),
      'last_name' => trim($_POST['last_name'] ?? ''),
      'email' => trim($_POST['email'] ?? ''),
      'user_type' => trim($_POST['user_type'] ?? ''),
      'profile_id' => trim($_POST['profile_id'] ?? ''),
      'student_first_name' => trim($_POST['student_first_name'] ?? ''),
      'student_last_name' => trim($_POST['student_last_name'] ?? ''),
      'student_class' => trim($_POST['student_class'] ?? ''),
      'iban_owner_name' => trim($_POST['iban_owner_name'] ?? ''),
      'iban_town' => trim($_POST['iban_town'] ?? ''),
      // Il JS postUnchecked(), dove presente, inserisce un hidden "donate_books=0"
      // quando la casella non e' spuntata: il campo puo' quindi essere sempre
      // presente in POST. Si legge il VALORE (0/1), non la sola presenza (isset
      // darebbe sempre 1 anche se il campo e' presente da deselezionato).
      'donate_books' => isset($_POST['donate_books']) ? (int)$_POST['donate_books'] : 0,
      'iban' => $_POST['iban'] ?? '',
    ];

    $result = $mgr->adminUpdate($postedId, $updateData);

    if ($result['ok']) {
      $campi = $result['changed'] ? implode(', ', $result['changed']) : 'nessuna modifica';
      log_activity($loggedInUser->id, 'admin_user_updated', 'user_id: ' . $postedId . ', campi: ' . $campi);
      echo "<script>location.href='".ROOT_URL."admin/?page=users-list&msg=updated';</script>";
      exit;
    }

    $errors = $result['errors'];
    $lblAction = 'Modifica';
    $submit = 'update';
    $formData = array_merge($formData, $updateData);
    $formData['id'] = (int)$postedId;
    $formData = array_merge($formData, admin_user_readonly_fields($mgr, $postedId));
  }
}
?>

<a href="<?php echo ROOT_URL . 'admin/?page=users-list'; ?>" class="back underline">&laquo; Lista Utenti</a>

<h1><?php echo esc_html($lblAction); ?> Utente</h1>

<?php if ($errors) : ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $err) : ?>
        <li><?php echo esc_html($err); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" class="mt-5">
  <?php csrf_field(); ?>

  <div class="card mb-4">
    <div class="card-header">Account</div>
    <div class="card-body">
      <div class="form-group">
        <label for="first_name">Nome</label>
        <input name="first_name" id="first_name" type="text" class="form-control" value="<?php echo esc_html($formData['first_name']); ?>">
      </div>
      <div class="form-group">
        <label for="last_name">Cognome</label>
        <input name="last_name" id="last_name" type="text" class="form-control" value="<?php echo esc_html($formData['last_name']); ?>">
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input name="email" id="email" type="text" class="form-control" value="<?php echo esc_html($formData['email']); ?>">
      </div>
      <div class="form-group">
        <label for="user_type">Tipo Utente</label>
        <select name="user_type" id="user_type" class="form-control">
          <option value=""> - Seleziona - </option>
          <option <?php if ($formData['user_type'] == 'admin') echo 'selected'; ?> value="admin">Amministratore</option>
          <option <?php if ($formData['user_type'] == 'regular') echo 'selected'; ?> value="regular">Regolare</option>
          <option <?php if ($formData['user_type'] == 'pwuser') echo 'selected'; ?> value="pwuser">Power user</option>
        </select>
      </div>
      <div class="form-group">
        <label for="profile_id">Profilo</label>
        <select name="profile_id" id="profile_id" class="form-control">
          <option value="0"> - Scegli una profilo - </option>
          <?php if (count($profiles) > 0) : ?>
            <?php foreach ($profiles as $profile) : ?>
              <option <?php if ($formData['profile_id'] == $profile->id) echo 'selected'; ?> value="<?php echo esc_html($profile->id); ?>"><?php echo esc_html($profile->name); ?></option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Studente</div>
    <div class="card-body">
      <div class="form-group">
        <label for="student_first_name">Nome studente</label>
        <input name="student_first_name" id="student_first_name" type="text" maxlength="100" class="form-control" value="<?php echo esc_html((string)$formData['student_first_name']); ?>">
      </div>
      <div class="form-group">
        <label for="student_last_name">Cognome studente</label>
        <input name="student_last_name" id="student_last_name" type="text" maxlength="100" class="form-control" value="<?php echo esc_html((string)$formData['student_last_name']); ?>">
      </div>
      <div class="form-group">
        <label for="student_class">Classe</label>
        <input name="student_class" id="student_class" type="text" maxlength="3" class="form-control" style="max-width: 8rem;" value="<?php echo esc_html((string)$formData['student_class']); ?>">
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Bonifico</div>
    <div class="card-body">
      <div class="form-group">
        <label for="iban">IBAN</label>
        <input name="iban" id="iban" type="text" class="form-control" value="<?php echo esc_html((string)$formData['iban']); ?>">
        <small class="form-text text-muted">Lascia vuoto per rimuovere l'IBAN</small>
      </div>
      <div class="form-group">
        <label for="iban_owner_name">Intestatario IBAN</label>
        <input name="iban_owner_name" id="iban_owner_name" type="text" maxlength="100" class="form-control" value="<?php echo esc_html((string)$formData['iban_owner_name']); ?>">
      </div>
      <?php if ($supportsIbanTown) : ?>
      <div class="form-group">
        <label for="iban_town">Località beneficiario</label>
        <input name="iban_town" id="iban_town" type="text" maxlength="35" class="form-control" placeholder="<?php echo esc_html($ibanTownPlaceholder); ?>" value="<?php echo esc_html((string)$formData['iban_town']); ?>">
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label for="iban_updated_at">Ultimo aggiornamento IBAN</label>
        <input id="iban_updated_at" type="text" class="form-control" value="<?php echo esc_html(admin_user_fmt_date($formData['iban_updated_at'])); ?>" readonly>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Donazione</div>
    <div class="card-body">
      <div class="form-group form-check">
        <input type="checkbox" class="form-check-input" id="donate_books" name="donate_books" value="1" <?php echo ((int)$formData['donate_books'] === 1) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="donate_books">Dona i libri invenduti</label>
      </div>
      <div class="form-group">
        <label for="donate_books_date">Data donazione</label>
        <input id="donate_books_date" type="text" class="form-control" value="<?php echo esc_html(admin_user_fmt_date($formData['donate_books_date'])); ?>" readonly>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Privacy</div>
    <div class="card-body">
      <p>
        <strong>Consenso privacy:</strong>
        <?php echo esc_html($formData['privacy_consent'] ? 'Sì' : 'No'); ?>
        (<?php echo esc_html(admin_user_fmt_date($formData['privacy_consent_date'])); ?>)
      </p>
      <p>
        <strong>Consenso newsletter:</strong>
        <?php echo esc_html($formData['newsletter_consent'] ? 'Sì' : 'No'); ?>
        (<?php echo esc_html(admin_user_fmt_date($formData['newsletter_consent_date'])); ?>)
      </p>
      <p class="mb-0">
        <strong>Richiesta di cancellazione:</strong>
        <?php echo esc_html($formData['deletion_requested'] ? 'Sì' : 'No'); ?>
        (<?php echo esc_html(admin_user_fmt_date($formData['deletion_requested_date'])); ?>)
      </p>
    </div>
  </div>

  <input type="hidden" name="id" value="<?php echo esc_html($formData['id']); ?>">
  <input name="<?php echo esc_html($submit); ?>" type="submit" class="btn btn-primary" value="<?php echo esc_html($lblAction); ?> Utente">
</form>
