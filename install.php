<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $adminKey = $_POST['admin_key'] ?? '';
    
    if (empty($username) || empty($password) || empty($adminKey)) {
        $error = 'All fields are required';
    } elseif ($adminKey !== 'SETUP_ADMIN_2026') {
        $error = 'Invalid admin setup key';
    } else {
        require_once INCLUDES_DIR . '/database.php';
        $db = Database::getInstance();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $db->execute(
                "INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')",
                [$username, $hash]
            );
            $success = 'Installation complete! You can now login.';
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Installation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="card" style="max-width: 400px; margin: 3rem auto;">
        <h1 class="card-title text-center">System Setup</h1>
        <p class="text-center text-muted text-sm">Use the setup key to create the admin account</p>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <a href="index.php?page=login" class="btn btn-primary w-100">Go to Login</a>
        <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Admin Username *</label>
                <input type="text" name="username" class="form-input" placeholder="Choose a username" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Admin Password *</label>
                <input type="password" name="password" class="form-input" placeholder="Choose a strong password" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Setup Key *</label>
                <input type="text" name="admin_key" class="form-input" placeholder="SETUP_ADMIN_2026" required>
                <small class="text-muted">Setup key: <strong>SETUP_ADMIN_2026</strong></small>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Setup System</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>