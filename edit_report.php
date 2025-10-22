<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

if (!isAdmin()) { header('Location: admin-login.php'); exit; }

// CSRF
$csrfToken = SecurityMiddleware::generateCSRFToken();

function getDBConnection() {
    $conn = new mysqli('localhost', 'root', '', 'vehicleregistrationsystem');
    if ($conn->connect_error) { die('Connection failed'); }
    return $conn;
}

$conn = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: admin_reports.php'); exit; }

// Fetch existing report
$stmt = $conn->prepare('SELECT * FROM admin_reports WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$report) { $conn->close(); header('Location: admin_reports.php'); exit; }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_token'] ?? '';
    if (!$csrf || !SecurityMiddleware::verifyCSRFToken($csrf)) {
        $error = 'Invalid CSRF token';
    } else {
        // Discover columns
        $existing = [];
        if ($cols = $conn->query("SHOW COLUMNS FROM admin_reports")) {
            while ($row = $cols->fetch_assoc()) { $existing[strtolower($row['Field'])] = true; }
            $cols->close();
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['content'] ?? ''));
        $category = trim((string)($_POST['type'] ?? ''));
        $regNumber = trim((string)($_POST['regNumber'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'open'));
        $officer = trim((string)($_POST['officer'] ?? ''));
        $reportDate = $_POST['report_date'] ?? date('Y-m-d');

        if ($title === '' || $description === '' || $category === '') {
            $error = 'Please fill in all required fields.';
        } else {
            // Handle optional file replacement
            $filePath = $report['file_path'] ?? null;
            if (!empty($_FILES['evidence']['name'])) {
                $allowed = ['image/jpeg','image/png','image/gif','application/pdf'];
                $tmp = $_FILES['evidence']['tmp_name'] ?? '';
                $mime = $tmp && file_exists($tmp) ? @mime_content_type($tmp) : '';
                if (!$tmp || !in_array($mime, $allowed, true)) {
                    $error = 'Invalid file type. Allowed: JPG, PNG, GIF, PDF.';
                } else {
                    $ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
                    $safeName = 'report_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/reports';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    $dest = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($tmp, $dest)) {
                        // delete old
                        if (!empty($filePath)) { $abs = __DIR__ . '/' . ltrim($filePath, '/'); if (is_file($abs)) { @unlink($abs); } }
                        $filePath = 'uploads/reports/' . $safeName;
                    } else {
                        $error = 'Failed to upload file.';
                    }
                }
            }

            if ($error === '') {
                // Dynamic UPDATE
                $sets = [];
                $values = [];
                $types = '';
                if (isset($existing['title'])) { $sets[] = 'title = ?'; $values[] = $title; $types .= 's'; }
                if (isset($existing['description'])) { $sets[] = 'description = ?'; $values[] = $description; $types .= 's'; }
                if (isset($existing['category'])) { $sets[] = 'category = ?'; $values[] = $category; $types .= 's'; }
                if (isset($existing['reg_number'])) { $sets[] = 'reg_number = ?'; $values[] = $regNumber; $types .= 's'; }
                if (isset($existing['status'])) { $sets[] = 'status = ?'; $values[] = $status; $types .= 's'; }
                if (isset($existing['officer'])) { $sets[] = 'officer = ?'; $values[] = $officer; $types .= 's'; }
                if (isset($existing['report_date'])) { $sets[] = 'report_date = ?'; $values[] = $reportDate; $types .= 's'; }
                if (isset($existing['file_path'])) { $sets[] = 'file_path = ?'; $values[] = $filePath; $types .= 's'; }

                if (!empty($sets)) {
                    $sql = 'UPDATE admin_reports SET ' . implode(', ', $sets) . ' WHERE id = ?';
                    $types .= 'i';
                    $values[] = $id;
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param($types, ...$values);
                        if ($stmt->execute()) {
                            $success = 'Report updated successfully';
                            // refresh fetched data
                            $stmt->close();
                            $stmt = $conn->prepare('SELECT * FROM admin_reports WHERE id = ?');
                            $stmt->bind_param('i', $id);
                            $stmt->execute();
                            $report = $stmt->get_result()->fetch_assoc();
                        } else {
                            $error = 'Update failed: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error = 'Prepare failed: ' . $conn->error;
                    }
                }
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report</title>
    <?php includeCommonAssets(); ?>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .page-header { background: linear-gradient(90deg, rgba(208,0,0,1) 0%, rgba(176,0,0,1) 100%); color:#fff; padding: 1rem 0; margin-bottom: 1.25rem; box-shadow: var(--shadow-sm); }
        .page-header .header-content { display:flex; align-items:center; justify-content: space-between; gap:1rem; }
        .breadcrumbs { color: rgba(255,255,255,0.9); font-size: .95rem; }
        .card { background:#fff; border-radius:8px; padding:1.5rem; box-shadow: var(--shadow-sm); }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; }
        .section-title { margin: 0 0 .75rem; color: var(--gray-800); font-size: 1.15rem; font-weight: 600; }
        .actions { display:flex; gap:.5rem; align-items:center; }
        .btn .fa, .btn .fas { margin-right:.4rem; }
        .helper { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .error-message { color: #dc2626; font-size: 12px; margin-top: 4px; display:none; }
        @media (max-width: 768px) {
            .actions-bar { position: sticky; bottom: 0; background: #fff; padding: .75rem; box-shadow: 0 -2px 8px rgba(0,0,0,.06); display:flex; gap:.5rem; z-index: 50; }
            .actions-bar .btn { flex: 1; }
        }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container">
            <div class="header-content">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div class="header-logo" style="width: 70px;">
                        <a href="admin_reports.php">
                            <img src="assets/images/AULogo.png" alt="AULogo" style="width: 100%; height: auto;">
                        </a>
                    </div>
                    <div>
                        <h1 style="margin:0; font-size:1.5rem;">Edit Report</h1>
                        <div class="breadcrumbs"><i class="fa fa-file-lines"></i> Admin / Reports / Edit</div>
                    </div>
                </div>
                <div class="actions">
                    <a class="btn btn-secondary" href="admin_reports.php"><i class="fa fa-arrow-left"></i> Back to Reports</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container" style="margin-bottom:2rem;">
        <div class="card">
            <h2 class="section-title"><i class="fa fa-pen-to-square" style="color:var(--primary-red);"></i> Report Details</h2>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form id="editReportForm" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="grid">
                    <div>
                        <label class="form-label">Title</label>
                        <input class="form-input" name="title" id="title" value="<?= htmlspecialchars($report['title'] ?? '') ?>" required>
                        <div class="error-message" id="err_title">Please enter a report title.</div>
                    </div>
                    <div>
                        <label class="form-label">Type</label>
                        <select name="type" id="type" class="form-input" required>
                            <?php $t = strtolower($report['category'] ?? ''); ?>
                            <option value="incident" <?= $t==='incident'?'selected':'' ?>>Incident</option>
                            <option value="maintenance" <?= $t==='maintenance'?'selected':'' ?>>Maintenance</option>
                            <option value="general" <?= $t==='general'?'selected':'' ?>>General</option>
                        </select>
                        <div class="error-message" id="err_type">Please select a type.</div>
                    </div>
                    <div>
                        <label class="form-label">Reg Number</label>
                        <input class="form-input" name="regNumber" value="<?= htmlspecialchars($report['reg_number'] ?? '') ?>">
                        <div class="helper">e.g., ABC123</div>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <?php $s = strtolower($report['status'] ?? 'open'); ?>
                        <select name="status" class="form-input">
                            <option value="open" <?= $s==='open'?'selected':'' ?>>Open</option>
                            <option value="in_progress" <?= $s==='in_progress'?'selected':'' ?>>In Progress</option>
                            <option value="closed" <?= $s==='closed'?'selected':'' ?>>Closed</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Officer In Charge</label>
                        <input class="form-input" name="officer" value="<?= htmlspecialchars($report['officer'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label">Report Date</label>
                        <input type="date" class="form-input" name="report_date" value="<?= htmlspecialchars($report['report_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Description</label>
                    <textarea class="form-input" name="content" id="content" required><?= htmlspecialchars($report['description'] ?? '') ?></textarea>
                    <div class="error-message" id="err_content">Please provide a description.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Replace Evidence (Image/PDF)</label>
                    <input type="file" class="form-input" name="evidence" id="evidence" accept="image/*,application/pdf">
                    <?php if (!empty($report['file_path'])): ?>
                        <div class="mt-2"><a class="btn btn-secondary" href="<?= htmlspecialchars($report['file_path']) ?>" target="_blank"><i class="fa fa-file"></i> Current File</a></div>
                    <?php endif; ?>
                    <div class="helper">Allowed: JPG, PNG, GIF, PDF. Max 10 MB.</div>
                    <div class="error-message" id="err_file">Invalid file. Please upload a supported type up to 10 MB.</div>
                </div>
                <div class="modal-buttons actions-bar" style="justify-content:flex-start;">
                    <button id="btnSave" class="btn btn-primary" type="submit"><i class="fa fa-floppy-disk"></i> Save Changes</button>
                    <a class="btn btn-secondary" href="admin_reports.php"><i class="fa fa-arrow-left"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toast" class="toast toast-success" style="display:none;">Report saved successfully</div>

    <script>
        (function() {
            const form = document.getElementById('editReportForm');
            const title = document.getElementById('title');
            const type = document.getElementById('type');
            const content = document.getElementById('content');
            const evidence = document.getElementById('evidence');
            const btnSave = document.getElementById('btnSave');

            function showError(el, errId, show) {
                const err = document.getElementById(errId);
                if (!err) return;
                err.style.display = show ? 'block' : 'none';
                if (show) { el.classList.add('error'); } else { el.classList.remove('error'); }
            }

            function validate() {
                let ok = true;
                const t = (title.value || '').trim();
                const tp = (type.value || '').trim();
                const c = (content.value || '').trim();
                showError(title, 'err_title', t === ''); ok = ok && t !== '';
                showError(type, 'err_type', tp === ''); ok = ok && tp !== '';
                showError(content, 'err_content', c === ''); ok = ok && c !== '';

                // File check (optional if selected)
                const f = evidence.files && evidence.files[0];
                if (f) {
                    const max = 10 * 1024 * 1024; // 10 MB
                    const allowed = ['image/jpeg','image/png','image/gif','application/pdf'];
                    const bad = f.size > max || allowed.indexOf(f.type) === -1;
                    showError(evidence, 'err_file', bad);
                    ok = ok && !bad;
                } else {
                    showError(evidence, 'err_file', false);
                }
                return ok;
            }

            form.addEventListener('submit', function(e) {
                if (!validate()) {
                    e.preventDefault();
                    return false;
                }
                // Prevent double submit
                btnSave.disabled = true;
                btnSave.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            });

            // Show success toast if server rendered success alert exists
            const successAlert = document.querySelector('.alert.alert-success');
            if (successAlert) {
                const toast = document.getElementById('toast');
                toast.style.display = 'block';
                toast.classList.add('show');
                setTimeout(() => { toast.style.display = 'none'; toast.classList.remove('show'); }, 3000);
            }
        })();
    </script>
</body>
</html>


