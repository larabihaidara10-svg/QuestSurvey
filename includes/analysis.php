<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/questionnaire.php';
require_once __DIR__ . '/response.php';

class AnalysisModel {
    private $db;
    private $questionnaire;
    private $response;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->questionnaire = new QuestionnaireModel();
        $this->response = new ResponseModel();
    }
    
    public function analyzeQuestionnaire($questionnaireId) {
        $questions = $this->questionnaire->getQuestions($questionnaireId);
        $responses = $this->response->getResponsesWithAnswers($questionnaireId);
        
        $analysis = [
            'total_responses' => count($responses),
            'questions' => []
        ];
        
        foreach ($questions as $q) {
            $analysis['questions'][] = $this->analyzeQuestion($q, $responses);
        }
        
        return $analysis;
    }
    
    private function analyzeQuestion($question, $responses) {
        $type = $question['type'];
        $options = json_decode($question['options'] ?? '[]', true) ?: [];
        $answers = [];
        
        foreach ($responses as $r) {
            if (isset($r['answers'][$question['id']])) {
                $answers[] = $r['answers'][$question['id']];
            }
        }
        
        $result = [
            'id' => $question['id'],
            'text' => $question['question_text'],
            'type' => $type,
            'total_answers' => count($answers),
            'answers' => $answers
        ];
        
        switch ($type) {
            case 'multiple_choice':
                $counts = array_count_values($answers);
                $result['counts'] = $counts;
                $result['percentages'] = $this->calculatePercentages($counts, count($answers));
                break;
                
            case 'likert_5':
                $numeric = array_map('intval', $answers);
                $counts = array_count_values($numeric);
                $result['counts'] = $counts;
                $result['average'] = count($numeric) > 0 ? array_sum($numeric) / count($numeric) : 0;
                $result['min'] = count($numeric) > 0 ? min($numeric) : 0;
                $result['max'] = count($numeric) > 0 ? max($numeric) : 0;
                break;
                
            case 'yes_no':
            case 'true_false':
                $counts = array_count_values($answers);
                $result['counts'] = $counts;
                $result['percentages'] = $this->calculatePercentages($counts, count($answers));
                break;
                
            case 'number':
                $numeric = array_map('floatval', $answers);
                if (count($numeric) > 0) {
                    $result['average'] = array_sum($numeric) / count($numeric);
                    $result['min'] = min($numeric);
                    $result['max'] = max($numeric);
                    $result['sum'] = array_sum($numeric);
                }
                break;

            case 'date':
                $result['date_responses'] = $answers;
                break;

            case 'short_text':
            case 'long_text':
                $result['text_responses'] = $answers;
                break;
        }
        
        return $result;
    }
    
    private function calculatePercentages($counts, $total) {
        $percentages = [];
        foreach ($counts as $key => $count) {
            $percentages[$key] = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        }
        return $percentages;
    }
}