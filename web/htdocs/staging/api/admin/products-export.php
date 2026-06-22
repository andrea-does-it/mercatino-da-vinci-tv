<?php
// Export the admin books list (same data as ?page=products-list) to CSV or Excel.
// Standalone endpoint (direct URL), so no output precedes the download headers.

require_once '../../inc/init.php';
global $loggedInUser;

// Same access as the admin area (admin or power user).
if (!$loggedInUser || ($loggedInUser->user_type != 'admin' && $loggedInUser->user_type != 'pwuser')) {
  header('HTTP/1.1 403 Forbidden');
  echo 'Forbidden';
  exit;
}

$format = (isset($_GET['format']) && strtolower($_GET['format']) === 'xls') ? 'xls' : 'csv';

$mgr = new ProductManager();
$catMgr = new CategoryManager();
$imgMgr = new ProductImageManager();
$products = $mgr->getAll();

$headers = ['ID', 'Titolo', 'ISBN', 'Autori', 'Editore', 'Prezzo', 'Prezzo Listino', 'Note Volumi', 'In esaurimento', 'Nascosto', 'Categoria', 'File Immagini'];

$rows = [];
foreach ($products as $p) {
  $catName = '';
  if (!empty($p->category_id)) {
    $c = $catMgr->GetCategory($p->category_id);
    $catName = isset($c->name) ? $c->name : '';
  }
  // Nomi dei file immagine associati (cartella images/<product_id>/): utile per
  // sapere quali libri hanno una copertina caricata.
  $imgNames = [];
  foreach ($imgMgr->getImages($p->id) as $img) {
    $imgNames[] = $img->id . '.' . $img->image_extension;
  }
  $rows[] = [
    $p->id,
    $p->name,
    $p->ISBN,
    $p->autori,
    $p->editore,
    $p->price,
    $p->prezzo_listino,
    $p->nota_volumi,
    ((int)$p->fl_esaurimento === 1) ? 'Si' : 'No',
    ((int)$p->nascosto === 1) ? 'Si' : 'No',
    $catName,
    implode('; ', $imgNames),
  ];
}

$filename = 'libri_' . date('Ymd_His');

if ($format === 'xls') {
  // HTML-table workbook: Excel opens it natively as a sheet.
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
  echo "\xEF\xBB\xBF"; // UTF-8 BOM
  echo '<table border="1"><thead><tr>';
  foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
  }
  echo '</tr></thead><tbody>';
  foreach ($rows as $r) {
    echo '<tr>';
    foreach ($r as $cell) {
      echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    echo '</tr>';
  }
  echo '</tbody></table>';
  exit;
}

// CSV (comma-separated, UTF-8 with BOM so Excel shows accents correctly)
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($rows as $r) {
  fputcsv($out, $r);
}
fclose($out);
exit;
