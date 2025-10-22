<?php
/**
 * Utility Functions
 * 
 * This file contains common utility functions used throughout the application.
 * These functions provide reusable functionality for common tasks like
 * input sanitization, validation, formatting, and security.
 */

require_once CONFIG_PATH . '/app.php';

/**
 * Sanitize Input Data
 * 
 * Cleans and sanitizes user input to prevent XSS attacks and ensure data integrity.
 * 
 * @param mixed $data Input data to sanitize
 * @param string $type Type of sanitization ('string', 'email', 'int', 'float')
 * @return mixed Sanitized data
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $data);
    }
    
    switch ($type) {
        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var(trim($data), FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate Email Address
 * 
 * Checks if the provided email address is valid.
 * 
 * @param string $email Email address to validate
 * @return bool True if email is valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Phone Number
 * 
 * Validates phone number format (basic validation for Zimbabwe numbers).
 * 
 * @param string $phone Phone number to validate
 * @return bool True if phone number is valid
 */
function isValidPhone($phone) {
    // Remove spaces, dashes, and parentheses
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // Check if it's a valid Zimbabwe phone number
    // +263 or 0 followed by 7 or 8 digits
    return preg_match('/^(\+263|0)[78]\d{7}$/', $phone);
}

/**
 * Format Phone Number
 * 
 * Formats phone number to standard Zimbabwe format.
 * 
 * @param string $phone Phone number to format
 * @return string Formatted phone number
 */
function formatPhoneNumber($phone) {
    // Remove all non-digit characters except +
    $phone = preg_replace('/[^\d\+]/', '', $phone);
    
    // If starts with 0, replace with +263
    if (preg_match('/^0/', $phone)) {
        $phone = '+263' . substr($phone, 1);
    }
    
    // If doesn't start with +, add +263
    if (!preg_match('/^\+/', $phone)) {
        $phone = '+263' . $phone;
    }
    
    return $phone;
}

/**
 * Generate Random String
 * 
 * Generates a cryptographically secure random string.
 * 
 * @param int $length Length of the string to generate
 * @param string $characters Characters to use (default: alphanumeric)
 * @return string Random string
 */
function generateRandomString($length = 32, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $string = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[random_int(0, $max)];
    }
    
    return $string;
}

/**
 * Generate Secure Token
 * 
 * Generates a secure token for password reset, CSRF protection, etc.
 * 
 * @param int $length Length of the token
 * @return string Secure token
 */
function generateSecureToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash Password
 * 
 * Creates a secure hash of the password using PHP's password_hash function.
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify Password
 * 
 * Verifies a password against its hash.
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validate Password Strength
 * 
 * Checks if password meets security requirements.
 * 
 * @param string $password Password to validate
 * @return array Array with 'valid' boolean and 'errors' array
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    }
    
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Format Date
 * 
 * Formats a date string to a readable format.
 * 
 * @param string $date Date string
 * @param string $format Output format (default: 'F j, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) {
        return 'N/A';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 'Invalid Date';
    }
    
    return date($format, $timestamp);
}

/**
 * Format DateTime
 * 
 * Formats a datetime string to a readable format.
 * 
 * @param string $datetime Datetime string
 * @param string $format Output format (default: 'F j, Y g:i A')
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid Date/Time';
    }
    
    return date($format, $timestamp);
}

/**
 * Get Time Ago
 * 
 * Returns a human-readable time ago string.
 * 
 * @param string $datetime Datetime string
 * @return string Time ago string
 */
function getTimeAgo($datetime) {
    if (empty($datetime)) {
        return 'Unknown';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Invalid Date';
    }
    
    $timeDiff = time() - $timestamp;
    
    if ($timeDiff < 60) {
        return 'Just now';
    } elseif ($timeDiff < 3600) {
        $minutes = floor($timeDiff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($timeDiff < 86400) {
        $hours = floor($timeDiff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($timeDiff < 2592000) {
        $days = floor($timeDiff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Redirect to URL
 * 
 * Performs a redirect to the specified URL.
 * 
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code (default: 302)
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit();
}

/**
 * Get Current URL
 * 
 * Returns the current page URL.
 * 
 * @return string Current URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return $protocol . '://' . $host . $uri;
}

/**
 * Get Base URL
 * 
 * Returns the base URL of the application.
 * 
 * @return string Base URL
 */
function getBaseUrl() {
    return BASE_URL;
}

/**
 * Log Error
 * 
 * Logs an error message to the error log file.
 * 
 * @param string $message Error message
 * @param string $level Error level (ERROR, WARNING, INFO)
 * @param array $context Additional context data
 */
function logError($message, $level = 'ERROR', $context = []) {
    if (!LOG_ERRORS) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    
    if (!empty($context)) {
        $logMessage .= ' Context: ' . json_encode($context);
    }
    
    $logMessage .= PHP_EOL;
    
    $logDir = dirname(ERROR_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(ERROR_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Display Error Message
 * 
 * Displays an error message in a user-friendly format.
 * 
 * @param string $message Error message
 * @param bool $log Whether to log the error
 */
function displayError($message, $log = true) {
    if ($log) {
        logError($message);
    }
    
    if (DISPLAY_ERRORS) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>';
    } else {
        echo '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
    }
}

/**
 * Display Success Message
 * 
 * Displays a success message in a user-friendly format.
 * 
 * @param string $message Success message
 */
function displaySuccess($message) {
    echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display Warning Message
 * 
 * Displays a warning message in a user-friendly format.
 * 
 * @param string $message Warning message
 */
function displayWarning($message) {
    echo '<div class="alert alert-warning">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display Info Message
 * 
 * Displays an info message in a user-friendly format.
 * 
 * @param string $message Info message
 */
function displayInfo($message) {
    echo '<div class="alert alert-info">' . htmlspecialchars($message) . '</div>';
} 