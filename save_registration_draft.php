<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'];
$rawBody = file_get_contents('php://input');
$formData = json_decode($rawBody, true);

if (!$formData) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$conn = getLegacyDatabaseConnection();

// Check if draft exists
$checkStmt = $conn->prepare("SELECT id FROM registration_drafts WHERE applicant_id = ?");
$checkStmt->bind_param('i', $userId);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

$draftData = json_encode($formData);
$updatedAt = date('Y-m-d H:i:s');

if ($existing) {
    // Update existing draft
    $stmt = $conn->prepare("UPDATE registration_drafts SET draft_data = ?, updated_at = ? WHERE applicant_id = ?");
    $stmt->bind_param('ssi', $draftData, $updatedAt, $userId);
} else {
    // Create new draft
    $stmt = $conn->prepare("INSERT INTO registration_drafts (applicant_id, draft_data, created_at, updated_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $draftData, $updatedAt, $updatedAt);
}

$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Draft saved successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save draft']);
}
?>





























