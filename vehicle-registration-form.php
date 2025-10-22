<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];
$userName = $_SESSION['user_name'];
$applicationStatus = $_SESSION['application_status'] ?? 'draft';

// Get existing application data
$conn = getLegacyDatabaseConnection();
$stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get colleges for dropdown
$colleges = [];
$collegeStmt = $conn->query("SELECT college_name FROM colleges WHERE is_active = 1 ORDER BY college_name");
if ($collegeStmt) {
    while ($row = $collegeStmt->fetch_assoc()) {
        $colleges[] = $row['college_name'];
    }
}

// Get license classes for dropdown
$licenseClasses = [];
$licenseStmt = $conn->query("SELECT class_code, class_description FROM license_classes WHERE is_active = 1 ORDER BY class_code");
if ($licenseStmt) {
    while ($row = $licenseStmt->fetch_assoc()) {
        $licenseClasses[] = $row;
    }
}

// Get authorized drivers
$authorizedDrivers = [];
$driversStmt = $conn->prepare("SELECT * FROM authorized_drivers WHERE applicant_id = ? ORDER BY created_at");
$driversStmt->bind_param('i', $userId);
$driversStmt->execute();
$driversResult = $driversStmt->get_result();
if ($driversResult) {
    while ($row = $driversResult->fetch_assoc()) {
        $authorizedDrivers[] = $row;
    }
}
$driversStmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Registration Form - Africa University</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .registration-form {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fafafa;
        }
        
        .form-section h3 {
            color: var(--primary-red);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-red);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-red);
            outline: none;
        }
        
        .form-group input[readonly] {
            background-color: #f5f5f5;
            color: #666;
        }
        
        .required::after {
            content: " *";
            color: var(--primary-red);
        }
        
        .authorized-drivers-section {
            border: 2px dashed #ccc;
            padding: 20px;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .driver-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            position: relative;
        }
        
        .remove-driver {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-red);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-driver-btn {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
        }
        
        .add-driver-btn:hover {
            background: #c41e3a;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary-red);
            color: white;
        }
        
        .btn-primary:hover {
            background: #c41e3a;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-red);
            transition: width 0.3s ease;
        }
        
        .auto-save-indicator {
            text-align: center;
            color: #666;
            font-style: italic;
            margin-bottom: 20px;
        }
        
        .user-info-header {
            background: linear-gradient(135deg, var(--primary-red), #c41e3a);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .user-info-header h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .user-info-header p {
            margin: 5px 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="registration-form">
        <!-- User Info Header -->
        <div class="user-info-header">
            <h2>🚗 Vehicle Registration System</h2>
            <p><strong>Welcome:</strong> <?= htmlspecialchars($userName) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($userEmail) ?></p>
            <p><strong>Status:</strong> <?= ucfirst($applicationStatus) ?></p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
        </div>
        <div class="auto-save-indicator">
            <i class="fa fa-save"></i> Auto-saving your progress...
        </div>

        <form id="vehicleRegistrationForm" method="POST" action="submit-vehicle-registration.php">
            <input type="hidden" name="applicant_id" value="<?= $userId ?>">
            
            <!-- Applicant Information Section -->
            <div class="form-section">
                <h3><i class="fa fa-user"></i> Applicant Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Full Names</label>
                        <input type="text" name="fullName" value="<?= htmlspecialchars($userData['fullName'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="required">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dateOfBirth" value="<?= $userData['dateOfBirth'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="required">College</label>
                        <select name="college" required>
                            <option value="">Select College</option>
                            <?php foreach ($colleges as $college): ?>
                                <option value="<?= htmlspecialchars($college) ?>" 
                                    <?= ($userData['college'] ?? '') === $college ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($college) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="required">ID/Passport Number</label>
                        <input type="text" name="idNumber" value="<?= htmlspecialchars($userData['idNumber'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Driver's License Information Section -->
            <div class="form-section">
                <h3><i class="fa fa-id-card"></i> Driver's License Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Driver's License Number</label>
                        <input type="text" name="licenseNumber" value="<?= htmlspecialchars($userData['licenseNumber'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="required">License Class</label>
                        <select name="licenseClass" required>
                            <option value="">Select Class</option>
                            <?php foreach ($licenseClasses as $class): ?>
                                <option value="<?= htmlspecialchars($class['class_code']) ?>" 
                                    <?= ($userData['licenseClass'] ?? '') === $class['class_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['class_code']) ?> - <?= htmlspecialchars($class['class_description']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Date Issued</label>
                        <input type="date" name="licenseDate" value="<?= $userData['licenseDate'] ?? '' ?>" required>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information Section -->
            <div class="form-section">
                <h3><i class="fa fa-car"></i> Vehicle Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Registration Number</label>
                        <input type="text" name="vehicleRegistrationNumber" value="<?= htmlspecialchars($userData['vehicleRegistrationNumber'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="required">Make</label>
                        <input type="text" name="vehicleMake" value="<?= htmlspecialchars($userData['vehicleMake'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="vehicleModel" value="<?= htmlspecialchars($userData['vehicleModel'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="required">Registered Owner</label>
                        <input type="text" name="registeredOwner" value="<?= htmlspecialchars($userData['registeredOwner'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Address</label>
                        <textarea name="vehicleAddress" rows="3" required><?= htmlspecialchars($userData['vehicleAddress'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="required">Plate Number</label>
                        <input type="text" name="plateNumber" value="<?= htmlspecialchars($userData['plateNumber'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Authorized Drivers Section -->
            <div class="form-section authorized-drivers-section">
                <h3><i class="fa fa-users"></i> Authorized Drivers</h3>
                <div id="authorizedDriversList">
                    <?php if (empty($authorizedDrivers)): ?>
                        <p>No authorized drivers added yet. Click the button below to add drivers.</p>
                    <?php else: ?>
                        <?php foreach ($authorizedDrivers as $driver): ?>
                            <div class="driver-item" data-driver-id="<?= $driver['driver_id'] ?>">
                                <button type="button" class="remove-driver" onclick="removeDriver(<?= $driver['driver_id'] ?>)">
                                    <i class="fa fa-times"></i>
                                </button>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" name="drivers[<?= $driver['driver_id'] ?>][fullName]" 
                                               value="<?= htmlspecialchars($driver['fullName']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>License Number</label>
                                        <input type="text" name="drivers[<?= $driver['driver_id'] ?>][licenseNumber]" 
                                               value="<?= htmlspecialchars($driver['licenseNumber']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Contact Information</label>
                                        <input type="text" name="drivers[<?= $driver['driver_id'] ?>][contactInfo]" 
                                               value="<?= htmlspecialchars($driver['contactInfo'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-driver-btn" onclick="addDriver()">
                    <i class="fa fa-plus"></i> Add Authorized Driver
                </button>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="saveProgress()">
                    <i class="fa fa-save"></i> Save Progress
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>

    <script>
        let driverCounter = <?= count($authorizedDrivers) ?>;
        let autoSaveTimer;

        // Auto-save functionality
        function setupAutoSave() {
            const form = document.getElementById('vehicleRegistrationForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(saveProgress, 2000); // Auto-save after 2 seconds of inactivity
                });
            });
        }

        // Save progress
        function saveProgress() {
            const formData = new FormData(document.getElementById('vehicleRegistrationForm'));
            formData.append('action', 'save_progress');
            
            fetch('save-registration-progress.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProgressBar();
                    showNotification('Progress saved successfully!', 'success');
                } else {
                    showNotification('Failed to save progress: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                showNotification('Error saving progress', 'error');
            });
        }

        // Add authorized driver
        function addDriver() {
            driverCounter++;
            const driverHtml = `
                <div class="driver-item" data-driver-id="new_${driverCounter}">
                    <button type="button" class="remove-driver" onclick="removeDriver('new_${driverCounter}')">
                        <i class="fa fa-times"></i>
                    </button>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="drivers[new_${driverCounter}][fullName]" required>
                        </div>
                        <div class="form-group">
                            <label>License Number</label>
                            <input type="text" name="drivers[new_${driverCounter}][licenseNumber]" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Contact Information</label>
                            <input type="text" name="drivers[new_${driverCounter}][contactInfo]">
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('authorizedDriversList').insertAdjacentHTML('beforeend', driverHtml);
            setupAutoSave(); // Re-setup auto-save for new inputs
        }

        // Remove authorized driver
        function removeDriver(driverId) {
            if (confirm('Are you sure you want to remove this driver?')) {
                if (driverId.toString().startsWith('new_')) {
                    // Remove from DOM if it's a new driver
                    document.querySelector(`[data-driver-id="${driverId}"]`).remove();
                } else {
                    // Delete from database if it's an existing driver
                    fetch('remove-authorized-driver.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ driver_id: driverId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelector(`[data-driver-id="${driverId}"]`).remove();
                            showNotification('Driver removed successfully!', 'success');
                        } else {
                            showNotification('Failed to remove driver: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Remove error:', error);
                        showNotification('Error removing driver', 'error');
                    });
                }
            }
        }

        // Update progress bar
        function updateProgressBar() {
            const form = document.getElementById('vehicleRegistrationForm');
            const requiredFields = form.querySelectorAll('[required]');
            const filledFields = Array.from(requiredFields).filter(field => field.value.trim() !== '');
            const progress = (filledFields.length / requiredFields.length) * 100;
            
            document.getElementById('progressFill').style.width = progress + '%';
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 6px;
                color: white;
                font-weight: 600;
                z-index: 1000;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoSave();
            updateProgressBar();
        });
    </script>
</body>
</html>


