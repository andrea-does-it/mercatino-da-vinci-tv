<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

  $errors = false;
  $password = '';
  $confirm_password = '';

  $userMgr = new UserManager();

  if (isset($_POST['change_password'])){
    // Validate CSRF token
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      // Don't escape passwords - they get hashed, not stored as text
      $password = $_POST['password'];
      $confirm_password = $_POST['confirm_password'];

      if (!$userMgr->passwordsMatch($password, $confirm_password)){
        $errors = true;
        $alertMsg = 'passwords_not_match';
      }

      if(!$errors AND !$userMgr->isValidPassword($password)){
        $errors = true;
        $alertMsg = 'invalid_password';
      }

      if(!$errors){
        $userMgr->updatePassword($loggedInUser->id, $password);
        $alertMsg = 'updated';
        $password = '';
        $confirm_password = '';
      }
    }
  }

  if (isset($_POST['change_address'])){
    // Validate CSRF token
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $street = esc($_POST['street']);
      $city = esc($_POST['city']);
      $cap = esc($_POST['cap']);

      if ($street == '' OR $city == '' OR $cap == ''){
        $errors = true;
        $alertMsg = 'mandatory_fields';
      } else {
        $userMgr->createAddress($loggedInUser->id, $street, $city, $cap);
        $alertMsg = 'updated';
      }
    }
  }

  // Handle IBAN update
  $ibanError = '';
  $ibanSuccess = false;
  $ibanInputValue = '';
  $ibanOwnerInputValue = '';
  if (isset($_POST['change_iban'])){
    // Validate CSRF token
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $ibanInputValue = isset($_POST['iban']) ? esc($_POST['iban']) : '';
      $ibanOwnerInputValue = isset($_POST['iban_owner_name']) ? esc($_POST['iban_owner_name']) : '';

      if ($ibanInputValue == '') {
        // Delete IBAN if empty
        $userMgr->deleteIBAN($loggedInUser->id);
        $ibanSuccess = true;
        $alertMsg = 'iban_deleted';
      } else if (trim($ibanOwnerInputValue) == '') {
        $errors = true;
        $ibanError = 'L\'intestatario del conto è obbligatorio quando si inserisce un IBAN.';
      } else if (!$userMgr->isValidIBAN($ibanInputValue)) {
        $errors = true;
        $ibanError = 'Il codice IBAN inserito non è valido. Verifica di averlo digitato correttamente.';
      } else {
        $userMgr->saveIBAN($loggedInUser->id, $ibanInputValue, $ibanOwnerInputValue);
        $ibanSuccess = true;
        $alertMsg = 'updated';
      }
    }
  }

  $address = $userMgr->getAddress($loggedInUser->id);
  $ibanData = $userMgr->getIBAN($loggedInUser->id);
?>

<h1>Il tuo Profilo</h1>
<p>Puoi gestire i tuoi dati personali...</p>

<hr class=mb-4>

<h5 class="mb-3 mt-3">Indirizzo di spedizione</h5>

<form method="post">
  <?php csrf_field(); ?>
  <div class="mb-3">
    <label for="street">Via</label>
    <input name="street" type="text" class="form-control" id="street" value="<?php echo isset($address['street']) ? esc_html($address['street']) : ''; ?>">
  </div>
  <div class="row">
    <div class="col-md-8 mb-3">
      <label for="city">Città</label>
      <input name="city" type="text" class="form-control" id="city" value="<?php echo isset($address['city']) ? esc_html($address['city']) : ''; ?>">
    </div>
    <div class="col-md-4 mb-3">
      <label for="cap">CAP</label>
      <input name="cap" type="text" class="form-control" id="cap" value="<?php echo isset($address['cap']) ? esc_html($address['cap']) : ''; ?>">
    </div>
  </div>
  <input name="change_address" type="submit" class="btn btn-primary" value="Cambia Indirizzo">
</form>

<hr class="mb-4">

<h5 class="mb-3 mt-3">Dati Bancari (per il rimborso dei libri venduti)</h5>

<form method="post">
  <?php csrf_field(); ?>
  <div class="mb-3">
    <label for="iban_owner_name">Intestatario conto</label>
    <input name="iban_owner_name" type="text" class="form-control" id="iban_owner_name" value="<?php echo $ibanError ? esc_html($ibanOwnerInputValue) : ($ibanData ? esc_html($ibanData['iban_owner_name']) : ''); ?>" placeholder="Es. Mario Rossi" maxlength="100">
    <small class="form-text text-muted">Nome e cognome dell'intestatario del conto corrente.</small>
  </div>
  <div class="mb-3">
    <label for="iban">IBAN</label>
    <input name="iban" type="text" class="form-control<?php echo $ibanError ? ' is-invalid' : ''; ?>" id="iban" value="<?php echo $ibanError ? esc_html($ibanInputValue) : ($ibanData ? esc_html($ibanData['iban_formatted']) : ''); ?>" placeholder="Es. IT60X0542811101000000123456" maxlength="40">
    <?php if ($ibanError): ?>
      <div class="invalid-feedback d-block"><?php echo esc_html($ibanError); ?></div>
    <?php endif; ?>
    <small class="form-text text-muted">
      Il codice IBAN viene utilizzato esclusivamente per accreditare il ricavato dalla vendita dei tuoi libri.
      <?php if ($ibanData && $ibanData['iban_updated_at']): ?>
        <br>Ultimo aggiornamento: <?php echo date('d/m/Y H:i', strtotime($ibanData['iban_updated_at'])); ?>
      <?php endif; ?>
    </small>
  </div>
  <input name="change_iban" type="submit" class="btn btn-primary" value="Salva Dati Bancari">
  <?php if ($ibanData): ?>
    <button type="submit" name="change_iban" class="btn btn-outline-danger" onclick="document.getElementById('iban').value=''; document.getElementById('iban_owner_name').value='';">Rimuovi Dati Bancari</button>
  <?php endif; ?>
  <?php if ($ibanSuccess): ?>
    <span class="text-success ml-2"><i class="fas fa-check"></i> Salvato</span>
  <?php endif; ?>
</form>

<hr class="mb-4">

<h5 class="mb-3 mt-3">Cambio Password</h5>
<form method="post">
  <?php csrf_field(); ?>
  <div class="form-group">
    <label for="password">Nuova Password</label>
    <input name="password" id="password" type="password" class="form-control" value="<?php echo esc_html($password); ?>">
    <small class="form-text text-muted">Minimo 8 caratteri, con almeno una maiuscola, una minuscola e un numero.</small>
  </div>
  <div class="form-group">
    <label for="confirm_password">Conferma Password</label>
    <input name="confirm_password" id="confirm_password" type="password" class="form-control" value="<?php echo esc_html($confirm_password); ?>">
  </div>
  <input name="change_password" type="submit" class="btn btn-primary" value="Cambia Password">
</form>

<hr class="mb-4">

<div class="alert alert-info">
  <i class="fas fa-shield-alt"></i>
  Per gestire le tue preferenze privacy, il consenso newsletter, esportare i tuoi dati o cancellare il tuo account,
  visita la sezione <a href="<?php echo ROOT_URL; ?>user?page=privacy">Gestione Privacy</a>.
</div>