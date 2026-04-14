# QuestSurvey - Technical Documentation

## 1. System Overview

**Project Name:** QuestSurvey  
**Type:** Web-Based Questionnaire System  
**Technology Stack:** PHP 7.4+, SQLite, HTML5, CSS3, JavaScript  
**Hosting:** Unix/Linux (Apache/Nginx) or Windows (XAMPP)

---

## 2. File Structure

```
questsurvey/
├── index.php              # Main entry point & router
├── config.php             # Configuration & constants
├── install.php            # Database installation
├── seed.php               # Sample data creation
├── SPEC.md               # Project specification
├── docs/
│   └── README.md         # User guide
├── data/                  # SQLite databases
│   ├── questionnaires.db
│   └── responses.db
├── includes/
│   ├── database.php       # PDO wrapper
│   ├── auth.php          # Authentication
│   ├── questionnaire.php # Survey CRUD
│   └── response.php      # Response handling
├── templates/
│   ├── header.php
│   └── footer.php
└── assets/
    └── css/style.css
```

---

## 3. Database Schema

### 3.1 Users Table
```sql
CREATE TABLE users (
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
```

### 3.2 Questionnaires Table
```sql
CREATE TABLE questionnaires (
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
```

### 3.3 Questions Table
```sql
CREATE TABLE questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    questionnaire_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    question_text TEXT NOT NULL,
    options TEXT,
    required INTEGER DEFAULT 0,
    order_index INTEGER DEFAULT 0,
    FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE
);
```

### 3.4 Access Codes Table
```sql
CREATE TABLE access_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    questionnaire_id INTEGER NOT NULL,
    code TEXT NOT NULL UNIQUE,
    is_used INTEGER DEFAULT 0,
    used_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE
);
```

### 3.5 Responses Table
```sql
CREATE TABLE responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    questionnaire_id INTEGER NOT NULL,
    respondent_name TEXT,
    student_number TEXT,
    access_code_id INTEGER,
    ip_address TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 3.6 Answers Table
```sql
CREATE TABLE answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    response_id INTEGER NOT NULL,
    question_id INTEGER NOT NULL,
    answer_value TEXT,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)
);
```

---

## 4. Question Types

| Type | Description | Database Value |
|------|-------------|----------------|
| Multiple Choice | Single selection | `multiple_choice` |
| Likert Scale | 1-5 rating | `likert_5` |
| Short Text | Single line | `short_text` |
| Long Text | Multi-line | `long_text` |
| Yes/No | Binary | `yes_no` |
| Date | Date picker | `date` |
| Number | Numeric | `number` |

---

## 5. Identity Modes

| Mode | Description | Behavior |
|------|-------------|----------|
| Anonymous | No identification | No name required |
| Identified | Name + student number | Required before questions |
| Access Code | One-time codes | Code deleted after use |

---

## 6. API Reference

### 6.1 Authentication
- `Auth::register($username, $password, $email, $firstName, $lastName, $contact, $role)`
- `Auth::login($username, $password)`
- `Auth::logout()`

### 6.2 Questionnaire
- `Questionnaire::create($userId, $data)`
- `Questionnaire::update($id, $data)`
- `Questionnaire::delete($id)`
- `Questionnaire::get($id)`
- `Questionnaire::getAll($userId)`
- `Questionnaire::getQuestions($questionnaireId)`
- `Questionnaire::addQuestion($questionnaireId, $data)`

### 6.3 Response
- `ResponseHandler::createResponse($questionnaireId, $respondentName, $studentNumber, $accessCodeId)`
- `ResponseHandler::saveAnswer($responseId, $questionId, $value)`
- `ResponseHandler::getResponses($questionnaireId)`
- `ResponseHandler::getAnswers($responseId)`

### 6.4 Database
- `Database::query($sql, $params)`
- `Database::queryOne($sql, $params)`
- `Database::execute($sql, $params)`
- `Database::generateAccessCodes($questionnaireId, $count)`
- `Database::validateAccessCode($questionnaireId, $code)`
- `Database::markAccessCodeUsed($codeId)`

---

## 7. Security

- Passwords hashed using PHP `password_hash()`
- SQL injection prevention via PDO prepared statements
- XSS prevention via `htmlspecialchars()`
- Session regeneration on login

---

## 8. Installation

1. Upload files to web directory
2. Ensure `data/` folder is writable
3. Navigate to application URL
4. Create admin account
5. System auto-creates databases

**Requirements:**
- PHP 7.4+
- SQLite3 extension (included in PHP)

---

## 9. Customization

### Configuration (config.php)
- `APP_NAME`: System name
- `QUESTION_TYPES`: Available question types
- `IDENTITY_MODES`: Access modes
- `DEBUG`: Enable debug mode

### Styling (assets/css/style.css)
- CSS custom properties for colors
- Responsive design
- Bootstrap-like utility classes
