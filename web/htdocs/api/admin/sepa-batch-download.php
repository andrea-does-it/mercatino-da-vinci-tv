<?php
// Genera una distinta SEPA (XML CBI) e la restituisce come download.
// Endpoint standalone: nessun output prima degli header. L'XML non viene salvato.

require_once '../../inc/init.php';
global $loggedInUser;

if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
  header('HTTP/1.1 403 Forbidden');
  echo 'Forbidden';
  exit;
}

$year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$pageUrl = ROOT_URL . 'admin/?page=seller-refund-sepa&year=' . $year;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $pageUrl);
  exit;
}
CSRF::validateOrDie($pageUrl . '&msg=csrf_error');

$mgr = new SepaBatchManager();
try {
  if (!$mgr->isInstalled()) {
    throw new SepaException('La migrazione 202610010001_sepa_distinte.sql non è stata applicata su questo database.');
  }
  $template = isset($_POST['template']) ? (string)$_POST['template'] : '';
  $current = $mgr->getDebtorSettings();
  if ($template !== '' && $template !== $current['template']) {
    $errors = $mgr->saveTemplate($template);
    if ($errors) {
      throw new SepaException(implode(' ', $errors));
    }
  }
  $result = $mgr->createBatch(
    $year,
    isset($_POST['refund_ids']) ? (array)$_POST['refund_ids'] : [],
    isset($_POST['execution_date']) ? (string)$_POST['execution_date'] : '',
    $template,
    (int)$loggedInUser->id
  );
} catch (SepaException $e) {
  $_SESSION['sepa_error'] = $e->getMessage();
  header('Location: ' . $pageUrl . '&msg=sepa_error');
  exit;
}

log_activity($loggedInUser->id, 'admin_sepa_batch_created',
  'batch: ' . $result['batch_id'] . ', bonifici: ' . $result['tx_count'] . ', totale: ' . number_format($result['total'], 2, '.', ''));

// Il JS della pagina aspetta questo cookie per sapere che il download è partito.
$dlToken = isset($_POST['download_token']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string)$_POST['download_token']) : '';
if ($dlToken !== '') {
  // header() invece di setcookie(): l'array di opzioni di setcookie richiede PHP >= 7.3.
  header('Set-Cookie: sepa_dl=' . $dlToken . '; Max-Age=120; Path=/; SameSite=Lax', false);
}

header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($result['xml']));
echo $result['xml'];
exit;
