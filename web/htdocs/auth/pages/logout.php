<?php

if (isset($_SESSION['user_id'])) {
  log_activity($_SESSION['user_id'], 'logout');
}
unset($_SESSION['user_id']);
session_destroy();
header('Location: ' . ROOT_URL . 'auth/?page=login');
exit;