<?php
/**
 * Authentication Functions
 * 
 * This file contains all authentication-related functions including
 * login, logout, session management, and access control.
 */

require_once CONFIG_PATH . '/app.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions/utilities.php';

/**
 * User Login
 * 
 * Authenticates a user and creates a session if credentials are valid.
 * 
 * @param string $identifier User's email address or registration number
 * @param string $password User's password
 * @param string $userType Type of user (student, staff)
 * @return array Array with 'success' boolean and 'message' string
 */
function userLogin($identifier, $password, $userType) {
    try {
        $conn = getLegacyDatabaseConnection();
        
        // Sanitize inputs
        $identifier = sanitizeInput($identifier, 'string');
        $userType = sanitizeInput($userType, 'string');
        
        // Prepare query based on user type
        switch ($userType) {
            case 'student':
                $stmt = $conn->prepare("SELECT * FROM applicants WHERE (studentRegNo = ? OR Email = ?) AND registrantType = 'student'");
                $stmt->bind_param("ss", $identifier, $identifier);
                break;
            case 'staff':
                $stmt = $conn->prepare("SELECT * FROM applicants WHERE (staffsRegNo = ? OR Email = ?) AND registrantType = 'staff'");
                $stmt->bind_param("ss", $identifier, $identifier);
                break;
            // guest removed
            default:
                return ['success' => false, 'message' => 'Invalid user type.'];
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Fallback: try without restricting registrantType, and check both reg numbers and Email
            $stmt->close();
            $fallback = $conn->prepare("SELECT * FROM applicants WHERE (studentRegNo = ? OR staffsRegNo = ? OR Email = ?)");
            $fallback->bind_param("sss", $identifier, $identifier, $identifier);
            $fallback->execute();
            $result = $fallback->get_result();
            if ($result->num_rows === 0) {
                return ['success' => false, 'message' => 'User not found or invalid user type.'];
            }
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password
        if (!verifyPassword($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid password.'];
        }
        
        // Create session
        $_SESSION['user_id'] = $user['applicant_id'];
        $_SESSION['user_email'] = $user['Email'] ?? '';
        $_SESSION['user_name'] = $user['fullName'] ?? '';
        $_SESSION['user_type'] = $user['registrantType'] ?? '';
        $_SESSION['user_college'] = $user['college'] ?? null;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Update last login time
        $updateStmt = $conn->prepare("UPDATE applicants SET last_login = NOW() WHERE applicant_id = ?");
        $updateStmt->bind_param("i", $user['applicant_id']);
        $updateStmt->execute();
        
        $stmt->close();
        $updateStmt->close();
        $conn->close();
        
        return ['success' => true, 'message' => 'Login successful.'];
        
    } catch (Exception $e) {
        logError("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during login. Please try again.'];
    }
}

/**
 * Admin Login
 * 
 * Authenticates an admin user and creates a session if credentials are valid.
 * 
 * @param string $username Admin username
 * @param string $password Admin password
 * @return array Array with 'success' boolean and 'message' string
 */
function adminLogin($username, $password) {
    try {
        $conn = getLegacyDatabaseConnection();
        
        // Sanitize inputs
        $username = sanitizeInput($username, 'string');
        
        // Check if admin table exists, if not create it
        $checkTable = $conn->query("SHOW TABLES LIKE 'admin'");
        if ($checkTable->num_rows === 0) {
            // Create admin table and default admin user
            $createTable = "CREATE TABLE admin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
            
            // Create default admin user
            $defaultPassword = hashPassword('admin123');
            $insertAdmin = "INSERT INTO admin (username, password, email) VALUES ('admin', '$defaultPassword', 'admin@au.ac.zw')";
            $conn->query($insertAdmin);
        }
        
        $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        $admin = $result->fetch_assoc();
        
        // Verify password
        if (!verifyPassword($password, $admin['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Create admin session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['is_admin'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        $stmt->close();
        $conn->close();
        
        return ['success' => true, 'message' => 'Admin login successful.'];
        
    } catch (Exception $e) {
        logError("Admin login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during login. Please try again.'];
    }
}

/**
 * User Logout
 * 
 * Destroys the user session and redirects to login page.
 * 
 * @param string $redirectUrl URL to redirect after logout
 */
function userLogout($redirectUrl = null) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page or specified URL
    $redirectUrl = $redirectUrl ?: BASE_URL . '/login.php';
    redirect($redirectUrl);
}

/**
 * Check if User is Logged In
 * 
 * Checks if a user is currently logged in and session is valid.
 * 
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if User is Admin
 * 
 * Checks if the current user is an admin.
 * 
 * @return bool True if user is admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Get Current User ID
 * 
 * Returns the ID of the currently logged in user.
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get Current User Type
 * 
 * Returns the type of the currently logged in user.
 * 
 * @return string|null User type or null if not logged in
 */
function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Get Current User Email
 * 
 * Returns the email of the currently logged in user.
 * 
 * @return string|null User email or null if not logged in
 */
function getCurrentUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Get Current User Name
 * 
 * Returns the name of the currently logged in user.
 * 
 * @return string|null User name or null if not logged in
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Require Authentication
 * 
 * Redirects to login page if user is not authenticated.
 * 
 * @param string $redirectUrl URL to redirect after login
 */
function requireAuth($redirectUrl = null) {
    if (!isLoggedIn()) {
        // Check if the request is an API call expecting JSON
        $isApiRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
                        (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) || 
                        (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);

        if ($isApiRequest) {
            http_response_code(401); // Unauthorized
            throw new Exception('Authentication required. Please log in.');
        } else {
            $redirectUrl = $redirectUrl ?: getCurrentUrl();
            redirect(BASE_URL . '/login.php?redirect=' . urlencode($redirectUrl));
        }
    }
}

/**
 * Require Admin Access
 * 
 * Redirects to admin login page if user is not an admin.
 * 
 * @param string $redirectUrl URL to redirect after login
 */
function requireAdmin($redirectUrl = null) {
    if (!isAdmin()) {
        $redirectUrl = $redirectUrl ?: getCurrentUrl();
        redirect(BASE_URL . '/admin-login.php?redirect=' . urlencode($redirectUrl));
    }
}

/**
 * Check Session Timeout
 * 
 * Checks if the user session has expired and logs out if necessary.
 * 
 * @return bool True if session is still valid
 */
function checkSessionTimeout() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $loginTime = $_SESSION['login_time'] ?? 0;
    $currentTime = time();
    $sessionLifetime = SESSION_LIFETIME;
    
    if (($currentTime - $loginTime) > $sessionLifetime) {
        userLogout();
        return false;
    }
    
    // Update login time to extend session
    $_SESSION['login_time'] = $currentTime;
    return true;
}

/**
 * Get User Permissions
 * 
 * Returns the permissions for the current user based on their type.
 * 
 * @return array Array of permissions
 */
function getUserPermissions() {
    $userType = getCurrentUserType();
    
    $permissions = [
        'can_register_vehicles' => false,
        'max_vehicles' => 0,
        'can_manage_drivers' => false,
        'can_view_reports' => false,
        'can_manage_users' => false,
        'can_manage_system' => false
    ];
    
    switch ($userType) {
        case 'student':
            $permissions['can_register_vehicles'] = true;
            $permissions['max_vehicles'] = MAX_VEHICLES_PER_STUDENT;
            $permissions['can_manage_drivers'] = true;
            break;
            
        case 'staff':
            $permissions['can_register_vehicles'] = true;
            $permissions['max_vehicles'] = MAX_VEHICLES_PER_STAFF;
            $permissions['can_manage_drivers'] = true;
            break;
            
        // guest removed
    }
    
    // Admin has all permissions
    if (isAdmin()) {
        $permissions = array_fill_keys(array_keys($permissions), true);
        $permissions['max_vehicles'] = -1; // Unlimited
    }
    
    return $permissions;
}

/**
 * Check User Permission
 * 
 * Checks if the current user has a specific permission.
 * 
 * @param string $permission Permission to check
 * @return bool True if user has permission
 */
function hasPermission($permission) {
    $permissions = getUserPermissions();
    return $permissions[$permission] ?? false;
}

/**
 * Get User Vehicle Count
 * 
 * Returns the number of vehicles registered by the current user.
 * 
 * @return int Number of vehicles
 */
function getUserVehicleCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    
    try {
        $conn = getLegacyDatabaseConnection();
        $userId = getCurrentUserId();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM vehicles WHERE applicant_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        return (int) $row['count'];
        
    } catch (Exception $e) {
        logError("Error getting user vehicle count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Can Register Vehicle
 * 
 * Checks if the current user can register a new vehicle.
 * 
 * @return bool True if user can register vehicle
 */
function canRegisterVehicle() {
    if (!hasPermission('can_register_vehicles')) {
        return false;
    }
    
    $permissions = getUserPermissions();
    $currentCount = getUserVehicleCount();
    $maxVehicles = $permissions['max_vehicles'];
    
    // -1 means unlimited
    if ($maxVehicles === -1) {
        return true;
    }
    
    return $currentCount < $maxVehicles;
} 