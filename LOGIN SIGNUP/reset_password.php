<?php
session_start();
require '../DATABASE/csrf.php';
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $otp = trim($_POST['otp']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($otp) || empty($new_password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error = "Password must be at least 8 characters with uppercase, lowercase, and number.";
        } else {
            $user_id = $_SESSION['reset_user_id'];
            
            // Debug: Check if OTP exists
            $debug_stmt = $conn->prepare("SELECT otp_id, otp, expires_at, is_used FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $debug_stmt->bind_param("i", $user_id);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            
            if ($debug_result->num_rows == 0) {
                $error = "No OTP found. Please request a new verification code.";
            } else {
                $debug_row = $debug_result->fetch_assoc();
                $db_otp = $debug_row['otp'];
                $expires = $debug_row['expires_at'];
                $is_used = $debug_row['is_used'];
                
                if ($is_used == 1) {
                    $error = "This verification code has already been used. Please request a new one.";
                } elseif (strtotime($expires) < time()) {
                    $error = "Verification code has expired. Please request a new one.";
                } elseif ($db_otp !== $otp) {
                    $error = "Invalid verification code. Please check and try again.";
                } else {
                    // OTP is valid, proceed with password reset
                    $otp_id = $debug_row['otp_id'];
                    
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $update_stmt->bind_param("si", $password_hash, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    $mark_used = $conn->prepare("UPDATE otps SET is_used = 1 WHERE otp_id = ?");
                    $mark_used->bind_param("i", $otp_id);
                    $mark_used->execute();
                    $mark_used->close();
                    
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_email']);
                    
                    $_SESSION['password_reset_success'] = true;
                    header("Location: new_login.php");
                    exit();
                }
            }
            $debug_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PESO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
    <style>
        .helper-text { font-size: 0.75rem; color: #6b7280; margin-top: 0.375rem; }
        .login-right { display: flex; flex-direction: column; justify-content: center; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <img src="../BONGABON.png" style="width:80px;">
            </div>
            <h1>Reset Your Password</h1>
            <p>Enter the verification code sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong> and create a new password.</p>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Create New Password</h2>
                <p>Enter verification code and new password</p>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                <?php echo csrf_field(); ?>
                <?php if($error): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="otp">Verification Code</label>
                    <input type="text" id="otp" name="otp" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password')">👁️</button>
                    </div>
                    <div class="helper-text">At least 8 characters with uppercase, lowercase, and number</div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Reset Password</button>
                
                <div style="text-align: center; margin-top: 0.75rem;">
                    <a href="forgot_password.php" style="color: #1e40af; text-decoration: none; font-size: 0.875rem;">Resend Code</a> |
                    <a href="new_login.php" style="color: #6b7280; text-decoration: none; font-size: 0.875rem; margin-left: 0.5rem;">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
