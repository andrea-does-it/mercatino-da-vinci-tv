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
        log_activity($loggedInUser->id, 'password_change');
        $alertMsg = 'updated';
        $password = '';
        $confirm_password = '';
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
        log_activity($loggedInUser->id, 'iban_delete');
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
        log_activity($loggedInUser->id, 'iban_update');
        $ibanSuccess = true;
        $alertMsg = 'updated';
      }
    }
  }

  // Handle student info update
  $studentError = '';
  $studentSuccess = false;
  if (isset($_POST['change_student'])){
    if (!CSRF::validateToken()) {
      $alertMsg = 'csrf_error';
    } else {
      $studentFirstName = isset($_POST['student_first_name']) ? esc($_POST['student_first_name']) : '';
      $studentLastName = isset($_POST['student_last_name']) ? esc($_POST['student_last_name']) : '';
      $studentClass = isset($_POST['student_class']) ? esc($_POST['student_class']) : '';

      if ($studentClass !== '' && !preg_match('/^[1-5][A-Za-z]$/', $studentClass)) {
        $errors = true;
        $studentError = 'Formato classe non valido. Usa il formato: numero (1-5) seguito da una lettera (es. 3B).';
      }

      if (!$errors) {
        $userMgr->saveStudentInfo($loggedInUser->id, $studentFirstName, $studentLastName, $studentClass);
        log_activity($loggedInUser->id, 'student_info_update');
        $studentSuccess = true;
        $alertMsg = 'updated';
        $loggedInUser = $userMgr->get($loggedInUser->id);
      }
    }
  }

  $ibanData = $userMgr->getIBAN($loggedInUser->id);
?>

<h1>Il tuo Profilo</h1>

<h5 class="mb-3 mt-3">Informazioni personali</h5>
<div class="row">
  <div class="col-md-4 mb-3">
    <label>Nome</label>
    <input type="text" class="form-control-plaintext font-weight-bold" value="<?php echo esc_html($loggedInUser->first_name); ?>" readonly>
  </div>
  <div class="col-md-4 mb-3">
    <label>Cognome</label>
    <input type="text" class="form-control-plaintext font-weight-bold" value="<?php echo esc_html($loggedInUser->last_name); ?>" readonly>
  </div>
  <div class="col-md-4 mb-3">
    <label>Email</label>
    <input type="text" class="form-control-plaintext font-weight-bold" value="<?php echo esc_html($loggedInUser->email); ?>" readonly>
  </div>
</div>

<hr class="mb-4">

<?php $scholasticYear = (date('Y') - 1) . '/' . date('Y'); ?>
<h5 class="mb-3 mt-3">Dati Studente <small class="text-muted">(anno scolastico <?php echo $scholasticYear; ?>)</small></h5>

<form method="post">
  <?php csrf_field(); ?>
  <div class="form-group">
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="is_student" onchange="copyNameToStudent(this)">
      <label class="form-check-label" for="is_student">Ti stai registrando come studente?</label>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group col-md-5">
      <label for="student_first_name">Nome studente</label>
      <input name="student_first_name" id="student_first_name" type="text" class="form-control" value="<?php echo esc_html($loggedInUser->student_first_name); ?>" placeholder="Nome dello studente">
    </div>
    <div class="form-group col-md-5">
      <label for="student_last_name">Cognome studente</label>
      <input name="student_last_name" id="student_last_name" type="text" class="form-control" value="<?php echo esc_html($loggedInUser->student_last_name); ?>" placeholder="Cognome dello studente">
    </div>
    <div class="form-group col-md-2">
      <label for="student_class">Classe</label>
      <input name="student_class" id="student_class" type="text" class="form-control<?php echo $studentError ? ' is-invalid' : ''; ?>" value="<?php echo esc_html($loggedInUser->student_class); ?>" placeholder="Es. 3B" maxlength="3" pattern="[1-5][A-Za-z]" title="Formato: numero (1-5) seguito da una lettera (es. 3B)" style="text-transform: uppercase;">
      <?php if ($studentError): ?>
        <div class="invalid-feedback d-block"><?php echo esc_html($studentError); ?></div>
      <?php endif; ?>
    </div>
  </div>
  <input name="change_student" type="submit" class="btn btn-primary" value="Salva Dati Studente">
  <?php if ($studentSuccess): ?>
    <span class="text-success ml-2"><i class="fas fa-check"></i> Salvato</span>
  <?php endif; ?>
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

<script>
function copyNameToStudent(cb) {
  if (cb.checked) {
    document.getElementById('student_first_name').value = '<?php echo addslashes(esc_html($loggedInUser->first_name)); ?>';
    document.getElementById('student_last_name').value = '<?php echo addslashes(esc_html($loggedInUser->last_name)); ?>';
  } else {
    document.getElementById('student_first_name').value = '';
    document.getElementById('student_last_name').value = '';
  }
}
</script>