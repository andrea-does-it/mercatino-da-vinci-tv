<?php

require_once '../../inc/init.php';
global $loggedInUser;

if (!$loggedInUser || $loggedInUser->user_type != 'admin') {
  header('Content-type: application/json', false, 403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

// Validate CSRF token for all POST requests
CSRF::validateAjaxOrDie();

header('Content-type: application/json');

try {

$op = isset($_POST['op']) ? $_POST['op'] : '';

if ($op === 'lookup') {
  $isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';

  if (empty($isbn)) {
    echo json_encode(['error' => 'ISBN non fornito']);
    exit;
  }

  $lookup = BookLookup::lookup($isbn);

  if ($lookup === null) {
    echo json_encode(['error' => 'ISBN non valido']);
    exit;
  }

  $list_price = isset($lookup['list_price']) ? $lookup['list_price'] : null;
  $prezzo_mercatino = ($list_price !== null) ? round($list_price / 2 - 1.50, 2) : null;

  $mgr = new ProductManager();
  $existing = $mgr->findByISBN($isbn);

  $response = [
    'isbn' => $lookup['isbn'],
    'title' => $lookup['title'],
    'authors' => isset($lookup['authors']) ? $lookup['authors'] : '',
    'publisher' => isset($lookup['publisher']) ? $lookup['publisher'] : '',
    'list_price' => $list_price,
    'prezzo_mercatino' => $prezzo_mercatino,
    'cover_url' => isset($lookup['cover_url']) ? $lookup['cover_url'] : '',
    'exists' => $existing !== null,
    'existing_id' => $existing !== null ? (int)$existing->id : null,
    'warnings' => isset($lookup['warnings']) ? $lookup['warnings'] : []
  ];

  echo json_encode($response);
  exit;
}

if ($op === 'check') {
  // Verifica rapida presenza a DB di una lista di ISBN (nessun lookup Libraccio).
  $isbns = isset($_POST['isbns']) ? json_decode($_POST['isbns'], true) : [];
  if (!is_array($isbns)) {
    echo json_encode(['error' => 'isbns non forniti']);
    exit;
  }
  $mgr = new ProductManager();
  $results = [];
  foreach ($isbns as $isbn) {
    $isbn = trim((string)$isbn);
    if ($isbn === '') {
      continue;
    }
    $existing = $mgr->findByISBN($isbn);
    $results[] = [
      'isbn' => $isbn,
      'exists' => $existing !== null,
      'existing_id' => $existing !== null ? (int)$existing->id : null,
    ];
  }
  echo json_encode(['results' => $results]);
  exit;
}

if ($op === 'visible_products') {
  // Elenco dei libri attualmente visibili (nascosto=0). Se viene passato un elenco
  // di ISBN, marca quali verrebbero nascosti dalla sincronizzazione (non in elenco).
  $listIsbns = isset($_POST['isbns']) ? json_decode($_POST['isbns'], true) : [];
  $set = [];
  if (is_array($listIsbns)) {
    foreach ($listIsbns as $i) {
      $i = preg_replace('/[^0-9Xx]/', '', (string)$i);
      if ($i !== '') {
        $set[$i] = true;
      }
    }
  }
  $hasList = count($set) > 0;

  $mgr = new ProductManager();
  $products = $mgr->GetVisibleProducts();
  $rows = [];
  foreach ($products as $p) {
    $isbnNorm = preg_replace('/[^0-9Xx]/', '', (string)$p->ISBN);
    $rows[] = [
      'id'         => (int)$p->id,
      'isbn'       => $p->ISBN,
      'name'       => $p->name,
      'category'   => isset($p->category) ? $p->category : '',
      'qta'        => (int)$p->qta,
      'would_hide' => $hasList ? !isset($set[$isbnNorm]) : false,
    ];
  }
  echo json_encode(['results' => $rows, 'has_list' => $hasList]);
  exit;
}

if ($op === 'sync_visibility') {
  // Nasconde dallo shop i libri il cui ISBN NON e' nell'elenco fornito,
  // e rende visibili quelli presenti nell'elenco.
  $isbns = isset($_POST['isbns']) ? json_decode($_POST['isbns'], true) : [];
  if (!is_array($isbns) || count($isbns) === 0) {
    echo json_encode(['error' => 'Elenco ISBN non fornito']);
    exit;
  }
  $mgr = new ProductManager();
  $res = $mgr->SyncVisibilityByISBN($isbns);
  echo json_encode($res);
  exit;
}

if ($op === 'import') {
  $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

  if (!is_array($items) || empty($items)) {
    echo json_encode(['error' => 'Items non forniti']);
    exit;
  }

  $mgr = new ProductManager();
  $imgMgr = new ProductImageManager();

  // se true, per i libri gia' presenti aggiorna i dati dal file (titolo, autore,
  // editore, prezzo) invece di saltarli
  $updateExisting = isset($_POST['update_existing']) && ($_POST['update_existing'] === '1' || $_POST['update_existing'] === 1 || $_POST['update_existing'] === true);

  $results = [];

  foreach ($items as $item) {
    $isbn = isset($item['isbn']) ? trim($item['isbn']) : '';
    // name/autori/editore: la colonna e' VARCHAR(100) -> tronca per sicurezza
    $name = isset($item['name']) ? mb_substr(trim($item['name']), 0, 100) : '';
    $authors = isset($item['authors']) ? esc(mb_substr(trim($item['authors']), 0, 100)) : '';
    $publisher = isset($item['publisher']) ? esc(mb_substr(trim($item['publisher']), 0, 100)) : '';
    $list_price = isset($item['list_price']) ? (float)$item['list_price'] : null;
    $prezzo_mercatino = isset($item['prezzo_mercatino']) ? (float)$item['prezzo_mercatino'] : null;
    $category_id = isset($item['category_id']) ? (int)$item['category_id'] : 0;
    $qta = isset($item['qta']) ? (int)$item['qta'] : 0;

    $itemResult = [
      'isbn' => $isbn,
      'product_id' => null,
      'cover' => false,
      'error' => null,
      'skipped' => false,
      'updated' => false
    ];

    try {
      // Check if product already exists
      $existing = $mgr->findByISBN($isbn);
      if ($existing !== null) {
        $itemResult['product_id'] = (int)$existing->id;
        if ($updateExisting) {
          // aggiorna i dati dal file, preservando categoria/qta/visibilita' ecc.
          $existing->name = $name;
          $existing->autori = $authors;
          $existing->editore = $publisher;
          $existing->price = $prezzo_mercatino;
          $existing->prezzo_listino = $list_price;
          $mgr->update($existing, (int)$existing->id);
          $itemResult['updated'] = true;
        } else {
          $itemResult['skipped'] = true;
        }
        array_push($results, $itemResult);
        continue;
      }

      // Create product
      $p = new Product(0, $name, $prezzo_mercatino, $category_id, 0, null, null, $qta, $isbn, $authors, $publisher, '', 0);
      $p->prezzo_listino = $list_price;
      $id = $mgr->create($p);

      if ($id <= 0) {
        $itemResult['error'] = 'Errore nella creazione del prodotto';
        array_push($results, $itemResult);
        continue;
      }

      $itemResult['product_id'] = (int)$id;

      // Create image record
      $img = new ProductImage(0, $id, 'jpg');
      $imgId = $imgMgr->create($img);

      if ($imgId <= 0) {
        $itemResult['error'] = 'Errore nella creazione del record immagine';
        array_push($results, $itemResult);
        continue;
      }

      // Create directory if needed
      $dir = ROOT_PATH . 'images/' . $id;
      if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
      }

      // Download cover and process images
      try {
        $path = $dir . '/' . $imgId . '.jpg';
        $ok = BookLookup::downloadCover($isbn, $path);

        if ($ok) {
          // Process images with wallpaper and thumbnail
          ImageUtilities::wallpaper($path);
          ImageUtilities::thumbnail($path);
          $itemResult['cover'] = true;
        } else {
          // Delete image record if download failed
          $imgMgr->delete($imgId);
        }
      } catch (Exception $imgException) {
        // Delete image record if processing failed
        $imgMgr->delete($imgId);
        // Product remains valid, just without cover
      }

      array_push($results, $itemResult);

    } catch (Exception $e) {
      $itemResult['error'] = 'Eccezione: ' . $e->getMessage();
      array_push($results, $itemResult);
    }
  }

  echo json_encode(['results' => $results]);
  exit;
}

echo json_encode(['error' => 'Operazione non valida']);

} catch (Throwable $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>
