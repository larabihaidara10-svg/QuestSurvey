<?php
require_once __DIR__ . '/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($username, $password, $email = null, $firstName = null, $lastName = null, $contact = null, $role = 'user') {
        $existing = $this->db->queryOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            return ['success' => false, 'error' => 'Username already exists'];
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO users (username, password, email, first_name, last_name, contact, role) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$username, $hash, $email, $firstName, $lastName, $contact, $role]
        );
        
        return ['success' => true, 'user_id' => $this->db->lastInsertId()];
    }
    
    public function login($username, $password, $adminKey = null) {
        $user = $this->db->queryOne("SELECT * FROM users WHERE username = ?", [$username]);
        
        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }
        
        if ($adminKey && $this->isAdminKey($adminKey)) {
            $user['role'] = 'creator';
        }
        
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'contact' => $user['contact'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        
        return ['success' => true, 'user' => $_SESSION['user']];
    }
    
    private function isAdminKey($key) {
        return $key === 'CREATE_CREATOR_2026' || $key === 'admin123';
    }
    
    public function logout() {
        session_destroy();
    }
    
    public function getUser($userId) {
        return $this->db->queryOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }
    
    public function getAllUsers() {
        return $this->db->query("SELECT id, username, email, first_name, last_name, role, created_at FROM users ORDER BY created_at DESC");
    }
    
    public function deleteUser($userId) {
        $user = $this->getUser($userId);
        if ($user && $user['role'] === 'admin') {
            return false;
        }
        return $this->db->execute("DELETE FROM users WHERE id = ?", [$userId]);
    }
}