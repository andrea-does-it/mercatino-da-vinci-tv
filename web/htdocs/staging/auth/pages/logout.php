<?php

unset($_SESSION['user_id']);
session_destroy();
header('Location: ' . ROOT_URL . 'auth/?page=login');
exit;