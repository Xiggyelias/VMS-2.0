<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Generate CSRF token for forms
$csrfToken = SecurityMiddleware::generateCSRFToken();

// Prepare alert messages (rendered later inside the page)
$alert_type = null;
$alert_message = null;

if (isset($_GET['error'])) {
    $error = $_GET['error'];
    if ($error === 'empty_fields') {
        $alert_type = 'danger';
        $alert_message = 'Please fill in all fields.';
    } elseif ($error === 'invalid_password') {
        $alert_type = 'danger';
        $alert_message = 'Invalid password.';
    } elseif ($error === 'not_found') {
        $alert_type = 'warning';
        $alert_message = 'Account not found.';
    }
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $alert_type = 'success';
    $alert_message = 'Your password has been reset successfully. Please login with your new password.';
}

// Handle login result
if (isset($login_successful) && $login_successful) {
    // Save logged-in user ID to session
    $_SESSION['user_id'] = $user['applicant_id'];
    header("Location: user-dashboard.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title>Login - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fff 0%, #f3f6ff 35%, #fdeeee 100%);
        }

        // Lightweight UX cache (localStorage) for last role/identifier per temp user id
        function saveUserCache(userId, data) {
            try { if (userId) localStorage.setItem('user_cache_' + userId, JSON.stringify(data || {})); } catch (_) {}
        }
        function loadUserCache(userId) {
            try {
                if (!userId) return null;
                const raw = localStorage.getItem('user_cache_' + userId);
                return raw ? JSON.parse(raw) : null;
            } catch (_) { return null; }
        }
        function clearUserCache(userId) {
            try { if (userId) localStorage.removeItem('user_cache_' + userId); } catch (_) {}
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

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
            color: var(--primary-red);
            border-bottom-color: var(--primary-red);
        }

        .login-form-container {
            display: none;
        }

        .login-form-container.active {
            display: block;
        }

        .login-left {
            background-color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-right {
            background-color: var(--primary-red);
            color: var(--white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .login-right::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0) 100%);
        }

        .welcome-text {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .welcome-text h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 400px;
            line-height: 1.6;
        }

        .login-form {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2rem 2rem 1.5rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header .logo {
            width: 84px;
            height: 84px;
            background: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            border: 1px solid #eef0f4;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 1rem 3rem 1rem 3rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #fff;
        }

        .input-group input:focus, .input-group select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.12);
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

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .forgot-password {
            color: var(--primary-red);
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-button {
            width: 100%;
            padding: 1rem;
            background-color: var(--primary-red);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .1s ease, background-color 0.2s ease;
            box-shadow: 0 8px 16px rgba(208,0,0,0.15);
        }

        .login-button:hover {
            background-color: #b00000;
            transform: translateY(-1px);
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
        }

        .register-link a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        /* Google Sign-In specific styles */
        .g_id_signin {
            margin: 1rem 0;
            min-height: 40px;
        }

        #googleLoginForm {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }



        @media (max-width: 768px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .login-right {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-left">
            <div class="login-form">
                <div class="login-header">
                    <div class="logo">
                        <img src="assets/images/AULogo.png" alt="AU Logo" style="height: 56px; width: auto;">
                    </div>
                    <h1 style="color: var(--primary-red); margin: 0;">Welcome Back</h1>
                    <p style="color: #666; margin-top: 0.5rem;">Please log in to your account</p>
                </div>

                <?php if ($alert_type && $alert_message): ?>
                    <div class="alert alert-<?= htmlspecialchars($alert_type) ?>">
                        <?= htmlspecialchars($alert_message) ?>
                    </div>
                <?php endif; ?>


                <div id="googleLoginForm" class="login-form-container active" role="tabpanel" aria-labelledby="tab-google">
                    <div style="display:flex; flex-direction:column; gap:1rem; align-items:center;">
                        <!-- Google Sign-In Button -->
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
                        
                    </div>
                </div>

            </div>
        </div>

        <div class="login-right">
            <div class="welcome-text">
                <h2>Vehicle Registration System</h2>
                <p>Manage your vehicle registrations efficiently and securely. Keep track of all your vehicles in one place.</p>
            </div>
        </div>
    </div>

    <script>
        // Check if Google Sign-In loads properly
        window.addEventListener('load', function() {
            setTimeout(function() {
                const googleSignIn = document.querySelector('.g_id_signin');
                
                // Silently check if Google Sign-In is present; avoid logging sensitive data
            }, 3000); // Wait 3 seconds for Google Sign-In to load
        });

        // FedCM usage disabled; rely on Google button instead

        // Traditional OAuth fallback (or let Google button handle it)
        function fallbackLogin() {
            // If Google Identity Services is present, let the user click the button.
            // Optionally, you can open a popup to Google OAuth as a strict fallback.
            // const url = 'https://accounts.google.com/o/oauth2/v2/auth?client_id=' + encodeURIComponent('<?= htmlspecialchars(GOOGLE_CLIENT_ID) ?>') + '&redirect_uri=' + encodeURIComponent(window.location.origin + '/google_oauth_callback.php') + '&response_type=token&scope=email%20profile';
            // window.open(url, '_blank', 'width=500,height=600');
        }


        function handleGoogleCredential(response) {
            // Do not log raw credentials; send minimal payload to backend for verification
            fetch('google_auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify({ credential: response.credential })
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success && data.requires_type_selection) {
                    // First-time login and role ambiguous: prompt for registrant type
                    const tempUserId = data.temp_user_id;
                    const suggested = (data.user_info && data.user_info.derived_type) ? data.user_info.derived_type : 'student';
                    // Persist pending context so modal can be restored if page reloads
                    try { sessionStorage.setItem('pending_role_selection', JSON.stringify({ tempUserId, suggested, ts: Date.now() })); } catch (_) {}
                    showRoleSelection(tempUserId, suggested);
                    return;
                }
                if (data && (data.success || data.status === 'success')) {
                    window.location.href = data.redirect || 'user-dashboard.php';
                } else {
                    alert('Sign-in failed. Please try again.');
                }
            })
            .catch(() => {
                alert('Sign-in failed. Please try again.');
            });
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
            // If a pending role selection exists (from a previous step), auto-open the modal
            try {
                const q = new URLSearchParams(window.location.search);
                const stored = sessionStorage.getItem('pending_role_selection');
                if (stored) {
                    const obj = JSON.parse(stored);
                    if (obj && obj.tempUserId) {
                        showRoleSelection(obj.tempUserId, obj.suggested);
                    }
                } else if (q.get('requires_type_selection') === '1') {
                    // Only open if we have a pending tempUserId cached; otherwise, wait for next auth
                    const obj = stored ? JSON.parse(stored) : null;
                    if (obj && obj.tempUserId) {
                        showRoleSelection(obj.tempUserId, obj.suggested);
                    }
                }
            } catch (_) {}
        });

        // One-time role selection UI with ID validation
        function showRoleSelection(userId, suggested) {
            // Inject minimal styles for a clean look
            if (!document.getElementById('roleSelectStyles')) {
                const style = document.createElement('style');
                style.id = 'roleSelectStyles';
                style.textContent = `
                    .role-modal { box-shadow: 0 12px 30px rgba(0,0,0,.18); border-radius: 14px; padding: 22px; }
                    .role-modal h3 { margin: 0 0 6px; color: var(--primary-red); font-size: 20px; }
                    .role-modal p { margin: 0 0 14px; color: #444; font-size: 14px; }
                    .role-actions { display:flex; gap:.75rem; }
                    .role-btn { flex:1; padding: .8rem 1rem; border: 1px solid #e5e7eb; border-radius: 10px; background:#f8fafc; color:#111827; font-weight:600; cursor:pointer; transition: all .15s ease; }
                    .role-btn:hover, .role-btn:focus { background: var(--primary-red); color:#fff; border-color: var(--primary-red); outline:none; box-shadow: 0 0 0 3px rgba(208,0,0,.12); }
                `;
                document.head.appendChild(style);
            }
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'rgba(0,0,0,0.5)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '2000';

            const panel = document.createElement('div');
            panel.className = 'role-modal';
            panel.style.background = '#fff';
            panel.style.width = '95%';
            panel.style.maxWidth = '420px';

            panel.innerHTML = `
                <h3>Select Your Role</h3>
                <p>Please select your registrant type to complete your first-time login.</p>
                <div class="role-actions">
                    <button data-role="student" class="role-btn">Student</button>
                    <button data-role="staff" class="role-btn">Staff</button>
                </div>
                <div id="roleIdBlock" style="margin-top:12px; display:none;">
                    <input id="roleIdInput" type="text" placeholder="Enter your ID" style="width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:10px;">
                    <div id="roleHint" style="font-size:12px; color:#6b7280; margin-top:6px;"></div>
                    <div id="roleMsg" style="font-size:13px; margin-top:8px; display:none;"></div>
                    <button id="roleContinue" class="role-btn" style="margin-top:10px; background: var(--primary-red); color:#fff; border-color: var(--primary-red);">Continue</button>
                </div>
            `;

            overlay.appendChild(panel);
            document.body.appendChild(overlay);

            const roleIdBlock = panel.querySelector('#roleIdBlock');
            const roleIdInput = panel.querySelector('#roleIdInput');
            const roleHint = panel.querySelector('#roleHint');
            const roleMsg = panel.querySelector('#roleMsg');
            const continueBtn = panel.querySelector('#roleContinue');

            // Restore last entered input/role if available (sessionStorage first, then localStorage)
            try {
                const saved = sessionStorage.getItem('pending_role_selection_state');
                if (saved) {
                    const s = JSON.parse(saved);
                    if (s && s.identifier) {
                        roleIdInput.value = s.identifier;
                    }
                    if (s && s.role) {
                        currentRole = s.role;
                        roleIdBlock.style.display = 'block';
                        setHint(currentRole);
                    }
                } else if (userId) {
                    const c = loadUserCache(userId);
                    if (c) {
                        if (c.identifier) roleIdInput.value = c.identifier;
                        if (c.role) {
                            currentRole = c.role;
                            roleIdBlock.style.display = 'block';
                            setHint(currentRole);
                        }
                    }
                }
            } catch (_) {}

            const rules = {
                student: /^\d{6}$/,                 // exactly 6 digits
                staff: /^[A-Za-z0-9]{5}$/            // exactly 5 alphanumeric (e.g., C1234)
            };

            let currentRole = null;

            const setHint = (role) => {
                if (role === 'student') {
                    roleHint.textContent = 'Enter 6-digit Student Reg No (e.g., 123456)';
                    roleIdInput.placeholder = 'Enter 6-digit Student Reg No';
                } else if (role === 'staff') {
                    roleHint.textContent = 'Enter 5-character Staff Reg No (alphanumeric, e.g., C1234)';
                    roleIdInput.placeholder = 'Enter 5-character Staff Reg No';
                }
            };

            panel.querySelectorAll('button[data-role]').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentRole = btn.getAttribute('data-role');
                    roleIdBlock.style.display = 'block';
                    roleMsg.style.display = 'none';
                    roleIdInput.value = '';
                    setHint(currentRole);
                    setTimeout(() => roleIdInput.focus(), 50);
                });
            });

            const trySubmit = () => {
                // Ensure we have a temp user id; try to recover from sessionStorage
                let effectiveUserId = userId;
                if (!effectiveUserId) {
                    try {
                        const pr = sessionStorage.getItem('pending_role_selection');
                        if (pr) {
                            const obj = JSON.parse(pr);
                            if (obj && obj.tempUserId) effectiveUserId = obj.tempUserId;
                        }
                    } catch (_) {}
                }
                if (!effectiveUserId) {
                    roleMsg.style.display = 'block';
                    roleMsg.style.color = '#991b1b';
                    roleMsg.textContent = 'Session expired. Please sign in again.';
                    return;
                }
                if (!currentRole) {
                    roleMsg.style.display = 'block';
                    roleMsg.style.color = '#991b1b';
                    roleMsg.textContent = 'Please select your role before continuing.';
                    return;
                }
                const role = currentRole;
                const idVal = (roleIdInput.value || '').trim();
                const regex = rules[role];
                const ok = regex.test(idVal);
                roleMsg.style.display = 'block';
                roleMsg.style.color = ok ? '#065f46' : '#991b1b';
                if (!ok) {
                    const msg = role === 'student'
                        ? 'You must enter a valid 6-digit Student Reg No before continuing.'
                        : role === 'staff'
                            ? 'You must enter a valid 5-character Staff ID (letters/numbers) before continuing.'
                            : 'You must enter a valid email address for Guest access before continuing.';
                    roleMsg.textContent = msg;
                } else {
                    roleMsg.textContent = 'Looks good.';
                }
                if (!ok) {
                    return; // do not proceed on invalid input
                }
                // Persist current role and identifier so we can restore on failure or reload
                try { sessionStorage.setItem('pending_role_selection_state', JSON.stringify({ role, identifier: idVal })); } catch (_) {}
                // Also save a UX cache for longer-lived restore
                try { saveUserCache(effectiveUserId, { role, identifier: idVal }); } catch (_) {}
                finalizeRole(effectiveUserId, role, overlay, { identifier: idVal });
            };

            continueBtn.addEventListener('click', trySubmit);
            roleIdInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); trySubmit(); } });
        }

        function finalizeRole(userId, role, overlay, extra = {}) {
            const payload = Object.assign({ temp_user_id: userId, registrantType: role }, extra);
            fetch('finalize_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data && (data.success || data.status === 'success')) {
                    // Clear persisted pending context
                    try {
                        sessionStorage.removeItem('pending_role_selection');
                        sessionStorage.removeItem('pending_role_selection_state');
                        clearUserCache(userId);
                    } catch (_) {}
                    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    window.location.href = data.redirect || 'user-dashboard.php';
                } else {
                    roleMsg.style.display = 'block';
                    roleMsg.style.color = '#991b1b';
                    roleMsg.textContent = (data && (data.message || data.error)) || 'Failed to save selection. Please try again.';
                }
            })
            .catch(err => {
                roleMsg.style.display = 'block';
                roleMsg.style.color = '#991b1b';
                roleMsg.textContent = 'Failed to save selection. Please try again.';
            });
        }
    </script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</body>
</html> 