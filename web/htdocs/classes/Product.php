<?php

class ProductImage {
  public $id;
  public $product_id;
  public $image_extension;

  public $title;
  public $alt;

  public function __construct($id, $product_id, $image_extension, $title = '', $alt = '', $order_number = 0) {
    $this->id = (int)$id;
    $this->product_id = (int)$product_id;
    $this->image_extension = $image_extension;
    $this->title = $title;
    $this->alt = $alt;
    $this->order_number = (int) $order_number;

  }
}

class ProductImageManager extends DBManager {

  public function __construct(){
    parent::__construct();
    $this->columns = array( 'id', 'product_id', 'image_extension', 'title', 'alt', 'order_number');
    $this->tableName = 'product_images';
  }

  public function GetImagesPath() {
    return ROOT_PATH . '/images/';
  }

  public function getImages($productId) {
      $imgsArr = $this->db->prepare(
          "SELECT * FROM product_images WHERE product_id = ?",
          [(int)$productId]
      );

      $images = [];
      if ($imgsArr) {
          foreach ($imgsArr as $img) {
              $images[] = (object)$img;
          }
      }
      return $images;
  }
}

class Product {

  public $id;
  public $name;
  public $autori;
  public $price;
  public $prezzo_listino;
  public $category_id;
  public $data_inizio_sconto;
  public $data_fine_sconto;
  public $qta;
  public $ISBN;
  public $sconto;
  public $editore;
  public $nota_volumi;
  public $fl_esaurimento;
  public $nascosto = 0; // 1 = escluso dalla vendita (non visibile nello shop)

  public function __construct($id, $name, $price, $category_id, $sconto = 0, $data_inizio_sconto = NULL, $data_fine_sconto = NULL, $qta = 1, $ISBN, $autori, $editore, $nota_volumi = '', $fl_esaurimento = 0){
    $this->id = (int)$id;
    $this->name = $name;
    $this->price = (float)$price;
    $this->category_id = (int)$category_id;
    $this->sconto = (int)$sconto;
    $this->autori = $autori;
    $this->editore = $editore;
    $this->nota_volumi = $nota_volumi;
    $this->fl_esaurimento = (int)$fl_esaurimento;
    
    if($this->sconto>0){
      $this->data_inizio_sconto = $data_inizio_sconto == NULL ? '1900-01-01' : $data_inizio_sconto;
      $this->data_fine_sconto = $data_fine_sconto == NULL ? '2099-01-01' : $data_fine_sconto;
    }else{
      $this->data_inizio_sconto = $data_inizio_sconto == NULL ? '1900-01-01' : $data_inizio_sconto;
      $this->data_fine_sconto = $data_fine_sconto == NULL ? '1900-01-01' : $data_fine_sconto;
      
    }
    $this->qta = (int) $qta;
    $this->ISBN = $ISBN;

  }

  public static function CreateEmpty() {
    return new Product(0, "", 0, 0, 0, NULL, NULL, 0, "", "", "", "", 0);
  }

}

class ProductManager extends DBManager {

  public function __construct(){
    parent::__construct();
    $this->columns = array( 'id', 'name', 'price', 'prezzo_listino', 'category_id', 'sconto', 'data_inizio_sconto', 'data_fine_sconto', 'qta', 'ISBN', 'autori', 'editore', 'nota_volumi', 'fl_esaurimento', 'nascosto' );
    $this->tableName = 'product';
  }
  
  public function decreaseQuantity($productId) {
    $product = $this->get($productId);
    $product->qta = ((int)$product->qta) - 1;
    $this->update($product, $productId);
  }
  public function increaseQuantity($productId) {
    $product = $this->get($productId);
    $product->qta = ((int)$product->qta) + 1;
    $this->update($product, $productId);
  }

  public function MoveTempImages($tmpDir, $productId) {
      $imgMgr = new ProductImageManager();
      $imgPath = $imgMgr->GetImagesPath();
      // Sanitize directory names to prevent path traversal
      $tmpDir = basename($tmpDir);
      $productId = (int)$productId;

      rename("$imgPath/$tmpDir", "$imgPath/$productId");

      $files = scandir("$imgPath/$productId");

      foreach ($files as $file) {
          if (strpos($file, '.jpg') !== false) {
              $imgId = (int)str_replace(".jpg", "", $file);
              $this->db->execute(
                  "UPDATE product_images SET product_id = ? WHERE id = ?",
                  [$productId, $imgId]
              );
          }
      }
  }

