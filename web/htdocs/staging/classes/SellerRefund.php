<?php

/**
 * SellerRefund - Represents a seller's refund record for a specific year
 */
class SellerRefund {

    public $id;
    public $user_id;
    public $year;
    public $payment_preference;
    public $preference_set_at;
    public $preference_token;
    public $preference_token_expires;
    public $amount_owed;
    public $amount_paid;
    public $payment_date;
    public $status;
    public $comments;
    public $donate_unsold;
    public $donate_unsold_set_at;
    public $seller_notes;
    public $envelope_prepared;
    public $newsletter_sent;
    public $newsletter_sent_at;
    public $newsletter_sent_by;
    public $created_at;
    public $updated_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * SellerRefundPayment - Represents an individual payment transaction
 */
class SellerRefundPayment {

    public $id;
    public $seller_refund_id;
    public $amount;
    public $payment_method;
    public $payment_date;
    public $reference;
    public $notes;
    public $operator_id;
    public $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * SellerRefundManager - Handles CRUD operations for seller refunds
 */
class SellerRefundManager extends DBManager {

    public function __construct() {
        parent::__construct();
        $this->columns = array('id', 'user_id', 'year', 'payment_preference', 'preference_set_at',
            'preference_token', 'preference_token_expires', 'newsletter_sent', 'newsletter_sent_at',
            'newsletter_sent_by', 'amount_owed', 'amount_paid', 'payment_date', 'status', 'comments',
            'donate_unsold', 'donate_unsold_set_at', 'seller_notes', 'envelope_prepared',
            'created_at', 'updated_at');
        $this->tableName = 'seller_refund';
    }

    /**
     * Get or create a seller refund record for a user and year
     * @param int $userId
     * @param int $year
     * @return object
     */
    public function getOrCreateForUserYear($userId, $year) {
        $existing = $this->getByUserYear($userId, $year);
        if ($existing) {
            return $existing;
        }

        // Create new record
        $data = [
            'user_id' => (int)$userId,
            'year' => (int)$year,
            'status' => 'pending'
        ];
        $id = $this->db->insert_one($this->tableName, $data);
        return $this->getById($id);
    }

    /**
     * Get seller refund by user and year
     * @param int $userId
     * @param int $year
     * @return object|null
     */
    public function getByUserYear($userId, $year) {
        $query = "SELECT * FROM seller_refund WHERE user_id = ? AND year = ?";
        $result = $this->db->prepare($query, [(int)$userId, (int)$year]);
        return $result && count($result) > 0 ? (object)$result[0] : null;
    }

    /**
     * Get seller refund by ID
     * @param int $id
     * @return object|null
     */
    public function getById($id) {
        $query = "SELECT * FROM seller_refund WHERE id = ?";
        $result = $this->db->prepare($query, [(int)$id]);
        return $result && count($result) > 0 ? (object)$result[0] : null;
    }

    /**
     * Get seller refund by token (for landing page)
     * @param string $token
     * @return object|null
     */
    public function getByToken($token) {
        if (empty($token)) {
            return null;
        }
        $query = "SELECT sr.*, u.first_name, u.last_name, u.email, u.iban, u.iban_owner_name
                  FROM seller_refund sr
                  INNER JOIN user u ON sr.user_id = u.id
                  WHERE sr.preference_token = ?
                  AND sr.preference_token_expires > NOW()";
        $result = $this->db->prepare($query, [$token]);
        return $result && count($result) > 0 ? (object)$result[0] : null;
    }

    /**
     * Generate a secure token for the landing page
     * @param int $sellerRefundId
     * @param int $expiresInDays Days until token expires (default 30)
     * @return string The generated token
     */
    public function generatePreferenceToken($sellerRefundId, $expiresInDays = 30) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));

        $this->db->execute(
            "UPDATE seller_refund SET preference_token = ?, preference_token_expires = ? WHERE id = ?",
            [$token, $expires, (int)$sellerRefundId]
        );

