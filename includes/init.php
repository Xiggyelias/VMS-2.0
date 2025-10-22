<?php
/**
 * Application Initialization
 * 
 * This file initializes the application by loading all required
 * configuration files and function libraries. Include this file
 * at the beginning of all PHP pages.
 */

// Start output buffering for better performance
ob_start();

// Load configuration files first
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Load utility functions (needed for error handling)
require_once __DIR__ . '/functions/utilities.php';

// Now set up error handling after utilities are loaded
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Set exception handler (now logError() is available)
set_exception_handler(function($exception) {
    logError("Uncaught Exception: " . $exception->getMessage(), 'ERROR', [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);

    // Check if the request is an API call expecting JSON
    $isApiRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                    (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) || 
                    (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);

    if ($isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(500);
        $errorDetails = [
            'success' => false,
            'message' => 'A server error occurred.'
        ];
        if (isDevelopment()) {
            $errorDetails['error'] = $exception->getMessage();
            $errorDetails['file'] = $exception->getFile();
            $errorDetails['line'] = $exception->getLine();
        }
        echo json_encode($errorDetails);
    } else {
        if (isDevelopment()) {
            echo '<div class="alert alert-danger">';
            echo '<strong>Error:</strong> ' . htmlspecialchars($exception->getMessage()) . '<br>';
            echo '<strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . '<br>';
            echo '<strong>Line:</strong> ' . $exception->getLine();
            echo '</div>';
        } else {
            // Avoid breaking the layout on production
            if (ob_get_level()) ob_clean(); // Clear any previous output
            include __DIR__ . '/../error-500.php'; // Show a generic error page
        }
    }
    exit;
});

// Load authentication functions
require_once __DIR__ . '/functions/auth.php';

// Load vehicle management functions
require_once __DIR__ . '/functions/vehicle.php';

// Load driver management functions (if exists)
if (file_exists(__DIR__ . '/functions/driver.php')) {
    require_once __DIR__ . '/functions/driver.php';
}

// Load email functions (if exists)
if (file_exists(__DIR__ . '/functions/email.php')) {
    require_once __DIR__ . '/functions/email.php';
}

// Load notification functions (if exists)
if (file_exists(__DIR__ . '/functions/notifications.php')) {
    require_once __DIR__ . '/functions/notifications.php';
}

// Check session timeout
if (isLoggedIn()) {
    checkSessionTimeout();
}

/**
 * Get Asset URL
 * 
 * Returns the full URL for an asset file.
 * 
 * @param string $path Asset path relative to assets directory
 * @return string Full asset URL
 */
function asset($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Get View Path
 * 
 * Returns the full path to a view file.
 * 
 * @param string $view View name (without .php extension)
 * @return string Full view path
 */
function view($view) {
    return VIEWS_PATH . '/' . $view . '.php';
}

/**
 * Include View
 * 
 * Includes a view file with optional data.
 * 
 * @param string $view View name
 * @param array $data Data to pass to the view
 */
function includeView($view, $data = []) {
    // Extract data to make variables available in view
    extract($data);
    
    $viewPath = view($view);
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        throw new Exception("View file not found: $viewPath");
    }
}

/**
 * Redirect with Message
 * 
 * Redirects to a URL with a flash message.
 * 
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
    redirect($url);
}

/**
 * Get Flash Message
 * 
 * Retrieves and clears the flash message from session.
 * 
 * @return array|null Flash message array or null if no message
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $message;
}

/**
 * Display Flash Message
 * 
 * Displays the flash message if one exists.
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        echo "<div class=\"alert alert-{$type}\">" . htmlspecialchars($message) . "</div>";
    }
}

// Auto-include common CSS and JS files
function includeCommonAssets() {
    echo '<link rel="stylesheet" href="' . asset('css/main.css') . '">';
    echo '<link rel="stylesheet" href="' . asset('css/styles.css') . '">';
    echo '<script src="' . asset('js/main.js') . '" defer></script>';
}

// Note: CSRF protection is now handled by SecurityMiddleware
// The old CSRF functions have been removed to avoid conflicts 