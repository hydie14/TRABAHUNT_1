<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

if (!isset($_SESSION['jobseeker_step2'])) {
    header("Location: jobseeker_step2.php");
    exit();
}

$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $step1 = $_SESSION['jobseeker_step1'];
    $step2 = $_SESSION['jobseeker_step2'];
    $step3 = $_POST;

    if ($step3['password'] !== $step3['confirmPassword']) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (strlen($step3['password']) < 8 || !preg_match('/[A-Z]/', $step3['password']) || !preg_match('/[a-z]/', $step3['password']) || !preg_match('/[0-9]/', $step3['password'])) {
        $_SESSION['error'] = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $stmt = $conn->prepare("SELECT contact_value FROM user_contacts WHERE contact_value = ? OR contact_value = ? LIMIT 1");
    $stmt->bind_param("ss", $step2['email'], $step2['mobile']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc()['contact_value'];
        if ($existing == $step2['email']) {
            $_SESSION['error'] = "An account with this email address already exists.";
        } else {
            $_SESSION['error'] = "An account with this mobile number already exists.";
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $stmt->close();

    $conn->begin_transaction();

    try {
        $password_hash = password_hash($step3['password'], PASSWORD_DEFAULT);
        $role = 'JobSeeker';
        
        $admin_email = "admin@peso.com";
        $admin_pass = "Peso@adminbongabon";
        if ($step2['email'] == $admin_email && $step3['password'] == $admin_pass) {
            $role = 'Admin';
        }

        $stmt = $conn->prepare("INSERT INTO users (password_hash, role) VALUES (?, ?)");
        $stmt->bind_param("ss", $password_hash, $role);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO user_contacts (user_id, contact_type, contact_value, is_primary) VALUES (?, 'Email', ?, 1)");
        $stmt->bind_param("is", $user_id, $step2['email']);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO user_contacts (user_id, contact_type, contact_value, is_primary) VALUES (?, 'Mobile', ?, 0)");
        $stmt->bind_param("is", $user_id, $step2['mobile']);
        $stmt->execute();
        $stmt->close();

        $barangay = $step2['barangay'];
        $city_municipality = 'Bongabon';
        $province = 'Nueva Ecija';
        
        $stmt = $conn->prepare("INSERT INTO locations (barangay, city_municipality, province) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $barangay, $city_municipality, $province);
        $stmt->execute();
        $location_id = $stmt->insert_id;
        $stmt->close();

        if ($role == 'JobSeeker' || $role == 'Admin') {
            $stmt = $conn->prepare("INSERT INTO jobseekers (seeker_id, first_name, middle_name, last_name, suffix, gender, civil_status, birthdate, place_of_birth, street_address, location_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $street_address = '';
            $stmt->bind_param("isssssssssi", $user_id, $step1['firstName'], $step1['middleName'], $step1['lastName'], $step1['suffix'], $step1['sex'], $step1['civilStatus'], $step1['dob'], $step1['placeOfBirth'], $street_address, $location_id);
            $stmt->execute();
            $stmt->close();
        }

        include_once __DIR__ . '/../OTP VERIFY LABORATORY/send_otp.php';
        $otp_error = '';
        if (send_otp($step2['email'], $user_id, $conn, $otp_error)) {
            $conn->commit();
            $_SESSION['otp_user_id'] = $user_id;
            $_SESSION['otp_user_role'] = $role;
            unset($_SESSION['jobseeker_step1']);
            unset($_SESSION['jobseeker_step2']);
            header("Location: ../OTP%20VERIFY%20LABORATORY/otp_verification.php");
            exit();
        } else {
            $conn->rollback();
            $_SESSION['error'] = "Failed to send verification email. Please try again.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Registration failed: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected error occurred during registration. Please try again.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seeker Registration - Step 3</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
            padding: 1rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 1rem;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 0.5rem;
            display: block;
        }

        .header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.875rem;
        }

        .progress-container {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e5e7eb;
            z-index: 0;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            height: 3px;
            background: #fbbf24;
            z-index: 1;
            transition: width 0.3s;
            width: 100%;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }

        .step.active .step-circle {
            background: #1e40af;
            color: white;
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2);
        }

        .step.completed .step-circle {
            background: #fbbf24;
            color: #1e40af;
        }

        .step-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            text-align: center;
        }

        .step.active .step-label {
            color: #1e40af;
            font-weight: 600;
        }

        .form-card {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-title {
            font-size: 1.25rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .form-subtitle {
            color: #6b7280;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .required {
            color: #ef4444;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-size: 1.25rem;
        }

        .password-strength {
            margin-top: 0.75rem;
        }

        .strength-bars {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .strength-bar {
            flex: 1;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .strength-bar.weak {
            background: #ef4444;
        }

        .strength-bar.medium {
            background: #f59e0b;
        }

        .strength-bar.strong {
            background: #10b981;
        }

        .strength-text {
            font-size: 0.75rem;
            font-weight: 500;
        }

        .strength-text.weak {
            color: #ef4444;
        }

        .strength-text.medium {
            color: #f59e0b;
        }

        .strength-text.strong {
            color: #10b981;
        }

        .helper-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.375rem;
        }

        .error-text {
            font-size: 0.75rem;
            color: #ef4444;
            margin-top: 0.375rem;
            display: none;
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
            border: none;
        }

        .btn-back {
            background: #f3f4f6;
            color: #6b7280;
        }

        .btn-back:hover {
            background: #e5e7eb;
        }

        .btn-submit {
            background: #1e40af;
            color: white;
            flex: 1;
        }

        .btn-submit:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }

        .btn-submit:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 768px) {
            .form-card {
                padding: 1rem;
            }

            .step-label {
                font-size: 0.6rem;
            }

            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../BONGABON.png" alt="Bongabon Logo" class="logo">
            <h1>Job Seeker Registration</h1>
            <p>Create your account to find your dream job</p>
        </div>

        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line"></div>
                <div class="step completed">
                    <div class="step-circle">✓</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step completed">
                    <div class="step-circle">✓</div>
                    <div class="step-label">Contact Details</div>
                </div>
                <div class="step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Login Credentials</div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <h2 class="form-title">Login Credentials</h2>
            <p class="form-subtitle">Secure your account</p>

            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form id="step3Form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                <?php echo csrf_field(); ?>
<div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bars">
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    <div class="helper-text">At least 8 characters with uppercase, lowercase, and number.</div>
                     <div id="passwordComplexityError" class="error-text"></div>
                </div>

                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</button>
                    </div>
                    <div id="passwordMatchError" class="error-text">Passwords do not match</div>
                </div>

                <div class="btn-container">
                    <button type="button" class="btn btn-back" onclick="window.location.href='jobseeker_step2.php'">← Back</button>
                    <button type="submit" class="btn btn-submit" id="submitBtn">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const submitBtn = document.getElementById('submitBtn');
        const passwordMatchError = document.getElementById('passwordMatchError');
        const passwordComplexityError = document.getElementById('passwordComplexityError');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthText = document.getElementById('strengthText');
        const strengthBars = document.querySelectorAll('.strength-bar');
        
        // Password strength indicator
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            if (password.length === 0) {
                passwordStrength.style.display = 'none';
                validateForm();
                return;
            }
            passwordStrength.style.display = 'block';
            const strength = calculatePasswordStrength(password);
            updateStrengthIndicator(strength);
            validateForm();
        });

        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            if (strength <= 2) return 'weak';
            if (strength <= 3) return 'medium';
            return 'strong';
        }

        function updateStrengthIndicator(strength) {
            strengthBars.forEach(bar => bar.className = 'strength-bar');
            strengthText.className = 'strength-text ' + strength;
            if (strength === 'weak') {
                strengthBars[0].classList.add('weak');
                strengthText.textContent = 'Weak password';
            } else if (strength === 'medium') {
                strengthBars[0].classList.add('medium');
                strengthBars[1].classList.add('medium');
                strengthBars[2].classList.add('medium');
                strengthText.textContent = 'Medium strength';
            } else {
                strengthBars.forEach(bar => bar.classList.add('strong'));
                strengthText.textContent = 'Strong password';
            }
        }
        
        function validateForm() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let isPasswordComplex = false;
            let doPasswordsMatch = false;

            const complexityRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
            if (complexityRegex.test(password)) {
                passwordComplexityError.style.display = 'none';
                isPasswordComplex = true;
            } else if (password.length > 0) {
                passwordComplexityError.textContent = 'Password must be 8+ characters with uppercase, lowercase, and a number.';
                passwordComplexityError.style.display = 'block';
                isPasswordComplex = false;
            }

            if (confirmPassword && password === confirmPassword) {
                passwordMatchError.style.display = 'none';
                doPasswordsMatch = true;
            } else if (confirmPassword) {
                passwordMatchError.style.display = 'block';
                doPasswordsMatch = false;
            }

            submitBtn.disabled = !(isPasswordComplex && doPasswordsMatch);
        }

        confirmPasswordInput.addEventListener('input', validateForm);

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
