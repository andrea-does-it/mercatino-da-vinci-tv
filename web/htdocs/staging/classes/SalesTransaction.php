<?php

/**
 * SalesTransaction - Represents a sales transaction
 */
class SalesTransaction {

    public $id;
    public $payment_method;
    public $description;
    public $operator_id;
    public $total_amount;
    public $created_at;
    public $updated_at;

    public function __construct($id, $payment_method, $description = null, $operator_id = null, $total_amount = 0.00, $created_at = null, $updated_at = null) {
        $this->id = (int)$id;
        $this->payment_method = $payment_method;
        $this->description = $description;
        $this->operator_id = $operator_id ? (int)$operator_id : null;
        $this->total_amount = (float)$total_amount;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
    }

    public static function CreateEmpty() {
        return new SalesTransaction(0, 'cash', null, null, 0.00, null, null);
    }
}

/**
 * SalesTransactionItem - Represents an item in a sales transaction
 */
class SalesTransactionItem {

    public $id;
    public $sales_transaction_id;
    public $order_item_id;
    public $price;
    public $created_at;

    public function __construct($id, $sales_transaction_id, $order_item_id, $price, $created_at = null) {
        $this->id = (int)$id;
        $this->sales_transaction_id = (int)$sales_transaction_id;
        $this->order_item_id = (int)$order_item_id;
        $this->price = (float)$price;
        $this->created_at = $created_at;
    }

    public static function CreateEmpty() {
        return new SalesTransactionItem(0, 0, 0, 0.00, null);
    }
}

/**
 * SalesTransactionManager - Handles CRUD operations for sales transactions
 */
class SalesTransactionManager extends DBManager {

    public function __construct() {
        parent::__construct();
        $this->columns = array('id', 'payment_method', 'description', 'operator_id', 'total_amount', 'created_at', 'updated_at');
        $this->tableName = 'sales_transaction';
    }

    /**
     * Get all valid payment methods
     * @return array
     */
    public static function getPaymentMethods() {
        return [
            'cash' => 'Contanti',
            'POS' => 'POS',
            'satispay' => 'Satispay',
            'paypal' => 'PayPal'
        ];
    }

