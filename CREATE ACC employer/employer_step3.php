<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

if (!isset($_SESSION['employer_step1'])) {
    header("Location: employer_step1.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];

        if ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = "Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.";
        } else {
            $_SESSION['employer_step3'] = $_POST;
            header("Location: employer_step4.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Registration - Step 3</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); min-height: 100vh; padding: 1rem; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { text-align: center; color: white; margin-bottom: 1rem; }
        .logo { width: 80px; height: 80px; margin: 0 auto 0.5rem; display: block; }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.25rem; }
        .header p { opacity: 0.9; font-size: 0.875rem; }
        .progress-container { background: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .progress-steps { display: flex; justify-content: space-between; position: relative; }
        .progress-steps::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #e5e7eb; z-index: 0; }
        .progress-line { position: absolute; top: 20px; left: 0; height: 3px; background: #fbbf24; z-index: 1; transition: width 0.3s; width: 50%; }
        .step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; flex: 1; max-width: 33%; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-bottom: 0.5rem; transition: all 0.3s; }
        .step.active .step-circle { background: #1e40af; color: white; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2); }
        .step.completed .step-circle { background: #fbbf24; color: #1e40af; }
        .step-label { font-size: 0.7rem; color: #6b7280; font-weight: 500; text-align: center; }
        .step.active .step-label { color: #1e40af; font-weight: 600; }
        .form-card { background: white; padding: 1.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.25rem; color: #1f2937; margin-bottom: 0.25rem; }
        .form-subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; font-size: 0.875rem; }
        .required { color: #ef4444; }
        .form-group input { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; }
        .form-group input:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 0.875rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280; font-size: 1.25rem; }
        .password-strength { margin-top: 0.75rem; }
        .strength-bars { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
        .strength-bar { flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px; transition: all 0.3s; }
        .strength-bar.weak { background: #ef4444; }
        .strength-bar.medium { background: #f59e0b; }
        .strength-bar.strong { background: #10b981; }
        .strength-text { font-size: 0.75rem; font-weight: 500; }
        .strength-text.weak { color: #ef4444; }
        .strength-text.medium { color: #f59e0b; }
        .strength-text.strong { color: #10b981; }
        .helper-text { font-size: 0.75rem; color: #6b7280; margin-top: 0.375rem; }
        .error-text { font-size: 0.75rem; color: #ef4444; margin-top: 0.375rem; }
        .btn-container { display: flex; justify-content: space-between; gap: 1rem; margin-top: 2rem; }
        .btn { padding: 0.875rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 1rem; border: none; }
        .btn-back { background: #f3f4f6; color: #6b7280; }
        .btn-back:hover { background: #e5e7eb; }
        .btn-next { background: #1e40af; color: white; flex: 1; }
        .btn-next:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        .btn-next:disabled { background: #d1d5db; cursor: not-allowed; transform: none; }
        @media (max-width: 768px) { .form-card { padding: 1rem; } .step-label { font-size: 0.6rem; } .container { max-width: 100%; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../BONGABON.png" alt="Bongabon Logo" class="logo">
            <h1>Employer Registration</h1>
            <p>PESO Bongabon, Nueva Ecija - Register your company</p>
        </div>
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line" style="width: 66.66%;"></div>
                <div class="step completed">
                    <div class="step-circle">✓</div>
                    <div class="step-label">Establishment Details</div>
                </div>
                <div class="step active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Credentials</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Documents</div>
                </div>
            </div>
        </div>
        <div class="form-card">
            <h2 class="form-title">Login Credentials</h2>
            <p class="form-subtitle">Secure your employer account</p>
            <form id="step3Form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                <?php echo csrf_field(); ?>
                <?php if($error): ?>
                    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                    </div>
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-bars">
                            <div class="strength-bar" id="bar1"></div>
                            <div class="strength-bar" id="bar2"></div>
                            <div class="strength-bar" id="bar3"></div>
                            <div class="strength-bar" id="bar4"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    <div class="helper-text">At least 8 characters with uppercase, lowercase, and number</div>
                </div>
                <div class="form-group">
                    <label>Confirm Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</button>
                    </div>
                    <div class="error-text" id="passwordError" style="display: none;">Passwords do not match</div>
                </div>
                <div class="btn-container">
                    <button type="button" class="btn btn-back" onclick="window.location.href='employer_step1.php'">← Back</button>
                    <button type="submit" class="btn btn-next" id="submitBtn">Next Step →</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordError = document.getElementById('passwordError');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            if (password.length === 0) {
                passwordStrength.style.display = 'none';
                return;
            }
            passwordStrength.style.display = 'block';
            const strength = calculatePasswordStrength(password);
            updateStrengthIndicator(strength);
        });

        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        passwordInput.addEventListener('input', function() {
            if (confirmPasswordInput.value) validatePasswordMatch();
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
            const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
            bars.forEach(bar => bar.className = 'strength-bar');
            strengthText.className = 'strength-text ' + strength;
            if (strength === 'weak') {
                bars[0].classList.add('weak');
                strengthText.textContent = 'Weak password';
            } else if (strength === 'medium') {
                bars[0].classList.add('medium');
                bars[1].classList.add('medium');
                bars[2].classList.add('medium');
                strengthText.textContent = 'Medium strength';
            } else {
                bars.forEach(bar => bar.classList.add('strong'));
                strengthText.textContent = 'Strong password';
            }
        }

        function validatePasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const complexityRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
            const isComplex = complexityRegex.test(password);
            const doMatch = confirmPassword && password === confirmPassword;
            
            if (confirmPassword && password !== confirmPassword) {
                passwordError.style.display = 'block';
                confirmPasswordInput.style.borderColor = '#ef4444';
            } else {
                passwordError.style.display = 'none';
                confirmPasswordInput.style.borderColor = '#e5e7eb';
            }
            
            submitBtn.disabled = !(isComplex && doMatch);
        }
    </script>
</body>
</html>
