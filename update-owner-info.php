<?php
// Start output buffering to catch any accidental output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set error reporting for development
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Set JSON header first
header('Content-Type: application/json; charset=UTF-8');

// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Load required files
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';

// Initialize security
SecurityMiddleware::initialize();

/**
 * Send a JSON response
 * 
 * @param string $status Response status (success/error)
 * @param string $message Response message
 * @param array $data Additional data to include in response
 * @param int|null $httpCode Optional explicit HTTP status code
 * @return void
 */
function sendResponse($status, $message, $data = [], $httpCode = null) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set the status code if provided; otherwise preserve existing
    if ($httpCode !== null) {
        http_response_code($httpCode);
    } else {
        http_response_code($status === 'error' ? 400 : 200);
    }

    // Create the response array
    $response = [
        'status' => $status,
        'message' => $message
    ];

    // Add data if provided
    if (!empty($data)) {
        $response['data'] = $data;
    }

    // Output the JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Ensure no further output
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    exit;
}

// Error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/update_owner_info_errors.log');

// Require authentication
if (!isLoggedIn()) {
    error_log('Authentication failed: User not logged in');
    sendResponse('error', 'Authentication required', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    sendResponse('error', 'Method not allowed', [], 405);
}

// Parse JSON body if provided and normalize payload
$jsonPayload = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) { $jsonPayload = $decoded; }
    }
}

// Validate CSRF token (accept header or body token or form token)
$token = $jsonPayload['_token']
    ?? ($_POST['_token'] ?? null)
    ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

if (!$token || !SecurityMiddleware::verifyCSRFToken($token)) {
    error_log('Invalid CSRF token');
    sendResponse('error', 'Invalid CSRF token', [], 419);
}

$user_id = getCurrentUserId();

// Get and validate input
// Prefer JSON payload fields when present; fallback to form fields
$fullName = trim(($jsonPayload['fullName'] ?? $_POST['fullName'] ?? ''));
$idNumber = trim(($jsonPayload['idNumber'] ?? $_POST['idNumber'] ?? ''));
$phone = trim(($jsonPayload['phone'] ?? $_POST['phone'] ?? ''));
$college = trim(($jsonPayload['college'] ?? $_POST['college'] ?? ''));

if (empty($fullName)) {
    error_log('Validation failed: Full name is required');
    sendResponse('error', 'Full name is required', [], 422);
}

try {
    $conn = getLegacyDatabaseConnection();
    if (!$conn) {
        throw new Exception('Failed to connect to database');
    }
    
    // Set charset to ensure proper encoding
    $conn->set_charset('utf8mb4');
    
    // Determine available columns
    $cols = [];
    $colsRes = $conn->query("SHOW COLUMNS FROM applicants");
    if ($colsRes) {
        while ($r = $colsRes->fetch_assoc()) {
            $cols[strtolower($r['Field'])] = $r['Field'];
        }
    }

    // Build dynamic update set
    $updates = [];
    $params = [];
    $types = '';

    if (isset($cols['fullname'])) { $updates[] = $cols['fullname'] . ' = ?'; $params[] = $fullName; $types .= 's'; }
    if (isset($cols['idnumber'])) { $updates[] = $cols['idnumber'] . ' = ?'; $params[] = $idNumber; $types .= 's'; }
    if (isset($cols['phone']))    { $updates[] = $cols['phone']    . ' = ?'; $params[] = $phone;    $types .= 's'; }
    if ($college !== '' && isset($cols['college'])) { $updates[] = $cols['college'] . ' = ?'; $params[] = $college; $types .= 's'; }
    if (isset($cols['updated_at'])) { $updates[] = $cols['updated_at'] . ' = NOW()'; }

    if (empty($updates)) {
        sendResponse('success', 'No changes were made to your information');
    }

    $sql = 'UPDATE applicants SET ' . implode(', ', $updates) . ' WHERE applicant_id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $types .= 'i';
    $params[] = $user_id;

    // Bind params dynamically
    $bindParams = array_merge([$types], $params);
    $refs = [];
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        if ($affectedRows > 0) {
            // Update session if fullName was changed
            if (isset($_SESSION['user_name']) && $fullName !== $_SESSION['user_name']) {
                $_SESSION['user_name'] = $fullName;
            }
            sendResponse('success', 'Owner information updated successfully', [
                'affected_rows' => $affectedRows
            ]);
        } else {
            // No rows were updated, but no error occurred
            sendResponse('success', 'No changes were made to your information');
        }
    } else {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    $errorMessage = 'Update owner info error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    error_log($errorMessage);
    
    // Log the backtrace for debugging
    error_log('Backtrace: ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
    
    sendResponse('error', 'Failed to update information. Please try again.', [], 500);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>
