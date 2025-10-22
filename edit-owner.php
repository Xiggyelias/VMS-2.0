<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: user-dashboard.php");
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
$message = '';
$error = '';

// Get owner details
if (isset($_GET['id'])) {
    $owner_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner = $result->fetch_assoc();

    if (!$owner) {
        header("Location: owner-list.php");
        exit();
    }
} else {
    header("Location: owner-list.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName']);
    $idNumber = trim($_POST['idNumber']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate input
    if (empty($fullName) || empty($idNumber) || empty($phone)) {
        $error = "Name, ID Number, and Phone are required fields.";
    } else {
        $stmt = $conn->prepare("UPDATE applicants SET fullName = ?, idNumber = ?, phone = ?, email = ?, address = ? WHERE applicant_id = ?");
        $stmt->bind_param("sssssi", $fullName, $idNumber, $phone, $email, $address, $owner_id);
        
        if ($stmt->execute()) {
            $message = "Owner information updated successfully!";
            // Refresh owner data
            $stmt = $conn->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
            $stmt->bind_param("i", $owner_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $owner = $result->fetch_assoc();
        } else {
            $error = "Error updating owner information: " . $conn->error;
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
    <title>Edit Owner - Vehicle Registration System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .admin-nav {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .admin-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .admin-nav li {
            margin: 0;
        }

        .admin-nav a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .admin-nav a:hover {
            background-color: var(--primary-red);
            color: white;
            border-color: var(--primary-red);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .admin-nav a.active {
            background-color: var(--primary-red);
            color: white;
            border-color: var(--primary-red);
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 2rem auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="header-logo" style="width: 80px;">
                    <a href="admin-dashboard.php">
                        <img src="assets/images/AULogo.png" alt="AULogo">
                    </a>
                </div>
                <h1>Edit Owner</h1>
                <button onclick="logout()" class="btn btn-logout">Logout</button>
            </div>
        </div>
    </header>

    <div class="container">
        <nav class="admin-nav">
            <ul>
                <li><a href="admin-dashboard.php">Dashboard</a></li>
                <li><a href="owner-list.php">Manage Owners</a></li>
                <li><a href="vehicle-list.php">Manage Vehicles</a></li>
                <li><a href="user-dashboard.php">User View</a></li>
            </ul>
        </nav>

        <div class="form-container">
            <?php if ($message): ?>
                <div class="message success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="fullName" required
                           value="<?= htmlspecialchars($owner['fullName']) ?>">
                </div>

                <div class="form-group">
                    <label for="idNumber">ID Number *</label>
                    <input type="text" id="idNumber" name="idNumber" required
                           value="<?= htmlspecialchars($owner['idNumber']) ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required
                           value="<?= htmlspecialchars($owner['phone']) ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($owner['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?= htmlspecialchars($owner['address'] ?? '') ?></textarea>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Update Owner</button>
                    <a href="owner-list.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function logout() {
            window.location.href = 'logout.php';
        }
    </script>
</body>
</html> 