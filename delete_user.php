<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }
    return $conn;
}

$userId = (int)($_POST['user_id'] ?? 0);
if (!$userId) { echo json_encode(['success' => false, 'message' => 'Invalid user']); exit; }

// Optional: check foreign key constraints or soft delete
$conn = getDBConnection();
$stmt = $conn->prepare('DELETE FROM applicants WHERE applicant_id = ?');
$stmt->bind_param('i', $userId);
$ok = $stmt->execute();
echo json_encode(['success' => $ok, 'message' => $ok ? 'User deleted' : 'Delete failed']);
exit;

