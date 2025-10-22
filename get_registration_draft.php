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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getLegacyDatabaseConnection();

$stmt = $conn->prepare("SELECT draft_data, updated_at FROM registration_drafts WHERE applicant_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$draft = $result->fetch_assoc();
$stmt->close();

if ($draft) {
    $formData = json_decode($draft['draft_data'], true);
    echo json_encode([
        'success' => true, 
        'draft' => $formData,
        'updated_at' => $draft['updated_at']
    ]);
} else {
    echo json_encode(['success' => true, 'draft' => null]);
}
?>





























