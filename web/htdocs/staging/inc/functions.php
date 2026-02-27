<?php
  // Prevent from direct access
  if (! defined('ROOT_URL')) {
    die;
  }

 function esc($str) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars($str));
  }

  function esc_html($str) {
    return htmlspecialchars($str);
  }

  function shorten($str) {
    return substr($str, 0, 30) . '...';
  }

  function random_string(){
    return substr(md5(mt_rand()), 0, 20);
  }

  /**
   * Output CSRF token hidden field for forms
   * Usage: <?php csrf_field(); ?> inside a form
   */
  function csrf_field() {
    echo CSRF::tokenField();
  }

  /**
   * Get CSRF token value for AJAX requests
   * @return string The token value
   */
  function csrf_token() {
    return CSRF::getToken();
  }

  /**
   * Validate CSRF token on POST requests
   * Returns true if valid, false otherwise
   * @return bool
   */
  function csrf_validate() {
    return CSRF::validateToken();
  }

  /**
   * Send email via authenticated SMTP using PHPMailer.
   *
   * @param string $to Recipient email address
   * @param string $subject Email subject
   * @param string $htmlBody HTML email body
   * @param string|null &$errorMsg Error message if sending fails
   * @return bool True on success, false on failure
   */
  function send_mail($to, $subject, $htmlBody, &$errorMsg = null, $debug = false) {
    require_once ROOT_PATH . 'lib/phpmailer/Exception.php';
    require_once ROOT_PATH . 'lib/phpmailer/PHPMailer.php';
    require_once ROOT_PATH . 'lib/phpmailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
      // SMTP configuration
      $mail->isSMTP();
      $mail->Host       = SMTP_HOST;
      $mail->SMTPAuth   = true;
      $mail->AuthType   = 'LOGIN';
      $mail->Username   = SMTP_USER;
      $mail->Password   = SMTP_PASS;
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = SMTP_PORT;

      if ($debug) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use (&$errorMsg) {
          $errorMsg .= $str . "\n";
        };
      }

      // Sender and recipient
      $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
      $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
      $mail->addAddress($to);

      // Content
      $mail->XMailer = ' ';
      $mail->isHTML(true);
      $mail->CharSet = 'UTF-8';
      $mail->Subject = $subject;
      $mail->Body    = $htmlBody;

      $mail->send();
      return true;
    } catch (PHPMailer\PHPMailer\Exception $e) {
      if (!$debug) {
        $errorMsg = $mail->ErrorInfo;
      }
      return false;
    }
  }