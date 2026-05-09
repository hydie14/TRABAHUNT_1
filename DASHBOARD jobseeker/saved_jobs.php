<?php
session_start();
include '../DATABASE/db_connect.php';

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

// Check if user is currently hired full-time
$check_emp = $conn->prepare("
    SELECT jp.employment_type 
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    WHERE ra.seeker_id = ? 
    AND ra.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment', 'Pending_Resignation')
");
$check_emp->bind_param("i", $user_id);
$check_emp->execute();
$emp_result = $check_emp->get_result();

$is_currently_hired = false;
$part_time_count = 0;
while ($row = $emp_result->fetch_assoc()) {
    if (in_array($row['employment_type'], ['Permanent', 'Contractual'])) {
        $is_currently_hired = true;
    } else {
        $part_time_count++;
    }
}
$check_emp->close();
if ($part_time_count >= 2) {
    $is_currently_hired = true;
}

// Fetch saved jobs
$stmt = $conn->prepare("
    SELECT jp.*, e.company_name, l.barangay, l.city_municipality
    FROM saved_jobs sj
    JOIN job_postings jp ON sj.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN locations l ON jp.location_id = l.location_id
    WHERE sj.seeker_id = ?
    ORDER BY sj.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$jobs_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs - PESO Bongabon</title>
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
        .user-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 3rem 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 2.25rem; font-weight: 800; color: #111827; margin-bottom: 0.25rem; letter-spacing: -0.02em; line-height: 1.2; }
        .page-subtitle { color: #64748b; font-size: 1.05rem; }
        
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .card { background: white; padding: 1.75rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #eaeaea; transition: all 0.3s ease; position: relative; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.04); }
        .card h3 { color: #0f172a; margin-bottom: 0.5rem; font-size: 1.25rem; font-weight: 700; padding-right: 1.5rem; }
        
        .btn-apply { display: inline-flex; align-items: center; justify-content: center; background-color: #2563eb; color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; transition: all 0.2s ease; margin-top: auto; align-self: flex-start; }
        .btn-apply:hover { background-color: #1d4ed8; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2), 0 2px 4px -1px rgba(37, 99, 235, 0.1); transform: translateY(-1px); }
        .btn-disabled { display: inline-flex; align-items: center; justify-content: center; background-color: #f8fafc; color: #94a3b8; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; margin-top: auto; align-self: flex-start; cursor: not-allowed; border: 1px solid #e2e8f0; }
        
        .remove-btn { position: absolute; top: 1rem; right: 1rem; background: none; border: none; cursor: pointer; font-size: 1.25rem; color: #ef4444; }
        .remove-btn:hover { transform: scale(1.1); }

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
            .dashboard-grid { grid-template-columns: 1fr !important; }
            .btn-apply, .btn-disabled { width: 100%; box-sizing: border-box; text-align: center; }
            .page-title { font-size: 1.75rem; }
        }
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
            <a href="saved_jobs.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg></span> 
                Saved Jobs
            </a>
            <a href="my_applications.php" class="nav-item">
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
            <div>
                <h1 class="page-title">Saved Jobs</h1>
                <p class="page-subtitle">Jobs you have bookmarked for later.</p>
            </div>
        </div>

        <div class="dashboard-grid">
        <?php while($job = $jobs_result->fetch_assoc()): ?>
        <div class="card">
            <h3 style="text-transform: capitalize;"><?php echo htmlspecialchars($job['job_title']); ?></h3>
            <p style="margin-bottom: 0.5rem; font-size: 1rem; text-transform: capitalize;"><a href="view_employer.php?id=<?php echo $job['employer_id']; ?>" style="color: #2563eb; font-weight: 600; text-decoration: none;" title="View Company Profile"><?php echo htmlspecialchars($job['company_name']); ?></a></p>
            <p style="font-size: 0.9rem; margin-bottom: 1rem; color: #475569; line-height: 1.5;">
                <?php 
                    if(!empty($job['barangay'])) echo htmlspecialchars($job['barangay'] . ', ' . $job['city_municipality']);
                    elseif(!empty($job['place_of_work'])) echo htmlspecialchars($job['place_of_work']);
                    else echo 'N/A';
                ?><br>
                <span style="background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; display: inline-flex; font-weight: 600; margin-top: 0.75rem;"><?php echo htmlspecialchars($job['employment_type']); ?></span>
            </p>
            
            <?php if ($is_currently_hired): ?>
                <span class="btn-disabled" title="You have reached the maximum active jobs allowed. Please report resignation to apply for new jobs.">Apply Now (Disabled)</span>
            <?php else: ?>
                <a href="apply_job.php?job_id=<?php echo $job['job_id']; ?>" class="btn-apply">Apply Now &rarr;</a>
            <?php endif; ?>
            
            <button onclick="removeSavedJob(<?php echo $job['job_id']; ?>, this)" class="remove-btn" title="Remove from Saved">✕</button>
        </div>
        <?php endwhile; ?>
        </div>

        <?php if($jobs_result->num_rows == 0): ?>
            <p>You haven't saved any jobs yet.</p>
        <?php endif; ?>
    </main>

    <script>
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

        function removeSavedJob(jobId, btn) {
            if(!confirm('Remove this job from saved list?')) return;
            
            fetch('toggle_save_job.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: jobId })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    btn.closest('.card').remove();
                    if(document.querySelectorAll('.card').length === 0) {
                        document.querySelector('.dashboard-grid').innerHTML = '<p>You haven\'t saved any jobs yet.</p>';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>