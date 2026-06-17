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

if ($op === 'import') {
  $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

  if (!is_array($items) || empty($items)) {
    echo json_encode(['error' => 'Items non forniti']);
    exit;
  }

  $mgr = new ProductManager();
  $imgMgr = new ProductImageManager();

  $results = [];

  foreach ($items as $item) {
    $isbn = isset($item['isbn']) ? trim($item['isbn']) : '';
    $name = isset($item['name']) ? trim($item['name']) : '';
    $authors = isset($item['authors']) ? esc(trim($item['authors'])) : '';
    $publisher = isset($item['publisher']) ? esc(trim($item['publisher'])) : '';
    $list_price = isset($item['list_price']) ? (float)$item['list_price'] : null;
    $prezzo_mercatino = isset($item['prezzo_mercatino']) ? (float)$item['prezzo_mercatino'] : null;
    $category_id = isset($item['category_id']) ? (int)$item['category_id'] : 0;
    $qta = isset($item['qta']) ? (int)$item['qta'] : 0;

    $itemResult = [
      'isbn' => $isbn,
      'product_id' => null,
      'cover' => false,
      'error' => null,
      'skipped' => false
    ];

    try {
      // Check if product already exists
      $existing = $mgr->findByISBN($isbn);
      if ($existing !== null) {
        $itemResult['product_id'] = (int)$existing->id;
        $itemResult['skipped'] = true;
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
exit;
?>
