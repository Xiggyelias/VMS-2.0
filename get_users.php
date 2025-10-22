<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin access
requireAdmin();

header('Content-Type: application/json');

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB connection failed']);
        exit;
    }
    return $conn;
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';

$wheres = [];
$params = [];
$typesStr = '';

if (in_array($type, ['student','staff','guest'])) {
    $wheres[] = 'a.registrantType = ?';
    $params[] = $type;
    $typesStr .= 's';
}

if (in_array($status, ['active','suspended'])) {
    $wheres[] = 'COALESCE(a.status, "active") = ?';
    $params[] = $status;
    $typesStr .= 's';
}

$whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

$sql = "
    SELECT 
        a.applicant_id,
        a.fullName,
        a.Email,
        a.phone,
        a.registrantType,
        DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i') as registration_date,
        COALESCE(a.status, 'active') as status,
        COUNT(v.vehicle_id) as vehicles_count
    FROM applicants a
    LEFT JOIN vehicles v ON v.applicant_id = a.applicant_id
    $whereSql
    GROUP BY a.applicant_id
    ORDER BY a.created_at DESC
    LIMIT 1000
";

$conn = getDBConnection();

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typesStr, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode(['success' => true, 'users' => $users]);
exit;

