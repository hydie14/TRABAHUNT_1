<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($current_password, $hashed_password)) {
            // Update to new password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update_stmt->bind_param("si", $new_hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error updating password. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "Incorrect current password.";
        }
    }
}

// Fetch user details for sidebar
$stmt = $conn->prepare("SELECT * FROM jobseekers WHERE seeker_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; display: flex; min-height: 100vh; }
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
        .main-content { flex: 1; margin-left: 260px; padding: 3rem 2rem; }
        
        .form-card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #eaeaea; max-width: 500px; margin: 0 auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-primary { background: #2563eb; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.2s ease; box-shadow: 0 2px 4px -1px rgba(37, 99, 235, 0.1); }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .btn-secondary { background: white; color: #374151; padding: 0.75rem 1.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; width: 100%; margin-top: 0.5rem; }
        .btn-secondary:hover { background: #f9fafb; }
        
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }

        /* Hamburger & Mobile Sidebar */
        .hamburger { display: none; background: none; border: none; cursor: pointer; color: #1f2937; margin-right: 1rem; padding: 0; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }

        @media (max-width: 768px) {
            .sidebar { display: flex; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 50; width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .hamburger { display: block; }
            .main-content { margin-left: 0; }
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
            <a href="jobseeker_dashboard.php" class="nav-item">Dashboard</a>
            <a href="browse_jobs.php" class="nav-item">Find Jobs</a>
            <a href="saved_jobs.php" class="nav-item">Saved Jobs</a>
            <a href="my_applications.php" class="nav-item">My Applications</a>
            <a href="my_profile.php" class="nav-item active">My Profile</a>
            <a href="../LOGIN%20SIGNUP/logout.php" class="nav-item" style="color: #ef4444; margin-top: auto;">Logout</a>
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

    <main class="main-content">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <button class="hamburger" onclick="toggleSidebar()" style="margin-bottom: 1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
        <div class="form-card">
            <h2 style="margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: 700;">Change Password</h2>
            
            <?php if($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required minlength="8"></div>
                
                <button type="submit" class="btn-primary">Update Password</button>
                <a href="my_profile.php" class="btn-secondary">Cancel</a>
            </form>
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