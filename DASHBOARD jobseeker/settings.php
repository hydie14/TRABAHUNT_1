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
    if (isset($_POST['change_password'])) {
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
    } elseif (isset($_POST['delete_account'])) {
        $delete_password = $_POST['delete_password'];
        
        // Verify password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (password_verify($delete_password, $hashed_password)) {
            $conn->begin_transaction();
            try {
                // Soft Delete user account (Archive instead of hard delete)
                // We keep user_contacts so they can be restored later
                $del_stmt = $conn->prepare("UPDATE users SET is_archived = 1, archived_at = NOW() WHERE user_id = ?");
                $del_stmt->bind_param("i", $user_id);
                $del_stmt->execute();
                $del_stmt->close();

                $conn->commit();
                session_unset();
                session_destroy();
                header("Location: ../LOGIN%20SIGNUP/new_login.php?msg=account_deleted");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error deleting account: " . $e->getMessage();
            }
        } else {
            $error = "Incorrect password. Cannot delete account.";
        }
    } elseif (isset($_POST['update_notifications'])) {
        $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET email_notifications = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $email_notif, $user_id);
        if ($stmt->execute()) {
            $success = "Notification preferences updated.";
        } else {
            $error = "Error updating preferences.";
        }
        $stmt->close();
    }
}

// Fetch user details for sidebar
$stmt = $conn->prepare("SELECT * FROM jobseekers WHERE seeker_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch current notification setting
$stmt = $conn->prepare("SELECT email_notifications FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($email_notif_setting);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PESO Bongabon</title>
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
        .user-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }
        .main-content { flex: 1; margin-left: 260px; padding: 3rem 2rem; }
        
        .form-card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #eaeaea; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-primary { background: #2563eb; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.2s ease; box-shadow: 0 2px 4px -1px rgba(37, 99, 235, 0.1); }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }

        .btn-danger { background: #ef4444; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-danger:hover { background: #dc2626; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
        .btn-cancel { background: #e5e7eb; color: #374151; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-cancel:hover { background: #d1d5db; }

        /* Switch Toggle */
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #1e40af; }
        input:focus + .slider { box-shadow: 0 0 1px #1e40af; }
        input:checked + .slider:before { transform: translateX(26px); }

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
            .form-card { padding: 1.5rem; width: 100%; box-sizing: border-box; }
            .modal-content { padding: 1.5rem; width: 95%; }
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
            <a href="saved_jobs.php" class="nav-item">
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
            <a href="settings.php" class="nav-item active">
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

    <main class="main-content">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <div style="display: flex; align-items: center; margin-bottom: 1.5rem;">
            <button class="hamburger" onclick="toggleSidebar()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
            <h2 style="margin-bottom: 0; font-size: 2.25rem; font-weight: 800; color: #111827; letter-spacing: -0.02em;">Settings</h2>
        </div>
        
        <?php if($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

        <!-- Security Settings -->
        <div class="form-card" style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Security</h3>
            <h4 style="font-size: 1rem; margin-bottom: 1rem; color: #374151;">Change Password</h4>
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required minlength="8"></div>
                
                <button type="submit" class="btn-primary">Update Password</button>
            </form>
        </div>

        <!-- Notification Settings -->
        <div class="form-card" style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #1f2937; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Notifications</h3>
            <form method="POST">
                <input type="hidden" name="update_notifications" value="1">
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <label style="margin-bottom: 0;">Email Notifications</label>
                        <p style="font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem;">Receive emails about job referrals and application updates.</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="email_notifications" <?php echo $email_notif_setting ? 'checked' : ''; ?> onchange="this.form.submit()">
                        <span class="slider round"></span>
                    </label>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="form-card" style="border: 1px solid #fca5a5;">
            <h3 style="font-size: 1.25rem; margin-bottom: 1rem; color: #dc2626; border-bottom: 1px solid #fca5a5; padding-bottom: 0.5rem;">Danger Zone</h3>
            <h4 style="font-size: 1rem; margin-bottom: 0.5rem; color: #374151;">Delete Account</h4>
            <p style="color: #6b7280; margin-bottom: 1.5rem; font-size: 0.9rem; line-height: 1.5;">
                Once you delete your account, there is no going back. All your data, including applications and profile information, will be permanently removed.
            </p>
            <button onclick="document.getElementById('deleteModal').style.display='flex'" class="btn-danger">Delete Account</button>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="color: #dc2626; margin-bottom: 1rem;">Delete Account?</h3>
            <p style="color: #4b5563; margin-bottom: 1.5rem;">Please enter your password to confirm account deletion. This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="delete_account" value="1">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="delete_password" class="form-control" required placeholder="Enter your password">
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
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
    </script>
</body>
</html>