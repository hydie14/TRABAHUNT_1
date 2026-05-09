<?php
session_start();
require '../DATABASE/csrf.php';

$lockout_time = 300; // 5 minutes
$remaining_lockout = 0;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SESSION['login_attempts'] > 5) {
    if (isset($_SESSION['last_login_attempt']) && (time() - $_SESSION['last_login_attempt']) < $lockout_time) {
        $remaining_lockout = $lockout_time - (time() - $_SESSION['last_login_attempt']);
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

include '../DATABASE/db_connect.php';

// HANDLE ACCOUNT RESTORATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_restore'])) {
    if (isset($_SESSION['temp_restore_id'])) {
        $restore_id = $_SESSION['temp_restore_id'];
        $restore_role = $_SESSION['temp_restore_role'];
        
        // Restore account in DB
        $restore_stmt = $conn->prepare("UPDATE users SET is_archived = 0, archived_at = NULL WHERE user_id = ?");
        $restore_stmt->bind_param("i", $restore_id);
        $restore_stmt->execute();
        $restore_stmt->close();
        
        // Clear temp session and log them in
        unset($_SESSION['temp_restore_id']);
        unset($_SESSION['temp_restore_role']);
        
        $_SESSION['user_id'] = $restore_id;
        $_SESSION['role'] = $restore_role;
        
        if ($restore_role === 'JobSeeker') {
            header("Location: ../DASHBOARD jobseeker/jobseeker_dashboard.php");
        } elseif ($restore_role === 'Employer') {
            header("Location: ../DASHBOARD employer/employer_dashboard.php");
        } elseif ($restore_role === 'ServiceProvider') {
            header("Location: ../DASHBOARD service provider/dashboard.php");
        } else {
            header("Location: ../DASHBOARD admin/admin_dashboard.php");
        }
        exit();
    }
}

$error = '';
$success = '';

if ($remaining_lockout > 0) {
    $error = "Too many failed login attempts. Please wait before trying again.";
}

if (isset($_SESSION['password_reset_success'])) {
    $success = "Password reset successful! You can now login with your new password.";
    unset($_SESSION['password_reset_success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($remaining_lockout > 0) {
        // Block login processing while locked out
    } elseif (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = "Email and password are required.";
        } else {
            $stmt = $conn->prepare("SELECT u.user_id, u.password_hash, u.role, u.is_email_verified, u.is_archived FROM users u JOIN user_contacts uc ON u.user_id = uc.user_id WHERE uc.contact_value = ? AND (uc.contact_type = 'Email' OR uc.contact_type = 'Mobile') LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password_hash'])) {
                    $_SESSION['login_attempts'] = 0;
                    
                    // CHECK IF ACCOUNT IS ARCHIVED (SOFT-DELETED)
                    if ($row['is_archived'] == 1) {
                        $_SESSION['temp_restore_id'] = $row['user_id'];
                        $_SESSION['temp_restore_role'] = $row['role'];
                        header("Location: new_login.php?action=restore_prompt");
                        exit();
                    }

                    if ($row['is_email_verified'] == 1) {
                        
                        // --- START: Admin Verification Check for Employer and Service Provider ---
                        if ($row['role'] === 'Employer') {
                            $check_emp = $conn->prepare("SELECT admin_verification_status FROM employers WHERE employer_id = ?");
                            $check_emp->bind_param("i", $row['user_id']);
                            $check_emp->execute();
                            $res_emp = $check_emp->get_result();
                            if ($emp_data = $res_emp->fetch_assoc()) {
                                if ($emp_data['admin_verification_status'] === 'Pending') {
                                    session_unset(); session_destroy();
                                    echo "<script>alert('Your account is pending for admin verification. You can log in once approved.'); window.location.href='new_login.php';</script>";
                                    exit();
                                } elseif ($emp_data['admin_verification_status'] === 'Rejected') {
                                    session_unset(); session_destroy();
                                    echo "<script>alert('Your account verification was rejected. Please contact PESO for assistance.'); window.location.href='new_login.php';</script>";
                                    exit();
                                }
                            }
                            $check_emp->close();
                        } elseif ($row['role'] === 'ServiceProvider') {
                            $check_sp = $conn->prepare("SELECT admin_verification_status FROM service_providers WHERE provider_id = ?");
                            $check_sp->bind_param("i", $row['user_id']);
                            $check_sp->execute();
                            $res_sp = $check_sp->get_result();
                            if ($sp_data = $res_sp->fetch_assoc()) {
                                if ($sp_data['admin_verification_status'] === 'Pending') {
                                    session_unset(); session_destroy();
                                    echo "<script>alert('Your account is pending for admin verification. You can log in once approved.'); window.location.href='new_login.php';</script>";
                                    exit();
                                } elseif ($sp_data['admin_verification_status'] === 'Rejected') {
                                    session_unset(); session_destroy();
                                    echo "<script>alert('Your account verification was rejected. Please contact PESO for assistance.'); window.location.href='new_login.php';</script>";
                                    exit();
                                }
                            }
                            $check_sp->close();
                        }
                        // --- END: Admin Verification Check ---

                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['role'] = $row['role'];

                        switch ($row['role']) {
                            case 'Admin':
                                header("Location: ../DASHBOARD admin/admin_dashboard.php");
                                break;
                            case 'Employer':
                                header("Location: ../DASHBOARD employer/employer_dashboard.php");
                                break;
                            case 'JobSeeker':
                                header("Location: ../DASHBOARD jobseeker/jobseeker_dashboard.php");
                                break;
                            case 'ServiceProvider':
                                header("Location: ../DASHBOARD service provider/dashboard.php");
                                break;
                        }
                        exit();
                    } else {
                        $_SESSION['otp_user_id'] = $row['user_id'];
                        $_SESSION['otp_user_role'] = $row['role'];
                        header("Location: ../OTP%20VERIFY%20LABORATORY/otp_verification.php");
                        exit();
                    }
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_login_attempt'] = time();
                    $error = "Invalid password.";
                }
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_login_attempt'] = time();
                $error = "No user found with that email or contact number.";
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
    <title>PESO - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                  <img src="../BONGABON.png" style="width:80px;">
            </div>
            <h1>Welcome!</h1>
            <p>Login to access your account and continue your job search journey. Connect with opportunities and manage your applications.</p>
            <div class="signup-prompt">
                <p>Don't have an account yet?</p>
                <a href="new_signup.php" class="btn btn-outline">Create Account</a>
            </div>
        </div>

        <div class="login-right">
            <div class="login-header">
                <h2>Login</h2>
                <p>Enter your credentials to continue</p>
            </div>

            <?php if (isset($_GET['action']) && $_GET['action'] == 'restore_prompt'): ?>
                <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                    <h3 style="color: #b45309; margin-top: 0; font-size: 1.25rem;">Account Scheduled for Deletion</h3>
                    <p style="color: #92400e; font-size: 0.95rem; margin-bottom: 1.5rem;">
                        Your account is currently deactivated and is scheduled for permanent deletion. 
                        Would you like to cancel the deletion and restore your account?
                    </p>
                    <form method="POST" action="new_login.php" style="display: flex; gap: 0.75rem; justify-content: center; flex-direction: column;">
                        <button type="submit" name="confirm_restore" style="background: #059669; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%;">
                            Yes, Restore Account
                        </button>
                        <a href="new_login.php" style="background: #e5e7eb; color: #374151; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 6px; font-weight: 600; display: block; width: 100%; box-sizing: border-box;">
                            No, Cancel
                        </a>
                    </form>
                </div>
            <?php else: ?>
            <form method="post" action="new_login.php" name="loginForm" id="loginForm">
                <?php echo csrf_field(); ?>
                <?php if($error): ?>
                    <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;">
                        <?php echo htmlspecialchars($error); ?>
                        <?php if($remaining_lockout > 0): ?>
                            <br><span id="countdown" style="display: inline-block; margin-top: 0.25rem;">Time remaining: <strong>...</strong></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if($success): ?>
                    <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="email">Email Address or Contact Number</label>
                    <input type="text" id="email" name="email" placeholder="your@email.com or 09XXXXXXXXX" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                    </div>
                </div>
                
               

                <button type="submit" class="btn-primary">Login</button>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="forgot_password.php" style="color: #1e40af; text-decoration: none; font-size: 0.875rem;">Forgot Password?</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        }

        <?php if($remaining_lockout > 0): ?>
        // Disable form inputs during lockout
        document.getElementById('email').disabled = true;
        document.getElementById('password').disabled = true;
        document.getElementById('captcha_answer').disabled = true;
        const btn = document.querySelector('.btn-primary');
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';

        let timeLeft = <?php echo $remaining_lockout; ?>;
        const countdownEl = document.getElementById('countdown');
        
        function updateDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownEl.innerHTML = `Time remaining: <strong>${minutes}m ${seconds}s</strong>`;
        }
        
        updateDisplay();
        const timer = setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(timer);
                countdownEl.innerHTML = "You can now try to login again. Refreshing...";
                setTimeout(() => window.location.reload(), 1000);
            } else {
                updateDisplay();
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>