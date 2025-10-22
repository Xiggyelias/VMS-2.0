<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function createNotification($type, $role, $userId, $message) {
    try {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (type, role, user_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssis", $type, $role, $userId, $message);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create notification: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

function getRoleAwareMessage($type, $role, $details = []) {
    $rolePrefix = ucfirst($role);
    
    switch ($type) {
        case 'new-registration':
            return "$rolePrefix registered a new vehicle: {$details['vehicle_make']} ({$details['plate_number']})";
            
        case 'update':
            return "$rolePrefix updated vehicle information: {$details['vehicle_make']} ({$details['plate_number']})";
            
        case 'transfer-request':
            return "$rolePrefix requested transfer of vehicle ownership: {$details['vehicle_make']} ({$details['plate_number']})";
            
        case 'disk-assignment':
            return "$rolePrefix assigned disk number {$details['disk_number']} to vehicle: {$details['vehicle_make']} ({$details['plate_number']})";
            
        case 'driver-assignment':
            return "$rolePrefix added an authorized driver to vehicle: {$details['vehicle_make']} ({$details['plate_number']})";
            
        default:
            return "$rolePrefix performed an action in the system";
    }
}

// Example usage:
// createNotification(
//     'new-registration',
//     'student',
//     $userId,
//     getRoleAwareMessage('new-registration', 'student', [
//         'vehicle_make' => 'Toyota',
//         'plate_number' => 'ABC123'
//     ])
// );
?> 