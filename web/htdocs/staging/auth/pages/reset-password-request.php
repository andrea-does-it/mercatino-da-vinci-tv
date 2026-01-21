<?php

global $loggedInUser;

if ($loggedInUser) {
  echo "<script>location.href='".ROOT_URL."user'</script>";
  exit;
}

// Validate that email is provided
if (!isset($_GET['email']) || empty($_GET['email'])) {
  echo "<script>location.href='".ROOT_URL."auth?page=forgot-password'</script>";
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

// Build HTML email
$msg = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
<h1 style='color: #17a2b8;'>" . esc_html(SITE_NAME) . " - Reset Password</h1>
<p>Buongiorno, " . esc_html($user["first_name"]) . " " . esc_html($user["last_name"]) . "</p>
<p>Clicca sul seguente link per reimpostare la tua password:</p>
<p><a href='" . esc_html($link) . "' style='color: #007bff; text-decoration: underline;'>Reset Password &raquo;</a></p>
<p>Se il link non funziona, copia e incolla questo URL nel tuo browser:<br>
<span style='word-break: break-all; color: #666;'>" . esc_html($link) . "</span></p>
<hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
<p style='font-size: 12px; color: #666;'>Se non hai richiesto il reset della password, puoi ignorare questa email.</p>
<p style='font-size: 12px; color: #666;'>Il link scadrà dopo il primo utilizzo.</p>
</body>
</html>";

$headers = "From: Mercatino Comitato Genitori <mercatino@comitatogenitoridavtv.it>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

$mailSent = mail($user['email'], SITE_NAME . " - Reset Password", $msg, $headers);

?>

<h3>Recupero Password</h3>
<?php if ($mailSent): ?>
<p class="text-muted">Ti abbiamo inviato una mail all'indirizzo <strong><?php echo esc_html($email); ?></strong> con un link per reimpostare la password.</p>
<p class="text-muted small">Se non ricevi l'email entro qualche minuto, controlla la cartella spam.</p>
<?php else: ?>
<div class="alert alert-danger">
  <p>Si è verificato un errore nell'invio dell'email. Riprova più tardi o contatta l'assistenza.</p>
</div>
<?php endif; ?>