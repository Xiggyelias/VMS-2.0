<?php
/**
 * Security Configuration
 * 
 * This file contains all security-related configuration settings.
 * Implements Laravel-level security standards for the Vehicle Registration System.
 */

// Security Headers Configuration
define('SECURITY_HEADERS', [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://accounts.google.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://accounts.google.com; img-src 'self' data: https: https://accounts.google.com; font-src 'self' data: https://cdnjs.cloudflare.com; connect-src 'self' https://accounts.google.com https://oauth2.googleapis.com; frame-src 'self' https://accounts.google.com; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self';",
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload'
]);

// Session Security Configuration
define('SESSION_SECURITY', [
    'name' => 'vehicle_registration_session',
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '',
    'secure' => true, // Set to true in production
    'httponly' => true,
    'samesite' => 'Strict',
    'regenerate_id' => true,
    'gc_maxlifetime' => 3600,
    'gc_probability' => 1,
    'gc_divisor' => 100
]);

// Password Security Configuration
define('PASSWORD_SECURITY', [
    'min_length' => 12,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_special_chars' => true,
    'max_age_days' => 90,
    'history_count' => 5,
    'algorithm' => PASSWORD_ARGON2ID, // Use Argon2id for maximum security
    'options' => [
        'memory_cost' => 65536, // 64MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 3          // 3 threads
    ]
]);

// CSRF Protection Configuration
define('CSRF_SECURITY', [
    'token_length' => 64,
    'token_name' => '_token',
    'expire_time' => 3600, // 1 hour
    'regenerate_on_login' => true,
    'exempt_routes' => [
        '/api/webhook/*',
        '/health-check',
        '/login.php',
        '/admin-login.php',
        '/forgot_password.php',
        '/process-reset.php',
        '/send-reset.php',
        '/google_auth.php',
        '/registration-form.php',
        '/submit_registration.php',
        '/save_registration_draft.php',
        '/get_registration_draft.php'
    ]
]);

// Rate Limiting Configuration
define('RATE_LIMITING', [
    'enabled' => true,
    'login_attempts' => [
        'max_attempts' => 5,
        'decay_minutes' => 15,
        'lockout_minutes' => 30
    ],
    'api_requests' => [
        'max_requests' => 100,
        'decay_minutes' => 1
    ],
    'file_uploads' => [
        'max_uploads' => 10,
        'decay_minutes' => 60
    ]
]);

// File Upload Security Configuration
define('FILE_UPLOAD_SECURITY', [
    'max_size' => 5 * 1024 * 1024, // 5MB
    'allowed_types' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx'],
        'archive' => ['zip', 'rar']
    ],
    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-rar-compressed'
    ],
    'upload_path' => UPLOADS_PATH . '/secure',
    'randomize_names' => true,
    'scan_virus' => true, // Enable virus scanning if available
    'validate_content' => true
]);

// Input Validation Rules
define('VALIDATION_RULES', [
    'email' => [
        'required' => true,
        'type' => 'email',
        'max_length' => 255,
        'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
    ],
    'phone' => [
        'required' => true,
        'pattern' => '/^(\+263|0)[78]\d{7}$/',
        'max_length' => 15
    ],
    'plate_number' => [
        'required' => true,
        'pattern' => '/^[A-Z]{2,3}\s?\d{3,4}$/',
        'max_length' => 10
    ],
    'id_number' => [
        'required' => true,
        'pattern' => '/^\d{2}-\d{6,7}[A-Z]\d{2}$/',
        'max_length' => 15
    ],
    'license_number' => [
        'required' => true,
        'pattern' => '/^[A-Z]{2}\d{6}$/',
        'max_length' => 10
    ]
]);

// SQL Injection Prevention
define('SQL_SECURITY', [
    'use_prepared_statements' => true,
    'escape_quotes' => true,
    'validate_identifiers' => true,
    'max_query_time' => 30, // seconds
    'log_slow_queries' => true,
    'slow_query_threshold' => 5 // seconds
]);

// XSS Protection Configuration
define('XSS_PROTECTION', [
    'escape_output' => true,
    'strip_tags' => false, // Use htmlspecialchars instead
    'allowed_tags' => [], // No HTML tags allowed by default
    'encoding' => 'UTF-8',
    'double_encode' => false
]);

