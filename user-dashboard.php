<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require authentication
requireAuth();

// Get current user data from session
$user_id = getCurrentUserId();
$user_type = getCurrentUserType();
$user_email = getCurrentUserEmail();
$user_name = getCurrentUserName();

if (!$user_id) {
    userLogout();
}

// Use the new database connection function
$conn = getLegacyDatabaseConnection();

// Fetch applicant info
$stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Gate: per requirement, only force setup for Student users missing a valid 6-digit reg number
$needsSetup = false;
if ($user) {
    $type = strtolower(trim($user['registrantType'] ?? ''));
    if ($type === 'student') {
        $needsSetup = !preg_match('/^\d{6}$/', (string)($user['studentRegNo'] ?? ''));
    }
}

if ($needsSetup) {
    // Redirect back to login to trigger role selection
    header('Location: login.php?requires_type_selection=1');
    exit();
}

// Get registrant type
$registrantType = $user['registrantType'] ?? 'guest';

// Count active vehicles
$stmt = $conn->prepare("SELECT COUNT(*) AS active_vehicle_count FROM vehicles WHERE applicant_id = ? AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_vehicle_count = $stmt->get_result()->fetch_assoc()['active_vehicle_count'] ?? 0;
$stmt->close();

// Count registered vehicles
$stmt = $conn->prepare("SELECT COUNT(*) AS vehicle_count FROM vehicles WHERE applicant_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicle_count = $stmt->get_result()->fetch_assoc()['vehicle_count'] ?? 0;
$stmt->close();

// Count authorized drivers (include vehicle-linked and legacy applicant-linked)
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ad.Id) AS driver_count
    FROM authorized_driver ad
    LEFT JOIN vehicles v ON ad.vehicle_id = v.vehicle_id
    WHERE v.applicant_id = ? OR ad.applicant_id = ?
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$driver_count = $stmt->get_result()->fetch_assoc()['driver_count'] ?? 0;
$stmt->close();

// Fetch all vehicles for this applicant
$stmt = $conn->prepare("SELECT *, DATE_FORMAT(last_updated, '%M %d, %Y %h:%i %p') as formatted_last_updated FROM vehicles WHERE applicant_id = ? ORDER BY status DESC, last_updated DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch all authorized drivers for this applicant (vehicle-linked and legacy applicant-linked)
$stmt = $conn->prepare("\n    SELECT DISTINCT ad.Id, ad.fullname, ad.licenseNumber, ad.contact, ad.vehicle_id, v.regNumber\n    FROM authorized_driver ad\n    LEFT JOIN vehicles v ON ad.vehicle_id = v.vehicle_id\n    WHERE v.applicant_id = ? OR ad.applicant_id = ?\n    ORDER BY ad.fullname ASC\n");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Close connection
$conn->close();

// Generate CSRF token for forms
$csrfToken = SecurityMiddleware::generateCSRFToken();

// echo '<pre>';
// print_r($user);
// print_r($drivers);
// print_r($vehicles);
// echo '</pre>';


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Vehicle Registration System</title>
    <?php includeCommonAssets(); ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        /* Header Enhancements */
        .header {
            background: linear-gradient(90deg, rgba(208,0,0,1) 0%, rgba(176,0,0,1) 100%);
        }
        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .header-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.95rem;
        }
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
            border: 1px solid #eef1f4;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-box::after {
            content: '';
            position: absolute;
            right: -20px;
            top: -20px;
            width: 80px;
            height: 80px;
            background: radial-gradient(rgba(208,0,0,0.15), transparent 70%);
            border-radius: 50%;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        }
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: 0.35rem;
        }
        .stat-label {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .management-section {
            background-color: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #eef1f4;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }

        .management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .management-header h2 {
            margin: 0;
            color: var(--primary-red);
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Use shared .btn-logout style from assets with slight refinement */
        .btn-logout {
            border-width: 2px;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
        }

        .owner-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-red);
        }

        .info-value {
            color: var(--black);
        }

        /* Enhanced Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 25px;
            border-radius: 12px;
            width: 95%;
            max-width: 700px;
            position: relative;
            max-height: 96vh;
            overflow-y: auto;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-red);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            padding: 5px;
            line-height: 1;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background-color: #f8f9fa;
            color: var(--primary-red);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        .form-input:focus {
            border-color: var(--primary-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.1);
        }

        .form-input::placeholder {
            color: #999;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-red);
            color: white;
        }

        .btn-primary:hover {
            background-color: #b00000;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            display: none;
            animation: alertSlideIn 0.3s ease-out;
        }

        @keyframes alertSlideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .table tr:nth-child(even) { background-color: #fafafa; }
        .table tr:hover { background-color: #f5f7fb; }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
        }

        .btn-icon {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-red);
            color: white;
        }

        .btn-primary:hover {
            background-color: #b00000;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .text-center {
            text-align: center;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-red);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Select Input Specific Styles */
        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }

        select.form-input:focus {
            border-color: var(--primary-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.1);
        }

        .required {
            color: var(--primary-red);
            margin-left: 4px;
        }
        
        .alert {
            display: none;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status-active { background-color: #d1fae5; color: #065f46; }

        .status-inactive { background-color: #fee2e2; color: #991b1b; }

        .last-updated {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            .user-stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .management-section {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            .management-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                padding-bottom: 0.5rem;
            }
            .owner-info {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            .table-container {
                overflow-x: auto;
                margin-top: 0.5rem;
            }
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.95rem;
            }
            .modal-content {
                width: 100%;
                padding: 10px;
                max-width: 98vw;
            }
            .modal-header h2 {
                font-size: 1.1rem;
            }
            .form-group label, .form-input, .btn {
                font-size: 1rem;
            }
            .modal-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            .btn, .btn-primary, .btn-secondary, .btn-danger {
                width: 100%;
                font-size: 1.1rem;
                padding: 1rem;
            }
            .btn-logout { position: static; width: 100%; margin-top: 0.75rem; }
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                        <a href="user-dashboard.php">
                            <img src="AULogo.png" alt="AULogo">
                        </a>
                    </div>
                    <div>
                        <h1>My Dashboard</h1>
                        <div class="header-subtitle">Welcome, <?php echo htmlspecialchars($user_name ?? $user_email ?? 'User'); ?></div>
                    </div>
                </div>
                <button onclick="logout()" class="btn btn-logout">Logout</button>
            </div>
        </div>
    </header>

    <div class="container">

        <!-- Owner Information -->
        <div class="management-section">
            <div class="management-header">
                <h2>Vehicle Owner Information</h2>
                <button class="btn btn-primary" onclick="openModal('editOwnerModal')"><i class="fas fa-pen"></i> Edit Info</button>
            </div>
            <div class="owner-info">
    <div class="info-item">
        <span class="info-label">Full Name</span>
        <span class="info-value"><?= isset($user['fullName']) ? htmlspecialchars($user['fullName']) : 'N/A' ?></span>
    </div>
    <div class="info-item">
        <span class="info-label">ID/Passport</span>
        <span class="info-value"><?= isset($user['idNumber']) ? htmlspecialchars($user['idNumber']) : 'N/A' ?></span>
    </div>
    <div class="info-item">
        <span class="info-label">Phone Number</span>
        <span class="info-value"><?= isset($user['phone']) ? htmlspecialchars($user['phone']) : 'N/A' ?></span>
    </div>
                <?php if (strtolower($registrantType) !== 'guest'): ?>
    <div class="info-item">
        <span class="info-label">College</span>
        <span class="info-value"><?= isset($user['college']) ? htmlspecialchars($user['college']) : 'N/A' ?></span>
                </div>
                <?php endif; ?>
    </div>
</div>

        <!-- Statistics Overview -->
        <div class="user-stats">
            <div class="stat-box">
                <div id="vehiclesCount" class="stat-number"><?= $vehicle_count ?></div>
                <div class="stat-label">My Registered Vehicles</div>
            </div>
            <div class="stat-box">
                <div id="driversCount" class="stat-number"><?= $driver_count ?></div>
                <div class="stat-label">Authorized Drivers</div>
            </div>
        </div>

        <!-- Vehicle Management -->
        <div class="management-section">
            <div class="management-header">
                <h2>My Registered Vehicles</h2>
                <button class="btn btn-primary" onclick="openModal('addVehicleModal')"><i class="fas fa-plus"></i> Add New Vehicle</button>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Registration Number</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vehicles) > 0): ?>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <tr id="vehicle-<?= (int)$vehicle['vehicle_id'] ?>">
                                    <td><?= htmlspecialchars($vehicle['make'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($vehicle['regNumber'] ?? '—') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($vehicle['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($vehicle['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="last-updated">
                                            <?= htmlspecialchars($vehicle['formatted_last_updated'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-primary btn-icon" 
                                                onclick="editVehicle(<?= (int)$vehicle['vehicle_id'] ?>, '<?= htmlspecialchars($vehicle['make'] ?? '') ?>', '<?= htmlspecialchars($vehicle['regNumber'] ?? '') ?>')">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-icon" 
                                                onclick="deleteVehicle(<?= (int)$vehicle['vehicle_id'] ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No registered vehicles found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Driver Management -->
        <div class="management-section">
            <div class="management-header">
                <h2>Authorized Drivers</h2>
                <button class="btn btn-primary" onclick="openDriverModal()">
                    <i class="fas fa-user-plus"></i> Add New Driver
                </button>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>License Number</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($drivers)): ?>
                            <?php foreach ($drivers as $driver): ?>
                                <tr id="driver-row-<?= (int)$driver['Id'] ?>">
                                    <td><?= htmlspecialchars($driver['fullname']) ?></td>
                                    <td><?= htmlspecialchars($driver['licenseNumber']) ?></td>
                                    <td><?= htmlspecialchars($driver['contact'] ?? 'N/A') ?></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-primary btn-icon" 
                                                onclick="editDriver(<?= $driver['Id'] ?>, '<?= htmlspecialchars($driver['fullname']) ?>', '<?= htmlspecialchars($driver['licenseNumber']) ?>', '<?= htmlspecialchars($driver['contact'] ?? '') ?>')">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-icon" 
                                                onclick="deleteDriver(<?= $driver['Id'] ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No authorized drivers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Vehicle Modal -->
    <div id="addVehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Vehicle</h2>
                <button type="button" class="close" onclick="closeModal('addVehicleModal')">&times;</button>
            </div>
            <div id="vehicleAlert" class="alert"></div>
            <?php if ($registrantType === 'student' && $active_vehicle_count > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You already have an active vehicle. Registering a new vehicle will deactivate your current active vehicle.
            </div>
            <?php endif; ?>
            <form id="addVehicleForm" onsubmit="return handleVehicleSubmit(event)">
                <div class="form-group">
                    <label for="make">Vehicle Make <span class="required">*</span></label>
                    <input type="text" id="make" name="make" class="form-input" required 
                           placeholder="Enter vehicle make (e.g., Toyota, Honda)">
                </div>
                <div class="form-group">
                    <label for="regNumber">Registration Number <span class="required">*</span></label>
                    <input type="text" id="regNumber" name="regNumber" class="form-input" required 
                           placeholder="Enter registration number">
                </div>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addVehicleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" 
                        <?php if ($registrantType === 'student' && $active_vehicle_count > 0): ?>
                        title="This will deactivate your current active vehicle"
                        <?php endif; ?>>
                        <span class="loading-spinner"></span>
                        <span class="button-text" data-original-text="Add Vehicle">Add Vehicle</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div id="editVehicleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Vehicle</h2>
                <button type="button" class="close" onclick="closeModal('editVehicleModal')">&times;</button>
            </div>
            <div id="editVehicleAlert" class="alert"></div>
            <form id="editVehicleForm" onsubmit="return handleEditVehicleSubmit(event)">
                <input type="hidden" id="edit_vehicle_id" name="id">
                <div class="form-group">
                    <label for="edit_make">Vehicle Make <span class="required">*</span></label>
                    <input type="text" id="edit_make" name="make" class="form-input" required 
                           placeholder="Enter vehicle make (e.g., Toyota, Honda)">
                </div>
                <div class="form-group">
                    <label for="edit_regNumber">Registration Number <span class="required">*</span></label>
                    <input type="text" id="edit_regNumber" name="regNumber" class="form-input" required 
                           placeholder="Enter registration number">
                </div>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editVehicleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="loading-spinner"></span>
                        <span class="button-text" data-original-text="Update Vehicle">Update Vehicle</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Driver Modal -->
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Driver</h2>
                <button type="button" class="close" onclick="closeDriverModal()">&times;</button>
            </div>
            <div id="driverAlert" class="alert"></div>
            <form id="driverForm" onsubmit="return handleDriverSubmit(event)">
                <input type="hidden" id="driver_id" name="driver_id">
                
                <div class="form-group">
                    <label for="fullname">Full Name <span class="required">*</span></label>
                    <input type="text" id="fullname" name="fullname" class="form-input" required 
                           placeholder="Enter driver's full name">
                </div>

                <div class="form-group">
                    <label for="licenseNumber">License Number <span class="required">*</span></label>
                    <input type="text" id="licenseNumber" name="licenseNumber" class="form-input" required 
                           placeholder="Enter driver's license number">
                </div>

                <div class="form-group">
                    <label for="contact">Phone Number</label>
                    <input type="tel" id="contact" name="contact" class="form-input" 
                           placeholder="Enter driver's phone number">
                </div>

                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeDriverModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="loading-spinner"></span>
                        <span class="button-text" data-original-text="Save Driver">Save Driver</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Owner Info Modal -->
    <div id="editOwnerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Owner Information</h2>
                <button type="button" class="close" onclick="closeModal('editOwnerModal')">&times;</button>
            </div>
            <div id="ownerAlert" class="alert"></div>
            <form id="editOwnerForm" onsubmit="return handleOwnerSubmit(event)">
                <div class="form-group">
                    <label for="owner_fullName">Full Name <span class="required">*</span></label>
                    <input type="text" id="owner_fullName" name="fullName" class="form-input" required 
                           value="<?= htmlspecialchars($user['fullName'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="owner_idNumber">ID/Passport</label>
                    <input type="text" id="owner_idNumber" name="idNumber" class="form-input" 
                           value="<?= htmlspecialchars($user['idNumber'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="owner_phone">Phone Number</label>
                    <input type="tel" id="owner_phone" name="phone" class="form-input" 
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <?php if (strtolower($registrantType) !== 'guest'): ?>
                <div class="form-group">
                    <label for="owner_college">College</label>
                    <input type="text" id="owner_college" name="college" class="form-input" 
                           value="<?= htmlspecialchars($user['college'] ?? '') ?>">
                </div>
                <?php endif; ?>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editOwnerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="loading-spinner"></span>
                        <span class="button-text" data-original-text="Save Changes">Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function logout() {
            window.location.href = 'logout.php';
        }

        // ---------- Live UI helpers ----------
        function setCount(elId, newVal) {
            const el = document.getElementById(elId);
            if (el) el.textContent = String(newVal);
        }
        function getCount(elId) {
            const el = document.getElementById(elId);
            return el ? parseInt(el.textContent || '0', 10) || 0 : 0;
        }
        function incCount(elId, delta) {
            setCount(elId, getCount(elId) + delta);
        }
        function ensureEmptyRow(tableSelector, colSpan, message) {
            const tbody = document.querySelector(tableSelector);
            if (!tbody) return;
            const hasRows = tbody.querySelectorAll('tr').length > 0;
            if (!hasRows) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = colSpan;
                td.className = 'text-center';
                td.textContent = message;
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
        }

        function showAlert(type, message, modalId = null) {
            try {
                // Default to the main alert box if no modalId is provided
                const alertId = modalId ? `${modalId}Alert` : 'alertBox';
                let alertBox = document.getElementById(alertId);
                
                // Create alert box if it doesn't exist
                if (!alertBox) {
                    // Try to find a container for the alert
                    let container = document.querySelector('.modal-content');
                    if (!container) {
                        container = document.body;
                    }
                    
                    // Create the alert element
                    alertBox = document.createElement('div');
                    alertBox.id = alertId;
                    alertBox.className = type === 'success' ? 'alert alert-success' : 'alert alert-danger';
                    alertBox.style.display = 'block';
                    alertBox.style.margin = '10px';
                    alertBox.style.padding = '10px';
                    alertBox.style.borderRadius = '4px';
                    
                    // Prepend to container or append to body
                    if (container) {
                        container.prepend(alertBox);
                    } else {
                        document.body.prepend(alertBox);
                    }
                }
                
                // Update alert content and style
                alertBox.className = type === 'success' ? 'alert alert-success' : 'alert alert-danger';
                alertBox.textContent = message;
                alertBox.style.display = 'block';
                
                // Ensure alert is visible
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Hide alert after 5 seconds
                setTimeout(() => {
                    if (alertBox) {
                        alertBox.style.display = 'none';
                    }
                }, 5000);
                
            } catch (error) {
                console.error('Error showing alert:', error);
                // Fallback to browser alert if something goes wrong
                alert(`${type.toUpperCase()}: ${message}`);
            }
        }

        function showLoading(button) {
            const spinner = button.querySelector('.loading-spinner');
            const buttonText = button.querySelector('.button-text');
            spinner.style.display = 'inline-block';
            buttonText.textContent = buttonText.getAttribute('data-original-text') === 'Add Vehicle' ? 'Adding...' : 'Saving...';
            button.disabled = true;
        }

        function hideLoading(button) {
            const spinner = button.querySelector('.loading-spinner');
            const buttonText = button.querySelector('.button-text');
            spinner.style.display = 'none';
            buttonText.textContent = buttonText.getAttribute('data-original-text');
            button.disabled = false;
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
            // Scroll modal to top
            document.querySelector('.modal-content').scrollTop = 0;
        }

        function showVehicleAlert(message, type) {
            const alertDiv = document.getElementById('vehicleAlert');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            alertDiv.style.display = 'block';
            
            // Scroll to alert
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Hide alert after 5 seconds
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        function handleVehicleSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            
            // Prevent duplicate submissions
            if (submitButton.disabled) {
                return false;
            }
            
            // Validate form fields
            const make = form.querySelector('#make').value.trim();
            const regNumber = form.querySelector('#regNumber').value.trim();
            
            if (!make || !regNumber) {
                showVehicleAlert('Please fill in all required fields.', 'danger');
                return false;
            }
            
            showLoading(submitButton);
            submitButton.disabled = true;

            const formData = new FormData(form);
            formData.append('action', 'add');
            formData.append('_token', '<?= htmlspecialchars($csrfToken) ?>');

            fetch('vehicle_operations.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': '<?= htmlspecialchars($csrfToken) ?>', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }).catch(() => {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                hideLoading(submitButton);
                submitButton.disabled = false;
                
                if (data.status === 'success' || data.success === true) {
                    showVehicleAlert(data.message || 'Vehicle added successfully! Previous vehicles have been deactivated.', 'success');
                    
                    // Update count immediately
                    incCount('vehiclesCount', 1);
                    
                    // Insert a new row if API returned vehicle data
                    const tbody = document.querySelector('table.table tbody');
                    if (tbody && data.vehicle) {
                        // Remove empty row if present
                        tbody.querySelectorAll('tr').forEach(tr => {
                            if (tr.querySelector('td') && tr.children.length === 1 && tr.textContent.includes('No registered vehicles')) {
                                tr.remove();
                            }
                        });
                        
                        // Update specific vehicles that were deactivated (if backend provided IDs)
                        if (data.deactivated_vehicle_ids && data.deactivated_vehicle_ids.length > 0) {
                            data.deactivated_vehicle_ids.forEach(vehicleId => {
                                const existingRow = document.getElementById('vehicle-' + vehicleId);
                                if (existingRow) {
                                    const statusBadge = existingRow.querySelector('.status-badge');
                                    if (statusBadge) {
                                        // Change to inactive appearance
                                        statusBadge.className = 'status-badge status-inactive';
                                        statusBadge.textContent = 'Inactive';
                                        // Add subtle animation to show the change
                                        statusBadge.style.transition = 'all 0.3s ease';
                                        statusBadge.style.transform = 'scale(0.95)';
                                        setTimeout(() => {
                                            statusBadge.style.transform = 'scale(1)';
                                        }, 150);
                                    }
                                    // Update last_updated time
                                    const lastUpdated = existingRow.querySelector('.last-updated');
                                    if (lastUpdated) {
                                        lastUpdated.textContent = 'Just now';
                                    }
                                }
                            });
                        } else {
                            // Fallback: update all existing vehicles to show inactive status
                            tbody.querySelectorAll('tr').forEach(existingRow => {
                                const statusBadge = existingRow.querySelector('.status-badge');
                                if (statusBadge && statusBadge.classList.contains('status-active')) {
                                    statusBadge.className = 'status-badge status-inactive';
                                    statusBadge.textContent = 'Inactive';
                                    statusBadge.style.transition = 'all 0.3s ease';
                                    statusBadge.style.transform = 'scale(0.95)';
                                    setTimeout(() => {
                                        statusBadge.style.transform = 'scale(1)';
                                    }, 150);
                                }
                            });
                        }
                        
                        const v = data.vehicle;
                        const tr = document.createElement('tr');
                        tr.id = 'vehicle-' + v.vehicle_id;
                        tr.style.opacity = '0';
                        tr.style.transform = 'translateY(-10px)';
                        tr.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        
                        tr.innerHTML = `
                            <td>${(v.make || '—')}</td>
                            <td>${(v.regNumber || '—')}</td>
                            <td><span class="status-badge status-${(v.status || 'inactive')}">${(v.status || 'Inactive').charAt(0).toUpperCase() + (v.status || 'Inactive').slice(1)}</span></td>
                            <td><div class="last-updated">${v.formatted_last_updated || ''}</div></td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-icon" onclick="editVehicle(${v.vehicle_id}, '${(v.make || '').replace(/'/g, "&#39;")}', '${(v.regNumber || '').replace(/'/g, "&#39;")}')"><i class="fas fa-pen"></i> Edit</button>
                                <button class="btn btn-danger btn-icon" onclick="deleteVehicle(${v.vehicle_id})"><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                        
                        tbody.prepend(tr);
                        
                        // Animate in the new row
                        setTimeout(() => {
                            tr.style.opacity = '1';
                            tr.style.transform = 'translateY(0)';
                        }, 10);
                    }
                    
                    // Close modal and reset form after short delay for user to see success message
                    setTimeout(() => {
                        form.reset();
                        closeModal('addVehicleModal');
                    }, 800);
                } else {
                    showVehicleAlert(data.message || 'An error occurred. Please try again.', 'danger');
                }
            })
            .catch(error => {
                hideLoading(submitButton);
                submitButton.disabled = false;
                const errorMsg = error.message || 'An error occurred. Please try again.';
                showVehicleAlert(errorMsg, 'danger');
                console.error('Vehicle operation error:', error);
            });

            return false;
        }

        function deleteVehicle(id) {
            if (!confirm('Are you sure you want to delete this vehicle?')) return;

            const row = document.getElementById('vehicle-' + id);
            const originalBg = row ? row.style.backgroundColor : '';
            if (row) { row.style.backgroundColor = '#fff3cd'; }

            const body = new URLSearchParams({ action: 'delete', id: String(id), _token: '<?= htmlspecialchars($csrfToken) ?>' });

            fetch('vehicle_operations.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': '<?= htmlspecialchars($csrfToken) ?>', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' || data.success === true) {
                    if (row) {
                        row.style.transition = 'opacity .25s ease, height .25s ease';
                        row.style.opacity = '0';
                        setTimeout(() => { if (row && row.parentNode) row.parentNode.removeChild(row); ensureEmptyRow('table.table tbody', 5, 'No registered vehicles found.'); }, 300);
                    } else {
                        ensureEmptyRow('table.table tbody', 5, 'No registered vehicles found.');
                    }
                    // Decrement count immediately
                    incCount('vehiclesCount', -1);
                } else {
                    if (row) row.style.backgroundColor = originalBg;
                    showVehicleAlert(data.message || 'Delete failed', 'danger');
                }
            })
            .catch(error => {
                if (row) row.style.backgroundColor = originalBg;
                console.error('Error:', error);
                showVehicleAlert('An error occurred. Please try again.', 'danger');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('addVehicleModal');
                if (modal.style.display === 'block') {
                    closeModal('addVehicleModal');
                }
            }
        });

        // Driver Modal Functions
        function openDriverModal() {
            document.getElementById('modalTitle').textContent = 'Add New Driver';
            document.getElementById('driverForm').reset();
            document.getElementById('driver_id').value = '';
            document.getElementById('driverAlert').style.display = 'none';
            document.getElementById('driverModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Scroll to top of modal
            document.querySelector('.modal-content').scrollTop = 0;
        }

        function closeDriverModal() {
            document.getElementById('driverModal').style.display = 'none';
            document.getElementById('driverAlert').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function editDriver(driverId, fullname, licenseNumber, contact) {
            document.getElementById('modalTitle').textContent = 'Edit Driver';
            document.getElementById('driver_id').value = driverId;
            document.getElementById('fullname').value = fullname;
            document.getElementById('licenseNumber').value = licenseNumber;
            document.getElementById('contact').value = contact || '';
            document.getElementById('driverAlert').style.display = 'none';
            document.getElementById('driverModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Scroll to top of modal
            document.querySelector('.modal-content').scrollTop = 0;
        }

        function showDriverAlert(message, type) {
            const alertDiv = document.getElementById('driverAlert');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            alertDiv.style.display = 'block';
            
            // Scroll to alert
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Hide alert after 5 seconds
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        function handleDriverSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            
            // Prevent duplicate submissions
            if (submitButton.disabled) {
                return false;
            }
            
            // Validate form fields
            const fullname = form.querySelector('#fullname').value.trim();
            const licenseNumber = form.querySelector('#licenseNumber').value.trim();
            
            if (!fullname || !licenseNumber) {
                showDriverAlert('Please fill in all required fields.', 'danger');
                return false;
            }
            
            showLoading(submitButton);
            submitButton.disabled = true;

            const formData = new FormData(form);
            const isEdit = formData.get('driver_id') !== '';
            formData.append('action', isEdit ? 'edit' : 'add');
            formData.append('_token', '<?= htmlspecialchars($csrfToken) ?>');

            fetch('driver_operations.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': '<?= htmlspecialchars($csrfToken) ?>' },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `HTTP error! status: ${response.status}`);
                    }).catch(() => {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                hideLoading(submitButton);
                submitButton.disabled = false;
                
                if (data.status === 'success' || data.success) {
                    showDriverAlert(data.message || 'Saved', 'success');
                    
                    // Safely get tbody element with multiple fallback options
                    let tbody = null;
                    const modal = document.querySelector('#driverModal');
                    if (modal) {
                        const section = modal.closest('.management-section');
                        if (section) {
                            tbody = section.querySelector('table tbody');
                        }
                    }
                    // Fallback: try to find any drivers table
                    if (!tbody) {
                        tbody = document.querySelector('.management-section:has(#driversCount) table tbody, table tbody');
                    }
                    
                    // Update count if adding
                    const driverIdField = form.querySelector('#driver_id');
                    const isEdit = driverIdField && (driverIdField.value || '') !== '';
                    if (!isEdit) incCount('driversCount', 1);
                    if (tbody && data.driver) {
                        // Remove empty row if present
                        tbody.querySelectorAll('tr').forEach(tr => {
                            if (tr.querySelector('td') && tr.children.length === 1 && tr.textContent.includes('No authorized drivers')) tr.remove();
                        });
                        const d = data.driver;
                        let tr = document.getElementById('driver-row-' + d.Id);
                        const isNewRow = !tr;
                        
                        if (!tr) {
                            tr = document.createElement('tr');
                            tr.id = 'driver-row-' + d.Id;
                            tr.style.opacity = '0';
                            tr.style.transform = 'translateY(-10px)';
                            tr.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            tbody.prepend(tr);
                        }
                        
                        tr.innerHTML = `
                            <td>${(d.fullname || '')}</td>
                            <td>${(d.licenseNumber || '')}</td>
                            <td>${(d.contact || 'N/A')}</td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-icon" onclick="editDriver(${d.Id}, '${(d.fullname || '').replace(/'/g, "&#39;")}', '${(d.licenseNumber || '').replace(/'/g, "&#39;")}', '${(d.contact || '').replace(/'/g, "&#39;")}')"><i class=\"fas fa-pen\"></i> Edit</button>
                                <button class="btn btn-danger btn-icon" onclick="deleteDriver(${d.Id})"><i class=\"fas fa-trash\"></i> Delete</button>
                            </td>`;
                        
                        // Animate in the new row
                        if (isNewRow) {
                            setTimeout(() => {
                                tr.style.opacity = '1';
                                tr.style.transform = 'translateY(0)';
                            }, 10);
                        }
                    }
                    
                    // Close modal after short delay for user to see success message
                    setTimeout(() => {
                        closeDriverModal();
                    }, 800);
                } else {
                    showDriverAlert(data.message || 'An error occurred. Please try again.', 'danger');
                }
            })
            .catch(error => {
                hideLoading(submitButton);
                submitButton.disabled = false;
                const errorMsg = error.message || 'An error occurred. Please try again.';
                showDriverAlert(errorMsg, 'danger');
                console.error('Driver operation error:', error);
            });

            return false;
        }

        function deleteDriver(driverId) {
            if (!confirm('Are you sure you want to delete this driver?')) return;

            const token = '<?= htmlspecialchars($csrfToken) ?>';
            const body = new URLSearchParams({ 
                action: 'delete', 
                driver_id: String(driverId), 
                _token: token 
            });

            const deleteButton = document.querySelector(`button[onclick*="deleteDriver(${driverId})"]`);
            const originalHtml = deleteButton ? deleteButton.innerHTML : null;
            if (deleteButton) { deleteButton.disabled = true; deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...'; }

            fetch('driver_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'X-CSRF-Token': token
                },
                credentials: 'same-origin',
                body: body.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data && (data.success || data.status === 'success')) {
                    // Remove row without page refresh
                    const row = document.getElementById('driver-row-' + driverId);
                    if (row) row.remove();
                    // Decrement count immediately
                    incCount('driversCount', -1);
                    ensureEmptyRow('#driverModal .management-section table tbody', 4, 'No authorized drivers found.');
                    showAlert('success', data.message || 'Driver deleted');
                } else {
                    throw new Error((data && data.message) || 'Delete failed');
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                showAlert('error', err.message || 'Failed to delete driver.');
            })
            .finally(() => {
                if (deleteButton) { deleteButton.disabled = false; deleteButton.innerHTML = originalHtml || 'Delete'; }
            });
        }

        function editVehicle(vehicleId, make, regNumber) {
            document.getElementById('edit_vehicle_id').value = vehicleId;
            document.getElementById('edit_make').value = make;
            document.getElementById('edit_regNumber').value = regNumber;
            document.getElementById('editVehicleAlert').style.display = 'none';
            openModal('editVehicleModal');
        }

        function handleEditVehicleSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            
            // Validate form fields
            const make = form.querySelector('#edit_make').value.trim();
            const regNumber = form.querySelector('#edit_regNumber').value.trim();
            
            if (!make || !regNumber) {
                showAlert('editVehicle', 'Please fill in all required fields.', 'danger');
                return false;
            }
            
            showLoading(submitButton);

            const formData = new FormData(form);
            formData.append('action', 'edit');
            // Ensure we send id param expected by backend
            if (!formData.get('id')) {
                formData.append('id', document.getElementById('edit_vehicle_id')?.value || '');
            }
            // Add CSRF token to body for robustness
            formData.append('_token', '<?= htmlspecialchars($csrfToken) ?>');

            fetch('vehicle_operations.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': '<?= htmlspecialchars($csrfToken) ?>', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading(submitButton);
                if (data.status === 'success' || data.success === true) {
                    showAlert('editVehicle', data.message || 'Vehicle updated successfully!', 'success');
                    // Update row without page refresh
                    const row = document.getElementById('vehicle-' + data.vehicle.vehicle_id);
                    if (row) {
                        row.innerHTML = `
                            <td>${(data.vehicle.make || '—')}</td>
                            <td>${(data.vehicle.regNumber || '—')}</td>
                            <td><span class="status-badge status-${(data.vehicle.status || 'inactive')}">${(data.vehicle.status || 'Inactive').charAt(0).toUpperCase() + (data.vehicle.status || 'Inactive').slice(1)}</span></td>
                            <td><div class="last-updated">${data.vehicle.formatted_last_updated || ''}</div></td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-icon" onclick="editVehicle(${data.vehicle.vehicle_id}, '${(data.vehicle.make || '').replace(/'/g, "&#39;")}', '${(data.vehicle.regNumber || '').replace(/'/g, "&#39;")}')"><i class="fas fa-pen"></i> Edit</button>
                                <button class="btn btn-danger btn-icon" onclick="deleteVehicle(${data.vehicle.vehicle_id})"><i class="fas fa-trash"></i> Delete</button>
                            </td>
                        `;
                    }
                    setTimeout(() => { closeModal('editVehicleModal'); }, 800);
                } else {
                    showAlert('editVehicle', data.message || 'An error occurred. Please try again.', 'danger');
                }
            })
            .catch(error => {
                hideLoading(submitButton);
                showAlert('editVehicle', 'An error occurred. Please try again.', 'danger');
                console.error('Error:', error);
            });

            return false;
        }

        async function handleOwnerSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const buttonText = submitButton ? submitButton.querySelector('.button-text') : submitButton;
            const spinner = submitButton ? submitButton.querySelector('.loading-spinner') : null;
            
            // Show loading state
            if (buttonText) buttonText.textContent = 'Saving...';
            if (submitButton) submitButton.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';

            const formData = new FormData(form);
            const csrfToken = document.querySelector('input[name="_token"]')?.value;
            
            if (!csrfToken) {
                showAlert('error', 'Security token missing. Please refresh the page and try again.', 'editOwnerModal');
                if (buttonText) buttonText.textContent = 'Save Changes';
                if (submitButton) submitButton.disabled = false;
                if (spinner) spinner.style.display = 'none';
                return false;
            }
            
            try {
                const response = await fetch('update-owner-info.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                let responseData;
                const responseText = await response.text();
                
                // Try to parse as JSON
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON response:', responseText);
                    throw new Error('Invalid server response. Please try again.');
                }

                if (!response.ok) {
                    const error = new Error(responseData.message || 'Failed to update information');
                    error.response = responseData;
                    throw error;
                }

                if (responseData && responseData.status === 'success') {
                    showAlert('success', responseData.message, 'editOwnerModal');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    throw new Error(responseData?.message || 'Failed to update information');
                }
            } catch (error) {
                console.error('Update error:', error);
                let errorMessage = 'An error occurred while updating your information.';
                
                if (error.message.includes('NetworkError')) {
                    errorMessage = 'Network error. Please check your connection and try again.';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                showAlert('error', errorMessage, 'editOwnerModal');
            } finally {
                if (buttonText) buttonText.textContent = 'Save Changes';
                if (submitButton) submitButton.disabled = false;
                if (spinner) spinner.style.display = 'none';
            }
            
            return false;
        }
    </script>
</body>
</html>
