<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// CSRF (if needed for future interactive elements)
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome | Vehicle Registration System</title>
    <?php includeCommonAssets(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        body { background: linear-gradient(135deg, #ffffff 0%, #f6f8ff 50%, #fff4f4 100%); }
        .site-header { background: linear-gradient(90deg, rgba(208,0,0,1) 0%, rgba(176,0,0,1) 100%); color: #fff; }
        .nav { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 0; }
        .brand { display: flex; align-items: center; gap: 0.75rem; }
        .brand img { width: 56px; height: auto; }
        .brand-name { font-weight: 700; letter-spacing: .3px; }

        .nav-links { display: flex; gap: .5rem; align-items: center; }
        .nav-links a { text-decoration: none; }
        .btn-outline { background: transparent; border: 2px solid #fff; color: #fff; padding: .5rem 1rem; border-radius: 8px; transition: all .2s ease; }
        .btn-outline:hover { background: #fff; color: var(--primary-red); }

        .hero { position: relative; padding: 4rem 0 3rem; color: #1f2937; }
        .hero-card { background: #fff; border: 1px solid #eef1f4; border-radius: 16px; box-shadow: 0 16px 40px rgba(0,0,0,.08); padding: 2rem; }
        .hero h1 { font-size: 2.25rem; line-height: 1.2; margin-bottom: .75rem; color: #111827; }
        .hero p { color: #6b7280; margin-bottom: 1.25rem; }
        .hero-ctas { display: flex; gap: .75rem; flex-wrap: wrap; }
        .btn-lg { padding: .9rem 1.25rem; border-radius: 10px; font-weight: 600; }

        .features { margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .feature { background: #fff; border: 1px solid #eef1f4; border-radius: 12px; padding: 1.25rem; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
        .feature h3 { margin: .5rem 0 .25rem; color: #111827; font-size: 1.1rem; }
        .feature p { color: #6b7280; font-size: .95rem; }
        .feature .icon { color: var(--primary-red); font-size: 1.4rem; }

        .how { margin: 3rem 0 0; }
        .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .step { background: #fff; border: 1px solid #eef1f4; border-radius: 12px; padding: 1.25rem; }
        .step .num { background: #fde8e8; color: var(--primary-red); font-weight: 700; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; margin-bottom: .5rem; }

        .site-footer { margin-top: 3rem; padding: 1.25rem 0; color: #6b7280; font-size: .95rem; }

        @media (max-width: 768px) {
            .hero { padding: 2rem 0 1.5rem; }
            .hero h1 { font-size: 1.75rem; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <nav class="nav">
                <div class="brand">
                    <a href="index.php" class="header-logo" aria-label="Home">
                        <img src="assets/images/AULogo.png" alt="AU Logo" />
                    </a>
                    <div class="brand-name">Vehicle Registration System</div>
                </div>
                <div class="nav-links">
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="registration-form.php" class="btn btn-logout" style="border-color:#fff;">Register</a>
                    <a href="admin-login.php" class="btn btn-outline" title="Admin Login"><i class="fa fa-user-shield"></i></a>
                </div>
            </nav>
        </div>
    </header>

    <main class="hero">
        <div class="container">
            <div class="hero-card">
                <h1>Register and manage your campus vehicle access with ease</h1>
                <p>Fast, secure, and convenient vehicle registration for students and staff. Manage vehicles and authorized drivers in one place.</p>
                <div class="hero-ctas">
                    <a href="registration-form.php" class="btn btn-primary btn-lg"><i class="fa fa-clipboard-check"></i> Get Started</a>
                    <a href="login.php" class="btn btn-secondary btn-lg"><i class="fa fa-right-to-bracket"></i> Login</a>
                </div>

                <div class="features">
                    <div class="feature">
                        <div class="icon"><i class="fa fa-bolt"></i></div>
                        <h3>Quick Registration</h3>
                        <p>Complete a guided form and submit your details in minutes with clear prompts.</p>
                    </div>
                    <div class="feature">
                        <div class="icon"><i class="fa fa-shield-halved"></i></div>
                        <h3>Secure Access</h3>
                        <p>Built-in security and session protection keep your information safe.</p>
                    </div>
                    <div class="feature">
                        <div class="icon"><i class="fa fa-car-side"></i></div>
                        <h3>Vehicle Management</h3>
                        <p>Add, edit, and manage authorized drivers for your vehicles.</p>
                    </div>
                </div>

                <section class="how">
                    <h2 style="color: var(--primary-red); margin-bottom: .75rem;">How it works</h2>
                    <div class="steps">
                        <div class="step">
                            <div class="num">1</div>
                            <h4>Create your account</h4>
                            <p>Choose your role (student or staff) and register your account.</p>
                        </div>
                        <div class="step">
                            <div class="num">2</div>
                            <h4>Register your vehicle</h4>
                            <p>Provide vehicle details and add authorized drivers if needed.</p>
                        </div>
                        <div class="step">
                            <div class="num">3</div>
                            <h4>Manage anytime</h4>
                            <p>Log in to update details, add vehicles, or manage drivers.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
            <div>© <?= date('Y') ?> AU Vehicle Registration System</div>
            <div style="display:flex; gap:.75rem; align-items:center;">
                <a href="login.php" class="btn btn-secondary" style="text-decoration:none; padding:.5rem .85rem;">Login</a>
                <a href="registration-form.php" class="btn btn-primary" style="text-decoration:none; padding:.5rem .85rem;">Register</a>
            </div>
        </div>
    </footer>
</body>
</html>