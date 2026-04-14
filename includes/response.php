<?php
class ResponseHandler {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function createResponse($questionnaireId, $respondentName = null, $studentNumber = null, $accessCodeId = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $this->db->executeResponse(
            "INSERT INTO responses (questionnaire_id, respondent_name, student_number, access_code_id, ip_address) VALUES (?, ?, ?, ?, ?)",
            array($questionnaireId, $respondentName, $studentNumber, $accessCodeId, $ip)
        );
        
        return $this->db->lastInsertResponseId();
    }
    
    public function saveAnswer($responseId, $questionId, $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        
        $this->db->executeResponse(
            "INSERT INTO answers (response_id, question_id, answer_value) VALUES (?, ?, ?)",
            array($responseId, $questionId, $value)
        );
    }
    
    public function saveMultiple($responseId, $answers) {
        foreach ($answers as $questionId => $value) {
            $this->saveAnswer($responseId, $questionId, $value);
        }
    }
    
    public function getResponses($questionnaireId, $limit = 10000) {
        return $this->db->queryResponse("SELECT * FROM responses WHERE questionnaire_id = ? ORDER BY submitted_at DESC LIMIT ?", array($questionnaireId, $limit));
    }
    
    public function getAnswers($responseId) {
        $answers = $this->db->queryResponse("SELECT question_id, answer_value FROM answers WHERE response_id = ?", array($responseId));
        
        $result = array();
        foreach ($answers as $a) {
            $val = $a['answer_value'];
            $decoded = json_decode($val, true);
            $result[$a['question_id']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
        }
        
        return $result;
    }
}
