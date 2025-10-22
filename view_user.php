<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

$userId = (int)$_GET['user_id'];

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }
    return $conn;
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT a.*, DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i') as registration_date, COALESCE(a.status, 'active') as status FROM applicants a WHERE a.applicant_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$stmt = $conn->prepare("SELECT vehicle_id, make, PlateNumber as plate, status FROM vehicles WHERE applicant_id = ? ORDER BY registration_date DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$user['vehicles'] = $vehicles;
$user['vehicles_count'] = count($vehicles);

echo json_encode(['success' => true, 'user' => $user]);
exit;

