<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Automatically expire jobs that have passed their validity date
$conn->query("UPDATE job_postings SET status = 'Expired' WHERE valid_until < CURDATE() AND status = 'Active'");

$employer_id = $_SESSION['user_id'];

// Fetch Employer Notifications
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $employer_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
$notif_stmt->close();

// Count unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $employer_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
$unread_stmt->close();

// Fetch Employer Data
$employer_query = $conn->prepare("
    SELECT e.*, l.barangay, l.city_municipality, l.province, bl.business_name 
    FROM employers e 
    LEFT JOIN locations l ON e.location_id = l.location_id 
    LEFT JOIN business_lines bl ON e.business_line_id = bl.business_line_id 
    WHERE e.employer_id = ?
");
$employer_query->bind_param("i", $employer_id);
$employer_query->execute();
$employer_result = $employer_query->get_result();
$employer = $employer_result->fetch_assoc();
$employer_query->close();

$locations = $conn->query("SELECT * FROM locations ORDER BY city_municipality, barangay");
$business_lines = $conn->query("SELECT * FROM business_lines ORDER BY business_name");

// Check Admin Verification Status
if ($employer['admin_verification_status'] === 'Pending') {
    echo "<!DOCTYPE html><html><head><title>Account Pending</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap' rel='stylesheet'><style>body{font-family:'Inter',sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f3f4f6;margin:0}.card{background:white;padding:2rem;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,0.1);text-align:center;max-width:500px}.btn{display:inline-block;margin-top:1rem;padding:0.75rem 1.5rem;background:#1e40af;color:white;text-decoration:none;border-radius:6px;font-weight:600}</style></head><body><div class='card'><h1>Account Pending Verification</h1><p>Your employer account is currently under review by the PESO Admin.</p><p>You will be able to access your dashboard once your documents have been verified.</p><a href='../LOGIN SIGNUP/logout.php' class='btn'>Logout</a></div></body></html>";
    exit();
} elseif ($employer['admin_verification_status'] === 'Rejected') {
    echo "<!DOCTYPE html><html><head><title>Account Rejected</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap' rel='stylesheet'><style>body{font-family:'Inter',sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background:#f3f4f6;margin:0}.card{background:white;padding:2rem;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,0.1);text-align:center;max-width:500px}.btn{display:inline-block;margin-top:1rem;padding:0.75rem 1.5rem;background:#1e40af;color:white;text-decoration:none;border-radius:6px;font-weight:600}</style></head><body><div class='card'><h1 style='color:#ef4444'>Account Rejected</h1><p>Your registration has been rejected.</p><p>Please contact PESO Bongabon for more information.</p><a href='../LOGIN SIGNUP/logout.php' class='btn'>Logout</a></div></body></html>";
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE employer_id = ? AND status = ?");
$status = 'Active';
$stmt->bind_param("is", $employer_id, $status);
$stmt->execute();
$active_job_posts = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications ra JOIN job_postings jp ON ra.job_id = jp.job_id WHERE jp.employer_id = ? AND ra.status NOT IN ('Pending', 'Pending_Docs', 'Verified', 'Rejected', 'Terminated', 'Resigned')");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$total_applications = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications ra JOIN job_postings jp ON ra.job_id = jp.job_id WHERE jp.employer_id = ? AND ra.status IN ('Referral_Issued', 'Issue Referral Letter', 'Pending Interview')");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$pending_review = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM referrals_applications ra 
    JOIN job_postings jp ON ra.job_id = jp.job_id 
    WHERE jp.employer_id = ? 
    AND ra.status IN ('Hired', 'Hired / Placed', 'For Deployment')");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$total_hired = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM job_postings WHERE employer_id = ? AND status IN ('Active', 'Pending_Approval', 'Rejected') ORDER BY created_at DESC");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$job_postings_result = $stmt->get_result();
$all_jobs = [];
while($row = $job_postings_result->fetch_assoc()) { $all_jobs[] = $row; }
$stmt->close();

// Fetch Closed and Expired Jobs for Archive
$stmt_closed = $conn->prepare("SELECT * FROM job_postings WHERE employer_id = ? AND status IN ('Closed', 'Expired') ORDER BY created_at DESC");
$stmt_closed->bind_param("i", $employer_id);
$stmt_closed->execute();
$closed_jobs_result = $stmt_closed->get_result();
$closed_jobs = [];
while($row = $closed_jobs_result->fetch_assoc()) { $closed_jobs[] = $row; }
$stmt_closed->close();

