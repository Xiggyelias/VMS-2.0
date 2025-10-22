<?php
// process-reset.php
session_start();
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'vehicleregistrationsystem';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $error = '';
    $success = false;

    if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        $error = 'Invalid or missing token.';
    } elseif (!$csrf_token || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
        $error = 'Invalid CSRF token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$conn->connect_error) {
            $stmt = $conn->prepare('
                SELECT prt.id, prt.user_id, prt.expires_at 
                FROM password_reset_tokens prt 
                WHERE prt.token = ? AND prt.used = FALSE 
                LIMIT 1
            ');
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($token_id, $user_id, $expires);
                $stmt->fetch();
                if (strtotime($expires) > time()) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare('UPDATE applicants SET password = ? WHERE applicant_id = ?');
                    $stmt2->bind_param('si', $password_hash, $user_id);
                    if ($stmt2->execute()) {
                        // Mark token as used
                        $stmt3 = $conn->prepare('UPDATE password_reset_tokens SET used = TRUE WHERE id = ?');
                        $stmt3->bind_param('i', $token_id);
                        $stmt3->execute();
                        $stmt3->close();
                        $success = true;
                        unset($_SESSION['csrf_token']);
                    }
                    $stmt2->close();
                } else {
                    $error = 'This reset link has expired.';
                }
            } else {
                $error = 'Invalid reset link.';
            }
            $stmt->close();
            $conn->close();
        } else {
            $error = 'Database connection error.';
        }
    }
} else {
    $error = 'Invalid request.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body { background: #121212; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { background: #1e1e1e; padding: 2rem 2.5rem; border-radius: 12px; box-shadow: 0 0 20px #d00000aa; max-width: 400px; width: 100%; }
        .logo { display: block; margin: 0 auto 1rem; height: 45px; filter: brightness(0) invert(1); }
        h2 { color: #d00000; text-align: center; margin-bottom: 1rem; }
        .alert { padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; text-align: center; }
        .alert-success { background: #005c00; color: #77ff77; }
        .alert-error { background: #5c0000; color: #ff7777; }
        a { color: #d00000; }
    </style>
</head>
<body>
<div class="container">
    <img src="AULogo.png" alt="AU Logo" class="logo" />
    <h2>Password Reset</h2>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">Your password has been reset. <a href="login.php">Login</a></div>
    <?php else: ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <div style="text-align:center;"><a href="forgot_password.php">Request new link</a></div>
    <?php endif; ?>
</div>
</body>
</html> 