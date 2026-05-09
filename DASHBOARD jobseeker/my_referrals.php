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

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch applications with referral status
$query = "
    SELECT ra.*, jp.job_title, jp.employment_type, jp.location_id, jp.place_of_work, e.company_name, l.barangay, l.city_municipality
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN locations l ON jp.location_id = l.location_id
    WHERE ra.seeker_id = ? AND (ra.status = 'Issue Referral Letter' OR ra.status = 'Referral_Issued' OR ra.status = 'Hired' OR ra.status = 'Rejected')
";

if (!empty($start_date)) {
    $query .= " AND DATE(ra.created_at) >= ?";
}
if (!empty($end_date)) {
    $query .= " AND DATE(ra.created_at) <= ?";
}

$query .= " ORDER BY ra.created_at DESC";

$stmt = $conn->prepare($query);

if (!empty($start_date) && !empty($end_date)) {
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
} elseif (!empty($start_date)) {
    $stmt->bind_param("is", $user_id, $start_date);
} elseif (!empty($end_date)) {
    $stmt->bind_param("is", $user_id, $end_date);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Referrals - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header { padding: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f3f4f6; }
        .logo { height: 40px; width: 40px; }
        .brand-name { font-weight: 800; font-size: 1.25rem; color: #1e40af; letter-spacing: -0.01em; }
        .nav-menu { padding: 1.5rem 1rem; flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #64748b; border-radius: 8px; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: #f8fafc; color: #0f172a; border-left-color: #cbd5e1; }
        .nav-item.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; font-weight: 600; }
        .nav-icon { font-size: 1.25rem; }
        .user-profile { padding: 1rem; border-top: 1px solid #f3f4f6; display: flex; align-items: center; gap: 0.75rem; }
        .avatar { width: 40px; height: 40px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #6b7280; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.75rem; color: #94a3b8; font-weight: 500; }

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
        .status-referral { background: #FEF3C7; color: #D97706; }
        
        /* Hamburger & Mobile Sidebar */
        .hamburger { display: none; background: none; border: none; cursor: pointer; color: #1f2937; margin-right: 1rem; padding: 0; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }

        @media (max-width: 768px) {
            .sidebar { display: flex; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 50; width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .hamburger { display: block; }
            .main-content { margin-left: 0; }
            
            /* Mobile UI Adjustments */
            .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            form[action="my_referrals.php"] { flex-direction: column; align-items: stretch !important; }
            form[action="my_referrals.php"] > div { width: 100%; }
            form[action="my_referrals.php"] button { width: 100%; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../BONGABON.png" alt="Logo" class="logo">
            <span class="brand-name">PESO - BONGABON</span>
        </div>
        <nav class="nav-menu">
            <a href="jobseeker_dashboard.php" class="nav-item">
                <span class="nav-icon"></span> Dashboard
            </a>
            <a href="browse_jobs.php" class="nav-item">
                <span class="nav-icon"></span> Find Jobs
            </a>
            <a href="saved_jobs.php" class="nav-item">
                <span class="nav-icon"></span> Saved Jobs
            </a>
            <a href="my_applications.php" class="nav-item">
                <span class="nav-icon"></span> My Applications
            </a>
            <a href="my_referrals.php" class="nav-item active">
                <span class="nav-icon"></span> My Referrals
            </a>
            <a href="my_profile.php" class="nav-item">
                <span class="nav-icon"></span> My Profile
            </a>
            <a href="settings.php" class="nav-item">
                <span class="nav-icon"></span> Settings
            </a>
            <a href="../LOGIN%20SIGNUP/logout.php" class="nav-item" style="color: #ef4444; margin-top: auto;">
                <span class="nav-icon"></span> Logout
            </a>
        </nav>
        <div class="user-profile">
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
            <h1 class="page-title" style="margin-bottom: 0;">My Referrals</h1>
        </div>

        <form method="GET" action="my_referrals.php" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
            <div>
                <label for="start_date" style="display: block; font-size: 0.875rem; color: #4b5563; margin-bottom: 0.25rem;">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            <div>
                <label for="end_date" style="display: block; font-size: 0.875rem; color: #4b5563; margin-bottom: 0.25rem;">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            <button type="submit" style="background: #1e40af; color: white; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">Filter</button>
            <?php if(!empty($start_date) || !empty($end_date)): ?>
                <a href="my_referrals.php" style="color: #4b5563; text-decoration: none; padding: 0.5rem; align-self: center;">Clear</a>
            <?php endif; ?>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date Referred</th>
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
                                    <span class="status-badge status-referral"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'] ?? 'now')); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #6b7280;">You do not have any job referrals yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>
