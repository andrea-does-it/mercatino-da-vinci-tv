<?php
// Prevent from direct access
if (!defined('ROOT_URL')) {
  die;
}
// Merged into news-management — redirect to the Downloads tab
echo "<script>location.href='".ROOT_URL."admin/?page=news-management&tab=downloads';</script>";
exit;
