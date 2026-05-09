<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $dob = $_POST['dob'];
        $today = new DateTime();
        $birthdate = new DateTime($dob);
        $age = $today->diff($birthdate)->y;

        if ($age < 18) {
            $error = "You must be at least 18 years old to register.";
        } else {
            $_SESSION['jobseeker_step1'] = array_map('htmlspecialchars', $_POST);
            header("Location: jobseeker_step2.php");
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
    <title>Job Seeker Registration - Step 1</title>
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
        .progress-line { position: absolute; top: 20px; left: 0; height: 3px; background: #fbbf24; z-index: 1; transition: width 0.3s; width: 0%; }
        .step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; flex: 1; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-bottom: 0.5rem; transition: all 0.3s; }
        .step.active .step-circle { background: #1e40af; color: white; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2); }
        .step.completed .step-circle { background: #fbbf24; color: #1e40af; }
        .step-label { font-size: 0.75rem; color: #6b7280; font-weight: 500; text-align: center; }
        .step.active .step-label { color: #1e40af; font-weight: 600; }
        .form-card { background: white; padding: 1.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.25rem; color: #1f2937; margin-bottom: 0.25rem; }
        .form-subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; font-size: 0.875rem; }
        .required { color: #ef4444; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .btn-container { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; }
        .btn { padding: 0.875rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 1rem; border: none; }
        .btn-back { background: #f3f4f6; color: #6b7280; }
        .btn-back:hover { background: #e5e7eb; }
        .btn-next { background: #1e40af; color: white; flex: 1; }
        .btn-next:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        @media (max-width: 768px) { .form-card { padding: 1rem; } .form-row { grid-template-columns: 1fr; } .step-label { font-size: 0.6rem; } .container { max-width: 100%; } }
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
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Contact Details</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Login Credentials</div>
                </div>
            </div>
        </div>
        <div class="form-card">
            <h2 class="form-title">Personal Information</h2>
            <p class="form-subtitle">Tell us about yourself</p>
            <?php if($error): ?>
                <div style="background-color: #fee2e2; color: #ef4444; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fca5a5; font-size: 0.9rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form id="step1Form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                <?php echo csrf_field(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" id="firstName" name="firstName" placeholder="Juan" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" id="middleName" name="middleName" placeholder="Santos">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" id="lastName" name="lastName" placeholder="Dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label>Suffix (e.g. Jr., III)</label>
                        <input type="text" id="suffix" name="suffix" placeholder="Jr.">
                    </div>
                </div>
                <div class="form-group">
                    <label>Date of Birth <span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" required>
                </div>
                <div class="form-group">
                    <label>Sex <span class="required">*</span></label>
                    <select id="sex" name="sex" required>
                        <option value="">Select your sex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Civil Status <span class="required">*</span></label>
                    <select id="civilStatus" name="civilStatus" required>
                        <option value="">Select Status</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Separated">Separated</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Place of Birth <span class="required">*</span></label>
                    <input type="text" id="placeOfBirth" name="placeOfBirth" placeholder="City/Municipality, Province" required>
                </div>
                <div class="btn-container">
                    <button type="button" class="btn btn-back" onclick="window.location.href='../LOGIN SIGNUP/new_signup.php'">Back</button>
                    <button type="submit" class="btn btn-next">Next Step →</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('step1Form').addEventListener('submit', function(e) {
            const dobInput = document.getElementById('dob');
            if (!dobInput.value) return;
            
            const dob = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                age--;
            }

            if (age < 18) {
                e.preventDefault();
                alert("You must be at least 18 years old to register.");
            }
        });
    </script>
</body>
</html>