// Access Control Configuration
define('ACCESS_CONTROL', [
    'default_role' => 'student',
    'roles' => [
        // guest removed
        'student' => [
            'permissions' => ['view_own_vehicles', 'manage_own_vehicles', 'manage_drivers'],
            'max_vehicles' => 1
        ],
        'staff' => [
            'permissions' => ['view_own_vehicles', 'manage_own_vehicles', 'manage_drivers'],
            'max_vehicles' => 5
        ],
        'admin' => [
            'permissions' => ['*'], // All permissions
            'max_vehicles' => -1 // Unlimited
        ]
    ],
    'permissions' => [
        'view_public' => 'View public information',
        'register' => 'Register new account',
        'view_own_vehicles' => 'View own vehicles',
        'manage_own_vehicles' => 'Manage own vehicles',
        'manage_drivers' => 'Manage authorized drivers',
        'view_all_vehicles' => 'View all vehicles',
        'manage_all_vehicles' => 'Manage all vehicles',
        'manage_users' => 'Manage users',
        'view_reports' => 'View reports',
        'manage_system' => 'Manage system settings'
    ]
]);

// Error Handling Configuration
define('ERROR_HANDLING', [
    'display_errors' => false, // Never show errors to users
    'log_errors' => true,
    'log_level' => 'ERROR',
    'error_log_file' => BASE_PATH . '/logs/error.log',
    'security_log_file' => BASE_PATH . '/logs/security.log',
    'custom_error_pages' => true
]);

// Audit Logging Configuration
define('AUDIT_LOGGING', [
    'enabled' => true,
    'log_file' => BASE_PATH . '/logs/audit.log',
    'events' => [
        'login_success',
        'login_failure',
        'logout',
        'password_change',
        'vehicle_registration',
        'vehicle_update',
        'vehicle_deletion',
        'driver_assignment',
        'admin_action',
        'file_upload',
        'data_export'
    ],
    'retention_days' => 365
]);

// Encryption Configuration
define('ENCRYPTION', [
    'algorithm' => 'AES-256-GCM',
    'key_length' => 32,
    'iv_length' => 16,
    'tag_length' => 16,
    'key_file' => BASE_PATH . '/config/encryption.key'
]);

// Backup Security Configuration
define('BACKUP_SECURITY', [
    'encrypt_backups' => true,
    'backup_retention_days' => 30,
    'backup_path' => BASE_PATH . '/backups',
    'exclude_files' => [
        '*.log',
        '*.tmp',
        'uploads/*'
    ]
]);

// Rate Limiting Security Configuration
define('RATE_LIMITING_SECURITY', [
    'enabled' => true,
    'max_requests_per_minute' => 60,
    'max_requests_per_hour' => 1000,
    'max_login_attempts' => 5,
    'lockout_duration' => 900, // 15 minutes
    'reset_after' => 3600, // 1 hour
    'storage' => 'session', // or 'database'
    'log_violations' => true
]);

/**
 * Initialize Security Settings
 * 
 * Applies all security configurations to the current request.
 */
function initializeSecurity() {
    // Set security headers
    foreach (SECURITY_HEADERS as $header => $value) {
        header("$header: $value");
    }
    
    // Configure session security only if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        $sessionConfig = SESSION_SECURITY;
        session_name($sessionConfig['name']);
        session_set_cookie_params(
            $sessionConfig['lifetime'],
            $sessionConfig['path'],
            $sessionConfig['domain'],
            $sessionConfig['secure'],
            $sessionConfig['httponly']
        );
        
        // Set session security options
        ini_set('session.cookie_samesite', $sessionConfig['samesite']);
        ini_set('session.gc_maxlifetime', $sessionConfig['gc_maxlifetime']);
        ini_set('session.gc_probability', $sessionConfig['gc_probability']);
        ini_set('session.gc_divisor', $sessionConfig['gc_divisor']);
        
        // Start session
        session_start();
    }
    
    // Regenerate session ID if needed (only if session is active)
    if (session_status() === PHP_SESSION_ACTIVE) {
        $sessionConfig = SESSION_SECURITY;
        if ($sessionConfig['regenerate_id'] && !isset($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
        }
    }
    
    // Set error handling
    $errorConfig = ERROR_HANDLING;
    ini_set('display_errors', $errorConfig['display_errors'] ? '1' : '0');
    ini_set('log_errors', $errorConfig['log_errors'] ? '1' : '0');
    ini_set('error_log', $errorConfig['error_log_file']);
    
    // Create log directories if they don't exist
    $logDir = dirname($errorConfig['error_log_file']);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
}

/**
 * Get Security Configuration
 * 
 * @param string $section Configuration section name
 * @return array Configuration array
 */
function getSecurityConfig($section) {
    $constant_name = strtoupper($section) . '_SECURITY';
    return defined($constant_name) ? constant($constant_name) : [];
}

/**
 * Check if Security Feature is Enabled
 * 
 * @param string $feature Feature name
 * @return bool True if enabled
 */
function isSecurityFeatureEnabled($feature) {
    $config = getSecurityConfig($feature);
    return $config['enabled'] ?? false;
} 