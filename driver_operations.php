<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Always respond in JSON
header('Content-Type: application/json; charset=UTF-8');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Please log in to continue.']);
    exit();
}

// Database connection function
function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'success' => false, 'message' => ''];
    
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getDBConnection();
        $applicant_id = getCurrentUserId();
        
        switch ($action) {
            case 'add':
                $fullname = trim($_POST['fullname'] ?? '');
                $licenseNumber = trim($_POST['licenseNumber'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                
                if (empty($fullname) || empty($licenseNumber)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                // Check if license number already exists
                $stmt = $conn->prepare("SELECT Id FROM authorized_driver WHERE licenseNumber = ?");
                $stmt->bind_param("s", $licenseNumber);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('This license number is already registered.');
                }
                $stmt->close();
                
                // Insert new driver
                $stmt = $conn->prepare("INSERT INTO authorized_driver (applicant_id, fullname, licenseNumber, contact) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $applicant_id, $fullname, $licenseNumber, $contact);
                
                if ($stmt->execute()) {
                    $driver_id = $conn->insert_id;
                    $response['status'] = 'success';
                    $response['success'] = true;
                    $response['message'] = 'Driver added successfully!';
                    $response['driver'] = [
                        'Id' => $driver_id,
                        'fullname' => $fullname,
                        'licenseNumber' => $licenseNumber,
                        'contact' => $contact
                    ];
                } else {
                    throw new Exception("Failed to add driver: " . $stmt->error);
                }
                break;
                
            case 'edit':
                $driver_id = intval($_POST['driver_id'] ?? 0);
                $fullname = trim($_POST['fullname'] ?? '');
                $licenseNumber = trim($_POST['licenseNumber'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                
                if ($driver_id <= 0 || empty($fullname) || empty($licenseNumber)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                // Check if license number already exists for other drivers
                $stmt = $conn->prepare("SELECT Id FROM authorized_driver WHERE licenseNumber = ? AND Id != ?");
                $stmt->bind_param("si", $licenseNumber, $driver_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('This license number is already registered to another driver.');
                }
                $stmt->close();
                
                // Update driver
                $stmt = $conn->prepare("UPDATE authorized_driver SET fullname = ?, licenseNumber = ?, contact = ? WHERE Id = ?");
                $stmt->bind_param("sssi", $fullname, $licenseNumber, $contact, $driver_id);
                
                if ($stmt->execute()) {
                    $response['status'] = 'success';
                    $response['success'] = true;
                    $response['message'] = 'Driver updated successfully!';
                    $response['driver'] = [
                        'Id' => $driver_id,
                        'fullname' => $fullname,
                        'licenseNumber' => $licenseNumber,
                        'contact' => $contact
                    ];
                } else {
                    throw new Exception("Failed to update driver: " . $stmt->error);
                }
                break;
                
            case 'delete':
                $driver_id = intval($_POST['driver_id'] ?? 0);
                
                if ($driver_id <= 0) {
                    throw new Exception('Invalid driver ID.');
                }
                
                // Delete driver
                $stmt = $conn->prepare("DELETE FROM authorized_driver WHERE Id = ?");
                $stmt->bind_param("i", $driver_id);
                
                if ($stmt->execute()) {
                    $response['status'] = 'success';
                    $response['success'] = true;
                    $response['message'] = 'Driver deleted successfully!';
                } else {
                    throw new Exception("Failed to delete driver: " . $stmt->error);
                }
                break;
                
            default:
                throw new Exception('Invalid action specified.');
        }
        
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// If not POST request
http_response_code(405);
echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Invalid request method.']);
exit();
?>
