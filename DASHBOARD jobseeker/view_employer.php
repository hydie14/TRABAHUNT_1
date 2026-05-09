<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in and is a Job Seeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($employer_id === 0) {
    header("Location: browse_jobs.php");
    exit();
}

// Fetch Jobseeker details for the sidebar
$stmt_user = $conn->prepare("SELECT first_name, last_name FROM jobseekers WHERE seeker_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// Fetch Employer Data
$employer_query = $conn->prepare("
    SELECT e.*, l.barangay, l.city_municipality, l.province, bl.business_name 
    FROM employers e 
    LEFT JOIN locations l ON e.location_id = l.location_id 
    LEFT JOIN business_lines bl ON e.business_line_id = bl.business_line_id 
    WHERE e.employer_id = ? AND e.admin_verification_status = 'Verified'
");
$employer_query->bind_param("i", $employer_id);
$employer_query->execute();
$employer = $employer_query->get_result()->fetch_assoc();
$employer_query->close();

if (!$employer) {
    // Redirect back if employer doesn't exist or is not verified
    header("Location: browse_jobs.php?error=employer_not_found");
    exit();
}

$formatted_address = implode(', ', array_filter([$employer['street_address'] ?? '', $employer['barangay'] ?? '', $employer['city_municipality'] ?? '', $employer['province'] ?? '']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($employer['company_name']); ?> - Company Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; min-height: 100vh; margin: 0; }
        
        /* Main Content Styles */
        .main-content { flex: 1; width: 100%; min-height: 100vh; display: flex; flex-direction: column; }
        .top-header { display: flex; justify-content: flex-start; align-items: center; gap: 1rem; background: #1e40af; color: white; padding: 1.25rem 2rem; border-bottom: 1px solid #1e3a8a; position: sticky; top: 0; z-index: 40; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .content-wrapper { padding: 2rem 3rem; max-width: 1000px; margin: 0 auto; width: 100%; }
        
        .btn-back { display: inline-flex; align-items: center; gap: 0.5rem; color: white; font-weight: 600; text-decoration: none; transition: opacity 0.2s; opacity: 0.9; }
        .btn-back:hover { opacity: 1; text-decoration: underline; }

        /* Facebook-like Profile Styles */
        .profile-header-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; border: 1px solid #e5e7eb; display: flex; flex-direction: column; }
        .profile-cover { height: 250px; background: #e5e7eb; border-radius: 12px 12px 0 0; background-size: cover; background-position: center; border-bottom: 1px solid #e5e7eb; }
        .profile-avatar-container { margin-top: -70px; margin-left: 2rem; position: relative; z-index: 10; width: 140px; }
        .profile-avatar { width: 140px; height: 140px; border-radius: 50%; border: 4px solid white; background: white; display: flex; align-items: center; justify-content: center; font-size: 4rem; font-weight: 700; color: #1e40af; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .profile-info { padding: 1rem 2rem 1.5rem 2rem; text-align: left; }
        .profile-info h1 { font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 0.25rem; }
        .profile-info p { color: #6b7280; font-size: 1rem; margin-bottom: 1rem; }

        /* Info Grid */
        .info-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; margin-bottom: 1.5rem; }
        .info-card h3 { color: #1e40af; font-size: 1.15rem; margin-bottom: 1.5rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .info-item { margin-bottom: 1rem; }
        .info-label { font-size: 0.8rem; text-transform: uppercase; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; color: #1f2937; font-weight: 500; }

        @media (max-width: 768px) {
            .content-wrapper { padding: 1rem; }
            .profile-avatar-container { margin-left: auto; margin-right: auto; display: flex; justify-content: center; }
            .profile-info { text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="main-content">
        <div class="top-header">
            <a href="javascript:history.back()" class="btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.2rem; height: 1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Back to Jobs
            </a>
            <h2 style="margin: 0; font-size: 1.25rem; border-left: 2px solid rgba(255,255,255,0.3); padding-left: 1rem;">Employer Profile</h2>
        </div>

        <div class="content-wrapper">
            <!-- Profile Header -->
            <div class="profile-header-card">
                <div class="profile-cover" style="<?php echo !empty($employer['cover_photo']) ? 'background-image: url('.htmlspecialchars($employer['cover_photo']).');' : ''; ?>">
                </div>
                
                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if(!empty($employer['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($employer['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($employer['company_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($employer['company_name']); ?></h1>
                    <p>🏢 <?php echo htmlspecialchars($employer['business_name'] ?? 'Business Line'); ?> | 📍 <?php echo htmlspecialchars($employer['city_municipality'] ?? 'Location'); ?></p>
                </div>
            </div>

            <!-- Read-Only View -->
            <div id="profile-view">
                <div class="info-card">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg> Company Details</h3>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Trade Name</div><div class="info-value"><?php echo htmlspecialchars($employer['trade_name'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">Employer Type</div><div class="info-value"><?php echo htmlspecialchars($employer['employer_type']); ?></div></div>
                        <div class="info-item"><div class="info-label">Office Type</div><div class="info-value"><?php echo htmlspecialchars($employer['office_type']); ?></div></div>
                        <div class="info-item"><div class="info-label">Total Work Force</div><div class="info-value"><?php echo htmlspecialchars($employer['total_work_force']); ?></div></div>
                        <div class="info-item"><div class="info-label">Address</div><div class="info-value"><?php echo htmlspecialchars($formatted_address); ?></div></div>
                    </div>
                    
                    <h3 style="margin-top: 2rem;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg> Contact Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Contact Person</div><div class="info-value"><?php echo htmlspecialchars($employer['contact_person_name']); ?></div></div>
                        <div class="info-item"><div class="info-label">Mobile Number</div><div class="info-value"><?php echo htmlspecialchars($employer['mobile_number']); ?></div></div>
                        <div class="info-item"><div class="info-label">Email Address</div><div class="info-value"><a href="mailto:<?php echo htmlspecialchars($employer['email_address']); ?>" style="color: #2563eb; text-decoration: none;"><?php echo htmlspecialchars($employer['email_address']); ?></a></div></div>
                        <?php if(!empty($employer['telephone_number'])): ?>
                            <div class="info-item"><div class="info-label">Telephone</div><div class="info-value"><?php echo htmlspecialchars($employer['telephone_number']); ?></div></div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin-top: 2rem;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg> Company Description</h3>
                    <div class="info-value" style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($employer['company_description'] ?: 'No description provided.'); ?></div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>