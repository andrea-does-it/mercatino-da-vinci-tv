<?php

class Pratica {

  public $numPratica;

  public function __construct($numPratica){
    $this->numPratica = $numPratrica;
  }
}

class PraticaManager extends DBManager {

  public function __construct(){
    parent::__construct();
    $this->columns = array( 'numPratica' );
    $this->tableName = 'pratica';
  }

  public function updatePratica(){
    $query = "UPDATE pratica SET numPratica = numPratica+1"; 
    $result = $this->db->query($query);
    return $result;
  }

  public function GetnumPratica() {
    return $this->db->query("
      SELECT numPratica
      FROM pratica;
    ");
  }

}


class Cart {

    public $id;
    public $user_id;
    public $client_id;
    public $shipment_id;


    public function __construct($id, $user_id, $client_id, $shipment_id = 0){
      $this->id = $id > 0 ? $id : 0;
      $this->user_id = $user_id;
      $this->client_id = $client_id;
      $this->shipment_id = $shipment_id;
    }
  }

class CartItem {

    public $id;
    public $cart_id;
    public $product_id;
    public $quantity;

    public function __construct($id, $cart_id, $product_id, $quantity){
      $this->id = $id > 0 ? $id : 0;
      $this->cart_id = $cart_id;
      $this->product_id = $product_id;
      $this->quantity = $quantity;
    }
  }

class Order {

    public $id;
    public $numPratica;
    public $user_id;
    public $status;
    public $is_restored;
    public $is_email_sent;
    public $shipment_name;
    public $shipment_price;

    public function __construct($id, $numPratica, $user_id, $status, $is_restored = 0, $is_email_sent = 0, $shipment_name = NULL, $shipment_price = 0){
      $this->id = $id > 0 ? $id : 0;
      $this->numPratica = $numPratica;
      $this->user_id = $user_id;
      $this->status = $status;
      $this->is_restored = $is_restored;
      $this->is_email_sent = $is_email_sent;
      $this->shipment_name = $shipment_name;
      $this->shipment_price = $shipment_price;

    }
  }

class CartItemManager extends DBManager {

    public function __construct(){
      parent::__construct();
      $this->columns = array( 'id', 'cart_id', 'product_id', 'quantity' );
      $this->tableName = 'cart_item';
    }


  }

class OrderManager extends DBManager {
    public function __construct(){
      parent::__construct();
      $this->columns = array( 'id', 'numPratica', 'user_id', 'status', 'is_restored', 'is_email_sent', 'shipment_name', 'shipment_price' );
      $this->tableName = 'orders';
    }

    public function updateStatus($orderId, $status){
      $query = "UPDATE orders SET status = '$status', updated_at = CURRENT_TIMESTAMP() WHERE id = $orderId"; 
      $result = $this->db->query($query);
      return $result;
    }

    public function updateStatusItem($id, $status){
      $query = "UPDATE order_item SET status = '$status' WHERE id = $id"; 
      $result = $this->db->query($query);
      return $result;
    }

    public function updateStatusItem1($id, $status){
      $query = "UPDATE order_item SET status = '$status', updated_at = CURRENT_DATE() WHERE id = $id"; 
      $result = $this->db->query($query);
      return $result;
    }

    public function updateStatusItem2($id, $status){
      $query = "UPDATE order_item SET status = '$status', updated_at = NULL WHERE id = $id"; 
      $result = $this->db->query($query);
      return $result;
    }

    public function updatenumPratica($orderId, $pratica){
      $query = "UPDATE orders SET numPratica = '$pratica' WHERE id = $orderId"; 
      $result = $this->db->query($query);
      return $result;
    }

    public function updateStatusItem3($id, $status){
      $query = "UPDATE order_item1 SET status = '$status' WHERE id = $id"; 
      $result = $this->db->query($query);
      return $result;
    }

    public function removeOrderItem(){
      return $this->db->query("DELETE FROM order_item1");
    }

