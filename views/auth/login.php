<?php
/**
 * User Login Page
 * 
 * This page handles user authentication for students, staff, and guests.
 * Uses the new modular structure with centralized functions.
 */

// Include application initialization
require_once '../../includes/init.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . '/user-dashboard.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $userType = $_POST['userType'] ?? '';
    
    // Validate inputs
    if (empty($email) || empty($password) || empty($userType)) {
        $error = 'Please fill in all fields.';
    } else {
        // Attempt login
        $result = userLogin($email, $password, $userType);
        
        if ($result['success']) {
            // Redirect to dashboard or specified URL
            $redirectUrl = $_GET['redirect'] ?? BASE_URL . '/user-dashboard.php';
            redirect($redirectUrl);
        } else {
            $error = $result['message'];
        }
    }
}

// Get flash message if any
$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $error = $flash['message'];
    }
}

// Generate CSRF token for admin form
$csrfToken = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- Include common assets -->
    <?php includeCommonAssets(); ?>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header img {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 24px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-danger {
            background-color: #fee;
            color: #c53030;
            border: 1px solid #fed7d7;
        }
        
        .alert-success {
            background-color: #f0fff4;
            color: #2f855a;
            border: 1px solid #c6f6d5;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .user-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .user-type-option {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-type-option:hover {
            border-color: #667eea;
        }
        
        .user-type-option.selected {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .user-type-option input {
            display: none;
        }
        
        /* Login Tabs */
        .login-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }
        
        .login-tab {
            padding: 1rem 2rem;
            cursor: pointer;
            color: #666;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s ease;
        }
        
        .login-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .login-tab:hover {
            color: #667eea;
        }
        
        /* Form Container Tabs */
        .login-form-container {
            display: none;
        }
        
        .login-form-container.active {
            display: block;
        }
        
        /* Ensure Google Sign-In elements are properly hidden when not active */
        .login-form-container:not(.active) .g_id_signin,
        .login-form-container:not(.active) #g_id_onload,
        .login-form-container:not(.active) .google-signin-container {
            display: none !important;
        }
        
        /* Additional rules to ensure Google elements are hidden */
        .login-form-container:not(.active) .g_id_signin > div,
        .login-form-container:not(.active) .g_id_signin > iframe,
        .login-form-container:not(.active) .g_id_signin > button {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        
        /* Hide any Google Sign-In elements that might be rendered outside containers */
        body:not(.google-tab-active) .g_id_signin,
        body:not(.google-tab-active) #g_id_onload {
            display: none !important;
        }
        
        /* When admin tab is active, hide all Google elements */
        body.admin-tab-active .g_id_signin,
        body.admin-tab-active #g_id_onload,
        body.admin-tab-active .google-signin-container {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        
        /* Admin Login Form Styles */
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-group input {
            width: 100%;
            padding: 1rem 3rem 1rem 3rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #fff;
        }
        
        .input-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
            outline: none;
        }
        
        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
            font-size: 1rem;
        }
        
        .toggle-password {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
            cursor: pointer;
        }
        
        .login-button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .1s ease, background-color 0.2s ease;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.15);
        }
        
        .login-button:hover {
            transform: translateY(-1px);
        }
        
        /* Google Sign-In specific styles */
        .g_id_signin {
            margin: 2rem auto;
            min-height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .fallback-google-btn {
            background: #4285f4 !important;
            color: white !important;
            border: none !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            font-size: 16px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 1rem auto !important;
            width: 100%;
            max-width: 280px;
            justify-content: center;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .fallback-google-btn:hover {
            background: #3367d6 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }
        
        .google-signin-container {
            text-align: center;
            margin: 2rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        .google-debug-info {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .google-debug-info p {
            margin: 0.25rem 0;
            font-size: 11px;
            color: #6c757d;
        }
        
        /* Ensure Google Sign-In button is properly centered */
        .g_id_signin > div {
            margin: 0 auto !important;
        }
        
        /* Add some spacing around the Google Sign-In area */
        .google-signin-container > div:first-child {
            margin-bottom: 1rem;
        }
        
        /* Add subtle animation to the Google Sign-In container */
        .google-signin-container {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .user-type-selector {
                flex-direction: column;
            }
            
            .login-tabs {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .login-tab {
                padding: 0.75rem 1rem;
                text-align: center;
                border-bottom: none;
                border-left: 3px solid transparent;
                margin-bottom: 0;
            }
            
            .login-tab.active {
                border-left-color: #667eea;
                border-bottom-color: transparent;
            }
            
            .google-signin-container {
                margin: 1rem 0;
                padding: 0.75rem;
            }
            
            .fallback-google-btn {
                max-width: 100%;
                padding: 14px 20px;
                font-size: 16px;
            }
            
            .input-group input {
                padding: 0.875rem 2.5rem 0.875rem 2.5rem;
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?= asset('images/AULogo.png') ?>" alt="AU Logo">
                <h1><?= APP_NAME ?></h1>
                <p style="color: #666; margin: 5px 0 0 0;">Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <!-- Login Tabs -->
            <div class="login-tabs">
                <div class="login-tab active" onclick="switchTab('google')">AU Google Sign-In</div>
                <div class="login-tab" onclick="switchTab('admin')">Admin Login</div>
            </div>
            
            <!-- Google Sign-In Form -->
            <div id="googleLoginForm" class="login-form-container active">
                <div class="google-signin-container">
                    <div id="g_id_onload"
                         data-client_id="<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-callback="handleGoogleCredential"
                         data-auto_select="false"
                         data-itp_support="true"
                         data-use_fedcm_for_prompt="false">
                    </div>
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="continue_with"
                         data-size="large"
                         data-logo_alignment="left">
                </div>
                
                    <!-- Fallback button in case Google Sign-In doesn't load -->
                    <button id="fallbackGoogleBtn" onclick="showGoogleSignInError()" class="fallback-google-btn" style="display:none;">
                        <i class="fa fa-google"></i>
                        Sign in with Google
                    </button>
                    
                    <div id="googleError" class="alert alert-danger" style="display:none; width:100%; text-align:center;"></div>
                    
                    <!-- Debug info (only in development) -->
                    <?php if (APP_ENV === 'development'): ?>
                    <div class="google-debug-info">
                        <p><strong>Client ID:</strong> <?= substr(GOOGLE_CLIENT_ID, 0, 20) ?>...</p>
                        <p><strong>Domain:</strong> <?= ALLOWED_GOOGLE_DOMAIN ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Admin Login Form -->
            <div id="adminLoginForm" class="login-form-container">
                <form id="adminLogin" action="admin-login.php" method="POST">
                    <!-- CSRF Token -->
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="input-group">
                        <i class="fa fa-user-gear"></i>
                        <input type="text" placeholder="Admin Username" id="Username" name="username" required>
                </div>
                
                    <div class="input-group">
                        <i class="fa fa-lock"></i>
                        <input type="password" id="adminPassword" name="password" placeholder="Admin Password" required autocomplete="current-password">
                        <span class="toggle-password" onclick="togglePassword('adminPassword', this)" aria-label="Show password"><i class="fa fa-eye"></i></span>
                </div>
                
                    <button type="submit" name="admin_login" class="login-button">Admin Login</button>
            </form>
            </div>
            
            <div class="login-footer">
                <p>
                    <a href="<?= BASE_URL ?>/admin-login.php">Admin Login</a>
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Check if Google Sign-In loads properly
        window.addEventListener('load', function() {
            // Ensure initial tab state is correct
            switchTab('google');
            
            setTimeout(function() {
                const googleSignIn = document.querySelector('.g_id_signin');
                const fallbackBtn = document.getElementById('fallbackGoogleBtn');
                
                if (!googleSignIn || !googleSignIn.children.length) {
                    console.log('Google Sign-In not loaded, showing fallback button');
                    fallbackBtn.style.display = 'block';
                } else {
                    console.log('Google Sign-In loaded successfully');
                }
            }, 3000); // Wait 3 seconds for Google Sign-In to load
        });

        function switchTab(tab) {
            // Update tab styles
            document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
            document.querySelector(`.login-tab[onclick="switchTab('${tab}')"]`).classList.add('active');
            
            // Show/hide forms
            document.querySelectorAll('.login-form-container').forEach(f => f.classList.remove('active'));
            document.getElementById(`${tab}LoginForm`).classList.add('active');
            
            // Update body class for CSS targeting
            document.body.classList.remove('google-tab-active', 'admin-tab-active');
            document.body.classList.add(`${tab}-tab-active`);
            
            // Force hide Google Sign-In elements when switching to admin tab
            if (tab === 'admin') {
                // Hide any Google Sign-In elements that might be visible
                const googleElements = document.querySelectorAll('.g_id_signin, #g_id_onload, .google-signin-container');
                googleElements.forEach(el => {
                    if (el.closest('#googleLoginForm')) {
                        el.style.display = 'none';
                    }
                });
                
                // Also hide any Google elements that might be rendered globally
                const globalGoogleElements = document.querySelectorAll('.g_id_signin, #g_id_onload');
                globalGoogleElements.forEach(el => {
                    if (!el.closest('#googleLoginForm')) {
                        el.style.display = 'none';
                    }
                });
            } else if (tab === 'google') {
                // Show Google Sign-In elements when switching back
                const googleElements = document.querySelectorAll('.g_id_signin, #g_id_onload, .google-signin-container');
                googleElements.forEach(el => {
                    if (el.closest('#googleLoginForm')) {
                        el.style.display = '';
                    }
                });
            }
        }

        function togglePassword(inputId, element) {
            const input = document.getElementById(inputId);
            const icon = element.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa fa-eye';
            }
        }

        function handleGoogleCredential(response) {
            console.log('Google credential received:', response);
            const errorBox = document.getElementById('googleError');
            errorBox.style.display = 'none';
            
            fetch('google_auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ credential: response.credential })
            })
            .then(r => r.json())
            .then(data => {
                console.log('Auth response:', data);
                if (data.success) {
                    window.location.href = data.redirect || 'user-dashboard.php';
                } else {
                    errorBox.textContent = data.message || 'Sign-in failed';
                    errorBox.style.display = 'block';
                }
            })
            .catch((error) => {
                console.error('Auth error:', error);
                errorBox.textContent = 'Sign-in failed. Please try again.';
                errorBox.style.display = 'block';
            });
        }

        function showGoogleSignInError() {
            const googleError = document.getElementById('googleError');
            googleError.textContent = 'Google Sign-In failed to load. Please try again or use the fallback button.';
            googleError.style.display = 'block';
        }

        // Debug function to check Google Sign-In status
        function checkGoogleSignInStatus() {
            const googleSignIn = document.querySelector('.g_id_signin');
            console.log('Google Sign-In element:', googleSignIn);
            if (googleSignIn) {
                console.log('Google Sign-In children:', googleSignIn.children.length);
            }
        }

        // Call debug function after page loads
        window.addEventListener('load', function() {
            setTimeout(checkGoogleSignInStatus, 2000);
        });
    </script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html> 