<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if (Database::isInstalled()) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($password) || empty($firstName) || empty($lastName)) {
        $error = 'Username, password, name and surname are required';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        try {
            Database::install($username, $password, $email, $firstName, $lastName, $contact);
            $success = 'Installation complete!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - <?php echo APP_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 40px; width: 100%; max-width: 450px; }
        h1 { font-size: 1.5rem; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.875rem; color: #374151; }
        input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .row { display: flex; gap: 10px; }
        .row .form-group { flex: 1; }
        .btn { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 500; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.875rem; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>
    <div class="card">
        <h1><?php echo APP_NAME; ?></h1>
        <p class="subtitle">Setup your questionnaire system (Admin Account)</p>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <a href="index.php" class="btn" style="text-decoration: none; text-align: center;">Continue to Login</a>
        <?php else: ?>
        <form method="POST">
            <div class="row">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" placeholder="John" required>
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" placeholder="Doe" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" placeholder="johndoe" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="john@example.com">
            </div>
            
            <div class="form-group">
                <label>Contact Address</label>
                <input type="text" name="contact" placeholder="Phone or address">
            </div>
            
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Minimum 6 characters" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" placeholder="Confirm password" required>
            </div>
            
            <button type="submit" class="btn">Install System</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
