<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in and is a Service Provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ServiceProvider') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch service provider details
$stmt = $conn->prepare("SELECT * FROM service_providers WHERE provider_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    session_destroy();
    header("Location: ../LOGIN%20SIGNUP/new_login.php?error=profile_not_found");
    exit();
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $skills_description = $_POST['skills_description'] ?? '';
    $base_rate = $_POST['base_rate'] ?? '';
    
    $update_stmt = $conn->prepare("UPDATE service_providers SET first_name = ?, last_name = ?, skills_description = ?, base_rate = ? WHERE provider_id = ?");
    $update_stmt->bind_param("ssssi", $first_name, $last_name, $skills_description, $base_rate, $user_id);
    
    if ($update_stmt->execute()) {
        $message = "<div style='background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;'>Profile updated successfully!</div>";
        $provider['first_name'] = $first_name; 
        $provider['last_name'] = $last_name;
        $provider['skills_description'] = $skills_description;
        $provider['base_rate'] = $base_rate;
    } else {
        $message = "<div style='background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca;'>Error updating profile.</div>";
    }
    $update_stmt->close();

    // Handle File Uploads for Certificates and Portfolios
    $upload_dir = '../uploads/service_providers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $files_to_upload = ['tesda_cert' => 'tesda_cert_path', 'portfolio' => 'portfolio_path'];
    foreach ($files_to_upload as $input_name => $db_column) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION));
            $new_filename = $user_id . '_' . $input_name . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_path)) {
                $conn->query("UPDATE service_providers SET $db_column = '$target_path' WHERE provider_id = $user_id");
                $provider[$db_column] = $target_path;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .sidebar-badge { background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 2rem; font-weight: 800; color: #111827; }
        
        .section { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 2rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-title { font-size: 1.25rem; font-weight: 700; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
        .btn-cancel { background: #e5e7eb; color: #374151; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-cancel:hover { background: #d1d5db; }
        .btn-danger-modal { background: #ef4444; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-danger-modal:hover { background: #dc2626; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../BONGABON.png" alt="Logo" class="logo">
            <span class="brand-name">PESO BONGABON</span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span> Dashboard
            </a>
            <a href="my_services.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg></span> My Services
            </a>
            <a href="bookings.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></span> Bookings
            </a>
            <a href="reviews.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg></span> Reviews
            </a>
            <a href="settings.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span> Profile Settings
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
                <?php echo strtoupper(substr($provider['first_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']); ?></div>
                <div class="user-role">Service Provider</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; margin-left: auto; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Profile Settings</h1>
        </div>

        <?php if ($provider['admin_verification_status'] === 'Verified'): ?>
            <?php echo $message; ?>
            <div class="section">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <h3 style="margin-bottom: 1rem; color: #1f2937; font-size: 1.1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Basic Information</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">Full Name</label>
                            <div style="display: flex; gap: 1rem;">
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($provider['first_name']); ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;" required>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($provider['last_name']); ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;" required>
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">Address</label>
                            <input type="text" value="<?php echo htmlspecialchars($provider['street_address'] . ', ' . $provider['barangay']); ?>" disabled style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; background: #f3f4f6; color: #6b7280; font-family: inherit;">
                        </div>
                    </div>

                    <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: #1f2937; font-size: 1.1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Verification Documents</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">Valid ID</label>
                            <?php if(!empty($provider['valid_id_path'])): ?>
                                <a href="<?php echo htmlspecialchars($provider['valid_id_path']); ?>" target="_blank" style="display: inline-block; padding: 0.5rem 1rem; background: #f3f4f6; color: #2563eb; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; border: 1px solid #e5e7eb;">👁 View Uploaded ID</a>
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 0.875rem; font-style: italic;">Not uploaded</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">Barangay Residency / Clearance</label>
                            <?php if(!empty($provider['brgy_residency_path'])): ?>
                                <a href="<?php echo htmlspecialchars($provider['brgy_residency_path']); ?>" target="_blank" style="display: inline-block; padding: 0.5rem 1rem; background: #f3f4f6; color: #2563eb; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; border: 1px solid #e5e7eb;">👁 View Uploaded Clearance</a>
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 0.875rem; font-style: italic;">Not uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: #1f2937; font-size: 1.1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Professional Profile</h3>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">Overall Skills Description</label>
                        <textarea name="skills_description" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; resize: vertical;" placeholder="Describe your overall experience and skills..."><?php echo htmlspecialchars($provider['skills_description'] ?? ''); ?></textarea>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">General Base Rate (Optional)</label>
                        <input type="text" name="base_rate" value="<?php echo htmlspecialchars($provider['base_rate'] ?? ''); ?>" placeholder="e.g., ₱500 per day or Depends on the job" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
                    </div>

                    <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: #1f2937; font-size: 1.1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Documents & Portfolio</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">TESDA NC II Certificate (Optional)</label>
                            <?php if(!empty($provider['tesda_cert_path'])): ?>
                                <div style="margin-bottom: 0.75rem;"><a href="<?php echo htmlspecialchars($provider['tesda_cert_path']); ?>" target="_blank" style="color: #10b981; text-decoration: none; font-size: 0.85rem; font-weight: 500;">✓ View Uploaded Certificate</a></div>
                            <?php endif; ?>
                            <input type="file" name="tesda_cert" accept=".jpg,.jpeg,.png,.pdf" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #4b5563; margin-bottom: 0.5rem;">Portfolio / Past Work (Optional)</label>
                            <?php if(!empty($provider['portfolio_path'])): ?>
                                <div style="margin-bottom: 0.75rem;"><a href="<?php echo htmlspecialchars($provider['portfolio_path']); ?>" target="_blank" style="color: #10b981; text-decoration: none; font-size: 0.85rem; font-weight: 500;">✓ View Uploaded Portfolio</a></div>
                            <?php endif; ?>
                            <input type="file" name="portfolio" accept=".jpg,.jpeg,.png,.pdf" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;">
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" style="background: #2563eb; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem;">Save Profile Settings</button>
                    </div>
                </form>
            </div>

        <!-- Danger Zone (Delete Account) -->
        <div class="section" style="border: 1px solid #fca5a5; background: #fef2f2;">
            <h3 style="color: #dc2626; font-size: 1.1rem; border-bottom: 1px solid #fecaca; padding-bottom: 0.5rem; margin-top: 0; margin-bottom: 1rem;">Danger Zone</h3>
            <h4 style="font-size: 1rem; margin-bottom: 0.5rem; color: #991b1b;">Delete Account</h4>
            <p style="color: #b91c1c; margin-bottom: 1.5rem; font-size: 0.9rem;">Once you delete your account, there is no going back. All your data and services will be archived.</p>
            <button type="button" onclick="document.getElementById('deleteModal').style.display='flex'" style="background: #ef4444; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%;">Delete Account</button>
        </div>

        <?php else: ?>
            <div class="section" style="text-align: center; padding: 3rem 1.5rem; background: #fffbeb; border-color: #fde68a;">
                <h3 style="color: #b45309; margin-bottom: 0.5rem;">Account Under Review</h3>
                <p style="color: #b45309;">Your account is pending verification. This feature is currently disabled.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="color: #dc2626; margin-bottom: 1rem; font-size: 1.25rem; margin-top: 0;">Delete Account?</h3>
            <p style="color: #4b5563; margin-bottom: 1.5rem;">Please enter your password to confirm account deletion. This action will archive your account.</p>
            <form method="POST">
                <input type="hidden" name="delete_account" value="1">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Password</label>
                    <input type="password" name="delete_password" required placeholder="Enter your password" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-danger-modal">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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