    public function getOrdersOfUser($userId, $status){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.created_at as created_date
          , o.updated_at as shipped_date
          , o.status as status
        FROM 
          orders o
        WHERE
          o.user_id = $userId
          AND ('$status' is NULL OR '$status' = o.status)
        ORDER BY
          o.created_at DESC;
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getEmailAndName($orderId){
      $result = $this->db->query("
        SELECT 
          u.email
          , u.first_name
          , u.last_name
        FROM 
          orders as o
          INNER JOIN user as u
            ON o.user_id = u.id
        WHERE 
          o.id = $orderId;
      ");
      //var_dump($result); die;
      return $result[0];
    }

    public function calcolaVendita($productId, $status){

      $orderMgr = new OrderManager();
      $item = $orderMgr->get($productId);

      $this->db->query("
        INSERT INTO order_item1 (product_id, quantity, single_price, status)
        SELECT 
          ci.product_id
          , ci.quantity
          , ci.single_price
          , ci.status
        FROM 
          order_item ci
        WHERE
          ci.id = $productId AND ci.status = '$status';
      "); 
    }

    public function createOrderFromCart($cartId, $userId){

      $cm = new CartManager();
      $cart = $cm->get($cartId);
      $sm = new ShipmentManager();
      $sh = $sm->get($cart->shipment_id);

      $orderId = $this->create(new Order(0, '', $userId, 'inviata', 0, 0, 'sede', 0.00));
      $this->db->query("
        INSERT INTO order_item (order_id, product_id, quantity, single_price)
        SELECT 
          $orderId
          , ci.product_id
          , ci.quantity
          , IF(p.sconto > 0 AND p.data_inizio_sconto <= NOW() AND p.data_fine_sconto >=  NOW(),
              CAST((p.price - (p.price * p.sconto)/100) AS DECIMAL(8,2)) 
              , ifnull(p.price, 0)) 
        FROM 
          cart c
          INNER JOIN cart_item ci
            ON c.id = ci.cart_id
          LEFT JOIN product p
            ON ci.product_id = p.id
        WHERE
          c.id = $cartId;
      ");

      // User Profile Discount
      $pm = new ProfileManager();
      $userDiscount = $pm->GetUserDiscount();
      if ($userDiscount > 0){
        $this->db->query("
          UPDATE order_item
          SET single_price = CAST(single_price - ((single_price * $userDiscount) / 100) AS DECIMAL(8,2)) 
          WHERE order_id = $orderId;
        ");
      }
        
      $this->db->query("
        DELETE cart, cart_item
          FROM cart
          INNER JOIN cart_item
          ON cart.id = cart_item.cart_id
        WHERE
          cart.id = $cartId;
      ");
      return $orderId;
    }

    public function getOrderTotal($orderId) {
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.user_id as user_id
          , SUM(ifnull(oi.quantity, 0)) as num_products
          , SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0)) as total
          , IFNULL(o.shipment_price, 0) AS shipment_price
          , IFNULL(o.shipment_name, 'N/D') AS shipment_name
        FROM 
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
        WHERE
        $orderId = o.id;
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderTotalAccettare($orderId) {
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.user_id as user_id
          , SUM(ifnull(oi.quantity, 0)) as num_products
          , SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0)) as total
          , IFNULL(o.shipment_price, 0) AS shipment_price
          , IFNULL(o.shipment_name, 'N/D') AS shipment_name
        FROM 
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
        WHERE
        $orderId = o.id AND oi.status = 'accettare';
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderTotalVendere($orderId) {
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.user_id as user_id
          , SUM(ifnull(oi.quantity, 0)) as num_products
          , SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price+2, 0)) as total
          , IFNULL(o.shipment_price, 0) AS shipment_price
          , IFNULL(o.shipment_name, 'N/D') AS shipment_name
        FROM 
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
        WHERE
        $orderId = o.id AND oi.status = 'vendere';
      ");
      //var_dump($result); die;
      return $result;
    }


    public function getOrderTotalVenduto($orderId) {
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.user_id as user_id
          , SUM(ifnull(oi.quantity, 0)) as num_products
          , SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price+2, 0)) as total
          , SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0)) as total_vend
          , SUM(ifnull(oi.quantity, 0) * 2) as total_com
          , IFNULL(o.shipment_price, 0) AS shipment_price
          , IFNULL(o.shipment_name, 'N/D') AS shipment_name
        FROM 
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
        WHERE
        $orderId = o.id AND oi.status = 'venduto';
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderTotalVenduto1() {
      $result = $this->db->query("
        SELECT 
          SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price+2, 0)) as total
        FROM 
          order_item as oi
        WHERE
        oi.status = 'venduto';
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderTotalVendutoPerData() {
      $result = $this->db->query("
        SELECT 
          SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price+2, 0)) as total
        FROM 
          order_item as oi
        WHERE
        CURRENT_DATE() = oi.updated_at AND oi.status = 'venduto';
      ");
      //var_dump($result); die;
      return $result;
    }


    public function getOrderTotalVendita() {
      $result = $this->db->query("
        SELECT 
          SUM(ifnull(oi.quantity, 0)) as num_products
          , SUM(ifnull(oi.quantity, 0) * ifnull(oi.single_price+2, 0)) as total
        FROM 
          order_item1 as oi
        WHERE
          oi.status = 'venduto';
      ");
      //var_dump($result); die;
      return $result;
    }


    public function getOrderItems($orderId){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , o.numPratica as pratica
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , p.nota_volumi as product_nota_volumi
          , u.last_name as last_name
          , u.first_name as first_name
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
          INNER JOIN user as u
          ON u.id = o.user_id
        WHERE
          (ifnull($orderId, 0) = 0
          OR $orderId = o.id);
      ");
      return $result;
    }


    public function getOrderItems1(){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , o.numPratica as pratica
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , u.last_name as last_name
          , u.first_name as first_name
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
          INNER JOIN user as u
           ON u.id = o.user_id
        WHERE
        oi.status = 'vendere';
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderItems2(){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , o.numPratica as pratica
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , u.last_name as last_name
          , u.first_name as first_name
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
          INNER JOIN user as u
           ON u.id = o.user_id
        WHERE
        oi.status = 'venduto';
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderItems3(){
      $result = $this->db->query("
        SELECT 
          oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price+1, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          order_item1 as oi
          INNER JOIN product as p
            ON p.id = oi.product_id
        WHERE
          oi.status = 'venduto';
      ");
      //var_dump($result); die;
      return $result;
    }

    public function getOrderItems4(){
      $result = $this->db->query("
        SELECT oi.quantity as quantita
          , o.id as order_id
          , o.status as order_status
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , u.last_name as last_name
          , u.first_name as first_name
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
          INNER JOIN user as u
           ON u.id = o.user_id
        WHERE
        oi.status = 'vendere';
      ");
      //var_dump($result); die;
      return $result;
    }


    public function getOrderItemsAccettare($orderId){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , p.nota_volumi as product_nota_volumi
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
        WHERE
          (ifnull($orderId, 0) = 0
          OR $orderId = o.id) AND o.status = 'inviata' AND oi.status = 'accettare';
      ");
      return $result;
    }

    public function getOrderItemsAccettare1($orderId){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
        WHERE
          (ifnull($orderId, 0) = 0
          OR $orderId = o.id) AND o.status = 'inviata' AND oi.status = 'vendere';
      ");
      //var_dump($result); die;
      return $result;
    }


    public function getOrderItemsVendere($orderId){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , p.nota_volumi as product_nota_volumi
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
        WHERE
        (ifnull($orderId, 0) = 0
          OR $orderId = o.id) AND oi.status = 'vendere';
      ");
      return $result;
    }

    public function getOrderItemsVenduto($orderId){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , p.nota_volumi as product_nota_volumi
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
        WHERE
          (ifnull($orderId, 0) = 0
          OR $orderId = o.id) AND o.status = 'accettata' AND oi.status = 'venduto';
      ");
      return $result;
    }

    public function getUserAddress($userId){
      $result = $this->db->query("SELECT street, city, cap FROM address WHERE user_id = $userId");
      //var_dump($result); die;
      return $result ? $result[0] : null;
    }
    
    public function getAllOrders($status){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.numPratica as pratica
          , o.created_at as created_date
          , o.updated_at as shipped_date
          , o.status as status
          , o.user_id as user_id
          , u.email as user_descr
          , u.first_name as user_name
          , u.last_name as user_surname
          , o.is_restored as is_restored
        FROM 
          orders o
          INNER JOIN user u
            ON o.user_id = u.id
        WHERE
          ('$status' is NULL OR '$status' = o.status)
        ORDER BY
          o.created_at DESC;
      ");
      //var_dump($result); die;
      return $result;
    }


    public function getAllOrders1($status, $user_id){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.numPratica as pratica
          , o.created_at as created_date
          , o.updated_at as shipped_date
          , o.status as status
          , o.user_id as user_id
          , u.email as user_descr
          , u.first_name as user_name
          , u.last_name as user_surname
          , o.is_restored as is_restored
        FROM 
          orders o
          INNER JOIN user u
            ON o.user_id = u.id
        WHERE
          ('$status' is NULL OR '$status' = o.status) AND (o.user_id = $user_id)
        ORDER BY
          o.created_at DESC;
      ");
      //var_dump($result); die;
      return $result;
    }

    
    public function SavePaymentDetails($orderId, $paymentCode, $paymentStatus, $paymentMethod, $status = NULL){
      if ($status == NULL){
        $status = $paymentStatus == 'approved' ? 'payed' : 'canceled';
      }

      $this->db->query("
        UPDATE orders
        SET 
          payment_code = '$paymentCode'
          , payment_status = '$paymentStatus'
          , status = '$status'
          , payment_method = '$paymentMethod'
          , updated_at = NOW()
        WHERE
          id = $orderId;
      ");
    }

    public function GetByUserIdAndPaymentCode($userId, $paymentCode) {
      return $this->db->query("
        SELECT *
        FROM orders
        WHERE
          user_id = $userId
          AND payment_code = '$paymentCode';
      ");
    }

    public function RestoreOrderQuantity($orderId){
      $this->db->query("
        UPDATE 
          orders o 
          INNER JOIN order_item oi 
            ON o.id = oi.order_id 
          INNER JOIN product p 
            ON p.id = oi.product_id 
        SET 
          p.qta = (p.qta + oi.quantity) 
        WHERE 
          o.id = $orderId
          AND o.status = 'canceled' 
          AND IFNULL(o.is_restored, 0) = 0;
      ");

      $this->db->query("
        UPDATE orders
        SET is_restored = 1
        WHERE id = $orderId;
      ");
    }

    public function sendAcceptanceEmail($orderId, $email, $first_name, $last_name, $pratica, $orderItems, $orderTotal) {
      $br = "\r\n";
      $to = $email;
      $subject = "Richiesta N. " . $orderId . " è stata accettata";
      $message = "<h2>La sua Richiesta è stata accettata e le è stato assegnato il seguente numero di Pratica " . $pratica . "</h2>";
  
      $headers = "From: Comitato Genitori Da Vinci - TV <mercatino@comitatogenitoridavtv.it>\r\n";
      $headers .= "MIME-Version: 1.0\r\n";
      $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
  
      $style = "style='border: 1px solid black; border-collapse: collapse;'";
      $br = "<br>";
      $message .= $br . "<h3>Riepilogo Pratica:</h3>";
  
      // $mailBody = "<table $style><tr><th $style>Titolo</th><th $style >Prezzo Unitario</th><th $style >N. Pezzi</th><th $style >Importo</th></tr>";
      // foreach($orderItems as $item) {
      //     $mailBody .= "<tr><td $style>".$item['product_name']."</td><td $style>".$item['single_price']."</td><td $style>".$item['quantity']."</td><td $style>".$item['total_price']."</td></tr>";
      // }
      // $mailBody .= "<tr><td $style colspan='4'>Totale €". (number_format((float)($orderTotal['total'] + $orderTotal['shipment_price']), 2, '.', '')) . "</td></tr>";
      // $mailBody .= "</table>";
      
      $acceptedTotal = 0;
      foreach($orderItems as $item) {
          // Only include accepted items (vendere status) in the email
          if ($item['order_item_status'] == 'vendere') {
              $mailBody .= "<tr><td $style>".$item['product_name']."</td><td $style>".$item['single_price']."</td><td $style>".$item['quantity']."</td><td $style>".$item['total_price']."</td></tr>";
              $acceptedTotal += $item['total_price'];
          }
      }
      $mailBody .= "<tr><td $style colspan='4'>Totale €". number_format((float)$acceptedTotal, 2, '.', '') . "</td></tr>";
      $mailBody .= "</table>"; 
      
      
      $message .= $mailBody . $br;
      $parameters = "-f mercatino@comitatogenitoridavtv.it";
  
      $result = mail($to, $subject, $message, $headers, $parameters);
      
      // Update the is_email_sent flag
      $order = $this->get($orderId);
      $order->is_email_sent = 1;
      $this->update($order, $orderId);
      
      return $result;
    }    

    public function getOrderItemsEliminato($orderId){
      $result = $this->db->query("
        SELECT 
          o.id as order_id
          , o.status as order_status
          , oi.id as order_item_id
          , oi.status as order_item_status
          , p.name as product_name
          , p.id as product_id
          , p.ISBN as product_ISBN
          , p.nota_volumi as product_nota_volumi
          , ifnull(oi.quantity, 0) as quantity
          , ifnull(oi.single_price, 0) as single_price
          , ifnull(oi.quantity, 0) * ifnull(oi.single_price, 0) as total_price
        FROM
          orders as o
          INNER JOIN order_item as oi
            ON o.id = oi.order_id
          INNER JOIN product as p
            ON p.id = oi.product_id
        WHERE
          (ifnull($orderId, 0) = 0
          OR $orderId = o.id) AND oi.status = 'eliminato';
      ");
      return $result;
    }

}

  

class CartManager extends DBManager {

    private $userId;
    private $clientId;
    private $cartItemMgr;

    public function __construct(){
      parent::__construct();

      //global $loggedInUser;

      $this->userId = isset($_SESSION['user']) ? unserialize($_SESSION['user'])->id : 0;
      // $this->clientId = isset($_COOKIE['client_id']) ? $_COOKIE['client_id'] : random_string();
      $this->clientId = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : random_string();

      $_SESSION['client_id'] = $this->clientId;
      //var_dump($this->clientId, $_SESSION);

      $this->columns = array( 'id', 'user_id', 'client_id', 'shipment_id' );
      $this->tableName = 'cart';

      $this->cartItemMgr = new CartItemManager();
    }

    private function quantityInCart($productId, $cartId) {
      $quantity = 0;
      $results = $this->db->query("SELECT quantity FROM cart_item WHERE cart_id = '$cartId' AND product_id = '$productId'");
      if(count($results) > 0) {
        $quantity = (int)$results[0]['quantity'];
      }
      return $quantity;
    }

    private function incrementByOne($productId, $cartId, $quantityInCart){
      $quantityInCart++;
      $this->db->query("UPDATE cart_item SET quantity = $quantityInCart WHERE cart_id = '$cartId' AND product_id = '$productId'");
    }

    private function decrementOne($productId, $cartId, $quantityInCart){
      $quantityInCart--;
      $this->db->query("UPDATE cart_item SET quantity = $quantityInCart WHERE cart_id = '$cartId' AND product_id = '$productId'");
    }

    private function createItem($productId, $cartId){
      $item_id = $this->cartItemMgr->create(new CartItem(0, $cartId, $productId, 1));
      //var_dump($item_id); die;
      return $item_id;
    }

    public function clearCart($cartId){
      if($this->userId) {
        $this->db->query("
          DELETE cart, cart_item 
          FROM cart 
          INNER JOIN cart_item ON cart.id = cart_item.cart_id 
          WHERE cart.user_id = '$this->userId' AND cart.id NOT IN ('$cartId');
        ");
      } else if ($this->clientIp) {
        $this->db->query(
          "DELETE cart, cart_item 
          FROM cart 
          INNER JOIN cart_item ON cart.id = cart_item.cart_id 
          WHERE cart.client_id = '$this->clientIp' AND cart.id NOT IN ('$cartId');
        ");
      }
    }

    public function isEmptyCart($cartId){
      $results = $this->db->query("SELECT 1 FROM cart_item WHERE cart_id = '$cartId'"); 
      return count($results) == 0;
    }

    private function createCart(){

      $client_id = $this->userId > 0 ? '' : $this->clientId;
      $cart_id = $this->create(new Cart(0, $this->userId, $client_id)); 
      return $cart_id;
    }

    private function removeItem($productId, $cartId){
      return $this->db->query("DELETE FROM cart_item WHERE cart_id = '$cartId' AND product_id = '$productId'");
    }

    private function clearUserCart() {
      if($this->userId) {
        $this->db->query('DELETE cart, cart_item FROM cart INNER JOIN cart_item ON cart.id = cart_item.cart_id WHERE cart.user_id = ' . $this->userId );
      }
    }

    public function mergeCarts(){

      $oldUserCart = $this->db->query("SELECT id FROM cart where user_id = $this->userId");
      $oldClientCart = $this->db->query("SELECT id FROM cart where client_id = '$this->clientId'");
      //var_dump($oldUserCart, $oldClientCart, $this->userId, $this->clientId); die;
      if (count($oldClientCart) > 0 AND count($oldUserCart) == 0){
        $result = $this->db->query("UPDATE cart SET user_id = $this->userId, client_id = '' WHERE client_id = '$this->clientId'");
      }

      else if (count($oldClientCart) > 0 AND count($oldUserCart) > 0 ) {

        $userCartId = $oldUserCart[0]['id'];
        $userCartItems = $this->getCartItems($userCartId);

        $clientCartId = $oldClientCart[0]['id'];
        $clientCartItems = $this->getCartItems($clientCartId);
        

        foreach($clientCartItems as $clientItem){
          
          $isAlreadyInCart = false;
          $clientProductId = $clientItem['product_id'];

          foreach($userCartItems as $userItem){
            if ($userItem['product_id'] == $clientProductId){
              $isAlreadyInCart = true;
              $newQuantity = $userItem['quantity'] + $clientItem['quantity'];
              $this->db->query("UPDATE cart_item SET quantity = $newQuantity  WHERE cart_id = $userCartId AND product_id = $clientProductId");
              $this->db->query("DELETE FROM cart_item WHERE cart_id = $clientCartId AND product_id = $clientProductId");
              break;
            }
          }

          if (!$isAlreadyInCart) {
            $this->db->query("UPDATE cart_item SET cart_id = $userCartId  WHERE cart_id = $clientCartId AND product_id = $clientProductId");
          }
        }

        $result = $this->db->query("DELETE FROM cart WHERE id = $clientCartId");
      }

      unset($_SESSION['client_id']);
      return $result;
    }


    public function addToCart($productId, $cartId) {

      // Check if product is available for purchase (not marked as out of stock)
      $prodMgr = new ProductManager();
      $product = $prodMgr->get($productId);
      
      if (!$product || $product->fl_esaurimento == 1) {
        // Product is marked as out of stock/unavailable, cannot add to cart
        return false;
      }

      $quantityInCart = $this->quantityInCart($productId, $cartId);

      if ($quantityInCart > 0){
        // Non incremento la quantità nel carrello se il prodotto esiste già
        // $this->incrementByOne($productId, $cartId, $quantityInCart);
      } else {
        $this->createItem($productId, $cartId);
      }
      $this->_updateCartLastInteraction($cartId);
      $prodMgr->decreaseQuantity($productId);
      
      return true;
    }

    public function removeFromCart($productId, $cartId) {

      $quantityInCart = $this->quantityInCart($productId, $cartId);

      if ($quantityInCart > 1){
        $this->decrementOne($productId, $cartId, $quantityInCart);
      } else {
        $this->removeItem($productId, $cartId);
      }
      $this->_updateCartLastInteraction($cartId);
      $prodMgr = new ProductManager();
      $prodMgr->increaseQuantity($productId);
    }

    public function getCurrentCartId(){
      $cartId = 0;

      if (!$this->userId) {
        //var_dump($this->clientId, $_SESSION['client_id']); die;
        $result = $this->db->query("SELECT id FROM cart WHERE client_id = '$this->clientId'"); 
        if (count($result) == 0) {
          $cartId = $this->createCart();
        } else {
          $cartId = $result[0]['id'];
        }
      } else {
        $result = $this->db->query("SELECT id FROM cart WHERE user_id = $this->userId");
        if (count($result) == 0) {
          $cartId = $this->createCart();
        } else {
          $cartId = $result[0]['id'];
        }
      }
        
      return $cartId;
    }

    public function getCurrentUserId(){
      $userId = 0;

      $result = $this->db->query("SELECT user_id FROM cart WHERE user_id = $this->userId");
      $userId = $result[0]['user_id'];
        
      return $userId;
    }

    public function getCartTotal($cartId) {
      $total = $this->db->query(" 
        SELECT 
          c.id as cart_id
          , c.user_id as user_id
          , SUM(ifnull(ci.quantity, 0)) as num_products
          , sum(ifnull(ci.quantity,0) * IF(p.sconto>0 AND data_inizio_sconto <= DATE(NOW()) AND data_fine_sconto >= DATE(NOW()),
              CAST((p.price - (p.price * p.sconto) / 100) AS DECIMAL(8,2)) 
              ,ifnull(p.price, 0))) 
            as total
          , IFNULL(s.id, 0) as shipment_id
          , IFNULL(s.price, 0) as shipment_price
        FROM 
          cart as c
          INNER JOIN cart_item as ci
            ON c.id = ci.cart_id
          INNER JOIN product as p
            ON ci.product_id = p.id
          LEFT JOIN shipment s
            ON s.id = c.shipment_id
        WHERE
          $cartId = c.id;"
      );

      $pm = new ProfileManager();
      $userDiscount = $pm->GetUserDiscount();
      if ($userDiscount > 0){
        foreach($total as $i => $tot){
          $tot['total'] = number_format($tot['total'] - (($tot['total'] * $userDiscount)/100), 2, '.', '') ;
          array_splice($total, $i, 1, [$i => $tot]);
        }
      }

      return $total;
    }

    public function getCartItems($cartId){
      return $this->_getCartItems($cartId);
    }

    public function ResetExpiredCarts() {
      $expiredCarts = $this->db->query("
        SELECT id
        FROM cart
        WHERE TIMESTAMPDIFF(MINUTE, last_interaction, NOW()) > 30;
      ");
      if (count($expiredCarts) == 0 ) return;
      
      foreach ($expiredCarts as $cart) { 
        $this->_clearCart($cart['id']);
      }
      return $expiredCarts;
    }

    public function setShipmentMethod($cartId, $shipmentMethod){
      $this->db->query("
        UPDATE cart
        SET shipment_id = $shipmentMethod
        WHERE id = $cartId;
      ");
    }

    // Privare Methods

    private function _updateCartLastInteraction($cartId) {
      $this->db->query("
        UPDATE cart
        SET last_interaction = NOW()
        WHERE id = $cartId;
      ");
    }

    private function _clearCart($cartId) {
      // Get all items in this cart
      $cartItems = $this->_getCartItems($cartId);
      // if (count($cartItems) == 0) return;
      
      // For each item in the cart
      $pm = new ProductManager();
      foreach ($cartItems as $item) {
          // Add the quantity back to the product's inventory
          $pm->AddQuantity($item['product_id'], $item['quantity']);
      }
  
      // First delete the cart items
      $this->db->query("
          DELETE FROM cart_item 
          WHERE cart_id = $cartId;
      ");
      
      // Then delete the cart itself
      $this->db->query("
          DELETE FROM cart 
          WHERE id = $cartId;
      ");
    }    

    private function _getCartItems($cartId) {
      $cartItems = $this->db->query("
        SELECT 
          c.id as cart_id
          , ci.id as cart_item_id
          , p.name as product_name
          , p.ISBN as product_ISBN
          , p.id as product_id
          , ifnull(ci.quantity, 0) as quantity
          , IF(p.sconto>0 AND p.data_inizio_sconto <= DATE(NOW()) AND p.data_fine_sconto >= DATE(NOW()),
              CAST((p.price -(p.price*p.sconto)/100) AS DECIMAL(8,2)) 
              ,ifnull(p.price, 0))AS single_price
          , ifnull(ci.quantity,0) * IF(p.sconto>0 AND data_inizio_sconto <= DATE(NOW()) AND data_fine_sconto >= DATE(NOW()),
              CAST((price -(price*sconto)/100) AS DECIMAL(8,2)) 
              ,ifnull(price, 0)) as total_price
          , ifnull(p.qta, 0) as available_quantity
        FROM
          cart as c
          INNER JOIN cart_item as ci
            ON c.id = ci.cart_id
          INNER JOIN product as p
            ON p.id = ci.product_id
        WHERE
          ifnull($cartId, 0) = 0
          OR $cartId = c.id;
      ");

      $pm = new ProfileManager();
      $userDiscount = $pm->GetUserDiscount();
      if ($userDiscount > 0){
        foreach($cartItems as $i => $ci){
          $ci['single_price'] = number_format($ci['single_price'] - (($ci['single_price'] * $userDiscount)/100), 2, '.', '') ;
          $ci['total_price'] = number_format($ci['total_price'] - (($ci['total_price'] * $userDiscount)/100), 2, '.', '');
          array_splice($cartItems, $i, 1, [$i => $ci]);
        }
      }

      return $cartItems;
    }

}