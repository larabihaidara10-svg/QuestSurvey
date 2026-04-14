<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
</head>
<body>
    <h1>Testing if PHP works</h1>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'; ?></p>
</body>
</html>
