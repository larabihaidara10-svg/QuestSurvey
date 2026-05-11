<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once INCLUDES_DIR . '/database.php';

$page = $_GET['page'] ?? '';

$db = Database::getInstance();

require_once INCLUDES_DIR . '/auth.php';
require_once INCLUDES_DIR . '/questionnaire.php';
require_once INCLUDES_DIR . '/response.php';
require_once INCLUDES_DIR . '/analysis.php';

$auth = new Auth();
$questionnaire = new QuestionnaireModel();
$response = new ResponseModel();
$analysis = new AnalysisModel();
$currentUser = getCurrentUser();

// Public pages (no login needed)
if ($page === 'login') {
    if (isLoggedIn()) redirect('index.php');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['admin_key'] ?? '');
        if ($result['success']) redirect('index.php');
        else $error = $result['error'];
    }
    $pageTitle = 'Login';
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="card" style="max-width: 400px; margin: 3rem auto;">
        <h1 class="card-title text-center">System Login</h1>
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
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <p class="text-center mt-2 text-sm text-muted">Take a survey? <a href="index.php?page=public">Browse surveys</a></p>
    </div>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'logout') {
    $auth->logout();
    redirect('index.php');
    exit;
}

// Public survey listing (no login needed)
if ($page === 'public') {
    $search = $_GET['q'] ?? '';
    $filterCategory = $_GET['cat'] ?? '';
    $allSurveys = $questionnaire->getAllPublic();
    $publicSurveys = [];
    $now = date('Y-m-d H:i:s');

    // Extract unique categories
    $categories = [];
    foreach ($allSurveys as $s) {
        $cat = $s['category'] ?? 'General';
        if (!in_array($cat, $categories)) $categories[] = $cat;
    }

    foreach ($allSurveys as $s) {
        $active = true;
        if (!empty($search)) {
            if (stripos($s['title'], $search) === false && stripos($s['category'], $search) === false && stripos($s['description'] ?? '', $search) === false) {
                $active = false;
            }
        }
        if (!empty($filterCategory) && ($s['category'] ?? 'General') !== $filterCategory) {
            $active = false;
        }
        if ($active && !empty($s['start_date']) && $now < $s['start_date']) $active = false;
        if ($active && !empty($s['end_date']) && $now > $s['end_date']) $active = false;
        if ($active) $publicSurveys[] = $s;
    }
    $pageTitle = 'Available Surveys';
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">Available Surveys</h1>
        <p class="page-subtitle">Browse and participate in public surveys</p>
    </div>
    <form method="GET" action="index.php" class="mb-3" style="max-width:500px;">
        <input type="hidden" name="page" value="public">
        <div class="form-group" style="display:flex;gap:.5rem;">
            <input type="text" name="q" class="form-input" placeholder="Search surveys by name, category, or description..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search) || !empty($filterCategory)): ?>
            <a href="index.php?page=public" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if (!empty($categories)): ?>
    <div class="mb-3">
        <span class="text-sm text-muted" style="margin-right:.5rem;">Filter by:</span>
        <a href="index.php?page=public<?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>" class="btn btn-sm <?php echo empty($filterCategory) ? 'btn-primary' : 'btn-ghost'; ?>">All</a>
        <?php foreach ($categories as $cat): ?>
        <a href="index.php?page=public&cat=<?php echo urlencode($cat); ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?>" class="btn btn-sm <?php echo $filterCategory === $cat ? 'btn-primary' : 'btn-ghost'; ?>"><?php echo sanitize($cat); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (empty($publicSurveys)): ?>
    <div class="empty-state">
        <div class="empty-state-title">No surveys available right now</div>
        <p class="text-muted">Check back later or contact the administrator</p>
    </div>
    <?php else: ?>
    <div class="grid gap-3">
        <?php foreach ($publicSurveys as $s): ?>
        <div class="card flex justify-between items-start">
            <div>
                <h3 class="card-title"><?php echo sanitize($s['title']); ?></h3>
                <p><?php echo sanitize($s['description'] ?? ''); ?></p>
                <p class="text-sm text-muted">
                    Category: <?php echo sanitize($s['category'] ?? 'General'); ?> |
                    Mode: <?php echo IDENTITY_MODES[$s['identity_mode']] ?? ''; ?>
                </p>
            </div>
            <a href="index.php?page=view&id=<?php echo $s['id']; ?>" class="btn btn-primary">Take Survey</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    // Show "Coming Soon" section for pending surveys
    $pendingSurveys = [];
    foreach ($allSurveys as $s) {
        if (!empty($s['start_date']) && $now < $s['start_date']) {
            $matches = true;
            if (!empty($search)) {
                if (stripos($s['title'], $search) === false && stripos($s['category'], $search) === false && stripos($s['description'] ?? '', $search) === false) {
                    $matches = false;
                }
            }
            if ($matches) $pendingSurveys[] = $s;
        }
    }
    ?>
    <?php if (!empty($pendingSurveys)): ?>
    <div class="mt-3">
        <h3 class="card-title" style="border-bottom: 2px solid #e5e7eb; padding-bottom: .5rem;">
            🕐 Coming Soon — <?php echo count($pendingSurveys); ?> survey<?php echo count($pendingSurveys) > 1 ? 's' : ''; ?>
        </h3>
        <p class="text-muted text-sm mb-2">These surveys are not yet available. Check back at the start date.</p>
        <div class="grid gap-2">
            <?php foreach ($pendingSurveys as $s): ?>
            <div class="card" style="opacity: 0.7; background: #f9fafb;">
                <h4 class="card-title" style="margin:0 0 .25rem; font-size:1rem;"><?php echo sanitize($s['title']); ?></h4>
                <p class="text-sm text-muted" style="margin:0;">
                    Opens: <strong><?php echo date('M d, Y \a\t h:i A', strtotime($s['start_date'])); ?></strong>
                    <?php if (!empty($s['end_date'])): ?>
                    &nbsp;|&nbsp; Closes: <strong><?php echo date('M d, Y \a\t h:i A', strtotime($s['end_date'])); ?></strong>
                    <?php endif; ?>
                </p>
                <?php if (!empty($s['category'])): ?>
                <p class="text-sm text-muted" style="margin:0;">Category: <?php echo sanitize($s['category']); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

// View survey (public - no login needed)
if ($page === 'view') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey) die('Survey not found');
    $questions = $questionnaire->getQuestions($surveyId);

    $viewError = null;
    $accessCodeValid = false;
    $now = date('Y-m-d H:i:s');

    if (!empty($survey['start_date']) && $now < $survey['start_date']) {
        $viewError = 'This survey has not started yet';
    } elseif (!empty($survey['end_date']) && $now > $survey['end_date']) {
        $viewError = 'This survey has expired';
    } elseif (!$survey['is_active']) {
        $viewError = 'This survey is not active';
    }

    // Handle access code validation
    if ($survey['identity_mode'] === 'access_code') {
        if (isset($_POST['access_code']) && empty($viewError)) {
            $codeData = $db->validateAccessCode($surveyId, $_POST['access_code']);
            if ($codeData) {
                $accessCodeValid = true;
                $_SESSION['access_code_valid_' . $surveyId] = $_POST['access_code'];
            } else {
                unset($_SESSION['access_code_valid_' . $surveyId]);
                $viewError = 'Invalid or already used access code';
            }
        } elseif (isset($_SESSION['access_code_valid_' . $surveyId])) {
            $accessCodeValid = true;
        }
    }

    // Handle identity step for 'identified' mode
    $identifySubmitted = false;
    if ($survey['identity_mode'] === 'identified') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identify_step'])) {
            $respondentName = trim($_POST['respondent_name'] ?? '');
            $surname = trim($_POST['surname'] ?? '');
            $studentNumber = trim($_POST['student_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $requireEmail = !empty($survey['collect_email']);
            $requireStudentNumber = !empty($survey['collect_student_number']);

            $idError = '';
            if (empty($respondentName) || empty($surname)) {
                $idError = 'Name and Surname are required.';
            } elseif ($requireEmail && empty($email)) {
                $idError = 'Email is required.';
            } elseif ($requireStudentNumber && empty($studentNumber)) {
                $idError = 'Student Number is required.';
            }

            if (empty($idError)) {
                $_SESSION['survey_identity'] = [
                    'respondent_name' => $respondentName,
                    'surname' => $surname,
                    'student_number' => $studentNumber,
                    'email' => $email
                ];
                $identifySubmitted = true;
            } else {
                $viewError = $idError;
            }
        } elseif (isset($_SESSION['survey_identity'])) {
            $identifySubmitted = true;
        }
    } else {
        $identifySubmitted = true;
    }

    // Handle survey response submission
    $submitted = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['access_code']) && empty($_POST['identify_step'])) {
        $respondentName = null;
        $studentNumber = null;
        $email = null;
        $accessCodeId = null;

        $isSubmission = false;
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $isSubmission = true;
                break;
            }
        }

        if ($isSubmission && $identifySubmitted) {
            if ($survey['identity_mode'] === 'identified') {
                $ident = $_SESSION['survey_identity'] ?? [];
                $firstName = $ident['respondent_name'] ?? '';
                $surname = $ident['surname'] ?? '';
                $respondentName = ($firstName && $surname) ? $firstName . ' ' . $surname : ($surname ?: $firstName);
                $studentNumber = $ident['student_number'] ?? null;
                $email = $ident['email'] ?? null;
                unset($_SESSION['survey_identity']);
            } elseif ($survey['identity_mode'] === 'access_code' && $accessCodeValid) {
                $codeData = $db->validateAccessCode($surveyId, $_SESSION['access_code_valid_' . $surveyId] ?? '');
                if ($codeData) {
                    $accessCodeId = $codeData['id'];
                }
            }

            if (!$survey['allow_multiple'] && $accessCodeId) {
                $existing = $db->queryResponse("SELECT id FROM responses WHERE access_code_id = ?", [$accessCodeId]);
                if (!empty($existing)) {
                    $viewError = 'This access code has already been used';
                }
            }

            if (!$viewError) {
                $responseId = $response->createResponse($surveyId, $respondentName, $studentNumber, $email, $accessCodeId);

                if ($accessCodeId) {
                    $db->markAccessCodeUsed($accessCodeId);
                    unset($_SESSION['access_code_valid_' . $surveyId]);
                }

                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'question_') === 0) {
                        $qid = (int)str_replace('question_', '', $key);
                        $response->saveAnswer($responseId, $qid, $value);
                    }
                }
                $submitted = true;
            }
        } elseif (!$identifySubmitted) {
            // Identity not yet provided, show the form
        }
    }

    $pageTitle = sanitize($survey['title'] ?? 'Survey');
    include TEMPLATES_DIR . '/header.php';
    ?>
    <?php if ($submitted): ?>
    <div class="alert alert-success" style="max-width:700px;margin:3rem auto;">
        <h3 style="margin:0 0 .5rem;">Thank you!</h3>
        <p>Your response has been recorded successfully.</p>
        <a href="index.php?page=public" class="btn btn-primary mt-2" style="display:inline-block;">Browse More Surveys</a>
    </div>
    <?php elseif ($viewError): ?>
    <div class="alert alert-error" style="max-width:700px;margin:3rem auto;">
        <?php echo sanitize($viewError); ?>
    </div>
    <?php else: ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h1 class="card-title" style="font-size:1.5rem;"><?php echo sanitize($survey['title']); ?></h1>
        <?php if ($survey['description']): ?>
        <p class="text-muted"><?php echo sanitize($survey['description']); ?></p>
        <?php endif; ?>

        <?php if ($survey['identity_mode'] === 'access_code' && !$accessCodeValid): ?>
        <p>Enter your access code to participate in this survey:</p>
        <form method="POST" style="max-width:400px;">
            <div class="form-group">
                <label class="form-label">Access Code *</label>
                <input type="text" name="access_code" class="form-input" placeholder="e.g., A1B2C3D4" required style="text-transform:uppercase;">
            </div>
            <button type="submit" class="btn btn-primary">Validate Code</button>
        </form>
        <?php elseif ($survey['identity_mode'] === 'identified' && !$identifySubmitted): ?>
        <p class="text-muted mb-2">Please provide your information to participate:</p>
        <form method="POST" class="card" style="padding:1.5rem;">
            <input type="hidden" name="identify_step" value="1">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="respondent_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Surname *</label>
                <input type="text" name="surname" class="form-input" required>
            </div>
            <?php if (!empty($survey['collect_email'])): ?>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-input" required>
            </div>
            <?php endif; ?>
            <?php if (!empty($survey['collect_student_number'])): ?>
            <div class="form-group">
                <label class="form-label">Student Number *</label>
                <input type="text" name="student_number" class="form-input" required>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Start Survey</button>
        </form>
        <?php else: ?>
        <form method="POST">
            <?php foreach ($questions as $q): ?>
            <?php
            $type = $q['type'];
            $rawOptions = $q['options'] ?? '';
            $options = is_array($rawOptions) ? $rawOptions : json_decode($rawOptions, true);
            if (!is_array($options)) $options = [];
            if ($type === 'multiple_choice' && empty($options)) {
                $options = ['A', 'B', 'C', 'D', 'E'];
            }
            ?>
            <div class="form-group">
                <label class="form-label">
                    <?php echo sanitize($q['question_text']); ?>
                    <?php if ($q['required']): ?><span style="color: red;"> *</span><?php endif; ?>
                </label>
                <?php if ($type === 'multiple_choice'): ?>
                    <?php foreach ($options as $opt): ?>
                    <label class="form-check"><input type="radio" name="question_<?php echo $q['id']; ?>" value="<?php echo sanitize($opt); ?>" class="form-check-input" <?php echo $q['required'] ? 'required' : ''; ?>> <span><?php echo sanitize($opt); ?></span></label>
                    <?php endforeach; ?>
                <?php elseif ($type === 'likert_5'): ?>
                    <div class="flex gap-2" style="flex-wrap:wrap;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label class="form-check"><input type="radio" name="question_<?php echo $q['id']; ?>" value="<?php echo $i; ?>" class="form-check-input" <?php echo $q['required'] ? 'required' : ''; ?>> <span><?php echo $i; ?></span></label>
                        <?php endfor; ?>
                    </div>
                <?php elseif ($type === 'short_text'): ?>
                    <input type="text" name="question_<?php echo $q['id']; ?>" class="form-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                <?php elseif ($type === 'long_text'): ?>
                    <textarea name="question_<?php echo $q['id']; ?>" class="form-textarea" <?php echo $q['required'] ? 'required' : ''; ?>></textarea>
                <?php elseif ($type === 'yes_no'): ?>
                    <label class="form-check"><input type="radio" name="question_<?php echo $q['id']; ?>" value="Yes" class="form-check-input"> <span>Yes</span></label>
                    <label class="form-check"><input type="radio" name="question_<?php echo $q['id']; ?>" value="No" class="form-check-input"> <span>No</span></label>
                <?php elseif ($type === 'true_false'): ?>
                    <label class="form-check"><input type="radio" name="question_<?php echo $q['id']; ?>" value="True" class="form-check-input"> <span>True</span></label>
                    <label class="form-check"><input type="radio" name="question_<?php echo $q['id']; ?>" value="False" class="form-check-input"> <span>False</span></label>
                <?php elseif ($type === 'number'): ?>
                    <input type="number" name="question_<?php echo $q['id']; ?>" class="form-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                <?php elseif ($type === 'date'): ?>
                    <input type="date" name="question_<?php echo $q['id']; ?>" class="form-input" <?php echo $q['required'] ? 'required' : ''; ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($questions)): ?>
            <button type="submit" class="btn btn-primary">Submit Survey</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

