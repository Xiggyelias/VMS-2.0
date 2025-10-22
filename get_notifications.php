<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

try {
    $conn = getDBConnection();
    
    // Get both read and unread notifications, with unread ones first
    $stmt = $conn->prepare("
        SELECT n.*, a.fullName as user_name, a.registrantType as user_role
        FROM notifications n
        JOIN applicants a ON n.user_id = a.applicant_id
        ORDER BY n.is_read ASC, n.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format notifications for response
    $formatted_notifications = array_map(function($notification) {
        return [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'role' => $notification['role'],
            'user_name' => $notification['user_name'],
            'user_role' => $notification['user_role'],
            'message' => $notification['message'],
            'created_at' => date('M j, Y g:i A', strtotime($notification['created_at'])),
            'is_read' => (bool)$notification['is_read']
        ];
    }, $notifications);
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 