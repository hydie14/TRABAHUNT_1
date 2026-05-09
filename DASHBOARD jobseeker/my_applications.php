<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in and is a Job Seeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details for sidebar
$stmt = $conn->prepare("SELECT * FROM jobseekers WHERE seeker_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Count unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
$unread_stmt->close();

// Fetch applications
$query = "
    SELECT ra.*, jp.job_title, jp.employment_type, jp.location_id, jp.place_of_work, e.company_name, l.barangay, l.city_municipality, ra.deployment_date, ra.deployment_message
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN locations l ON jp.location_id = l.location_id
    WHERE ra.seeker_id = ?
    ORDER BY ra.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header { padding: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #f3f4f6; }
        .logo { height: 60px; width: 60px; object-fit: contain; }
        .brand-name { font-weight: 800; font-size: 1.1rem; color: #1e40af; letter-spacing: -0.01em; }
        .nav-menu { padding: 1rem 0.75rem; flex: 1; display: flex; flex-direction: column; gap: 0.15rem; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; text-decoration: none; color: #64748b; border-radius: 8px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: #f8fafc; color: #0f172a; border-left-color: #cbd5e1; }
        .nav-item.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; font-weight: 600; }
        .nav-icon svg { width: 1.1rem !important; height: 1.1rem !important; }
        
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

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 3rem 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 2.25rem; font-weight: 800; color: #111827; margin-bottom: 0.25rem; letter-spacing: -0.02em; line-height: 1.2; }
        
        /* Table Styles */
        .table-container { background: white; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #eaeaea; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; }
        tr:last-child td { border-bottom: none; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #d1fae5; color: #059669; }
        .status-verified { background: #dbeafe; color: #1e40af; }
        .status-referral { background: #e0e7ff; color: #4338ca; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        
        /* Hamburger & Mobile Sidebar */
        .hamburger { display: none; background: none; border: none; cursor: pointer; color: #1f2937; margin-right: 1rem; padding: 0; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }

        @media (max-width: 768px) {
            .sidebar { display: flex; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 50; width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .hamburger { display: block; }
            .main-content { margin-left: 0; }
            html { font-size: 14px; }
            .page-title { font-size: 1.5rem; }
            
            /* Mobile UI Adjustments */
            .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .btn-action { width: 100%; text-align: center; box-sizing: border-box; }
            .modal-content { padding: 1.5rem; width: 95%; }
        }

        /* New Status Styles */
        .status-message { font-size: 0.8rem; color: #6b7280; margin-top: 4px; display: block; }
        .btn-action { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; text-decoration: none; margin-top: 8px; cursor: pointer; border: 1px solid transparent; font-weight: 500; transition: all 0.2s; }
        .btn-download { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-download:hover { background: #dbeafe; }
        .btn-resign { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
        .btn-resign:hover { background: #ffe4e6; }
        
        .status-referral { background: #f3e8ff; color: #6b21a8; } /* Purple */
        .status-hired { background: #dcfce7; color: #166534; } /* Green */
        .status-resigned { background: #f3f4f6; color: #4b5563; } /* Grey */
        .status-terminated { background: #fee2e2; color: #991b1b; } /* Red */

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../BONGABON.png" alt="Logo" class="logo">
            <span class="brand-name">PESO BONGABON</span>
        </div>
        <nav class="nav-menu">
            <a href="jobseeker_dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span> 
                Dashboard
            </a>
            <a href="browse_jobs.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></span> 
                Find Jobs
            </a>
            <a href="browse_services.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M11.42 15.17L15.17 11.42m-3.75 3.75L3.75 21m6.938-9.938l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M6.938 11.062l-2.472 2.472a.375.375 0 000 .53l2.472 2.472m0 0l2.472-2.472" /></svg></span> 
                Find Services
            </a>
            <a href="saved_jobs.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg></span> 
                Saved Jobs
            </a>
            <a href="my_applications.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg></span> 
                My Applications
            </a>
            <a href="my_profile.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg></span> 
                My Profile
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
                <a href="../LOGIN%20SIGNUP/logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg> 
                    Logout
                </a>
            </div>
            <div class="avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="user-role">Job Seeker</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; margin-left: auto; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <div class="page-header" style="display: flex; align-items: center;">
            <button class="hamburger" onclick="toggleSidebar()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            <h1 class="page-title" style="margin-bottom: 0;">My Applications</h1>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date Applied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="text-transform: capitalize;"><?php echo htmlspecialchars($row['job_title']); ?></td>
                                <td style="text-transform: capitalize;"><?php echo htmlspecialchars($row['company_name']); ?></td>
                                <td>
                                    <?php 
                                        if(!empty($row['barangay'])) echo htmlspecialchars($row['barangay'] . ', ' . $row['city_municipality']);
                                        elseif(!empty($row['place_of_work'])) echo htmlspecialchars($row['place_of_work']);
                                        else echo 'N/A';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['employment_type']); ?></td>
                                <td>
                                    <?php 
                                        $status = $row['status'];
                                        $statusClass = '';
                                        $statusText = $status;
                                        $msg = '';
                                        $action = '';

                                        if($status == 'Pending' || $status == 'Pending_Docs') {
                                            $statusClass = 'status-pending'; // Yellow
                                            $statusText = 'Pending Docs';
                                            $msg = 'Please visit PESO office to submit hardcopy requirements.';
                                        } elseif($status == 'Verified') {
                                            $statusClass = 'status-verified'; // Blue
                                            $statusText = 'Verified';
                                            $msg = 'Your documents are verified. Waiting for PESO Referral.';
                                        } elseif($status == 'Referral_Issued' || $status == 'Issue Referral Letter') {
                                            $statusClass = 'status-referral'; // Purple
                                            $statusText = 'Referred';
                                            $msg = 'Please check your notifications/email for your schedule to visit the PESO office and claim your referral letter.';
                                            $action = '';
                                        } elseif($status == 'Pending Interview') {
                                            $statusClass = 'status-pending'; // Yellow
                                            $statusText = 'Interview Scheduled';
                                            $msg = 'The employer has scheduled you for an interview.';
                                            if (!empty($row['interview_date'])) {
                                                $msg .= '<br><span style="color: #92400e; font-weight: 600; display: inline-block; margin-top: 4px;">' . date('F d, Y - h:i A', strtotime($row['interview_date'])) . '</span>';
                                                if (!empty($row['interview_message'])) {
                                                    $msg .= '<br><span style="color: #4b5563; font-style: italic; display: inline-block; margin-top: 2px;">"' . htmlspecialchars($row['interview_message']) . '"</span>';
                                                }
                                            }
                                            $msg .= '<br><span style="color: #1e40af; font-size: 0.8rem; margin-top: 4px; display: block; font-weight: 600;">Don\'t forget to claim your signed Referral Letter at the PESO office before going.</span>';
                                            $action = '';
                                        } elseif($status == 'Hired' || $status == 'Hired / Placed') {
                                            $statusClass = 'status-hired'; // Green
                                            $statusText = 'Hired';
                                            $msg = 'Congratulations! You are hired.';
                                            $action = '<button type="button" class="btn-action btn-resign" onclick="openResignModal('.$row['application_id'].')">Report Resignation/Termination</button>';
                                        } elseif($status == 'For Deployment') {
                                            $statusClass = 'status-verified'; // Blue
                                            $statusText = 'For Deployment';
                                            $msg = 'You have been scheduled for deployment.';
                                            if (!empty($row['deployment_date'])) {
                                                $msg .= '<br><span style="color: #4338ca; font-weight: 600; display: inline-block; margin-top: 4px;">' . date('F d, Y - h:i A', strtotime($row['deployment_date'])) . '</span>';
                                                if (!empty($row['deployment_message'])) {
                                                    $msg .= '<br><span style="color: #4b5563; font-style: italic; display: inline-block; margin-top: 2px;">"' . htmlspecialchars($row['deployment_message']) . '"</span>';
                                                }
                                            }
                                            $msg .= '<br><span style="color: #166534; font-size: 0.8rem; margin-top: 4px; display: block; font-weight: 600;">Congratulations on your upcoming deployment!</span>';
                                            $action = '<button type="button" class="btn-action btn-resign" onclick="openResignModal('.$row['application_id'].')">Report Resignation/Termination</button>';
                                        } elseif($status == 'Pending_Resignation') {
                                            $statusClass = 'status-pending'; // Yellow
                                            $statusText = 'Pending Resignation';
                                            $msg = 'Your resignation is currently being reviewed by PESO.';
                                        } elseif($status == 'Rejected' || $status == 'Rejected / Not Qualified') {
                                            $statusClass = 'status-rejected'; // Red
                                            $statusText = 'Rejected';
                                            $msg = 'Application declined by employer.';
                                        } elseif($status == 'Cancelled') {
                                            $statusClass = 'status-resigned'; // Grey
                                            $statusText = 'Cancelled';
                                            $msg = 'Auto-cancelled because you were hired for a full-time role elsewhere.';
                                        } elseif($status == 'Resigned') {
                                            $statusClass = 'status-resigned'; // Grey
                                            $statusText = 'Resigned';
                                            $msg = 'You have reported resignation.';
                                        } elseif($status == 'Terminated') {
                                            $statusClass = 'status-terminated'; // Dark Red
                                            $statusText = 'Terminated';
                                            $msg = 'Employment terminated.';
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span>
                                    <?php if($msg): ?><span class="status-message"><?php echo $msg; ?></span><?php endif; ?>
                                    <?php echo $action; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'] ?? 'now')); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #6b7280;">You haven't applied to any jobs yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Resignation Modal -->
        <div id="resignModal" class="modal">
            <div class="modal-content">
                <h3 style="color: #1f2937; margin-bottom: 1rem;">Report Resignation/Termination</h3>
                <p style="color: #6b7280; margin-bottom: 1.5rem; font-size: 0.9rem;">Are you sure you want to report that you are no longer employed in this job?</p>
                <form action="report_resignation.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="app_id" id="modalAppId">
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Reason <span style="color: red;">*</span></label>
                    <input type="text" name="end_reason" required maxlength="50" oninput="document.getElementById('reason_counter').textContent = this.value.length + '/50'" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;" placeholder="e.g. Resigned, End of Contract, Health Issues, etc.">
                    <div id="reason_counter" style="text-align: right; font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">0/50</div>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Additional Remarks / Formal Message <span style="color: red;">*</span></label>
                        <textarea name="remarks" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; resize: vertical;" placeholder="You must state your reason or leave a formal message to the employer here..." required></textarea>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem; color: #374151;">Upload Proof (Resignation/Termination Letter) <span style="color: red;">*</span></label>
                        <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;">
                        <span style="font-size: 0.75rem; color: #6b7280; display: block; margin-top: 4px;">Accepted formats: JPG, PNG, PDF (Max 5MB)</span>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('resignModal').style.display='none'" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                        <button type="submit" style="padding: 0.5rem 1rem; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script>
        function openResignModal(appId) {
            document.getElementById('modalAppId').value = appId;
            document.getElementById('resignModal').style.display = 'flex';
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }

        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const profile = document.querySelector('.user-profile');
            if (profile && !profile.contains(event.target)) {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) dropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>