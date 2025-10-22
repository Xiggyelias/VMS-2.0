<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Generate CSRF token
$csrfToken = SecurityMiddleware::generateCSRFToken();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = getDBConnection();

// Initialize searched vehicle holder
$searched_vehicle = null;

// Handle disk number assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_disk') {
    $vehicle_id = $_POST['vehicle_id'];
    $disk_number = $_POST['disk_number'];
    
    // Standardize to the correct column name 'disk_number'
    $stmt = $conn->prepare("UPDATE vehicles SET disk_number = ? WHERE vehicle_id = ?");
    $stmt->bind_param("si", $disk_number, $vehicle_id);
    
    if ($stmt->execute()) {
        $success_message = "Disk number assigned successfully!";
    } else {
        $error_message = "Failed to assign disk number: " . $stmt->error;
    }
    $stmt->close();
}

// If a registration number is searched, fetch that specific vehicle pending assignment
if (isset($_GET['search_reg']) && trim($_GET['search_reg']) !== '') {
    $reg = trim($_GET['search_reg']);
    $stmt = $conn->prepare("
        SELECT v.*, a.fullName as owner_name
        FROM vehicles v
        JOIN applicants a ON v.applicant_id = a.applicant_id
        WHERE v.regNumber = ? AND (v.disk_number IS NULL OR v.disk_number = '')
        LIMIT 1
    ");
    $stmt->bind_param("s", $reg);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $searched_vehicle = $res->fetch_assoc();
    }
    $stmt->close();
}

// Get vehicles without disk numbers
$stmt = $conn->prepare("
    SELECT v.*, a.fullName as owner_name 
    FROM vehicles v 
    JOIN applicants a ON v.applicant_id = a.applicant_id 
    WHERE v.disk_number IS NULL OR v.disk_number = ''
    ORDER BY v.registration_date DESC
");
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Disk Numbers - Vehicle Registration System</title>
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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.1);
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

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
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
                    <i class="fas fa-hashtag icon"></i>
                    <h1>Disk Number Management</h1>
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
                <li><a href="manage-vehicle-status.php">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php" class="active">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($vehicles) ?></div>
                <div class="stat-label">Pending Assignment</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= !empty($searched_vehicle) ? '1' : '0' ?></div>
                <div class="stat-label">Selected Vehicle</div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

<div class="search-container">
    <form class="search-form" method="GET">
        <input type="text" name="search_reg" class="search-input" 
               placeholder="Enter Registration Number..." 
                       value="<?= htmlspecialchars($_GET['search_reg'] ?? '') ?>" required>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($_GET['search_reg'])): ?>
                    <a href="manage-disk-numbers.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
        <?php endif; ?>
    </form>
</div>

        <?php if (!empty($searched_vehicle)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-car"></i> Vehicle Details</h3>
                </div>
                <div style="padding: 2rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                        <div>
                            <div class="vehicle-details">Registration Number</div>
                            <div class="vehicle-info"><?= htmlspecialchars($searched_vehicle['regNumber']) ?></div>
                        </div>
                        <div>
                            <div class="vehicle-details">Make & Model</div>
                            <div class="vehicle-info"><?= htmlspecialchars($searched_vehicle['make']) ?></div>
                    </div>
                        <div>
                            <div class="vehicle-details">Owner</div>
                            <div class="vehicle-info"><?= htmlspecialchars($searched_vehicle['owner_name']) ?></div>
                    </div>
                        <div>
                            <div class="vehicle-details">Status</div>
                            <div class="vehicle-info">
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending Assignment
                                </span>
                    </div>
                        </div>
                    </div>
                    
                    <form method="POST" style="background: var(--gray-100); padding: 1.5rem; border-radius: var(--border-radius);">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="assign_disk">
                    <input type="hidden" name="vehicle_id" value="<?= $searched_vehicle['vehicle_id'] ?>">
                        
                        <div class="form-group">
                            <label for="disk_number">Assign Disk Number</label>
                            <input type="text" name="disk_number" id="disk_number" class="form-control" 
                                   placeholder="Enter disk number (e.g., AU-001)" required
                           pattern="[A-Za-z0-9-]+" title="Only letters, numbers, and hyphens allowed">
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Assign Disk Number
                        </button>
                </form>
                </div>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Vehicles Pending Disk Number Assignment</h3>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Registration Number</th>
                            <th>Make & Model</th>
                            <th>Owner</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vehicles)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <h3>All vehicles have disk numbers assigned</h3>
                                        <p>No vehicles are pending disk number assignment.</p>
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
                                        <div class="vehicle-details"><?= htmlspecialchars($vehicle['registration_date']) ?></div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?search_reg=<?= urlencode($vehicle['regNumber']) ?>" 
                                               class="btn btn-primary btn-icon">
                                                <i class="fas fa-hashtag"></i> Assign Disk
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Add form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const diskNumberInput = this.querySelector('input[name="disk_number"]');
                if (diskNumberInput && !/^[A-Za-z0-9-]+$/.test(diskNumberInput.value)) {
                    e.preventDefault();
                    alert('Disk number can only contain letters, numbers, and hyphens');
                }
            });
        });
    </script>
</body>
</html> 