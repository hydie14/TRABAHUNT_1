<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all notifications
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$all_notifications = $notif_stmt->get_result();
$notif_stmt->close();

// Count unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
$unread_stmt->close();

// Fetch Employer Data for Profile
$emp_query = $conn->prepare("SELECT * FROM employers WHERE employer_id = ?");
$emp_query->bind_param("i", $user_id);
$emp_query->execute();
$employer = $emp_query->get_result()->fetch_assoc();
$emp_query->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History - Employer</title>
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

        h1 { color: #111827; margin-bottom: 1.5rem; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; }
        
        .notif-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; overflow: hidden; }
        .notif-item { padding: 1.5rem; border-bottom: 1px solid #f3f4f6; transition: background 0.2s; display: flex; gap: 1rem; align-items: flex-start; text-decoration: none; color: inherit; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f9fafb; }
        .notif-item.unread { background: #eff6ff; }
        .notif-item.unread:hover { background: #e0e7ff; }
        
        .notif-icon { width: 48px; height: 48px; background: #dbeafe; color: #1e40af; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-content { flex: 1; }
        .notif-title { font-weight: 600; font-size: 1rem; color: #1f2937; margin-bottom: 0.25rem; }
        .notif-message { color: #4b5563; line-height: 1.5; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .notif-time { font-size: 0.85rem; color: #9ca3af; }
        
        .empty-state { padding: 4rem 2rem; text-align: center; color: #6b7280; }
        
        .btn-back { display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: #4b5563; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: color 0.2s; }
        .btn-back:hover { color: #1e40af; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; width: 100%; }
            .content-wrapper { padding: 1rem; }
            .top-header { padding: 1rem; }
            .notif-item { flex-direction: column; padding: 1.25rem; }
            .notif-icon { width: 40px; height: 40px; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="employer_dashboard.php" class="brand">
                <img src="../BONGABON.png" alt="Logo">
                <span class="brand-name">PESO BONGABON EMPLOYER</span>
            </a>
        </div>
        <nav class="nav-menu">
            <a href="employer_dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span>
                Dashboard
            </a>
            <a href="employer_dashboard.php#jobs" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.075A2.25 2.25 0 0118 20.5H6A2.25 2.25 0 013.75 18.225V6.11A2.25 2.25 0 016 3.862h12a2.25 2.25 0 012.25 2.25v8.078zM15 3.862v1.714a2.25 2.25 0 01-2.25 2.25h-3.75A2.25 2.25 0 016.75 5.576V3.862" /></svg></span>
                My Job Posts
            </a>
            <a href="employer_dashboard.php#archive" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg></span>
                Job Archive
            </a>
            <a href="employer_dashboard.php#referrals" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></span>
                PESO Referrals
            </a>
            <a href="browse_services.php" id="nav-services" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M11.42 15.17L15.17 11.42m-3.75 3.75L3.75 21m6.938-9.938l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M6.938 11.062l-2.472 2.472a.375.375 0 000 .53l2.472 2.472m0 0l2.472-2.472" /></svg></span>
                Find Services
            </a>
            <a href="company_profile.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg></span>
                Company Profile
            </a>
            <a href="notifications.php" class="nav-item active">
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
                <h2 style="margin: 0; font-size: 1.25rem; color: #ffffff;">Notifications</h2>
            </div>
        </div>
        
        <div class="content-wrapper">
        <h1>Notification History</h1>
        
        <div class="notif-container">
            <?php if($all_notifications->num_rows > 0): ?>
                <?php while($notif = $all_notifications->fetch_assoc()): ?>
                    <a href="employer_dashboard.php#referrals" class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" onclick="fetch('../DASHBOARD jobseeker/mark_read.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'id=<?php echo $notif['notification_id']; ?>'})">
                        <div class="notif-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                        </div>
                        <div class="notif-content">
                            <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notif-time"><?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?></div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state"><h3>All caught up!</h3><p>You have no notifications yet.</p></div>
            <?php endif; ?>
        </div>
    </div>
    </main>
    <script>
        function toggleSidebar() {
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.toggle('active');
                document.getElementById('sidebarOverlay').classList.toggle('active');
            } else {
                document.getElementById('sidebar').classList.toggle('closed');
                document.getElementById('mainContent').classList.toggle('expanded');
            }
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