<?php

global $loggedInUser;

if ($loggedInUser) {
  echo "<script>location.href='".ROOT_URL."user'</script>";
  exit;
}

$password = "";
$confirm_password = "";
$userMgr = new UserManager();
$showForm = true;

// Get GUID from GET (initial load) or POST (form submission)
$guid = "";
if (isset($_POST["guid"]) && !empty($_POST["guid"])) {
    $guid = esc($_POST["guid"]);
} elseif (isset($_GET["guid"]) && !empty($_GET["guid"])) {
    $guid = esc($_GET["guid"]);
}

// Validate GUID exists
if (empty($guid)) {
    echo '<div class="alert alert-danger">Richiesta non valida. Il link potrebbe essere scaduto o malformato.</div>';
    echo '<p><a href="'.ROOT_URL.'auth?page=forgot-password" class="btn btn-primary">Richiedi nuovo link</a></p>';
    $showForm = false;
} else {
    $userId = $userMgr->guidExists($guid);
    if (!$userId) {
        echo '<div class="alert alert-danger">Il link di reset non è valido o è già stato utilizzato.</div>';
        echo '<p><a href="'.ROOT_URL.'auth?page=forgot-password" class="btn btn-primary">Richiedi nuovo link</a></p>';
        $showForm = false;
    }
}

// Handle form submission
if ($showForm && isset($_POST['resetPwd'])) {
    // Validate CSRF token
    if (!CSRF::validateToken()) {
        global $alertMsg;
        $alertMsg = "csrf_error";
    } else {
        // Don't escape passwords - they get hashed, not stored as text
        // Using esc() would corrupt special characters like & < > " '
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];

        // Validate passwords match
        if (!$userMgr->passwordsMatch($password, $confirm_password)) {
            global $alertMsg;
            $alertMsg = "passwords_not_match";
        }
        // Validate password strength
        else if (!$userMgr->isValidPassword($password)) {
            global $alertMsg;
            $alertMsg = "invalid_password";
        }
        // All validations passed - update password
        else {
            $userMgr->updatePassword($userId, $password);
            $userMgr->invalidateGuid($guid);
            echo "<script>location.href='".ROOT_URL."auth?page=login&msg=password_updated'</script>";
            exit;
        }
    }
}

if (!$showForm) {
    return;
}

?>

<h1>Reset Password</h1>
<p>Imposta una nuova password per il tuo account.</p>

<form method="post" class="mb-4">
  <?php csrf_field(); ?>
  <div class="form-group">
    <label for="password">Nuova Password</label>
    <input name="password" id="password" type="password" class="form-control" required>
    <small class="form-text text-muted"><?php echo $userMgr->getPasswordRequirements(); ?></small>
  </div>
  <div class="form-group">
    <label for="confirm_password">Conferma Password</label>
    <input name="confirm_password" id="confirm_password" type="password" class="form-control" required>
  </div>
  <input type="hidden" name="guid" value="<?php echo esc_html($guid); ?>">
  <input class="btn btn-primary right mt-3" type="submit" value="Reset Password" name="resetPwd">
</form>
