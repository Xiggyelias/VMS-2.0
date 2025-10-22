<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

// Handle test requests
if (isset($payload['test']) && $payload['test'] === 'direct_connection') {
    echo json_encode([
        'success' => true, 
        'message' => 'Backend is accessible',
        'timestamp' => date('Y-m-d H:i:s'),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
        ]
    ]);
    exit;
}

$idToken = $payload['credential'] ?? null;

if (!$idToken) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing token',
        'error_details' => [
            'payload_keys' => array_keys($payload),
            'raw_body' => substr($rawBody, 0, 200) . (strlen($rawBody) > 200 ? '...' : '')
        ]
    ]);
    exit;
}

try {
// Verify the token using Google public keys (basic verification via JWT header parsing)
// For brevity and no external libs, we will call Google tokeninfo endpoint.
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
$resp = @file_get_contents($verifyUrl);
    
if ($resp === false) {
    http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Token verification failed',
            'error_details' => [
                'verify_url' => $verifyUrl,
                'error' => error_get_last()
            ]
        ]);
    exit;
}

$tokenInfo = json_decode($resp, true);
if (!is_array($tokenInfo) || ($tokenInfo['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid audience',
            'error_details' => [
                'expected_audience' => GOOGLE_CLIENT_ID,
                'received_audience' => $tokenInfo['aud'] ?? 'Not set',
                'token_info' => $tokenInfo
            ]
        ]);
    exit;
}

$email = $tokenInfo['email'] ?? '';
$emailVerified = ($tokenInfo['email_verified'] ?? 'false') === 'true';
if (!$email || !$emailVerified) {
    http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Email not verified',
            'error_details' => [
                'email' => $email,
                'email_verified' => $emailVerified,
                'token_info' => $tokenInfo
            ]
        ]);
    exit;
}

// Restrict to africau.edu
$allowedDomain = ALLOWED_GOOGLE_DOMAIN;
if (strtolower(substr(strrchr($email, '@'), 1)) !== strtolower($allowedDomain)) {
    http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Only Africa University emails are allowed.',
            'error_details' => [
                'email' => $email,
                'allowed_domain' => $allowedDomain,
                'extracted_domain' => strtolower(substr(strrchr($email, '@'), 1))
            ]
        ]);
    exit;
}

    // Extract additional profile information
    $fullName = $tokenInfo['name'] ?? '';
    $dateOfBirth = null;
    
    // Try to extract DOB from Google profile metadata if available
    if (isset($tokenInfo['birthdate'])) {
        $dateOfBirth = $tokenInfo['birthdate'];
    } elseif (isset($tokenInfo['birth_year']) && isset($tokenInfo['birth_month']) && isset($tokenInfo['birth_day'])) {
        $dateOfBirth = sprintf('%04d-%02d-%02d', $tokenInfo['birth_year'], $tokenInfo['birth_month'], $tokenInfo['birth_day']);
    }

// Create or find user record by email, then set session and return next step
$conn = getLegacyDatabaseConnection();

    // Detect existing columns to avoid referencing missing ones
    $existingColumns = [];
    $colsRes = $conn->query("SHOW COLUMNS FROM applicants");
    if ($colsRes) {
        while ($row = $colsRes->fetch_assoc()) {
            $existingColumns[strtolower($row['Field'])] = true;
        }
    }
    $hasApplicationStatus = isset($existingColumns['applicationstatus']);
    $hasDateOfBirth = isset($existingColumns['dateofbirth']);

    // Build SELECT dynamically based on available columns
    $selectFields = ['applicant_id', 'registrantType', 'fullName'];
    if ($hasApplicationStatus) { $selectFields[] = 'applicationStatus'; }
    if ($hasDateOfBirth) { $selectFields[] = 'dateOfBirth'; }
    $selectSql = "SELECT " . implode(', ', $selectFields) . " FROM applicants WHERE Email = ?";

    $stmt = $conn->prepare($selectSql);
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

// Do not auto-assign role; require explicit selection on first login

