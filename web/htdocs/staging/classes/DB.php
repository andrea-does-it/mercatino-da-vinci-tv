<?php

class DB {

    private $conn;
    public $pdo;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
        if (mysqli_connect_errno()) {
            throw new Exception('Failed to connect to MySQL: ' . mysqli_connect_errno());
        }
        $this->pdo = new PDO(
            'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }

    /**
     * Execute a query with prepared statements (SECURE)
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @return array Query results
     */
    public function prepare($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Execute an INSERT/UPDATE/DELETE with prepared statements (SECURE)
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @return int|bool Last insert ID for INSERT, affected rows for UPDATE/DELETE
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Return last insert ID for INSERT statements
            if (stripos(trim($sql), 'INSERT') === 0) {
                return $this->pdo->lastInsertId();
            }
            // Return affected rows for UPDATE/DELETE
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * @deprecated Use prepare() with parameterized queries instead
     * Legacy query method - DO NOT USE for user input
     */
    public function query($sql) {
        try {
            $q = $this->pdo->query($sql);
            if (!$q) {
                throw new Exception("Error executing query...");
            }
            return $q->fetchAll();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @deprecated Use execute() with parameterized queries instead
     */
    public function exec($sql) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            return ['result' => false, 'message' => $e->getMessage()];
        }
        return ['result' => true, 'message' => 'OK'];
    }

    /**
     * Select all rows from a table (SECURE - uses prepared statements)
     */
    public function select_all($tableName, $columns = []) {
        // Whitelist column names (no user input allowed)
        $allowedColumns = array_map(function($col) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        }, $columns);

        $strCol = implode(', ', $allowedColumns);
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        $query = "SELECT $strCol FROM $tableName";
        return $this->prepare($query);
    }

    /**
     * Select one row by ID (SECURE - uses prepared statements)
     */
    public function select_one($tableName, $columns = [], $id) {
        // Whitelist column names
        $allowedColumns = array_map(function($col) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        }, $columns);

        $strCol = implode(', ', $allowedColumns);
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        $query = "SELECT $strCol FROM $tableName WHERE id = ?";
        $result = $this->prepare($query, [(int)$id]);

        return $result ? $result[0] : null;
    }

    /**
     * Delete one row by ID (SECURE - uses prepared statements)
     */
    public function delete_one($tableName, $id) {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $query = "DELETE FROM $tableName WHERE id = ?";
        return $this->execute($query, [(int)$id]);
    }

    /**
     * Update one row by ID (SECURE - uses prepared statements)
     */
    public function update_one($tableName, $columns = [], $id) {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        $setParts = [];
        $params = [];

        foreach ($columns as $colName => $colValue) {
            $colName = preg_replace('/[^a-zA-Z0-9_]/', '', $colName);
            if ($colValue === 'NULL' || $colValue === null) {
                $setParts[] = "$colName = NULL";
            } else {
                $setParts[] = "$colName = ?";
                $params[] = $colValue;
            }
        }

        $params[] = (int)$id;
        $query = "UPDATE $tableName SET " . implode(', ', $setParts) . " WHERE id = ?";

        return $this->execute($query, $params);
    }

    /**
     * Insert one row (SECURE - uses prepared statements)
     */
    public function insert_one($tableName, $columns = []) {
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

        $colNames = [];
        $placeholders = [];
        $params = [];

        foreach ($columns as $colName => $colValue) {
            $colName = preg_replace('/[^a-zA-Z0-9_]/', '', $colName);
            $colNames[] = $colName;
            $placeholders[] = '?';
            $params[] = $colValue;
        }

        $query = "INSERT INTO $tableName (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $placeholders) . ")";

        return $this->execute($query, $params);
    }
}

class DBManager {

  protected $db;
  protected $columns;
  protected $tableName;

  public function __construct(){
    $this->db = new DB();
  }

  public function get($id) {
    $resultArr = $this->db->select_one($this->tableName, $this->columns, (int)$id);
    return (object) $resultArr;
  }

  public function getAll() {
    $results = $this->db->select_all($this->tableName, $this->columns);
    $objects = array();
    foreach($results as $result) {
      array_push($objects, (object)$result);
    }
    return $objects;
  }

  public function create($obj) {
  //var_dump($obj);die;
    $newId = $this->db->insert_one($this->tableName, (array) $obj);
    return $newId;
  }

  public function delete($id) {
    $rowsDeleted = $this->db->delete_one($this->tableName, (int)$id);
    return (int) $rowsDeleted;
  }

  public function update($obj, $id) {
    $rowsUpdated = $this->db->update_one($this->tableName, (array) $obj, (int)$id);
    return (int) $rowsUpdated;
  }
}