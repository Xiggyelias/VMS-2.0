<?php
function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

try {
    $conn = getDBConnection();
    
    // Create notifications table with role-aware fields
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('new-registration', 'update', 'transfer-request', 'disk-assignment', 'driver-assignment') NOT NULL,
        role ENUM('student', 'staff', 'guest') NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES applicants(applicant_id)
    )";
    
    if ($conn->query($sql)) {
        echo "Notifications table created successfully";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 