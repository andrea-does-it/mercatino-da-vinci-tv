<?php

$email = '';
$password = '';
global $alertMsg;

if (isset($_POST['resetPwd'])) {

  // Validate CSRF token
  if (!CSRF::validateToken()) {
    echo "<script>location.href='".ROOT_URL."auth?page=forgot-password&msg=csrf_error';</script>";
    exit;
  }

  $email = esc($_POST['email']);
  $userMgr = new UserManager();
  $userObj = $userMgr->getUserByEmail($email);

  if (!$userObj) {
    echo "<script>location.href='".ROOT_URL."auth?page=forgot-password&msg=email_not_exists';</script>";
    exit;
  }

  log_activity($userObj->id, 'password_reset_request');
  echo "<script>location.href='".ROOT_URL."auth?page=reset-password-request&email=".urlencode($email)."';</script>";
  exit;
}
?>

<h3>Recupero Password</h3>
<p class="text-muted">Inserire l'indirizzo email del tuo account. </p>
<!-- <p class="text-muted">Ti invieremo una mail con un link per reimpostare la password.</p> -->

<form method="post" class="mb-4">
  <?php csrf_field(); ?>
  <div class="form-group">
    <label for="email">Email</label>
    <input name="email" id="email" type="email" class="form-control" value="<?php echo esc_html($email); ?>" required>
  </div>
  <input class="btn btn-primary right" type="submit" value="Reimposta Password" name="resetPwd">
</form>