    /**
     * Get all books available for sale (order_item with status 'vendere')
     * @param string|null $search Search term for ISBN, product name, or pratica
     * @return array
     */
    public function getAvailableBooksForSale($search = null) {
        $params = [];
        $searchCondition = '';

        if ($search !== null && $search !== '') {
            $searchCondition = "AND (p.ISBN LIKE ? OR p.name LIKE ? OR o.numPratica LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query = "
            SELECT
                oi.id as order_item_id,
                oi.single_price,
                oi.quantity,
                p.id as product_id,
                p.name as product_name,
                p.ISBN as isbn,
                p.nota_volumi,
                o.id as order_id,
                o.numPratica as pratica,
                u.first_name as seller_first_name,
                u.last_name as seller_last_name
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN product p ON oi.product_id = p.id
            INNER JOIN user u ON o.user_id = u.id
            WHERE oi.status = 'vendere'
            AND o.numPratica > 0
            $searchCondition
            ORDER BY p.name ASC
        ";

        $results = $this->db->prepare($query, $params);
        $items = [];
        foreach ($results as $result) {
            // Calculate sale price (single_price + bookshop markup)
            $result['sale_price'] = (float)$result['single_price'] + SiteSettings::totalMarkup();
            $items[] = (object)$result;
        }
        return $items;
    }

    /**
     * Create a new sales transaction with items
     * This will also update the order_item status from 'vendere' to 'venduto'
     *
     * @param string $paymentMethod Payment method (cash, POS, satispay, paypal)
     * @param string|null $description Customer name or note
     * @param int|null $operatorId User ID of the operator
     * @param array $orderItemIds Array of order_item IDs to sell
     * @return int|false The ID of the created transaction or false on failure
     */
    public function createTransaction($paymentMethod, $description, $operatorId, $orderItemIds = []) {
        if (count($orderItemIds) == 0) {
            return false;
        }

        $orderMgr = new OrderManager();

        // Calculate total amount from order items
        $totalAmount = 0.00;
        $validItems = [];

        foreach ($orderItemIds as $orderItemId) {
            $orderItemId = (int)$orderItemId;
            // Verify the item is still available (status = 'vendere')
            $itemInfo = $this->getOrderItemForSale($orderItemId);
            if ($itemInfo) {
                $salePrice = (float)$itemInfo->single_price + SiteSettings::totalMarkup();
                $totalAmount += $salePrice;
                $validItems[] = [
                    'order_item_id' => $orderItemId,
                    'price' => $salePrice
                ];
            }
        }

        if (count($validItems) == 0) {
            return false;
        }

        // Create the main transaction - only include fields needed for INSERT
        $transactionData = [
            'payment_method' => $paymentMethod,
            'description' => $description,
            'operator_id' => $operatorId,
            'total_amount' => $totalAmount
        ];
        $transactionId = $this->db->insert_one($this->tableName, $transactionData);

        if (!$transactionId) {
            return false;
        }

        // Create the items and update order_item status
        foreach ($validItems as $item) {
            // Create sales transaction item - only include fields needed for INSERT
            $itemData = [
                'sales_transaction_id' => $transactionId,
                'order_item_id' => $item['order_item_id'],
                'price' => $item['price']
            ];
            $this->db->insert_one('sales_transaction_item', $itemData);

            // Update order_item status to 'venduto' and set date
            $orderMgr->updateStatusItem1($item['order_item_id'], 'venduto');

            // Also call calcolaVendita as in the original libri_da_vendere.php
            $orderMgr->calcolaVendita($item['order_item_id'], 'venduto');
        }

        return $transactionId;
    }

    /**
     * Get an order_item that is available for sale
     * @param int $orderItemId
     * @return object|null
     */
    private function getOrderItemForSale($orderItemId) {
        $query = "
            SELECT
                oi.id as order_item_id,
                oi.single_price,
                oi.status,
                p.name as product_name,
                p.ISBN as isbn,
                o.numPratica as pratica
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN product p ON oi.product_id = p.id
            WHERE oi.id = ? AND oi.status = 'vendere'
        ";
        $result = $this->db->prepare($query, [(int)$orderItemId]);
        return $result ? (object)$result[0] : null;
    }

    /**
     * Refund an item from a transaction
     * This will remove the item from the transaction and change order_item status back to 'vendere'
     *
     * @param int $salesTransactionItemId
     * @param string|null $notes Optional refund note
     * @return bool
     */
    /**
     * Refund a single item from a transaction (soft-delete: marks item as refunded)
     * If all items in the transaction are refunded, the transaction header is also marked as refunded.
     *
     * @param int $salesTransactionItemId
     * @param string|null $notes Optional refund note
     * @param int|null $refundedBy User ID of who performed the refund
     * @return bool
     */
    public function refundItem($salesTransactionItemId, $notes = null, $refundedBy = null) {
        $itemMgr = new SalesTransactionItemManager();
        $item = $itemMgr->get($salesTransactionItemId);

        if (!$item || !isset($item->order_item_id)) {
            return false;
        }

        // Skip if already refunded
        if (!empty($item->refunded_at)) {
            return false;
        }

        $orderItemId = $item->order_item_id;
        $transactionId = $item->sales_transaction_id;

        $orderMgr = new OrderManager();

        // Get the product_id from order_item before changing status
        $orderItemDetails = $orderMgr->getOrderItemById($orderItemId);
        if (!$orderItemDetails) {
            return false;
        }
        $productId = $orderItemDetails['product_id'];

        // Change order_item status back to 'vendere'
        $orderMgr->updateStatusItem2($orderItemId, 'vendere');

        // Store refund note on order_item
        if ($notes !== null && trim($notes) !== '') {
            $this->db->execute(
                "UPDATE order_item SET refund_notes = ? WHERE id = ?",
                [trim($notes), (int)$orderItemId]
            );
        }

        // Remove from order_item1 table (reverse of calcolaVendita)
        $orderMgr->removeFirstOrderItem1($productId);

        // Soft-delete: mark the transaction item as refunded
        $this->db->execute(
            "UPDATE sales_transaction_item SET refunded_at = NOW(), refunded_by = ?, refund_notes = ? WHERE id = ?",
            [$refundedBy, $notes !== null ? trim($notes) : null, (int)$salesTransactionItemId]
        );

        // Update transaction total (only counts non-refunded items)
        $this->updateTransactionTotal($transactionId);

        // If all items are now refunded, mark the transaction header as refunded too
        $this->checkAndMarkTransactionRefunded($transactionId, $notes, $refundedBy);

        return true;
    }

    /**
     * Refund entire transaction (soft-delete: marks as refunded, keeps items for audit trail)
     * @param int $transactionId
     * @param string|null $notes Optional refund note (applied to all items)
     * @param int|null $refundedBy User ID of who performed the refund
     * @return bool
     */
    public function refundTransaction($transactionId, $notes = null, $refundedBy = null) {
        $transaction = $this->getTransactionWithItems($transactionId);
        if (!$transaction || count($transaction->items) == 0) {
            return false;
        }

        $orderMgr = new OrderManager();

        // Restore each item to 'vendere' status without deleting transaction items
        foreach ($transaction->items as $item) {
            $orderItemId = $item->order_item_id;

            $orderItemDetails = $orderMgr->getOrderItemById($orderItemId);
            if (!$orderItemDetails) {
                continue;
            }
            $productId = $orderItemDetails['product_id'];

            // Change order_item status back to 'vendere'
            $orderMgr->updateStatusItem2($orderItemId, 'vendere');

            // Store refund note on order_item
            if ($notes !== null && trim($notes) !== '') {
                $this->db->execute(
                    "UPDATE order_item SET refund_notes = ? WHERE id = ?",
                    [trim($notes), (int)$orderItemId]
                );
            }

            // Remove from order_item1 table (reverse of calcolaVendita)
            $orderMgr->removeFirstOrderItem1($productId);
        }

        // Soft-delete: mark transaction as refunded (keep the row and items for history)
        $this->db->execute(
            "UPDATE sales_transaction SET refunded_at = NOW(), refunded_by = ?, refund_notes = ? WHERE id = ?",
            [$refundedBy, $notes !== null ? trim($notes) : null, (int)$transactionId]
        );

        return true;
    }

    /**
     * Get a transaction with all its items and related info
     * @param int $transactionId
     * @return object|null Transaction with items property
     */
    public function getTransactionWithItems($transactionId) {
        // Use direct query to avoid issues with DBManager->get() returning empty object
        $query = "SELECT * FROM sales_transaction WHERE id = ?";
        $result = $this->db->prepare($query, [(int)$transactionId]);

        if (!$result || count($result) == 0) {
            return null;
        }

        $transaction = (object)$result[0];

        $itemMgr = new SalesTransactionItemManager();
        $transaction->items = $itemMgr->getItemsByTransactionWithDetails($transactionId);

        return $transaction;
    }

    /**
     * Get all transactions with pagination
     * @param int $offset
     * @param int $limit
     * @param string|null $paymentMethod Filter by payment method
     * @param string|null $dateFrom Filter from date (Y-m-d)
     * @param string|null $dateTo Filter to date (Y-m-d)
     * @return array
     */
    public function getTransactionsPaginated($offset = 0, $limit = 20, $paymentMethod = null, $dateFrom = null, $dateTo = null) {
        $params = [];
        $conditions = [];

        if ($paymentMethod !== null && $paymentMethod !== '') {
            $conditions[] = "st.payment_method = ?";
            $params[] = $paymentMethod;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = "DATE(st.created_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = "DATE(st.created_at) <= ?";
            $params[] = $dateTo;
        }

        $whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $params[] = (int)$offset;
        $params[] = (int)$limit;

        $query = "
            SELECT st.*, u.first_name as operator_first_name, u.last_name as operator_last_name,
                   ru.first_name as refunded_by_first_name, ru.last_name as refunded_by_last_name,
                   (SELECT COUNT(*) FROM sales_transaction_item WHERE sales_transaction_id = st.id) as item_count
            FROM sales_transaction st
            LEFT JOIN user u ON st.operator_id = u.id
            LEFT JOIN user ru ON st.refunded_by = ru.id
            $whereClause
            ORDER BY st.created_at DESC
            LIMIT ?, ?
        ";

        $results = $this->db->prepare($query, $params);
        $transactions = [];
        foreach ($results as $result) {
            $transactions[] = (object)$result;
        }
        return $transactions;
    }

    /**
     * Get total count of transactions (for pagination)
     * @param string|null $paymentMethod
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return int
     */
    public function getTransactionsCount($paymentMethod = null, $dateFrom = null, $dateTo = null) {
        $params = [];
        $conditions = [];

        if ($paymentMethod !== null && $paymentMethod !== '') {
            $conditions[] = "payment_method = ?";
            $params[] = $paymentMethod;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
        }

        $whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $query = "SELECT COUNT(*) as total FROM sales_transaction $whereClause";
        $result = $this->db->prepare($query, $params);
        return isset($result[0]['total']) ? (int)$result[0]['total'] : 0;
    }

    /**
     * Check if a transaction has been refunded
     * @param object $transaction
     * @return bool
     */
    public function isRefunded($transaction) {
        return !empty($transaction->refunded_at);
    }

    /**
     * Get total sales amount for a period
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param string|null $paymentMethod
     * @return array with totals by payment method
     */
    public function getSalesTotals($dateFrom = null, $dateTo = null, $paymentMethod = null) {
        $params = [];
        $conditions = ["refunded_at IS NULL"];

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
        }

        if ($paymentMethod !== null && $paymentMethod !== '') {
            $conditions[] = "payment_method = ?";
            $params[] = $paymentMethod;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $query = "
            SELECT
                payment_method,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_amount
            FROM sales_transaction
            $whereClause
            GROUP BY payment_method
        ";

        $results = $this->db->prepare($query, $params);

        $totals = [
            'by_method' => [],
            'grand_total' => 0.00,
            'transaction_count' => 0
        ];

        foreach ($results as $row) {
            $totals['by_method'][$row['payment_method']] = [
                'count' => (int)$row['transaction_count'],
                'amount' => (float)$row['total_amount']
            ];
            $totals['grand_total'] += (float)$row['total_amount'];
            $totals['transaction_count'] += (int)$row['transaction_count'];
        }

        return $totals;
    }

    /**
     * Delete a transaction (use refundTransaction instead if you need to restore items)
     * @param int $transactionId
     * @return bool
     */
    public function deleteTransaction($transactionId) {
        // Items will be deleted automatically due to ON DELETE CASCADE
        return $this->delete($transactionId) > 0;
    }

    /**
     * Recalculate and update transaction total from non-refunded items
     * @param int $transactionId
     */
    private function updateTransactionTotal($transactionId) {
        $query = "
            UPDATE sales_transaction
            SET total_amount = (
                SELECT COALESCE(SUM(price), 0)
                FROM sales_transaction_item
                WHERE sales_transaction_id = ? AND refunded_at IS NULL
            )
            WHERE id = ?
        ";
        $this->db->execute($query, [(int)$transactionId, (int)$transactionId]);
    }

    /**
     * Check if all items in a transaction are refunded; if so, mark the header as refunded
     * @param int $transactionId
     * @param string|null $notes
     * @param int|null $refundedBy
     */
    private function checkAndMarkTransactionRefunded($transactionId, $notes = null, $refundedBy = null) {
        $result = $this->db->prepare(
            "SELECT COUNT(*) as total, SUM(CASE WHEN refunded_at IS NULL THEN 1 ELSE 0 END) as active
             FROM sales_transaction_item WHERE sales_transaction_id = ?",
            [(int)$transactionId]
        );

        if ($result && (int)$result[0]['active'] === 0) {
            $this->db->execute(
                "UPDATE sales_transaction SET refunded_at = NOW(), refunded_by = ?, refund_notes = ? WHERE id = ? AND refunded_at IS NULL",
                [$refundedBy, $notes !== null ? trim($notes) : null, (int)$transactionId]
            );
        }
    }

    /**
     * Get today's sales summary
     * @return array
     */
    public function getTodaySummary() {
        $today = date('Y-m-d');
        return $this->getSalesTotals($today, $today);
    }
}

/**
 * SalesTransactionItemManager - Handles CRUD operations for transaction items
 */
class SalesTransactionItemManager extends DBManager {

