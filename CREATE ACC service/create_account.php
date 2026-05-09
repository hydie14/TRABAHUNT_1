<?php 
session_start();
include '../DATABASE/db_connect.php'; 
// Include CSRF helper if available, otherwise fallback
if(file_exists('../DATABASE/csrf.php')) {
    include '../DATABASE/csrf.php';
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    function generate_csrf_token() { return $_SESSION['csrf_token']; }
}

$max_date = date('Y-m-d', strtotime('-18 years'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider Registration - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); min-height: 100vh; padding: 2rem 1rem; color: #1a1a1a; display: flex; justify-content: center; align-items: flex-start; }
        .container { max-width: 900px; width: 100%; margin: 0 auto; }
        .header { text-align: center; color: white; margin-bottom: 1.5rem; }
        .logo { width: 80px; height: 80px; margin: 0 auto 0.5rem; display: block; }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.25rem; font-weight: 700; }
        .header p { opacity: 0.9; font-size: 0.875rem; }
        .progress-container { background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .progress-steps { display: flex; justify-content: space-between; position: relative; max-width: 100%; margin: 0 auto; }
        .progress-steps::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #e5e7eb; z-index: 0; }
        .progress-line { position: absolute; top: 20px; left: 0; height: 3px; background: #fbbf24; z-index: 1; transition: width 0.3s; width: 0%; }
        .step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; flex: 1; text-align: center; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-bottom: 0.5rem; transition: all 0.3s; border: 3px solid white; }
        .step.active .step-circle { background: #1e40af; color: white; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2); }
        .step.completed .step-circle { background: #fbbf24; color: #1e40af; }
        .step-label { font-size: 0.75rem; color: #6b7280; font-weight: 500; text-align: center; }
        .step.active .step-label { color: #1e40af; font-weight: 600; }
        .form-card { background: white; padding: 2.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        /* Form Steps */
        .form-step { display: none; animation: fadeIn 0.4s; }
        .form-step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .form-row { display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem; }
        .form-group { flex: 1; min-width: 220px; display: flex; flex-direction: column; }
        .form-group.full-width { min-width: 100%; }
        .form-group label { font-weight: 500; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem; }
        .form-group input, .form-group select { padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; outline: none; transition: all 0.2s; background: #ffffff; color: #1f2937; width: 100%; }
        .form-group input:focus, .form-group select:focus { border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .form-group small { color: #6b7280; font-size: 0.75rem; margin-top: 0.35rem; }
        
        .password-wrapper { position: relative; width: 100%; }
        .toggle-password { position: absolute; right: 0.875rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280; font-size: 1.25rem; }
        .password-strength { margin-top: 0.75rem; width: 100%; }
        .strength-bars { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
        .strength-bar { flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px; transition: all 0.3s; }
        .strength-bar.weak { background: #ef4444; }
        .strength-bar.medium { background: #f59e0b; }
        .strength-bar.strong { background: #10b981; }
        .strength-text { font-size: 0.75rem; font-weight: 500; }
        .strength-text.weak { color: #ef4444; }
        .strength-text.medium { color: #f59e0b; }
        .strength-text.strong { color: #10b981; }
        
        .section-title { font-size: 1.25rem; color: #1f2937; margin-bottom: 1.5rem; font-weight: 600; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb; }
        
        /* Buttons */
        .btn-container { display: flex; justify-content: space-between; margin-top: 2rem; gap: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-next { background: #1e40af; color: white; flex: 1; }
        .btn-next:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        .btn-back { background: #f3f4f6; color: #6b7280; }
        .btn-back:hover { background: #e5e7eb; color: #1f2937; }
        
        .login-link { text-align: center; margin-top: 2rem; font-size: 0.9rem; color: #e5e7eb; }
        .login-link a { color: #fbbf24; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }

        .file-upload-wrapper { border: 2px dashed #d1d5db; padding: 1.5rem; text-align: center; border-radius: 8px; background: #f9fafb; transition: all 0.2s; cursor: pointer; }
        .file-upload-wrapper:hover { border-color: #1e40af; background: #dbeafe; }
        .file-upload-wrapper input[type="file"] { display: none; }
        .file-upload-label { cursor: pointer; color: #1e40af; font-weight: 600; display: block; }
        .file-name { display: block; margin-top: 0.5rem; font-size: 0.85rem; color: #4b5563; }

        @media (max-width: 640px) {
            .form-row { flex-direction: column; gap: 1rem; }
            .btn-container { flex-direction: column-reverse; }
            .btn { width: 100%; }
            .step-label { font-size: 0.65rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="../BONGABON.png" alt="PESO Logo" class="logo">
        <h1>Service Provider Registration</h1>
        <p>Join PESO Bongabon and offer your services to the local community.</p>
    </div>

    <!-- Progress Bar -->
    <div class="progress-container">
        <div class="progress-steps">
            <div class="progress-line" id="progress-line"></div>
            <div class="step active" id="step-wrapper-1">
                <div class="step-circle" id="circle-1">1</div>
                <div class="step-label">Personal Info</div>
            </div>
            <div class="step" id="step-wrapper-2">
                <div class="step-circle" id="circle-2">2</div>
                <div class="step-label">Account details</div>
            </div>
            <div class="step" id="step-wrapper-3">
                <div class="step-circle" id="circle-3">3</div>
                <div class="step-label">Documents</div>
            </div>
        </div>
    </div>

    <div class="form-card">
    <form action="process_service_provider.php" method="POST" enctype="multipart/form-data" id="registrationForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <!-- STEP 1: Personal Info & Address -->
        <div class="form-step active" id="step-1">
            <h3 class="section-title">Personal Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span style="color:red">*</span></label>
                    <input type="text" id="first_name" name="first_name" required placeholder="e.g. Juan" pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
                </div>
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" placeholder="e.g. Dela">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span style="color:red">*</span></label>
                    <input type="text" id="last_name" name="last_name" required placeholder="e.g. Cruz" pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="birthdate">Birthdate <span style="color:red">*</span></label>
                    <input type="date" id="birthdate" name="birthdate" max="<?php echo $max_date; ?>" required>
                    <small id="birthdate_error" style="color: #ef4444; display: none;">You must be at least 18 years old to register.</small>
                </div>   
                <div class="form-group">
                    <label for="gender">Gender <span style="color:red">*</span></label>
                    <select id="gender" name="gender" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>

            <h3 class="section-title" style="margin-top: 1rem;">Complete Address</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="barangay">Barangay (Bongabon) <span style="color:red">*</span></label>
                    <select id="barangay" name="barangay" required>
                        <option value="" disabled selected>Select Barangay</option>
                        <option value="Antipolo">Antipolo</option>
                        <option value="Aripil">Aripil</option>
                        <option value="Bantug">Bantug</option>
                        <option value="Bitañgan">Bitañgan</option>
                        <option value="Calaanan">Calaanan</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Cruz">Cruz</option>
                        <option value="Curva">Curva</option>
                        <option value="Digmala">Digmala</option>
                        <option value="Kaingin">Kaingin</option>
                        <option value="Labi">Labi</option>
                        <option value="Larcon">Larcon</option>
                        <option value="Lusok">Lusok</option>
                        <option value="Macabaclay">Macabaclay</option>
                        <option value="Magtanggol">Magtanggol</option>
                        <option value="Mantile">Mantile</option>
                        <option value="Olivete">Olivete</option>
                        <option value="Palo Maria">Palo Maria</option>
                        <option value="Pesa">Pesa</option>
                        <option value="Rizal">Rizal</option>
                        <option value="San Roque">San Roque</option>
                        <option value="Santor">Santor</option>
                        <option value="Sinipit">Sinipit</option>
                        <option value="Sisilang">Sisilang</option>
                        <option value="Social">Social</option>
                        <option value="Vega">Vega</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label for="street_address">Street Address / House No. <span style="color:red">*</span></label>
                    <input type="text" id="street_address" name="street_address" required placeholder="e.g. 123 Example St.">
                </div>
            </div>

            <div class="btn-container">
                <button type="button" class="btn btn-back" onclick="window.location.href='../LOGIN SIGNUP/new_signup.php'">&larr; Back</button>
                <button type="button" class="btn btn-next" onclick="nextStep(2)">Next Step &rarr;</button>
            </div>
        </div>

        <!-- STEP 2: Contact & Account -->
        <div class="form-step" id="step-2">
            <h3 class="section-title">Contact Details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone_number">Active Phone Number <span style="color:red">*</span></label>
                    <input type="tel" id="phone_number" name="phone_number" required placeholder="09123456789" pattern="09[0-9]{9}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11)" title="Format: 09XXXXXXXXX. Must start with 09 and have 11 digits.">
                    <small id="phone_error" style="color: #ef4444; display: none;">Please enter a valid 11-digit number starting with 09.</small>
                </div>
                <div class="form-group">
                    <label for="email">Email Address <span style="color:red">*</span></label>                 
                    <input type="email" id="email" name="email" required placeholder="juan@example.com">
                    <small id="email_error" style="color: #ef4444; display: none;">Please enter a valid email address.</small>
                </div>
            </div>

            <h3 class="section-title" style="margin-top: 1rem;">Account Credentials</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span style="color:red">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Minimum 8 characters" minlength="8">
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
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color:red">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Retype password" minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                    </div>
                    <small id="password_error" style="color: #ef4444; display: none;">Passwords do not match.</small>
                </div>
            </div>

            <div class="btn-container">
                <button type="button" class="btn btn-back" onclick="prevStep(1)">&larr; Back</button>
                <button type="button" class="btn btn-next" onclick="nextStep(3)">Next Step &rarr;</button>
            </div>
        </div>

        <!-- STEP 3: Verification Documents -->
        <div class="form-step" id="step-3">
            <h3 class="section-title">Verification Documents</h3>
            <p style="font-size: 0.9rem; color: #4b5563; margin-bottom: 1.5rem;">PESO Bongabon strictly verifies all service providers. Please upload clear copies of the following to proceed.</p>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Valid Government ID <span style="color:red">*</span></label>
                    <div class="file-upload-wrapper" onclick="document.getElementById('valid_id').click()">
                        <label for="valid_id" class="file-upload-label">
                            <span style="font-size: 1.5rem; display:block; margin-bottom:0.25rem;">📄</span>
                            <br>Click to upload ID
                        </label>
                        <input type="file" id="valid_id" name="valid_id" accept="image/*,.pdf" required onchange="updateFileName(this, 'valid_id_name')">
                        <span class="file-name" id="valid_id_name">No file chosen</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Barangay Residency <span style="color:red">*</span></label>
                    <div class="file-upload-wrapper" onclick="document.getElementById('brgy_residency').click()">
                        <label for="brgy_residency" class="file-upload-label">
                            <span style="font-size: 1.5rem; display:block; margin-bottom:0.25rem;">📄</span>
                            <br>Click to upload Residency
                        </label>
                        <input type="file" id="brgy_residency" name="brgy_residency" accept="image/*,.pdf" required onchange="updateFileName(this, 'brgy_name')">
                        <span class="file-name" id="brgy_name">No file chosen</span>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>TESDA NC II / License <span style="color:#6b7280; font-weight:normal;">(Optional)</span></label>
                    <div class="file-upload-wrapper" onclick="document.getElementById('tesda_cert').click()">
                        <label for="tesda_cert" class="file-upload-label">
                            <span style="font-size: 1.5rem; display:block; margin-bottom:0.25rem;">📄</span>
                            <br>Click to upload Certificate
                        </label>
                        <input type="file" id="tesda_cert" name="tesda_cert" accept="image/*,.pdf" onchange="updateFileName(this, 'tesda_name')">
                        <span class="file-name" id="tesda_name">No file chosen</span>
                    </div>
                    <small>Boosts your verified trust rating</small>
                </div>
                <div class="form-group">
                    <label>Portfolio / Past Work <span style="color:#6b7280; font-weight:normal;">(Optional)</span></label>
                    <div class="file-upload-wrapper" onclick="document.getElementById('portfolio').click()">
                        <label for="portfolio" class="file-upload-label">
                            <span style="font-size: 1.5rem; display:block; margin-bottom:0.25rem;">📄</span>
                            <br>Click to upload Portfolio
                        </label>
                        <input type="file" id="portfolio" name="portfolio" accept="image/*,.pdf" onchange="updateFileName(this, 'portfolio_name')">
                        <span class="file-name" id="portfolio_name">No file chosen</span>
                    </div>
                    <small>Pictures of previous successful projects</small>
                </div>
            </div>

            <div class="btn-container">
                <button type="button" class="btn btn-back" onclick="prevStep(2)">&larr; Back</button>
                <button type="submit" class="btn btn-next">Complete Registration &rarr;</button>
            </div>
        </div>
    </form>
    </div>
</div>

<script>
    function updateFileName(input, textId) {
        const textElement = document.getElementById(textId);
        if (input.files && input.files.length > 0) {
            textElement.textContent = input.files[0].name;
            textElement.style.color = '#10b981';
            textElement.style.fontWeight = '600';
        } else {
            textElement.textContent = 'No file chosen';
            textElement.style.color = '#4b5563';
            textElement.style.fontWeight = 'normal';
        }
    }

    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        field.type = field.type === 'password' ? 'text' : 'password';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            if (password.length === 0) {
                passwordStrength.style.display = 'none';
                return;
            }
            passwordStrength.style.display = 'block';
            const strength = calculatePasswordStrength(password);
            updateStrengthIndicator(strength, strengthText);
        });
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

    function updateStrengthIndicator(strength, textElement) {
        const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
        bars.forEach(bar => bar.className = 'strength-bar');
        textElement.className = 'strength-text ' + strength;
        if (strength === 'weak') {
            bars[0].classList.add('weak');
            textElement.textContent = 'Weak password';
        } else if (strength === 'medium') {
            bars[0].classList.add('medium');
            bars[1].classList.add('medium');
            bars[2].classList.add('medium');
            textElement.textContent = 'Medium strength';
        } else {
            bars.forEach(bar => bar.classList.add('strong'));
            textElement.textContent = 'Strong password';
        }
    }

    function validateStep(step) {
        const stepElement = document.getElementById('step-' + step);
        const inputs = stepElement.querySelectorAll('input[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value) {
                isValid = false;
                input.style.borderColor = '#ef4444';
            } else {
                input.style.borderColor = '#d1d5db';
            }
        });

        if (step === 1) {
            const birthdateInput = document.getElementById('birthdate');
            const birthdateError = document.getElementById('birthdate_error');
            if (birthdateInput && birthdateInput.value) {
                const birthdate = new Date(birthdateInput.value);
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const m = today.getMonth() - birthdate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }
                if (age < 18) {
                    isValid = false;
                    birthdateInput.style.borderColor = '#ef4444';
                    if (birthdateError) birthdateError.style.display = 'block';
                } else if (birthdateError) {
                    birthdateError.style.display = 'none';
                }
            }
        }

        if (step === 2) {
            const phoneInput = document.getElementById('phone_number');
            const emailInput = document.getElementById('email');
            const phoneError = document.getElementById('phone_error');
            const emailError = document.getElementById('email_error');

            if (phoneInput && phoneInput.value && !validatePhoneNumber(phoneInput.value)) {
                isValid = false;
                phoneInput.style.borderColor = '#ef4444';
                if (phoneError) phoneError.style.display = 'block';
            } else if (phoneError) {
                phoneError.style.display = 'none';
            }

            if (emailInput && emailInput.value && !validateEmail(emailInput.value)) {
                isValid = false;
                emailInput.style.borderColor = '#ef4444';
                if (emailError) emailError.style.display = 'block';
            } else if (emailError) {
                emailError.style.display = 'none';
            }
            
            const pwd = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirm_password').value;
            const errorText = document.getElementById('password_error');
            
            if (pwd && confirmPwd && pwd !== confirmPwd) {
                isValid = false;
                document.getElementById('confirm_password').style.borderColor = '#ef4444';
                errorText.style.display = 'block';
            } else {
                errorText.style.display = 'none';
            }
        }

        if (!isValid) {
            alert('Please fill in all required fields correctly before proceeding.');
        }

        return isValid;
    }

    function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
    }

    function validatePhoneNumber(phone_number) {
    const re = /^09[0-9]{9}$/;
    return re.test(phone_number);
    }


    function nextStep(step) {
        if (!validateStep(step - 1)) return;

        // Update UI
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');

        // Update Progress
        document.getElementById('step-wrapper-' + (step - 1)).classList.remove('active');
        document.getElementById('step-wrapper-' + (step - 1)).classList.add('completed');
        document.getElementById('circle-' + (step - 1)).innerHTML = '✓';
        
        document.getElementById('step-wrapper-' + step).classList.add('active');
        
        const progressLine = document.getElementById('progress-line');
        if (step === 2) progressLine.style.width = '50%';
        if (step === 3) progressLine.style.width = '100%';
    }

    function prevStep(step) {
        // Update UI
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');

        // Update Progress
        document.getElementById('step-wrapper-' + (step + 1)).classList.remove('active');
        document.getElementById('step-wrapper-' + (step + 1)).classList.remove('completed');
        document.getElementById('circle-' + (step + 1)).innerHTML = (step + 1);
        
        document.getElementById('step-wrapper-' + step).classList.remove('completed');
        document.getElementById('step-wrapper-' + step).classList.add('active');
        document.getElementById('circle-' + step).innerHTML = step;
        
        const progressLine = document.getElementById('progress-line');
        if (step === 1) progressLine.style.width = '0%';
        if (step === 2) progressLine.style.width = '50%';
    }
</script>

</body>
</html>