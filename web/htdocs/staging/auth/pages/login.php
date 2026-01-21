<?php

$email = '';
$password = '';

if (isset($_POST['login'])) {

  // Validate CSRF token
  if (!CSRF::validateToken()) {
    echo "<script>location.href='".ROOT_URL."auth?page=login&msg=csrf_error';</script>";
    exit;
  }

  $email = trim($_POST['email']);
  $password = $_POST['password'];

  // Basic validation
  if (empty($email) || empty($password)) {
    echo "<script>location.href='".ROOT_URL."auth?page=login&msg=mandatory_fields';</script>";
    exit;
  }

  $userMgr = new UserManager();
  $userObj = $userMgr->login($email, $password);

  if ($userObj) {
    // Store only user ID in session to prevent object injection attacks
    $_SESSION['user_id'] = $userObj->id;
    if (isset($_SESSION['client_id'])) {
      $cartMgr = new CartManager();
      $cartMgr->mergeCarts();
    }
    header('Location: ' . ROOT_URL . 'user?page=dashboard');
    exit;
  } else {
    // Redirect with error message and preserve email
    $emailParam = urlencode($email);
    echo "<script>location.href='".ROOT_URL."auth?page=login&msg=login_err&email=".$emailParam."';</script>";
    exit;
  }
}

// Preserve email value from URL parameter if redirected with error
$email = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : $email;

// Clean URL after displaying the error message (removes the flash effect)
if (isset($_GET['msg']) && !isset($_POST['login'])) {
  echo "<script>
    // Clean the URL after the page loads to prevent error flash on next submit
    if (window.history.replaceState) {
      const url = new URL(window.location);
      url.searchParams.delete('msg');
      if (!url.searchParams.get('email')) {
        url.searchParams.delete('email');
      }
      window.history.replaceState({}, document.title, url.toString());
    }
  </script>";
}
?>

<h1>Login</h1>

<form method="post" class="mb-4">
  <?php csrf_field(); ?>
  <div class="form-group">
    <label for="email">Email</label>
    <input name="email" id="email" type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
  </div>
  <div class="form-group">
    <label for="password">Password</label>
    <input name="password" id="password" type="password" class="form-control" required>
  </div>
  <input class="btn btn-primary right" type="submit" value="Login" name="login">
  <a class="underline" href="<?php echo ROOT_URL; ?>auth?page=register">Non hai un account? Registrati</a>
  <br>
  <a class="underline" href="<?php echo ROOT_URL; ?>auth?page=forgot-password">Hai dimenticato la password?</a>
</form>