<?php

global $loggedInUser;

if ($loggedInUser) {
  echo "<script>location.href='".ROOT_URL."user'</script>";
  exit;
}

$email = esc($_GET['email']);
$userMgr = new UserManager();
$user = $userMgr->getUserByEmail($email);
if (!$user) {
  echo "<script>location.href='".ROOT_URL."public'</script>";
  exit;
}

$link = $userMgr->createResetLink($user['id']);
$msg = "<h1>" .SITE_NAME ." - Reset Password</h1>
<p>Buongiorno, ".$user["first_name"]." ".$user["last_name"]."</p>
<p>Clicca sul seguente link per reimpostare la tua password per accedere a '".SITE_NAME."':</p>
<p><a href='".$link."' style='color: #007bff; text-decoration: underline;'>Reset Password &raquo;</a></p>
<p>Se il link non funziona, copia e incolla questo URL nel tuo browser:<br>
<span style='word-break: break-all; color: #666;'>".$link."</span></p>
";

$br = "\r\n";
$headers = "From: Mercatino Comitato Genitori <mercatino@comitatogenitoridavtv.it>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
mail($user['email'], SITE_NAME. " - Reset Password", $msg, $headers);

?>

<h3>Recupero Password</h3>
<p class="text-muted">Ti abbiamo inviato una mail con un link per reimpostare la password.</p>