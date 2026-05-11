<?php
require_once __DIR__ . '/database.php';

class QuestionnaireModel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($userId, $data) {
        $this->db->execute(
            "INSERT INTO questionnaires (user_id, title, description, category, identity_mode, start_date, end_date, allow_multiple, is_active, collect_email, collect_student_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $data['title'],
                $data['description'] ?? '',
                $data['category'] ?? '',
                $data['identity_mode'] ?? 'anonymous',
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
                $data['allow_multiple'] ?? 0,
                $data['is_active'] ?? 1,
                $data['collect_email'] ?? 0,
                $data['collect_student_number'] ?? 0
            ]
        );
        
        $id = $this->db->lastInsertId();
        
        if (isset($data['access_codes_count']) && $data['access_codes_count'] > 0) {
            $this->db->generateAccessCodes($id, (int)$data['access_codes_count']);
        }
        
        return $id;
    }
    
public function update($id, $data) {
        $oldSurvey = $this->get($id);
        $oldMode = $oldSurvey['identity_mode'] ?? '';
        $newMode = $data['identity_mode'] ?? 'anonymous';

        $this->db->execute(
            "UPDATE questionnaires SET title = ?, description = ?, category = ?, identity_mode = ?, start_date = ?, end_date = ?, allow_multiple = ?, is_active = ?, collect_email = ?, collect_student_number = ? WHERE id = ?",
            [
                $data['title'],
                $data['description'],
                $data['category'] ?? '',
                $newMode,
                $data['start_date'],
                $data['end_date'],
                $data['allow_multiple'] ?? 0,
                $data['is_active'] ?? 1,
                $data['collect_email'] ?? 0,
                $data['collect_student_number'] ?? 0,
                $id
            ]
        );

        // Generate access codes if mode changed to access_code
        if ($newMode === 'access_code' && !empty($data['access_codes_count'])) {
            $this->db->generateAccessCodes($id, (int)$data['access_codes_count']);
        }

        // Clear access codes if mode changed away from access_code
        if ($oldMode === 'access_code' && $newMode !== 'access_code') {
            $this->db->execute("DELETE FROM access_codes WHERE questionnaire_id = ?", [$id]);
        }
    }
    
    public function delete($id) {
        $this->db->execute("DELETE FROM questions WHERE questionnaire_id = ?", [$id]);
        return $this->db->execute("DELETE FROM questionnaires WHERE id = ?", [$id]);
    }
    
    public function get($id) {
        return $this->db->queryOne("SELECT * FROM questionnaires WHERE id = ?", [$id]);
    }
    
    public function getAll($userId) {
        $surveys = $this->db->query("SELECT * FROM questionnaires WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
        
        foreach ($surveys as &$s) {
            $count = $this->db->queryOne("SELECT COUNT(*) as cnt FROM questions WHERE questionnaire_id = ?", [$s['id']]);
            $s['questions_count'] = $count ? (int)$count['cnt'] : 0;
        }
        
        return $surveys;
    }
    
    public function getAllPublic() {
        return $this->db->query("SELECT q.*, u.username as creator_name FROM questionnaires q JOIN users u ON q.user_id = u.id WHERE q.is_active = 1 ORDER BY q.created_at DESC");
    }
    
    public function getQuestions($questionnaireId) {
        return $this->db->query("SELECT * FROM questions WHERE questionnaire_id = ? ORDER BY order_index", [$questionnaireId]);
    }
    
    public function addQuestion($questionnaireId, $data) {
        $max = $this->db->queryOne("SELECT MAX(order_index) as mx FROM questions WHERE questionnaire_id = ?", [$questionnaireId]);
        $order = $max && $max['mx'] ? (int)$max['mx'] + 1 : 0;
        
        $options = is_array($data['options'] ?? '') ? json_encode($data['options']) : ($data['options'] ?? '');
        
        $this->db->execute(
            "INSERT INTO questions (questionnaire_id, type, question_text, options, required, order_index) VALUES (?, ?, ?, ?, ?, ?)",
            [$questionnaireId, $data['type'], $data['question_text'], $options, $data['required'] ?? 0, $order]
        );
        
        return $this->db->lastInsertId();
    }
    
    public function deleteQuestion($questionId) {
        return $this->db->execute("DELETE FROM questions WHERE id = ?", [$questionId]);
    }
    
    public function copy($surveyId, $newUserId, $newTitle = null) {
        $survey = $this->get($surveyId);
        if (!$survey) return false;
        
        $questions = $this->getQuestions($surveyId);
        
        $data = [
            'title' => $newTitle ?: $survey['title'] . ' (Copy)',
            'description' => $survey['description'],
            'category' => $survey['category'],
            'identity_mode' => $survey['identity_mode'],
            'start_date' => null,
            'end_date' => null,
            'allow_multiple' => $survey['allow_multiple'],
            'is_active' => 0,
            'collect_email' => $survey['collect_email'],
            'collect_student_number' => $survey['collect_student_number']
        ];
        
        $newSurveyId = $this->create($newUserId, $data);
        
        foreach ($questions as $question) {
            $this->addQuestion($newSurveyId, [
                'type' => $question['type'],
                'question_text' => $question['question_text'],
                'options' => $question['options'],
                'required' => $question['required']
            ]);
        }
        
        return $newSurveyId;
    }
    
    public function transferOwnership($surveyId, $newUserId) {
        return $this->db->execute(
            "UPDATE questionnaires SET user_id = ? WHERE id = ?",
            [$newUserId, $surveyId]
        );
    }
    
    public function getStats($questionnaireId) {
        $resp = $this->db->queryResponseOne("SELECT COUNT(*) as cnt FROM responses WHERE questionnaire_id = ?", [$questionnaireId]);
        $quest = $this->db->queryOne("SELECT COUNT(*) as cnt FROM questions WHERE questionnaire_id = ?", [$questionnaireId]);
        
        return [
            'total_responses' => $resp ? (int)$resp['cnt'] : 0,
            'questions_count' => $quest ? (int)$quest['cnt'] : 0
        ];
    }
}