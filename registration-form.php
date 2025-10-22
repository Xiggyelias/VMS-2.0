<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/middleware/security.php';
SecurityMiddleware::initialize();

// Generate CSRF token
$csrfToken = SecurityMiddleware::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Registration - Vehicle Registration System</title>
    <?php includeCommonAssets(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #ffffff 0%, #f6f8ff 50%, #fff4f4 100%);
        }
        .header {
            background: linear-gradient(90deg, rgba(208,0,0,1) 0%, rgba(176,0,0,1) 100%);
            color: #fff;
        }
        .header-content { display: flex; align-items: center; gap: 1rem; }
        .form-container {
            max-width: 860px;
            margin: 2rem auto;
            background: #fff;
            border: 1px solid #eef1f4;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2rem;
        }
        .form-label { font-weight: 600; color: #333; margin-bottom: 0.5rem; display: block; }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #fff;
        }
        .form-input:focus {
            border-color: var(--primary-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(208, 0, 0, 0.12);
        }
        select.form-input { appearance: none; background-image: none; }
        .section-title { align-items: center; gap: 0.5rem; margin: 1.5rem 0 1rem; }
        .section-title i { color: var(--primary-red); }
        .vehicle-section {
            border: 1px solid #eef1f4;
            border-radius: 12px;
            padding: 1.25rem;
            background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .add-vehicle-btn, .add-driver-btn {
            background: #fff;
            border: 1px dashed var(--primary-red);
        }
        .btn.btn-primary { border-radius: 10px; padding: 0.9rem 1.25rem; font-weight: 600; }
        .alert { border-radius: 10px; }
        .vehicle-section {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }
        .vehicle-section:hover {
            border-color: var(--primary-red);
        }
        .remove-vehicle {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #ffe6e6;
            color: var(--primary-red);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .remove-vehicle:hover {
            background: var(--primary-red);
            color: white;
        }
        .add-vehicle-btn, .add-driver-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--white);
            color: var(--primary-red);
            border: 2px dashed var(--primary-red);
            padding: 1rem;
            width: 100%;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        .add-driver-btn {
            margin: 1rem 0;
            padding: 0.75rem;
            font-size: 0.9rem;
        }
        .add-vehicle-btn:hover, .add-driver-btn:hover {
            background: rgba(208, 0, 0, 0.1);
        }
        .drivers-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #ddd;
        }
        .driver-card {
            background: #f8f8f8;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .remove-driver {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: none;
            border: none;
            color: var(--primary-red);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
        }
        .remove-driver:hover {
            color: #b00000;
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .section-title i {
            color: var(--primary-red);
            font-size: 1.2rem;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
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

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-red);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* New styles for tooltips and info icons */
        .info-icon {
            color: var(--primary-red);
            cursor: pointer;
            margin-left: 0.5rem;
            font-size: 1.1rem;
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 250px;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 0.5rem;
            display: none;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .info-icon:hover .tooltip {
            display: block;
        }

        .help-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .help-modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .help-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .help-modal-close:hover {
            color: var(--primary-red);
        }

        .help-modal h3 {
            color: var(--primary-red);
            margin-bottom: 1rem;
        }

        .help-modal p {
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .help-modal ul {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .help-modal li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Add loading overlay -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
        <p>Saving your registration...</p>
    </div>

    <!-- Add alert container -->
    <div id="alertContainer"></div>

    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="header-logo" style="width: 80px;">
                    <a href="login.php">
                        <img src="AULogo.png" alt="AULogo">
                    </a>
                </div>
                <div>
                <h1>Vehicle Registration Form</h1>
                    <div style="opacity:0.9">Provide accurate details to complete your registration</div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <form id="registrationForm" onsubmit="return handleSubmit(event)">
                <!-- CSRF Token -->
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <h2 style="color: var(--primary-red); margin-bottom: 1rem; display:flex; align-items:center; gap:.5rem;"><i class="fa fa-user"></i> Applicant Information</h2>
                
                <div class="form-group">
                    <label for="registrantType" class="form-label">Registrant Type <span class="required">*</span></label>
                    <select id="registrantType" name="registrantType" class="form-input" required onchange="handleRegistrantTypeChange()">
                        <option value="">Select Registrant Type</option>
                        <option value="student">Student </option>
                        <option value="staff">Staff </option>
                        <option value="guest">Guest</option>
                    </select>
                </div>

                <div id="studentRegNoGroup" class="form-group" style="display: none;">
                    <label for="studentRegNo" class="form-label">Student Registration Number <span class="required">*</span></label>
                    <input type="text" id="studentRegNo" name="studentRegNo" class="form-input">
                </div>
                
                <div id="staffRegNoGroup" class="form-group" style="display: none;">
                    <label for="staffsregno" class="form-label">Staff Registration Number <span class="required">*</span></label>
                    <input type="text" id="staffsregno" name="staffsRegNo" class="form-input">
                </div>

                <div class="form-group">
                    <label for="fullName" class="form-label">Applicant's Full Names <span class="required">*</span></label>
                    <input type="text" id="fullName" name="fullName" class="form-input" required>
                </div>
                  <!-- Add Password Section -->
                  <div class="form-group">
                      <label for="password" class="form-label">Password</label>
                      <input type="password" id="password" name="password" class="form-input" required>
                      <small class="form-text text-muted">Please choose a secure password for your account</small>
                  </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email <span class="required">*</span></label>
                    <input type="email" id="Email" name="Email" class="form-input" required>
                </div>

                <div id="collegeSection">
                  <div class="form-group">
                        <label for="college" class="form-label">College <span class="required">*</span></label>
                        <select id="college" name="college" class="form-input">
                        <option value="">Select College</option>
                        <option value="College of Business and Management Sciences">College of Business and Management Sciences</option>
                        <option value="College of Engineering and Applied Sciences">College of Engineering and Applied Sciences</option>
                        <option value="College of Health, Agriculture and Natural Sciences">College of Health, Agriculture and Natural Sciences</option>
                        <option value="School of Law">School of Law</option>
                        <option value="College of Social Sciences, Theology, Humanities and Education">College of Social Sciences, Theology, Humanities and Education</option>
                            <option value="Non Teaching Staffs">Non Teaching Staff</option>
                    </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="idNumber" class="form-label">ID/Passport Number</label>
                    <input type="text" id="idNumber" name="idNumber" class="form-input" required>
                </div>

                <h2 style="color: var(--primary-red); margin: 2rem 0 1rem; display:flex; align-items:center; gap:.5rem;"><i class="fa fa-id-card"></i> Driver's License Information</h2>

                <div class="form-group">
                    <label for="licenseNumber" class="form-label">Driver's License Number <span class="required">*</span></label>
                    <input type="text" id="licenseNumber" name="licenseNumber" class="form-input" required>
                    <small class="form-text text-muted">Students must have a valid driver's license to register a vehicle</small>
                </div>

                <div class="form-group">
                    <label for="licenseClass" class="form-label">Driver's License Class <span class="required">*</span></label>
                    <select id="licenseClass" name="licenseClass" class="form-input" required>
                        <option value="">Select Class</option>
                        <option value="A">Class A</option>
                        <option value="B">Class B</option>
                        <option value="C">Class C</option>
                        <option value="D">Class D</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="licenseDate" class="form-label">Driver's License Date Issued <span class="required">*</span></label>
                    <input type="date" id="licenseDate" name="licenseDate" class="form-input" required>
                </div>

                <h2 style="color: var(--primary-red); margin: 2rem 0 1rem; display:flex; align-items:center; gap:.5rem;">
                    <i class="fa fa-car"></i> Vehicle Information
                    <span class="info-icon" onclick="showHelpModal('vehicle')">
                        ℹ️
                        <span class="tooltip">Click for more information about vehicle registration</span>
                    </span>
                </h2>

                <div id="studentVehicleWarning" class="alert alert-warning" style="display: none; margin-bottom: 1rem;">
                    <strong>Important:</strong> Students are allowed to register only one vehicle at a time. If you need to register a different vehicle, you must first deactivate your current vehicle.
                </div>

                <div id="vehiclesContainer">
                  <div class="vehicle-section" data-vehicle-index="0">
    <div class="section-title">
        <i>🚗</i>
                            <h3>Vehicle Information</h3>
    </div>

    <div class="form-group">
                            <label class="form-label">Registration Number <span class="required">*</span></label>
        <input type="text" name="vehicles[0][regNumber]" class="form-input" required>
                            <small class="form-text text-muted">Only registered vehicles with valid number plates are allowed</small>
    </div>

    <div class="form-group">
                            <label class="form-label">Make <span class="required">*</span></label>
        <input type="text" name="vehicles[0][make]" class="form-input" required>
    </div>

    <div class="form-group">
                            <label class="form-label">Registered Owner <span class="required">*</span></label>
        <input type="text" name="vehicles[0][owner]" class="form-input" required>
    </div>

    <div class="form-group">
                            <label class="form-label">Address <span class="required">*</span></label>
        <textarea name="vehicles[0][address]" class="form-input" rows="3" required></textarea>
    </div>

    <div class="form-group">
                            <label class="form-label">Plate Number <span class="required">*</span></label>
                            <input type="text" name="vehicles[0][PlateNumber]" class="form-input" required>
                            <small class="form-text text-muted">Enter the vehicle's official license plate number</small>
    </div>

                        <!-- Authorized Drivers Section -->
                        <div class="drivers-section">
                            <div class="section-title">
                                <i>👥</i>
                                <h4>Authorized Drivers
                                    <span class="info-icon" onclick="showHelpModal('driver')">
                                        ℹ️
                                        <span class="tooltip">Click for more information about authorized drivers</span>
                                    </span>
                                </h4>
                            </div>
                            
                            <div class="drivers-container" id="driversContainer_0">
                                <!-- Initial driver card will be added by JavaScript -->
                            </div>

                            <button type="button" class="add-driver-btn" onclick="addDriver(0)">
                                ➕ Add Authorized Driver
                            </button>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 2rem; display:flex; gap:.75rem; justify-content:center;">
                    <a href="login.php" class="btn btn-secondary" style="text-decoration:none"><i class="fa fa-arrow-left"></i> Back to Login</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit Registration</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Help Modals -->
    <div id="vehicleHelpModal" class="help-modal">
        <div class="help-modal-content">
            <button class="help-modal-close" onclick="closeHelpModal('vehicle')">&times;</button>
            <h3>Vehicle Information Guide</h3>
            <p>This section allows you to register your vehicle for campus access.</p>
            <p><strong>Important Policy:</strong></p>
            <ul>
                <li>Students are allowed to register only one vehicle at a time</li>
                <li>If you need to register a different vehicle, you must first deactivate your current vehicle</li>
                <li>Staff and guests may register multiple vehicles</li>
            </ul>
            <p>For your vehicle, you'll need to provide:</p>
            <ul>
                <li><strong>Registration Number:</strong> Your vehicle's official registration number</li>
                <li><strong>Make:</strong> The manufacturer of your vehicle (e.g., Toyota, Honda)</li>
                <li><strong>Registered Owner:</strong> The legal owner of the vehicle</li>
                <li><strong>Address:</strong> The registered address for the vehicle</li>
                <li><strong>Plate Number:</strong> Your vehicle's license plate number</li>
            </ul>
        </div>
    </div>

    <div id="driverHelpModal" class="help-modal">
        <div class="help-modal-content">
            <button class="help-modal-close" onclick="closeHelpModal('driver')">&times;</button>
            <h3>Authorized Drivers Guide</h3>
            <p>This section allows you to register other drivers who are authorized to use your vehicle on campus.</p>
            <p>For each authorized driver, you'll need to provide:</p>
            <ul>
                <li><strong>Full Name:</strong> The complete name of the authorized driver</li>
                <li><strong>Driver's License Number:</strong> Their valid driver's license number</li>
                <li><strong>Contact Information:</strong> Their phone number (optional but recommended)</li>
            </ul>
            <p>You can add multiple authorized drivers for each vehicle by clicking the "Add Authorized Driver" button.</p>
            <p><strong>Note:</strong> All authorized drivers must have a valid driver's license and will be subject to the same campus driving regulations as the primary vehicle owner.</p>
        </div>
    </div>

    <script>
        let vehicleCount = 1;

        // Driver card template function
        function getDriverTemplate(vehicleIndex, driverIndex) {
    return `
        <div class="driver-card" data-driver-index="${driverIndex}">
            <button type="button" class="remove-driver" onclick="removeDriver(this)">✕</button>
            
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="vehicles[${vehicleIndex}][drivers][${driverIndex}][fullName]" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Driver's License Number</label>
                <input type="text" name="vehicles[${vehicleIndex}][drivers][${driverIndex}][licenseNumber]" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Contact Information (Optional)</label>
                <input type="tel" name="vehicles[${vehicleIndex}][drivers][${driverIndex}][contact]" class="form-input" placeholder="Phone number">
            </div>
        </div>
    `;

        }

        // Add driver function
        function addDriver(vehicleIndex) {
            const driversContainer = document.getElementById(`driversContainer_${vehicleIndex}`);
            const driverIndex = driversContainer.children.length;
            
            const driverElement = document.createElement('div');
            driverElement.innerHTML = getDriverTemplate(vehicleIndex, driverIndex);
            driversContainer.appendChild(driverElement.firstElementChild);
        }

        // Remove driver function
        function removeDriver(button) {
            const driverCard = button.closest('.driver-card');
            const driversContainer = driverCard.parentElement;
            driverCard.remove();
            
            // Reindex remaining drivers
            const vehicleIndex = driversContainer.closest('.vehicle-section').dataset.vehicleIndex;
            Array.from(driversContainer.children).forEach((card, index) => {
                card.dataset.driverIndex = index;
                card.querySelectorAll('input').forEach(input => {
                    const name = input.getAttribute('name');
                    input.setAttribute('name', name.replace(/drivers\[\d+\]/, `drivers[${index}]`));
                });
            });
        }

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertContainer.appendChild(alert);

            // Remove alert after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        function showLoading() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.querySelector('.loading-overlay').style.display = 'none';
        }

        // Check for error parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            showAlert('An error occurred while saving your registration. Please try again.', 'danger');
        }

        // Add this at the beginning of your script section
        function handleRegistrantTypeChange() {
            const registrantType = document.getElementById('registrantType').value;
            const studentRegNoGroup = document.getElementById('studentRegNoGroup');
            const staffRegNoGroup = document.getElementById('staffRegNoGroup');
            const collegeSection = document.getElementById('collegeSection');
            const collegeSelect = document.getElementById('college');
            const studentVehicleWarning = document.getElementById('studentVehicleWarning');
            const licenseFields = document.querySelectorAll('#licenseNumber, #licenseClass, #licenseDate');

            // Reset all fields
            document.getElementById('studentRegNo').value = '';
            document.getElementById('staffsregno').value = '';
            collegeSelect.value = '';

            // Hide all registration number fields initially
            studentRegNoGroup.style.display = 'none';
            staffRegNoGroup.style.display = 'none';

            // Show/hide fields based on selection
            switch(registrantType) {
                case 'student':
                    studentRegNoGroup.style.display = 'block';
                    collegeSection.style.display = 'block';
                    collegeSelect.required = true;
                    studentVehicleWarning.style.display = 'block';
                    // Make license fields required for students
                    licenseFields.forEach(field => {
                        field.required = true;
                        field.closest('.form-group').style.display = 'block';
                    });
                    break;
                case 'staff':
                    staffRegNoGroup.style.display = 'block';
                    collegeSection.style.display = 'block';
                    collegeSelect.required = true;
                    studentVehicleWarning.style.display = 'none';
                    // Make license fields required for staff
                    licenseFields.forEach(field => {
                        field.required = true;
                        field.closest('.form-group').style.display = 'block';
                    });
                    break;
                case 'guest':
                    collegeSection.style.display = 'none';
                    collegeSelect.required = false;
                    studentVehicleWarning.style.display = 'none';
                    // Make license fields optional for guests
                    licenseFields.forEach(field => {
                        field.required = false;
                        field.closest('.form-group').style.display = 'block';
                    });
                    break;
                default:
                    collegeSection.style.display = 'none';
                    collegeSelect.required = false;
                    studentVehicleWarning.style.display = 'none';
                    // Hide license fields by default
                    licenseFields.forEach(field => {
                        field.required = false;
                        field.closest('.form-group').style.display = 'none';
                    });
            }
        }

        // Add validation for vehicle registration
        function validateVehicleRegistration(event) {
            const registrantType = document.getElementById('registrantType').value;
            const licenseNumber = document.getElementById('licenseNumber').value.trim();
            const plateNumber = document.querySelector('input[name="vehicles[0][PlateNumber]"]').value.trim();
            const regNumber = document.querySelector('input[name="vehicles[0][regNumber]"]').value.trim();

            if (registrantType === 'student' && !licenseNumber) {
                showAlert('Students must have a valid driver\'s license to register a vehicle', 'danger');
                event.preventDefault();
                return false;
            }

            if (!plateNumber || !regNumber) {
                showAlert('Vehicle must have a valid registration number and plate number', 'danger');
                event.preventDefault();
                return false;
            }

            return true;
        }

        // Modify the existing handleSubmit function to validate based on registrant type
        function handleSubmit(event) {
            event.preventDefault();
            
            const registrantType = document.getElementById('registrantType').value;
            const studentRegNo = document.getElementById('studentRegNo').value.trim();
            const staffRegNo = document.getElementById('staffsregno').value.trim();
            const college = document.getElementById('college').value;

            // Validate based on registrant type
            if (registrantType === 'student' && !studentRegNo) {
                showAlert('Please enter your Student Registration Number.', 'danger');
                return false;
            }
            if (registrantType === 'staff' && !staffRegNo) {
                showAlert('Please enter your Staff Registration Number.', 'danger');
                return false;
            }
            if ((registrantType === 'student' || registrantType === 'staff') && !college) {
                showAlert('Please select your College.', 'danger');
                return false;
            }
            
            // Show loading overlay
            showLoading();

            const formData = new FormData(event.target);
            
            // Process vehicles and drivers data
            const vehicles = [];
            const vehicleSections = document.querySelectorAll('.vehicle-section');
            
            vehicleSections.forEach((section, vehicleIndex) => {
                const vehicle = {
                    regNumber: formData.get(`vehicles[${vehicleIndex}][regNumber]`),
                    make: formData.get(`vehicles[${vehicleIndex}][make]`),
                    owner: formData.get(`vehicles[${vehicleIndex}][owner]`),
                    address: formData.get(`vehicles[${vehicleIndex}][address]`),
                    PlateNumber: formData.get(`vehicles[${vehicleIndex}][PlateNumber]`),
                    drivers: []
                };

                // Get all driver cards for this vehicle
                const driverCards = section.querySelectorAll('.driver-card');
                driverCards.forEach((card, driverIndex) => {
                    const driver = {
                        fullName: formData.get(`vehicles[${vehicleIndex}][drivers][${driverIndex}][fullName]`),
                        licenseNumber: formData.get(`vehicles[${vehicleIndex}][drivers][${driverIndex}][licenseNumber]`),
                        contact: formData.get(`vehicles[${vehicleIndex}][drivers][${driverIndex}][contact]`)
                    };
                    
                    // Only add driver if required fields are filled
                    if (driver.fullName && driver.licenseNumber) {
                        vehicle.drivers.push(driver);
                    }
                });

                vehicles.push(vehicle);
            });

            // Add vehicles data to formData
            formData.append('vehicles', JSON.stringify(vehicles));
            
            // Debug log
            console.log('Form data being sent:', Object.fromEntries(formData));
            
            fetch('submit_registration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                hideLoading();
                if (data.includes('error')) {
                    throw new Error(data);
                }
                showAlert('Registration completed successfully! Redirecting to login page...', 'success');
                
                // Redirect to login page after 2 seconds
                setTimeout(() => {
                    window.location.href = 'login.php?registration=success';
                }, 2000);
            })
            .catch(error => {
                hideLoading();
                showAlert('An error occurred while saving your registration: ' + error.message, 'danger');
                console.error('Error:', error);
            });

            return false;
        }

        // Add initial driver section to first vehicle
        document.addEventListener('DOMContentLoaded', () => {
            addDriver(0);
            loadDraft(); // Load saved draft if exists
            setupAutoSave(); // Setup autosave functionality
        });

        // Load saved draft data
        function loadDraft() {
            fetch('get_registration_draft.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.draft) {
                        populateFormWithDraft(data.draft);
                        showAlert('Your previous progress has been loaded. You can continue from where you left off.', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error loading draft:', error);
                });
        }

        // Populate form with draft data
        function populateFormWithDraft(draft) {
            // Basic form fields
            if (draft.registrantType) {
                document.getElementById('registrantType').value = draft.registrantType;
                updateFormVisibility();
            }
            if (draft.studentRegNo) document.getElementById('studentRegNo').value = draft.studentRegNo;
            if (draft.staffsregno) document.getElementById('staffsregno').value = draft.staffsregno;
            if (draft.college) document.getElementById('college').value = draft.college;
            if (draft.idNumber) document.getElementById('idNumber').value = draft.idNumber;
            if (draft.licenseNumber) document.getElementById('licenseNumber').value = draft.licenseNumber;
            if (draft.licenseClass) document.getElementById('licenseClass').value = draft.licenseClass;
            if (draft.licenseDate) document.getElementById('licenseDate').value = draft.licenseDate;

            // Vehicles
            if (draft.vehicles && draft.vehicles.length > 0) {
                // Remove existing vehicles except the first one
                const vehicleSections = document.querySelectorAll('.vehicle-section');
                for (let i = 1; i < vehicleSections.length; i++) {
                    vehicleSections[i].remove();
                }

                draft.vehicles.forEach((vehicle, index) => {
                    if (index > 0) {
                        addVehicle(); // Add new vehicle section
                    }
                    
                    const vehicleSection = document.querySelectorAll('.vehicle-section')[index];
                    if (vehicleSection) {
                        if (vehicle.regNumber) vehicleSection.querySelector('[name*="[regNumber]"]').value = vehicle.regNumber;
                        if (vehicle.make) vehicleSection.querySelector('[name*="[make]"]').value = vehicle.make;
                        if (vehicle.owner) vehicleSection.querySelector('[name*="[owner]"]').value = vehicle.owner;
                        if (vehicle.address) vehicleSection.querySelector('[name*="[address]"]').value = vehicle.address;
                        if (vehicle.PlateNumber) vehicleSection.querySelector('[name*="[PlateNumber]"]').value = vehicle.PlateNumber;

                        // Drivers for this vehicle
                        if (vehicle.drivers && vehicle.drivers.length > 0) {
                            // Remove existing drivers
                            const driverCards = vehicleSection.querySelectorAll('.driver-card');
                            driverCards.forEach(card => card.remove());

                            vehicle.drivers.forEach((driver, driverIndex) => {
                                if (driverIndex > 0) {
                                    addDriver(index);
                                }
                                
                                const driverCards = vehicleSection.querySelectorAll('.driver-card');
                                const driverCard = driverCards[driverIndex];
                                if (driverCard) {
                                    if (driver.fullName) driverCard.querySelector('[name*="[fullName]"]').value = driver.fullName;
                                    if (driver.licenseNumber) driverCard.querySelector('[name*="[licenseNumber]"]').value = driver.licenseNumber;
                                    if (driver.contact) driverCard.querySelector('[name*="[contact]"]').value = driver.contact;
                                }
                            });
                        }
                    }
                });
            }
        }

        // Setup autosave functionality
        function setupAutoSave() {
            let saveTimeout;
            const form = document.getElementById('registrationForm');
            
            // Save draft every 30 seconds when user is typing
            form.addEventListener('input', () => {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(saveDraft, 30000); // 30 seconds delay
            });

            // Save draft when user leaves the page
            window.addEventListener('beforeunload', () => {
                saveDraft();
            });
        }

        // Save current form data as draft
        function saveDraft() {
            const formData = new FormData(document.getElementById('registrationForm'));
            const draftData = {
                registrantType: formData.get('registrantType'),
                studentRegNo: formData.get('studentRegNo'),
                staffsregno: formData.get('staffsregno'),
                college: formData.get('college'),
                idNumber: formData.get('idNumber'),
                licenseNumber: formData.get('licenseNumber'),
                licenseClass: formData.get('licenseClass'),
                licenseDate: formData.get('licenseDate'),
                vehicles: []
            };

            // Collect vehicles data
            const vehicleSections = document.querySelectorAll('.vehicle-section');
            vehicleSections.forEach((section, vehicleIndex) => {
                const vehicle = {
                    regNumber: formData.get(`vehicles[${vehicleIndex}][regNumber]`),
                    make: formData.get(`vehicles[${vehicleIndex}][make]`),
                    owner: formData.get(`vehicles[${vehicleIndex}][owner]`),
                    address: formData.get(`vehicles[${vehicleIndex}][address]`),
                    PlateNumber: formData.get(`vehicles[${vehicleIndex}][PlateNumber]`),
                    drivers: []
                };

                // Collect drivers data
                const driverCards = section.querySelectorAll('.driver-card');
                driverCards.forEach((card, driverIndex) => {
                    const driver = {
                        fullName: formData.get(`vehicles[${vehicleIndex}][drivers][${driverIndex}][fullName]`),
                        licenseNumber: formData.get(`vehicles[${vehicleIndex}][drivers][${driverIndex}][licenseNumber]`),
                        contact: formData.get(`vehicles[${vehicleIndex}][drivers][${driverIndex}][contact]`)
                    };
                    vehicle.drivers.push(driver);
                });

                draftData.vehicles.push(vehicle);
            });

            // Send draft data to server
            fetch('save_registration_draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= htmlspecialchars($csrfToken) ?>'
                },
                body: JSON.stringify(draftData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Draft saved successfully');
                }
            })
            .catch(error => {
                console.error('Error saving draft:', error);
            });
        }

        // Add new functions for help modals
        function showHelpModal(type) {
            const modal = document.getElementById(`${type}HelpModal`);
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeHelpModal(type) {
            const modal = document.getElementById(`${type}HelpModal`);
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('help-modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.help-modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'flex') {
                        modal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        });
    </script>
    
</body>
</html> 