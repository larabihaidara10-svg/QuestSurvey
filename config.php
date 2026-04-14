<?php
// QuestSurvey Configuration

define('APP_NAME', 'QuestSurvey');
define('APP_VERSION', '1.0.0');
define('DEBUG', false);

define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');
define('INCLUDES_DIR', APP_ROOT . '/includes');
define('PAGES_DIR', APP_ROOT . '/pages');
define('TEMPLATES_DIR', APP_ROOT . '/templates');
define('ASSETS_DIR', APP_ROOT . '/assets');

define('DB_FILE', DATA_DIR . '/questionnaires.db');
define('RESPONSES_FILE', DATA_DIR . '/responses.db');

define('QUESTION_TYPES', array(
    'multiple_choice' => 'Multiple Choice',
    'likert_5' => 'Likert Scale (1-5)',
    'short_text' => 'Short Text',
    'long_text' => 'Long Text',
    'yes_no' => 'Yes/No',
    'date' => 'Date',
    'number' => 'Number'
));

define('IDENTITY_MODES', array(
    'anonymous' => 'Anonymous',
    'identified' => 'Identified',
    'access_code' => 'Access Code'
));

function isLoggedIn() {
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return $_SESSION['user'];
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getQuestionnaireStatus($questionnaire) {
    if (!$questionnaire['is_active']) return 'inactive';
    $now = date('Y-m-d H:i:s');
    if ($questionnaire['start_date'] && $now < $questionnaire['start_date']) return 'pending';
    if ($questionnaire['end_date'] && $now > $questionnaire['end_date']) return 'expired';
    return 'active';
}
