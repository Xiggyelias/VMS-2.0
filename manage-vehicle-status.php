<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();
// Generate CSRF token for POST requests
$csrfToken = SecurityMiddleware::generateCSRFToken();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin access
requireAdmin();

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id']) && isset($_POST['new_status'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $new_status = $_POST['new_status'];
    
    // Validate status
    if (!in_array($new_status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Update vehicle status
    $stmt = $conn->prepare("UPDATE vehicles SET status = ?, last_updated = NOW() WHERE vehicle_id = ?");
    $stmt->bind_param("si", $new_status, $vehicle_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vehicle status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update vehicle status']);
    }
    exit;
}

// Get filter status from query parameter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare the query based on filter
$query = "SELECT v.*, a.fullName as owner_name, a.Email, a.phone 
          FROM vehicles v 
          JOIN applicants a ON v.applicant_id = a.applicant_id";

if ($status_filter !== 'all') {
    $query .= " WHERE v.status = ?";
}

$query .= " ORDER BY v.last_updated DESC";

$stmt = $conn->prepare($query);

if ($status_filter !== 'all') {
    $stmt->bind_param("s", $status_filter);
}

$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicle Status - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <style>
        :root {
            --primary-red: #d00000;
            --primary-red-600: #b00000;
            --white: #ffffff;
            --black: #000000;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --border-radius: 8px;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--primary-red-600) 100%);
            color: var(--white);
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .page-title .icon {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .filter-container {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.1);
        }

        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 2rem;
            border: 1px solid var(--gray-200);
        }

        .table-header {
            background: var(--gray-100);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-header h3 {
            margin: 0;
            color: var(--gray-800);
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--gray-100);
            color: var(--gray-800);
            padding: 1.25rem 2rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-200);
        }

        .table td {
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: var(--gray-100);
        }

        .table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .table tbody tr:nth-child(even):hover {
            background-color: var(--gray-100);
        }

        .vehicle-info {
            font-weight: 600;
            color: var(--gray-800);
        }

        .vehicle-details {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-icon {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s ease;
        }

        .btn-icon:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem;
            color: var(--gray-700);
        }

        /* Admin Navigation Styles */
        .admin-nav {
            background: var(--white);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
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
            padding: 0.65rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-weight: 500;
            display: inline-block;
        }

        .admin-nav a:hover,
        .admin-nav a.active {
            background-color: var(--primary-red);
            color: var(--white);
            box-shadow: var(--shadow-sm);
        }

        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary-red);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-red-600);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--black);
        }

        .btn-secondary:hover {
            background-color: var(--gray-300);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background-color: #28a745;
            color: var(--white);
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background-color: #dc3545;
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-logout {
            border: 2px solid var(--white);
            background-color: transparent;
            color: var(--white);
            padding: 0.5rem 1rem;
        }

        .btn-logout:hover {
            background-color: var(--white);
            color: var(--primary-red);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 768px) {
            .page-header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .filter-form {
                flex-direction: column;
                gap: 0.75rem;
            }

            .filter-select {
                width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            .table th,
            .table td {
                padding: 1rem;
                font-size: 0.85rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <div class="page-title">
                    <i class="fas fa-cogs icon"></i>
                    <h1>Vehicle Status Management</h1>
                </div>
                <div class="header-actions">
                    <a href="admin-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button onclick="logout()" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php" class="active">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($vehicles) ?></div>
                <div class="stat-label">Total Vehicles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($vehicles, fn($v) => $v['status'] === 'active')) ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($vehicles, fn($v) => $v['status'] === 'inactive')) ?></div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>

        <div class="filter-container">
            <form class="filter-form" method="GET">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Vehicles</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </form>
        </div>

        <div id="alert" class="alert" style="display: none;"></div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Vehicle Status Directory</h3>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Registration Number</th>
                        <th>Make & Model</th>
                        <th>Owner</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehicles)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-car"></i>
                                    <h3>No vehicles found</h3>
                                    <p>No vehicles match your filter criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['regNumber']) ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['make']) ?></div>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['model'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['owner_name']) ?></div>
                                </td>
                                <td>
                                    <div class="vehicle-info"><?= htmlspecialchars($vehicle['phone']) ?></div>
                                    <div class="vehicle-details"><?= htmlspecialchars($vehicle['Email']) ?></div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $vehicle['status'] ?>">
                                        <i class="fas fa-circle"></i>
                                    <?= ucfirst($vehicle['status']) ?>
                                </span>
                            </td>
                                <td>
                                    <div class="vehicle-details"><?= date('M j, Y g:i A', strtotime($vehicle['last_updated'])) ?></div>
                                </td>
                            <td>
                                    <div class="action-buttons">
                                <button 
                                            class="btn <?= $vehicle['status'] === 'active' ? 'btn-danger' : 'btn-success' ?> btn-icon"
                                    onclick="toggleStatus(<?= $vehicle['vehicle_id'] ?>, '<?= $vehicle['status'] ?>')"
                                >
                                            <i class="fas <?= $vehicle['status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                    <?= $vehicle['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                                    </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
            }
        }

        function filterVehicles(status) {
            window.location.href = `manage-vehicle-status.php?status=${status}`;
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }

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
                body: `vehicle_id=${encodeURIComponent(vehicleId)}&new_status=${encodeURIComponent(newStatus)}`
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
    </script>
</body>
</html> 