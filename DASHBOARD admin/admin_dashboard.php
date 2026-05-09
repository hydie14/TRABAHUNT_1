<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Function to compress numbers (e.g., 1000 -> 1K)
function format_number($n) {
    if ($n < 1000) {
        $n_format = number_format($n);
    } else if ($n < 1000000) {
        $n_format = number_format($n / 1000, 1) . 'K';
    } else if ($n < 1000000000) {
        $n_format = number_format($n / 1000000, 1) . 'M';
    } else {
        $n_format = number_format($n / 1000000000, 1) . 'B';
    }
    // Only remove .0 if it exists (prevents removing '0' from just 0)
    if (strpos($n_format, '.') !== false) {
        $n_format = rtrim(rtrim($n_format, '0'), '.');
    }
    return $n_format;
}

// Fetch Admin Notifications
$admin_user_id = $_SESSION['user_id'];
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $admin_user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
$notif_stmt->close();

// Count unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $admin_user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
$unread_stmt->close();

// Automatically expire jobs that have passed their validity date
$conn->query("UPDATE job_postings SET status = 'Expired' WHERE valid_until < CURDATE() AND status = 'Active'");

$stmt = $conn->prepare("SELECT COUNT(DISTINCT ra.seeker_id) as count FROM referrals_applications ra JOIN users u ON ra.seeker_id = u.user_id WHERE u.role = 'JobSeeker' AND ra.status NOT IN ('Pending', 'Pending_Docs', 'Verified', 'Rejected')");
$stmt->execute();
$total_job_seekers = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications WHERE status NOT IN ('Pending', 'Pending_Docs', 'Verified', 'Rejected')");
$stmt->execute();
$successfully_referred = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE status = ?");
$status = 'Active';
$stmt->bind_param("s", $status);
$stmt->execute();
$active_job_posts = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications WHERE status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment')");
$stmt->execute();
$total_hired = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count Pending Applications (For Job Matching)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications WHERE status IN ('Pending', 'Pending_Docs', 'Verified', 'Issue Referral Letter')");
$stmt->execute();
$pending_applications_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Count Pending Employers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM employers WHERE admin_verification_status = 'Pending'");
$stmt->execute();
$pending_employers_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// New Referral Counter
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications WHERE status NOT IN ('Pending', 'Pending_Docs', 'Verified', 'Rejected')");
$stmt->execute();
$total_referrals_issued = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get Pending Employers List
$stmt = $conn->prepare("
    SELECT e.employer_id, e.company_name, e.employer_type, u.created_at
    FROM employers e
    JOIN users u ON e.employer_id = u.user_id
    WHERE e.admin_verification_status = 'Pending'
    ORDER BY u.created_at ASC
    LIMIT 3
");
$stmt->execute();
$pending_employers_result = $stmt->get_result();
$stmt->close();

// Count Pending Service Providers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM service_providers WHERE admin_verification_status = 'Pending'");
$stmt->execute();
$pending_sp_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get Pending Service Providers List
$stmt = $conn->prepare("
    SELECT sp.provider_id, sp.first_name, sp.last_name, u.created_at
    FROM service_providers sp
    JOIN users u ON sp.provider_id = u.user_id
    WHERE sp.admin_verification_status = 'Pending'
    ORDER BY u.created_at ASC
    LIMIT 3
");
$stmt->execute();
$pending_sp_result = $stmt->get_result();
$stmt->close();

// Count Pending Job Posts
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE status = 'Pending_Approval'");
$stmt->execute();
$pending_jobs_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get Pending Job Posts List
$stmt = $conn->prepare("
    SELECT jp.job_id, jp.job_title, e.company_name, jp.created_at
    FROM job_postings jp JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.status = 'Pending_Approval' ORDER BY jp.created_at ASC LIMIT 3
");
$stmt->execute();
$pending_jobs_result = $stmt->get_result();
$stmt->close();

// Count Closed/Expired Jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE status IN ('Closed', 'Expired')");
$stmt->execute();
$closed_jobs_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get Closed/Expired Job Posts List
$stmt = $conn->prepare("
    SELECT jp.job_id, jp.job_title, jp.status, jp.close_reason, e.company_name, jp.created_at
    FROM job_postings jp JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.status IN ('Closed', 'Expired') ORDER BY jp.created_at DESC LIMIT 3
");
$stmt->execute();
$closed_jobs_result = $stmt->get_result();
$stmt->close();

// --- Chart Data Queries ---

// Application Status Distribution
$app_stats = ['Pending' => 0, 'Verified' => 0, 'Referred' => 0, 'Hired' => 0, 'Rejected' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM referrals_applications GROUP BY status");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $s = $row['status'];
    if (in_array($s, ['Pending', 'Pending_Docs'])) $app_stats['Pending'] += $row['count'];
    elseif ($s === 'Verified') $app_stats['Verified'] += $row['count'];
    elseif (in_array($s, ['Issue Referral Letter', 'Referral_Issued'])) $app_stats['Referred'] += $row['count'];
    elseif (in_array($s, ['Hired', 'Hired / Placed', 'Accepted', 'For Deployment'])) $app_stats['Hired'] += $row['count'];
    elseif (in_array($s, ['Rejected', 'Rejected / Not Qualified'])) $app_stats['Rejected'] += $row['count'];
}
$stmt->close();

// User Demographics
$user_stats = ['JobSeekers' => 0, 'Employers' => 0, 'ServiceProviders' => 0];
$stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users WHERE role IN ('JobSeeker', 'Employer', 'ServiceProvider') AND is_archived = 0 GROUP BY role");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    if($row['role'] == 'JobSeeker') $user_stats['JobSeekers'] = $row['count'];
    if($row['role'] == 'Employer') $user_stats['Employers'] = $row['count'];
    if($row['role'] == 'ServiceProvider') $user_stats['ServiceProviders'] = $row['count'];
}
$stmt->close();

// User Registrations Over Last 6 Months
$months_labels = [];
$registrations_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_val = date('Y-m', strtotime("-$i months"));
    $months_labels[] = date('M Y', strtotime("-$i months"));
    $registrations_data[$month_val] = 0;
}

$stmt = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users WHERE role IN ('JobSeeker', 'Employer', 'ServiceProvider') AND is_archived = 0 AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') GROUP BY month");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    if (isset($registrations_data[$row['month']])) {
        $registrations_data[$row['month']] = $row['count'];
    }
}
$stmt->close();
$reg_counts = array_values($registrations_data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PESO Admin Dashboard - Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; display: flex; min-height: 100vh; }
        
        /* Sidebar Styles */
        .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100%; transition: transform 0.3s ease; z-index: 50; top: 0; left: 0; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 1rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f3f4f6; }
        .brand { display: flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .brand img { height: 60px; width: 60px; object-fit: contain; }
        .brand-name { font-weight: 800; font-size: 1.1rem; color: #1e40af; letter-spacing: -0.01em; }
        .nav-menu { padding: 1rem 0.75rem; flex: 1; display: flex; flex-direction: column; gap: 0.15rem; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; text-decoration: none; color: #64748b; border-radius: 8px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: #f8fafc; color: #0f172a; border-left-color: #cbd5e1; }
        .nav-item.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; font-weight: 600; }
        .nav-icon svg { width: 1.1rem !important; height: 1.1rem !important; }
        
        /* Dropdown Nav Styles */
        .dropdown-menu { display: none; flex-direction: column; padding-left: 2.25rem; margin-top: 0.1rem; gap: 0.1rem; }
        .dropdown-menu.active { display: flex; }
        .dropdown-item { text-decoration: none; color: #64748b; font-size: 0.8rem; padding: 0.4rem 0.75rem; border-radius: 8px; transition: all 0.2s ease; }
        .dropdown-item:hover { color: #0f172a; background: #f8fafc; }
        .dropdown-item.active { color: #2563eb; font-weight: 600; }
        .dropdown-arrow { margin-left: auto; transition: transform 0.2s ease; display: flex; align-items: center; }
        .dropdown-arrow.open { transform: rotate(180deg); }

        .sidebar-badge { background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        
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
        .top-header { display: flex; justify-content: space-between; align-items: center; background: #1e40af; color: white; padding: 1rem 2rem; border-bottom: 1px solid #1e3a8a; position: sticky; top: 0; z-index: 40; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .hamburger { background: none; border: none; cursor: pointer; color: white; padding: 0.25rem; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: background 0.2s; }
        .hamburger:hover { background: rgba(255,255,255,0.1); }
        .content-wrapper { padding: 2rem 3rem; max-width: 1400px; margin: 0 auto; width: 100%; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }

        .welcome-card { background: white; padding: 1.25rem; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .welcome-card h1 { color: #1e40af; margin-bottom: 0.25rem; font-size: 1.5rem; }
        .welcome-card p { color: #6b7280; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #1e40af; }
        .stat-card.yellow { border-left-color: #fbbf24; }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card h3 { font-size: 1.5rem; color: #1e40af; margin-bottom: 0.25rem; line-height: 1; }
        .stat-card p { color: #6b7280; font-size: 0.8rem; line-height: 1.2; margin: 0; }
        .section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .section-title { font-size: 1.25rem; color: #1f2937; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .section-title h2 { font-size: 1.25rem; margin: 0; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.9rem; }
        .btn-primary { background: #1e40af; color: white; }
        .btn-primary:hover { background: #1e3a8a; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .application-card { border: 2px solid #e5e7eb; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; }
        .application-card h4 { color: #1f2937; margin-bottom: 0.5rem; }
        .application-card .info { color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem; }
        .application-card .actions { display: flex; gap: 1rem; }
        .btn svg { width: 1.2em; height: 1.2em; vertical-align: text-bottom; margin-right: 0.25rem; }
        
        /* Notification Styles */
        .notification-wrapper { position: relative; margin-right: 0.5rem; }
        .bell-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .bell-btn:hover { background: rgba(255,255,255,0.2); }
        .bell-icon { font-size: 1.25rem; }
        .badge-count { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; border: 2px solid #1e3a8a; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .notification-dropdown { position: absolute; top: 50px; right: 0; width: 320px; background: white; border-radius: 12px; box-shadow: 0 10px 25px -3px rgba(0,0,0,0.15), 0 4px 6px -2px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; z-index: 150; display: none; overflow: hidden; color: #1f2937; }
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
        
        /* Charts Styles */
        .charts-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .chart-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
        .chart-card h3 { color: #1f2937; font-size: 1.1rem; margin-bottom: 1.5rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.75rem; }
        .canvas-container { position: relative; height: 260px; width: 100%; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; width: 100%; }
            .content-wrapper { padding: 1rem; }
            .top-header { padding: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            html { font-size: 14px; }
            
            /* Mobile UI Adjustments */
            .section-title { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .section-title div[style*="display: flex"] { width: 100%; flex-direction: column !important; gap: 0.5rem !important; }
            .section-title div .btn { width: 100%; text-align: center; box-sizing: border-box; margin: 0 !important; }
            .section-title > a.btn { width: 100%; text-align: center; box-sizing: border-box; }
            .application-card .actions { flex-direction: column; }
            .application-card .actions a, .application-card .actions button { width: 100%; text-align: center; box-sizing: border-box; }
            .application-card > div[style*="justify-content: space-between"] { flex-direction: column !important; align-items: flex-start !important; gap: 0.5rem; }
            #reports > div[style*="display: grid"] { grid-template-columns: 1fr !important; }
            .notification-dropdown { position: fixed; top: 70px; right: 1rem; width: calc(100vw - 2rem); max-width: 320px; z-index: 1000; }
            .charts-wrapper { grid-template-columns: 1fr; }
        }
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
            <div class="brand">
                <img src="../BONGABON.png" alt="Logo">
                <span class="brand-name">PESO Admin</span>
            </div>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span>
                Dashboard
            </a>
            
            <div class="nav-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_job_approval.php', 'admin_service_approval.php', 'admin_view_job.php', 'admin_view_service.php'])) ? 'active' : ''; ?>" style="cursor: pointer;" onclick="toggleNavDropdown('approvalDropdown', 'approvalArrow')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></span>
                Post Approval
                <span class="dropdown-arrow <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_job_approval.php', 'admin_service_approval.php', 'admin_view_job.php', 'admin_view_service.php'])) ? 'open' : ''; ?>" id="approvalArrow"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg></span>
            </div>
            <div class="dropdown-menu <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_job_approval.php', 'admin_service_approval.php', 'admin_view_job.php', 'admin_view_service.php'])) ? 'active' : ''; ?>" id="approvalDropdown">
                <a href="admin_job_approval.php" class="dropdown-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_job_approval.php', 'admin_view_job.php'])) ? 'active' : ''; ?>">Job Approval</a>
                <a href="admin_service_approval.php" class="dropdown-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_service_approval.php', 'admin_view_service.php'])) ? 'active' : ''; ?>">Service Approval</a>
            </div>

              <div class="nav-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_employer_approval.php', 'admin_sp_approval.php'])) ? 'active' : ''; ?>" style="cursor: pointer;" onclick="toggleNavDropdown('registrationDropdown', 'registrationArrow')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></span>
                Registrations
                <span class="dropdown-arrow <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_employer_approval.php', 'admin_sp_approval.php'])) ? 'open' : ''; ?>" id="registrationArrow"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg></span>
            </div>
            <div class="dropdown-menu <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_employer_approval.php', 'admin_sp_approval.php'])) ? 'active' : ''; ?>" id="registrationDropdown">
                <a href="admin_employer_approval.php" class="dropdown-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_employer_approval.php'])) ? 'active' : ''; ?>">Employer Registration</a>
                <a href="admin_sp_approval.php" class="dropdown-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_sp_approval.php'])) ? 'active' : ''; ?>">Service Provider Registration</a>
            </div>

            <a href="job_matching_tab.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h18M16.5 3L21 7.5m0 0L16.5 12M21 7.5H3" /></svg></span>
                Job Matching
            </a>
            <a href="deployment_roster.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg></span>
                Deployments
            </a>
            <a href="admin_job_archive.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg></span>
                Job Archive
            </a>
            <a href="admin_reports.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg></span>
                Reports
            </a>
            <div class="nav-item <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_users_jobseekers.php', 'admin_users_employers.php', 'admin_users_providers.php', 'admin_users_archive.php'])) ? 'active' : ''; ?>" style="cursor: pointer;" onclick="toggleNavDropdown('usersDropdown', 'usersArrow')">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-7.5-2.962c.51.056 1.02.082 1.5.082a21.21 21.21 0 017.5 0c.51.056 1.02.082 1.5.082m-7.5-2.962a3.375 3.375 0 00-3-3.375m0 0c-1.125 0-2.156.386-2.962.962m2.962.962a3.375 3.375 0 013 3.375M3.75 18.72v-3.375c0-1.621 1.328-2.925 2.962-2.925m0 0a3.375 3.375 0 013-3.375m-3 3.375a3.375 3.375 0 00-3 3.375m6 .043c.51.056 1.02.082 1.5.082a21.21 21.21 0 017.5 0c.51.056 1.02.082 1.5.082M12 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span>
                Users
                <span class="dropdown-arrow <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_users_jobseekers.php', 'admin_users_employers.php', 'admin_users_providers.php', 'admin_users_archive.php'])) ? 'open' : ''; ?>" id="usersArrow"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg></span>
            </div>
            <div class="dropdown-menu <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_users_jobseekers.php', 'admin_users_employers.php', 'admin_users_providers.php', 'admin_users_archive.php'])) ? 'active' : ''; ?>" id="usersDropdown">
                <a href="admin_users_jobseekers.php" class="dropdown-item">Job Seekers</a>
                <a href="admin_users_employers.php" class="dropdown-item">Employers</a>
                <a href="admin_users_providers.php" class="dropdown-item">Service Providers</a>
                <a href="admin_users_archive.php" class="dropdown-item" style="color: #dc2626;">Archived Users</a>
            </div>
             <a href="notifications.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg></span>
                Notifications
                <?php if(isset($unread_count) && $unread_count > 0): ?>
                    <span class="sidebar-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_settings.php" class="nav-item">
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
            <div class="avatar">A</div>
            <div class="user-info">
                <div class="user-name">PESO Admin</div>
                <div class="user-role">Administrator</div>
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
                <h2 style="margin: 0; font-size: 1.25rem; color: white;">Admin Dashboard</h2>
            </div>
            
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
                                <?php 
                                    $n_link = 'admin_dashboard.php';
                                    if(in_array($notif['type'], ['job_post', 'job_close'])) $n_link = 'admin_view_job.php?job_id=' . $notif['reference_id'];
                                    elseif($notif['type'] === 'resignation') $n_link = 'resignation_reports.php';
                                    elseif(in_array($notif['type'], ['status_update', 'interview', 'hired', 'referral'])) $n_link = 'view_application.php?id=' . $notif['reference_id'];
                                ?>
                                <a href="<?php echo $n_link; ?>" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" onclick="markRead(<?php echo $notif['notification_id']; ?>)">
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
        
        <div class="content-wrapper page-transition">
        <div class="welcome-card">
            <h1>PESO Bongabon Dashboard</h1>
            <p>Manage job referrals and generate employment reports</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo format_number($total_job_seekers); ?></h3>
                <p>Total Job Seekers Helped</p>
            </div>
            <div class="stat-card green">
                <h3><?php echo format_number($successfully_referred); ?></h3>
                <p>Successfully Referred</p>
            </div>
            <div class="stat-card">
                <h3><?php echo format_number($active_job_posts); ?></h3>
                <p>Active Job Posts</p>
            </div>
            <div class="stat-card green">
                <h3><?php echo format_number($total_hired); ?></h3>
                <p>Total Hired</p>
            </div>
            <div class="stat-card yellow">
                <h3><?php echo format_number($pending_applications_count); ?></h3>
                <p>Pending Applications</p>
            </div>
            <div class="stat-card yellow">
                <h3><?php echo format_number($pending_employers_count); ?></h3>
                <p>Pending Employers</p>
            </div>
            <div class="stat-card yellow">
                <h3><?php echo format_number($pending_sp_count); ?></h3>
                <p>Pending Service Providers</p>
            </div>
            <div class="stat-card">
                <h3><?php echo format_number($total_referrals_issued); ?></h3>
                <p>Total Referral Letters Issued</p>
            </div>
        </div>

        <div class="charts-wrapper">
            <div class="chart-card">
                <h3>Application Status Overview</h3>
                <div class="canvas-container">
                    <canvas id="appStatusChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>Registered Users Demographics</h3>
                <div class="canvas-container">
                    <canvas id="userTypeChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>User Registrations (Last 6 Months)</h3>
                <div class="canvas-container">
                    <canvas id="registrationsChart"></canvas>
                </div>
            </div>
        </div>
        
    </main>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Remove Loader on Page Load
        window.addEventListener('load', function() {
            const loader = document.getElementById('loader-wrapper');
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => { loader.style.display = 'none'; }, 500);
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

        function toggleNavDropdown(dropdownId, arrowId) {
            document.getElementById(dropdownId).classList.toggle('active');
            if (arrowId) {
                document.getElementById(arrowId).classList.toggle('open');
            }
        }

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // App Status Chart (Bar)
            const appCtx = document.getElementById('appStatusChart').getContext('2d');
            new Chart(appCtx, {
                type: 'bar',
                data: {
                    labels: ['Pending Docs', 'Verified', 'Referred', 'Hired', 'Rejected'],
                    datasets: [{
                        label: 'Total Applications',
                        data: [
                            <?php echo $app_stats['Pending']; ?>,
                            <?php echo $app_stats['Verified']; ?>,
                            <?php echo $app_stats['Referred']; ?>,
                            <?php echo $app_stats['Hired']; ?>,
                            <?php echo $app_stats['Rejected']; ?>
                        ],
                        backgroundColor: [
                            'rgba(245, 158, 11, 0.8)', // Amber/Pending
                            'rgba(59, 130, 246, 0.8)', // Blue/Verified
                            'rgba(139, 92, 246, 0.8)', // Purple/Referred
                            'rgba(16, 185, 129, 0.8)', // Green/Hired
                            'rgba(239, 68, 68, 0.8)'   // Red/Rejected
                        ],
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { borderDash: [4, 4] } }, x: { grid: { display: false } } } }
            });

            // User Demographics Chart (Doughnut)
            const userCtx = document.getElementById('userTypeChart').getContext('2d');
            new Chart(userCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Job Seekers', 'Employers', 'Service Providers'],
                    datasets: [{
                        data: [ <?php echo $user_stats['JobSeekers']; ?>, <?php echo $user_stats['Employers']; ?>, <?php echo $user_stats['ServiceProviders']; ?> ],
                        backgroundColor: [ 'rgba(37, 99, 235, 0.8)', 'rgba(16, 185, 129, 0.8)', 'rgba(245, 158, 11, 0.8)' ],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
            });

            // User Registrations Chart (Line)
            const regCtx = document.getElementById('registrationsChart').getContext('2d');
            new Chart(regCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months_labels); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode($reg_counts); ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { borderDash: [4, 4] } }, x: { grid: { display: false } } } }
            });
        });
    </script>
</body>
</html>
