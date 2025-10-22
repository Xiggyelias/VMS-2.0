<?php
/**
 * Vehicle Management Functions
 * 
 * This file contains all vehicle-related functions including
 * CRUD operations, validation, and business logic.
 */

require_once CONFIG_PATH . '/app.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions/utilities.php';
require_once INCLUDES_PATH . '/functions/auth.php';

/**
 * Add New Vehicle
 * 
 * Registers a new vehicle for the current user.
 * 
 * @param array $vehicleData Vehicle data array
 * @return array Array with 'success' boolean and 'message' string
 */
function addVehicle($vehicleData) {
    try {
        // Check if user can register vehicles
        if (!canRegisterVehicle()) {
            return ['success' => false, 'message' => 'You have reached the maximum number of vehicles allowed for your account type.'];
        }
        
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        // Sanitize inputs
        $make = sanitizeInput($vehicleData['make'], 'string');
        $regNumber = sanitizeInput($vehicleData['regNumber'], 'string');
        
        // Validate required fields
        if (empty($make) || empty($regNumber)) {
            return ['success' => false, 'message' => 'Please fill in all required fields.'];
        }
        
        // Check if registration number already exists
        $checkStmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE regNumber = ?");
        $checkStmt->bind_param("s", $regNumber);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'A vehicle with this registration number already exists.'];
        }
        
        // Get user type to determine status
        $userType = getCurrentUserType();
        $status = ($userType === 'student') ? 'active' : 'pending';
        
        // Insert new vehicle
        $stmt = $conn->prepare("
            INSERT INTO vehicles (applicant_id, make, regNumber, status, registration_date, last_updated) 
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("isss", $userId, $make, $regNumber, $status);
        
        if ($stmt->execute()) {
            $vehicleId = $conn->insert_id;
            
            // If student, deactivate other vehicles
            if ($userType === 'student') {
                $deactivateStmt = $conn->prepare("
                    UPDATE vehicles 
                    SET status = 'inactive', last_updated = NOW() 
                    WHERE applicant_id = ? AND vehicle_id != ?
                ");
                $deactivateStmt->bind_param("ii", $userId, $vehicleId);
                $deactivateStmt->execute();
                $deactivateStmt->close();
            }
            
            $stmt->close();
            $checkStmt->close();
            $conn->close();
            
            return ['success' => true, 'message' => 'Vehicle registered successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to register vehicle. Please try again.'];
        }
        
    } catch (Exception $e) {
        logError("Error adding vehicle: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while registering the vehicle.'];
    }
}

/**
 * Update Vehicle
 * 
 * Updates an existing vehicle's information.
 * 
 * @param int $vehicleId Vehicle ID to update
 * @param array $vehicleData Updated vehicle data
 * @return array Array with 'success' boolean and 'message' string
 */
function updateVehicle($vehicleId, $vehicleData) {
    try {
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        // Sanitize inputs
        $make = sanitizeInput($vehicleData['make'], 'string');
        $regNumber = sanitizeInput($vehicleData['regNumber'], 'string');
        
        // Validate required fields
        if (empty($make) || empty($regNumber)) {
            return ['success' => false, 'message' => 'Please fill in all required fields.'];
        }
        
        // Check if vehicle belongs to current user
        $checkStmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND applicant_id = ?");
        $checkStmt->bind_param("ii", $vehicleId, $userId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Vehicle not found or access denied.'];
        }
        
        // Check if registration number already exists (excluding current vehicle)
        $checkRegStmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE regNumber = ? AND vehicle_id != ?");
        $checkRegStmt->bind_param("si", $regNumber, $vehicleId);
        $checkRegStmt->execute();
        $regResult = $checkRegStmt->get_result();
        
        if ($regResult->num_rows > 0) {
            return ['success' => false, 'message' => 'A vehicle with this registration number already exists.'];
        }
        
        // Update vehicle
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET make = ?, regNumber = ?, last_updated = NOW() 
            WHERE vehicle_id = ? AND applicant_id = ?
        ");
        $stmt->bind_param("ssii", $make, $regNumber, $vehicleId, $userId);
        
        if ($stmt->execute()) {
            $stmt->close();
            $checkStmt->close();
            $checkRegStmt->close();
            $conn->close();
            
            return ['success' => true, 'message' => 'Vehicle updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update vehicle. Please try again.'];
        }
        
    } catch (Exception $e) {
        logError("Error updating vehicle: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while updating the vehicle.'];
    }
}

/**
 * Delete Vehicle
 * 
 * Deletes a vehicle from the system.
 * 
 * @param int $vehicleId Vehicle ID to delete
 * @return array Array with 'success' boolean and 'message' string
 */
function deleteVehicle($vehicleId) {
    try {
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        // Check if vehicle belongs to current user
        $checkStmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND applicant_id = ?");
        $checkStmt->bind_param("ii", $vehicleId, $userId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Vehicle not found or access denied.'];
        }
        
        // Delete vehicle
        $stmt = $conn->prepare("DELETE FROM vehicles WHERE vehicle_id = ? AND applicant_id = ?");
        $stmt->bind_param("ii", $vehicleId, $userId);
        
        if ($stmt->execute()) {
            $stmt->close();
            $checkStmt->close();
            $conn->close();
            
            return ['success' => true, 'message' => 'Vehicle deleted successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete vehicle. Please try again.'];
        }
        
    } catch (Exception $e) {
        logError("Error deleting vehicle: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while deleting the vehicle.'];
    }
}

/**
 * Get User Vehicles
 * 
 * Retrieves all vehicles for the current user.
 * 
 * @return array Array of vehicles
 */
function getUserVehicles() {
    try {
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        $stmt = $conn->prepare("
            SELECT *, DATE_FORMAT(last_updated, '%M %d, %Y %h:%i %p') as formatted_last_updated 
            FROM vehicles 
            WHERE applicant_id = ? 
            ORDER BY status DESC, last_updated DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $vehicles = $result->fetch_all(MYSQLI_ASSOC);
        
        $stmt->close();
        $conn->close();
        
        return $vehicles;
        
    } catch (Exception $e) {
        logError("Error getting user vehicles: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Vehicle by ID
 * 
 * Retrieves a specific vehicle by ID.
 * 
 * @param int $vehicleId Vehicle ID
 * @return array|null Vehicle data or null if not found
 */
function getVehicleById($vehicleId) {
    try {
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        $stmt = $conn->prepare("
            SELECT * FROM vehicles 
            WHERE vehicle_id = ? AND applicant_id = ?
        ");
        $stmt->bind_param("ii", $vehicleId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $vehicle = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        return $vehicle;
        
    } catch (Exception $e) {
        logError("Error getting vehicle by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Search Vehicle by Plate Number
 * 
 * Searches for a vehicle by plate number.
 * 
 * @param string $plateNumber Plate number to search for
 * @return array Array with vehicle information
 */
function searchVehicleByPlate($plateNumber) {
    try {
        $conn = getLegacyDatabaseConnection();
        
        // Sanitize input
        $plateNumber = sanitizeInput($plateNumber, 'string');
        
        if (empty($plateNumber)) {
            return ['success' => false, 'message' => 'Please enter a plate number.'];
        }
        
        $stmt = $conn->prepare("
            SELECT 
                v.vehicle_id,
                v.applicant_id,
                v.regNumber,
                v.make,
                v.owner,
                v.address,
                v.PlateNumber,
                v.registration_date,
                v.disk_number,
                v.status,
                a.idNumber,
                a.phone,
                a.email,
                a.fullName,
                d.fullname as driver_name,
                d.licenseNumber
            FROM vehicles v
            LEFT JOIN applicants a ON v.applicant_id = a.applicant_id
            LEFT JOIN authorized_driver d ON v.vehicle_id = d.vehicle_id
            WHERE v.PlateNumber = ?
        ");
        $stmt->bind_param("s", $plateNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $vehicle = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            
            return [
                'success' => true,
                'data' => $vehicle,
                'isRegistered' => true
            ];
        } else {
            // Log unregistered plate
            logUnregisteredPlate($plateNumber);
            
            $stmt->close();
            $conn->close();
            
            return [
                'success' => false,
                'message' => 'Unregistered vehicle detected',
                'isRegistered' => false
            ];
        }
        
    } catch (Exception $e) {
        logError("Error searching vehicle: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while searching for the vehicle.'];
    }
}

/**
 * Log Unregistered Plate
 * 
 * Logs an unregistered plate number for review.
 * 
 * @param string $plateNumber Plate number to log
 */
function logUnregisteredPlate($plateNumber) {
    try {
        $conn = getLegacyDatabaseConnection();
        
        // Check if already logged
        $checkStmt = $conn->prepare("SELECT id FROM unregistered_plates WHERE plate_number = ?");
        $checkStmt->bind_param("s", $plateNumber);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            // Insert new unregistered plate
            $insertStmt = $conn->prepare("INSERT INTO unregistered_plates (plate_number, detected_at) VALUES (?, NOW())");
            $insertStmt->bind_param("s", $plateNumber);
            $insertStmt->execute();
            $insertStmt->close();
        }
        
        $checkStmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        logError("Error logging unregistered plate: " . $e->getMessage());
    }
}

/**
 * Get Vehicle Statistics
 * 
 * Returns statistics about the current user's vehicles.
 * 
 * @return array Array with vehicle statistics
 */
function getVehicleStatistics() {
    try {
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        // Count active vehicles
        $activeStmt = $conn->prepare("SELECT COUNT(*) AS count FROM vehicles WHERE applicant_id = ? AND status = 'active'");
        $activeStmt->bind_param("i", $userId);
        $activeStmt->execute();
        $activeResult = $activeStmt->get_result();
        $activeCount = $activeResult->fetch_assoc()['count'];
        
        // Count total vehicles
        $totalStmt = $conn->prepare("SELECT COUNT(*) AS count FROM vehicles WHERE applicant_id = ?");
        $totalStmt->bind_param("i", $userId);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        $totalCount = $totalResult->fetch_assoc()['count'];
        
        // Count pending vehicles
        $pendingStmt = $conn->prepare("SELECT COUNT(*) AS count FROM vehicles WHERE applicant_id = ? AND status = 'pending'");
        $pendingStmt->bind_param("i", $userId);
        $pendingStmt->execute();
        $pendingResult = $pendingStmt->get_result();
        $pendingCount = $pendingResult->fetch_assoc()['count'];
        
        $activeStmt->close();
        $totalStmt->close();
        $pendingStmt->close();
        $conn->close();
        
        return [
            'active' => (int) $activeCount,
            'total' => (int) $totalCount,
            'pending' => (int) $pendingCount
        ];
        
    } catch (Exception $e) {
        logError("Error getting vehicle statistics: " . $e->getMessage());
        return ['active' => 0, 'total' => 0, 'pending' => 0];
    }
}

/**
 * Validate Vehicle Data
 * 
 * Validates vehicle data before saving.
 * 
 * @param array $vehicleData Vehicle data to validate
 * @return array Array with 'valid' boolean and 'errors' array
 */
function validateVehicleData($vehicleData) {
    $errors = [];
    
    // Check required fields
    if (empty($vehicleData['make'])) {
        $errors[] = 'Vehicle make is required.';
    }
    
    if (empty($vehicleData['regNumber'])) {
        $errors[] = 'Registration number is required.';
    }
    
    // Validate make (alphanumeric and spaces only)
    if (!empty($vehicleData['make']) && !preg_match('/^[a-zA-Z0-9\s]+$/', $vehicleData['make'])) {
        $errors[] = 'Vehicle make can only contain letters, numbers, and spaces.';
    }
    
    // Validate registration number format
    if (!empty($vehicleData['regNumber'])) {
        $regNumber = strtoupper($vehicleData['regNumber']);
        if (!preg_match('/^[A-Z]{2,3}\s?\d{3,4}$/', $regNumber)) {
            $errors[] = 'Invalid registration number format. Expected format: ABC 123 or ABC123.';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
} 