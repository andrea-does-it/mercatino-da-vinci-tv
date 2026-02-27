<?php
  // Prevent from direct access
  if (!defined('ROOT_URL')) {
      die;
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
