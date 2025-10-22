<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

header('Content-Type: application/json; charset=UTF-8');

// Make mysqli throw exceptions on errors so we can return JSON instead of fatals
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (align with app session structure)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please log in to continue.']);
    exit();
}

// Use app-configured database connection for consistency
function getDBConnection() {
    return getLegacyDatabaseConnection();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => ''];
    
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getDBConnection();
        $conn->set_charset('utf8mb4');
        $applicant_id = getCurrentUserId();
        
        switch ($action) {
            case 'add':
                $make = trim($_POST['make'] ?? '');
                $regNumber = trim($_POST['regNumber'] ?? '');
                
                if (empty($make) || empty($regNumber)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                // Check if registration number already exists
                $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE regNumber = ?");
                $stmt->bind_param("s", $regNumber);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('This registration number is already registered.');
                }
                $stmt->close();
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Get IDs of vehicles that will be deactivated
                    $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE applicant_id = ? AND status = 'active'");
                    $stmt->bind_param("i", $applicant_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $deactivated_ids = [];
                    while ($row = $result->fetch_assoc()) {
                        $deactivated_ids[] = $row['vehicle_id'];
                    }
                    $stmt->close();
                    
                    // Deactivate any existing active vehicles for this applicant
                    $stmt = $conn->prepare("UPDATE vehicles SET status = 'inactive', last_updated = NOW() WHERE applicant_id = ? AND status = 'active'");
                    $stmt->bind_param("i", $applicant_id);
                    $stmt->execute();
                    $deactivated_count = $stmt->affected_rows;
                    $stmt->close();

                    // Insert new vehicle with active status (minimal required columns)
                    $stmt = $conn->prepare("INSERT INTO vehicles (applicant_id, regNumber, make, status, last_updated) VALUES (?, ?, ?, 'active', NOW())");
                    $stmt->bind_param("iss", $applicant_id, $regNumber, $make);
                    
                    if ($stmt->execute()) {
                        $vehicle_id = $conn->insert_id;
                        $conn->commit();
                        
                        $message = 'Vehicle added successfully!';
                        if ($deactivated_count > 0) {
                            $message .= " ($deactivated_count previous vehicle(s) deactivated)";
                        }
                        
                        $response['status'] = 'success';
                        $response['success'] = true;
                        $response['message'] = $message;
                        
                        // Return vehicle data for UI update
                        $response['vehicle'] = [
                            'vehicle_id' => $vehicle_id,
                            'make' => $make,
                            'regNumber' => $regNumber,
                            'status' => 'active',
                            'formatted_last_updated' => date('M j, Y g:i A')
                        ];
                        
                        // Return IDs of deactivated vehicles for UI update
                        $response['deactivated_vehicle_ids'] = $deactivated_ids;
                        $response['deactivated_count'] = $deactivated_count;
                    } else {
                        throw new Exception("Failed to add vehicle: " . $stmt->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;

            case 'delete_driver':
                // Support AJAX delete for drivers from user-dashboard
                // Expect id parameter (driver Id) and restrict by current user's ownership via vehicle link is not guaranteed here,
                // so we restrict by applicant_id directly on authorized_driver table.
                $driver_id = intval($_POST['id'] ?? 0);
                if ($driver_id <= 0) {
                    http_response_code(400);
                    throw new Exception('Invalid driver ID.');
                }

                $stmt = $conn->prepare("DELETE FROM authorized_driver WHERE Id = ? AND applicant_id = ?");
                $stmt->bind_param("ii", $driver_id, $applicant_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Driver deleted';
                    // For compatibility with frontend expecting { success: true }
                    $response['success'] = true;
                } else {
                    http_response_code(404);
                    $response['status'] = 'error';
                    $response['message'] = 'Driver not found or not authorized';
                    $response['success'] = false;
                }
                $stmt->close();
                break;
                
            case 'edit':
                $vehicle_id = intval($_POST['id'] ?? 0);
                $make = trim($_POST['make'] ?? '');
                $regNumber = trim($_POST['regNumber'] ?? '');
                
                if ($vehicle_id <= 0 || empty($make) || empty($regNumber)) {
                    throw new Exception('Please fill in all required fields.');
                }
                
                // Check if registration number already exists for other vehicles
                $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE regNumber = ? AND vehicle_id != ?");
                $stmt->bind_param("si", $regNumber, $vehicle_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('This registration number is already registered to another vehicle.');
                }
                $stmt->close();
                
                // Verify vehicle ownership
                $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND applicant_id = ?");
                $stmt->bind_param("ii", $vehicle_id, $applicant_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    throw new Exception('Vehicle not found or not authorized.');
                }
                $stmt->close();
                
                // Update vehicle
                $stmt = $conn->prepare("UPDATE vehicles SET make = ?, regNumber = ?, last_updated = NOW() WHERE vehicle_id = ? AND applicant_id = ?");
                $stmt->bind_param("ssii", $make, $regNumber, $vehicle_id, $applicant_id);
                
                if ($stmt->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Vehicle updated successfully!';
                } else {
                    throw new Exception("Failed to update vehicle: " . $stmt->error);
                }
                break;
                
            case 'delete':
                $vehicle_id = intval($_POST['id'] ?? 0);
                if ($vehicle_id <= 0) {
                    http_response_code(400);
                    throw new Exception('Invalid vehicle ID.');
                }

                // Wrap delete operations in a transaction
                $conn->begin_transaction();
                try {
                    // Verify vehicle ownership and get current status
                    $stmt = $conn->prepare("SELECT status FROM vehicles WHERE vehicle_id = ? AND applicant_id = ?");
                    $stmt->bind_param("ii", $vehicle_id, $applicant_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        http_response_code(404);
                        throw new Exception('Vehicle not found or not authorized.');
                    }
                    $vehicleRow = $result->fetch_assoc();
                    $wasActive = strtolower($vehicleRow['status'] ?? '') === 'active';
                    $stmt->close();
                    $stmt = null;

                    // Delete authorized drivers linked to the vehicle
                    $stmt = $conn->prepare("DELETE FROM authorized_driver WHERE vehicle_id = ?");
                    $stmt->bind_param("i", $vehicle_id);
                    $stmt->execute();
                    $stmt->close();
                    $stmt = null;

                    // Delete the vehicle
                    $stmt = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ? AND applicant_id = ?");
                    $stmt->bind_param("ii", $vehicle_id, $applicant_id);
                    $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                    $stmt = null;

                    if ($affected <= 0) {
                        http_response_code(400);
                        throw new Exception('Delete failed');
                    }

                    // If the deleted vehicle was active, reactivate the most recently updated remaining vehicle
                    if ($wasActive) {
                        $stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE applicant_id = ? ORDER BY last_updated DESC LIMIT 1");
                        $stmt->bind_param("i", $applicant_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $prevVehicleId = intval($row['vehicle_id']);
                            $stmt->close();
                            $stmt = null;
                            $stmt = $conn->prepare("UPDATE vehicles SET status = 'active', last_updated = NOW() WHERE vehicle_id = ?");
                            $stmt->bind_param("i", $prevVehicleId);
                            $stmt->execute();
                            // Close the UPDATE statement explicitly
                            $stmt->close();
                            $stmt = null;
                        } else {
                            $stmt->close();
                            $stmt = null;
                        }
                    }

                    $conn->commit();
                    $response['status'] = 'success';
                    $response['message'] = 'Vehicle deleted';
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Vehicle delete error: ' . $e->getMessage());
                    // Preserve any 4xx code set earlier (e.g., 400/404); otherwise set sensible defaults
                    if ($e instanceof Exception) {
                        $current = http_response_code();
                        if ($current < 400 || $current > 599) {
                            http_response_code(400);
                        }
                    } else {
                        http_response_code(500);
                    }
                    $response['status'] = 'error';
                    $response['message'] = isDevelopment() ? $e->getMessage() : 'Delete failed';
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
        
    } catch (Throwable $e) {
        $code = $e instanceof Exception ? 400 : 500;
        http_response_code($code);
        $response['status'] = 'error';
        $response['message'] = isDevelopment() ? ($e->getMessage()) : 'Server error. Please try again.';
        // Debug logging for development
        error_log('vehicle_operations error: ' . $e->getMessage());
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// If not POST request
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
exit();
?> 