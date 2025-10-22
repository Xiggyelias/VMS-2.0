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

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) { $payload = $_POST; }

// CSRF: accept header or body token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($payload['_token'] ?? ($_POST['_token'] ?? null));
if (!$csrfToken || !SecurityMiddleware::verifyCSRFToken($csrfToken)) {
    http_response_code(419);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = (int)($payload['temp_user_id'] ?? $payload['user_id'] ?? 0);
$typeRaw = $payload['registrantType'] ?? $payload['registrant_type'] ?? '';
$type = strtolower(trim($typeRaw));
// Identifier provided from the modal (accept both keys)
$identity = trim($payload['identity'] ?? ($payload['identifier'] ?? ''));

$validTypes = ['student','staff'];
if (!$userId || !in_array($type, $validTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $conn = getLegacyDatabaseConnection();

    // Resolve temp session reference created during Google auth
    $pending = $_SESSION['pending_oauth'][$userId] ?? null;
    if (!$pending || !is_array($pending)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please sign in again.']);
        exit;
    }
    $pendingEmail = trim((string)($pending['email'] ?? ''));
    $pendingName  = trim((string)($pending['name'] ?? ($pending['fullName'] ?? '')));

    // Find or create applicant by email
    $user = null;
    $applicantId = 0;
    if ($pendingEmail !== '') {
        $stmt = $conn->prepare("SELECT applicant_id, Email, fullName FROM applicants WHERE Email = ? LIMIT 1");
        $stmt->bind_param('s', $pendingEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();
    }

    if (!$user) {
        // Create new applicant with minimal fields (no guest accounts)
        $stmt = $conn->prepare("INSERT INTO applicants (Email, fullName, registrantType) VALUES (?, ?, 'pending')");
        $stmt->bind_param('ss', $pendingEmail, $pendingName);
        if (!$stmt->execute()) {
            $stmt->close();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to create user']);
            exit;
        }
        $applicantId = (int)$conn->insert_id;
        $stmt->close();
        $user = [ 'applicant_id' => $applicantId, 'Email' => $pendingEmail, 'fullName' => $pendingName ];
    } else {
        $applicantId = (int)$user['applicant_id'];
    }

    // Validate per role (server-side enforcement)
    $validationStatus = 'success';
    if ($type === 'student') {
        if (!preg_match('/^\d{6}$/', $identity)) { $validationStatus = 'failed'; }
    } elseif ($type === 'staff') {
        if (!preg_match('/^[A-Za-z0-9]{5}$/', $identity)) { $validationStatus = 'failed'; }
    }

    if ($validationStatus !== 'success') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Failed. Please provide a valid identifier for the selected role.']);
        exit;
    }

    // Enforce Registration Number ↔ Email binding rules
    // 1) If the account already has a reg number saved, it MUST match the provided one.
    if ($type === 'student') {
        $check = $conn->prepare("SELECT studentRegNo FROM applicants WHERE applicant_id = ?");
        $check->bind_param('i', $applicantId);
        $check->execute();
        $resx = $check->get_result();
        $rowx = $resx ? $resx->fetch_assoc() : null;
        $check->close();
        if ($rowx && !empty($rowx['studentRegNo']) && $rowx['studentRegNo'] !== $identity) {
            http_response_code(403);
            echo json_encode(['status' => 'denied', 'message' => 'Registration number does not match our records for this email.']);
            exit;
        }
        // 2) Prevent two different emails from claiming the same reg number
        $dup = $conn->prepare("SELECT applicant_id FROM applicants WHERE studentRegNo = ? AND applicant_id <> ? LIMIT 1");
        $dup->bind_param('si', $identity, $applicantId);
        $dup->execute();
        $dupRes = $dup->get_result();
        $dup->close();
        if ($dupRes && $dupRes->num_rows > 0) {
            http_response_code(403);
            echo json_encode(['status' => 'denied', 'message' => 'This student registration number is already in use by another account.']);
            exit;
        }
    } elseif ($type === 'staff') {
        $check = $conn->prepare("SELECT staffsRegNo FROM applicants WHERE applicant_id = ?");
        $check->bind_param('i', $applicantId);
        $check->execute();
        $resx = $check->get_result();
        $rowx = $resx ? $resx->fetch_assoc() : null;
        $check->close();
        if ($rowx && !empty($rowx['staffsRegNo']) && $rowx['staffsRegNo'] !== $identity) {
            http_response_code(403);
            echo json_encode(['status' => 'denied', 'message' => 'Registration number does not match our records for this email.']);
            exit;
        }
        $dup = $conn->prepare("SELECT applicant_id FROM applicants WHERE staffsRegNo = ? AND applicant_id <> ? LIMIT 1");
        $dup->bind_param('si', $identity, $applicantId);
        $dup->execute();
        $dupRes = $dup->get_result();
        $dup->close();
        if ($dupRes && $dupRes->num_rows > 0) {
            http_response_code(403);
            echo json_encode(['status' => 'denied', 'message' => 'This staff registration number is already in use by another account.']);
            exit;
        }
    }

    // Update role and identifier columns according to mapping
    // Mapping: student -> studentRegNo; staff -> staffsRegNo
    if ($type === 'student') {
        $u = $conn->prepare("UPDATE applicants SET registrantType = 'student', studentRegNo = ?, staffsRegNo = NULL WHERE applicant_id = ?");
        $u->bind_param('si', $identity, $applicantId);
    } elseif ($type === 'staff') {
        $u = $conn->prepare("UPDATE applicants SET registrantType = 'staff', staffsRegNo = ?, studentRegNo = NULL WHERE applicant_id = ?");
        $u->bind_param('si', $identity, $applicantId);
    }
    $ok = $u && $u->execute();
    if ($u) { $u->close(); }

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update role']);
        exit;
    }

    // Optional: update last_login if column exists
    $hasLastLogin = false;
    if ($res = $conn->query("SHOW COLUMNS FROM applicants LIKE 'last_login'")) {
        $hasLastLogin = $res->num_rows > 0;
        $res->close();
    }
    if ($hasLastLogin) {
        $ll = $conn->prepare("UPDATE applicants SET last_login = NOW() WHERE applicant_id = ?");
        if ($ll) { $ll->bind_param('i', $applicantId); $ll->execute(); $ll->close(); }
    }

    // Set session and respond with redirect
    $_SESSION['user_id'] = (int)$applicantId;
    $_SESSION['user_email'] = $user['Email'] ?? '';
    $_SESSION['user_name'] = $user['fullName'] ?? '';
    $_SESSION['user_type'] = $type;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Clear pending entry for this temp user id
    unset($_SESSION['pending_oauth'][$userId]);

    // Redirect to unified dashboard regardless of role
    $redirect = 'user-dashboard.php';

    echo json_encode([
        'status' => $validationStatus,
        'role' => $type,
        'redirect' => $redirect,
        'user' => [
            'id' => (int)$user['applicant_id'],
            'email' => $user['Email'] ?? '',
            'name' => $user['fullName'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}
