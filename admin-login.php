<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Check if admin is already logged in
if (isAdmin()) {
    redirect(BASE_URL . '/admin-dashboard.php');
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'empty_fields') {
        $error = 'Please fill in all fields.';
    } elseif ($_GET['error'] === 'invalid_password') {
        $error = 'Invalid username or password.';
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        redirect(BASE_URL . '/admin-login.php?error=empty_fields');
    }

    $result = adminLogin($username, $password);
    if ($result['success']) {
        redirect(BASE_URL . '/admin-dashboard.php');
    } else {
        redirect(BASE_URL . '/admin-login.php?error=invalid_password');
    }
}

// GET request: render admin login page
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= htmlspecialchars(APP_NAME) ?></title>
    <?php includeCommonAssets(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --au-red: #d00000; --au-red-dark: #b00000; }
        .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #fff 0%, #f6f7fb 40%, #fdeeee 100%); padding: 24px; }
        .login-card { background: #fff; border-radius: 16px; box-shadow: 0 18px 40px rgba(0,0,0,0.08); padding: 36px 32px 28px; width: 100%; max-width: 440px; border: 1px solid #f0f2f5; }
        .login-header { text-align: center; margin-bottom: 24px; }
        .login-header .logo { width: 84px; height: 84px; border-radius: 50%; background: #fff; display:flex; align-items:center; justify-content:center; margin: 0 auto 12px; box-shadow: 0 8px 22px rgba(0,0,0,0.06); border: 1px solid #eef0f4; }
        .login-header h1 { margin: 0; color: var(--au-red); font-size: 22px; letter-spacing: .2px; font-weight: 700; }
        .login-header p { margin: 6px 0 0; color: #6b7280; font-size: 14px; }
        .input-group { position: relative; margin-bottom: 1rem; }
        .input-group i { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: #9aa0a6; }
        .input-group input { width: 100%; padding: 0.9rem 2.75rem; border: 1px solid #e5e7eb; border-radius: 12px; font-size: 1rem; background:#fff; }
        .input-group input:focus { border-color: var(--au-red); outline: none; box-shadow: 0 0 0 3px rgba(208,0,0,.12); }
        .login-button { width: 100%; padding: 0.95rem; background: var(--au-red); color: #fff; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 18px rgba(208,0,0,0.18); transition: transform .12s ease, background-color .15s ease; }
        .login-button:hover { background: var(--au-red-dark); transform: translateY(-1px); }
        .alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 16px; }
        .alert-danger { background: #fff1f1; color: #9b1c1c; border: 1px solid #f7caca; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="<?= asset('images/AULogo.png') ?>" alt="AU Logo" style="height:56px; width:auto;" />
                </div>
                <h1>Admin Login</h1>
                <p>Sign in to the admin dashboard</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form action="admin-login.php" method="POST" autocomplete="off">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="input-group">
                    <i class="fa fa-user-gear"></i>
                    <input type="text" name="username" id="Username" placeholder="Admin Username" required>
                </div>
                <div class="input-group">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" id="adminPassword" placeholder="Admin Password" required autocomplete="current-password">
                </div>
                <button type="submit" class="login-button">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
?>
