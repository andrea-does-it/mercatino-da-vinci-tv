<?php

$nome = '';
$cognome = '';
$email = '';
$password = '';
$confirm_password = '';
$iban = '';
$iban_owner_name = '';
$student_first_name = '';
$student_last_name = '';
$student_class = '';

if (isset($_POST['register'])) {

  // Validate CSRF token
  if (!CSRF::validateToken()) {
    echo "<script>location.href='".ROOT_URL."auth?page=register&msg=csrf_error';</script>";
    exit;
  }

  $errors = false;

  $nome = esc($_POST['nome']);
  $cognome = esc($_POST['cognome']);
  $email = esc($_POST['email']);
  // Don't escape passwords - they get hashed, not stored as text
  // Using esc() would corrupt special characters like & < > " '
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  // IBAN is optional
  $iban = isset($_POST['iban']) ? esc($_POST['iban']) : '';
  $iban_owner_name = isset($_POST['iban_owner_name']) ? esc($_POST['iban_owner_name']) : '';

  // Student info (optional)
  $student_first_name = isset($_POST['student_first_name']) ? esc($_POST['student_first_name']) : '';
  $student_last_name = isset($_POST['student_last_name']) ? esc($_POST['student_last_name']) : '';
  $student_class = isset($_POST['student_class']) ? esc($_POST['student_class']) : '';

  // Validate privacy consent (required)
  $privacy_consent = isset($_POST['privacy_consent']) ? true : false;
  $rules_consent = isset($_POST['rules_consent']) ? true : false;
  $newsletter_consent = isset($_POST['newsletter_consent']) ? true : false;

  if (!$privacy_consent) {
    $params = http_build_query([
      'msg' => 'privacy_required',
      'nome' => $nome,
      'cognome' => $cognome,
      'email' => $email,
      'iban' => $iban,
      'iban_owner_name' => $iban_owner_name,
      'student_first_name' => $student_first_name,
      'student_last_name' => $student_last_name,
      'student_class' => $student_class
    ]);
    echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
    exit;
  }

  if (!$rules_consent) {
    $params = http_build_query([
      'msg' => 'rules_required',
      'nome' => $nome,
      'cognome' => $cognome,
      'email' => $email,
      'iban' => $iban,
      'iban_owner_name' => $iban_owner_name,
      'student_first_name' => $student_first_name,
      'student_last_name' => $student_last_name,
      'student_class' => $student_class
    ]);
    echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
    exit;
  }

  // Only require name, email, password
  if ($nome != '' AND $cognome != '' AND $email != '' AND $password != '' AND $confirm_password != '') {

    $userMgr = new UserManager();

    // Check email validity
    if(!$errors AND !$userMgr->isValidEmail($email)) {
      // Redirect with error message and preserve form data
      $params = http_build_query([
        'msg' => 'invalid_email',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Reject liceo email addresses
    if(!$errors AND stripos($email, '@liceodavinci.tv') !== false) {
      $params = http_build_query([
        'msg' => 'liceo_email',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Check IBAN owner name is provided when IBAN is provided
    if(!$errors AND $iban != '' AND trim($iban_owner_name) == '') {
      $params = http_build_query([
        'msg' => 'iban_owner_required',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Check IBAN validity (if provided)
    if(!$errors AND $iban != '' AND !$userMgr->isValidIBAN($iban)) {
      $params = http_build_query([
        'msg' => 'invalid_iban',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Check password validity
    if(!$errors AND !$userMgr->isValidPassword($password)) {
      $params = http_build_query([
        'msg' => 'invalid_password',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Check password match
    if(!$errors AND !$userMgr->passwordsMatch($password, $confirm_password)){
      $params = http_build_query([
        'msg' => 'passwords_not_match',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Check if user already exists
    if(!$errors AND $userMgr->userExists($email)){
      $params = http_build_query([
        'msg' => 'user_already_exists',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    if (!$errors) {
      $userId = $userMgr->register($nome, $cognome, $email, $password, 1, $privacy_consent, $newsletter_consent, $student_first_name, $student_last_name, $student_class);
      if ($userId > 0){
        // Save IBAN if provided
        if ($iban != '') {
          $userMgr->saveIBAN($userId, $iban, $iban_owner_name);
        }
        log_activity($userId, 'register');
        echo "<script>location.href='".ROOT_URL."auth?page=login&msg=registered';</script>";
        exit;
      } else {
        $params = http_build_query([
          'msg' => 'err',
          'nome' => $nome,
          'cognome' => $cognome,
          'email' => $email,
          'iban' => $iban,
          'iban_owner_name' => $iban_owner_name
        ]);
        echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
        exit;
      }
    }
  } else {
    // Mandatory fields error
    $params = http_build_query([
      'msg' => 'mandatory_fields',
      'nome' => $nome,
      'cognome' => $cognome,
      'email' => $email,
      'iban' => $iban,
      'iban_owner_name' => $iban_owner_name,
      'student_first_name' => $student_first_name,
      'student_last_name' => $student_last_name,
      'student_class' => $student_class
    ]);
    echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
    exit;
  }
}

// Restore form values from URL parameters if redirected with error
$nome = isset($_GET['nome']) ? htmlspecialchars($_GET['nome']) : $nome;
$cognome = isset($_GET['cognome']) ? htmlspecialchars($_GET['cognome']) : $cognome;
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : $email;
$iban = isset($_GET['iban']) ? htmlspecialchars($_GET['iban']) : $iban;
$iban_owner_name = isset($_GET['iban_owner_name']) ? htmlspecialchars($_GET['iban_owner_name']) : $iban_owner_name;
$student_first_name = isset($_GET['student_first_name']) ? htmlspecialchars($_GET['student_first_name']) : $student_first_name;
$student_last_name = isset($_GET['student_last_name']) ? htmlspecialchars($_GET['student_last_name']) : $student_last_name;
$student_class = isset($_GET['student_class']) ? htmlspecialchars($_GET['student_class']) : $student_class;
?>

<a class="underline " href="<?php echo ROOT_URL; ?>auth?page=login">Già Possiedi un account? Accedi</a>

<h1>Registrazione</h1>

<form method="post" class="mb-4">
  <?php csrf_field(); ?>
  <h5 class="mb-3 mt-3">Informazioni personali</h5>
  <div class="form-group">
    <label for="nome">Nome <span class="text-danger">*</span></label>
    <input name="nome" id="nome" type="text" class="form-control" value="<?php echo esc_html($nome); ?>" required>
  </div>
  <div class="form-group">
    <label for="cognome">Cognome <span class="text-danger">*</span></label>
    <input name="cognome" id="cognome" type="text" class="form-control" value="<?php echo esc_html($cognome); ?>" required>
  </div>
  <div class="form-group">
    <label for="email">Email <span class="text-danger">*</span></label>
    <input name="email" id="email" type="email" class="form-control" value="<?php echo esc_html($email); ?>" required
      oninput="validateEmailDomain(this)">
    <small class="form-text text-danger font-weight-bold">Non usare l'indirizzo email del liceo (@liceodavinci.tv).</small>
    <div id="emailDomainError" class="invalid-feedback">Non puoi usare un indirizzo email @liceodavinci.tv.</div>
  </div>
  <div class="form-group">
    <label for="password">Password <span class="text-danger">*</span></label>
    <input name="password" id="password" type="password" class="form-control" required>
    <small class="form-text text-muted">Minimo 8 caratteri, con almeno una maiuscola, una minuscola e un numero.</small>
  </div>
  <div class="form-group">
    <label for="confirm_password">Conferma Password <span class="text-danger">*</span></label>
    <input name="confirm_password" id="confirm_password" type="password" class="form-control" required>
  </div>

  <hr class="mb-4">

  <?php $scholasticYear = (date('Y') - 1) . '/' . date('Y'); ?>
  <h5 class="mb-3 mt-3">Dati Studente <small class="text-muted">(anno scolastico <?php echo $scholasticYear; ?>)</small></h5>
  <div class="form-group">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="is_student" onchange="copyNameToStudent(this)">
      <label class="form-check-label" for="is_student">Ti stai registrando come studente?</label>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-5">
      <label for="student_first_name">Nome studente</label>
      <input name="student_first_name" id="student_first_name" type="text" class="form-control" value="<?php echo esc_html($student_first_name); ?>" placeholder="Nome dello studente">
    </div>
    <div class="form-group col-md-5">
      <label for="student_last_name">Cognome studente</label>
      <input name="student_last_name" id="student_last_name" type="text" class="form-control" value="<?php echo esc_html($student_last_name); ?>" placeholder="Cognome dello studente">
    </div>
    <div class="form-group col-md-2">
      <label for="student_class">Classe</label>
      <input name="student_class" id="student_class" type="text" class="form-control" value="<?php echo esc_html($student_class); ?>" placeholder="Es. 3B" maxlength="3" pattern="[1-5][A-Za-z]" title="Formato: numero (1-5) seguito da una lettera (es. 3B)" style="text-transform: uppercase;">
    </div>
  </div>

  <hr class="mb-4">

  <h5 class="mb-3 mt-3">Dati Bancari <small class="text-muted">(opzionale - per il rimborso dei libri venduti)</small></h5>
  <div class="mb-3">
    <label for="iban_owner_name">Intestatario conto</label>
    <input name="iban_owner_name" type="text" class="form-control" id="iban_owner_name" value="<?php echo esc_html($iban_owner_name); ?>" placeholder="Es. Mario Rossi" maxlength="100">
    <small class="form-text text-muted">Nome e cognome dell'intestatario del conto corrente.</small>
  </div>
  <div class="mb-3">
    <label for="iban">IBAN</label>
    <input name="iban" type="text" class="form-control" id="iban" value="<?php echo esc_html($iban); ?>" placeholder="Es. IT60X0542811101000000123456" maxlength="34">
    <small class="form-text text-muted">Il codice IBAN viene utilizzato esclusivamente per accreditare il ricavato dalla vendita dei tuoi libri. Puoi aggiungerlo anche in seguito dalla tua area personale.</small>
  </div>

  <hr class="mb-4">

  <h5 class="mb-3 mt-3">Consensi</h5>

  <div class="form-group">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="rules_consent" name="rules_consent" required>
      <label class="form-check-label" for="rules_consent">
        <span class="text-danger">*</span> Ho letto e accetto il
        <a href="<?php echo ROOT_URL; ?>public/docs/regolamento-mercatino-libri-usati-da-vinci.pdf" target="_blank">Regolamento del Mercatino dei Libri Usati</a>.
      </label>
    </div>
  </div>

  <div class="form-group">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="privacy_consent" name="privacy_consent" required>
      <label class="form-check-label" for="privacy_consent">
        <span class="text-danger">*</span> Ho letto e accetto l'<a href="<?php echo ROOT_URL; ?>public?page=privacy" target="_blank">Informativa sulla Privacy</a>
        e acconsento al trattamento dei miei dati personali per la gestione del servizio.
      </label>
    </div>
  </div>

  <div class="form-group">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="newsletter_consent" name="newsletter_consent">
      <label class="form-check-label" for="newsletter_consent">
        Desidero ricevere comunicazioni e newsletter dal Comitato Genitori (facoltativo).
      </label>
    </div>
  </div>

  <p class="small text-muted mt-3"><span class="text-danger">*</span> Campi obbligatori</p>

  <input class="btn btn-primary right mt-3" type="submit" value="Registrati" name="register">

</form>

<script>
function validateEmailDomain(input) {
  if (input.value.toLowerCase().indexOf('@liceodavinci.tv') !== -1) {
    input.setCustomValidity('Non puoi usare un indirizzo email @liceodavinci.tv.');
    input.classList.add('is-invalid');
  } else {
    input.setCustomValidity('');
    input.classList.remove('is-invalid');
  }
}

function copyNameToStudent(cb) {
  if (cb.checked) {
    document.getElementById('student_first_name').value = document.getElementById('nome').value;
    document.getElementById('student_last_name').value = document.getElementById('cognome').value;
  } else {
    document.getElementById('student_first_name').value = '';
    document.getElementById('student_last_name').value = '';
  }
}
</script>
