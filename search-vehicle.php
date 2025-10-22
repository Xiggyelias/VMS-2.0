<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Generate CSRF token
$csrfToken = SecurityMiddleware::generateCSRFToken();

function getDBConnection() {
    $conn = new mysqli("localhost", "root", "", "vehicleregistrationsystem");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function escapeHTML($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------- Handle Manual Search Form Submission ----------
$vehicle = null;
$authorizedDrivers = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_value'])) {
    $searchValue = trim($_POST['search_value']);

    if (empty($searchValue)) {
        $error = "Please enter a registration number";
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("
            SELECT 
                v.vehicle_id,
                v.applicant_id,
                v.regNumber,
                v.make,
                v.owner,
                v.address,
                v.PlateNumber,
                v.registration_date,
                v.disk_number,
                a.idNumber,
                a.phone,
                a.email
            FROM vehicles v
            LEFT JOIN applicants a ON v.applicant_id = a.applicant_id
            WHERE v.regNumber = ?
        ");
        $stmt->bind_param("s", $searchValue);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $vehicle = $result->fetch_assoc();
            // Fetch authorized drivers linked by applicant_id
            $drvStmt = $conn->prepare("
                SELECT fullname, licenseNumber
                FROM authorized_driver
                WHERE applicant_id = ?
                ORDER BY fullname
            ");
            $drvStmt->bind_param("i", $vehicle['applicant_id']);
            $drvStmt->execute();
            $drvRes = $drvStmt->get_result();
            if ($drvRes && $drvRes->num_rows > 0) {
                $authorizedDrivers = $drvRes->fetch_all(MYSQLI_ASSOC);
            }
            $drvStmt->close();
        } else {
            $error = "No vehicle found with the provided registration number";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Search - Manual Entry</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    background: radial-gradient(1200px 600px at 0% 0%, #2b2b2b 0%, #171717 50%, #121212 100%);
    color: #eee;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    padding: 2rem 1rem;
    display: flex;
    justify-content: center;
  }
  .container {
            max-width: 1000px;
    width: 100%;
    background: linear-gradient(180deg, rgba(30,30,30,.9), rgba(24,24,24,.9));
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(208,0,0,.18), inset 0 1px 0 rgba(255,255,255,.03);
    padding: 2rem;
    border: 1px solid rgba(255,255,255,.06);
    backdrop-filter: blur(6px);
  }
  header {
    text-align: center;
    margin-bottom: 2rem;
  }
  header img {
    height: 56px;
    filter: brightness(0) invert(1);
    margin-bottom: 1rem;
  }
  header h1 {
    font-size: 2.2rem;
    color: #fff;
    letter-spacing: 1px;
    margin-bottom: 0.2rem;
    font-weight: 800;
    text-shadow: 0 2px 12px rgba(0,0,0,.35);
  }
  header p {
    color: #c9c9c9;
    font-size: 1rem;
        }
        .search-method {
            background: #1b1b1b;
            padding: 1.25rem 1.25rem 1rem;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.08);
            margin-bottom: 2rem;
            box-shadow: 0 8px 26px rgba(0,0,0,.35);
        }
        .search-method h3 {
            color: #ff4d4d;
            margin-bottom: .75rem;
            font-size: 1.05rem;
            letter-spacing: .5px;
  }
  .search-input {
            position: relative;
            margin-bottom: 1rem;
        }
  .search-input i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa0a6;
        }
  input[type="text"] {
            width: 100%;
            padding: 0.9rem 0.9rem 0.9rem 2.4rem;
            border: 1px solid #3a3a3a;
            border-radius: 10px;
            background: #141414;
    color: #eee;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.02);
            transition: border-color .2s ease, box-shadow .2s ease;
        }
  input[type="text"]:focus {
            border-color: #ff4d4d;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,77,77,.15);
        }
        .buttons {
            display: flex;
            gap: 1rem;
  }
  button {
    background: linear-gradient(180deg, #ff5a5a, #d00000);
    border: none;
            padding: 0.8rem 1.2rem;
    border-radius: 10px;
    color: white;
    font-weight: 700;
            font-size: 0.95rem;
    cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
            box-shadow: 0 10px 18px rgba(208,0,0,.28);
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(208,0,0,.35);
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
            text-align: center;
  }
  .alert-error {
    background: #5c0000;
    color: #ff7777;
  }
  .tabs { margin-top: 1rem; }
  .tab-buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .6rem;
    margin-bottom: 1rem;
  }
  .tab-buttons button {
    background: #171717;
    color: #e8e8e8;
    border: 1px solid rgba(255,255,255,.08);
    padding: 0.7rem;
    font-weight: 600;
    border-radius: 999px;
    cursor: pointer;
  }
  .tab-buttons button.active {
    background: #ff4d4d;
    border-color: #ff4d4d;
    color: #fff;
    box-shadow: 0 8px 18px rgba(255,77,77,.28);
  }
  .tab-content {
    display: none;
            padding: 1rem 1rem 0.2rem;
            background: #141414;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.08);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.02);
  }
  .tab-content.active { display: block; }
  .info-group {
    margin-bottom: 1rem;
  }
  .info-label { font-size: 0.78rem; color: #a9a9a9; text-transform: uppercase; letter-spacing: .5px; }
  .info-value { font-size: 1.05rem; color: #fff; }
        .nav-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .nav-btn {
            background: #292929;
            color: #eee;
            border: 2px solid #444;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .nav-btn:hover {
            background: #d00000;
            border-color: #d00000;
            transform: translateY(-2px);
        }
        .nav-btn.active {
            background: #d00000;
            border-color: #d00000;
        }
        @media (max-width: 768px) {
            .buttons {
                flex-direction: column;
            }
            button {
            width: 100%;
            }
            .nav-buttons {
                flex-direction: column;
        }
            .nav-btn {
            width: 100%;
            text-align: center;
        }
        }
    </style>
</head>
<body>
<main class="container">
  <header>
    <img src="AULogo.png" alt="AU Logo" />
            <h1>Vehicle Search</h1>
            <p>Search vehicle information by registration number</p>
  </header>

    <div class="nav-buttons">
        <a href="search-vehicle.php" class="nav-btn active">Manual Search</a>
        </div>

        <!-- Manual Entry Section -->
            <div class="search-method">
                <h3>Manual Entry</h3>
                <p style="color:#a9a9a9;margin:0 0 .75rem 0;font-size:.9rem">Enter the vehicle registration number below to view details.</p>
                <form method="POST" action="" autocomplete="off">
            <!-- CSRF Token -->
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="search-input">
                    <i class="fa fa-magnifying-glass"></i>
                    <input type="text" name="search_value" placeholder="e.g. ABC 123 or ABC123" 
      value="<?= escapeHTML($_POST['search_value'] ?? '') ?>" />
            </div>
                    <div class="buttons">
    <button type="submit">Search</button>
                        <button type="button" onclick="clearSearch()">Clear</button>
                    </div>
  </form>
        </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= escapeHTML($error) ?></div>
  <?php endif; ?>

        <div id="results" style="display: <?= $vehicle ? 'block' : 'none' ?>;">
    <div class="tabs">
      <div class="tab-buttons">
                    <button class="active" data-tab="vehicleTab">🚗 Vehicle Info</button>
                    <button data-tab="ownerTab">👤 Owner Info</button>
                    <button data-tab="driverTab">🧑‍✈️ Authorized Drivers</button>
      </div>

                <?php if ($vehicle): ?>
        <div id="vehicleTab" class="tab-content active">
          <div class="info-group"><div class="info-label">Make</div><div class="info-value"><?= escapeHTML($vehicle['make']) ?></div></div>
          <div class="info-group"><div class="info-label">Reg Number</div><div class="info-value"><?= escapeHTML($vehicle['regNumber']) ?></div></div>
          <div class="info-group"><div class="info-label">Disk Number</div><div class="info-value"><?= escapeHTML($vehicle['disk_number'] ?: 'Not Assigned') ?></div></div>
          <div class="info-group"><div class="info-label">Registration Date</div><div class="info-value"><?= escapeHTML($vehicle['registration_date']) ?></div></div>
        </div>

        <div id="ownerTab" class="tab-content">
          <div class="info-group"><div class="info-label">Owner</div><div class="info-value"><?= escapeHTML($vehicle['owner']) ?></div></div>
          <div class="info-group"><div class="info-label">ID Number</div><div class="info-value"><?= escapeHTML($vehicle['idNumber']) ?></div></div>
          <div class="info-group"><div class="info-label">Phone</div><div class="info-value"><?= escapeHTML($vehicle['phone']) ?></div></div>
          <div class="info-group"><div class="info-label">Email</div><div class="info-value"><?= escapeHTML($vehicle['email']) ?></div></div>
        </div>

        <div id="driverTab" class="tab-content">
          <?php if (!empty($authorizedDrivers)): ?>
            <?php foreach ($authorizedDrivers as $drv): ?>
              <div class="info-group" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;align-items:center;">
                <div>
                  <div class="info-label">Full Name</div>
                  <div class="info-value"><?= escapeHTML($drv['fullname']) ?></div>
                </div>
                <div>
                  <div class="info-label">License Number</div>
                  <div class="info-value"><?= escapeHTML($drv['licenseNumber']) ?></div>
                </div>
              </div>
              <div style="height:1px;background:#2a2a2a;margin:.25rem 0 .6rem 0;border-radius:1px;"></div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="info-group"><div class="info-label">Authorized Drivers</div><div class="info-value">None</div></div>
          <?php endif; ?>
        </div>
                <?php endif; ?>
            </div>
    </div>
</main>

    <script>
        // Clear button functionality
    function clearSearch() {
                document.querySelector('input[name="search_value"]').value = '';
        document.getElementById('results').style.display = 'none';
                const errorAlert = document.querySelector('.alert-error');
                if (errorAlert) errorAlert.remove();
        }

    // Tab functionality
        document.querySelectorAll('.tab-buttons button').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-buttons button').forEach(btn => btn.classList.remove('active'));
                document.getElementById(button.dataset.tab).classList.add('active');
                button.classList.add('active');
            });
        });
    </script>
</body>
</html>