// ===== Protected pages (login required) =====

if ($page === '' || $page === 'dashboard') {
    if (!isLoggedIn()) {
        redirect('index.php?page=public');
    }
    $currentUser = getCurrentUser();
    $surveys = $questionnaire->getAll($currentUser['id']);
    $totalResponses = 0;
    foreach ($surveys as $s) {
        $stats = $questionnaire->getStats($s['id']);
        $totalResponses += $stats['total_responses'];
    }
    $pageTitle = 'Dashboard';
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo sanitize($currentUser['first_name'] ?? $currentUser['username']); ?></p>
    </div>
    <div class="flex gap-2 mb-3">
        <div class="card" style="flex: 1;">
            <h3 class="card-title">My Surveys</h3>
            <p style="font-size: 2rem; font-weight: 600;"><?php echo count($surveys); ?></p>
            <a href="index.php?page=surveys" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card" style="flex: 1;">
            <h3 class="card-title">Total Responses</h3>
            <p style="font-size: 2rem; font-weight: 600;"><?php echo $totalResponses; ?></p>
        </div>
    </div>
    <a href="index.php?page=create" class="btn btn-primary">Create New Survey</a>
    <a href="index.php?page=public" class="btn btn-ghost mt-2" style="display:inline-block;">Browse Public Surveys</a>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

// All remaining pages require login
if (!isLoggedIn()) {
    redirect('index.php?page=login');
}

if ($page === 'surveys') {
    $surveys = $questionnaire->getAll($currentUser['id']);
    $pageTitle = 'My Surveys';
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header flex justify-between items-center">
        <div>
            <h1 class="page-title">My Surveys</h1>
            <p class="page-subtitle">Manage your questionnaires</p>
        </div>
        <a href="index.php?page=create" class="btn btn-primary">Create Survey</a>
    </div>
    <?php if (empty($surveys)): ?>
    <div class="empty-state">
        <div class="empty-state-title">No surveys yet</div>
        <a href="index.php?page=create" class="btn btn-primary mt-2">Create Your First Survey</a>
    </div>
    <?php else: ?>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Mode</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($surveys as $s): ?>
                <?php $status = getQuestionnaireStatus($s); ?>
                <tr>
                    <td><a href="index.php?page=edit&id=<?php echo $s['id']; ?>"><?php echo sanitize($s['title']); ?></a></td>
                    <td><?php echo sanitize($s['category'] ?? 'General'); ?></td>
                    <td><span class="badge badge-primary"><?php echo IDENTITY_MODES[$s['identity_mode']] ?? 'Anonymous'; ?></span></td>
                    <td><?php echo $s['questions_count']; ?></td>
                    <td><span class="badge badge-<?php echo $status === 'active' ? 'success' : ($status === 'expired' ? 'warning' : 'primary'); ?>"><?php echo ucfirst($status); ?></span></td>
                    <td>
                        <a href="index.php?page=view&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost" target="_blank">Preview</a>
                        <a href="index.php?page=results&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">Results</a>
                        <?php if ($s['identity_mode'] === 'access_code'): ?>
                        <a href="index.php?page=download_codes&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">Codes</a>
                        <?php endif; ?>
                        <a href="index.php?page=copy&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost" onclick="return confirmAction('Copy this survey? Questions will be duplicated.')">Copy</a>
                        <a href="index.php?page=delete_survey&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost" onclick="return confirmAction('Delete this survey and all its questions?')">Delete</a>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <a href="index.php?page=transfer&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-ghost">Transfer</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'create') {
    $pageTitle = 'Create Survey';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
        $startRaw = trim($_POST['start_date'] ?? '');
        $endRaw = trim($_POST['end_date'] ?? '');
        // HTML datetime-local uses 'T' separator, normalize to space + ensure seconds
        $startDate = $startRaw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $startRaw))) : null;
        $endDate = $endRaw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $endRaw))) : null;
        $idExtraField = $_POST['id_extra_field'] ?? 'none';
        $data = [
            'title' => $_POST['title'],
            'description' => $_POST['description'] ?? '',
            'category' => $_POST['category'] ?? '',
            'identity_mode' => $_POST['identity_mode'] ?? 'anonymous',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'allow_multiple' => isset($_POST['allow_multiple']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'access_codes_count' => (int)($_POST['access_codes_count'] ?? 0),
            'collect_email' => $idExtraField === 'email' ? 1 : 0,
            'collect_student_number' => $idExtraField === 'student_number' ? 1 : 0
        ];
        $surveyId = $questionnaire->create($currentUser['id'], $data);
        redirect('index.php?page=edit&id=' . $surveyId);
    }
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">Create New Survey</h1>
    </div>
    <div class="card">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Survey Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g., Course Evaluation 2026" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" placeholder="Optional description of the survey"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-input" placeholder="e.g., Course Evaluation, Feedback">
            </div>
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" checked class="form-check-input">
                    <span>Active (available immediately)</span>
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Identity Mode *</label>
                <select name="identity_mode" class="form-select" id="identityModeSelect">
                    <option value="anonymous">Anonymous - Open to everyone, no identity checked</option>
                    <option value="identified">Identified - Collect name, surname, and optionally email/student number</option>
                    <option value="access_code">Access Code - Distribute one-time passwords to participants</option>
                </select>
            </div>
            <div id="identifiedOptions" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Collect Optional Field</label>
                    <div class="radio-group">
                        <label class="form-check"><input type="radio" name="id_extra_field" value="none" class="form-check-input" checked> <span>None</span></label>
                        <label class="form-check"><input type="radio" name="id_extra_field" value="email" class="form-check-input"> <span>Email address</span></label>
                        <label class="form-check"><input type="radio" name="id_extra_field" value="student_number" class="form-check-input"> <span>Student number</span></label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Number of Access Codes</label>
                <input type="number" name="access_codes_count" class="form-input" value="0" min="0" max="10000">
                <small class="text-muted">Set to 0 if no codes needed. Codes can be downloaded later.</small>
            </div>
            <button type="submit" class="btn btn-primary">Create Survey</button>
        </form>
    </div>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'transfer') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $currentUser['role'] !== 'admin') {
        setMessage('error', 'Survey not found or access denied');
        redirect('index.php?page=surveys');
    }
    $creators = $auth->getAllUsers();
    $creators = array_filter($creators, fn($u) => $u['role'] === 'creator');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newOwnerId = (int)($_POST['new_owner'] ?? 0);
        // Verify the new owner is a creator
        $valid = false;
        foreach ($creators as $c) {
            if ($c['id'] === $newOwnerId) { $valid = true; break; }
        }
        if ($valid) {
            $questionnaire->transferOwnership($surveyId, $newOwnerId);
            setMessage('success', 'Survey ownership transferred');
            redirect('index.php?page=surveys');
        } else {
            setMessage('error', 'Invalid creator selected');
        }
    }

    $pageTitle = 'Transfer Survey';
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">Transfer: <?php echo sanitize($survey['title']); ?></h1>
    </div>
    <div class="card" style="max-width:500px;">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Select New Owner (Creator) *</label>
                <select name="new_owner" class="form-select" required>
                    <option value="">-- Select Creator --</option>
                    <?php foreach ($creators as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?> (@<?php echo sanitize($c['username']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Transfer Survey</button>
            <a href="index.php?page=surveys" class="btn btn-ghost">Cancel</a>
        </form>
    </div>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'edit') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $survey['user_id'] != $currentUser['id']) {
        setMessage('error', 'Survey not found or access denied');
        redirect('index.php?page=surveys');
    }
    $questions = $questionnaire->getQuestions($surveyId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_survey'])) {
            $startRaw = trim($_POST['start_date'] ?? '');
            $endRaw = trim($_POST['end_date'] ?? '');
            $_POST['start_date'] = $startRaw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $startRaw))) : null;
            $_POST['end_date'] = $endRaw ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $endRaw))) : null;
            $idExtraField = $_POST['id_extra_field'] ?? 'none';
            $_POST['collect_email'] = $idExtraField === 'email' ? 1 : 0;
            $_POST['collect_student_number'] = $idExtraField === 'student_number' ? 1 : 0;
            $questionnaire->update($surveyId, $_POST);
            setMessage('success', 'Survey updated');
        } elseif (isset($_POST['add_question'])) {
            $options = [];
            if (isset($_POST['options']) && is_array($_POST['options'])) {
                $options = array_values(array_filter($_POST['options'], function($o) { return !empty(trim($o)); }));
            }
            $questionnaire->addQuestion($surveyId, [
                'type' => $_POST['type'],
                'question_text' => $_POST['question_text'],
                'options' => $options,
                'required' => isset($_POST['required']) ? 1 : 0
            ]);
            setMessage('success', 'Question added');
        } elseif (isset($_POST['delete_question'])) {
            $questionnaire->deleteQuestion($_POST['question_id']);
            setMessage('success', 'Question deleted');
        }
        redirect('index.php?page=edit&id=' . $surveyId);
    }

    $pageTitle = 'Edit: ' . $survey['title'];
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title"><?php echo sanitize($survey['title']); ?></h1>
        <p class="page-subtitle">ID: <?php echo $survey['id']; ?> | Mode: <?php echo IDENTITY_MODES[$survey['identity_mode']] ?? ''; ?></p>
    </div>
    <div class="card mb-3">
        <h2 class="card-title">Survey Settings</h2>
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
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-input" value="<?php echo sanitize($survey['category'] ?? ''); ?>" placeholder="e.g., Course Evaluation">
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="datetime-local" name="start_date" class="form-input" value="<?php echo $survey['start_date'] ? str_replace(' ', 'T', htmlspecialchars($survey['start_date'])) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="datetime-local" name="end_date" class="form-input" value="<?php echo $survey['end_date'] ? str_replace(' ', 'T', htmlspecialchars($survey['end_date'])) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Identity Mode *</label>
                <select name="identity_mode" class="form-select" id="identityModeSelect">
                    <option value="anonymous" <?php echo ($survey['identity_mode'] ?? '') === 'anonymous' ? 'selected' : ''; ?>>Anonymous - Open to everyone</option>
                    <option value="identified" <?php echo ($survey['identity_mode'] ?? '') === 'identified' ? 'selected' : ''; ?>>Identified - Name, Surname, Email/Student Number</option>
                    <option value="access_code" <?php echo ($survey['identity_mode'] ?? '') === 'access_code' ? 'selected' : ''; ?>>Access Code - One-time passwords</option>
                </select>
            </div>
            <div id="identifiedOptionsEdit"<?php echo ($survey['identity_mode'] ?? '') === 'identified' ? '' : ' style="display:none;"'; ?>>
                <div class="form-group">
                    <label class="form-label">Collect Optional Field</label>
                    <?php
                    $currentExtraField = (!empty($survey['collect_email']) ? 'email' : (!empty($survey['collect_student_number']) ? 'student_number' : 'none'));
                    ?>
                    <div class="radio-group">
                        <label class="form-check"><input type="radio" name="id_extra_field" value="none" class="form-check-input" <?php echo $currentExtraField === 'none' ? 'checked' : ''; ?>> <span>None</span></label>
                        <label class="form-check"><input type="radio" name="id_extra_field" value="email" class="form-check-input" <?php echo $currentExtraField === 'email' ? 'checked' : ''; ?>> <span>Email address</span></label>
                        <label class="form-check"><input type="radio" name="id_extra_field" value="student_number" class="form-check-input" <?php echo $currentExtraField === 'student_number' ? 'checked' : ''; ?>> <span>Student number</span></label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Number of Access Codes</label>
                <input type="number" name="access_codes_count" class="form-input" value="0" min="0" max="10000">
                <small class="text-muted">Set to 0 if no codes needed. Only used when Identity Mode is Access Code.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
        </form>
    </div>
    <div class="card">
        <h2 class="card-title">Questions (<?php echo count($questions); ?>)</h2>
        <?php if (!empty($questions)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Question</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $i => $q): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo sanitize(substr($q['question_text'], 0, 60)) . (strlen($q['question_text']) > 60 ? '...' : ''); ?></td>
                    <td><span class="badge badge-primary"><?php echo ucfirst($q['type']); ?></span></td>
                    <td><?php echo $q['required'] ? '<span class="badge badge-warning">Yes</span>' : 'No'; ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                            <button type="submit" name="delete_question" class="btn btn-sm btn-ghost" onclick="return confirmAction('Delete this question?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No questions yet.</p>
        <?php endif; ?>

        <h3 class="mt-3">Add Question</h3>
        <form method="POST" class="card" style="padding:1rem;">
            <input type="hidden" name="add_question" value="1">
            <div class="form-group">
                <select name="type" class="form-select">
                    <option value="multiple_choice">Multiple Choice (A,B,C,D,E)</option>
                    <option value="likert_5">Likert Scale (1-5)</option>
                    <option value="short_text">Short Text</option>
                    <option value="long_text">Long Text</option>
                    <option value="yes_no">Yes / No</option>
                    <option value="true_false">True / False</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                </select>
            </div>
            <div class="form-group">
                <input type="text" name="question_text" class="form-input" placeholder="Enter your question..." required>
            </div>
            <div class="form-check">
                <input type="checkbox" name="required" value="1" class="form-check-input">
                <span>Required question</span>
            </div>
            <div style="margin-top:10px">
                <strong>Options</strong> (for Multiple Choice - leave blank for default A,B,C,D,E):
                <div id="options-container">
                    <div class="form-group" style="display:flex;gap:.5rem;">
                        <input type="text" name="options[]" class="form-input" placeholder="Option A">
                        <input type="text" name="options[]" class="form-input" placeholder="Option B">
                        <input type="text" name="options[]" class="form-input" placeholder="Option C">
                        <input type="text" name="options[]" class="form-input" placeholder="Option D">
                        <input type="text" name="options[]" class="form-input" placeholder="Option E">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-2">Add Question</button>
        </form>
    </div>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'results') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $survey['user_id'] != $currentUser['id']) {
        setMessage('error', 'Access denied');
        redirect('index.php?page=surveys');
    }
    $questions = $questionnaire->getQuestions($surveyId);
    $responses = $response->getResponsesWithAnswers($surveyId);
    $analysisData = $analysis->analyzeQuestionnaire($surveyId);
    $pageTitle = 'Results: ' . $survey['title'];
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header flex justify-between items-center">
        <div>
            <h1 class="page-title">Results: <?php echo sanitize($survey['title']); ?></h1>
            <p>Total Responses: <?php echo count($responses); ?></p>
        </div>
        <a href="index.php?page=export_csv&id=<?php echo $surveyId; ?>" class="btn btn-primary">Export CSV</a>
    </div>
    <div class="card">
        <h2 class="card-title">Question Analysis</h2>
        <?php if (empty($analysisData['questions'])): ?>
        <p class="text-muted">No questions or responses yet.</p>
        <?php endif; ?>
        <?php foreach ($analysisData['questions'] as $q): ?>
        <div class="mb-3" style="padding:1rem;border:1px solid #e5e7eb;border-radius:.5rem;">
            <h4><?php echo sanitize($q['text']); ?> <span class="badge badge-primary"><?php echo ucfirst($q['type']); ?></span></h4>
            <?php if (!empty($q['counts'])): ?>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($q['counts'] as $opt => $cnt): ?>
                <li style="padding:.25rem 0;"><?php echo sanitize($opt); ?>: <?php echo $cnt; ?> (<?php echo $q['percentages'][$opt] ?? 0; ?>%)</li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (isset($q['average'])): ?>
            <p style="margin:.5rem 0;">Average: <?php echo round($q['average'], 2); ?></p>
            <?php endif; ?>
            <?php if (!empty($q['text_responses'])): ?>
            <details style="margin-top:.5rem;"><summary>View Text Responses (<?php echo count($q['text_responses']); ?>)</summary>
                <ul style="max-height:200px;overflow-y:auto;">
                    <?php foreach ($q['text_responses'] as $t): ?>
                    <li style="padding:.25rem 0;border-bottom:1px solid #eee;"><?php echo sanitize($t); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
            <?php if (!empty($q['date_responses'])): ?>
            <ul style="list-style:none;padding:0;">
                <?php foreach ($q['date_responses'] as $d): ?>
                <li style="padding:.25rem 0;"><?php echo sanitize($d); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'export_csv') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $survey['user_id'] != $currentUser['id']) {
        die('Access denied');
    }
    $questions = $questionnaire->getQuestions($surveyId);
    $responses = $response->getResponsesWithAnswers($surveyId);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="results_' . $surveyId . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    $headers = ['Response ID', 'Submitted', 'Name', 'Student Number', 'Email'];
    foreach ($questions as $q) {
        $headers[] = sanitize($q['question_text']);
    }
    fputcsv($out, $headers);

    foreach ($responses as $r) {
        $row = [
            $r['id'],
            $r['submitted_at'],
            $r['respondent_name'] ?? '',
            $r['student_number'] ?? '',
            $r['email'] ?? ''
        ];
        foreach ($questions as $q) {
            $val = $r['answers'][$q['id']] ?? '';
            if (is_array($val)) $val = implode(', ', $val);
            $row[] = $val;
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

if ($page === 'download_codes') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $survey['user_id'] != $currentUser['id']) {
        setMessage('error', 'Access denied');
        redirect('index.php?page=surveys');
    }
    $codes = $db->getAccessCodes($surveyId);
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="access_codes_' . $surveyId . '.txt"');
    echo "Access Codes for: " . $survey['title'] . "\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "======================================================\n\n";
    foreach ($codes as $code) {
        $status = $code['is_used'] ? " (USED)" : "";
        echo $code['code'] . $status . "\n";
    }
    exit;
}

