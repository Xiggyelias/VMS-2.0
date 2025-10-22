<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

header('Content-Type: application/json');

try {
    // CSRF check
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_token'] ?? '');
    if (!$csrf || !SecurityMiddleware::verifyCSRFToken($csrf)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $reportId = (int)($_POST['report_id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid report id']);
        exit;
    }

    // DB connection
    $conn = new mysqli('localhost', 'root', '', 'vehicleregistrationsystem');
    if ($conn->connect_error) {
        throw new Exception('DB connection failed');
    }

    // Fetch file_path if any
    $filePath = null;
    if ($stmt = $conn->prepare('SELECT file_path FROM admin_reports WHERE id = ?')) {
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) { $filePath = $row['file_path'] ?? null; }
        $stmt->close();
    }

    // Delete record
    if (!($stmt = $conn->prepare('DELETE FROM admin_reports WHERE id = ?'))) {
        throw new Exception('Delete prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $reportId);
    if (!$stmt->execute()) {
        throw new Exception('Delete failed: ' . $stmt->error);
    }
    $stmt->close();

    // Attempt to delete file if exists
    if ($filePath) {
        $abs = __DIR__ . '/' . ltrim($filePath, '/');
        if (is_file($abs)) { @unlink($abs); }
    }

    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Report deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>


