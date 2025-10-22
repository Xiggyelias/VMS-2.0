<?php
// send-reset.php
require_once __DIR__ . '/vendor/autoload.php'; // For PHPMailer

// Configurable settings
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'vehicleregistrationsystem';

// Email Configuration - Updated with actual Gmail credentials
$from_email = 'ebubechiezugwu@gmail.com';
$from_name = 'Vehicle Registration System';
$smtp_username = 'ebubechiezugwu@gmail.com';
$smtp_password = 'pdlg clxh oyjx zxkq';
$reset_url_base = 'http://localhost/system/frontend/reset-password.php?token='; // Change to your domain

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        header('Location: forgot_password.php?error=1');
        exit;
    }

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        header('Location: forgot_password.php?error=1');
        exit;
    }

    // Do not reveal if email exists
    $stmt = $conn->prepare('SELECT applicant_id FROM applicants WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($applicant_id);
        $stmt->fetch();
        
        // Generate token and expiry
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // First delete any existing tokens for this user
        $deleteStmt = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $deleteStmt->bind_param('i', $applicant_id);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Then insert the new token
        $stmt2 = $conn->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt2->bind_param('iss', $applicant_id, $token, $expires);
        $stmt2->execute();
        $stmt2->close();

        // Send email using Gmail SMTP
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Server settings for Gmail
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Enable debug output (set to 0 for production)
            $mail->SMTPDebug = 0;
            
            // Recipients
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - Vehicle Registration System';
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <h2 style="color: #d00000;">Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for the Vehicle Registration System.</p>
                    <p>Click the button below to reset your password:</p>
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . $reset_url_base . urlencode($token) . '" 
                           style="background-color: #d00000; color: white; padding: 12px 24px; 
                                  text-decoration: none; border-radius: 5px; display: inline-block;">
                            Reset Password
                        </a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style="word-break: break-all; color: #666;">
                        ' . $reset_url_base . urlencode($token) . '
                    </p>
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    <p>If you did not request this password reset, please ignore this email.</p>
                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
                    <p style="color: #666; font-size: 12px;">
                        This is an automated message from the Vehicle Registration System.
                    </p>
                </div>
            ';
            $mail->AltBody = '
                Password Reset Request
                
                Click the following link to reset your password:
                ' . $reset_url_base . urlencode($token) . '
                
                This link will expire in 1 hour.
                
                If you did not request this password reset, please ignore this email.
            ';
            
            $mail->send();
        } catch (Exception $e) {
            // Log error for debugging
            error_log("Password reset email error: " . $e->getMessage());
            // Don't reveal the error to the user for security
        }
    }
    $stmt->close();
    $conn->close();
    // Always redirect to the same page
    header('Location: forgot_password.php?sent=1');
    exit;
} else {
    header('Location: forgot_password.php?error=1');
    exit;
} 
    header('Location: forgot_password.php?sent=1');
    exit;
} else {
    header('Location: forgot_password.php?error=1');
    exit;
} 