        return $token;
    }

    /**
     * Set payment preference from landing page
     * @param string $token
     * @param string $preference 'cash' or 'wire_transfer'
     * @param string|null $iban IBAN if wire_transfer
     * @param string|null $ibanOwnerName Owner name if wire_transfer
     * @return bool
     */
    public function setPaymentPreference($token, $preference, $iban = null, $ibanOwnerName = null, $donateUnsold = null, $sellerNotes = null) {
        $refund = $this->getByToken($token);
        if (!$refund) {
            return false;
        }

        // Update preference
        $this->db->execute(
            "UPDATE seller_refund SET payment_preference = ?, preference_set_at = NOW() WHERE id = ?",
            [$preference, (int)$refund->id]
        );

        // If wire transfer, update user's IBAN
        if ($preference === 'wire_transfer' && $iban) {
            $userMgr = new UserManager();
            $userMgr->saveIBAN($refund->user_id, $iban, $ibanOwnerName);
        }

        // Update donation preference if provided
        if ($donateUnsold !== null) {
            $this->db->execute(
                "UPDATE seller_refund SET donate_unsold = ?, donate_unsold_set_at = NOW() WHERE id = ?",
                [(int)$donateUnsold, (int)$refund->id]
            );
        }

        // Update seller notes if provided
        if ($sellerNotes !== null) {
            $this->db->execute(
                "UPDATE seller_refund SET seller_notes = ? WHERE id = ?",
                [$sellerNotes, (int)$refund->id]
            );
        }

        return true;
    }

    /**
     * Check if a user has unsold books (status 'vendere')
     * @param int $userId
     * @return bool
     */
    public function userHasUnsoldBooks($userId) {
        $query = "
            SELECT COUNT(*) as count
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = ?
            AND o.numPratica > 0
            AND oi.status = 'vendere'
        ";
        $result = $this->db->prepare($query, [(int)$userId]);
        return $result && (int)$result[0]['count'] > 0;
    }

    /**
     * Get count of unsold books for a user
     * @param int $userId
     * @return int
     */
    public function getUnsoldBooksCount($userId) {
        $query = "
            SELECT COUNT(*) as count
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = ?
            AND o.numPratica > 0
            AND oi.status = 'vendere'
        ";
        $result = $this->db->prepare($query, [(int)$userId]);
        return $result ? (int)$result[0]['count'] : 0;
    }

    /**
     * Update comments and envelope_prepared for a seller refund
     * @param int $sellerRefundId
     * @param string $comments
     * @param bool $envelopePrepared
     * @return bool
     */
    public function updateCommentsAndEnvelope($sellerRefundId, $comments, $envelopePrepared) {
        return $this->db->execute(
            "UPDATE seller_refund SET comments = ?, envelope_prepared = ? WHERE id = ?",
            [$comments, (int)$envelopePrepared, (int)$sellerRefundId]
        ) !== false;
    }

    /**
     * Calculate amount owed to a seller for a specific year
     * Based on sold books (order_item with status 'venduto')
     * @param int $userId
     * @param int $year
     * @return float
     */
    public function calculateAmountOwed($userId, $year) {
        // Get all sold items for this user in the given year
        // The seller gets single_price (the original price without markup)
        $query = "
            SELECT COALESCE(SUM(oi.single_price), 0) as total
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = ?
            AND oi.status = 'venduto'
            AND YEAR(oi.updated_at) = ?
        ";
        $result = $this->db->prepare($query, [(int)$userId, (int)$year]);
        return $result ? (float)$result[0]['total'] : 0.00;
    }

    /**
     * Update the amount_owed for a seller refund record
     * @param int $sellerRefundId
     * @return float The calculated amount
     */
    public function recalculateAmountOwed($sellerRefundId) {
        $refund = $this->getById($sellerRefundId);
        if (!$refund) {
            return 0.00;
        }

        $amount = $this->calculateAmountOwed($refund->user_id, $refund->year);

        $this->db->execute(
            "UPDATE seller_refund SET amount_owed = ? WHERE id = ?",
            [$amount, (int)$sellerRefundId]
        );

        return $amount;
    }

    /**
     * Get all seller refunds for a year with user details
     * @param int $year
     * @param string|null $status Filter by status
     * @param string|null $paymentPreference Filter by payment preference
     * @return array
     */
    public function getRefundsForYear($year, $status = null, $paymentPreference = null) {
        $params = [(int)$year];
        $conditions = ["sr.year = ?"];

        if ($status !== null && $status !== '') {
            $conditions[] = "sr.status = ?";
            $params[] = $status;
        }

        if ($paymentPreference !== null && $paymentPreference !== '') {
            $conditions[] = "sr.payment_preference = ?";
            $params[] = $paymentPreference;
        }

        $whereClause = implode(' AND ', $conditions);

        $query = "
            SELECT sr.*,
                   u.first_name, u.last_name, u.email,
                   (SELECT COUNT(DISTINCT o.numPratica) FROM orders o WHERE o.user_id = sr.user_id AND o.numPratica > 0) as pratica_count
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            WHERE $whereClause
            ORDER BY u.last_name, u.first_name
        ";

        $results = $this->db->prepare($query, $params);
        $refunds = [];
        foreach ($results as $result) {
            $refunds[] = (object)$result;
        }
        return $refunds;
    }

    /**
     * Get sellers who have sold books in a year but don't have a refund record yet
     * @param int $year
     * @return array
     */
    public function getSellersWithoutRefundRecord($year) {
        $query = "
            SELECT DISTINCT o.user_id,
                   u.first_name, u.last_name, u.email,
                   COUNT(DISTINCT o.numPratica) as pratica_count,
                   COALESCE(SUM(oi.single_price), 0) as total_owed
            FROM orders o
            INNER JOIN order_item oi ON o.id = oi.order_id
            INNER JOIN user u ON o.user_id = u.id
            LEFT JOIN seller_refund sr ON o.user_id = sr.user_id AND sr.year = ?
            WHERE o.numPratica > 0
            AND oi.status = 'venduto'
            AND YEAR(oi.updated_at) = ?
            AND sr.id IS NULL
            GROUP BY o.user_id, u.first_name, u.last_name, u.email
            HAVING total_owed > 0
            ORDER BY u.last_name, u.first_name
        ";

        $results = $this->db->prepare($query, [(int)$year, (int)$year]);
        $sellers = [];
        foreach ($results as $result) {
            $sellers[] = (object)$result;
        }
        return $sellers;
    }

    /**
     * Create refund records for all sellers who sold books in a year
     * @param int $year
     * @return int Number of records created
     */
    public function createRecordsForYear($year) {
        $sellers = $this->getSellersWithoutRefundRecord($year);
        $count = 0;

        foreach ($sellers as $seller) {
            $data = [
                'user_id' => (int)$seller->user_id,
                'year' => (int)$year,
                'amount_owed' => (float)$seller->total_owed,
                'status' => 'pending'
            ];
            $this->db->insert_one($this->tableName, $data);
            $count++;
        }

        return $count;
    }

    /**
     * Record a payment for a seller refund
     * @param int $sellerRefundId
     * @param float $amount
     * @param string $paymentMethod 'cash' or 'wire_transfer'
     * @param string $paymentDate Y-m-d format
     * @param string|null $reference
     * @param string|null $notes
     * @param int|null $operatorId
     * @return bool
     */
    public function recordPayment($sellerRefundId, $amount, $paymentMethod, $paymentDate, $reference = null, $notes = null, $operatorId = null) {
        $refund = $this->getById($sellerRefundId);
        if (!$refund) {
            return false;
        }

        // Insert payment record
        $paymentData = [
            'seller_refund_id' => (int)$sellerRefundId,
            'amount' => (float)$amount,
            'payment_method' => $paymentMethod,
            'payment_date' => $paymentDate,
            'reference' => $reference,
            'notes' => $notes,
            'operator_id' => $operatorId ? (int)$operatorId : null
        ];
        $this->db->insert_one('seller_refund_payment', $paymentData);

        // Update total paid and status
        $newAmountPaid = (float)$refund->amount_paid + (float)$amount;
        $newStatus = $newAmountPaid >= (float)$refund->amount_owed ? 'completed' : 'partial';

        $this->db->execute(
            "UPDATE seller_refund SET amount_paid = ?, payment_date = ?, status = ? WHERE id = ?",
            [$newAmountPaid, $paymentDate, $newStatus, (int)$sellerRefundId]
        );

        return true;
    }

    /**
     * Get payment history for a seller refund
     * @param int $sellerRefundId
     * @return array
     */
    public function getPaymentHistory($sellerRefundId) {
        $query = "
            SELECT srp.*, u.first_name as operator_first_name, u.last_name as operator_last_name
            FROM seller_refund_payment srp
            LEFT JOIN user u ON srp.operator_id = u.id
            WHERE srp.seller_refund_id = ?
            ORDER BY srp.payment_date DESC, srp.created_at DESC
        ";
        $results = $this->db->prepare($query, [(int)$sellerRefundId]);
        $payments = [];
        foreach ($results as $result) {
            $payments[] = (object)$result;
        }
        return $payments;
    }

    /**
     * Get summary statistics for a year
     * @param int $year
     * @return object
     */
    public function getYearSummary($year) {
        $query = "
            SELECT
                COUNT(*) as total_sellers,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN payment_preference IS NULL THEN 1 ELSE 0 END) as no_preference_count,
                SUM(CASE WHEN payment_preference = 'cash' THEN 1 ELSE 0 END) as cash_preference_count,
                SUM(CASE WHEN payment_preference = 'wire_transfer' THEN 1 ELSE 0 END) as wire_preference_count,
                COALESCE(SUM(amount_owed), 0) as total_owed,
                COALESCE(SUM(amount_paid), 0) as total_paid
            FROM seller_refund
            WHERE year = ?
        ";
        $result = $this->db->prepare($query, [(int)$year]);
        return $result ? (object)$result[0] : null;
    }

    /**
     * Update comments for a seller refund
     * @param int $sellerRefundId
     * @param string $comments
     * @return bool
     */
    public function updateComments($sellerRefundId, $comments) {
        return $this->db->execute(
            "UPDATE seller_refund SET comments = ? WHERE id = ?",
            [$comments, (int)$sellerRefundId]
        ) !== false;
    }

    /**
     * Get distinct years that have refund records
     * @return array
     */
    public function getAvailableYears() {
        $query = "SELECT DISTINCT year FROM seller_refund ORDER BY year DESC";
        $results = $this->db->prepare($query, []);
        $years = [];
        foreach ($results as $result) {
            $years[] = (int)$result['year'];
        }
        return $years;
    }

    /**
     * Generate landing page URL for a seller
     * @param int $sellerRefundId
     * @return string
     */
    public function getLandingPageUrl($sellerRefundId) {
        $refund = $this->getById($sellerRefundId);
        if (!$refund || !$refund->preference_token) {
            return '';
        }
        return ROOT_URL . 'payment-preference?token=' . urlencode($refund->preference_token);
    }

    /**
     * Get sellers for newsletter sending procedure
     * Returns all sellers with active pratica for a given year with filtering options
     * @param int $year
     * @param string|null $newsletterFilter 'sent', 'not_sent', or null for all
     * @param string|null $preferenceFilter 'set', 'not_set', or null for all
     * @return array
     */
    public function getSellersForNewsletter($year, $newsletterFilter = null, $preferenceFilter = null) {
        $params = [(int)$year, (int)$year];
        $conditions = ["sr.year = ?"];

        if ($newsletterFilter === 'sent') {
            $conditions[] = "sr.newsletter_sent = 1";
        } elseif ($newsletterFilter === 'not_sent') {
            $conditions[] = "sr.newsletter_sent = 0";
        }

        if ($preferenceFilter === 'set') {
            $conditions[] = "sr.payment_preference IS NOT NULL";
        } elseif ($preferenceFilter === 'not_set') {
            $conditions[] = "sr.payment_preference IS NULL";
        }

        $whereClause = implode(' AND ', $conditions);

        $query = "
            SELECT sr.*,
                   u.first_name, u.last_name, u.email,
                   (SELECT GROUP_CONCAT(DISTINCT o.numPratica ORDER BY o.numPratica SEPARATOR ', ')
                    FROM orders o
                    WHERE o.user_id = sr.user_id AND o.numPratica > 0) as pratica_numbers,
                   (SELECT COUNT(DISTINCT o.numPratica)
                    FROM orders o
                    WHERE o.user_id = sr.user_id AND o.numPratica > 0) as pratica_count,
                   sender.first_name as sender_first_name, sender.last_name as sender_last_name
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            LEFT JOIN user sender ON sr.newsletter_sent_by = sender.id
            WHERE $whereClause
            AND EXISTS (
                SELECT 1 FROM orders o
                INNER JOIN order_item oi ON o.id = oi.order_id
                WHERE o.user_id = sr.user_id
                AND o.numPratica > 0
                AND oi.status = 'venduto'
                AND YEAR(oi.updated_at) = ?
            )
            ORDER BY u.last_name, u.first_name
        ";

        $results = $this->db->prepare($query, $params);
        $sellers = [];
        foreach ($results as $result) {
            $sellers[] = (object)$result;
        }
        return $sellers;
    }

    /**
     * Get newsletter statistics for a year
     * @param int $year
     * @return object
     */
    public function getNewsletterStats($year) {
        $query = "
            SELECT
                COUNT(*) as total_sellers,
                SUM(CASE WHEN newsletter_sent = 1 THEN 1 ELSE 0 END) as newsletter_sent_count,
                SUM(CASE WHEN newsletter_sent = 0 THEN 1 ELSE 0 END) as newsletter_not_sent_count,
                SUM(CASE WHEN payment_preference IS NOT NULL THEN 1 ELSE 0 END) as preference_set_count,
                SUM(CASE WHEN payment_preference IS NULL THEN 1 ELSE 0 END) as preference_not_set_count,
                SUM(CASE WHEN newsletter_sent = 1 AND payment_preference IS NULL THEN 1 ELSE 0 END) as sent_no_response_count
            FROM seller_refund
            WHERE year = ?
        ";
        $result = $this->db->prepare($query, [(int)$year]);
        return $result ? (object)$result[0] : null;
    }

    /**
     * Mark newsletter as sent for a seller refund
     * Also generates a new token if needed
     * @param int $sellerRefundId
     * @param int $sentBy User ID of admin who sent
     * @return bool
     */
    public function markNewsletterSent($sellerRefundId, $sentBy) {
        $refund = $this->getById($sellerRefundId);
        if (!$refund) {
            return false;
        }

        // Generate token if not exists or expired
        if (!$refund->preference_token || strtotime($refund->preference_token_expires) < time()) {
            $this->generatePreferenceToken($sellerRefundId);
        }

        $this->db->execute(
            "UPDATE seller_refund SET newsletter_sent = 1, newsletter_sent_at = NOW(), newsletter_sent_by = ? WHERE id = ?",
            [(int)$sentBy, (int)$sellerRefundId]
        );

        return true;
    }

    /**
     * Mark newsletter as sent for multiple seller refunds
     * @param array $sellerRefundIds
     * @param int $sentBy
     * @return int Number of records updated
     */
    public function markMultipleNewsletterSent($sellerRefundIds, $sentBy) {
        $count = 0;
        foreach ($sellerRefundIds as $id) {
            if ($this->markNewsletterSent((int)$id, $sentBy)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Reset newsletter sent status (for re-sending)
     * @param int $sellerRefundId
     * @return bool
     */
    public function resetNewsletterStatus($sellerRefundId) {
        return $this->db->execute(
            "UPDATE seller_refund SET newsletter_sent = 0, newsletter_sent_at = NULL, newsletter_sent_by = NULL WHERE id = ?",
            [(int)$sellerRefundId]
        ) !== false;
    }

    /**
     * Get seller refund with full details for email template
     * @param int $sellerRefundId
     * @return object|null
     */
    public function getSellerRefundForEmail($sellerRefundId) {
        $query = "
            SELECT sr.*,
                   u.first_name, u.last_name, u.email,
                   (SELECT GROUP_CONCAT(DISTINCT o.numPratica ORDER BY o.numPratica SEPARATOR ', ')
                    FROM orders o
                    WHERE o.user_id = sr.user_id AND o.numPratica > 0) as pratica_numbers
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            WHERE sr.id = ?
        ";
        $result = $this->db->prepare($query, [(int)$sellerRefundId]);

        if (!$result || count($result) == 0) {
            return null;
        }

        $refund = (object)$result[0];

        // Generate token if not exists or expired
        if (!$refund->preference_token || strtotime($refund->preference_token_expires) < time()) {
            $this->generatePreferenceToken($sellerRefundId);
            // Refresh the data
            $result = $this->db->prepare($query, [(int)$sellerRefundId]);
            $refund = (object)$result[0];
        }

        // Add landing page URL
        $refund->landing_url = ROOT_URL . 'payment-preference?token=' . urlencode($refund->preference_token);

        return $refund;
    }

    /**
     * Generate email content for payment preference newsletter
     * @param object $sellerRefund The seller refund object from getSellerRefundForEmail
     * @return array ['subject' => string, 'body' => string]
     */
    public function generateNewsletterEmailContent($sellerRefund) {
        $subject = "Mercatino del Libro - Rimborso Vendite {$sellerRefund->year}";

        $body = "
Gentile {$sellerRefund->first_name} {$sellerRefund->last_name},

ti scriviamo per comunicarti che è disponibile il rimborso per i libri che hai venduto tramite il Mercatino del Libro nell'anno {$sellerRefund->year}.

RIEPILOGO:
- Pratica/e: {$sellerRefund->pratica_numbers}
- Importo da rimborsare: € " . number_format((float)$sellerRefund->amount_owed, 2, ',', '.') . "

Per procedere al rimborso, abbiamo bisogno di sapere come preferisci riceverlo.

CLICCA SUL LINK SEGUENTE per indicare la tua preferenza:
{$sellerRefund->landing_url}

Potrai scegliere tra:
- Contanti: ritiro presso la sede del Comitato
- Bonifico bancario: accredito sul tuo conto corrente (dovrai fornire l'IBAN)

Il link sarà valido per 30 giorni.

Per qualsiasi domanda, non esitare a contattarci.

Cordiali saluti,
Il Comitato Genitori Da Vinci
";

        return [
            'subject' => $subject,
            'body' => trim($body)
        ];
    }

    /**
     * Get detailed report data for export (Excel-like report)
     * Returns all seller refunds with books to sell and sold books list
     * @param int $year
     * @return array
     */
    public function getReportData($year) {
        // Get all seller refunds for the year with user details
        $query = "
            SELECT sr.*,
                   u.first_name, u.last_name, u.email, u.iban, u.iban_owner_name
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            WHERE sr.year = ?
            ORDER BY u.last_name, u.first_name
        ";
        $results = $this->db->prepare($query, [(int)$year]);

        $reportData = [];
        foreach ($results as $row) {
            $userId = (int)$row['user_id'];

            // Get books still for sale (status 'vendere') with pratica number
            $booksToSellQuery = "
                SELECT oi.id, p.name as book_title, o.numPratica
                FROM order_item oi
                INNER JOIN orders o ON oi.order_id = o.id
                INNER JOIN product p ON oi.product_id = p.id
                WHERE o.user_id = ?
                AND o.numPratica > 0
                AND oi.status = 'vendere'
                ORDER BY o.numPratica, p.name
            ";
            $booksToSell = $this->db->prepare($booksToSellQuery, [$userId]);

            // Get pratica numbers with sold books
            $soldPraticasQuery = "
                SELECT DISTINCT o.numPratica
                FROM order_item oi
                INNER JOIN orders o ON oi.order_id = o.id
                WHERE o.user_id = ?
                AND o.numPratica > 0
                AND oi.status = 'venduto'
                AND YEAR(oi.updated_at) = ?
                ORDER BY o.numPratica
            ";
            $soldPraticas = $this->db->prepare($soldPraticasQuery, [$userId, (int)$year]);

            // Decrypt IBAN if encrypted
            $ibanFormatted = '';
            if (!empty($row['iban'])) {
                $storedIban = $row['iban'];
                if (Encryption::isConfigured()) {
                    $decrypted = Encryption::decrypt($storedIban);
                    if ($decrypted !== false) {
                        $storedIban = $decrypted;
                    }
                }
                // Format IBAN in groups of 4
                $ibanFormatted = implode(' ', str_split($storedIban, 4));
            }

            // Format books to sell list
            $booksToSellList = [];
            foreach ($booksToSell as $book) {
                $booksToSellList[] = $book['numPratica'] . '/' . $book['book_title'];
            }

            // Format sold praticas list
            $soldPraticasList = [];
            foreach ($soldPraticas as $pratica) {
                $soldPraticasList[] = $pratica['numPratica'];
            }

            $reportData[] = (object)[
                'id' => $row['id'],
                'user_id' => $userId,
                'last_name' => $row['last_name'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'donate_unsold' => $row['donate_unsold'],
                'iban' => $ibanFormatted,
                'iban_owner_name' => $row['iban_owner_name'],
                'envelope_prepared' => $row['envelope_prepared'],
                'amount_owed' => (float)$row['amount_owed'],
                'payment_preference' => $row['payment_preference'],
                'books_to_sell' => $booksToSellList,
                'sold_praticas' => $soldPraticasList,
                'comments' => $row['comments'],
                'seller_notes' => $row['seller_notes']
            ];
        }

        return $reportData;
    }
}
