<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

if ($page === 'install' || $page === 'setup') {
    if (Database::isInstalled()) redirect('index.php');
    include 'install.php';
    exit;
}

if (!Database::isInstalled()) {
    redirect('index.php?page=install');
}

require_once INCLUDES_DIR . '/auth.php';
require_once INCLUDES_DIR . '/questionnaire.php';
require_once INCLUDES_DIR . '/response.php';

$auth = new Auth();
$questionnaire = new Questionnaire();
$responseHandler = new ResponseHandler();

$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
unset($_SESSION['message']);

switch ($page) {
    case 'login':
        if (isLoggedIn()) redirect('index.php');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
            if ($result['success']) redirect('index.php');
            else $error = $result['error'];
        }
        break;
        
    case 'register':
        if (isLoggedIn()) redirect('index.php');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $auth->register(
                $_POST['username'] ?? '',
                $_POST['password'] ?? '',
                $_POST['email'] ?? '',
                $_POST['first_name'] ?? '',
                $_POST['last_name'] ?? '',
                $_POST['contact'] ?? ''
            );
            if ($result['success']) {
                $auth->login($_POST['username'], $_POST['password']);
                redirect('index.php');
            } else {
                $error = $result['error'];
            }
        }
        break;
        
    case 'logout':
        $auth->logout();
        redirect('index.php?page=login');
        break;
        
    case 'surveys':
        if (!isLoggedIn()) redirect('index.php?page=login');
        $userId = getCurrentUser()['id'];
        $surveys = $questionnaire->getAll($userId);
        break;
        
    case 'create':
        if (!isLoggedIn()) redirect('index.php?page=login');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
            $userId = getCurrentUser()['id'];
            $data = array(
                'title' => $_POST['title'],
                'description' => $_POST['description'] ?? '',
                'identity_mode' => $_POST['identity_mode'] ?? 'anonymous',
                'start_date' => $_POST['start_date'] ?: null,
                'end_date' => $_POST['end_date'] ?: null,
                'allow_multiple' => isset($_POST['allow_multiple']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'access_codes_count' => $_POST['access_codes_count'] ?? 0
            );
            
            $surveyId = $questionnaire->create($userId, $data);
            if ($surveyId) {
                $_SESSION['message'] = array('type' => 'success', 'text' => 'Survey created!');
                redirect('index.php?page=edit&id=' . $surveyId);
            } else {
                $error = 'Failed to create survey';
            }
        }
        break;
        
    case 'edit':
        if (!isLoggedIn()) redirect('index.php?page=login');
        $surveyId = $_GET['id'] ?? 0;
        $survey = $questionnaire->get($surveyId);
        
        if (!$survey || $survey['user_id'] != getCurrentUser()['id']) {
            $_SESSION['message'] = array('type' => 'error', 'text' => 'Survey not found');
            redirect('index.php?page=surveys');
        }
        
        $questions = $questionnaire->getQuestions($surveyId);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['update_survey'])) {
                $questionnaire->update($surveyId, $_POST);
                $_SESSION['message'] = array('type' => 'success', 'text' => 'Survey updated!');
                redirect('index.php?page=edit&id=' . $surveyId);
            } elseif (isset($_POST['add_question'])) {
                $options = array();
                if (isset($_POST['options']) && is_array($_POST['options'])) {
                    $options = array_filter($_POST['options'], function($o) { return !empty(trim($o)); });
                }
                $questionnaire->addQuestion($surveyId, array(
                    'type' => $_POST['type'],
                    'question_text' => $_POST['question_text'],
                    'options' => $options,
                    'required' => isset($_POST['required']) ? 1 : 0
                ));
                $_SESSION['message'] = array('type' => 'success', 'text' => 'Question added!');
                redirect('index.php?page=edit&id=' . $surveyId);
            } elseif (isset($_POST['delete_question'])) {
                $questionnaire->deleteQuestion($_POST['question_id']);
                $_SESSION['message'] = array('type' => 'success', 'text' => 'Question deleted!');
                redirect('index.php?page=edit&id=' . $surveyId);
            }
        }
        break;
        
    case 'delete':
        if (!isLoggedIn()) redirect('index.php?page=login');
        $surveyId = $_GET['id'] ?? 0;
        $survey = $questionnaire->get($surveyId);
        
        if ($survey && $survey['user_id'] == getCurrentUser()['id']) {
            $questionnaire->delete($surveyId);
            $_SESSION['message'] = array('type' => 'success', 'text' => 'Survey deleted!');
        }
        
        redirect('index.php?page=surveys');
        break;
        
    case 'view':
        $surveyId = $_GET['id'] ?? 0;
        $survey = $questionnaire->get($surveyId);
        
        if (!$survey) die('Survey not found');
        
        $viewError = null;
        
        if ($survey['identity_mode'] === 'access_code' && isset($_POST['access_code'])) {
            $db = Database::getInstance();
            $codeData = $db->validateAccessCode($surveyId, $_POST['access_code']);
            if (!$codeData) {
                $viewError = 'Invalid or already used access code';
            }
        }
        
        $questions = $questionnaire->getQuestions($surveyId);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($viewError)) {
                // Invalid access code, don't process
            } else {
                $db = Database::getInstance();
                $respondentName = null;
                $studentNumber = null;
                $accessCodeId = null;
                
                if ($survey['identity_mode'] === 'identified') {
                    $respondentName = $_POST['respondent_name'] ?? '';
                    $studentNumber = $_POST['student_number'] ?? '';
                } elseif ($survey['identity_mode'] === 'access_code') {
                    $codeData = $db->validateAccessCode($surveyId, $_POST['access_code'] ?? '');
                    if ($codeData) $accessCodeId = $codeData['id'];
                }
                
                $responseId = $responseHandler->createResponse($surveyId, $respondentName, $studentNumber, $accessCodeId);
                
                if ($accessCodeId) $db->markAccessCodeUsed($accessCodeId);
                
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'question_') === 0) {
                        $questionId = (int)str_replace('question_', '', $key);
                        $responseHandler->saveAnswer($responseId, $questionId, $value);
                    }
                }
                
                $submitted = true;
            }
        }
        break;
        
    case 'results':
        if (!isLoggedIn()) redirect('index.php?page=login');
        $surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$surveyId) {
            $error = "No survey selected";
            break;
        }
        
        $survey = $questionnaire->get($surveyId);
        
        if (!$survey || $survey['user_id'] != getCurrentUser()['id']) {
            $error = "Survey not found or access denied";
            break;
        }
        
        $responses = $responseHandler->getResponses($surveyId);
        $questions = $questionnaire->getQuestions($surveyId);
        $totalResponses = count($responses);
        
        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=results_' . $surveyId . '.csv');
            
            $out = fopen('php://output', 'w');
            fputcsv($out, array('Response ID', 'Submitted', 'Name', 'Student Number'));
            
            foreach ($responses as $r) {
                fputcsv($out, array($r['id'], $r['submitted_at'], $r['respondent_name'] ?? '', $r['student_number'] ?? ''));
            }
            
            fclose($out);
            exit;
        }
        break;
        
    default:
        if (!isLoggedIn()) {
            $page = 'login';
            break;
        }
        if ($page === 'dashboard' || $page === '') {
            $currentUser = getCurrentUser();
            if ($currentUser && isset($currentUser['id'])) {
                $userId = $currentUser['id'];
                $surveys = $questionnaire->getAll($userId);
                $totalSurveys = count($surveys);
                $totalResponses = 0;
                foreach ($surveys as $s) {
                    $stats = $questionnaire->getStats($s['id']);
                    $totalResponses += $stats['total_responses'];
                }
            }
        }
        break;
}

