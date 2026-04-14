<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('APP_NAME') ? APP_NAME : 'QuestSurvey'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand"><?php echo defined('APP_NAME') ? APP_NAME : 'QuestSurvey'; ?></a>
            <?php if (isLoggedIn()): ?>
            <div class="navbar-menu">
                <a href="index.php" class="nav-link">Dashboard</a>
                <a href="index.php?page=create" class="nav-link">Create</a>
                <a href="index.php?page=surveys" class="nav-link">My Surveys</a>
                <span class="nav-link"><?php echo sanitize(getCurrentUser()['username']); ?></span>
                <a href="index.php?page=logout" class="nav-link" style="color: var(--error);">Logout</a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <main class="main-content">
        <div class="container">