if ($page === 'admin') {
    if ($currentUser['role'] !== 'admin') {
        setMessage('error', 'Access denied');
        redirect('index.php');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_creator'])) {
        $result = $auth->register(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['email'] ?? null,
            $_POST['first_name'] ?? null,
            $_POST['last_name'] ?? null,
            null,
            'creator'
        );
        if ($result['success']) {
            setMessage('success', 'Creator created successfully');
        } else {
            setMessage('error', $result['error']);
        }
        redirect('index.php?page=admin');
    }
    $users = $auth->getAllUsers();
    $pageTitle = 'Admin Panel';
    include TEMPLATES_DIR . '/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">Admin Panel</h1>
    </div>
    <div class="card mb-3">
        <h2 class="card-title">Create New Creator (Lecturer)</h2>
        <form method="POST">
            <input type="hidden" name="create_creator" value="1">
            <div class="form-group">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-input" required placeholder="e.g., john_doe">
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">Create Creator Account</button>
        </form>
    </div>
    <div class="card">
        <h2 class="card-title">All Users</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo sanitize($u['username']); ?></td>
                    <td><?php echo sanitize(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></td>
                    <td><?php echo sanitize($u['email'] ?? ''); ?></td>
                    <td><span class="badge badge-<?php echo $u['role'] === 'admin' ? 'warning' : ($u['role'] === 'creator' ? 'primary' : 'success'); ?>"><?php echo ucfirst($u['role']); ?></span></td>
                    <td><?php echo $u['created_at']; ?></td>
                    <td>
                        <?php if ($u['role'] !== 'admin'): ?>
                        <a href="index.php?page=delete_user&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-ghost" onclick="return confirmAction('Delete this user?')">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    include TEMPLATES_DIR . '/footer.php';
    exit;
}

if ($page === 'delete_user') {
    if ($currentUser['role'] !== 'admin') {
        setMessage('error', 'Access denied');
        redirect('index.php');
    }
    $userId = (int)($_GET['id'] ?? 0);
    $auth->deleteUser($userId);
    setMessage('success', 'User deleted');
    redirect('index.php?page=admin');
    exit;
}

if ($page === 'copy') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $survey['user_id'] != $currentUser['id']) {
        setMessage('error', 'Survey not found or access denied');
        redirect('index.php?page=surveys');
    }
    $newSurveyId = $questionnaire->copy($surveyId, $currentUser['id']);
    if ($newSurveyId) {
        setMessage('success', 'Survey copied successfully. Edit the copy as needed.');
        redirect('index.php?page=edit&id=' . $newSurveyId);
    } else {
        setMessage('error', 'Failed to copy survey');
        redirect('index.php?page=surveys');
    }
    exit;
}

if ($page === 'delete_survey') {
    $surveyId = (int)($_GET['id'] ?? 0);
    $survey = $questionnaire->get($surveyId);
    if (!$survey || $survey['user_id'] != $currentUser['id']) {
        setMessage('error', 'Survey not found or access denied');
        redirect('index.php?page=surveys');
    }
    $questionnaire->delete($surveyId);
    setMessage('success', 'Survey deleted');
    redirect('index.php?page=surveys');
    exit;
}

// Default: redirect to public surveys (student landing page)
if ($page === '') {
    redirect('index.php?page=public');
    exit;
}