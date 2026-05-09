<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

if (!isset($_SESSION['otp_user_id'])) {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['otp_user_id'];
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $otp_input = trim($_POST['otp']);
        
        if (!preg_match('/^[0-9]{6}$/', $otp_input)) {
            $error = "Invalid OTP format.";
        } else {
            $current_time = date("Y-m-d H:i:s");
            
            $stmt = $conn->prepare("SELECT otp_id FROM otps WHERE user_id = ? AND otp = ? AND is_used = 0 AND expires_at > ? ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("iss", $user_id, $otp_input, $current_time);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                $update_otp = $conn->prepare("UPDATE otps SET is_used = 1 WHERE otp_id = ?");
                $update_otp->bind_param("i", $row['otp_id']);
                $update_otp->execute();
                
                $update_user = $conn->prepare("UPDATE users SET is_email_verified = 1 WHERE user_id = ?");
                $update_user->bind_param("i", $user_id);
                $update_user->execute();
                
                $role = $_SESSION['otp_user_role'] ?? 'JobSeeker';
                unset($_SESSION['otp_user_id']);
                unset($_SESSION['otp_user_role']);
                
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;

                echo "<script>alert('Verification Successful!'); window.location.href='../LOGIN SIGNUP/new_login.php';</script>";
                exit();
            } else {
                $error = "Invalid or expired OTP. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .otp-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .otp-card h2 { color: #1f2937; margin-bottom: 0.5rem; }
        .otp-card p { color: #6b7280; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .otp-input { width: 100%; padding: 0.75rem; font-size: 1.25rem; letter-spacing: 0.5rem; text-align: center; border: 2px solid #e5e7eb; border-radius: 8px; margin-bottom: 1rem; text-indent: 0.5rem; }
        .otp-input:focus { border-color: #1e40af; outline: none; }
        .btn-verify { width: 100%; padding: 0.75rem; background: #1e40af; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-verify:hover { background: #1e3a8a; }
        .error-msg { color: #ef4444; font-size: 0.875rem; margin-bottom: 1rem; }
        .resend-link { margin-top: 1rem; display: block; color: #1e40af; text-decoration: none; font-size: 0.875rem; }
        .resend-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="otp-card">
        <h2>Verification Code</h2>
        <p>We have sent a verification code to your email address.</p>
        
        <?php if($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="text" name="otp" class="otp-input" placeholder="123456" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
            <button type="submit" class="btn-verify">Verify Account</button>
        </form>
        
        <a href="#" class="resend-link" id="resendLink">Resend Code</a>
        <p id="resendMessage" style="margin-top: 10px; font-size: 0.875rem; min-height: 1.25rem;"></p>
    </div>

    <script>
        document.getElementById('resendLink').addEventListener('click', function(e) {
            e.preventDefault();
            const link = this;
            const msg = document.getElementById('resendMessage');
            
            if (link.style.pointerEvents === 'none') return;

            link.style.pointerEvents = 'none';
            link.style.opacity = '0.5';
            msg.textContent = 'Sending...';
            msg.style.color = '#6b7280';

            fetch('resend_otp.php')
                .then(response => response.json())
                .then(data => {
                    msg.textContent = data.message;
                    msg.style.color = data.success ? '#10b981' : '#ef4444';
                    
                    if (!data.success) {
                        link.style.pointerEvents = 'auto';
                        link.style.opacity = '1';
                    } else {
                        // Keep disabled for a while to prevent spam
                        setTimeout(() => {
                            link.style.pointerEvents = 'auto';
                            link.style.opacity = '1';
                            link.textContent = 'Resend Code';
                        }, 30000); // 30 seconds cooldown
                        
                        let countdown = 30;
                        const timer = setInterval(() => {
                            link.textContent = `Resend in ${countdown}s`;
                            countdown--;
                            if (countdown < 0) clearInterval(timer);
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    msg.textContent = 'An error occurred. Please try again.';
                    msg.style.color = '#ef4444';
                    link.style.pointerEvents = 'auto';
                    link.style.opacity = '1';
                });
        });
    </script>
</body>
</html>