$stmt2 = $conn->prepare("
    SELECT js.first_name, js.last_name, jp.job_title, ra.created_at as application_date, ra.status, jp.job_id, ra.application_id 
    FROM referrals_applications ra 
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id 
    JOIN job_postings jp ON ra.job_id = jp.job_id 
    WHERE jp.employer_id = ? AND ra.status NOT IN ('Pending', 'Pending_Docs', 'Verified')
    ORDER BY ra.created_at DESC 
");
$stmt2->bind_param("i", $employer_id);
$stmt2->execute();
$referrals_result = $stmt2->get_result();
$all_referrals = [];
while($row = $referrals_result->fetch_assoc()) { $all_referrals[] = $row; }
$stmt2->close();

// Fetch verified service providers for the new tab
$service_providers_result = $conn->query("
    SELECT sp.provider_id, sp.first_name, sp.last_name, sp.barangay, sp.street_address, 
           ps.service_id, ps.service_name, ps.category, ps.description, ps.base_rate 
    FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.provider_id
    WHERE sp.admin_verification_status = 'Verified' AND ps.status = 'Active'
    ORDER BY ps.created_at DESC
");
$service_providers = [];
if ($service_providers_result) {
    while($row = $service_providers_result->fetch_assoc()) { $service_providers[] = $row; }
}

// Fetch Upcoming Interviews
$stmt_interviews = $conn->prepare("
    SELECT js.first_name, js.last_name, jp.job_title, ra.interview_date 
    FROM referrals_applications ra 
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id 
    JOIN job_postings jp ON ra.job_id = jp.job_id 
    WHERE jp.employer_id = ? AND ra.status = 'Pending Interview' AND ra.interview_date IS NOT NULL
    ORDER BY ra.interview_date ASC
");
$stmt_interviews->bind_param("i", $employer_id);
$stmt_interviews->execute();
$upcoming_interviews = $stmt_interviews->get_result();
$stmt_interviews->close();

// Prepare formatted address
$address_parts = array_filter([
    $employer['street_address'] ?? '',
    $employer['barangay'] ?? '',
    $employer['city_municipality'] ?? '',
    $employer['province'] ?? ''
]);
$formatted_address = !empty($address_parts) ? implode(', ', $address_parts) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; min-height: 100vh; margin: 0; }
        
        /* Sidebar Styles */
        .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100%; transition: transform 0.3s ease; z-index: 50; top: 0; left: 0; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 1rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f3f4f6; }
        .brand { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; cursor: pointer; }
        .brand img { height: 60px; width: 60px; object-fit: contain; }
        .brand-name { font-weight: 800; font-size: 1.1rem; color: #1e40af; letter-spacing: -0.01em; }
        .nav-menu { padding: 1rem 0.75rem; flex: 1; display: flex; flex-direction: column; gap: 0.15rem; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; text-decoration: none; color: #64748b; border-radius: 8px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: #f8fafc; color: #0f172a; border-left-color: #cbd5e1; }
        .nav-item.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; font-weight: 600; }
        .sidebar-badge { background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        .nav-icon svg { width: 1.1rem !important; height: 1.1rem !important; }
        
        /* Profile Section */
        .user-profile { padding: 0.75rem; border-top: 1px solid #f3f4f6; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; position: relative; transition: background 0.2s; }
        .user-profile:hover { background: #f8fafc; }
        .avatar { width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; color: #6b7280; flex-shrink: 0; overflow: hidden; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #111827; }
        .user-role { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }
        
        /* Main Content Styles */
         .main-content { flex: 1; margin-left: 260px; transition: margin-left 0.3s ease, width 0.3s ease; width: calc(100% - 260px); min-height: 100vh; display: flex; flex-direction: column; }
        .main-content.expanded { margin-left: 0; width: 100%; }
        .top-header { display: flex; justify-content: space-between; align-items: center; background: #1e40af; color: white; padding: 1.25rem 2rem; border-bottom: 1px solid #1e3a8a; position: sticky; top: 0; z-index: 40; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .hamburger { background: none; border: none; cursor: pointer; color: white; padding: 0.25rem; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: background 0.2s; }
        .hamburger:hover { background: rgba(255,255,255,0.1); }
        .content-wrapper { padding: 2rem 3rem; max-width: 1000px; margin: 0 auto; width: 100%; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }

      

        .welcome-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .welcome-card h1 { font-size: 1.5rem; color: #1e40af; margin-bottom: 0.5rem; }
        .welcome-card p { color: #6b7280; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #1e40af; display: flex; flex-direction: column; justify-content: center; }
        .stat-card h3 { font-size: 2rem; font-weight: 800; color: #1e40af; margin-bottom: 0.5rem; line-height: 1; }
        .stat-card p { color: #6b7280; font-size: 0.85rem; font-weight: 500; margin: 0; }
        .section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .section-title { font-size: 1.1rem; color: #1f2937; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .btn-primary { background: #1e40af; color: white; }
        .btn-primary:hover { background: #1e3a8a; }
        .btn-secondary { background: #fbbf24; color: #1e40af; }
        .btn-secondary:hover { background: #f59e0b; }
        .job-card { border: 2px solid #e5e7eb; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; }
        .job-card h4 { color: #1f2937; margin-bottom: 0.5rem; }
        .job-card .details { color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem; }
        .job-card .actions { display: flex; gap: 1rem; }
        .applicant-card { border: 2px solid #e5e7eb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .applicant-info h5 { color: #1f2937; margin-bottom: 0.25rem; }
        .applicant-info p { color: #6b7280; font-size: 0.875rem; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #dbeafe; color: #1e40af; }
        
        /* Notification Styles */
        .notification-wrapper { position: relative; margin-right: 0.5rem; }
        .bell-btn { background: #f3f4f6; border: 1px solid #e5e7eb; color: #4b5563; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .bell-btn:hover { background: #e5e7eb; color: #1f2937; }
        .bell-icon { font-size: 1.25rem; }
        .badge-count { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; border: 2px solid #1e3a8a; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .notification-dropdown { position: absolute; top: 50px; right: 0; width: 320px; background: white; border-radius: 12px; box-shadow: 0 10px 25px -3px rgba(0,0,0,0.15), 0 4px 6px -2px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; z-index: 150; display: none; overflow: hidden; color: #1f2937; text-align: left; }
        .notification-dropdown.active { display: block; }
        .notif-header { padding: 1rem; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .notif-header h3 { font-size: 1rem; font-weight: 600; margin: 0; }
        .mark-read-btn { font-size: 0.75rem; color: #1e40af; text-decoration: none; cursor: pointer; }
        .notif-list { max-height: 300px; overflow-y: auto; }
        .notif-item { padding: 1rem; border-bottom: 1px solid #f3f4f6; transition: background 0.2s; cursor: pointer; text-decoration: none; display: block; color: inherit; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f9fafb; }
        .notif-item.unread { background: #eff6ff; }
        .notif-item.unread:hover { background: #e0e7ff; }
        .notif-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .notif-message { font-size: 0.85rem; color: #6b7280; line-height: 1.4; margin-bottom: 0.5rem; }
        .notif-time { font-size: 0.75rem; color: #9ca3af; }
        .empty-notif { padding: 2rem; text-align: center; color: #6b7280; font-size: 0.9rem; }

        /* Form Styles for Edit Profile */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; }
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500;}
        .success { background: #d1fae5; color: #065f46; border: 1px solid #34d399;}
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #f87171;}

        /* Loading Screen & Transition */
        #loader-wrapper { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #f3f4f6; z-index: 9999; display: flex; justify-content: center; align-items: center; transition: opacity 0.5s ease; }
        .loader { border: 4px solid #e5e7eb; border-top: 4px solid #1e40af; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .page-transition { animation: fadeIn 0.6s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; width: 100%; }
            .content-wrapper { padding: 1rem; }
            .top-header { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .section-title { flex-direction: column; gap: 1rem; align-items: flex-start; }
            .section-title a, .section-title button { width: 100%; text-align: center; box-sizing: border-box; }
            .job-card .actions { flex-direction: column; }
            .job-card .actions a, .job-card .actions button { width: 100%; text-align: center; box-sizing: border-box; margin-bottom: 0.5rem; margin-left: 0 !important; }
            .applicant-card { flex-direction: column; gap: 1rem; align-items: flex-start; }
            .notification-dropdown { position: fixed; top: 70px; right: 1rem; width: calc(100vw - 2rem); max-width: 320px; z-index: 1000; }
            #profile-view { grid-template-columns: 1fr !important; }
            .form-grid { grid-template-columns: 1fr !important; }
            html { font-size: 14px; }
            .welcome-card h1 { font-size: 1.5rem; }
            .stat-card h3 { font-size: 1.25rem; }
        }
        .profile-details p { margin-bottom: 0.75rem; color: #374151; }
        .profile-details strong { color: #1f2937; width: 150px; display: inline-block; }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loader-wrapper">
        <div class="loader"></div>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#dashboard" onclick="showSection('dashboard')" class="brand">
                <img src="../BONGABON.png" alt="Logo">
                <span class="brand-name">PESO BONGABON EMPLOYER</span>
            </a>
        </div>
        <nav class="nav-menu">
            <a href="#dashboard" id="nav-dashboard" class="nav-item active" onclick="showSection('dashboard')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span>
                Dashboard
            </a>
            <a href="#jobs" id="nav-jobs" class="nav-item" onclick="showSection('jobs')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.075A2.25 2.25 0 0118 20.5H6A2.25 2.25 0 013.75 18.225V6.11A2.25 2.25 0 016 3.862h12a2.25 2.25 0 012.25 2.25v8.078zM15 3.862v1.714a2.25 2.25 0 01-2.25 2.25h-3.75A2.25 2.25 0 016.75 5.576V3.862" /></svg></span>
                My Job Posts
            </a>
            <a href="#archive" id="nav-archive" class="nav-item" onclick="showSection('archive')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg></span>
                Job Archive
            </a>
            <a href="#referrals" id="nav-referrals" class="nav-item" onclick="showSection('referrals')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></span>
                PESO Referrals
            </a>
            <a href="browse_services.php" id="nav-services" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M11.42 15.17L15.17 11.42m-3.75 3.75L3.75 21m6.938-9.938l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M6.938 11.062l-2.472 2.472a.375.375 0 000 .53l2.472 2.472m0 0l2.472-2.472" /></svg></span>
                Find Services
            </a>
            <a href="company_profile.php" id="nav-company" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg></span>
                Company Profile
            </a>
            <a href="notifications.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg></span>
                Notifications
                <?php if(isset($unread_count) && $unread_count > 0): ?>
                    <span class="sidebar-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="settings.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span>
                Settings
            </a>
        </nav>
        <div class="user-profile" onclick="toggleProfileDropdown()">
            <div class="profile-dropdown" id="profileDropdown">
                <a href="../LOGIN SIGNUP/logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                    Logout
                </a>
            </div>
            <div class="avatar">
                <?php if(!empty($employer['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($employer['profile_picture']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($employer['company_name'] ?? 'E', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($employer['company_name'] ?? 'Employer'); ?></div>
                <div class="user-role">Employer</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; margin-left: auto; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <main class="main-content" id="mainContent">
        <div class="top-header">
            <div class="header-left">
                <button class="hamburger" onclick="toggleSidebar()">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <h2 id="pageTitle" style="margin: 0; font-size: 1.25rem; color: #ffffff;">Dashboard Overview</h2>
            </div>
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Notification Bell -->
                <div class="notification-wrapper">
                    <div class="bell-btn" onclick="toggleNotifications()">
                        <span class="bell-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg></span>
                        <?php if($unread_count > 0): ?>
                            <span class="badge-count" id="badgeCount"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h3>Notifications</h3>
                            <?php if($unread_count > 0): ?>
                                <span class="mark-read-btn" onclick="markAllRead()">Mark all as read</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-list">
                            <?php if($notifications->num_rows > 0): ?>
                                <?php while($notif = $notifications->fetch_assoc()): ?>
                                    <a href="#referrals" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" onclick="markRead(<?php echo $notif['notification_id']; ?>); showSection('referrals');">
                                        <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                        <div class="notif-time"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></div>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-notif">No notifications yet.</div>
                            <?php endif; ?>
                        </div>
                        <div class="notif-footer" style="padding: 0.75rem; text-align: center; border-top: 1px solid #f3f4f6;">
                            <a href="notifications.php" style="color: #1e40af; font-size: 0.875rem; font-weight: 600; text-decoration: none;">View All Notifications</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-wrapper page-transition">
        <div id="dashboard-overview">
        <div class="welcome-card">
            <h1>Hello, <?php echo htmlspecialchars($employer['company_name']); ?>!</h1>
            <p>Manage your job postings and review PESO referrals</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $active_job_posts; ?></h3>
                <p>Active Job Posts</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_applications; ?></h3>
                <p>PESO Referrals Received</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $pending_review; ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_hired; ?></h3>
                <p>Total Hired</p>
            </div>
        </div>
        
        <!-- Upcoming Interviews Section -->
        <div class="section" style="margin-bottom: 2rem; background: #eff6ff; border: 1px solid #bfdbfe;">
            <div class="section-title" style="margin-bottom: 1rem;">
                <h2 style="color: #1e40af;">Upcoming Interviews</h2>
            </div>
            <?php if($upcoming_interviews->num_rows > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                <?php while($interview = $upcoming_interviews->fetch_assoc()): ?>
                    <div style="background: white; border-left: 4px solid #3b82f6; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <h4 style="margin: 0 0 0.25rem 0; color: #1f2937;"><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></h4>
                        <p style="margin: 0 0 0.75rem 0; color: #6b7280; font-size: 0.85rem;">Applying for: <?php echo ucwords(htmlspecialchars($interview['job_title'])); ?></p>
                        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #f3f4f6; color: #374151; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 14px; height: 14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            <?php echo date('M d, Y - h:i A', strtotime($interview['interview_date'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; font-style: italic; margin: 0;">No upcoming interviews scheduled.</p>
            <?php endif; ?>
        </div>

        <!-- Previews for Dashboard Overview -->
        <div class="section" style="margin-bottom: 2rem;">
            <div class="section-title">
                <h2>Recent Job Postings</h2>
                <a href="#jobs" onclick="showSection('jobs')" class="btn btn-secondary">View All Jobs</a>
            </div>
            <?php $job_count = 0; foreach($all_jobs as $job): if($job_count >= 3) break; $job_count++; ?>
            <div class="job-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4><?php echo ucwords(htmlspecialchars($job['job_title'])); ?></h4>
                    <?php 
                        $jobStatusColor = '#dbeafe'; $jobTextColor = '#1e40af';
                        if ($job['status'] === 'Active') { $jobStatusColor = '#d1fae5'; $jobTextColor = '#059669'; }
                        elseif ($job['status'] === 'Pending_Approval') { $jobStatusColor = '#fef3c7'; $jobTextColor = '#d97706'; }
                        elseif ($job['status'] === 'Rejected') { $jobStatusColor = '#fee2e2'; $jobTextColor = '#dc2626'; }
                    ?>
                    <span style="background: <?php echo $jobStatusColor; ?>; color: <?php echo $jobTextColor; ?>; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                        <?php echo str_replace('_', ' ', htmlspecialchars($job['status'])); ?>
                    </span>
                </div>
                <div class="details">Posted: <?php echo date("M d, Y", strtotime($job['created_at'])); ?></div>
                <div class="actions">
                    <a href="view_job.php?job_id=<?php echo $job['job_id']; ?>" class="btn" style="background: #e5e7eb; color: #374151;">View Details</a>
                    <?php if ($job['status'] === 'Active'): ?>
                        <a href="view_referrals.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-secondary">View Referrals</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($all_jobs)): ?>
                <p>No active job postings.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <div class="section-title">
                <h2>Recent PESO Referrals</h2>
                <a href="#referrals" onclick="showSection('referrals')" class="btn btn-secondary">View All Referrals</a>
            </div>
            <?php $ref_count = 0; foreach($all_referrals as $referral): if($ref_count >= 3) break; $ref_count++; ?>
            <div class="applicant-card">
                <div class="applicant-info">
                    <h5><?php echo htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']); ?></h5>
                    <p>Applied for: <?php echo ucwords(htmlspecialchars($referral['job_title'])); ?></p>
                    <p>Referred by PESO: <?php echo date("M d, Y", strtotime($referral['application_date'])); ?></p>
                </div>
                <div>
                    <?php 
                        $statusColor = '#dbeafe'; $textColor = '#1e40af'; // Default Blue
                        if(strpos($referral['status'], 'Hired') !== false || $referral['status'] === 'Accepted' || $referral['status'] === 'For Deployment') { $statusColor = '#d1fae5'; $textColor = '#059669'; } // Green
                        elseif(strpos($referral['status'], 'Reject') !== false || in_array($referral['status'], ['Terminated', 'Resigned'])) { $statusColor = '#fee2e2'; $textColor = '#dc2626'; } // Red
                    ?>
                    <span class="badge" style="background: <?php echo $statusColor; ?>; color: <?php echo $textColor; ?>; font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                        <?php echo htmlspecialchars($referral['status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($all_referrals)): ?>
                <p>No recent referrals.</p>
            <?php endif; ?>
        </div>
        </div>

        <div class="section" id="jobs" style="display: none;">
            <div class="section-title">
                <h2>All Job Postings</h2>
                <a href="post_job.php" class="btn btn-primary">+ Post New Job</a>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <input type="text" id="jobSearch" onkeyup="filterJobs()" placeholder="Search by job title..." style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
            </div>
            <?php foreach($all_jobs as $job): ?>
            <div class="job-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4><?php echo ucwords(htmlspecialchars($job['job_title'])); ?></h4>
                    <?php 
                        $jobStatusColor = '#dbeafe'; $jobTextColor = '#1e40af';
                        if ($job['status'] === 'Active') { $jobStatusColor = '#d1fae5'; $jobTextColor = '#059669'; }
                        elseif ($job['status'] === 'Pending_Approval') { $jobStatusColor = '#fef3c7'; $jobTextColor = '#d97706'; }
                        elseif ($job['status'] === 'Rejected') { $jobStatusColor = '#fee2e2'; $jobTextColor = '#dc2626'; }
                    ?>
                    <span style="background: <?php echo $jobStatusColor; ?>; color: <?php echo $jobTextColor; ?>; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                        <?php echo str_replace('_', ' ', htmlspecialchars($job['status'])); ?>
                    </span>
                </div>
                <div class="details">Posted: <?php echo date("M d, Y", strtotime($job['created_at'])); ?></div>
                <div class="actions">
                    <a href="view_job.php?job_id=<?php echo $job['job_id']; ?>" class="btn" style="background: #e5e7eb; color: #374151;">View Details</a>
                    <?php if ($job['status'] === 'Active'): ?>
                        <a href="view_referrals.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-secondary">View Referrals</a>
                    <?php endif; ?>
                    <?php if (in_array($job['status'], ['Active', 'Pending_Approval'])): ?>
                        <a href="edit_post.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-primary">Edit Post</a>
                    <?php endif; ?>
                    <?php if ($job['status'] === 'Active'): ?>
                        <button type="button" class="btn" style="background: #dc2626; color: white;" onclick="openCloseModal(<?php echo $job['job_id']; ?>)">Close Job</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
             <?php if(empty($all_jobs)): ?>
                <p>No active job postings.</p>
            <?php endif; ?>
        </div>

        <div class="section" id="archive" style="display: none;">
            <div class="section-title">
                <h2>Closed Job Postings (Archive)</h2>
            </div>
            <?php foreach($closed_jobs as $job): ?>
            <div class="job-card" style="background: #f9fafb; border-color: #d1d5db;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4 style="color: #6b7280; margin-bottom: 0.5rem;"><?php echo ucwords(htmlspecialchars($job['job_title'])); ?></h4>
                    <span style="background: <?php echo $job['status'] === 'Expired' ? '#fee2e2' : '#f3f4f6'; ?>; color: <?php echo $job['status'] === 'Expired' ? '#dc2626' : '#4b5563'; ?>; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                        <?php echo htmlspecialchars($job['status']); ?>
                    </span>
                </div>
                <div class="details" style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem;">
                    Posted: <?php echo date("M d, Y", strtotime($job['created_at'])); ?><br>
                    <?php if($job['close_reason']): ?>
                        Reason: <strong><?php echo htmlspecialchars($job['close_reason']); ?></strong>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <a href="view_job.php?job_id=<?php echo $job['job_id']; ?>" class="btn" style="background: #e5e7eb; color: #374151;">View Details</a>
                    <form action="reopen_job.php" method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to reopen this job? It will be sent to the PESO Admin for re-approval.');">Reopen Job</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
             <?php if(empty($closed_jobs)): ?>
                <p style="color: #6b7280; font-style: italic;">No closed or expired job postings.</p>
            <?php endif; ?>
        </div>

        <div class="section" id="referrals" style="display: none;">
            <div class="section-title">
                <h2>All PESO Referrals</h2>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <input type="text" id="referralSearch" onkeyup="filterReferrals()" placeholder="Search by applicant name..." style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
            </div>
            <?php foreach($all_referrals as $referral): ?>
            <div class="applicant-card">
                <div class="applicant-info">
                    <h5><?php echo htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']); ?></h5>
                    <p>Applied for: <?php echo ucwords(htmlspecialchars($referral['job_title'])); ?></p>
                    <p>Referred by PESO: <?php echo date("M d, Y", strtotime($referral['application_date'])); ?></p>
                </div>
                <div>
                    <?php 
                        $statusColor = '#dbeafe'; $textColor = '#1e40af'; // Default Blue
                        if(strpos($referral['status'], 'Hired') !== false || $referral['status'] === 'Accepted' || $referral['status'] === 'For Deployment') { $statusColor = '#d1fae5'; $textColor = '#059669'; } // Green
                        elseif(strpos($referral['status'], 'Reject') !== false || in_array($referral['status'], ['Terminated', 'Resigned'])) { $statusColor = '#fee2e2'; $textColor = '#dc2626'; } // Red
                    ?>
                    <span class="badge" style="background: <?php echo $statusColor; ?>; color: <?php echo $textColor; ?>; font-size: 0.8rem; padding: 0.35rem 0.85rem;">
                        <?php echo htmlspecialchars($referral['status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($all_referrals)): ?>
                <p>No referrals found.</p>
            <?php endif; ?>
        </div>

        <div class="section" id="services" style="display: none;">
            <div class="section-title">
                <h2>Find Local Services</h2>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <input type="text" id="serviceSearch" onkeyup="filterServices()" placeholder="Search by name, skill, or service offered..." style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
            </div>
            <div id="serviceList" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php if (!empty($service_providers)): ?>
                    <?php foreach($service_providers as $sp): ?>
                        <div class="job-card service-card">
                            <div style="display: flex; justify-content: space-between;">
                                <h4 style="color: #1e40af; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($sp['service_name']); ?></h4>
                                <span style="font-weight: bold; color: #059669;"><?php echo htmlspecialchars($sp['base_rate']); ?></span>
                            </div>
                            <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Provider: <?php echo htmlspecialchars($sp['first_name'] . ' ' . $sp['last_name']); ?></p>
                            <div class="details">
                                <p style="margin-bottom: 0.5rem;">📍 <?php echo htmlspecialchars($sp['street_address'] . ', ' . $sp['barangay']); ?></p>
                                <p style="color: #4b5563; margin-bottom: 1rem; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($sp['description'])); ?></p>
                                <a href="book_service.php?id=<?php echo $sp['service_id']; ?>" class="btn btn-primary" style="width: 100%; text-align: center; display: block; box-sizing: border-box;">Contact / Book</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No verified service providers found at the moment.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
    </main>

    <!-- Close Job Modal -->
    <div id="closeJobModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; width: 100%; max-width: 500px; position: relative;">
            <h2 style="margin-top: 0; color: #1e40af;">Close Job Post</h2>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">Why are you closing this job offer?</p>
            
            <form action="close_job.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="job_id" id="modalJobId">
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Reason for Closing</label>
                    <select name="close_reason" required style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="">Select a reason...</option>
                        <option value="Quota Reached">Quota Reached (Nakuha na ang quota)</option>
                        <option value="Position Cancelled">Position Cancelled</option>
                        <option value="Internal Hire">Internal Hire</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div style="text-align: right; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeCloseModal()" style="padding: 0.75rem 1.5rem; background: #e5e7eb; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 0.75rem 1.5rem; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm Close</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Remove Loader on Page Load
        window.addEventListener('load', function() {
            const loader = document.getElementById('loader-wrapper');
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => { loader.style.display = 'none'; }, 500);
            }
        });

        // Handle initial load based on URL hash
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1);
            if (hash) {
                showSection(hash);
            }
        });

        function toggleSidebar() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.toggle('active');
                document.getElementById('sidebarOverlay').classList.toggle('active');
            } else {
                document.getElementById('sidebar').classList.toggle('closed');
                document.getElementById('mainContent').classList.toggle('expanded');
            }
        }

        function showSection(sectionId) {
            // Hide all sections first
            document.getElementById('dashboard-overview').style.display = 'none';
            document.getElementById('jobs').style.display = 'none';
            document.getElementById('referrals').style.display = 'none';
            document.getElementById('archive').style.display = 'none';
            document.getElementById('services').style.display = 'none';

            // Remove active class from nav items
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

            // Show the requested section
            let targetId = sectionId === '' ? 'dashboard' : sectionId;
            let targetEl = document.getElementById(targetId === 'dashboard' ? 'dashboard-overview' : targetId);
            
            if(targetEl) {
                targetEl.style.display = 'block';
                let navItem = document.getElementById('nav-' + targetId);
                if(navItem) navItem.classList.add('active');
            } else {
                document.getElementById('dashboard-overview').style.display = 'block';
                document.getElementById('nav-dashboard').classList.add('active');
                targetId = 'dashboard';
            }

            // Update page title
            const pageTitle = document.getElementById('pageTitle');
            switch(targetId) {
                case 'jobs':
                    pageTitle.innerText = 'My Job Posts'; break;
                case 'archive':
                    pageTitle.innerText = 'Job Archive'; break;
                case 'referrals':
                    pageTitle.innerText = 'PESO Referrals'; break;
                case 'services':
                    pageTitle.innerText = 'Find Local Services'; break;
                default:
                    pageTitle.innerText = 'Dashboard Overview';
            }

            // Close mobile menu if open
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarOverlay').classList.remove('active');
            }
        }

        function filterJobs() {
            const input = document.getElementById('jobSearch');
            const filter = input.value.toLowerCase();
            const jobCards = document.querySelectorAll('#jobs .job-card');

            jobCards.forEach(card => {
                const title = card.querySelector('h4').textContent.toLowerCase();
                if (title.includes(filter)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function filterReferrals() {
            const input = document.getElementById('referralSearch');
            const filter = input.value.toLowerCase();
            const referralCards = document.querySelectorAll('#referrals .applicant-card');

            referralCards.forEach(card => {
                const name = card.querySelector('.applicant-info h5').textContent.toLowerCase();
                if (name.includes(filter)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function filterServices() {
            const input = document.getElementById('serviceSearch');
            const filter = input.value.toLowerCase();
            const serviceCards = document.querySelectorAll('#serviceList .service-card');

            serviceCards.forEach(card => {
                const textContent = card.textContent.toLowerCase();
                if (textContent.includes(filter)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }

        function toggleProfileEdit() {
            const view = document.getElementById('profile-view');
            const edit = document.getElementById('profile-edit');
            const btn = document.getElementById('editProfileBtn');
            if (view.style.display === 'none') {
                view.style.display = 'grid';
                edit.style.display = 'none';
                btn.style.display = 'inline-block';
            } else {
                view.style.display = 'none';
                edit.style.display = 'block';
                btn.style.display = 'none';
            }
        }

        function openCloseModal(jobId) {
            document.getElementById('modalJobId').value = jobId;
            document.getElementById('closeJobModal').style.display = 'flex';
        }

        function closeCloseModal() {
            document.getElementById('closeJobModal').style.display = 'none';
        }

        function toggleNotifications() {
            document.getElementById('notifDropdown').classList.toggle('active');
        }

        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const wrapper = document.querySelector('.notification-wrapper');
            if (wrapper && !wrapper.contains(event.target)) {
                document.getElementById('notifDropdown').classList.remove('active');
            }
            const profile = document.querySelector('.user-profile');
            if (profile && !profile.contains(event.target)) {
                document.getElementById('profileDropdown').classList.remove('active');
            }
        });

        function markRead(id) {
            fetch('../DASHBOARD jobseeker/mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            });
        }

        function markAllRead() {
            fetch('../DASHBOARD jobseeker/mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all=1'
            }).then(() => { location.reload(); });
        }
    </script>
</body>
</html>
