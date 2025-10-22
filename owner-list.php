<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// // Check if user is logged in and is admin
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: user-dashboard.php");
//     exit();
// }

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause = "WHERE fullName LIKE ? OR idNumber LIKE ? OR phone LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
    $types = 'sss';
}

$conn = getDBConnection();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as count FROM applicants $where_clause";
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['count'];
} else {
    $result = $conn->query($count_sql);
    $total_records = $result->fetch_assoc()['count'];
}

$total_pages = ceil($total_records / $per_page);

// Get owners with vehicle count
$sql = "
    SELECT a.*, COUNT(v.vehicle_id) as vehicle_count 
    FROM applicants a 
    LEFT JOIN vehicles v ON a.applicant_id = v.applicant_id 
    $where_clause 
    GROUP BY a.applicant_id 
    ORDER BY a.fullName 
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$owners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner List - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="csrf-token" content="<?= htmlspecialchars(SecurityMiddleware::generateCSRFToken()) ?>">
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

        .search-container {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .search-input:focus {
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

        .owner-name {
            font-weight: 600;
            color: var(--gray-800);
        }

        .owner-details {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .vehicle-count {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: rgba(208, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .vehicle-count:hover {
            background: rgba(208, 0, 0, 0.2);
            transform: translateY(-1px);
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

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .pagination a:hover {
            background-color: var(--gray-100);
            border-color: var(--primary-red);
            color: var(--primary-red);
        }

        .pagination a.active {
            background-color: var(--primary-red);
            color: var(--white);
            border-color: var(--primary-red);
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

            .search-form {
                flex-direction: column;
                gap: 0.75rem;
            }

            .search-input {
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
                    <i class="fas fa-users icon"></i>
                    <h1>Owner Management</h1>
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
                <li><a href="owner-list.php" class="active">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="manage-vehicle-status.php">Manage Vehicle Status</a></li>
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_records ?></div>
                <div class="stat-label">Total Owners</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($owners) ?></div>
                <div class="stat-label">Showing</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_pages ?></div>
                <div class="stat-label">Pages</div>
            </div>
        </div>

        <div class="search-container">
            <form class="search-form" method="GET">
                <input type="text" name="search" class="search-input" 
                       placeholder="Search by name, ID number, or phone..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="owner-list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Owner Directory</h3>
            </div>
            <?php if (empty($owners)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No owners found</h3>
                    <p><?= !empty($search) ? 'Try adjusting your search criteria.' : 'No owners have been registered yet.' ?></p>
                </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                            <th><i class="fas fa-user"></i> Owner Details</th>
                            <th><i class="fas fa-id-card"></i> ID Number</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-car"></i> Vehicles</th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($owners as $owner): ?>
                        <tr>
                                <td>
                                    <div class="owner-name"><?= htmlspecialchars($owner['fullName']) ?></div>
                                    <div class="owner-details"><?= ucfirst(htmlspecialchars($owner['registrantType'])) ?></div>
                                </td>
                            <td><?= htmlspecialchars($owner['idNumber']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($owner['phone'] ?? '') ?></div>
                                    <div class="owner-details"><?= htmlspecialchars($owner['Email'] ?? ($owner['email'] ?? 'N/A')) ?></div>
                                </td>
                            <td>
                                <a href="owner-details.php?id=<?= $owner['applicant_id'] ?>" 
                                   class="vehicle-count">
                                        <i class="fas fa-car"></i>
                                        <?= $owner['vehicle_count'] ?> Vehicle<?= $owner['vehicle_count'] != 1 ? 's' : '' ?>
                                </a>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-primary btn-icon" 
                                            onclick="viewOwner(<?= $owner['applicant_id'] ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-secondary btn-icon" 
                                            onclick="editOwner(<?= $owner['applicant_id'] ?>)"
                                            title="Edit Owner">
                                        <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-icon" 
                                            onclick="deleteOwner(<?= $owner['applicant_id'] ?>)"
                                            title="Delete Owner">
                                        <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function logout() {
            window.location.href = 'logout.php';
        }

        function viewOwner(ownerId) {
            window.location.href = `owner-details.php?id=${ownerId}`;
        }

        function editOwner(ownerId) {
            window.location.href = `edit-owner.php?id=${ownerId}`;
        }

        function deleteOwner(ownerId) {
            if (!confirm('Are you sure you want to delete this owner? This will remove the owner record.')) {
                return;
            }

            const formData = new FormData();
            formData.append('user_id', ownerId);

            fetch('delete_user.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': '<?= htmlspecialchars(SecurityMiddleware::generateCSRFToken()) ?>' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Refresh to reflect deletion
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete owner');
                }
            })
            .catch(() => alert('An error occurred. Please try again.'));
        }
    </script>
</body>
</html> 