<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin access
requireAdmin();

// Check if vehicle ID is provided
if (!isset($_GET['id'])) {
    header("Location: admin-dashboard.php");
    exit();
}

$vehicle_id = (int)$_GET['id'];

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = getDBConnection();

// Get vehicle details with owner information (FIXED SQL)
// Get vehicle details with owner information (UPDATED SQL)
$stmt = $conn->prepare("
    SELECT v.*, a.fullName as owner_name, a.idNumber as owner_id, a.phone as owner_phone, a.email as owner_email
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    WHERE v.vehicle_id = ?
");

$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    header("Location: admin-dashboard.php");
    exit();
}

// Extract applicant_id from the first query result for subsequent queries
$applicant_id = (int)$vehicle['applicant_id'];

// Get authorized drivers for this vehicle
$stmt = $conn->prepare("SELECT * FROM authorized_driver WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Details - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .details-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .detail-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .detail-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .detail-header h2 {
            color: var(--primary-red);
            margin: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            margin-bottom: 1rem;
        }

        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 500;
        }

        .driver-list {
            margin-top: 1rem;
        }

        .driver-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .driver-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .driver-license {
            color: #666;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        .admin-nav {
            background: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .admin-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .admin-nav a {
            color: var(--primary-red);
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-weight: 500;
            display: inline-block;
        }

        .admin-nav a:hover, .admin-nav a.active {
            background-color: var(--primary-red);
            color: white;
        }

        /* Use shared btn-logout */

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-logo" style="width: 80px;">
                        <a href="admin-dashboard.php">
                            <img src="assets/images/AULogo.png" alt="AULogo">
                        </a>
                    </div>
                    <h1>Vehicle Details</h1>
                </div>
                <div class="header-right">
                    <button onclick="logout()" class="btn btn-logout">Logout</button>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php" class="active">Manage Vehicles</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="details-container">
            <!-- Vehicle Information -->
            <div class="detail-card">
                <div class="detail-header">
                    <h2>Vehicle Information</h2>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Make</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['make']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registration Number</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['regNumber']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Disk Number</div>
                        <div class="info-value">
                            <?php if (!empty($vehicle['diskNumber'])): ?>
                                <?= htmlspecialchars($vehicle['diskNumber']) ?>
                            <?php else: ?>
                                <span class="status-badge status-pending">Not Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registration Date</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['registration_date']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-active">Active</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Owner Information -->
            <div class="detail-card">
                <div class="detail-header">
                    <h2>Owner Information</h2>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['owner_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ID Number</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['owner_id']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['owner_phone']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($vehicle['owner_email']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Authorized Drivers -->
            <div class="detail-card">
                <div class="detail-header">
                    <h2>Authorized Drivers</h2>
                </div>
                <div class="driver-list">
                    <?php if (count($drivers) > 0): ?>
                        <?php foreach ($drivers as $driver): ?>
                            <div class="driver-item">
                                <div class="driver-name"><?= htmlspecialchars($driver['fullname']) ?></div>
                                <div class="driver-license">License: <?= htmlspecialchars($driver['licenseNumber']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No authorized drivers found for this vehicle.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="editVehicle(<?= $vehicle_id ?>)">Edit Vehicle</button>
            <button class="btn btn-danger" onclick="deleteVehicle(<?= $vehicle_id ?>)">Delete Vehicle</button>
        </div>
    </div>

    <script>
        function editVehicle(vehicleId) {
            window.location.href = `edit-vehicle.php?id=${vehicleId}`;
        }

        function deleteVehicle(vehicleId) {
            if (confirm('Are you sure you want to delete this vehicle? This action cannot be undone.')) {
                // Add delete functionality here
                alert('Delete functionality to be implemented');
            }
        }

        function logout() {
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html> 