if (!$user) {
        // Create a new applicant record with Google profile data
        // Do NOT set a concrete role here; mark as 'pending' if column exists
        // Build INSERT dynamically
        $columns = ['fullName', 'Email'];
        $values = [$fullName, $email];
        $placeholders = ['?', '?'];
        if (isset($existingColumns['registranttype'])) {
            $columns[] = 'registrantType';
            $values[] = 'pending';
            $placeholders[] = '?';
        }
        
        if ($hasApplicationStatus) {
            $columns[] = 'applicationStatus';
            $values[] = 'draft';
            $placeholders[] = '?';
        }
        if ($hasDateOfBirth && $dateOfBirth) {
            $columns[] = 'dateOfBirth';
            $values[] = $dateOfBirth;
            $placeholders[] = '?';
        }
        
        // Try to add registration_date if it exists
        if (isset($existingColumns['registration_date'])) {
            $columns[] = 'registration_date';
            $values[] = date('Y-m-d H:i:s');
            $placeholders[] = '?';
        }
        
        $columnList = implode(', ', $columns);
        $placeholderList = implode(', ', $placeholders);
        $insert = $conn->prepare("INSERT INTO applicants ($columnList) VALUES ($placeholderList)");
        if (!$insert) {
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to prepare INSERT statement',
                'error_details' => [
                    'database_error' => $conn->error,
                    'columns' => $columns,
                    'sql' => "INSERT INTO applicants ($columnList) VALUES ($placeholderList)"
                ]
            ]);
            exit;
        }
        $types = str_repeat('s', count($values));
        $insert->bind_param($types, ...$values);
    $ok = $insert->execute();
    $insert->close();
        
    if (!$ok) {
        http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to create account',
                'error_details' => [
                    'database_error' => $conn->error,
                    'email' => $email,
                    'name' => $fullName,
                    'columns_used' => $columns
                ]
            ]);
        exit;
    }
    $userId = $conn->insert_id;
        $applicationStatus = $hasApplicationStatus ? 'draft' : 'draft';

        // Always require explicit role/identifier selection for first-time users
            // Store minimal pending OAuth context for finalize step
            if (!isset($_SESSION['pending_oauth']) || !is_array($_SESSION['pending_oauth'])) {
                $_SESSION['pending_oauth'] = [];
            }
            $_SESSION['pending_oauth'][$userId] = [
                'email' => $email,
                'name'  => $fullName
            ];

            echo json_encode([
                'success' => true,
                'requires_type_selection' => true,
                'temp_user_id' => $userId,
                'user_info' => [
                    'id' => $userId,
                    'name' => $fullName,
                    'email' => $email,
                    'dob' => $dateOfBirth,
                'application_status' => $applicationStatus
                ]
            ]);
            exit;
} else {
    $userId = (int)$user['applicant_id'];
        $applicationStatus = $hasApplicationStatus ? ($user['applicationStatus'] ?? 'draft') : 'draft';
        
        // Update existing user's name and DOB if we have new information and columns exist
        if ($fullName && (!isset($user['fullName']) || $fullName !== $user['fullName'])) {
            $updateStmt = $conn->prepare("UPDATE applicants SET fullName = ? WHERE applicant_id = ?");
            $updateStmt->bind_param('si', $fullName, $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        if ($hasDateOfBirth && $dateOfBirth && (!isset($user['dateOfBirth']) || !$user['dateOfBirth'])) {
            $updateDobStmt = $conn->prepare("UPDATE applicants SET dateOfBirth = ? WHERE applicant_id = ?");
            $updateDobStmt->bind_param('si', $dateOfBirth, $userId);
            $updateDobStmt->execute();
            $updateDobStmt->close();
        }
}

// Determine registrantType and whether setup is required
// Re-fetch with required columns to validate identifiers
$selectFields = ['applicant_id', 'registrantType', 'fullName', 'Email'];
if ($hasApplicationStatus) { $selectFields[] = 'applicationStatus'; }
// Include identifier columns if they exist
if (isset($existingColumns['studentregno'])) { $selectFields[] = 'studentRegNo'; }
if (isset($existingColumns['staffsregno'])) { $selectFields[] = 'staffsRegNo'; }
$selectSql2 = "SELECT " . implode(', ', $selectFields) . " FROM applicants WHERE Email = ?";
$stmt2 = $conn->prepare($selectSql2);
$stmt2->bind_param('s', $email);
$stmt2->execute();
$res2 = $stmt2->get_result();
$user = $res2->fetch_assoc();
$stmt2->close();

$currentType = strtolower(trim((string)($user['registrantType'] ?? '')));
// Role must be explicitly chosen and identifiers must exist per role
$hasStudentId = isset($user['studentRegNo']) && preg_match('/^\d{6}$/', (string)$user['studentRegNo']);
$hasStaffId   = isset($user['staffsRegNo']) && preg_match('/^[A-Za-z0-9]{5}$/', (string)$user['staffsRegNo']);

$validRole = in_array($currentType, ['student','staff'], true);
if (!$validRole) {
    $needsSetup = true;
} else if ($currentType === 'student') {
    $needsSetup = !$hasStudentId;
} else if ($currentType === 'staff') {
    $needsSetup = !$hasStaffId;
}

if ($needsSetup) {
    // Store pending context and request role selection on client
    if (!isset($_SESSION['pending_oauth']) || !is_array($_SESSION['pending_oauth'])) {
        $_SESSION['pending_oauth'] = [];
    }
    $_SESSION['pending_oauth'][$userId] = [
        'email' => $email,
        'name'  => $fullName
    ];

    echo json_encode([
        'success' => true,
        'requires_type_selection' => true,
        'temp_user_id' => $userId,
        'user_info' => [
            'id' => $userId,
            'name' => $fullName,
            'email' => $email,
            'application_status' => $applicationStatus
        ]
    ]);
    exit;
}

// Update last_login if column exists
if (isset($existingColumns['last_login'])) {
    $ll = $conn->prepare("UPDATE applicants SET last_login = NOW() WHERE applicant_id = ?");
    if ($ll) { $ll->bind_param('i', $userId); $ll->execute(); $ll->close(); }
}

// Start session (only when setup is complete)
$_SESSION['user_id'] = $userId;
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $fullName;
$_SESSION['user_type'] = $currentType;
$_SESSION['logged_in'] = true;
$_SESSION['application_status'] = $applicationStatus;

    // Always redirect to the user dashboard after successful login
    $redirect = 'user-dashboard.php';

    echo json_encode([
        'success' => true, 
        'redirect' => $redirect,
        'user_info' => [
            'id' => $userId,
            'name' => $fullName,
            'email' => $email,
            'username' => $fullName,
            'registrant_type' => $currentType,
            'dob' => $dateOfBirth,
            'application_status' => $applicationStatus
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication failed: ' . $e->getMessage(),
        'error_details' => [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>






