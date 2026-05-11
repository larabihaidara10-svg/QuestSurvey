<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?><?php echo isset($pageTitle) ? ' - ' . $pageTitle : ''; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar">
        <div class="container flex justify-between items-center">
            <a href="index.php" class="navbar-brand"><?php echo APP_NAME; ?></a>
            <div class="flex gap-2 items-center">
                <a href="index.php?page=surveys" class="nav-link">My Surveys</a>
                <a href="index.php?page=public" class="nav-link">Public Surveys</a>
                <?php if (getCurrentUser()['role'] === 'admin'): ?>
                <a href="index.php?page=admin" class="nav-link">Admin Panel</a>
                <?php endif; ?>
                <span class="text-sm text-muted">
                    <?php echo getCurrentUser()['first_name'] ?? getCurrentUser()['username']; ?>
                </span>
                <a href="index.php?page=logout" class="btn btn-sm btn-ghost">Logout</a>
            </div>
        </div>
    </nav>
    <?php else: ?>
    <nav class="navbar">
        <div class="container flex justify-between items-center">
            <a href="index.php" class="navbar-brand"><?php echo APP_NAME; ?></a>
            <div class="flex gap-2 items-center">
                <a href="index.php?page=public" class="nav-link">Surveys</a>
                <a href="index.php?page=login" class="nav-link">Creator Login</a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="container py-3">
        <?php if ($msg = getMessage()): ?>
        <div class="alert alert-<?php echo $msg['type']; ?>">
            <?php echo sanitize($msg['text']); ?>
        </div>
        <?php endif; ?>
        
        <script>
        function confirmAction(msg) {
            return confirm(msg || 'Are you sure?');
        }
        </script>