  public function GetProductWithImages($productId) {
    $product = $this->_getProducts(0, $productId);
    if (!$product) {
      return NULL;
    }
    $product = $product[0];
    //var_dump($product); die;
    $imgMgr = new ProductImageManager();
    $images = $imgMgr->getImages($productId);
    $product->images = $images;
    return $product;
  }

  public function GetProductSubcategories($productId){
    $product = $this->get($productId);
    if (!isset($product->id)) {
      return [];
    }
    $cm = new CategoryManager();
    $subcats = $cm->GetCategoriesAndSubs($product->category_id, $productId);
    return $subcats;
  }

  public function GetProducts($categoryId) {
    return $this->_getProducts($categoryId);
  }

  private function _getProducts($categoryId = 0, $productId = 0, $search = '', $limit = '', $onlyVisible = false) {

    $products = $this->_getProductsQuery($categoryId, $productId, $search, $limit, $onlyVisible);
    
    $urlUtilities = new UrlUtilities('shop');

    foreach($products as $product){  

      $product->disc_price = NULL;
      if ($product->sconto != "0" && $product->data_inizio_sconto <= date('Y-m-d') && $product->data_fine_sconto >= date('Y-m-d')){
        $product->disc_price = $product->price - (($product->price * $product->sconto)/100.0);
      } 

      $product->url = $urlUtilities->product($product->id, $product->name);
    }
    
    $pm = new ProfileManager();
    $userDiscount = $pm->GetUserDiscount();
    if ($userDiscount > 0)  {
      foreach($products as $product){
        $product->price = number_format(($product->price - (($product->price * $userDiscount)/100)), 2, '.', '');
        if ( $product->disc_price != NULL) {
          $product->disc_price = number_format(($product->disc_price - (($product->disc_price * $userDiscount)/100)), 2, '.', '');
        }
      }
    }     

    return $products;
  }

  public function DeleteProduct($productId) {
    $this->delete($productId);
    $this->_deleteImagesFromFileSystem($productId);
    $this->_deleteImagesFromDB($productId);
  }

  public function DeleteTempImages($tmpDir){
    $this->_deleteImagesFromFileSystem($tmpDir);
  }

  public function AddQuantity($productId, $quantity) {
      $this->db->execute(
          "UPDATE product SET qta = qta + ? WHERE id = ?",
          [(int)$quantity, (int)$productId]
      );
  }

  public function SearchProducts($search) {

    $products = $this->_getProducts(0, 0, $search, 5, true);
    if (!$products) {
      return [];
    }

    $imgMgr = new ProductImageManager();
    foreach($products as $product) {
      $images = $imgMgr->getImages($product->id);
      $product->image_id = count($images) > 0 ? $images[0]->id : "0";
    }

    return $products;
  }

  public function getDiscountedPrice($productId){
    $product = $this->get($productId);
    if ($product->sconto == 0) {
      return null;
    }

    $now = date('Y-m-d');
    if ($product->data_inizio_sconto <= $now && $product->data_fine_sconto >= $now) {
      return round($product->price - (($product->sconto * $product->price)/100), 2);
    }
    return null;
  }

  // Private Methods
  private function _deleteImagesFromFileSystem($productId){
    $imgMgr = new ProductImageManager();
    $dirname = $imgMgr->GetImagesPath() . $productId;
    array_map('unlink', glob("$dirname/*.*"));
    if(is_dir($dirname))rmdir($dirname);
  }

  private function _deleteImagesFromDB($productId) {
      $this->db->execute(
          "DELETE FROM product_images WHERE product_id = ?",
          [(int)$productId]
      );
  }

