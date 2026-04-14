<?php
class Questionnaire {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($userId, $data) {
        $this->db->execute(
            "INSERT INTO questionnaires (user_id, title, description, identity_mode, start_date, end_date, allow_multiple, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            array($userId, $data['title'], $data['description'], $data['identity_mode'], $data['start_date'], $data['end_date'], $data['allow_multiple'] ?? 0, $data['is_active'] ?? 1)
        );
        
        $id = $this->db->lastInsertId();
        
        if (isset($data['access_codes_count']) && $data['access_codes_count'] > 0) {
            $this->db->generateAccessCodes($id, (int)$data['access_codes_count']);
        }
        
        return $id;
    }
    
    public function update($id, $data) {
        $this->db->execute(
            "UPDATE questionnaires SET title = ?, description = ?, identity_mode = ?, start_date = ?, end_date = ?, allow_multiple = ?, is_active = ? WHERE id = ?",
            array($data['title'], $data['description'], $data['identity_mode'], $data['start_date'], $data['end_date'], $data['allow_multiple'] ?? 0, $data['is_active'] ?? 1, $id)
        );
    }
    
    public function delete($id) {
        $this->db->execute("DELETE FROM questions WHERE questionnaire_id = ?", array($id));
        return $this->db->execute("DELETE FROM questionnaires WHERE id = ?", array($id));
    }
    
    public function get($id) {
        return $this->db->queryOne("SELECT * FROM questionnaires WHERE id = ?", array($id));
    }
    
    public function getAll($userId) {
        $surveys = $this->db->query("SELECT * FROM questionnaires WHERE user_id = ? ORDER BY created_at DESC", array($userId));
        
        foreach ($surveys as &$s) {
            $count = $this->db->queryOne("SELECT COUNT(*) as cnt FROM questions WHERE questionnaire_id = ?", array($s['id']));
            $s['questions_count'] = $count ? (int)$count['cnt'] : 0;
        }
        
        return $surveys;
    }
    
    public function getQuestions($questionnaireId) {
        return $this->db->query("SELECT * FROM questions WHERE questionnaire_id = ? ORDER BY order_index", array($questionnaireId));
    }
    
    public function addQuestion($questionnaireId, $data) {
        $max = $this->db->queryOne("SELECT MAX(order_index) as mx FROM questions WHERE questionnaire_id = ?", array($questionnaireId));
        $order = $max && $max['mx'] ? (int)$max['mx'] + 1 : 0;
        
        $options = is_array($data['options'] ?? '') ? json_encode($data['options']) : ($data['options'] ?? '');
        
        $this->db->execute(
            "INSERT INTO questions (questionnaire_id, type, question_text, options, required, order_index) VALUES (?, ?, ?, ?, ?, ?)",
            array($questionnaireId, $data['type'], $data['question_text'], $options, $data['required'] ?? 0, $order)
        );
        
        return $this->db->lastInsertId();
    }
    
    public function deleteQuestion($questionId) {
        return $this->db->execute("DELETE FROM questions WHERE id = ?", array($questionId));
    }
    
    public function getStats($questionnaireId) {
        $resp = $this->db->queryResponseOne("SELECT COUNT(*) as cnt FROM responses WHERE questionnaire_id = ?", array($questionnaireId));
        $quest = $this->db->queryOne("SELECT COUNT(*) as cnt FROM questions WHERE questionnaire_id = ?", array($questionnaireId));
        
        return array(
            'total_responses' => $resp ? (int)$resp['cnt'] : 0,
            'questions_count' => $quest ? (int)$quest['cnt'] : 0
        );
    }
}
