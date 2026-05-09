<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

if (!isset($_SESSION['jobseeker_step1'])) {
    header("Location: jobseeker_step1.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } elseif (!preg_match('/^09\d{9}$/', $_POST['mobile'])) {
        $error = "Invalid Mobile Number. It must start with 09 and be 11 digits long.";
    } else {
        $_SESSION['jobseeker_step2'] = array_map('htmlspecialchars', $_POST);
        header("Location: jobseeker_step3.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seeker Registration - Step 2</title>
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
        .helper-text { font-size: 0.75rem; color: #6b7280; margin-top: 0.375rem; }
        .btn-container { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; }
        .btn { padding: 0.875rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 1rem; border: none; }
        .btn-back { background: #f3f4f6; color: #6b7280; }
        .btn-back:hover { background: #e5e7eb; }
        .btn-next { background: #1e40af; color: white; flex: 1; }
        .btn-next:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        @media (max-width: 768px) { .form-card { padding: 1rem; } .step-label { font-size: 0.6rem; } .container { max-width: 100%; } }
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
                <div class="step active">
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
            <h2 class="form-title">Contact Details</h2>
            <p class="form-subtitle">How can we reach you?</p>
            <?php if($error): ?>
                <div style="background-color: #fee2e2; color: #ef4444; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fca5a5;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form id="step2Form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Mobile Number <span class="required">*</span></label>
                    <input type="tel" id="mobile" name="mobile" placeholder="09XX XXX XXXX" pattern="09[0-9]{9}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" required>
                    <div class="helper-text">Primary contact for verification (11 digits)</div>
                </div>
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="juan.delacruz@email.com" required>
                    <div class="helper-text">We'll send important updates to this email</div>
                </div>
                <div class="form-group">
                    <label>Barangay</label>
                    <select id="barangay" name="barangay">
                        <option value="">Select your barangay</option>
                        <option value="Antipolo">Antipolo</option>
                        <option value="Ariendo">Ariendo</option>
                        <option value="Bantug">Bantug</option>
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
                        <option value="Pook Rizal">Pook Rizal</option>
                        <option value="Sampalucan">Sampalucan</option>
                        <option value="San Roque">San Roque</option>
                        <option value="Santor">Santor</option>
                        <option value="Sinipit">Sinipit</option>
                        <option value="Sisilang na Ligaya">Sisilang na Ligaya</option>
                        <option value="Social">Social</option>
                        <option value="Tugatog">Tugatog</option>
                        <option value="Tulay na Bato">Tulay na Bato</option>
                        <option value="Vega">Vega</option>
                    </select>
                    <div class="helper-text">Bongabon, Nueva Ecija - helps us show location-based jobs</div>
                </div>
                <div class="btn-container">
                    <button type="button" class="btn btn-back" onclick="window.location.href='jobseeker_step1.php'">← Back</button>
                    <button type="submit" class="btn btn-next">Next Step →</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
