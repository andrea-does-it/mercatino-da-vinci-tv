<?php

  class User {

    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $user_type;
    public $profile_id;

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
    
    public function register($first_name, $last_name, $email, $password, $profile_id){
      $user = new User(0, $first_name, $last_name, $email, 'regular', $profile_id);
      $userId = $this->_createUser($user, $password);
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
      return strlen($pwd) > 6;
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
        $pwd = $password ? $password : User::generatePassword();
        $pwd = password_hash($pwd, PASSWORD_DEFAULT);
        $this->db->execute(
            "UPDATE {$this->tableName} SET password = ? WHERE id = ?",
            [$pwd, (int)$userId]
        );
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