  private function _getProductsQuery($categoryId = 0, $productId = 0, $search = '', $limit = '', $onlyVisible = false) {
      $params = [];
      $conditions = [];

      if ($categoryId > 0) {
          $conditions[] = "p.category_id = ?";
          $params[] = (int)$categoryId;
      }

      if ($productId > 0) {
          $conditions[] = "p.id = ?";
          $params[] = (int)$productId;
      }

      $searchClause = $this->_shopSearchClause($search, $params);
      if ($searchClause !== null) {
          $conditions[] = $searchClause;
      }

      if ($onlyVisible) {
          $conditions[] = "p.nascosto = 0";
      }

      $whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $limitClause = '';
      if ($limit !== '' && is_numeric($limit)) {
          $limitClause = "LIMIT " . (int)$limit;
      }

      $query = "
          SELECT p.*, c.name as category
          FROM product p
          INNER JOIN category c ON p.category_id = c.id
          $whereClause
          $limitClause
      ";

      $productsObjArr = [];
      $products = $this->db->prepare($query, $params);
      if ($products) {
          foreach ($products as $product) {
              $productsObjArr[] = (object)$product;
          }
      }
      return $productsObjArr;
  } 


  // 20250404: aggiunte per paginazione

  /**
   * Get the total count of products in a specific category
   *
   * @param int $categoryId The category ID to filter by (0 for all)
   * @return int Total count of products
   */
  /**
   * Costruisce la condizione di ricerca dello shop (titolo, ISBN, materia, autori).
   * Aggiunge i parametri a $params (per riferimento) e restituisce la clausola SQL,
   * oppure null se la ricerca e' vuota. La ricerca usa parametri preparati (LIKE ?).
   */
  private function _shopSearchClause($search, &$params) {
      $search = trim((string)$search);
      if ($search === '') {
          return null;
      }
      $term = '%' . $search . '%';
      $params[] = $term; // p.name
      $params[] = $term; // p.ISBN
      $params[] = $term; // c.name (materia)
      $params[] = $term; // p.autori
      return "(p.name LIKE ? OR p.ISBN LIKE ? OR c.name LIKE ? OR p.autori LIKE ?)";
  }

  public function GetProductsCount($categoryId = 0, $search = '') {
      $params = [];
      $conditions = ["p.nascosto = 0"]; // shop: esclude i libri nascosti

      if ($categoryId > 0) {
          $conditions[] = "p.category_id = ?";
          $params[] = (int)$categoryId;
      }

      $searchClause = $this->_shopSearchClause($search, $params);
      if ($searchClause !== null) {
          $conditions[] = $searchClause;
      }

      $whereClause = "WHERE " . implode(" AND ", $conditions);

      $query = "
          SELECT COUNT(*) as total
          FROM product p
          INNER JOIN category c ON p.category_id = c.id
          $whereClause
      ";

      $result = $this->db->prepare($query, $params);
      return isset($result[0]['total']) ? (int)$result[0]['total'] : 0;
  }

  /**
   * Get paginated products
   *
   * @param int $categoryId The category ID to filter by (0 for all)
   * @param int $offset Starting position for the query
   * @param int $limit Maximum number of records to return
   * @return array Array of product objects
   */
  public function GetProductsPaginated($categoryId = 0, $offset = 0, $limit = 12, $search = '') {
      $params = [];
      $conditions = ["p.nascosto = 0"]; // shop: esclude i libri nascosti

      if ($categoryId > 0) {
          $conditions[] = "p.category_id = ?";
          $params[] = (int)$categoryId;
      }

      $searchClause = $this->_shopSearchClause($search, $params);
      if ($searchClause !== null) {
          $conditions[] = $searchClause;
      }

      $whereClause = "WHERE " . implode(" AND ", $conditions);

      $params[] = (int)$offset;
      $params[] = (int)$limit;

      $query = "
          SELECT p.*, c.name as category
          FROM product p
          INNER JOIN category c ON p.category_id = c.id
          $whereClause
          ORDER BY p.name
          LIMIT ?, ?
      ";

      $productsObjArr = [];
      $products = $this->db->prepare($query, $params);

      if ($products) {
          $urlUtilities = new UrlUtilities('shop');
          foreach ($products as $product) {
              $productObj = (object)$product;

              // Add URL and check for discounts
              $productObj->url = $urlUtilities->product($productObj->id, $productObj->name);

              $productObj->disc_price = null;
              if ($productObj->sconto != "0" && $productObj->data_inizio_sconto <= date('Y-m-d') && $productObj->data_fine_sconto >= date('Y-m-d')) {
                  $productObj->disc_price = $productObj->price - (($productObj->price * $productObj->sconto) / 100.0);
              }

              $productsObjArr[] = $productObj;
          }
      }

      // Apply user discount if applicable
      $pm = new ProfileManager();
      $userDiscount = $pm->GetUserDiscount();
      if ($userDiscount > 0) {
          foreach ($productsObjArr as $product) {
              $product->price = number_format(($product->price - (($product->price * $userDiscount) / 100)), 2, '.', '');
              if ($product->disc_price !== null) {
                  $product->disc_price = number_format(($product->disc_price - (($product->disc_price * $userDiscount) / 100)), 2, '.', '');
              }
          }
      }

      return $productsObjArr;
  }

