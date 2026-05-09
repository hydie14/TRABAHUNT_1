<?php include '../DATABASE/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO - Create Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="signup.css?v=3">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
               <img src="../BONGABON.png" style="width:90px;">
            </div>
            <h1>Create Your Account</h1>
            <p>Choose the account type that best fits your needs</p>
        </div>

        <div class="account-types">
            <!-- Job Seeker -->
            <div class="account-card" onclick="window.location.href='../CREATE ACC job seeker/jobseeker_step1.php?type=jobseeker'">
                <div class="account-icon">👤</div>
                <h3>Job Seeker</h3>
                <p>Looking for employment opportunities</p>
                <ul class="feature-list">
                    <li>Apply to jobs</li>
                    <li>Get job alerts</li>
                </ul>
                <button class="btn btn-primary">Continue</button>
            </div>

            <!-- Employer -->
            <div class="account-card" onclick="window.location.href='../CREATE ACC employer/employer_step1.php?type=employer'">
                <div class="account-icon">🏢</div>
                <h3>Employer</h3>
                <p>Hiring talented individuals</p>
                <ul class="feature-list">
                    <li>Post job openings</li>
                    <li>Manage applications</li>
                </ul>
                <button class="btn btn-primary">Continue</button>
            </div>

            <!-- Service Provider -->
            <div class="account-card" onclick="window.location.href='../create acc service/create_account.php?type=service_provider'">
                <div class="account-icon">🛠️</div>
                <h3>Service Provider</h3>
                <p>Offering skilled services to the community</p>
                <ul class="feature-list">
                    <li>List your services</li>
                    <li>Get local bookings</li>
                </ul>
                <button class="btn btn-primary">Continue</button>
            </div>
        </div>

        <div class="login-link">
            <p>Already have an account? <a href="new_login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
