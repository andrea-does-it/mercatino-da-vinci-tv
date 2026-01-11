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