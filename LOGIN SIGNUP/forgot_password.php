<?php
session_start();
require '../DATABASE/csrf.php';
include '../DATABASE/db_connect.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT u.user_id FROM users u JOIN user_contacts uc ON u.user_id = uc.user_id WHERE uc.contact_value = ? AND uc.contact_type = 'Email' LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $user_id = $row['user_id'];
                
                include_once '../OTP VERIFY LABORATORY/send_otp.php';
                $otp_error = '';
                if (send_otp($email, $user_id, $conn, $otp_error)) {
                    $_SESSION['reset_user_id'] = $user_id;
                    $_SESSION['reset_email'] = $email;
                    header("Location: reset_password.php");
                    exit();
                } else {
                    $error = "Failed to send reset code. Please try again.";
                }
            } else {
                $error = "No account found with that email address.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PESO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <img src="../BONGABON.png" style="width:80px;">
            </div>
            <h1>Forgot Password?</h1>
            <p>Don't worry! Enter your email address and we'll send you a verification code to reset your password.</p>
            <div class="signup-prompt">
                <p>Remember your password?</p>
                <a href="new_login.php" class="btn btn-outline">Back to Login</a>
            </div>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Reset Password</h2>
                <p>Enter your email to receive a verification code</p>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                <?php echo csrf_field(); ?>
                <?php if($error): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <?php if($success): ?>
                    <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <button type="submit" class="btn-primary">Send Reset Code</button>
            </form>
        </div>
    </div>
</body>
</html>
