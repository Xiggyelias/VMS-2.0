<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

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

// ---------- Handle AJAX Scan Request ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plate_number'])) {
    header('Content-Type: application/json');

    $plateNumber = trim($_POST['plate_number']);
    $response = [
        'success' => false,
        'data' => null,
        'error' => null,
        'isRegistered' => false
    ];

    if (empty($plateNumber)) {
        $response['error'] = "No plate number provided";
        echo json_encode($response);
        exit;
    }

    $conn = getDBConnection();

    // Check if the plate is registered
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
            a.email,
            d.fullname,
            d.licenseNumber
        FROM vehicles v
        LEFT JOIN applicants a ON v.applicant_id = a.applicant_id
        LEFT JOIN authorized_driver d ON v.vehicle_id = d.vehicle_id
        WHERE v.PlateNumber = ?
    ");
    $stmt->bind_param("s", $plateNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $response['success'] = true;
        $response['data'] = $result->fetch_assoc();
        $response['isRegistered'] = true;
    } else {
        // Check if already in unregistered_plates
        $checkStmt = $conn->prepare("SELECT * FROM unregistered_plates WHERE plate_number = ?");
        $checkStmt->bind_param("s", $plateNumber);
        $checkStmt->execute();
        $unregResult = $checkStmt->get_result();

        if ($unregResult->num_rows === 0) {
            // Save to unregistered_plates
            $insertStmt = $conn->prepare("INSERT INTO unregistered_plates (plate_number) VALUES (?)");
            $insertStmt->bind_param("s", $plateNumber);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $response['error'] = "Unregistered vehicle detected";
        $response['isRegistered'] = false;
    }

    $stmt->close();
    $checkStmt->close();
    $conn->close();

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Scanner - Camera Detection</title>
    <script src="https://unpkg.com/tesseract.js@v2.1.0/dist/tesseract.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #121212;
            color: #eee;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
        }
        .container {
            max-width: 1200px;
            width: 100%;
            background: #1e1e1e;
            border-radius: 12px;
            box-shadow: 0 0 20px #d00000aa;
            padding: 2rem;
        }
        header {
            text-align: center;
            margin-bottom: 2rem;
        }
        header img {
            height: 45px;
            filter: brightness(0) invert(1);
            margin-bottom: 1rem;
        }
        header h1 {
            font-size: 2.4rem;
            color: #d00000;
            letter-spacing: 2px;
            margin-bottom: 0.2rem;
            font-weight: 700;
        }
        header p {
            color: #bbb;
            font-size: 1.1rem;
        }
        .search-method {
            background: #292929;
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid #333;
        }
        .search-method h3 {
            color: #d00000;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .camera-container {
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
            background: #1e1e1e;
            border-radius: 8px;
            overflow: hidden;
        }
        #video {
            width: 100%;
            display: block;
        }
        #canvas {
            display: none;
        }
        .scan-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
        }
        .plate-box {
            position: absolute;
            border: 3px solid #00ff00;
            border-radius: 4px;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
            display: none;
            transition: all 0.3s ease;
        }
        .plate-box.active {
            display: block;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(0, 255, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 0, 0); }
        }
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #00ff00;
            top: 50%;
            animation: scan 2s linear infinite;
        }
        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }
        .plate-input {
            margin-top: 1rem;
            text-align: center;
        }
        .plate-input input {
            font-size: 1.2rem;
            letter-spacing: 2px;
            text-align: center;
            padding: 0.75rem;
            border: 2px solid #444;
            border-radius: 6px;
            background: #1e1e1e;
            color: #fff;
            width: 100%;
            max-width: 300px;
        }
        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        button {
            background: #d00000;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:hover {
            background: #ff0000;
            transform: translateY(-2px);
        }
        button:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }
        .status {
            margin-top: 1rem;
            text-align: center;
            font-size: 1.1rem;
            color: #bbb;
        }
        .status.active {
            color: #d00000;
        }
        .status {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: 4px;
            text-align: center;
            font-weight: 500;
        }
        .status.success {
            background: rgba(0, 255, 0, 0.1);
            color: #00ff00;
        }
        .status.error {
            background: rgba(255, 0, 0, 0.1);
            color: #ff0000;
        }
        .status.info {
            background: rgba(0, 0, 255, 0.1);
            color: #00ffff;
        }
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
        .tabs {
            margin-top: 1rem;
        }
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tab-buttons button {
            flex: 1;
            background: #292929;
            color: #eee;
            border: 1px solid #444;
            padding: 0.75rem;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
        }
        .tab-buttons button.active {
            background: #d00000;
            border-color: #d00000;
        }
        .tab-content {
            display: none;
            padding: 1rem;
            background: #292929;
            border-radius: 8px;
        }
        .tab-content.active {
            display: block;
        }
        .info-group {
            margin-bottom: 1rem;
        }
        .info-label {
            font-size: 0.85rem;
            color: #bbb;
            text-transform: uppercase;
        }
        .info-value {
            font-size: 1.1rem;
            color: #fff;
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
        <h1>Vehicle Scanner</h1>
        <p>Scan vehicle license plates using camera</p>
    </header>

    <div class="nav-buttons">
        <a href="search-vehicle.php" class="nav-btn">Manual Search</a>
        <a href="scan-vehicle.php" class="nav-btn active">Camera Scanner</a>
    </div>

    <!-- Camera Scan Section -->
    <div class="search-method">
        <h3>Smart Plate Detection</h3>
        <div class="camera-container">
            <video id="video" autoplay playsinline></video>
            <canvas id="canvas" style="display: none;"></canvas>
            <div class="scan-overlay">
                <div class="plate-box"></div>
                <div class="scan-line"></div>
            </div>
        </div>
        <div class="plate-input">
            <input type="text" id="plateNumber" placeholder="Detected plate number" readonly>
            <button id="searchButton" disabled>Search</button>
        </div>
        <div class="buttons">
            <button id="startButton">Start Camera</button>
            <button id="stopButton" disabled>Stop Camera</button>
        </div>
        <div id="status" class="status">Ready to scan</div>
    </div>

    <div id="results" style="display: none;">
        <div class="tabs">
            <div class="tab-buttons">
                <button class="active" data-tab="vehicleTab">🚗 Vehicle Info</button>
                <button data-tab="ownerTab">👤 Owner Info</button>
                <button data-tab="driverTab">🧑‍✈️ Authorized Driver</button>
            </div>

            <div id="vehicleTab" class="tab-content active">
                <!-- Vehicle info will be populated here -->
            </div>

            <div id="ownerTab" class="tab-content">
                <!-- Owner info will be populated here -->
            </div>

            <div id="driverTab" class="tab-content">
                <!-- Driver info will be populated here -->
            </div>
        </div>
    </div>
</main>

<script>
    // Initialize video elements and canvas
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const status = document.getElementById('status');
    const startButton = document.getElementById('startButton');
    const stopButton = document.getElementById('stopButton');
    const searchButton = document.getElementById('searchButton');
    const plateInput = document.getElementById('plateNumber');
    const plateBox = document.querySelector('.plate-box');
    const results = document.getElementById('results');

    // Initialize Tesseract worker
    const worker = Tesseract.createWorker();
    
    // Camera state variables
    let stream = null;
    let isScanning = false;
    let lastDetectedPlate = '';
    let lastSearchTime = 0;
    const SEARCH_COOLDOWN = 2000; // 2 seconds cooldown

    // Country-specific regex patterns with strict validation
    const PLATE_PATTERNS = {
        'Zimbabwe': /^[A-Z]{2,3}\s?\d{3,4}$/,
        'Mozambique': /^[A-Z]{2,3}-\d{3}-MZ$/,
        'Zambia': /^GRZ\s?\d{3,4}$/,
        'South Africa': /^[A-Z]{2}\s?\d{3}[-\s]?\d{3}$/
    };

    // Minimum confidence threshold for OCR
    const MIN_CONFIDENCE = 60;

    // Initialize camera on page load
    window.addEventListener('DOMContentLoaded', async () => {
        await initTesseract();
        startCamera();
    });

    async function initTesseract() {
        try {
            await worker.load();
            await worker.loadLanguage('eng');
            await worker.initialize('eng');
            await worker.setParameters({
                tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-',
                tessedit_pageseg_mode: Tesseract.PSM.SINGLE_LINE,
                tessedit_ocr_engine_mode: Tesseract.OEM.LSTM_ONLY,
                preserve_interword_spaces: '0',
                tessedit_ocr_engine_mode: Tesseract.OEM.LSTM_ONLY,
                tessjs_create_pdf: '0',
                tessjs_create_hocr: '0',
                tessjs_create_tsv: '0',
                tessjs_create_box: '0',
                tessjs_create_unlv: '0',
                tessjs_create_osd: '0'
            });
            status.textContent = 'Ready to scan';
            status.className = 'status info';
        } catch (err) {
            status.textContent = 'Error initializing OCR: ' + err.message;
            status.className = 'status error';
        }
    }

    async function startCamera() {
        try {
            // First try to get the environment camera (rear camera on mobile)
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                        frameRate: { ideal: 30 }
                    }
                });
            } catch (envError) {
                console.log('Environment camera not available, trying user camera:', envError);
                // Fallback to user camera (front camera on mobile)
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        frameRate: { ideal: 30 }
                    }
                });
            }

            if (!stream) {
                throw new Error('No camera stream available');
            }

            video.srcObject = stream;
            
            // Wait for video to be ready
            await new Promise((resolve, reject) => {
                video.onloadedmetadata = () => {
                    video.play()
                        .then(resolve)
                        .catch(reject);
                };
                video.onerror = () => reject(new Error('Video element error'));
            });

            startButton.disabled = true;
            stopButton.disabled = false;
            status.textContent = '🔍 Scanning for license plate...';
            status.className = 'status info';
            isScanning = true;

            detectPlate();
        } catch (err) {
            console.error('Camera error:', err);
            let errorMessage = 'Error accessing camera: ';
            
            if (err.name === 'NotAllowedError') {
                errorMessage += 'Camera access was denied. Please allow camera access in your browser settings.';
            } else if (err.name === 'NotFoundError') {
                errorMessage += 'No camera found. Please connect a camera and try again.';
            } else if (err.name === 'NotReadableError') {
                errorMessage += 'Camera is in use by another application. Please close other applications using the camera.';
            } else if (err.name === 'OverconstrainedError') {
                errorMessage += 'Camera does not support the requested resolution. Trying with lower resolution...';
                // Try again with lower resolution
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            width: { ideal: 640 },
                            height: { ideal: 480 }
                        }
                    });
                    video.srcObject = stream;
                    await video.play();
                    startButton.disabled = true;
                    stopButton.disabled = false;
                    status.textContent = '🔍 Scanning for license plate...';
                    status.className = 'status info';
                    isScanning = true;
                    detectPlate();
                    return;
                } catch (retryErr) {
                    errorMessage += ' Failed to start camera with lower resolution.';
                }
            } else {
                errorMessage += err.message;
            }
            
            status.textContent = errorMessage;
            status.className = 'status error';
            startButton.disabled = false;
            stopButton.disabled = true;
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => {
                track.stop();
                track.enabled = false;
            });
            stream = null;
        }
        if (video.srcObject) {
            video.srcObject = null;
        }
        startButton.disabled = false;
        stopButton.disabled = true;
        searchButton.disabled = true;
        plateInput.value = '';
        plateBox.classList.remove('active');
        status.textContent = 'Camera stopped';
        status.className = 'status info';
        isScanning = false;
    }

    async function detectPlate() {
        if (!isScanning) return;

        try {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Preprocess the image
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const processedImageData = preprocessImage(imageData);
            ctx.putImageData(processedImageData, 0, 0);

            const { data: { text, confidence, words } } = await worker.recognize(canvas);
            
            // Only process if confidence is high enough
            if (confidence < MIN_CONFIDENCE) {
                plateBox.classList.remove('active');
                searchButton.disabled = true;
                status.textContent = '⚠️ Low confidence reading. Please adjust camera position.';
                status.className = 'status error';
                requestAnimationFrame(detectPlate);
                return;
            }

            // Clean and validate the text
            const cleanedText = text
                .replace(/[^A-Z0-9 -]/g, '') // Remove invalid characters
                .replace(/\s+/g, ' ')        // Normalize spaces
                .trim();

            // Only update input if we have a valid plate
            let detectedCountry = null;
            let validPlate = null;

            // Check against all country patterns
            for (const [country, pattern] of Object.entries(PLATE_PATTERNS)) {
                if (pattern.test(cleanedText)) {
                    detectedCountry = country;
                    validPlate = cleanedText;
                    break;
                }
            }

            if (validPlate) {
                if (validPlate !== lastDetectedPlate) {
                    lastDetectedPlate = validPlate;
                    searchButton.disabled = false;
                    plateInput.value = validPlate;
                    
                    // Update plate box position
                    if (words && words.length > 0) {
                        const word = words[0];
                        const box = word.bbox;
                        plateBox.style.left = (box.x0 / canvas.width * 100) + '%';
                        plateBox.style.top = (box.y0 / canvas.height * 100) + '%';
                        plateBox.style.width = ((box.x1 - box.x0) / canvas.width * 100) + '%';
                        plateBox.style.height = ((box.y1 - box.y0) / canvas.height * 100) + '%';
                        plateBox.classList.add('active');
                    }

                    status.textContent = `✅ ${detectedCountry} plate detected: ${validPlate}`;
                    status.className = 'status success';

                    const now = Date.now();
                    if (now - lastSearchTime >= SEARCH_COOLDOWN) {
                        lastSearchTime = now;
                        await searchPlate(validPlate);
                    }
                }
            } else {
                // Don't show random text in the input
                plateInput.value = '';
                plateBox.classList.remove('active');
                searchButton.disabled = true;
                status.textContent = '🔍 Looking for license plate...';
                status.className = 'status info';
            }
        } catch (err) {
            console.error('OCR Error:', err);
            plateBox.classList.remove('active');
            status.textContent = '⚠️ Error processing image. Please try again.';
            status.className = 'status error';
        }

        requestAnimationFrame(detectPlate);
    }

    // Enhanced image preprocessing function
    function preprocessImage(imageData) {
        const data = imageData.data;
        
        // Convert to grayscale and apply adaptive threshold
        for (let i = 0; i < data.length; i += 4) {
            const avg = (data[i] + data[i + 1] + data[i + 2]) / 3;
            // Use a higher threshold for better contrast
            const threshold = 150;
            const value = avg > threshold ? 255 : 0;
            data[i] = data[i + 1] = data[i + 2] = value;
        }

        return imageData;
    }

    async function searchPlate(plateNumber) {
        if (!plateNumber) return;

        try {
            status.textContent = '🔍 Searching database...';
            status.className = 'status info';

            const response = await fetch('scan-vehicle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `plate_number=${encodeURIComponent(plateNumber)}`
            });

            const data = await response.json();
            
            if (data.success && data.isRegistered) {
                displayResults(data.data);
                status.textContent = '🚗 Vehicle found in database';
                status.className = 'status success';
            } else if (!data.isRegistered) {
                status.textContent = '🚨 Unregistered vehicle detected - Logged for review';
                status.className = 'status error';
                results.style.display = 'none';
            } else {
                status.textContent = '⚠️ No match found in database';
                status.className = 'status error';
                results.style.display = 'none';
            }
        } catch (err) {
            status.textContent = '❌ Error searching database';
            status.className = 'status error';
        }
    }

    function displayResults(vehicle) {
        results.style.display = 'block';
        
        document.getElementById('vehicleTab').innerHTML = `
            <div class="info-group"><div class="info-label">Make</div><div class="info-value">${vehicle.make}</div></div>
            <div class="info-group"><div class="info-label">Reg Number</div><div class="info-value">${vehicle.regNumber}</div></div>
            <div class="info-group"><div class="info-label">Disk Number</div><div class="info-value">${vehicle.disk_number || 'Not Assigned'}</div></div>
            <div class="info-group"><div class="info-label">Registration Date</div><div class="info-value">${vehicle.registration_date}</div></div>
        `;

        document.getElementById('ownerTab').innerHTML = `
            <div class="info-group"><div class="info-label">Owner</div><div class="info-value">${vehicle.owner}</div></div>
            <div class="info-group"><div class="info-label">ID Number</div><div class="info-value">${vehicle.idNumber}</div></div>
            <div class="info-group"><div class="info-label">Phone</div><div class="info-value">${vehicle.phone}</div></div>
            <div class="info-group"><div class="info-label">Email</div><div class="info-value">${vehicle.email}</div></div>
        `;

        document.getElementById('driverTab').innerHTML = `
            <div class="info-group"><div class="info-label">Full Name</div><div class="info-value">${vehicle.fullname || 'N/A'}</div></div>
            <div class="info-group"><div class="info-label">License Number</div><div class="info-value">${vehicle.licenseNumber || 'N/A'}</div></div>
        `;
    }

    // Event listeners
    startButton.addEventListener('click', startCamera);
    stopButton.addEventListener('click', stopCamera);
    searchButton.addEventListener('click', () => {
        if (plateInput.value) {
            searchPlate(plateInput.value);
        }
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        stopCamera();
    });

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