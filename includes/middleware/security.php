<?php
/**
 * Security Middleware
 * 
 * This file contains security middleware functions that implement
 * Laravel-level security standards for the Vehicle Registration System.
 */

require_once CONFIG_PATH . '/security.php';
require_once INCLUDES_PATH . '/functions/utilities.php';

/**
 * Security Middleware Class
 * 
 * Handles all security-related middleware operations including
 * authentication, authorization, input validation, and attack prevention.
 */
class SecurityMiddleware {
    
    /**
     * Initialize Security for Request
     * 
     * Applies all security measures at the beginning of each request.
     */
    public static function initialize() {
        // Initialize security settings
        initializeSecurity();
        
        // Apply rate limiting
        self::applyRateLimiting();
        
        // Validate CSRF token for POST requests (with exemptions)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::validateCSRF();
        }
        
        // Log security events
        self::logSecurityEvent('request_start', [
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI']
        ]);
    }
    
    /**
     * Apply Rate Limiting
     * 
     * Implements rate limiting for login attempts and API requests.
     */
    public static function applyRateLimiting() {
        $config = getSecurityConfig('rate_limiting');
        
        // Skip if rate limiting is disabled
        if (!($config['enabled'] ?? true)) {
            return;
        }
        
        $clientIP = self::getClientIP();
        $currentTime = time();
        
        // Check login attempts
        $loginAttempts = self::getRateLimitData($clientIP, 'login_attempts');
        if (count($loginAttempts) >= $config['max_login_attempts']) {
            $oldestAttempt = min($loginAttempts);
            $lockoutTime = $config['lockout_duration'];
            
            if (($currentTime - $oldestAttempt) < $lockoutTime) {
                self::logSecurityEvent('rate_limit_exceeded', [
                    'ip' => $clientIP,
                    'type' => 'login_attempts',
                    'attempts' => count($loginAttempts)
                ]);
                
                http_response_code(429);
                die('Too many login attempts. Please try again later.');
            } else {
                // Reset attempts after lockout period
                self::clearRateLimitData($clientIP, 'login_attempts');
            }
        }
        
        // Check API requests (using per-minute limit)
        $apiRequests = self::getRateLimitData($clientIP, 'api_requests');
        if (count($apiRequests) >= $config['max_requests_per_minute']) {
            $oldestRequest = min($apiRequests);
            $decayTime = 60; // 1 minute
            
            if (($currentTime - $oldestRequest) < $decayTime) {
                http_response_code(429);
                die('Rate limit exceeded. Please try again later.');
            } else {
                self::clearRateLimitData($clientIP, 'api_requests');
            }
        }
    }
    
    /**
     * Validate CSRF Token
     * 
     * Validates CSRF tokens for POST requests to prevent CSRF attacks.
     */
    public static function validateCSRF() {
        $config = getSecurityConfig('csrf');
        $tokenName = $config['token_name'];
        
        // Skip validation for exempt routes
        $currentRoute = $_SERVER['REQUEST_URI'];
        
        // Clean the route (remove query parameters)
        $currentRoute = parse_url($currentRoute, PHP_URL_PATH);
        
        // Explicitly check for login-related routes and public forms
        $exemptRoutes = [
            '/login.php',
            '/admin-login.php',
            '/forgot_password.php',
            '/process-reset.php',
            '/send-reset.php',
            '/registration-form.html',
            '/registration-form.php',
            '/submit_registration.php',
            '/google_auth.php',
            '/save_registration_draft.php',
            '/get_registration_draft.php'
        ];
        
        // Check if current route is exempt (handle subdirectory paths)
        foreach ($exemptRoutes as $exemptRoute) {
            if ($currentRoute === $exemptRoute || str_ends_with($currentRoute, $exemptRoute)) {
                return; // Skip CSRF validation for exempt routes
            }
        }
        
        // Check exempt routes from configuration (for pattern matching)
        foreach ($config['exempt_routes'] as $exemptRoute) {
            if (self::routeMatches($currentRoute, $exemptRoute)) {
                return;
            }
        }
        
        // Check if token exists
        $token = $_POST[$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token) {
            self::logSecurityEvent('csrf_missing_token', [
                'ip' => self::getClientIP(),
                'route' => $currentRoute
            ]);
            http_response_code(419);
            // Return JSON for AJAX/JSON requests
            $expectsJson = (
                (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            );
            if ($expectsJson) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
                exit;
            }
            die('CSRF token missing. Please refresh the page and try again.');
        }
        
        // Validate token
        if (!self::verifyCSRFToken($token)) {
            self::logSecurityEvent('csrf_invalid_token', [
                'ip' => self::getClientIP(),
                'route' => $currentRoute,
                'provided_token' => substr($token, 0, 10) . '...'
            ]);
            http_response_code(419);
            // Return JSON for AJAX/JSON requests
            $expectsJson = (
                (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            );
            if ($expectsJson) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
                exit;
            }
            die('CSRF token invalid. Please refresh the page and try again.');
        }
    }
    
    /**
     * Generate CSRF Token
     * 
     * Generates a new CSRF token and stores it in session.
     * 
     * @return string CSRF token
     */
    public static function generateCSRFToken() {
        $config = getSecurityConfig('csrf');
        
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        // Clean expired tokens
        $currentTime = time();
        $_SESSION['csrf_tokens'] = array_filter(
            $_SESSION['csrf_tokens'],
            function($tokenData) use ($currentTime, $config) {
                return ($currentTime - $tokenData['created']) < $config['expire_time'];
            }
        );
        
        // Generate new token
        $token = bin2hex(random_bytes($config['token_length'] / 2));
        $_SESSION['csrf_tokens'][$token] = [
            'created' => $currentTime,
            'used' => false
        ];
        
        return $token;
    }
    
    /**
     * Verify CSRF Token
     * 
     * Verifies that the provided token is valid and not expired.
     * 
     * @param string $token Token to verify
     * @return bool True if token is valid
     */
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        $tokenData = $_SESSION['csrf_tokens'][$token];
        $config = getSecurityConfig('csrf');
        
        // Check if token is expired
        if ((time() - $tokenData['created']) > $config['expire_time']) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Mark token as used (optional, for additional security)
        $_SESSION['csrf_tokens'][$token]['used'] = true;
        
        return true;
    }
    
    /**
     * Validate Input Data
     * 
     * Validates and sanitizes input data according to defined rules.
     * 
     * @param array $data Input data to validate
     * @param array $rules Validation rules
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validateInput($data, $rules) {
        $errors = [];
        $validated = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Check required fields
            if (($rule['required'] ?? false) && empty($value)) {
                $errors[$field][] = "The $field field is required.";
                continue;
            }
            
            // Skip validation for empty optional fields
            if (empty($value)) {
                continue;
            }
            
            // Validate field type
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "The $field must be a valid email address.";
                        }
                        break;
                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field][] = "The $field must be an integer.";
                        }
                        break;
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field][] = "The $field must be a number.";
                        }
                        break;
                }
            }
            
            // Validate length
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field][] = "The $field may not be greater than {$rule['max_length']} characters.";
            }
            
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field][] = "The $field must be at least {$rule['min_length']} characters.";
            }
            
            // Validate pattern
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field][] = "The $field format is invalid.";
            }
            
            // Sanitize value if no errors
            if (!isset($errors[$field])) {
                $validated[$field] = self::sanitizeInput($value, $rule['type'] ?? 'string');
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
    
    /**
     * Sanitize Input
     * 
     * Sanitizes input data to prevent XSS and other attacks.
     * 
     * @param mixed $input Input to sanitize
     * @param string $type Type of sanitization
     * @return mixed Sanitized input
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Escape Output
     * 
     * Escapes output to prevent XSS attacks.
     * 
     * @param string $output Output to escape
     * @param string $context Output context (html, js, css, url)
     * @return string Escaped output
     */
    public static function escapeOutput($output, $context = 'html') {
        switch ($context) {
            case 'html':
                return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
            case 'js':
                return json_encode($output);
            case 'css':
                return preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $output);
            case 'url':
                return urlencode($output);
            default:
                return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate File Upload
     * 
     * Validates uploaded files for security.
     * 
     * @param array $file Uploaded file array
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validateFileUpload($file) {
        $errors = [];
        $config = getSecurityConfig('file_upload');
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed with error code: " . $file['error'];
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > $config['max_size']) {
            $errors[] = "File size exceeds maximum allowed size of " . formatBytes($config['max_size']);
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = $file['type'];
        
        $allowedExtensions = array_merge(...array_values($config['allowed_types']));
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowedExtensions);
        }
        
        if (!in_array($mimeType, $config['allowed_mimes'])) {
            $errors[] = "MIME type not allowed.";
        }
        
        // Validate file content (basic check)
        if ($config['validate_content']) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($detectedMime, $config['allowed_mimes'])) {
                $errors[] = "File content validation failed.";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Secure File Upload
     * 
     * Securely handles file uploads with proper naming and storage.
     * 
     * @param array $file Uploaded file array
     * @param string $directory Upload directory
     * @return array Array with 'success' boolean and 'path' string
     */
    public static function secureFileUpload($file, $directory = null) {
        $config = getSecurityConfig('file_upload');
        
        // Validate file
        $validation = self::validateFileUpload($file);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        // Create secure upload directory
        $uploadDir = $directory ?: $config['upload_path'];
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($config['randomize_names']) {
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        } else {
            $filename = self::sanitizeInput(pathinfo($file['name'], PATHINFO_FILENAME), 'string') . '.' . $extension;
        }
        
        $filepath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Set proper permissions
            chmod($filepath, 0644);
            
            self::logSecurityEvent('file_upload_success', [
                'original_name' => $file['name'],
                'stored_name' => $filename,
                'size' => $file['size'],
                'mime_type' => $file['type']
            ]);
            
            return ['success' => true, 'path' => $filepath, 'filename' => $filename];
        } else {
            return ['success' => false, 'errors' => ['Failed to move uploaded file']];
        }
    }
    
    /**
     * Check Permission
     * 
     * Checks if the current user has the required permission.
     * 
     * @param string $permission Permission to check
     * @return bool True if user has permission
     */
    public static function checkPermission($permission) {
        if (!isLoggedIn()) {
            return false;
        }
        
        $userType = getCurrentUserType();
        $config = getSecurityConfig('access_control');
        
        // Admin has all permissions
        if ($userType === 'admin' || isAdmin()) {
            return true;
        }
        
        // Check user role permissions
        if (isset($config['roles'][$userType])) {
            $permissions = $config['roles'][$userType]['permissions'];
            return in_array($permission, $permissions) || in_array('*', $permissions);
        }
        
        return false;
    }
    
    /**
     * Require Permission
     * 
     * Requires a specific permission and redirects if not granted.
     * 
     * @param string $permission Permission required
     * @param string $redirectUrl URL to redirect if permission denied
     */
    public static function requirePermission($permission, $redirectUrl = null) {
        if (!self::checkPermission($permission)) {
            self::logSecurityEvent('permission_denied', [
                'permission' => $permission,
                'user_id' => getCurrentUserId(),
                'user_type' => getCurrentUserType(),
                'ip' => self::getClientIP()
            ]);
            
            $redirectUrl = $redirectUrl ?: BASE_URL . '/login.php';
            redirect($redirectUrl);
        }
    }
    
    /**
     * Log Security Event
     * 
     * Logs security-related events for monitoring and auditing.
     * 
     * @param string $event Event type
     * @param array $data Event data
     */
    public static function logSecurityEvent($event, $data = []) {
        // Get audit logging configuration directly
        $config = defined('AUDIT_LOGGING') ? AUDIT_LOGGING : [];
        
        // Skip if audit logging is disabled or not configured
        if (!($config['enabled'] ?? false)) {
            return;
        }
        
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_id' => getCurrentUserId(),
            'user_type' => getCurrentUserType(),
            'data' => $data
        ];
        
        $logEntry = json_encode($logData) . PHP_EOL;
        
        // Ensure log directory exists
        $logDir = dirname($config['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get Client IP Address
     * 
     * Gets the real client IP address, handling proxy headers.
     * 
     * @return string Client IP address
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get Rate Limit Data
     * 
     * Gets rate limiting data for a specific IP and type.
     * 
     * @param string $ip IP address
     * @param string $type Rate limit type
     * @return array Rate limit data
     */
    private static function getRateLimitData($ip, $type) {
        $key = "rate_limit_{$type}_{$ip}";
        return $_SESSION[$key] ?? [];
    }
    
    /**
     * Clear Rate Limit Data
     * 
     * Clears rate limiting data for a specific IP and type.
     * 
     * @param string $ip IP address
     * @param string $type Rate limit type
     */
    private static function clearRateLimitData($ip, $type) {
        $key = "rate_limit_{$type}_{$ip}";
        unset($_SESSION[$key]);
    }
    
    /**
     * Route Matches Pattern
     * 
     * Checks if a route matches a pattern (supports wildcards).
     * 
     * @param string $route Route to check
     * @param string $pattern Pattern to match
     * @return bool True if route matches pattern
     */
    private static function routeMatches($route, $pattern) {
        $pattern = str_replace('*', '.*', $pattern);
        return preg_match("#^{$pattern}$#", $route);
    }
}

/**
 * Helper function to format bytes
 * 
 * @param int $bytes Bytes to format
 * @return string Formatted bytes
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
} 