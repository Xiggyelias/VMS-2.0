<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin access
requireAdmin();
// Generate CSRF token for POST requests
$csrfToken = SecurityMiddleware::generateCSRFToken();

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = getDBConnection();

// Get total owners count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applicants");
$stmt->execute();
$total_owners = $stmt->get_result()->fetch_assoc()['count'];

// Get total vehicles count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM vehicles");
$stmt->execute();
$total_vehicles = $stmt->get_result()->fetch_assoc()['count'];

// Get total drivers count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM authorized_driver");
$stmt->execute();
$total_drivers = $stmt->get_result()->fetch_assoc()['count'];

// Get recent registrations with enhanced applicant details
$stmt = $conn->prepare("
    SELECT 
        v.*,
        a.fullName as owner_name,
        a.registrantType,
        a.studentRegNo,
        a.staffsRegNo,
        a.Email,
        a.college,
        a.licenseNumber,
        a.licenseClass,
        DATE_FORMAT(v.last_updated, '%M %d, %Y %h:%i %p') as formatted_last_updated
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    ORDER BY v.registration_date DESC 
    LIMIT 5
");
$stmt->execute();
$recent_registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total counts by registrant type
$stmt = $conn->prepare("
    SELECT 
       registrantType,
        COUNT(*) as count
    FROM applicants 
    GROUP BY registrantType
");
$stmt->execute();
$registrant_counts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get vehicle counts by status
$stmt = $conn->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM vehicles 
    GROUP BY status
");
$stmt->execute();
$vehicle_status_counts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE is_read = FALSE 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Vehicle Registration System</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <style>
        :root {
            --primary-red: #d00000; /* Brand red */
            --primary-red-dark: #b00000; /* Darker shade for hover */
            --white: #ffffff;
            --black: #000000;
            --gray-100: #f8f9fa; /* Light background */
            --gray-200: #e9ecef; /* Slightly darker gray */
            --gray-300: #dee2e6; /* Border color */
            --gray-600: #6c757d; /* Meta text color */
            --gray-800: #343a40; /* Main text color */
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05); /* Small shadow */
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1); /* Medium shadow */
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--gray-800);
            line-height: 1.5;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header Styling */
        header { background: linear-gradient(90deg, rgba(208,0,0,1) 0%, rgba(176,0,0,1) 100%); padding: 1rem 2rem; color: var(--white); box-shadow: var(--shadow-sm); }

         .header-content {
            display: flex; /* Use flexbox for layout */
            justify-content: space-between; /* Put space between left and right items */
            align-items: center; /* Vertically align items in the center */
        }

        header h1 {
            margin: 0;
            font-size: 1.8rem; /* Slightly larger font for main title */
            font-weight: 600;
        }

        header .header-left {
            display: flex;
            align-items: center;
            gap: 15px; /* Space between logo and title */
        }

        .header-logo { width: 120px; }
        header .header-logo img { width: 100%; height: auto; }

        /* Using shared .btn .btn-logout from assets/css/styles.css */

        /* Admin Navigation Styling */
        .admin-nav { background: var(--white); padding: 1rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: var(--shadow-sm); }
        .admin-nav ul { list-style: none; padding: 0; margin: 0; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .admin-nav a { color: var(--primary-red); text-decoration: none; padding: 0.65rem 1rem; border-radius: 6px; transition: all 0.2s ease; font-weight: 500; display: inline-block; }
        .admin-nav a:hover, .admin-nav a.active { background-color: var(--primary-red); color: var(--white); box-shadow: var(--shadow-sm); }

        /* Stats Container Styling */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-number {
            font-size: 2.5rem;
            color: var(--primary-red); /* Red number */
            font-weight: 700;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--gray-600); /* Gray label */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .stat-label i {
            color: var(--primary-red);
        }

        .stat-breakdown {
            margin-top: 1rem;
            font-size: 0.9rem;
            text-align: left;
            padding: 0 1rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .stat-type {
            color: var(--gray-600);
        }

        .stat-value {
            font-weight: 600;
            color: var(--gray-800);
        }

        /* Quick Actions Styling */
        .quick-actions,
        .recent-activity,
        .notifications-section { background: var(--white); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: var(--shadow-sm); }

        .quick-actions h2,
        .recent-activity h2,
        .notifications-section h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            color: var(--gray-800);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .quick-actions h2 i,
        .recent-activity h2 i {
            color: var(--primary-red);
        }

        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-top: 1rem; }

        .action-button { background-color: var(--primary-red); color: var(--white); padding: 0.9rem 1.2rem; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; letter-spacing: 0.2px; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: var(--shadow-sm); }

        .btn-primary { background-color: var(--primary-red); color: var(--white); padding: 0.75rem 1.2rem; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer; box-shadow: var(--shadow-sm); transition: all 0.3s ease; }

        .action-button:hover, .btn-primary:hover { background-color: var(--primary-red-dark); transform: translateY(-2px); box-shadow: var(--shadow-md); }

         /* Table Styling */
        .table-container {
            overflow-x: auto; /* Add horizontal scroll on small screens */
        }

        .table {
            width: 100%;
            border-collapse: collapse; /* Remove space between borders */
            margin-top: 1rem;
        }

        .table th,
        .table td {
            border: 1px solid var(--gray-300); /* Light gray border */
            padding: 0.75rem; /* Comfortable padding */
            text-align: left;
        }

        .table th {
            background-color: var(--gray-100); /* Light gray background for headers */
            color: var(--gray-800); /* Dark text for headers */
            font-weight: 600; /* Bold headers */
        }

        .table tbody tr:nth-child(even) { /* Zebra striping */
            background-color: #f2f2f2;
        }

        .table tbody tr:hover {
            background-color: var(--gray-200);
        }

        /* Status Badge Styling */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .last-updated {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

         /* Notification Styling */
        .notifications-section h2 {
            margin-bottom: 1rem;
        }

        .notifications-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }

        .notifications-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notifications-section h3 i {
            color: var(--primary-red);
        }

        .notifications-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary-red);
            border-radius: 2px;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .notification-icon.new-registration {
            background-color: #4CAF50;
            color: white;
        }

        .notification-icon.update {
            background-color: #2196F3;
            color: white;
        }

        .notification-icon.warning {
            background-color: #FFC107;
            color: white;
        }

        .notification-content {
            flex-grow: 1;
            min-width: 0;
        }

        .notification-message {
            margin: 0 0 4px 0;
            color: #333;
            font-size: 0.95em;
            word-wrap: break-word;
        }

        .notification-time {
            color: #666;
            font-size: 0.85em;
        }

        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: #2196F3;
            cursor: pointer;
            font-size: 0.9em;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .mark-read-btn:hover {
            background-color: #e3f2fd;
        }

        .mark-read-btn::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.9em;
        }

        .no-notifications {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin: 10px 0;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column; /* Stack items vertically on small screens */
                gap: 1rem;
                text-align: center;
            }

             header .header-left {
                flex-direction: column;
                gap: 10px;
                align-items: center; /* Center logo/title when stacked */
            }

            .admin-nav ul {
                flex-direction: column;
            }

            .admin-nav a {
                width: 100%;
                text-align: center;
            }

            .stats-container,
            .quick-actions,
            .recent-activity,
            .notifications-section {
                 padding: 1rem; /* Adjust padding for smaller screens */
             }

            .action-grid {
                grid-template-columns: 1fr; /* Stack buttons */
            }

            .table th, .table td {
                 padding: 0.5rem; /* Smaller padding in table */
                 font-size: 0.9rem;
             }

            .notification-item {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .notification-actions .mark-read-btn {
                width: 100%;
            }
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        /* Compact button variant for table action buttons */
        .btn-primary.btn-icon {
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }

        .status-toggle {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-toggle.active {
            background-color: #dc3545;
            color: white;
        }

        .status-toggle.inactive {
            background-color: #28a745;
            color: white;
        }

        .status-toggle:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* status-badge styles are defined once above */

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            display: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Focus states for accessibility */
        .action-button:focus,
        .btn-primary:focus,
        .status-toggle:focus,
        .mark-read-btn:focus {
            outline: 3px solid rgba(208, 0, 0, 0.3);
            outline-offset: 2px;
        }

        /* Custom scrollbar styling */
        .notifications-list::-webkit-scrollbar,
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .notifications-list::-webkit-scrollbar-track,
        .table-container::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 4px;
        }

        .notifications-list::-webkit-scrollbar-thumb,
        .table-container::-webkit-scrollbar-thumb {
            background: var(--gray-600);
            border-radius: 4px;
        }

        .notifications-list::-webkit-scrollbar-thumb:hover,
        .table-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-red);
        }

        /* Loading animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-box,
        .notification-item {
            animation: fadeIn 0.4s ease-out;
        }

        /* Smooth alert transitions */
        .alert {
            transition: all 0.3s ease-in-out;
        }

        .alert.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }

        /* Enhanced table row hover effect */
        .table tbody tr {
            transition: all 0.2s ease;
        }

        /* Card hover effects */
        .quick-actions,
        .recent-activity,
        .notifications-section {
            transition: box-shadow 0.3s ease;
        }

        .quick-actions:hover,
        .recent-activity:hover,
        .notifications-section:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        /* Improved button disabled state */
        .action-button:disabled,
        .btn-primary:disabled,
        .status-toggle:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Better link styling within content */
        a:not(.action-button):not(.btn-primary) {
            color: var(--primary-red);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:not(.action-button):not(.btn-primary):hover {
            color: var(--primary-red-dark);
            text-decoration: underline;
        }

        /* Skeleton loading for notifications */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin: 0.5rem 0;
        }

        /* Tooltip styling */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.5rem 1rem;
            background: var(--gray-800);
            color: var(--white);
            border-radius: 4px;
            font-size: 0.85rem;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 0.5rem;
        }

        /* Print styles */
        @media print {
            header,
            .admin-nav,
            .quick-actions,
            .action-buttons,
            .notifications-section {
                display: none;
            }

            .container {
                max-width: 100%;
                padding: 0;
            }

            .stat-box,
            .recent-activity {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid var(--gray-300);
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
                    <h1>Admin Dashboard</h1>
                </div>
                <div class="header-right">
                    <form id="adminLogoutForm" action="logout.php" method="POST" style="margin:0;">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button type="submit" class="btn btn-logout" aria-label="Logout">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php" class="active">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="notifications-section">
            <h3><i class="fas fa-bell"></i> Notifications</h3>
            <div id="notifications-container">
                <div class="notifications-list">
                    <!-- Notifications will be loaded here dynamically -->
                </div>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-box">
                <div class="stat-number"><?= $total_owners ?></div>
                <div class="stat-label">
                    <i class="fas fa-users"></i> Total Owners
                </div>
                <div class="stat-breakdown">
                    <?php foreach ($registrant_counts as $count): ?>
                        <div class="stat-item">
                            <span class="stat-type"><?= ucfirst($count['registrantType']) ?>:</span>
                            <span class="stat-value"><?= $count['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $total_vehicles ?></div>
                <div class="stat-label">
                    <i class="fas fa-car"></i> Total Vehicles
                </div>
                <div class="stat-breakdown">
                    <?php foreach ($vehicle_status_counts as $count): ?>
                        <div class="stat-item">
                            <span class="stat-type"><?= ucfirst($count['status']) ?>:</span>
                            <span class="stat-value"><?= $count['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $total_drivers ?></div>
                <div class="stat-label">
                    <i class="fas fa-id-card"></i> Authorized Drivers
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="action-grid">
                <a href="vehicle-list.php" class="action-button">
                    <i class="fas fa-car"></i> View All Vehicles
                </a>
                <a href="owner-list.php" class="action-button">
                    <i class="fas fa-users"></i> Manage Owners
                </a>
                <a href="registration-form.html" class="action-button">
                    <i class="fas fa-plus-circle"></i> New Registration
                </a>
                <a href="search-vehicle.php" class="action-button">
                    <i class="fas fa-search"></i> Search Vehicle
                </a>
                <a href="admin_reports.php" class="action-button">
                    <i class="fas fa-file-alt"></i> Create Report
                </a>
            </div>
        </div>

        <div class="recent-activity">
            <h2><i class="fas fa-history"></i> Recent Registrations</h2>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Owner</th>
                            <th>Type</th>
                            <th>Registration</th>
                            <th>College</th>
                            <th>License</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_registrations as $registration): ?>
                            <tr>
                                <td><?= htmlspecialchars($registration['make']) ?></td>
                                <td><?= htmlspecialchars($registration['owner_name']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($registration['registrantType'])) ?></td>
                                <td>
                                    <?php
                                    if ($registration['registrantType'] === 'student') {
                                        echo htmlspecialchars($registration['studentRegNo']);
                                    } elseif ($registration['registrantType'] === 'staff') {
                                        echo htmlspecialchars($registration['staffsRegNo']);
                                    } else {
                                        echo htmlspecialchars($registration['Email']);
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($registration['college']) ?></td>
                                <td>
                                    <?= htmlspecialchars($registration['licenseNumber']) ?>
                                    (<?= htmlspecialchars($registration['licenseClass']) ?>)
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $registration['status'] ?>">
                                        <?= ucfirst($registration['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="last-updated">
                                        <?= htmlspecialchars($registration['formatted_last_updated']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                    <a href="vehicle-details.php?id=<?= $registration['vehicle_id'] ?>" class="btn btn-primary btn-icon">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                        <button 
                                            class="status-toggle <?= $registration['status'] === 'active' ? 'active' : 'inactive' ?>"
                                            onclick="toggleStatus(<?= $registration['vehicle_id'] ?>, '<?= $registration['status'] ?>')"
                                        >
                                            <i class="fas fa-<?= $registration['status'] === 'active' ? 'ban' : 'check-circle' ?>"></i>
                                            <?= $registration['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="alert" class="alert"></div>

    <script>
        // Fallback logout if needed
        function logout() {
            const f = document.getElementById('adminLogoutForm');
            if (f) { f.submit(); } else { window.location.href = 'logout.php'; }
        }

        function updateNotifications() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = document.querySelector('.notifications-list');
                        if (data.notifications.length === 0) {
                            container.innerHTML = '<div class="no-notifications">No notifications</div>';
                            return;
                        }

                        container.innerHTML = data.notifications.map(notification => `
                            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                                <div class="notification-icon ${notification.type}">
                                    ${getNotificationIcon(notification.type)}
                                </div>
                                <div class="notification-content">
                                    <p class="notification-message">${notification.message}</p>
                                    <span class="notification-time">${notification.created_at}</span>
                                </div>
                                ${!notification.is_read ? `
                                    <div class="notification-actions">
                                        <button class="mark-read-btn" onclick="markAsRead(${notification.id})">
                                            Mark as read
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        `).join('');
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }

        function getNotificationIcon(type) {
            switch(type) {
                case 'new-registration':
                    return '<i class="fas fa-car"></i>';
                case 'update':
                    return '<i class="fas fa-edit"></i>';
                case 'warning':
                    return '<i class="fas fa-exclamation-triangle"></i>';
                default:
                    return '<i class="fas fa-bell"></i>';
            }
        }

        function markAsRead(notificationId) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotifications();
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }

        // Update notifications every 30 seconds
        document.addEventListener('DOMContentLoaded', () => {
            updateNotifications();
            setInterval(updateNotifications, 30000);
        });

        function toggleStatus(vehicleId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this vehicle?`)) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch('manage-vehicle-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken,
                },
                body: `vehicle_id=${vehicleId}&new_status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    // Reload the page to show updated status
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred while updating the status', 'error');
                console.error('Error:', error);
            });
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert alert-${type} show`;
            
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html> 