<?php

$nome = '';
$cognome = '';
$email = '';
$password = '';
$confirm_password = '';
$street = '';
$city = '';
$cap = '';

if (isset($_POST['register'])) {

  $errors = false;
  
  $nome = esc($_POST['nome']);
  $cognome = esc($_POST['cognome']);
  $email = esc($_POST['email']);
  $password = esc($_POST['password']);
  $confirm_password = esc($_POST['confirm_password']);

  $street = esc($_POST['street']);
  $city = esc($_POST['city']);
  $cap = esc($_POST['cap']);

  if ($nome != '' AND $cognome != '' AND $email != '' AND $password != '' AND $confirm_password != '' AND $street != '' AND $city != '' AND $cap != '' ) {

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
        'cap' => $cap
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
        'cap' => $cap
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
        'cap' => $cap
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    // Check if user already exists - THIS IS THE MAIN FIX
    if(!$errors AND $userMgr->userExists($email)){
      $params = http_build_query([
        'msg' => 'user_already_exists',
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'street' => $street,
        'city' => $city,
        'cap' => $cap
      ]);
      echo "<script>location.href='".ROOT_URL."auth?page=register&$params';</script>";
      exit;
    }

    if (!$errors ) {
      $userId = $userMgr->register($nome, $cognome, $email, $password, 1);
      if ($userId > 0){
        $userMgr->createAddress($userId, $street, $city, $cap);
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
          'cap' => $cap
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
      'cap' => $cap
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
?>

<a class="underline " href="<?php echo ROOT_URL; ?>auth?page=login">Già Possiedi un account? Accedi</a>

<h1>Registrazione</h1>

<form method="post" class="mb-4">
  <h5 class="mb-3 mt-3">Informazioni personali</h5>
  <div class="form-group">
    <label for="nome">Nome</label>
    <input name="nome" id="nome" type="text" class="form-control" value="<?php echo esc_html($nome); ?>" required>
  </div>
  <div class="form-group">
    <label for="cognome">Cognome</label>
    <input name="cognome" id="cognome" type="text" class="form-control" value="<?php echo esc_html($cognome); ?>" required>
  </div>
  <div class="form-group">
    <label for="email">Email ****** NO MAIL LICEO ******</label>
    <input name="email" id="email" type="email" class="form-control" value="<?php echo esc_html($email); ?>" required>
  </div>
  <div class="form-group">
    <label for="password">Password</label>
    <input name="password" id="password" type="password" class="form-control" required>
  </div>
  <div class="form-group">
    <label for="confirm_password">Conferma Password</label>
    <input name="confirm_password" id="confirm_password" type="password" class="form-control" required>
  </div>

  <hr class=mb-4>

  <h5  class="mb-3 mt-3">Indirizzo del mercatino</h5>
  <div class="mb-3">
    <label for="street">Via</label>
    <input name="street" type="text" class="form-control" id="street" value="<?php echo $street ?: 'Viale Europa 32'; ?>" required>
  </div>
  <div class="row">
    <div class="col-md-8 mb-3">
      <label for="city">Città</label>
      <input name="city" type="text" class="form-control" id="city" value="<?php echo $city ?: 'Treviso'; ?>" required>
    </div>
    <div class="col-md-4 mb-3">
      <label for="cap">CAP</label>
      <input name="cap" type="text" class="form-control" id="cap" value="<?php echo $cap ?: '31100'; ?>" required>
    </div>
  </div>

  <input class="btn btn-primary right mt-3" type="submit" value="Registrati" name="register">
  
</form>