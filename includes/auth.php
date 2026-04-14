<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($username, $password, $email, $firstName, $lastName, $contact, $role = 'creator') {
        $existing = $this->db->queryOne("SELECT id FROM users WHERE username = ?", array($username));
        if ($existing) {
            return array('success' => false, 'error' => 'Username already exists');
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (username, password, email, first_name, last_name, contact, role) VALUES (?, ?, ?, ?, ?, ?, ?)",
            array($username, $hash, $email, $firstName, $lastName, $contact, $role)
        );
        
        return array('success' => true, 'user_id' => $this->db->lastInsertId());
    }
    
    public function login($username, $password) {
        $user = $this->db->queryOne("SELECT * FROM users WHERE username = ?", array($username));
        
        if (!$user || !password_verify($password, $user['password'])) {
            return array('success' => false, 'error' => 'Invalid username or password');
        }
        
        $_SESSION['user'] = array(
            'id' => $user['id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'contact' => $user['contact'],
            'email' => $user['email'],
            'role' => $user['role']
        );
        
        return array('success' => true, 'user' => $_SESSION['user']);
    }
    
    public function logout() {
        session_destroy();
    }
    
    public function getUser($userId) {
        return $this->db->queryOne("SELECT * FROM users WHERE id = ?", array($userId));
    }
}
