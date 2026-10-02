<?php

  class User {

    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $user_type;
    public $profile_id;
    public $student_first_name;
    public $student_last_name;
    public $student_class;
    public $privacy_consent;
    public $newsletter_consent;
    // Default 0: DBManager->create() casts the whole object to an INSERT, and the
    // donate_books column is NOT NULL — an uninitialized (null) property would break
    // user registration. The registration opt-in upgrades this to 1 afterwards.
    public $donate_books = 0;

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
      $this->columns = array('id', 'email', 'first_name', 'last_name', 'user_type', 'profile_id', 'student_first_name', 'student_last_name', 'student_class');
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

    public function register($first_name, $last_name, $email, $password, $profile_id, $privacy_consent = true, $newsletter_consent = false, $student_first_name = '', $student_last_name = '', $student_class = '', $donate_books = false){
      $user = new User(0, $first_name, $last_name, $email, 'regular', $profile_id);
      $userId = $this->_createUser($user, $password);

      // Save consent information
      if ($userId > 0) {
        $this->saveConsent($userId, $privacy_consent, $newsletter_consent);
        if ($student_first_name !== '' || $student_last_name !== '' || $student_class !== '') {
          $this->saveStudentInfo($userId, $student_first_name, $student_last_name, $student_class);
        }
        if ($donate_books) {
          $this->updateDonateBooks($userId, 1);
        }
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

    public function getUserByEmail($email){
      return $this->_getUserByEmail($email);
    }

    public function createUser($user, $password){
      return $this->_createUser($user, $password);
    }

    // Student Info Methods
    public function saveStudentInfo($userId, $studentFirstName, $studentLastName, $studentClass) {
        $this->db->execute(
            "UPDATE {$this->tableName} SET student_first_name = ?, student_last_name = ?, student_class = ? WHERE id = ?",
            [trim($studentFirstName), trim($studentLastName), strtoupper(trim($studentClass)), (int)$userId]
        );
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

    public function updateDonateBooks($userId, $consent) {
        $this->db->execute(
            "UPDATE {$this->tableName} SET donate_books = ?, donate_books_date = NOW() WHERE id = ?",
            [(int)$consent, (int)$userId]
        );
    }

    public function getDonateBooks($userId) {
        $result = $this->db->prepare(
            "SELECT donate_books FROM {$this->tableName} WHERE id = ?",
            [(int)$userId]
        );
        if (count($result) > 0) {
            return (int)$result[0]['donate_books'];
        }
        return 0;
    }

    public function getConsent($userId) {
        $result = $this->db->prepare(
            "SELECT privacy_consent, newsletter_consent, privacy_consent_date, newsletter_consent_date, donate_books, donate_books_date FROM {$this->tableName} WHERE id = ?",
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

    // Admin: modifica completa di un utente (pagina admin/pages/user.php)

    /** @var bool|null Cache di processo: esiste la colonna iban_town (migrazione 202610020001)? */
    private static $ibanTownColumnExists = null;

    /** true se la colonna iban_town esiste su questo DB. Cache statica: una sola query per request. */
    private function hasIbanTownColumn() {
        if (self::$ibanTownColumnExists === null) {
            try {
                self::$ibanTownColumnExists = count($this->db->prepare(
                    "SHOW COLUMNS FROM {$this->tableName} LIKE 'iban_town'"
                )) === 1;
            } catch (Exception $e) {
                self::$ibanTownColumnExists = false;
            }
        }
        return self::$ibanTownColumnExists;
    }

    /** Wrapper pubblico: admin/pages/user.php lo usa per nascondere il campo "Localita' beneficiario". */
    public function supportsIbanTown() {
        return $this->hasIbanTownColumn();
    }

    /**
     * Riga con i campi non esposti dall'oggetto User (studente, IBAN, donazione,
     * consensi). Non include MAI password o reset_link. iban_town e' incluso solo se
     * la colonna esiste (altrimenti la SELECT fallirebbe sui DB senza la migrazione
     * 202610020001).
     */
    public function getAdminRow($userId) {
        $cols = "student_first_name, student_last_name, student_class,
                 iban_owner_name, iban_updated_at,
                 donate_books, donate_books_date,
                 privacy_consent, privacy_consent_date,
                 newsletter_consent, newsletter_consent_date,
                 deletion_requested, deletion_requested_date";
        if ($this->hasIbanTownColumn()) {
            $cols .= ", iban_town";
        }
        $rows = $this->db->prepare(
            "SELECT {$cols} FROM {$this->tableName} WHERE id = ?",
            [(int)$userId]
        );
        return $rows ? (object)$rows[0] : null;
    }

    /**
     * Confronta l'IBAN postato (grezzo) con quello salvato (decifrato) e stabilisce
     * cosa fare: non scrive nulla. Usato sia da validateAdminData() (solo per
     * l'eventuale errore) sia da adminUpdate() (anche per il valore da cifrare).
     * $currentUserId = 0 vuol dire "nessun utente esistente ancora" (creazione):
     * in quel caso un IBAN non vuoto e' sempre "nuovo".
     * @return array [string|null $action ('save'|'clear'|null), string $normalizedValue, string|null $error]
     */
    private function determineIbanAction(array $data, $currentUserId) {
        if (!array_key_exists('iban', $data)) {
            return [null, '', null];
        }
        $normalizedNew = SepaCbiExport::normalizeIban((string)$data['iban']);
        $normalizedCurrent = '';
        if ((int)$currentUserId > 0) {
            $currentIbanInfo = $this->getIBAN($currentUserId);
            $normalizedCurrent = $currentIbanInfo ? SepaCbiExport::normalizeIban($currentIbanInfo['iban']) : '';
        }
        if ($normalizedNew === $normalizedCurrent) {
            return [null, '', null];
        }
        if ($normalizedNew === '') {
            return ['clear', '', null];
        }
        if (!SepaCbiExport::ibanIsValid($normalizedNew)) {
            return [null, '', 'IBAN non valido.'];
        }
        if (!Encryption::isConfigured()) {
            return [null, '', "Cifratura IBAN non configurata: impossibile salvare l'IBAN."];
        }
        return ['save', $normalizedNew, null];
    }

    /**
     * Validazione condivisa fra adminUpdate() e la creazione da admin
     * (admin/pages/user.php, prima di chiamare createUser()): solo errori, nessuna
     * scrittura. $excludeId e' l'utente da escludere dal controllo di unicita'
     * dell'email (0 = nessuno, cioe' nuovo utente: controlla su tutti) ed e' anche
     * l'utente di cui leggere l'IBAN attuale (0 = nessuno ancora).
     * @return string[] errori in italiano, vuoto se tutto ok
     */
    public function validateAdminData(array $data, $excludeId = 0) {
        $excludeId = (int)$excludeId;
        $errors = [];

        $first_name = trim((string)($data['first_name'] ?? ''));
        $last_name  = trim((string)($data['last_name'] ?? ''));
        $email      = trim((string)($data['email'] ?? ''));
        $user_type  = trim((string)($data['user_type'] ?? ''));

        if ($first_name === '') {
            $errors[] = 'Il nome è obbligatorio.';
        }
        if ($last_name === '') {
            $errors[] = 'Il cognome è obbligatorio.';
        }
        if ($email === '') {
            $errors[] = "L'email è obbligatoria.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email non valida.';
        } else {
            $dupe = $this->db->prepare(
                "SELECT id FROM {$this->tableName} WHERE email = ? AND id <> ?",
                [$email, $excludeId]
            );
            if ($dupe) {
                $errors[] = 'Email già usata da un altro utente.';
            }
        }
        if ($user_type === '') {
            $errors[] = 'Il tipo utente è obbligatorio.';
        } elseif (!in_array($user_type, ['regular', 'admin', 'pwuser'], true)) {
            $errors[] = 'Tipo utente non valido.';
        }

        $student_class = trim((string)($data['student_class'] ?? ''));
        if (mb_strlen($student_class) > 3) {
            $errors[] = 'La classe può avere al massimo 3 caratteri.';
        }

        list(, , $ibanError) = $this->determineIbanAction($data, $excludeId);
        if ($ibanError !== null) {
            $errors[] = $ibanError;
        }

        return $errors;
    }

    /**
     * Modifica completa di un utente da admin. Valida tutto prima di scrivere
     * qualsiasi cosa (validateAdminData()), cifra un eventuale nuovo IBAN PRIMA di
     * aprire la transazione (cosi' un fallimento di cifratura non scrive nulla), poi
     * scrive in un'unica transazione su $this->db->pdo: le colonne "semplici"
     * cambiate, donate_books (+data) e l'IBAN. Non usa DBManager::update() (casta
     * l'intero oggetto User e azzererebbe le colonne che l'oggetto non espone, es.
     * student_*, privacy_consent, donate_books: vedi context/06-conventions-and-gotchas.md).
     *
     * @param int   $userId
     * @param array $data Campi: first_name, last_name, email, user_type, profile_id,
     *                    student_first_name, student_last_name, student_class,
     *                    iban_owner_name, iban_town, donate_books (0/1), iban (stringa grezza).
     * @return array ['ok' => bool, 'errors' => string[], 'changed' => string[]]
     */
    public function adminUpdate($userId, array $data) {
        $userId = (int)$userId;

        $cols = "first_name, last_name, email, user_type, profile_id,
                 student_first_name, student_last_name, student_class,
                 iban_owner_name, donate_books";
        if ($this->hasIbanTownColumn()) {
            $cols .= ", iban_town";
        }
        $currentRows = $this->db->prepare(
            "SELECT {$cols} FROM {$this->tableName} WHERE id = ?",
            [$userId]
        );
        if (!$currentRows) {
            return ['ok' => false, 'errors' => ['Utente non trovato.'], 'changed' => []];
        }
        $current = $currentRows[0];

        $errors = $this->validateAdminData($data, $userId);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'changed' => []];
        }

        $first_name = trim((string)($data['first_name'] ?? ''));
        $last_name  = trim((string)($data['last_name'] ?? ''));
        $email      = trim((string)($data['email'] ?? ''));
        $user_type  = trim((string)($data['user_type'] ?? ''));

        $profileRaw = trim((string)($data['profile_id'] ?? ''));
        $profile_id = ($profileRaw === '' || $profileRaw === '0') ? null : (int)$profileRaw;

        $student_class = trim((string)($data['student_class'] ?? ''));
        $student_first_name = trim((string)($data['student_first_name'] ?? ''));
        $student_last_name  = trim((string)($data['student_last_name'] ?? ''));
        $iban_owner_name    = trim((string)($data['iban_owner_name'] ?? ''));
        $iban_town          = trim((string)($data['iban_town'] ?? ''));

        $donate_books = !empty($data['donate_books']) ? 1 : 0;

        // Ricalcola l'azione IBAN: validateAdminData sopra ha gia' escluso l'errore,
        // ma qui serve anche il valore normalizzato da cifrare.
        list($ibanAction, $ibanToSave, $ibanError) = $this->determineIbanAction($data, $userId);
        if ($ibanError !== null) {
            // Difesa in profondita': non dovrebbe succedere, validateAdminData l'avrebbe
            // gia' intercettato sopra.
            return ['ok' => false, 'errors' => [$ibanError], 'changed' => []];
        }

        // Cifratura PRIMA della transazione: se fallisce, non si scrive nulla (ne'
        // l'IBAN ne' gli altri campi di questo stesso salvataggio).
        $encryptedIban = null;
        if ($ibanAction === 'save') {
            $encryptedIban = Encryption::encrypt($ibanToSave);
            if ($encryptedIban === false) {
                return ['ok' => false, 'errors' => ["Errore nella cifratura dell'IBAN."], 'changed' => []];
            }
        }

        // Colonne "semplici": un solo UPDATE parametrizzato, solo per quelle cambiate.
        // iban_owner_name resta fuori da qui se l'IBAN cambia: in quel caso lo si
        // scrive insieme a iban e iban_updated_at, piu' sotto, nella stessa transazione.
        $candidates = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'user_type' => $user_type,
            'profile_id' => $profile_id,
            'student_first_name' => $student_first_name !== '' ? $student_first_name : null,
            'student_last_name' => $student_last_name !== '' ? $student_last_name : null,
            'student_class' => $student_class !== '' ? strtoupper($student_class) : null,
        ];
        if ($this->hasIbanTownColumn()) {
            $candidates['iban_town'] = $iban_town !== '' ? $iban_town : null;
        }
        if ($ibanAction === null) {
            $candidates['iban_owner_name'] = $iban_owner_name !== '' ? $iban_owner_name : null;
        }

        $changed = [];
        $setCols = [];
        foreach ($candidates as $col => $value) {
            $curVal = array_key_exists($col, $current) ? $current[$col] : null;
            $curStr = $curVal === null ? '' : (string)$curVal;
            $newStr = $value === null ? '' : (string)$value;
            if ($curStr !== $newStr) {
                $changed[] = $col;
                $setCols[$col] = $value;
            }
        }

        $donateBooksChanged = $donate_books !== (int)$current['donate_books'];
        if ($donateBooksChanged) {
            $changed[] = 'donate_books';
        }
        if ($ibanAction !== null) {
            $changed[] = 'iban';
        }

        if (!$setCols && !$donateBooksChanged && $ibanAction === null) {
            // Nulla e' cambiato: nessuna transazione da aprire.
            return ['ok' => true, 'errors' => [], 'changed' => []];
        }

        $pdo = $this->db->pdo;
        $pdo->beginTransaction();
        try {
            if ($setCols) {
                $this->db->update_one($this->tableName, $setCols, $userId);
            }
            if ($donateBooksChanged) {
                $this->db->execute(
                    "UPDATE {$this->tableName} SET donate_books = ?, donate_books_date = " .
                    ($donate_books === 1 ? 'NOW()' : 'NULL') . " WHERE id = ?",
                    [$donate_books, $userId]
                );
            }
            if ($ibanAction === 'save') {
                $this->db->execute(
                    "UPDATE {$this->tableName} SET iban = ?, iban_owner_name = ?, iban_updated_at = NOW() WHERE id = ?",
                    [$encryptedIban, $iban_owner_name !== '' ? $iban_owner_name : null, $userId]
                );
            } elseif ($ibanAction === 'clear') {
                $this->db->execute(
                    "UPDATE {$this->tableName} SET iban = NULL, iban_owner_name = NULL, iban_updated_at = NULL WHERE id = ?",
                    [$userId]
                );
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('UserManager::adminUpdate: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['Errore nel salvataggio: nessuna modifica è stata fatta.'], 'changed' => []];
        }

        return ['ok' => true, 'errors' => [], 'changed' => $changed];
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
