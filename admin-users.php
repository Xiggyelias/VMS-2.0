<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Require admin access
requireAdmin();

// Generate CSRF token for POST requests
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users - Vehicle Registration System</title>
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

        .user-info {
            font-weight: 600;
            color: var(--gray-800);
        }

        .user-details {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .role-admin {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .role-staff {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .role-student {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
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
        .topbar .btn-icon { color: #555; }
        .topbar .btn-icon:hover { color: var(--primary); }

        /* Table tweaks */
        table.dataTable tbody tr:hover { background-color: #f2f6ff; }
        .badge-active { background-color: #28a745; }
        .badge-suspended { background-color: #dc3545; }

        /* Mobile sidebar */
        @media (max-width: 992px) {
            .sidebar { position: fixed; transform: translateX(-100%); transition: transform .25s ease; z-index: 1040; }
            .sidebar.show { transform: translateX(0); }
            .content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <div class="page-title">
                    <i class="fas fa-users icon"></i>
                    <h1>User Management</h1>
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
                <li><a href="manage-disk-numbers.php">Manage Disk Numbers</a></li>
                <li><a href="admin_reports.php">Reports</a></li>
                <li><a href="admin-users.php" class="active">User View</a></li>
            </ul>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="totalUsers">0</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="activeUsers">0</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="studentUsers">0</div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="staffUsers">0</div>
                <div class="stat-label">Staff</div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> User Directory</h3>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <select id="filterType" class="filter-select" style="min-width: 180px">
                        <option value="">All Registrant Types</option>
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                    </select>
                    <select id="filterStatus" class="filter-select" style="min-width: 160px">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
            <table id="usersTable" class="table">
                <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Registrant Type</th>
                                    <th>Vehicles</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                <tbody>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <h3>Loading users...</h3>
                                <p>Please wait while we fetch user data.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
                        </table>
                    </div>
                </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let usersTable;

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        async function fetchUsers() {
            const type = document.getElementById('filterType').value;
            const status = document.getElementById('filterStatus').value;
            
            try {
                const response = await fetch(`get_users.php?type=${type}&status=${status}`);
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.stats);
                    populateTable(data.users);
                } else {
                    console.error('Failed to fetch users:', data.message);
                }
            } catch (error) {
                console.error('Error fetching users:', error);
            }
        }

        function updateStats(stats) {
            document.getElementById('totalUsers').textContent = stats.total || 0;
            document.getElementById('activeUsers').textContent = stats.active || 0;
            document.getElementById('studentUsers').textContent = stats.student || 0;
            document.getElementById('staffUsers').textContent = stats.staff || 0;
        }

        function populateTable(users) {
            const tbody = document.querySelector('#usersTable tbody');
            tbody.innerHTML = '';

            if (users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>No users found</h3>
                                <p>No users match your filter criteria.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            users.forEach(user => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="user-info">${user.applicant_id}</div>
                    </td>
                    <td>
                        <div class="user-info">${user.fullName || 'N/A'}</div>
                    </td>
                    <td>
                        <div class="user-details">${user.Email || 'N/A'}</div>
                    </td>
                    <td>
                        <div class="user-details">${user.phone || 'N/A'}</div>
                    </td>
                    <td>
                        <span class="role-badge role-${user.registrantType || 'student'}">
                            <i class="fas fa-circle"></i>
                            ${(user.registrantType || 'student').charAt(0).toUpperCase() + (user.registrantType || 'student').slice(1)}
                        </span>
                    </td>
                    <td>
                        <div class="user-details">${user.vehicle_count || 0}</div>
                    </td>
                    <td>
                        <div class="user-details">${user.registration_date || 'N/A'}</div>
                    </td>
                    <td>
                        <span class="role-badge ${user.status === 'active' ? 'role-staff' : 'role-admin'}">
                            <i class="fas fa-circle"></i>
                            ${(user.status || 'active').charAt(0).toUpperCase() + (user.status || 'active').slice(1)}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-primary btn-icon" onclick="viewUser(${user.applicant_id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-secondary btn-icon" onclick="editUser(${user.applicant_id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function viewUser(userId) {
            // Implement view user functionality
            alert('View user functionality would be implemented here for user ID: ' + userId);
        }

        function editUser(userId) {
            // Implement edit user functionality
            alert('Edit user functionality would be implemented here for user ID: ' + userId);
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            fetchUsers();
            
            // Add event listeners for filters
            document.getElementById('filterType').addEventListener('change', fetchUsers);
            document.getElementById('filterStatus').addEventListener('change', fetchUsers);
        });
    </script>
</body>
</html>
            const url = new URL('get_users.php', window.location.href);
            if (type) url.searchParams.set('type', type);
            if (status) url.searchParams.set('status', status);
            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            showLoading(false);
            return data.users || [];
        }

        function statusBadge(status) {
            const s = (status || 'active').toLowerCase();
            return s === 'suspended'
                ? '<span class="badge badge-suspended">Suspended</span>'
                : '<span class="badge badge-active">Active</span>'
        }

        function actionButtons(row) {
            const suspendAction = row.status?.toLowerCase() === 'suspended' ? 'Activate' : 'Suspend';
            return `
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View Details" onclick="onView(${row.applicant_id})"><i class="fa fa-eye"></i></button>
                    <button class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit User" onclick="onEdit(${row.applicant_id})"><i class="fa fa-pen"></i></button>
                    <button class="btn btn-outline-warning" data-bs-toggle="tooltip" title="${suspendAction}" onclick="onToggleSuspend(${row.applicant_id})"><i class="fa fa-user-slash"></i></button>
                    <button class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Delete" onclick="onDelete(${row.applicant_id})"><i class="fa fa-trash"></i></button>
                </div>
            `;
        }

        async function loadTable() {
            const users = await fetchUsers();
            if (usersTable) {
                usersTable.clear();
                usersTable.rows.add(users);
                usersTable.draw();
                initTooltips();
                return;
            }
            usersTable = new DataTable('#usersTable', {
                data: users,
                responsive: true,
                columns: [
                    { data: 'applicant_id' },
                    { data: 'fullName' },
                    { data: 'Email' },
                    { data: 'phone' },
                    { data: 'registrantType', render: (d) => (d||'').toString().charAt(0).toUpperCase() + (d||'').toString().slice(1) },
                    { data: 'vehicles_count' },
                    { data: 'registration_date' },
                    { data: null, render: (row) => statusBadge(row.status) },
                    { data: null, orderable: false, searchable: false, render: (row) => actionButtons(row) },
                ],
                order: [[6, 'desc']],
                pageLength: 10,
            });
            initTooltips();
        }

        async function onView(userId) {
            const modal = new bootstrap.Modal(document.getElementById('viewModal'));
            document.getElementById('viewContent').innerHTML = 'Loading...';
            modal.show();
            const res = await fetch('view_user.php?user_id=' + encodeURIComponent(userId), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) {
                document.getElementById('viewContent').innerHTML = '<div class="text-danger">Failed to load user details.</div>';
                return;
            }
            const u = data.user;
            const vehiclesHtml = (u.vehicles || []).map(v => `<li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><strong>${v.plate}</strong> — ${v.make || ''}</span>
                    <span class="badge bg-secondary">${(v.status||'').toString().toUpperCase()}</span>
                </li>`).join('');
            document.getElementById('viewContent').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <div><strong>Full Name:</strong> ${u.fullName || ''}</div>
                        <div><strong>Email:</strong> ${u.Email || ''}</div>
                        <div><strong>Phone:</strong> ${u.phone || ''}</div>
                    </div>
                    <div class="col-md-6">
                        <div><strong>Registrant Type:</strong> ${(u.registrantType||'').toString().toUpperCase()}</div>
                        <div><strong>Status:</strong> ${statusBadge(u.status)}</div>
                        <div><strong>Registered Vehicles:</strong> ${u.vehicles_count}</div>
                    </div>
                </div>
                <hr/>
                <h6>Vehicles</h6>
                <ul class="list-group list-group-flush">${vehiclesHtml || '<li class="list-group-item small text-muted">No vehicles</li>'}</ul>
                <hr/>
                <h6>Registration History</h6>
                <div class="small text-muted">First registration: ${u.registration_date || 'N/A'}</div>
            `;
        }

        async function onEdit(userId) {
            const res = await fetch('view_user.php?user_id=' + encodeURIComponent(userId), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.success) return alert('Failed to load user');
            const u = data.user;
            document.getElementById('editUserId').value = u.applicant_id;
            document.getElementById('editFullName').value = u.fullName || '';
            document.getElementById('editEmail').value = u.Email || '';
            document.getElementById('editPhone').value = u.phone || '';
            document.getElementById('editRegistrantType').value = (u.registrantType || '').toLowerCase();
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        document.getElementById('editForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const body = new URLSearchParams(new FormData(form));
            showLoading(true);
            const res = await fetch('update_user.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
                body
            });
            showLoading(false);
            const data = await res.json();
            if (!data.success) return alert(data.message || 'Update failed');
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            await loadTable();
        });

        async function onToggleSuspend(userId) {
            const confirmText = 'Toggle user status (Suspend/Activate)?';
            if (!confirm(confirmText)) return;
            showLoading(true);
            const res = await fetch('update_user.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_status&user_id=${encodeURIComponent(userId)}`
            });
            showLoading(false);
            const data = await res.json();
            if (!data.success) return alert(data.message || 'Operation failed');
            await loadTable();
        }

        async function onDelete(userId) {
            if (!confirm('Delete this user? This cannot be undone.')) return;
            showLoading(true);
            const res = await fetch('delete_user.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(userId)}`
            });
            showLoading(false);
            const data = await res.json();
            if (!data.success) return alert(data.message || 'Delete failed');
            await loadTable();
        }

        document.getElementById('btnRefresh').addEventListener('click', loadTable);
        document.getElementById('filterType').addEventListener('change', loadTable);
        document.getElementById('filterStatus').addEventListener('change', loadTable);
        document.getElementById('btnOpenSidebar').addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));
        document.getElementById('btnCloseSidebar').addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));

        document.addEventListener('DOMContentLoaded', loadTable);
    </script>
</body>
</html>



                            <input type="text" id="editPhone" name="phone" class="form-control" />

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Registrant Type</label>

                            <select id="editRegistrantType" name="registrantType" class="form-select" required>

                                <option value="student">Student</option>

                                <option value="staff">Staff</option>

                                <option value="guest">Guest</option>

                            </select>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                        <button type="submit" class="btn btn-primary">Save Changes</button>

                    </div>

                </form>

            </div>

        </div>

    </div>



    <!-- Bootstrap JS + DataTables -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.datatables.net/v/bs5/dt-2.1.8/r-3.0.3/datatables.min.js"></script>



    <script>

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let usersTable;



        const showLoading = (show) => {

            document.getElementById('loadingOverlay').classList.toggle('d-none', !show);

        }



        function logout() { window.location.href = 'logout.php'; }



        function initTooltips() {

            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))

            tooltipTriggerList.map(function (tooltipTriggerEl) {

                return new bootstrap.Tooltip(tooltipTriggerEl)

            })

        }



        async function fetchUsers() {

            showLoading(true);

            const type = document.getElementById('filterType').value;

            const status = document.getElementById('filterStatus').value;

            const url = new URL('get_users.php', window.location.href);

            if (type) url.searchParams.set('type', type);

            if (status) url.searchParams.set('status', status);

            const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });

            const data = await res.json();

            showLoading(false);

            return data.users || [];

        }



        function statusBadge(status) {

            const s = (status || 'active').toLowerCase();

            return s === 'suspended'

                ? '<span class="badge badge-suspended">Suspended</span>'

                : '<span class="badge badge-active">Active</span>'

        }



        function actionButtons(row) {

            const suspendAction = row.status?.toLowerCase() === 'suspended' ? 'Activate' : 'Suspend';

            return `

                <div class="btn-group btn-group-sm" role="group">

                    <button class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View Details" onclick="onView(${row.applicant_id})"><i class="fa fa-eye"></i></button>

                    <button class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit User" onclick="onEdit(${row.applicant_id})"><i class="fa fa-pen"></i></button>

                    <button class="btn btn-outline-warning" data-bs-toggle="tooltip" title="${suspendAction}" onclick="onToggleSuspend(${row.applicant_id})"><i class="fa fa-user-slash"></i></button>

                    <button class="btn btn-outline-danger" data-bs-toggle="tooltip" title="Delete" onclick="onDelete(${row.applicant_id})"><i class="fa fa-trash"></i></button>

                </div>

            `;

        }



        async function loadTable() {

            const users = await fetchUsers();

            if (usersTable) {

                usersTable.clear();

                usersTable.rows.add(users);

                usersTable.draw();

                initTooltips();

                return;

            }

            usersTable = new DataTable('#usersTable', {

                data: users,

                responsive: true,

                columns: [

                    { data: 'applicant_id' },

                    { data: 'fullName' },

                    { data: 'Email' },

                    { data: 'phone' },

                    { data: 'registrantType', render: (d) => (d||'').toString().charAt(0).toUpperCase() + (d||'').toString().slice(1) },

                    { data: 'vehicles_count' },

                    { data: 'registration_date' },

                    { data: null, render: (row) => statusBadge(row.status) },

                    { data: null, orderable: false, searchable: false, render: (row) => actionButtons(row) },

                ],

                order: [[6, 'desc']],

                pageLength: 10,

            });

            initTooltips();

        }



        async function onView(userId) {

            const modal = new bootstrap.Modal(document.getElementById('viewModal'));

            document.getElementById('viewContent').innerHTML = 'Loading...';

            modal.show();

            const res = await fetch('view_user.php?user_id=' + encodeURIComponent(userId), { headers: { 'Accept': 'application/json' } });

            const data = await res.json();

            if (!data.success) {

                document.getElementById('viewContent').innerHTML = '<div class="text-danger">Failed to load user details.</div>';

                return;

            }

            const u = data.user;

            const vehiclesHtml = (u.vehicles || []).map(v => `<li class="list-group-item d-flex justify-content-between align-items-center">

                    <span><strong>${v.plate}</strong> — ${v.make || ''}</span>

                    <span class="badge bg-secondary">${(v.status||'').toString().toUpperCase()}</span>

                </li>`).join('');

            document.getElementById('viewContent').innerHTML = `

                <div class="row g-3">

                    <div class="col-md-6">

                        <div><strong>Full Name:</strong> ${u.fullName || ''}</div>

                        <div><strong>Email:</strong> ${u.Email || ''}</div>

                        <div><strong>Phone:</strong> ${u.phone || ''}</div>

                    </div>

                    <div class="col-md-6">

                        <div><strong>Registrant Type:</strong> ${(u.registrantType||'').toString().toUpperCase()}</div>

                        <div><strong>Status:</strong> ${statusBadge(u.status)}</div>

                        <div><strong>Registered Vehicles:</strong> ${u.vehicles_count}</div>

                    </div>

                </div>

                <hr/>

                <h6>Vehicles</h6>

                <ul class="list-group list-group-flush">${vehiclesHtml || '<li class="list-group-item small text-muted">No vehicles</li>'}</ul>

                <hr/>

                <h6>Registration History</h6>

                <div class="small text-muted">First registration: ${u.registration_date || 'N/A'}</div>

            `;

        }



        async function onEdit(userId) {

            const res = await fetch('view_user.php?user_id=' + encodeURIComponent(userId), { headers: { 'Accept': 'application/json' } });

            const data = await res.json();

            if (!data.success) return alert('Failed to load user');

            const u = data.user;

            document.getElementById('editUserId').value = u.applicant_id;

            document.getElementById('editFullName').value = u.fullName || '';

            document.getElementById('editEmail').value = u.Email || '';

            document.getElementById('editPhone').value = u.phone || '';

            document.getElementById('editRegistrantType').value = (u.registrantType || '').toLowerCase();

            new bootstrap.Modal(document.getElementById('editModal')).show();

        }



        document.getElementById('editForm').addEventListener('submit', async (e) => {

            e.preventDefault();

            const form = e.target;

            const body = new URLSearchParams(new FormData(form));

            showLoading(true);

            const res = await fetch('update_user.php', {

                method: 'POST',

                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },

                body

            });

            showLoading(false);

            const data = await res.json();

            if (!data.success) return alert(data.message || 'Update failed');

            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();

            await loadTable();

        });



        async function onToggleSuspend(userId) {

            const confirmText = 'Toggle user status (Suspend/Activate)?';

            if (!confirm(confirmText)) return;

            showLoading(true);

            const res = await fetch('update_user.php', {

                method: 'POST',

                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },

                body: `action=toggle_status&user_id=${encodeURIComponent(userId)}`

            });

            showLoading(false);

            const data = await res.json();

            if (!data.success) return alert(data.message || 'Operation failed');

            await loadTable();

        }



        async function onDelete(userId) {

            if (!confirm('Delete this user? This cannot be undone.')) return;

            showLoading(true);

            const res = await fetch('delete_user.php', {

                method: 'POST',

                headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },

                body: `user_id=${encodeURIComponent(userId)}`

            });

            showLoading(false);

            const data = await res.json();

            if (!data.success) return alert(data.message || 'Delete failed');

            await loadTable();

        }



        document.getElementById('btnRefresh').addEventListener('click', loadTable);

        document.getElementById('filterType').addEventListener('change', loadTable);

        document.getElementById('filterStatus').addEventListener('change', loadTable);

        document.getElementById('btnOpenSidebar').addEventListener('click', () => document.getElementById('sidebar').classList.add('show'));

        document.getElementById('btnCloseSidebar').addEventListener('click', () => document.getElementById('sidebar').classList.remove('show'));



        document.addEventListener('DOMContentLoaded', loadTable);

    </script>

</body>

</html>