  /**
   * Find a product by ISBN
   * @param string $isbn ISBN to search
   * @return object|null Product object or null if not found
   */
  public function findByISBN($isbn) {
    $isbn = preg_replace('/[^0-9Xx]/', '', (string)$isbn);
    if ($isbn === '') return null;
    $rows = $this->db->prepare("SELECT * FROM product WHERE ISBN = ? LIMIT 1", [$isbn]);
    return ($rows && count($rows) > 0) ? (object)$rows[0] : null;
  }

  /**
   * Elenco dei prodotti attualmente visibili nello shop (nascosto = 0),
   * con il nome della categoria. Usato per l'anteprima prima della sincronizzazione.
   */
  public function GetVisibleProducts() {
    $rows = $this->db->prepare(
      "SELECT p.id, p.ISBN, p.name, p.qta, c.name AS category
       FROM product p
       LEFT JOIN category c ON c.id = p.category_id
       WHERE p.nascosto = 0
       ORDER BY c.name, p.name"
    );
    $out = [];
    if ($rows) {
      foreach ($rows as $r) {
        $out[] = (object)$r;
      }
    }
    return $out;
  }

  /**
   * Sincronizza la visibilita' nello shop con l'elenco di ISBN fornito:
   * rende visibili (nascosto=0) i prodotti il cui ISBN e' nell'elenco e
   * nasconde (nascosto=1) tutti gli altri.
   * Ritorna i conteggi risultanti, oppure ['error'=>...] se l'elenco e' vuoto.
   */
  public function SyncVisibilityByISBN($isbns) {
    $clean = [];
    foreach ((array)$isbns as $i) {
      $i = preg_replace('/[^0-9Xx]/', '', (string)$i);
      if ($i !== '') {
        $clean[$i] = true;
      }
    }
    $clean = array_keys($clean);
    if (count($clean) === 0) {
      return ['error' => 'Elenco ISBN vuoto: operazione annullata'];
    }

    $placeholders = implode(',', array_fill(0, count($clean), '?'));
    // nascondi i libri NON presenti nell'elenco
    $this->db->execute("UPDATE product SET nascosto = 1 WHERE ISBN NOT IN ($placeholders)", $clean);
    // rendi visibili i libri presenti nell'elenco
    $this->db->execute("UPDATE product SET nascosto = 0 WHERE ISBN IN ($placeholders)", $clean);

    $rows = $this->db->prepare(
      "SELECT
         SUM(CASE WHEN nascosto = 1 THEN 1 ELSE 0 END) AS nascosti,
         SUM(CASE WHEN nascosto = 0 THEN 1 ELSE 0 END) AS visibili,
         COUNT(*) AS totale
       FROM product"
    );
    $row = ($rows && count($rows) > 0) ? $rows[0] : ['nascosti' => 0, 'visibili' => 0, 'totale' => 0];
    return [
      'nascosti'  => (int)$row['nascosti'],
      'visibili'  => (int)$row['visibili'],
      'totale'    => (int)$row['totale'],
      'in_elenco' => count($clean),
    ];
  }
}