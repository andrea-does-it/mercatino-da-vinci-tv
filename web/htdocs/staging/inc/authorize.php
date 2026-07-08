<?php
  // Prevent from direct access
  if (!defined('ROOT_URL')) {
      die;
  }

  // ── Bootstrap di sicurezza (gira a ogni richiesta via inc/init.php, prima di ogni output) ──
  if (!headers_sent()) {
      // Rileva HTTPS. Su TopHost nginx termina il TLS e fa da proxy ad Apache, quindi
      // $_SERVER['HTTPS'] puo' risultare "off" anche su richieste https: consideriamo
      // anche X-Forwarded-Proto / porta 443. Se non e' rilevabile, Secure e HSTS restano
      // disattivi (nessun rischio di lockout): non vengono forzati.
      $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
          || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
          || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

      // Forza HTTPS: le richieste in chiaro vengono rediritte alla versione https.
      // La rilevazione $isHttps e' verificata affidabile su questo hosting, quindi dopo
      // il redirect la richiesta risulta https e non si creano loop. Solo GET/HEAD, per
      // non alterare eventuali POST (i form sono comunque serviti su https).
      if (!$isHttps
          && isset($_SERVER['REQUEST_METHOD'])
          && in_array($_SERVER['REQUEST_METHOD'], array('GET', 'HEAD'), true)) {
          $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
          if ($host !== '') {
              header('Location: https://' . $host . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), true, 301);
              exit;
          }
      }

      // Header di sicurezza (sicuri sia in http sia in https)
      header('X-Content-Type-Options: nosniff');
      header('X-Frame-Options: SAMEORIGIN');
      header('Referrer-Policy: strict-origin-when-cross-origin');
      // HSTS solo quando la richiesta e' davvero https.
      if ($isHttps) {
          header('Strict-Transport-Security: max-age=31536000');
      }

      // Cookie di sessione rinforzato: HttpOnly + SameSite sempre; Secure solo su https.
      if (session_status() === PHP_SESSION_NONE) {
          ini_set('session.use_strict_mode', '1');
          if (PHP_VERSION_ID >= 70300) {
              session_set_cookie_params([
                  'lifetime' => 0,
                  'path'     => '/',
                  'httponly' => true,
                  'secure'   => $isHttps,
                  'samesite' => 'Lax',
              ]);
          } else {
              // Fallback pre-7.3: SameSite iniettato nel path.
              session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
          }
      }
  }

  global $loggedInUser;
  session_start();

  // Secure session handling: store only user_id, not serialized objects
  // This prevents PHP object injection attacks via unserialize()
  if (isset($_SESSION['user_id'])) {
      $userMgr = new UserManager();
      $loggedInUser = $userMgr->get($_SESSION['user_id']);

      // If user no longer exists in database, clear session
      if (!$loggedInUser) {
          unset($_SESSION['user_id']);
      }
  }
