# QuestSurvey - Web-Based Questionnaire System

## Project Requirements

**Objective:** Design and implement a Web-Based Questionnaire System with Construction, Management, Presentation and Analysis facilities.

**Hosting:** Unix/Linux based system with MySQL (file-based SQLite encouraged)

---

## 1. Literature Survey

### 1.1 Question Types Available on the Market
Based on research of SurveyMonkey, Google Forms, LimeSurvey, Typeform:

| Type | Description | Implementation |
|------|-------------|----------------|
| Multiple Choice | Single selection from options (A,B,C,D,E) | Radio buttons |
| Multiple Select | Multiple selections allowed | Checkboxes |
| Likert Scale | 1-5 scale (Strongly Disagree to Strongly Agree) | Radio buttons |
| Short Text | Single line text input | Input field |
| Long Text | Multi-line text response | Textarea |
| Yes/No | Binary response | Radio buttons |
| Date | Date picker | Date input |
| Number | Numeric-only input | Number input |

### 1.2 Questionnaire Access Methods
| Method | Description |
|--------|-------------|
| Anonymous | No identification required (course evaluation) |
| Identified | Name and student number required |
| Access Code | One-time unique codes for limited responses |

---

## 2. System Design

### 2.1 User Roles
| Role | Permissions |
|------|-------------|
| Admin | Full system access, manage users |
| Creator (Lecturer) | Create/manage own questionnaires, view own results |

### 2.2 Creator Information
- Name
- Surname
- Contact address
- Password

### 2.3 Database Schema

```sql
-- Users (Creators)
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    first_name TEXT,
    last_name TEXT,
    contact TEXT,
    role TEXT DEFAULT 'creator',
    created_at DATETIME
);

-- Questionnaires
CREATE TABLE questionnaires (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    title TEXT,
    description TEXT,
    identity_mode TEXT,  -- anonymous, identified, access_code
    start_date DATETIME,
    end_date DATETIME,
    allow_multiple INTEGER,
    is_active INTEGER,
    created_at DATETIME
);

-- Questions
CREATE TABLE questions (
    id INTEGER PRIMARY KEY,
    questionnaire_id INTEGER,
    type TEXT,
    question_text TEXT,
    options TEXT,
    required INTEGER,
    order_index INTEGER
);

-- Access Codes (one-time use)
CREATE TABLE access_codes (
    id INTEGER PRIMARY KEY,
    questionnaire_id INTEGER,
    code TEXT UNIQUE,
    is_used INTEGER DEFAULT 0,
    created_at DATETIME
);

-- Responses
CREATE TABLE responses (
    id INTEGER PRIMARY KEY,
    questionnaire_id INTEGER,
    respondent_name TEXT,
    student_number TEXT,
    access_code_id INTEGER,
    submitted_at DATETIME
);

-- Answers
CREATE TABLE answers (
    id INTEGER PRIMARY KEY,
    response_id INTEGER,
    question_id INTEGER,
    answer_value TEXT
);
```

---

## 3. Implementation

### 3.1 Content Management System (CMS)
- Create questionnaire with title, description
- Add/edit/delete questions
- Select question types
- Configure identity mode
- Set availability dates (start/end)
- Generate access codes (one-time)

### 3.2 Presentation Module
- Responsive survey form
- Identity collection based on mode:
  - **Anonymous:** No name required
  - **Identified:** Collect name + student number
  - **Access Code:** Validate code, delete after use
- Input validation
- Confirmation after submission

### 3.3 Analysis Module
- Total responses count
- Per-question analysis:
  - Multiple Choice: counts and percentages
  - Likert Scale: average score, distribution
  - Yes/No: counts
  - Number: average, min, max
  - Text: list all responses
- CSV export

---

## 4. Technical Specifications

### 4.1 Technology Stack
- **Backend:** PHP 7.4+ (native, no frameworks)
- **Database:** SQLite 3 (file-based, self-contained)
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Server:** Apache/Nginx on Unix/Linux

### 4.2 No External Dependencies
- Self-contained installation
- No MySQL required (SQLite file-based)
- No Composer
- No external libraries

---

## 5. Release Requirements

### 5.1 Installation
- Fully installable on host without technical information
- No server-side third-party applications needed
- Auto-creation of database on first run

### 5.2 Documentation
- Technical documentation
- User side documentation (User Guide)
- Poster presentation

---

## 6. Features Summary

- [x] Creator registration with name, surname, contact
- [x] 7 question types implementation
- [x] 3 identity modes (anonymous, identified, access code)
- [x] Access code generation and one-time use
- [x] Availability dates (start/end)
- [x] Survey taking with identity collection
- [x] Response storage
- [x] Results analysis
- [x] CSV export
- [x] Self-contained (no dependencies)
