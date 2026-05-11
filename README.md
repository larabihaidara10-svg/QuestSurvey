# QuestSurvey
Web-Based Questionnaire System

## Installation

1. Copy the entire project folder to your web server root (e.g., `/var/www/html/QuestSurvey` or `C:\xampp\htdocs\QuestSurvey`)
2. Ensure PHP 7.4+ with PDO SQLite extension is enabled
3. Access `http://your-server/QuestSurvey/install.php`
4. Enter the setup key: `SETUP_ADMIN_2026`
5. Create an admin username and password
6. Login at `index.php?page=login`

## User Roles

- **Admin**: Full system access, creates creator accounts
- **Creator**: Creates/manages questionnaires, views results
- **User**: Participates in surveys (anonymous or identified)

## Quick Creator Setup

Admin can create creator accounts from Admin Panel (`index.php?page=admin`)

## Directory Structure

```
QuestSurvey/
├── assets/css/style.css     # Styling
├── config.php               # Configuration
├── install.php              # Installation page
├── index.php                # Main application
├── includes/                # PHP classes
│   ├── database.php         # SQLite database
│   ├── auth.php             # Authentication
│   ├── questionnaire.php    # Survey management
│   ├── response.php         # Response handling
│   └── analysis.php         # Result analysis
└── templates/               # Layout
    ├── header.php
    └── footer.php
```

## Features

- 7 question types (Multiple Choice, Likert Scale, Short/Long Text, Yes/No, Number, Date)
- 3 identity modes (Anonymous, Identified, Access Code)
- Access code generation and one-time use
- Date-based survey availability
- CSV export of results
- Auto-installing database
- No external dependencies