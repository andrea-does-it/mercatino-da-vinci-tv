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

    /**
     * Profile conditions shared by the newsletter filters, the stats and the
     * defaults applied when refund records are created. They always refer to
     * the user table aliased as "u".
     */
    const SQL_HAS_IBAN = "(u.iban IS NOT NULL AND u.iban <> '')";
    const SQL_DONATES  = "(COALESCE(u.donate_books, 0) = 1)";

    /**
     * Pratica of the books owned by the bookshop itself (Comitato stock, not a
     * parent's books). Everything sold under this pratica is net income for the
     * bookshop and must never be counted as owed to a seller, so it is excluded
     * from every refund amount, list and report in this class.
     * Sales views (SalesTransaction) intentionally still include it: that money
     * really was taken in.
     */
    const BOOKSHOP_PRATICA = 100;

    /**
     * Build the WHERE conditions shared by the newsletter list and the closing
     * report, so the two pages filter identically.
     *
     * Expects the seller_refund table aliased "sr" and the user table "u".
     * No condition interpolates request data: the enum values are matched
     * against hardcoded lists and only the hardcoded string reaches the SQL.
     *
     * @param array $filters keys: newsletter ('sent'|'not_sent'),
     *        preference ('set'|'not_set'), iban ('with'|'without'),
     *        donation ('yes'|'no'), status (seller_refund.status enum),
     *        to_contact (bool)
     * @return array list of SQL condition strings
     */
    private function sellerFilterConditions($filters) {
        $conditions = [];

        $newsletter = isset($filters['newsletter']) ? $filters['newsletter'] : null;
        if ($newsletter === 'sent') {
            $conditions[] = "sr.newsletter_sent = 1";
        } elseif ($newsletter === 'not_sent') {
            $conditions[] = "sr.newsletter_sent = 0";
        }

        $preference = isset($filters['preference']) ? $filters['preference'] : null;
        if ($preference === 'set') {
            $conditions[] = "sr.payment_preference IS NOT NULL";
        } elseif ($preference === 'not_set') {
            $conditions[] = "sr.payment_preference IS NULL";
        }

        $iban = isset($filters['iban']) ? $filters['iban'] : null;
        if ($iban === 'with') {
            $conditions[] = self::SQL_HAS_IBAN;
        } elseif ($iban === 'without') {
            $conditions[] = "NOT (" . self::SQL_HAS_IBAN . ")";
        }

        $donation = isset($filters['donation']) ? $filters['donation'] : null;
        if ($donation === 'yes') {
            $conditions[] = self::SQL_DONATES;
        } elseif ($donation === 'no') {
            $conditions[] = "NOT (" . self::SQL_DONATES . ")";
        }

        if (!empty($filters['to_contact'])) {
            $conditions[] = "(NOT (" . self::SQL_HAS_IBAN . ") OR NOT (" . self::SQL_DONATES . "))";
        }

        // The status that reaches the query is the one from this list, never the
        // submitted string, so no request data is interpolated into the SQL.
        $allowedStatus = ['pending', 'partial', 'xmlsaved', 'completed', 'cancelled'];
        $statusKey = isset($filters['status']) ? array_search($filters['status'], $allowedStatus, true) : false;
        if ($statusKey !== false) {
            $conditions[] = "sr.status = '" . $allowedStatus[$statusKey] . "'";
        }

        return $conditions;
    }

    /**
     * SQL condition true only for sellers who have at least one real pratica,
     * i.e. something other than the bookshop's own stock. Used to keep the
     * bookshop out of the refund lists, summary and report even when a
     * seller_refund row already exists for it.
     * @param string $alias alias of the seller_refund table in the query
     * @return string
     */
    public function sqlIsRealSeller($alias = 'sr') {
        return "EXISTS (
                SELECT 1 FROM orders bo
                WHERE bo.user_id = {$alias}.user_id
                AND bo.numPratica > 0
                AND bo.numPratica <> " . self::BOOKSHOP_PRATICA . "
            )";
    }

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

        // Seed the donation flag from the seller's standing profile preference.
        $userMgr = new UserManager();
        $donateDefault = $userMgr->getDonateBooks($userId);

        // Create new record
        $data = [
            'user_id' => (int)$userId,
            'year' => (int)$year,
            'status' => 'pending',
            'donate_unsold' => $donateDefault
        ];
        if ($donateDefault) {
            $data['donate_unsold_set_at'] = date('Y-m-d H:i:s');
        }
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

        // Update donation preference if provided.
        if ($donateUnsold !== null) {
            $this->db->execute(
                "UPDATE seller_refund SET donate_unsold = ?, donate_unsold_set_at = NOW() WHERE id = ?",
                [(int)$donateUnsold, (int)$refund->id]
            );

            // A given donation is mirrored onto the standing profile preference
            // (user.donate_books), so the admin filters that read the profile
            // ("Da contattare" on the newsletter page) see the answer the seller
            // gave here instead of still treating them as never asked.
            // Only the "yes" is propagated: the donation is a one-way choice, and
            // writing a 0 back could silently undo a preference the seller set in
            // their own profile after this refund record was created.
            if ((int)$donateUnsold === 1) {
                $donateUserMgr = new UserManager();
                $donateUserMgr->updateDonateBooks($refund->user_id, 1);
            }
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
            AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
            AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
            AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
        $conditions = ["sr.year = ?", $this->sqlIsRealSeller('sr')];

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
                   (SELECT COUNT(DISTINCT o.numPratica) FROM orders o WHERE o.user_id = sr.user_id AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . ") as pratica_count
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
     * Estimate the refunds for a year, split by whether the seller has an IBAN
     * on file. Read-only: computed live from the books actually sold, so it
     * works before any seller_refund record exists.
     *
     * Uses the same seller set and the same amount as getSellersWithoutRefundRecord(),
     * so the estimate matches what createRecordsForYear() would produce.
     *
     * @param int $year
     * @return array ['wire' => ['sellers' => int, 'total_owed' => float],
     *                'cash' => ['sellers' => int, 'total_owed' => float]]
     */
    public function getRefundEstimateByIban($year) {
        $query = "
            SELECT has_iban,
                   COUNT(*) as sellers,
                   COALESCE(SUM(total_owed), 0) as total_owed
            FROM (
                SELECT o.user_id,
                       MAX(CASE WHEN " . self::SQL_HAS_IBAN . " THEN 1 ELSE 0 END) as has_iban,
                       COALESCE(SUM(oi.single_price), 0) as total_owed
                FROM orders o
                INNER JOIN order_item oi ON o.id = oi.order_id
                INNER JOIN user u ON o.user_id = u.id
                WHERE o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
                AND oi.status = 'venduto'
                AND YEAR(oi.updated_at) = ?
                GROUP BY o.user_id
                HAVING total_owed > 0
            ) t
            GROUP BY has_iban
        ";

        $estimate = [
            'wire' => ['sellers' => 0, 'total_owed' => 0.00],
            'cash' => ['sellers' => 0, 'total_owed' => 0.00]
        ];

        $results = $this->db->prepare($query, [(int)$year]);
        if ($results) {
            foreach ($results as $row) {
                $key = (int)$row['has_iban'] === 1 ? 'wire' : 'cash';
                $estimate[$key] = [
                    'sellers' => (int)$row['sellers'],
                    'total_owed' => (float)$row['total_owed']
                ];
            }
        }

        return $estimate;
    }

    /**
     * Pickup sheet data: one row per seller, for the year.
     *
     * Deliberately independent of both `seller_refund` and `sales_transaction`:
     * it reads `order_item.status` directly, so a seller with no refund record,
     * or books marked sold outside the sales ledger, still appear. That is what
     * makes it usable to check the other pages against.
     *
     * Sold amounts use the section's usual year basis
     * (YEAR(order_item.updated_at)); unsold books are whatever sits on the shelf
     * right now, so they carry no year. Pratica 100 is excluded as everywhere
     * else in the refund domain.
     *
     * Only sellers who actually have something to collect are returned (money
     * owed, or books to hand back): a seller with nothing to collect has no
     * reason to come to the desk and would only cost a printed line.
     *
     * @param int $year
     * @return array sellers, each ['user_id','last_name','first_name','email',
     *         'pratica_list','sold_books','sold_amount','unsold_books',
     *         'to_return','donated','donates','payment','books' => [...]]
     */
    public function getOrdersOverview($year) {
        $query = "
            SELECT o.user_id,
                   u.last_name, u.first_name, u.email,
                   COALESCE(sr.donate_unsold, u.donate_books, 0) as donates,
                   sr.payment_preference,
                   CASE WHEN " . self::SQL_HAS_IBAN . " THEN 1 ELSE 0 END as has_iban,
                   GROUP_CONCAT(DISTINCT o.numPratica ORDER BY o.numPratica SEPARATOR ', ') as pratica_list,
                   SUM(CASE WHEN oi.status = 'venduto' AND YEAR(oi.updated_at) = ? THEN 1 ELSE 0 END) as sold_books,
                   COALESCE(SUM(CASE WHEN oi.status = 'venduto' AND YEAR(oi.updated_at) = ?
                                     THEN oi.single_price ELSE 0 END), 0) as sold_amount,
                   SUM(CASE WHEN oi.status = 'vendere' THEN 1 ELSE 0 END) as unsold_books
            FROM orders o
            INNER JOIN order_item oi ON o.id = oi.order_id
            INNER JOIN user u ON o.user_id = u.id
            LEFT JOIN seller_refund sr ON sr.user_id = o.user_id AND sr.year = ?
            WHERE o.numPratica > 0
            AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
            AND oi.status IN ('vendere', 'venduto')
            GROUP BY o.user_id, u.last_name, u.first_name, u.email, donates,
                     sr.payment_preference, has_iban
            ORDER BY u.last_name, u.first_name
        ";

        $rows = $this->db->prepare($query, [(int)$year, (int)$year, (int)$year]);

        $sellers = [];
        foreach ($rows as $row) {
            $userId = (int)$row['user_id'];
            $donates = (int)$row['donates'] === 1;
            $unsold = (int)$row['unsold_books'];
            $soldAmount = (float)$row['sold_amount'];
            // Donated books stay with the Comitato: they are listed on the sheet
            // (so the desk can see them) but they are not handed back.
            $toReturn = $donates ? 0 : $unsold;

            // Nothing to collect: no money owed and no book to hand back.
            if ($soldAmount <= 0 && $toReturn === 0) {
                continue;
            }

            // Payment mode: the stored preference when a refund record has one,
            // otherwise the same rule createRecordsForYear() applies, so the
            // sheet is usable before the refund records exist.
            $payment = $row['payment_preference'];
            if ($payment !== 'cash' && $payment !== 'wire_transfer') {
                $payment = (int)$row['has_iban'] === 1 ? 'wire_transfer' : 'cash';
            }

            $sellers[$userId] = [
                'user_id' => $userId,
                'last_name' => $row['last_name'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'pratica_list' => $row['pratica_list'],
                'sold_books' => (int)$row['sold_books'],
                'sold_amount' => $soldAmount,
                'unsold_books' => $unsold,
                'to_return' => $toReturn,
                'donated' => $donates ? $unsold : 0,
                'donates' => $donates,
                'payment' => $payment,
                'books' => []
            ];
        }

        if (count($sellers) === 0) {
            return [];
        }

        // Unsold books, one row each, ordered by pratica then title: that is the
        // order the "Libri da rendere" column is printed in.
        $booksQuery = "
            SELECT o.user_id, o.numPratica as pratica, p.name as title
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            INNER JOIN product p ON oi.product_id = p.id
            WHERE o.numPratica > 0
            AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
            AND oi.status = 'vendere'
            ORDER BY o.user_id, o.numPratica, p.name
        ";
        $bookRows = $this->db->prepare($booksQuery, []);
        foreach ($bookRows as $book) {
            $userId = (int)$book['user_id'];
            if (!isset($sellers[$userId])) {
                continue;
            }
            $sellers[$userId]['books'][] = [
                'pratica' => (int)$book['pratica'],
                'title' => $book['title']
            ];
        }

        return array_values($sellers);
    }

    /**
     * Bookshop (Comitato) income for a year.
     *
     * Two components:
     *  - pratica 100: the mercatino owns those books, so the WHOLE amount the
     *    buyer paid is income;
     *  - every other pratica: only the overhead (seller deduction + buyer
     *    markup) stays with the Comitato, the rest is owed to the seller.
     *
     * Computed from the sales ledger, not from the current settings: the
     * overhead of each book is `sales_transaction_item.price - single_price`,
     * i.e. the margin actually taken at the time of sale. Changing
     * SiteSettings::totalMarkup() therefore never rewrites past years.
     * Refunded transactions and refunded single items are excluded.
     * The year is the year of the sale (`sales_transaction.created_at`).
     *
     * @param int $year
     * @return array ['bookshop' => ['books','gross'],
     *                'overhead' => ['books','total'],
     *                'total' => float,
     *                'by_pratica' => [['pratica','books','gross','overhead'], ...],
     *                'unlinked' => ['books','amount']]
     */
    public function getBookshopIncome($year) {
        $query = "
            SELECT o.numPratica as pratica,
                   COUNT(*) as books,
                   COALESCE(SUM(sti.price), 0) as gross,
                   COALESCE(SUM(sti.price - oi.single_price), 0) as overhead
            FROM sales_transaction_item sti
            INNER JOIN sales_transaction st ON sti.sales_transaction_id = st.id
            INNER JOIN order_item oi ON sti.order_item_id = oi.id
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE st.refunded_at IS NULL
            AND sti.refunded_at IS NULL
            AND YEAR(st.created_at) = ?
            AND o.numPratica > 0
            GROUP BY o.numPratica
            ORDER BY o.numPratica
        ";

        $income = [
            'bookshop' => ['books' => 0, 'gross' => 0.00],
            'overhead' => ['books' => 0, 'total' => 0.00],
            'total' => 0.00,
            'by_pratica' => [],
            'unlinked' => ['books' => 0, 'amount' => 0.00]
        ];

        $rows = $this->db->prepare($query, [(int)$year]);
        foreach ($rows as $row) {
            $pratica = (int)$row['pratica'];
            $books = (int)$row['books'];
            $gross = (float)$row['gross'];
            $overhead = (float)$row['overhead'];

            if ($pratica === self::BOOKSHOP_PRATICA) {
                $income['bookshop']['books'] += $books;
                $income['bookshop']['gross'] += $gross;
            } else {
                $income['overhead']['books'] += $books;
                $income['overhead']['total'] += $overhead;
                $income['by_pratica'][] = [
                    'pratica' => $pratica,
                    'books' => $books,
                    'gross' => $gross,
                    'overhead' => $overhead
                ];
            }
        }

        $income['total'] = $income['bookshop']['gross'] + $income['overhead']['total'];

        // Reconciliation: books flagged 'venduto' that no live sale row covers
        // (e.g. sold through the old per-item flow). They are NOT in the totals
        // above; surface them so a gap is visible instead of silently missing.
        $unlinkedQuery = "
            SELECT COUNT(*) as books,
                   COALESCE(SUM(oi.single_price), 0) as amount
            FROM order_item oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE oi.status = 'venduto'
            AND YEAR(oi.updated_at) = ?
            AND o.numPratica > 0
            AND NOT EXISTS (
                SELECT 1
                FROM sales_transaction_item sti
                INNER JOIN sales_transaction st ON sti.sales_transaction_id = st.id
                WHERE sti.order_item_id = oi.id
                AND st.refunded_at IS NULL
                AND sti.refunded_at IS NULL
            )
        ";
        $unlinked = $this->db->prepare($unlinkedQuery, [(int)$year]);
        if ($unlinked && count($unlinked) > 0) {
            $income['unlinked'] = [
                'books' => (int)$unlinked[0]['books'],
                'amount' => (float)$unlinked[0]['amount']
            ];
        }

        return $income;
    }

    /**
     * Get sellers who have sold books in a year but don't have a refund record yet
     *
     * Also returns the profile defaults used when the record is created:
     * has_iban (1 = the user has an IBAN on file, so the refund goes by wire
     * transfer) and donate_books (the profile donation preference).
     *
     * @param int $year
     * @return array
     */
    public function getSellersWithoutRefundRecord($year) {
        $query = "
            SELECT DISTINCT o.user_id,
                   u.first_name, u.last_name, u.email,
                   MAX(CASE WHEN " . self::SQL_HAS_IBAN . " THEN 1 ELSE 0 END) as has_iban,
                   MAX(COALESCE(u.donate_books, 0)) as donate_books,
                   COUNT(DISTINCT o.numPratica) as pratica_count,
                   COALESCE(SUM(oi.single_price), 0) as total_owed
            FROM orders o
            INNER JOIN order_item oi ON o.id = oi.order_id
            INNER JOIN user u ON o.user_id = u.id
            LEFT JOIN seller_refund sr ON o.user_id = sr.user_id AND sr.year = ?
            WHERE o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
     *
     * Defaults are taken from the user profile: the payment preference is
     * 'wire_transfer' when the user has an IBAN on file and 'cash' otherwise,
     * and the donation preference is the profile's donate_books flag.
     * preference_set_at is left NULL on purpose: it marks a preference the
     * seller set from the landing page, not an admin default.
     *
     * @param int $year
     * @return int Number of records created
     */
    public function createRecordsForYear($year) {
        $sellers = $this->getSellersWithoutRefundRecord($year);
        $count = 0;

        foreach ($sellers as $seller) {
            $donateDefault = (int)$seller->donate_books;
            $data = [
                'user_id' => (int)$seller->user_id,
                'year' => (int)$year,
                'amount_owed' => (float)$seller->total_owed,
                'status' => 'pending',
                'payment_preference' => (int)$seller->has_iban === 1 ? 'wire_transfer' : 'cash',
                'donate_unsold' => $donateDefault
            ];
            if ($donateDefault) {
                $data['donate_unsold_set_at'] = date('Y-m-d H:i:s');
            }
            $this->db->insert_one($this->tableName, $data);
            $count++;
        }

        return $count;
    }

    /**
     * Count the refund records of a year that can still inherit a profile default
     * (payment preference or donation preference still NULL).
     *
     * @param int $year
     * @return int
     */
    public function countRecordsNeedingUserDefaults($year) {
        $query = "
            SELECT COUNT(*) as cnt
            FROM {$this->tableName} sr
            INNER JOIN user u ON sr.user_id = u.id
            WHERE sr.year = ?
            AND (sr.payment_preference IS NULL OR sr.donate_unsold IS NULL)
            AND " . $this->sqlIsRealSeller('sr') . "
        ";

        $result = $this->db->prepare($query, [(int)$year]);
        return count($result) > 0 ? (int)$result[0]['cnt'] : 0;
    }

    /**
     * Apply the user profile defaults to the refund records of a year that do
     * not carry a preference yet.
     *
     * Only rows whose column is still NULL are touched, so a preference the
     * seller already expressed (landing page) is never overwritten.
     *
     * @param int $year
     * @return array ['payment' => int, 'donation' => int] rows updated
     */
    public function applyUserDefaultsToYear($year) {
        $paymentQuery = "
            UPDATE {$this->tableName} sr
            INNER JOIN user u ON sr.user_id = u.id
            SET sr.payment_preference = CASE
                    WHEN " . self::SQL_HAS_IBAN . " THEN 'wire_transfer'
                    ELSE 'cash'
                END
            WHERE sr.year = ?
            AND sr.payment_preference IS NULL
            AND " . $this->sqlIsRealSeller('sr') . "
        ";
        $paymentUpdated = $this->db->execute($paymentQuery, [(int)$year]);

        $donationQuery = "
            UPDATE {$this->tableName} sr
            INNER JOIN user u ON sr.user_id = u.id
            SET sr.donate_unsold = COALESCE(u.donate_books, 0),
                sr.donate_unsold_set_at = CASE
                    WHEN " . self::SQL_DONATES . " THEN NOW()
                    ELSE sr.donate_unsold_set_at
                END
            WHERE sr.year = ?
            AND sr.donate_unsold IS NULL
            AND " . $this->sqlIsRealSeller('sr') . "
        ";
        $donationUpdated = $this->db->execute($donationQuery, [(int)$year]);

        return [
            'payment' => (int)$paymentUpdated,
            'donation' => (int)$donationUpdated
        ];
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
                SUM(CASE WHEN sr.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN sr.status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN sr.status = 'xmlsaved' THEN 1 ELSE 0 END) as xmlsaved_count,
                SUM(CASE WHEN sr.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN sr.payment_preference IS NULL THEN 1 ELSE 0 END) as no_preference_count,
                SUM(CASE WHEN sr.payment_preference = 'cash' THEN 1 ELSE 0 END) as cash_preference_count,
                SUM(CASE WHEN sr.payment_preference = 'wire_transfer' THEN 1 ELSE 0 END) as wire_preference_count,
                COALESCE(SUM(sr.amount_owed), 0) as total_owed,
                COALESCE(SUM(sr.amount_paid), 0) as total_paid,
                -- Contanti ancora da consegnare: dovuto meno già pagato, solo per
                -- chi ha scelto (o eredita) la modalità contanti.
                COALESCE(SUM(CASE WHEN sr.payment_preference = 'cash'
                                  THEN sr.amount_owed - sr.amount_paid ELSE 0 END), 0) as cash_outstanding,
                SUM(CASE WHEN sr.payment_preference = 'cash'
                              AND (sr.amount_owed - sr.amount_paid) > 0 THEN 1 ELSE 0 END) as cash_outstanding_sellers
            FROM seller_refund sr
            WHERE sr.year = ?
            AND " . $this->sqlIsRealSeller('sr') . "
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
     * @param string|null $ibanFilter 'with', 'without', or null for all (user profile)
     * @param string|null $donationFilter 'yes', 'no', or null for all (user profile)
     * @param bool $toContactOnly only sellers missing the IBAN or the donation choice
     * @return array
     */
    public function getSellersForNewsletter($year, $newsletterFilter = null, $preferenceFilter = null, $ibanFilter = null, $donationFilter = null, $toContactOnly = false) {
        $params = [(int)$year, (int)$year];
        $conditions = array_merge(
            ["sr.year = ?", $this->sqlIsRealSeller('sr')],
            $this->sellerFilterConditions([
                'newsletter' => $newsletterFilter,
                'preference' => $preferenceFilter,
                'iban' => $ibanFilter,
                'donation' => $donationFilter,
                'to_contact' => $toContactOnly
            ])
        );

        $whereClause = implode(' AND ', $conditions);

        $query = "
            SELECT sr.*,
                   u.first_name, u.last_name, u.email,
                   CASE WHEN " . self::SQL_HAS_IBAN . " THEN 1 ELSE 0 END as has_iban,
                   COALESCE(u.donate_books, 0) as donate_books,
                   (SELECT GROUP_CONCAT(DISTINCT o.numPratica ORDER BY o.numPratica SEPARATOR ', ')
                    FROM orders o
                    WHERE o.user_id = sr.user_id AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . ") as pratica_numbers,
                   (SELECT COUNT(DISTINCT o.numPratica)
                    FROM orders o
                    WHERE o.user_id = sr.user_id AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . ") as pratica_count,
                   sender.first_name as sender_first_name, sender.last_name as sender_last_name
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            LEFT JOIN user sender ON sr.newsletter_sent_by = sender.id
            WHERE $whereClause
            AND EXISTS (
                SELECT 1 FROM orders o
                INNER JOIN order_item oi ON o.id = oi.order_id
                WHERE o.user_id = sr.user_id
                AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
                SUM(CASE WHEN sr.newsletter_sent = 1 THEN 1 ELSE 0 END) as newsletter_sent_count,
                SUM(CASE WHEN sr.newsletter_sent = 0 THEN 1 ELSE 0 END) as newsletter_not_sent_count,
                SUM(CASE WHEN sr.payment_preference IS NOT NULL THEN 1 ELSE 0 END) as preference_set_count,
                SUM(CASE WHEN sr.payment_preference IS NULL THEN 1 ELSE 0 END) as preference_not_set_count,
                SUM(CASE WHEN sr.newsletter_sent = 1 AND sr.payment_preference IS NULL THEN 1 ELSE 0 END) as sent_no_response_count,
                SUM(CASE WHEN NOT " . self::SQL_HAS_IBAN . " THEN 1 ELSE 0 END) as no_iban_count,
                SUM(CASE WHEN NOT " . self::SQL_DONATES . " THEN 1 ELSE 0 END) as no_donation_count,
                SUM(CASE WHEN NOT " . self::SQL_HAS_IBAN . " OR NOT " . self::SQL_DONATES . " THEN 1 ELSE 0 END) as to_contact_count
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            WHERE sr.year = ?
            AND " . $this->sqlIsRealSeller('sr') . "
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
                    WHERE o.user_id = sr.user_id AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . ") as pratica_numbers
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

ti scriviamo per comunicarti che stiamo preparando il rimborso per i libri che hai venduto tramite il Mercatino del Libro nell'anno {$sellerRefund->year}.

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
     * Plain newsletter text -> HTML email body.
     * Merge first, escape after, then linkify: the preference link must be
     * clickable, and the merged values cannot inject HTML.
     * @param string $plainBody
     * @return string
     */
    public function buildNewsletterHtmlBody($plainBody) {
        return email_html_document($plainBody);
    }

    /**
     * Get detailed report data for export (Excel-like report)
     * Returns all seller refunds with books to sell and sold books list
     * @param int $year
     * @param array $filters same keys as sellerFilterConditions(), so the report
     *        and the newsletter page filter the sellers identically
     * @return array
     */
    public function getReportData($year, $filters = []) {
        $conditions = array_merge(
            ["sr.year = ?", $this->sqlIsRealSeller('sr')],
            $this->sellerFilterConditions($filters)
        );
        $whereClause = implode(' AND ', $conditions);

        // Get all seller refunds for the year with user details
        $query = "
            SELECT sr.*,
                   u.first_name, u.last_name, u.email, u.iban, u.iban_owner_name
            FROM seller_refund sr
            INNER JOIN user u ON sr.user_id = u.id
            WHERE $whereClause
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
                AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
                AND o.numPratica > 0 AND o.numPratica <> " . self::BOOKSHOP_PRATICA . "
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
