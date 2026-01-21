<?php

$nome = '';
$cognome = '';
$email = '';
$password = '';
$confirm_password = '';
$street = '';
$city = '';
$cap = '';
$iban = '';
$iban_owner_name = '';

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

  // Address is now optional
  $street = isset($_POST['street']) ? esc($_POST['street']) : '';
  $city = isset($_POST['city']) ? esc($_POST['city']) : '';
  $cap = isset($_POST['cap']) ? esc($_POST['cap']) : '';

  // IBAN is optional
  $iban = isset($_POST['iban']) ? esc($_POST['iban']) : '';
  $iban_owner_name = isset($_POST['iban_owner_name']) ? esc($_POST['iban_owner_name']) : '';

  // Validate privacy consent (required)
  $privacy_consent = isset($_POST['privacy_consent']) ? true : false;
  $newsletter_consent = isset($_POST['newsletter_consent']) ? true : false;

  if (!$privacy_consent) {
    $params = http_build_query([
      'msg' => 'privacy_required',
      'nome' => $nome,
      'cognome' => $cognome,
      'email' => $email,
      'street' => $street,
      'city' => $city,
      'cap' => $cap,
      'iban' => $iban,
      'iban_owner_name' => $iban_owner_name
    ]);
    echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
    exit;
  }

  // Only require name, email, password (address is optional)
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
        'street' => $street,
        'city' => $city,
        'cap' => $cap,
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
        'street' => $street,
        'city' => $city,
        'cap' => $cap,
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
        'street' => $street,
        'city' => $city,
        'cap' => $cap,
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
        'street' => $street,
        'city' => $city,
        'cap' => $cap,
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
        'street' => $street,
        'city' => $city,
        'cap' => $cap,
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
        'street' => $street,
        'city' => $city,
        'cap' => $cap,
        'iban' => $iban,
        'iban_owner_name' => $iban_owner_name
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    if (!$errors) {
      $userId = $userMgr->register($nome, $cognome, $email, $password, 1, $privacy_consent, $newsletter_consent);
      if ($userId > 0){
        // Only create address if provided
        if ($street != '' && $city != '' && $cap != '') {
          $userMgr->createAddress($userId, $street, $city, $cap);
        }
        // Save IBAN if provided
        if ($iban != '') {
          $userMgr->saveIBAN($userId, $iban, $iban_owner_name);
        }
        echo "<script>location.href='".ROOT_URL."auth?page=login&msg=registered';</script>";
        exit;
      } else {
        $params = http_build_query([
          'msg' => 'err',
          'nome' => $nome,
          'cognome' => $cognome,
          'email' => $email,
          'street' => $street,
          'city' => $city,
          'cap' => $cap,
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
      'street' => $street,
      'city' => $city,
      'cap' => $cap,
      'iban' => $iban,
      'iban_owner_name' => $iban_owner_name
    ]);
    echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
    exit;
  }
}

// Restore form values from URL parameters if redirected with error
$nome = isset($_GET['nome']) ? htmlspecialchars($_GET['nome']) : $nome;
$cognome = isset($_GET['cognome']) ? htmlspecialchars($_GET['cognome']) : $cognome;
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : $email;
$street = isset($_GET['street']) ? htmlspecialchars($_GET['street']) : $street;
$city = isset($_GET['city']) ? htmlspecialchars($_GET['city']) : $city;
$cap = isset($_GET['cap']) ? htmlspecialchars($_GET['cap']) : $cap;
$iban = isset($_GET['iban']) ? htmlspecialchars($_GET['iban']) : $iban;
$iban_owner_name = isset($_GET['iban_owner_name']) ? htmlspecialchars($_GET['iban_owner_name']) : $iban_owner_name;
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
    <label for="email">Email <span class="text-danger">*</span> <small class="text-muted">(non usare email del liceo)</small></label>
    <input name="email" id="email" type="email" class="form-control" value="<?php echo esc_html($email); ?>" required>
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

  <h5 class="mb-3 mt-3">Indirizzo <small class="text-muted">(opzionale - per la consegna dei libri)</small></h5>
  <div class="mb-3">
    <label for="street">Via</label>
    <input name="street" type="text" class="form-control" id="street" value="<?php echo esc_html($street); ?>" placeholder="Es. Viale Europa 32">
  </div>
  <div class="row">
    <div class="col-md-8 mb-3">
      <label for="city">Città</label>
      <input name="city" type="text" class="form-control" id="city" value="<?php echo esc_html($city); ?>" placeholder="Es. Treviso">
    </div>
    <div class="col-md-4 mb-3">
      <label for="cap">CAP</label>
      <input name="cap" type="text" class="form-control" id="cap" value="<?php echo esc_html($cap); ?>" placeholder="Es. 31100">
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
