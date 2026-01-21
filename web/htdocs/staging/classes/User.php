<?php

  class User {

    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $user_type;
    public $profile_id;
    public $privacy_consent;
    public $newsletter_consent;
    public $created_at;

    public function __construct($id, $first_name, $last_name, $email, $user_type, $profile_id = null) {
      $this->id = (int)$id;
      $this->first_name = $first_name;
      $this->last_name = $last_name;
      $this->email = $email;
      $this->user_type = $user_type;
      $this->profile_id = $profile_id;
    }

    public static function generatePassword() {
        // Generate a secure random password
        return bin2hex(random_bytes(8));
    }
  }

  class UserManager extends DBManager {

    public function __construct(){
      parent::__construct();
      $this->tableName = 'user';
      $this->columns = array('id', 'email', 'first_name', 'last_name', 'user_type', 'profile_id');
    }

    public function guidExists($guid) {
        $result = $this->db->prepare(
            "SELECT id AS userId FROM user WHERE reset_link = ?",
            [$guid]
        );
        if ($result) {
            return $result[0]['userId'];
        }
        return false;
    }

    public function invalidateGuid($guid) {
        $this->db->execute(
            "UPDATE user SET reset_link = NULL WHERE reset_link = ?",
            [$guid]
        );
    }

    public function createResetLink($userId) {
        $guid = Utilities::guidv4();
        $this->db->execute(
            "UPDATE user SET reset_link = ? WHERE id = ?",
            [$guid, (int)$userId]
        );
        return ROOT_URL . "auth?page=reset-password&guid=" . urlencode($guid);
    }

    public function register($first_name, $last_name, $email, $password, $profile_id, $privacy_consent = true, $newsletter_consent = false){
      $user = new User(0, $first_name, $last_name, $email, 'regular', $profile_id);
      $userId = $this->_createUser($user, $password);

      // Save consent information
      if ($userId > 0) {
        $this->saveConsent($userId, $privacy_consent, $newsletter_consent);
      }

      return $userId;
    }

    public function login($email, $password) {

      $user = $this->_getUserByEmail($email);
      if (!$user){
        return false;
      }
      $existingHashFromDb = $this->_getPassword($user['id']);
      $isPasswordCorrect = password_verify($password, $existingHashFromDb);

      if ($isPasswordCorrect) {
        return new User($user['id'], $user['first_name'], $user['last_name'], $user['email'], $user['user_type'], $user['profile_id']);
      } else {
        return false;
      }
    }

    public function isValidPassword($pwd){
      // Password must be at least 8 characters
      if (strlen($pwd) < 8) {
        return false;
      }
      // Must contain at least one uppercase letter
      if (!preg_match('/[A-Z]/', $pwd)) {
        return false;
      }
      // Must contain at least one lowercase letter
      if (!preg_match('/[a-z]/', $pwd)) {
        return false;
      }
      // Must contain at least one number
      if (!preg_match('/[0-9]/', $pwd)) {
        return false;
      }
      return true;
    }

    public function getPasswordRequirements(){
      return 'Minimo 8 caratteri, con almeno una maiuscola, una minuscola e un numero.';
    }

    public function isValidEmail($email){
      return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function passwordsMatch($pwd1, $pwd2){
      return $pwd1 == $pwd2;
    }

    public function userExists($email) {
        $result = $this->db->prepare(
            "SELECT count(id) as count FROM user WHERE email = ?",
            [$email]
        );
        return $result[0]['count'] > 0;
    }

    public function updatePassword($userId, $password) {
        // Ensure password is not empty
        if (empty($password)) {
            $password = User::generatePassword();
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE {$this->tableName} SET password = ? WHERE id = ?",
            [$hashedPassword, (int)$userId]
        );
        return true;
    }

    public function createAddress($userId, $street, $city, $cap) {
        $result = $this->db->prepare(
            "SELECT count(1) as has_address FROM address WHERE user_id = ?",
            [(int)$userId]
        );

        if ($result[0]['has_address'] > 0) {
            $this->db->execute(
                "UPDATE address SET street = ?, city = ?, cap = ? WHERE user_id = ?",
                [$street, $city, $cap, (int)$userId]
            );
        } else {
            $this->db->execute(
                "INSERT INTO address (user_id, street, city, cap) VALUES (?, ?, ?, ?)",
                [(int)$userId, $street, $city, $cap]
            );
        }
    }

    public function getAddress($userId) {
        $result = $this->db->prepare(
            "SELECT street, city, cap FROM address WHERE user_id = ?",
            [(int)$userId]
        );
        if (count($result) > 0) {
            return $result[0];
        }
        return null;
    }

    public function getUserByEmail($email){
      return $this->_getUserByEmail($email);
    }

    public function createUser($user, $password){
      return $this->_createUser($user, $password);
    }

    // GDPR Consent Methods
    public function saveConsent($userId, $privacy_consent, $newsletter_consent) {
        $this->db->execute(
            "UPDATE {$this->tableName} SET privacy_consent = ?, newsletter_consent = ?, privacy_consent_date = NOW() WHERE id = ?",
            [(int)$privacy_consent, (int)$newsletter_consent, (int)$userId]
        );
    }

    public function updateNewsletterConsent($userId, $consent) {
        $this->db->execute(
            "UPDATE {$this->tableName} SET newsletter_consent = ?, newsletter_consent_date = NOW() WHERE id = ?",
            [(int)$consent, (int)$userId]
        );
    }

    public function getConsent($userId) {
        $result = $this->db->prepare(
            "SELECT privacy_consent, newsletter_consent, privacy_consent_date, newsletter_consent_date FROM {$this->tableName} WHERE id = ?",
            [(int)$userId]
        );
        if (count($result) > 0) {
            return $result[0];
        }
        return null;
    }

    // GDPR Data Export (Portability)
    public function exportUserData($userId) {
        $userData = $this->db->prepare(
            "SELECT id, email, first_name, last_name, user_type, privacy_consent, newsletter_consent, privacy_consent_date, iban, iban_updated_at, created_at FROM {$this->tableName} WHERE id = ?",
            [(int)$userId]
        );

        // Decrypt and mask IBAN in export for security (show full IBAN only in profile)
        if ($userData && isset($userData[0]['iban']) && $userData[0]['iban']) {
            $storedIban = $userData[0]['iban'];

            // Try to decrypt if encryption is configured
            if (Encryption::isConfigured()) {
                $decrypted = Encryption::decrypt($storedIban);
                if ($decrypted !== false) {
                    $storedIban = $decrypted;
                }
            }

            $userData[0]['iban'] = $this->maskIBAN($storedIban);
        }

        $address = $this->getAddress($userId);

        $orders = $this->db->prepare(
            "SELECT o.id, o.status, o.created_at
             FROM orders o
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC",
            [(int)$userId]
        );

        $orderItems = [];
        foreach ($orders as $order) {
            $items = $this->db->prepare(
                "SELECT oi.id, p.name as product_name, oi.quantity, oi.single_price, oi.status
                 FROM order_item oi
                 INNER JOIN product p ON oi.product_id = p.id
                 WHERE oi.order_id = ?",
                [(int)$order['id']]
            );
            $orderItems[$order['id']] = $items;
        }

        return [
            'user' => $userData[0] ?? null,
            'address' => $address,
            'orders' => $orders,
            'order_items' => $orderItems,
            'export_date' => date('Y-m-d H:i:s')
        ];
    }

    // IBAN Management
    public function isValidIBAN($iban) {
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Check basic format (2 letters + 2 digits + up to 30 alphanumeric)
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }

        // Italian IBAN specific check (27 characters)
        if (substr($iban, 0, 2) === 'IT' && strlen($iban) !== 27) {
            return false;
        }

        // IBAN checksum validation (ISO 7064 Mod 97-10)
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        // Mod 97 check using bcmod for large numbers
        return bcmod($numeric, '97') === '1';
    }

    public function formatIBAN($iban) {
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));
        // Format in groups of 4 for readability
        return implode(' ', str_split($iban, 4));
    }

    public function saveIBAN($userId, $iban, $ownerName = null) {
        // Clean IBAN
        $cleanIban = strtoupper(str_replace(' ', '', $iban));
        // Clean owner name (trim whitespace)
        $cleanOwnerName = $ownerName ? trim($ownerName) : null;

        // Encrypt before storing
        if (Encryption::isConfigured()) {
            $encryptedIban = Encryption::encrypt($cleanIban);
            if ($encryptedIban === false) {
                error_log("Failed to encrypt IBAN for user $userId");
                return false;
            }
            $this->db->execute(
                "UPDATE {$this->tableName} SET iban = ?, iban_owner_name = ?, iban_updated_at = NOW() WHERE id = ?",
                [$encryptedIban, $cleanOwnerName, (int)$userId]
            );
        } else {
            // Fallback to plain storage if encryption not configured (log warning)
            error_log("WARNING: Encryption key not configured. IBAN stored in plain text for user $userId");
            $this->db->execute(
                "UPDATE {$this->tableName} SET iban = ?, iban_owner_name = ?, iban_updated_at = NOW() WHERE id = ?",
                [$cleanIban, $cleanOwnerName, (int)$userId]
            );
        }
        return true;
    }

    public function getIBAN($userId) {
        $result = $this->db->prepare(
            "SELECT iban, iban_owner_name, iban_updated_at FROM {$this->tableName} WHERE id = ?",
            [(int)$userId]
        );
        if (count($result) > 0 && $result[0]['iban']) {
            $storedIban = $result[0]['iban'];

            // Try to decrypt (will return false if not encrypted or decryption fails)
            if (Encryption::isConfigured()) {
                $decrypted = Encryption::decrypt($storedIban);
                if ($decrypted !== false) {
                    $storedIban = $decrypted;
                }
                // If decryption fails, assume it's plain text (legacy data)
            }

            return [
                'iban' => $storedIban,
                'iban_formatted' => $this->formatIBAN($storedIban),
                'iban_owner_name' => $result[0]['iban_owner_name'],
                'iban_updated_at' => $result[0]['iban_updated_at']
            ];
        }
        return null;
    }

    public function deleteIBAN($userId) {
        $this->db->execute(
            "UPDATE {$this->tableName} SET iban = NULL, iban_owner_name = NULL, iban_updated_at = NULL WHERE id = ?",
            [(int)$userId]
        );
    }

    public function maskIBAN($iban) {
        // Show only first 4 and last 4 characters for privacy
        $clean = str_replace(' ', '', $iban);
        if (strlen($clean) <= 8) {
            return $clean;
        }
        return substr($clean, 0, 4) . str_repeat('*', strlen($clean) - 8) . substr($clean, -4);
    }

    // GDPR Account Deletion
    public function requestAccountDeletion($userId) {
        // Mark account for deletion (soft delete)
        $this->db->execute(
            "UPDATE {$this->tableName} SET deletion_requested = 1, deletion_requested_date = NOW() WHERE id = ?",
            [(int)$userId]
        );
        return true;
    }

    public function deleteAccount($userId) {
        // Delete address
        $this->db->execute(
            "DELETE FROM address WHERE user_id = ?",
            [(int)$userId]
        );

        // Anonymize orders (keep for accounting but remove personal data)
        $this->db->execute(
            "UPDATE orders SET user_id = 0 WHERE user_id = ?",
            [(int)$userId]
        );

        // Delete cart items
        $this->db->execute(
            "DELETE ci FROM cart_item ci
             INNER JOIN cart c ON ci.cart_id = c.id
             WHERE c.user_id = ?",
            [(int)$userId]
        );

        // Delete cart
        $this->db->execute(
            "DELETE FROM cart WHERE user_id = ?",
            [(int)$userId]
        );

        // Delete user
        $this->db->execute(
            "DELETE FROM {$this->tableName} WHERE id = ?",
            [(int)$userId]
        );

        return true;
    }

    /*
      Private Methods
    */

    private function _createUser($user, $password){
      $id = parent::create($user);
      $this->updatePassword($id, $password);
      return $id;
    }

    private function _getPassword($userId) {
        $result = $this->db->prepare(
            "SELECT password FROM user WHERE id = ?",
            [(int)$userId]
        );
        if ($result) {
            return $result[0]['password'];
        }
        return null;
    }

    private function _getUserByEmail($email) {
        $result = $this->db->prepare(
            "SELECT id, email, first_name, last_name, user_type, profile_id FROM {$this->tableName} WHERE email = ?",
            [$email]
        );
        if (count($result) == 0) {
            return null;
        }
        return $result[0];
    }



  }
