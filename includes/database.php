<?php
class Database {
    private static $instance = null;
    private $pdo;
    private $responsesDb;
    
    private function __construct() {
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        
        $this->pdo = new PDO('sqlite:' . DB_FILE);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initMainDatabase();
        
        $this->responsesDb = new PDO('sqlite:' . RESPONSES_FILE);
        $this->responsesDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initResponsesDatabase();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initMainDatabase() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT,
                first_name TEXT,
                last_name TEXT,
                contact TEXT,
                role TEXT DEFAULT 'creator',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS questionnaires (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                identity_mode TEXT DEFAULT 'anonymous',
                start_date DATETIME,
                end_date DATETIME,
                allow_multiple INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                questionnaire_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                question_text TEXT NOT NULL,
                options TEXT,
                required INTEGER DEFAULT 0,
                order_index INTEGER DEFAULT 0,
                FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS access_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                questionnaire_id INTEGER NOT NULL,
                code TEXT NOT NULL UNIQUE,
                is_used INTEGER DEFAULT 0,
                used_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE
            );
        ");
    }
    
    private function initResponsesDatabase() {
        $this->responsesDb->exec("
            CREATE TABLE IF NOT EXISTS responses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                questionnaire_id INTEGER NOT NULL,
                respondent_name TEXT,
                student_number TEXT,
                access_code_id INTEGER,
                ip_address TEXT,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS answers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                response_id INTEGER NOT NULL,
                question_id INTEGER NOT NULL,
                answer_value TEXT,
                FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
                FOREIGN KEY (question_id) REFERENCES questions(id)
            );
        ");
    }
    
    public function query($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function queryOne($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function execute($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function queryResponse($sql, $params = array()) {
        $stmt = $this->responsesDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function queryResponseOne($sql, $params = array()) {
        $stmt = $this->responsesDb->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function executeResponse($sql, $params = array()) {
        $stmt = $this->responsesDb->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function lastInsertResponseId() {
        return $this->responsesDb->lastInsertId();
    }
    
    public static function isInstalled() {
        return file_exists(DB_FILE) && file_exists(RESPONSES_FILE);
    }
    
    public static function install($username, $password, $email, $firstName, $lastName, $contact) {
        $db = self::getInstance();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->execute(
            "INSERT INTO users (username, password, email, first_name, last_name, contact, role) VALUES (?, ?, ?, ?, ?, ?, 'admin')",
            array($username, $hash, $email, $firstName, $lastName, $contact)
        );
        return $db->lastInsertId();
    }
    
    public function generateAccessCodes($questionnaireId, $count) {
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $this->execute("INSERT INTO access_codes (questionnaire_id, code) VALUES (?, ?)", array($questionnaireId, $code));
        }
    }
    
    public function validateAccessCode($questionnaireId, $code) {
        return $this->queryOne("SELECT id FROM access_codes WHERE questionnaire_id = ? AND code = ? AND is_used = 0", array($questionnaireId, $code));
    }
    
    public function markAccessCodeUsed($codeId) {
        $this->execute("UPDATE access_codes SET is_used = 1, used_at = CURRENT_TIMESTAMP WHERE id = ?", array($codeId));
    }
}