include TEMPLATES_DIR . '/header.php';

switch ($page) {
    case 'login':
        ?>
        <div class="card" style="max-width: 400px; margin: 3rem auto;">
            <h1 class="card-title text-center">Login</h1>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <p class="text-center mt-3 text-sm">
                Don't have an account? <a href="index.php?page=register">Register</a>
            </p>
        </div>
        <?php
        break;
        
    case 'register':
        ?>
        <div class="card" style="max-width: 450px; margin: 3rem auto;">
            <h1 class="card-title text-center">Create Account</h1>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="flex gap-2 mb-2">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contact</label>
                    <input type="text" name="contact" class="form-input" placeholder="Phone or address">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            </form>
            
            <p class="text-center mt-3 text-sm">
                Already have an account? <a href="index.php?page=login">Login</a>
            </p>
        </div>
        <?php
        break;
        
    case 'surveys':
        ?>
        <div class="page-header">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="page-title">My Surveys</h1>
                    <p class="page-subtitle">Manage your questionnaires</p>
                </div>
                <a href="index.php?page=create" class="btn btn-primary">Create Survey</a>
            </div>
        </div>
        
        <?php if (empty($surveys)): ?>
        <div class="empty-state">
            <div class="empty-state-title">No surveys yet</div>
            <p>Create your first survey</p>
            <a href="index.php?page=create" class="btn btn-primary mt-2">Create Survey</a>
        </div>
        <?php else: ?>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Mode</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($surveys as $s): ?>
                    <?php 
                        $status = getQuestionnaireStatus($s);
                        $statusClass = $status === 'active' ? 'badge-success' : ($status === 'expired' ? 'badge-warning' : 'badge-primary');
                    ?>
                    <tr>
                        <td><a href="index.php?page=edit&id=<?php echo $s['id']; ?>"><?php echo sanitize($s['title']); ?></a></td>
                        <td><span class="badge badge-primary"><?php echo IDENTITY_MODES[$s['identity_mode']] ?? 'Anonymous'; ?></span></td>
                        <td><?php echo $s['questions_count']; ?></td>
                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span></td>
                        <td>
                            <div class="flex gap-1">
                                <a href="index.php?page=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">Edit</a>
                                <a href="index.php?page=view&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost" target="_blank">View</a>
                                <a href="index.php?page=results&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">Results</a>
                                <a href="index.php?page=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost" onclick="return confirmAction('Delete?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
        break;
        
    case 'create':
        ?>
        <div class="page-header">
            <h1 class="page-title">Create New Survey</h1>
            <p class="page-subtitle">Set up your questionnaire</p>
        </div>
        
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Survey Title *</label>
                    <input type="text" name="title" class="form-input" placeholder="Enter title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" placeholder="Describe your survey"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Identity Mode *</label>
                    <select name="identity_mode" class="form-select" required>
                        <option value="anonymous">Anonymous - No identification</option>
                        <option value="identified">Identified - Name & student number</option>
                        <option value="access_code">Access Code - One-time codes</option>
                    </select>
                </div>
                
                <div class="form-group" id="codesGroup" style="display: none;">
                    <label class="form-label">Number of Access Codes</label>
                    <input type="number" name="access_codes_count" class="form-input" placeholder="e.g., 30" min="1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="datetime-local" name="start_date" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="datetime-local" name="end_date" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="allow_multiple" value="1" class="form-check-input">
                        <span>Allow multiple responses</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                        <span>Active</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Survey</button>
                <a href="index.php?page=surveys" class="btn btn-ghost">Cancel</a>
            </form>
        </div>
        
        <script>
            document.querySelector('select[name="identity_mode"]').addEventListener('change', function() {
                document.getElementById('codesGroup').style.display = this.value === 'access_code' ? 'block' : 'none';
            });
        </script>
        <?php
        break;
        
    case 'edit':
        ?>
        <div class="page-header">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="page-title">Edit Survey</h1>
                    <p class="page-subtitle"><?php echo sanitize($survey['title']); ?></p>
                </div>
                <a href="index.php?page=view&id=<?php echo $surveyId; ?>" class="btn btn-primary" target="_blank">Preview</a>
            </div>
        </div>
        
        <div class="card mb-3">
            <form method="POST">
                <input type="hidden" name="update_survey" value="1">
                
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-input" value="<?php echo sanitize($survey['title']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea"><?php echo sanitize($survey['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Identity Mode</label>
                    <select name="identity_mode" class="form-select">
                        <option value="anonymous" <?php echo $survey['identity_mode'] === 'anonymous' ? 'selected' : ''; ?>>Anonymous - No identification</option>
                        <option value="identified" <?php echo $survey['identity_mode'] === 'identified' ? 'selected' : ''; ?>>Identified - Name & student number</option>
                        <option value="access_code" <?php echo $survey['identity_mode'] === 'access_code' ? 'selected' : ''; ?>>Access Code - One-time codes</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?php echo $survey['is_active'] ? 'checked' : ''; ?> class="form-check-input">
                        <span>Active</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">Questions (<?php echo count($questions); ?>)</h2>
            
            <?php if (empty($questions)): ?>
            <p class="text-muted mb-3">No questions yet. Add your first question below.</p>
            <?php else: ?>
            <?php foreach ($questions as $index => $q): ?>
            <?php 
                $rawOpts = $q['options'] ?? '';
                $qOptions = is_array($rawOpts) ? $rawOpts : json_decode($rawOpts, true);
                if (!is_array($qOptions)) $qOptions = array();
            ?>
            <div class="question-item">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="badge badge-primary mr-1"><?php echo $index + 1; ?></span>
                        <strong><?php echo sanitize($q['question_text']); ?></strong>
                        <span class="badge badge-warning ml-1"><?php echo ucfirst(str_replace('_', ' ', $q['type'])); ?></span>
                    </div>
                    <form method="POST" class="flex gap-1">
                        <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                        <button type="submit" name="delete_question" value="1" class="btn btn-sm btn-ghost" onclick="return confirmAction('Delete?')">Delete</button>
                    </form>
                </div>
                <?php if ($q['type'] === 'multiple_choice' && !empty($qOptions)): ?>
                <div class="mt-1 text-sm text-muted">Options: <?php echo implode(', ', array_map('sanitize', $qOptions)); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <h3 class="mt-3 mb-2">Add Question</h3>
            <form method="POST">
                <input type="hidden" name="add_question" value="1">
                
                <div class="form-group">
                    <label class="form-label">Question Type</label>
                    <select name="type" class="form-select" id="questionType">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="likert_5">Likert Scale (1-5)</option>
                        <option value="short_text">Short Text</option>
                        <option value="long_text">Long Text</option>
                        <option value="yes_no">Yes/No</option>
                        <option value="date">Date</option>
                        <option value="number">Number</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Question Text *</label>
                    <input type="text" name="question_text" class="form-input" placeholder="Enter question" required>
                </div>
                
                <div class="form-group" id="optionsContainer" style="display: none;">
                    <label class="form-label">Options</label>
                    <div id="optionFields">
                        <input type="text" name="options[]" class="form-input mb-1" placeholder="Option 1">
                        <input type="text" name="options[]" class="form-input mb-1" placeholder="Option 2">
                        <input type="text" name="options[]" class="form-input mb-1" placeholder="Option 3">
                        <input type="text" name="options[]" class="form-input mb-1" placeholder="Option 4">
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="addOption()">+ Add Option</button>
                </div>
                
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="required" value="1" class="form-check-input">
                        <span>Required</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Question</button>
            </form>
        </div>
        
        <script>
            var optCount = 4;
            document.getElementById('questionType').addEventListener('change', function() {
                document.getElementById('optionsContainer').style.display = this.value === 'multiple_choice' ? 'block' : 'none';
            });
            function addOption() {
                optCount++;
                var inp = document.createElement('input');
                inp.type = 'text';
                inp.name = 'options[]';
                inp.className = 'form-input mb-1';
                inp.placeholder = 'Option ' + optCount;
                document.getElementById('optionFields').appendChild(inp);
            }
        </script>
        <?php
        break;
        
    case 'view':
        ?>
        <?php if (isset($submitted)): ?>
        <div class="alert alert-success">Thank you for your response!</div>
        <?php endif; ?>
        
        <?php if (isset($viewError)): ?>
        <div class="alert alert-error"><?php echo sanitize($viewError); ?></div>
        <?php endif; ?>
        
        <?php if (empty($questions)): ?>
        <div class="alert alert-warning">This survey has no questions yet. Please contact the survey creator.</div>
        <?php endif; ?>
        
        <div class="card" style="max-width: 700px; margin: 0 auto;">
            <h1 class="card-title"><?php echo sanitize($survey['title']); ?></h1>
            <?php if ($survey['description']): ?>
            <p class="text-muted mb-3"><?php echo sanitize($survey['description']); ?></p>
            <?php endif; ?>
            
            <?php if ($survey['identity_mode'] === 'identified'): ?>
            <div class="card mb-3" style="background: #f0f7ff;">
                <h3 class="card-title">Your Information</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="respondent_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Student Number *</label>
                        <input type="text" name="student_number" class="form-input" required>
                    </div>
            <?php elseif ($survey['identity_mode'] === 'access_code'): ?>
            <div class="card mb-3" style="background: #f0f7ff;">
                <h3 class="card-title">Access Code Required</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Enter Access Code *</label>
                        <input type="text" name="access_code" class="form-input" placeholder="e.g., A1B2C3D4" required>
                    </div>
            <?php else: ?>
            <form method="POST">
            <?php endif; ?>
            
                <?php foreach ($questions as $index => $q): ?>
                <?php 
                $type = $q['type'];
                $rawOptions = $q['options'] ?? '';
                $options = is_array($rawOptions) ? $rawOptions : json_decode($rawOptions, true);
                if (!is_array($options)) $options = array();
                if ($type === 'multiple_choice' && empty($options)) {
                    $options = ['Option A', 'Option B', 'Option C', 'Option D', 'Option E'];
                }
                ?>
                <div class="form-group">
                    <label class="form-label">
                        <?php echo $index + 1; ?>. <?php echo sanitize($q['question_text']); ?>
                        <?php if ($q['required']): ?><span style="color: red;"> *</span><?php endif; ?>
                    </label>
                    
                    <?php if ($type === 'short_text'): ?>
                    <input type="text" name="question_<?php echo $q['id']; ?>" class="form-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                    
                    <?php elseif ($type === 'long_text'): ?>
                    <textarea name="question_<?php echo $q['id']; ?>" class="form-textarea" <?php echo $q['required'] ? 'required' : ''; ?>></textarea>
                    
                    <?php elseif ($type === 'multiple_choice'): ?>
                    <?php foreach ($options as $opt): ?>
                    <label class="form-check">
                        <input type="radio" name="question_<?php echo $q['id']; ?>" value="<?php echo sanitize($opt); ?>" class="form-check-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                        <span><?php echo sanitize($opt); ?></span>
                    </label>
                    <?php endforeach; ?>
                    
                    <?php elseif ($type === 'likert_5'): ?>
                    <div class="flex gap-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label class="form-check">
                            <input type="radio" name="question_<?php echo $q['id']; ?>" value="<?php echo $i; ?>" class="form-check-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                            <span><?php echo $i; ?></span>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <small class="text-muted">1 = Strongly Disagree, 2 = Disagree, 3 = Neutral, 4 = Agree, 5 = Strongly Agree</small>
                    
                    <?php elseif ($type === 'yes_no'): ?>
                    <label class="form-check">
                        <input type="radio" name="question_<?php echo $q['id']; ?>" value="Yes" class="form-check-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                        <span>Yes</span>
                    </label>
                    <label class="form-check">
                        <input type="radio" name="question_<?php echo $q['id']; ?>" value="No" class="form-check-input">
                        <span>No</span>
                    </label>
                    
                    <?php elseif ($type === 'number'): ?>
                    <input type="number" name="question_<?php echo $q['id']; ?>" class="form-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                    
                    <?php elseif ($type === 'date'): ?>
                    <input type="date" name="question_<?php echo $q['id']; ?>" class="form-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                    
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (!empty($questions)): ?>
                <button type="submit" class="btn btn-primary">Submit</button>
                <?php endif; ?>
            </form>
        </div>
        <?php
        break;
        
    case 'results':
        ?>
        <?php if (isset($error)): ?>
        <div class="card">
            <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <a href="index.php?page=surveys" class="btn btn-primary">Back to My Surveys</a>
        </div>
        <?php else: ?>
        
        <div class="page-header">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="page-title">Results: <?php echo sanitize($survey['title']); ?></h1>
                    <p class="page-subtitle"><?php echo $totalResponses; ?> responses</p>
                </div>
                <a href="index.php?page=results&id=<?php echo $surveyId; ?>&action=export" class="btn btn-primary">Export CSV</a>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalResponses; ?></div>
                <div class="stat-label">Total Responses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($questions); ?></div>
                <div class="stat-label">Questions</div>
            </div>
        </div>
        
        <?php
        function analyzeQuestion($questionId, $type, $responses, $responseHandler) {
            $allAnswers = array();
            foreach ($responses as $resp) {
                $answers = $responseHandler->getAnswers($resp['id']);
                if (isset($answers[$questionId])) {
                    $allAnswers[] = $answers[$questionId];
                }
            }
            
            $result = array('total' => count($allAnswers), 'data' => array());
            
            if ($type === 'multiple_choice') {
                $counts = array();
                foreach ($allAnswers as $ans) {
                    $val = is_array($ans) ? implode(', ', $ans) : $ans;
                    $counts[$val] = ($counts[$val] ?? 0) + 1;
                }
                arsort($counts);
                $total = count($allAnswers);
                foreach ($counts as $opt => $cnt) {
                    $result['data'][] = array('option' => $opt, 'count' => $cnt, 'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0);
                }
            } elseif ($type === 'likert_5') {
                $counts = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
                $sum = 0;
                $valid = 0;
                foreach ($allAnswers as $ans) {
                    $val = is_numeric($ans) ? (int)$ans : 0;
                    if ($val >= 1 && $val <= 5) {
                        $counts[$val]++;
                        $sum += $val;
                        $valid++;
                    }
                }
                $result['data'] = array('counts' => $counts, 'average' => $valid > 0 ? round($sum / $valid, 2) : 0, 'total' => $valid);
            } elseif ($type === 'yes_no') {
                $yes = count(array_filter($allAnswers, fn($a) => strtolower($a) === 'yes'));
                $result['data'] = array('yes' => $yes, 'no' => count($allAnswers) - $yes);
            } elseif (in_array($type, ['short_text', 'long_text'])) {
                $result['data'] = array('responses' => array_filter($allAnswers, fn($a) => !empty(trim($a))));
            } elseif ($type === 'number') {
                $nums = array_filter($allAnswers, 'is_numeric');
                if (!empty($nums)) {
                    $result['data'] = array('average' => round(array_sum($nums) / count($nums), 2), 'min' => min($nums), 'max' => max($nums), 'count' => count($nums));
                }
            }
            
            return $result;
        }
        
        foreach ($questions as $index => $q):
            $analysis = analyzeQuestion($q['id'], $q['type'], $responses, $responseHandler);
        ?>
        <div class="card mb-3">
            <h3 class="card-title">
                <?php echo ($index + 1) . '. ' . sanitize($q['question_text']); ?>
                <span class="badge badge-warning ml-1"><?php echo ucfirst(str_replace('_', ' ', $q['type'])); ?></span>
            </h3>
            <p class="text-muted"><?php echo $analysis['total']; ?> responses</p>
            
            <?php if ($q['type'] === 'multiple_choice'): ?>
            <div style="max-width: 400px; margin: 1rem 0;">
                <canvas id="chart_<?php echo $q['id']; ?>"></canvas>
            </div>
            <table class="table">
                <thead><tr><th>Option</th><th>Count</th><th>%</th></tr></thead>
                <tbody>
                    <?php foreach ($analysis['data'] as $r): ?>
                    <tr><td><?php echo sanitize($r['option']); ?></td><td><?php echo $r['count']; ?></td><td><?php echo $r['percentage']; ?>%</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            new Chart(document.getElementById('chart_<?php echo $q['id']; ?>'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($analysis['data'], 'option')); ?>,
                    datasets: [{
                        label: 'Responses',
                        data: <?php echo json_encode(array_column($analysis['data'], 'count')); ?>,
                        backgroundColor: '#2563EB',
                        borderRadius: 4
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
            </script>
            
            <?php elseif ($q['type'] === 'likert_5'): ?>
            <?php $lr = $analysis['data']; ?>
            <div class="stats-grid mb-2">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $lr['average']; ?></div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>
            <div style="max-width: 300px; margin: 1rem 0;">
                <canvas id="chart_<?php echo $q['id']; ?>"></canvas>
            </div>
            <table class="table">
                <thead><tr><th>Scale</th><th>Count</th></tr></thead>
                <tbody>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php $labels = ['', 'Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree']; ?>
                    <tr><td><?php echo $i . ' - ' . $labels[$i]; ?></td><td><?php echo $lr['counts'][$i] ?? 0; ?></td></tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <script>
            new Chart(document.getElementById('chart_<?php echo $q['id']; ?>'), {
                type: 'pie',
                data: {
                    labels: ['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree'],
                    datasets: [{
                        data: <?php echo json_encode(array_values($lr['counts'])); ?>,
                        backgroundColor: ['#EF4444', '#F97316', '#F59E0B', '#10B981', '#22C55E']
                    }]
                },
                options: { responsive: true }
            });
            </script>
            
            <?php elseif ($q['type'] === 'yes_no'): ?>
            <?php $yn = $analysis['data']; ?>
            <div class="flex gap-2">
                <div class="stat-card"><div class="stat-value"><?php echo $yn['yes']; ?></div><div class="stat-label">Yes</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $yn['no']; ?></div><div class="stat-label">No</div></div>
            </div>
            
            <?php elseif ($q['type'] === 'number' && isset($analysis['data']['average'])): ?>
            <?php $n = $analysis['data']; ?>
            <div class="flex gap-2">
                <div class="stat-card"><div class="stat-value"><?php echo $n['average']; ?></div><div class="stat-label">Average</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $n['min']; ?></div><div class="stat-label">Min</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $n['max']; ?></div><div class="stat-label">Max</div></div>
            </div>
            
            <?php elseif (in_array($q['type'], ['short_text', 'long_text'])): ?>
            <?php $txt = $analysis['data']['responses'] ?? array(); ?>
            <?php if (empty($txt)): ?>
            <p class="text-muted">No text responses.</p>
            <?php else: ?>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 1rem; border-radius: 4px;">
                <?php foreach ($txt as $t): ?>
                <div class="mb-2 pb-2" style="border-bottom: 1px solid #eee;"><?php echo sanitize($t); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
        <?php
        break;
        
    default:
        ?>
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome, <?php echo sanitize(getCurrentUser()['username']); ?>!</p>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?>">
            <?php echo $_SESSION['message']['text']; ?>
        </div>
        <?php unset($_SESSION['message']); endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalSurveys; ?></div>
                <div class="stat-label">Total Surveys</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalResponses; ?></div>
                <div class="stat-label">Total Responses</div>
            </div>
        </div>
        
        <div class="card">
            <div class="flex justify-between items-center mb-3">
                <h2 class="card-title">Recent Surveys</h2>
                <a href="index.php?page=create" class="btn btn-primary btn-sm">Create New</a>
            </div>
            
            <?php if (empty($surveys)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No surveys yet</div>
                <p>Create your first survey</p>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Responses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($surveys, 0, 5) as $s): ?>
                    <?php $stats = $questionnaire->getStats($s['id']); ?>
                    <tr>
                        <td><a href="index.php?page=edit&id=<?php echo $s['id']; ?>"><?php echo sanitize($s['title']); ?></a></td>
                        <td>
                            <?php if ($s['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                            <?php else: ?>
                            <span class="badge badge-warning">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $stats['total_responses']; ?></td>
                        <td>
                            <div class="flex gap-1">
                                <a href="index.php?page=view&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">View</a>
                                <a href="index.php?page=results&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">Results</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
        break;
}

include TEMPLATES_DIR . '/footer.php';