    public function __construct() {
        parent::__construct();
        $this->columns = array('id', 'sales_transaction_id', 'order_item_id', 'price', 'created_at');
        $this->tableName = 'sales_transaction_item';
    }

    /**
     * Get all items for a specific transaction with full details
     * @param int $transactionId
     * @return array
     */
    public function getItemsByTransactionWithDetails($transactionId) {
        $query = "
            SELECT
                sti.id,
                sti.sales_transaction_id,
                sti.order_item_id,
                sti.price,
                sti.created_at,
                sti.refunded_at,
                sti.refunded_by,
                sti.refund_notes,
                oi.single_price as original_price,
                p.name as product_name,
                p.ISBN as isbn,
                p.nota_volumi,
                o.numPratica as pratica,
                u.first_name as seller_first_name,
                u.last_name as seller_last_name,
                ru.first_name as refunded_by_first_name,
                ru.last_name as refunded_by_last_name
            FROM sales_transaction_item sti
            INNER JOIN order_item oi ON sti.order_item_id = oi.id
            INNER JOIN product p ON oi.product_id = p.id
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN user u ON o.user_id = u.id
            LEFT JOIN user ru ON sti.refunded_by = ru.id
            WHERE sti.sales_transaction_id = ?
            ORDER BY sti.id
        ";
        $results = $this->db->prepare($query, [(int)$transactionId]);

        $items = [];
        foreach ($results as $result) {
            $items[] = (object)$result;
        }
        return $items;
    }

    /**
     * Get all items for a specific transaction (simple)
     * @param int $transactionId
     * @return array
     */
    public function getItemsByTransaction($transactionId) {
        $query = "SELECT * FROM sales_transaction_item WHERE sales_transaction_id = ? ORDER BY id";
        $results = $this->db->prepare($query, [(int)$transactionId]);

        $items = [];
        foreach ($results as $result) {
            $items[] = (object)$result;
        }
        return $items;
    }
}
