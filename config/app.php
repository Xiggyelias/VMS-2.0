<?php
/**
 * Application Configuration
 * 
 * This file contains all application-level configuration settings.
 * Centralized configuration makes it easier to manage system settings
 * and switch between different environments.
 */

// Load environment variables
require_once __DIR__ . '/env.php';

// Application Information
define('APP_NAME', env('APP_NAME', 'Vehicle Registration System'));
define('APP_VERSION', env('APP_VERSION', '2.0.0'));
define('APP_DESCRIPTION', env('APP_DESCRIPTION', 'AU Vehicle Registration and Management System'));

// File Paths
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('VIEWS_PATH', BASE_PATH . '/views');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// URL Configuration
define('BASE_URL', env('BASE_URL', 'http://localhost/system/frontend'));
define('ASSETS_URL', BASE_URL . '/assets');

// Session Configuration
define('SESSION_NAME', env('SESSION_NAME', 'vehicle_registration_session'));
define('SESSION_LIFETIME', env_int('SESSION_LIFETIME', 3600)); // 1 hour
define('SESSION_PATH', '/');
define('SESSION_DOMAIN', '');
define('SESSION_SECURE', env_bool('SESSION_SECURE', false));
define('SESSION_HTTP_ONLY', env_bool('SESSION_HTTP_ONLY', true));

// Security Configuration
define('PASSWORD_MIN_LENGTH', env_int('PASSWORD_MIN_LENGTH', 8));
define('PASSWORD_REQUIRE_SPECIAL', true);
define('LOGIN_MAX_ATTEMPTS', env_int('LOGIN_MAX_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_TIME', env_int('LOGIN_LOCKOUT_TIME', 900)); // 15 minutes

// Email Configuration (for password reset)
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env_int('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', 'your-email@gmail.com'));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', 'your-app-password'));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'noreply@au.ac.zw'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'AU Vehicle Registration System'));

// Vehicle Registration Settings
define('MAX_VEHICLES_PER_STUDENT', env_int('MAX_VEHICLES_PER_STUDENT', 1));
define('MAX_VEHICLES_PER_STAFF', env_int('MAX_VEHICLES_PER_STAFF', 5));
define('MAX_VEHICLES_PER_GUEST', env_int('MAX_VEHICLES_PER_GUEST', 3));
define('VEHICLE_REGISTRATION_EXPIRY_DAYS', env_int('VEHICLE_REGISTRATION_EXPIRY_DAYS', 365));

// File Upload Settings
define('MAX_FILE_SIZE', env_int('MAX_FILE_SIZE', 5 * 1024 * 1024)); // 5MB
define('ALLOWED_IMAGE_TYPES', env_array('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']));
define('UPLOAD_PATH', UPLOADS_PATH . '/vehicles');

// Pagination Settings
define('ITEMS_PER_PAGE', env_int('ITEMS_PER_PAGE', 20));
define('MAX_PAGES_DISPLAY', env_int('MAX_PAGES_DISPLAY', 5));

// Notification Settings
define('NOTIFICATION_SOUND_FILE', 'notification.mp3');
define('NOTIFICATION_AUTO_HIDE_DELAY', 5000); // 5 seconds

// Error Reporting (set to false in production)
define('DISPLAY_ERRORS', env_bool('DISPLAY_ERRORS', true));
define('LOG_ERRORS', env_bool('LOG_ERRORS', true));
define('ERROR_LOG_FILE', BASE_PATH . '/logs/error.log');

// Development/Production Mode
define('APP_ENV', env('APP_ENV', 'development')); // Change to 'production' for live site

// Initialize error reporting based on environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/**
 * Get Configuration Value
 * 
 * Safely retrieves a configuration value with optional default.
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if key doesn't exist
 * @return mixed Configuration value
 */
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

/**
 * Check if Application is in Development Mode
 * 
 * @return bool True if in development mode
 */
function isDevelopment() {
    return APP_ENV === 'development';
}

/**
 * Check if Application is in Production Mode
 * 
 * @return bool True if in production mode
 */
function isProduction() {
    return APP_ENV === 'production';
} 

// Google OAuth configuration
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', '561037470081-3fs3roso7v8gnq9idijoap15tn7sqr3l.apps.googleusercontent.com'));
}
if (!defined('ALLOWED_GOOGLE_DOMAIN')) {
    define('ALLOWED_GOOGLE_DOMAIN', env('ALLOWED_GOOGLE_DOMAIN', 'africau.edu'));
}