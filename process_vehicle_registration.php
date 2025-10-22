<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

session_start();
require_once 'vehicleregistrationsystem.php';

// Get database connection
$conn = getDBConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Validate and sanitize input
    $applicant_id = filter_input(INPUT_POST, 'applicant_id', FILTER_VALIDATE_INT);
    $vehicle_data = json_decode($_POST['vehicle_data'], true);
    
    if (!$applicant_id || !$vehicle_data) {
        throw new Exception("Invalid input data");
    }

    // Get applicant type
    $stmt = $conn->prepare("SELECT registrantType FROM applicants WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applicant = $result->fetch_assoc();

    if (!$applicant) {
        throw new Exception("Applicant not found");
    }

    // Handle student vehicle registration (only one active vehicle allowed)
    if ($applicant['registrant_type'] === 'student') {
        // Deactivate all existing vehicles for this student
        $stmt = $conn->prepare("UPDATE vehicles SET status = 'inactive' WHERE applicant_id = ?");
        $stmt->bind_param("i", $applicant_id);
        $stmt->execute();
    }

    // Insert new vehicle
    $stmt = $conn->prepare("
        INSERT INTO vehicles (
            applicant_id, 
            regNumber, 
            make, 
            owner, 
            address, 
            PlateNumber,
            status,
            registration_date,
            last_updated
        ) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ");

    $stmt->bind_param(
        "isssss",
        $applicant_id,
        $vehicle_data['regNumber'],
        $vehicle_data['make'],
        $vehicle_data['owner'],
        $vehicle_data['address'],
        $vehicle_data['PlateNumber']
    );

    if (!$stmt->execute()) {
        throw new Exception("Error inserting vehicle: " . $stmt->error);
    }

    $vehicle_id = $conn->insert_id;

    // Insert authorized drivers if any
    if (!empty($vehicle_data['drivers'])) {
        $driver_stmt = $conn->prepare("
            INSERT INTO authorized_driver (
                vehicle_id, 
                fullname, 
                licenseNumber, 
                contact
            ) VALUES (?, ?, ?, ?)
        ");

        foreach ($vehicle_data['drivers'] as $driver) {
            if (!empty($driver['fullName']) && !empty($driver['licenseNumber'])) {
                $contact = $driver['contact'] ?? '';
                $driver_stmt->bind_param(
                    "isss",
                    $vehicle_id,
                    $driver['fullName'],
                    $driver['licenseNumber'],
                    $contact
                );

                if (!$driver_stmt->execute()) {
                    throw new Exception("Error inserting driver: " . $driver_stmt->error);
                }
            }
        }
    }

    // Create notification for admin
    $notification_stmt = $conn->prepare("
        INSERT INTO notifications (
            type,
            message,
            created_at,
            is_read
        ) VALUES (
            'new_registration',
            ?,
            NOW(),
            FALSE
        )
    ");

    $notification_message = sprintf(
        "New vehicle registration: %s by %s (%s)",
        $vehicle_data['make'],
        $vehicle_data['owner'],
        ucfirst($applicant['registrant_type'])
    );

    $notification_stmt->bind_param("s", $notification_message);
    $notification_stmt->execute();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Vehicle registered successfully',
        'vehicle_id' => $vehicle_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log error
    error_log("Vehicle registration error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?> 