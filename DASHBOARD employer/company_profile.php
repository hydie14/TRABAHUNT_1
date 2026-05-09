<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

$employer_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle File Uploads (Profile & Cover Photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['profile_pic']) || isset($_FILES['cover_photo']))) {
    $upload_dir = "../UPLOADS/employer_profiles/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    
    // Profile Picture Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_ext) && $_FILES['profile_pic']['size'] <= 5242880) {
            $new_filename = "emp_profile_" . $employer_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $stmt = $conn->prepare("UPDATE employers SET profile_picture = ? WHERE employer_id = ?");
                $stmt->bind_param("si", $target_file, $employer_id);
                $stmt->execute();
                $success = "Profile picture updated!";
            }
        } else {
            $error = "Invalid file type or size exceeds 5MB.";
        }
    }
    
    // Cover Photo Upload
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_ext) && $_FILES['cover_photo']['size'] <= 5242880) {
            $new_filename = "emp_cover_" . $employer_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $target_file)) {
                $stmt = $conn->prepare("UPDATE employers SET cover_photo = ? WHERE employer_id = ?");
                $stmt->bind_param("si", $target_file, $employer_id);
                $stmt->execute();
                $success = "Cover photo updated!";
            }
        } else {
            $error = "Invalid file type or size exceeds 5MB.";
        }
    }
    header("Location: company_profile.php");
    exit();
}

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $company_name = trim($_POST['company_name']);
    $trade_name = trim($_POST['trade_name']);
    $acronym = strtoupper(trim($_POST['acronym']));
    $employer_type = $_POST['employer_type'];
    $employer_subtype = trim($_POST['employer_subtype']);
    $office_type = $_POST['office_type'];
    $total_work_force = $_POST['total_work_force'];
    $business_line_id = (int)$_POST['business_line_id'];
    $tin_number = trim($_POST['tin_number']);
    $street_address = trim($_POST['street_address']);
    $location_id = (int)$_POST['location_id'];
    $owner_name = trim($_POST['owner_name']);
    $contact_person = trim($_POST['contact_person_name']);
    $position = trim($_POST['contact_person_position']);
    $telephone = trim($_POST['telephone_number']);
    $mobile = trim($_POST['mobile_number']);
    $fax = trim($_POST['fax_number']);
    $email = trim($_POST['email_address']);
    $company_description = trim($_POST['company_description']);

    $stmt = $conn->prepare("UPDATE employers SET company_name=?, trade_name=?, acronym=?, employer_type=?, employer_subtype=?, office_type=?, total_work_force=?, business_line_id=?, tin_number=?, street_address=?, location_id=?, owner_name=?, contact_person_name=?, contact_person_position=?, telephone_number=?, mobile_number=?, fax_number=?, email_address=?, company_description=? WHERE employer_id=?");
    $stmt->bind_param("sssssssisssssssssssi", $company_name, $trade_name, $acronym, $employer_type, $employer_subtype, $office_type, $total_work_force, $business_line_id, $tin_number, $street_address, $location_id, $owner_name, $contact_person, $position, $telephone, $mobile, $fax, $email, $company_description, $employer_id);

    if ($stmt->execute()) {
        $success = "Company profile updated successfully.";
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

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
$employer = $employer_query->get_result()->fetch_assoc();
$employer_query->close();

$locations = $conn->query("SELECT * FROM locations ORDER BY city_municipality, barangay");
$business_lines = $conn->query("SELECT * FROM business_lines ORDER BY business_name");

$formatted_address = implode(', ', array_filter([$employer['street_address'] ?? '', $employer['barangay'] ?? '', $employer['city_municipality'] ?? '', $employer['province'] ?? '']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .hamburger { display: flex; background: none; border: none; cursor: pointer; color: white; padding: 0.25rem; align-items: center; justify-content: center; border-radius: 6px; transition: background 0.2s; }
        .hamburger:hover { background: rgba(255,255,255,0.1); }
        .content-wrapper { padding: 2rem 3rem; max-width: 1000px; margin: 0 auto; width: 100%; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }
        
        /* Facebook-like Profile Styles */
        .profile-header-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 2rem; border: 1px solid #e5e7eb; position: relative; }
        .profile-cover { height: 250px; background: #e5e7eb; position: relative; background-size: cover; background-position: center; border-bottom: 1px solid #e5e7eb; }
        .profile-avatar-container { margin-top: -70px; margin-left: 2rem; position: relative; z-index: 10; width: 140px; }
        .profile-avatar { width: 140px; height: 140px; border-radius: 50%; border: 4px solid white; background: white; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #1e40af; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .upload-btn { position: absolute; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s; border: none; }
        .upload-btn:hover { background: rgba(0,0,0,0.8); }
        .btn-cover { bottom: 1rem; right: 1rem; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; gap: 0.5rem; }
        .btn-avatar { bottom: 5px; right: 5px; width: 36px; height: 36px; border: 2px solid white; }
        
        .profile-info { padding: 1rem 2rem 1.5rem 2rem; text-align: left; }
        .profile-info h1 { font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 0.25rem; }
        .profile-info p { color: #6b7280; font-size: 1rem; margin-bottom: 1rem; }
        .btn-edit { background: #f3f4f6; color: #1f2937; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: 1px solid #d1d5db; display: inline-flex; align-items: center; gap: 0.5rem; transition: background 0.2s; }
        .btn-edit:hover { background: #e5e7eb; }

        /* Info Grid */
        .info-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; margin-bottom: 1.5rem; }
        .info-card h3 { color: #1e40af; font-size: 1.15rem; margin-bottom: 1.5rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .info-item { margin-bottom: 1rem; }
        .info-label { font-size: 0.8rem; text-transform: uppercase; color: #6b7280; font-weight: 600; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; color: #1f2937; font-weight: 500; }

        /* Form Styles */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; }
        .btn-primary { background: #1e40af; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-primary:hover { background: #1d4ed8; }
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500;}
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;}
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;}
        
        /* Modal Styles for Image Preview */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: white; padding: 1.5rem; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); text-align: center; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .main-content { margin-left: 0; width: 100%; }
            .content-wrapper { padding: 1rem; }
            .profile-avatar-container { margin-left: auto; margin-right: auto; display: flex; justify-content: center; }
            .profile-info { padding-top: 1rem; text-align: center; }
            .profile-info div[style*="display: flex"] { flex-direction: column; align-items: center !important; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="employer_dashboard.php" class="brand">
                <img src="../BONGABON.png" alt="Logo">
                <span class="brand-name">PESO BONGABON EMPLOYER</span>
            </a>
        </div>
        <nav class="nav-menu">
            <a href="employer_dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span> Dashboard
            </a>
            <a href="employer_dashboard.php#jobs" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.075A2.25 2.25 0 0118 20.5H6A2.25 2.25 0 013.75 18.225V6.11A2.25 2.25 0 016 3.862h12a2.25 2.25 0 012.25 2.25v8.078zM15 3.862v1.714a2.25 2.25 0 01-2.25 2.25h-3.75A2.25 2.25 0 016.75 5.576V3.862" /></svg></span> My Job Posts
            </a>
            <a href="employer_dashboard.php#archive" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg></span> Job Archive
            </a>
            <a href="employer_dashboard.php#referrals" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></span> PESO Referrals
            </a>
            <a href="browse_services.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M11.42 15.17L15.17 11.42m-3.75 3.75L3.75 21m6.938-9.938l2.472-2.472a.375.375 0 000-.53l-2.472-2.472M6.938 11.062l-2.472 2.472a.375.375 0 000 .53l2.472 2.472m0 0l2.472-2.472" /></svg></span> Find Services
            </a>
            <a href="company_profile.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg></span> Company Profile
            </a>
            <a href="notifications.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg></span> Notifications
            </a>
            <a href="settings.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71-.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span> Settings
            </a>
        </nav>
        <div class="user-profile" onclick="toggleProfileDropdown()">
            <div class="profile-dropdown" id="profileDropdown">
                <a href="../LOGIN SIGNUP/logout.php"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg> Logout</a>
            </div>
            <div class="avatar">
                <?php if(!empty($employer['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($employer['profile_picture']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?php echo strtoupper(substr($employer['company_name'], 0, 1)); ?>
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
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
                <h2 style="margin: 0; font-size: 1.25rem;">Company Profile</h2>
            </div>
        </div>

        <div class="content-wrapper">
            <?php if($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header-card">
                <div class="profile-cover" style="<?php echo !empty($employer['cover_photo']) ? 'background-image: url('.htmlspecialchars($employer['cover_photo']).');' : ''; ?>">
                    <form method="POST" enctype="multipart/form-data" id="coverForm">
                        <label for="cover_photo" class="upload-btn btn-cover" title="Change Cover Photo">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.2rem;height:1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" /></svg>
                            Edit Cover
                        </label>
                        <input type="file" name="cover_photo" id="cover_photo" accept="image/*" style="display:none;" onchange="showImagePreview(this, 'coverForm')">
                    </form>
                </div>
                
                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if(!empty($employer['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($employer['profile_picture']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($employer['company_name'], 0, 1)); ?>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" id="avatarForm">
                            <label for="profile_pic" class="upload-btn btn-avatar" title="Change Profile Picture">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.2rem;height:1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" /></svg>
                            </label>
                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*" style="display:none;" onchange="showImagePreview(this, 'avatarForm')">
                        </form>
                    </div>
                </div>

                <div class="profile-info">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h1><?php echo htmlspecialchars($employer['company_name']); ?></h1>
                            <p>🏢 <?php echo htmlspecialchars($employer['business_name'] ?? 'Business Line'); ?> | 📍 <?php echo htmlspecialchars($employer['city_municipality'] ?? 'Location'); ?></p>
                        </div>
                        <button class="btn-edit" onclick="toggleEditMode()">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.2rem;height:1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                            Edit Profile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Read-Only View -->
            <div id="profile-view">
                <div class="info-card">
                    <h3><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg> Company Details</h3>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Trade Name</div><div class="info-value"><?php echo htmlspecialchars($employer['trade_name'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">Acronym</div><div class="info-value"><?php echo htmlspecialchars($employer['acronym'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">Employer Type</div><div class="info-value"><?php echo htmlspecialchars($employer['employer_type']); ?></div></div>
                        <div class="info-item"><div class="info-label">Employer Subtype</div><div class="info-value"><?php echo htmlspecialchars($employer['employer_subtype'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">Office Type</div><div class="info-value"><?php echo htmlspecialchars($employer['office_type']); ?></div></div>
                        <div class="info-item"><div class="info-label">Total Work Force</div><div class="info-value"><?php echo htmlspecialchars($employer['total_work_force']); ?></div></div>
                        <div class="info-item"><div class="info-label">TIN Number</div><div class="info-value"><?php echo htmlspecialchars($employer['tin_number']); ?></div></div>
                        <div class="info-item"><div class="info-label">Address</div><div class="info-value"><?php echo htmlspecialchars($formatted_address); ?></div></div>
                    </div>
                    
                    <h3 style="margin-top: 2rem;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg> Contact Information</h3>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Owner Name</div><div class="info-value"><?php echo htmlspecialchars($employer['owner_name'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">Contact Person</div><div class="info-value"><?php echo htmlspecialchars($employer['contact_person_name']); ?></div></div>
                        <div class="info-item"><div class="info-label">Position</div><div class="info-value"><?php echo htmlspecialchars($employer['contact_person_position']); ?></div></div>
                        <div class="info-item"><div class="info-label">Mobile Number</div><div class="info-value"><?php echo htmlspecialchars($employer['mobile_number']); ?></div></div>
                        <div class="info-item"><div class="info-label">Telephone</div><div class="info-value"><?php echo htmlspecialchars($employer['telephone_number'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">Email Address</div><div class="info-value"><?php echo htmlspecialchars($employer['email_address']); ?></div></div>
                    </div>

                    <h3 style="margin-top: 2rem;"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.5rem;height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg> Company Description</h3>
                    <div class="info-value" style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($employer['company_description'] ?: 'No description provided.'); ?></div>
                </div>
            </div>

            <!-- Edit Mode -->
            <div id="profile-edit" style="display: none;">
                <div class="info-card">
                    <h3>Edit Company Profile</h3>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <h4 style="margin-bottom: 1rem; color: #374151;">I. Establishment Details</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Company Name</label><input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($employer['company_name']); ?>" required></div>
                            <div class="form-group"><label>Trade Name</label><input type="text" name="trade_name" class="form-control" value="<?php echo htmlspecialchars($employer['trade_name']); ?>"></div>
                            <div class="form-group"><label>Acronym</label><input type="text" name="acronym" class="form-control" value="<?php echo htmlspecialchars($employer['acronym']); ?>" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()"></div>
                            <div class="form-group"><label>Employer Type</label>
                                <select name="employer_type" class="form-control" required>
                                    <option value="Public" <?php echo $employer['employer_type'] == 'Public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="Private" <?php echo $employer['employer_type'] == 'Private' ? 'selected' : ''; ?>>Private</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Employer Subtype</label><input type="text" name="employer_subtype" class="form-control" value="<?php echo htmlspecialchars($employer['employer_subtype']); ?>"></div>
                            <div class="form-group"><label>Office Type</label>
                                <select name="office_type" class="form-control" required>
                                    <option value="Main office" <?php echo $employer['office_type'] == 'Main office' ? 'selected' : ''; ?>>Main office</option>
                                    <option value="Branch" <?php echo $employer['office_type'] == 'Branch' ? 'selected' : ''; ?>>Branch</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Total Work Force</label>
                                <select name="total_work_force" class="form-control" required>
                                    <option value="Micro (1-9)" <?php echo $employer['total_work_force'] == 'Micro (1-9)' ? 'selected' : ''; ?>>Micro (1-9)</option>
                                    <option value="Small (10-99)" <?php echo $employer['total_work_force'] == 'Small (10-99)' ? 'selected' : ''; ?>>Small (10-99)</option>
                                    <option value="Medium (100-199)" <?php echo $employer['total_work_force'] == 'Medium (100-199)' ? 'selected' : ''; ?>>Medium (100-199)</option>
                                    <option value="Large (200 and up)" <?php echo $employer['total_work_force'] == 'Large (200 and up)' ? 'selected' : ''; ?>>Large (200 and up)</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Business Line</label>
                                <select name="business_line_id" class="form-control" required>
                                    <?php if($business_lines) { $business_lines->data_seek(0); while($bl = $business_lines->fetch_assoc()): ?>
                                        <option value="<?php echo $bl['business_line_id']; ?>" <?php echo $employer['business_line_id'] == $bl['business_line_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($bl['business_name']); ?></option>
                                    <?php endwhile; } ?>
                                </select>
                            </div>
                            <div class="form-group"><label>TIN Number</label><input type="text" name="tin_number" class="form-control" value="<?php echo htmlspecialchars($employer['tin_number']); ?>" required></div>
                            <div class="form-group"><label>Street Address</label><input type="text" name="street_address" class="form-control" value="<?php echo htmlspecialchars($employer['street_address']); ?>" required></div>
                            <div class="form-group"><label>Location</label>
                                <select name="location_id" class="form-control" required>
                                    <?php if($locations) { $locations->data_seek(0); while($loc = $locations->fetch_assoc()): ?>
                                        <option value="<?php echo $loc['location_id']; ?>" <?php echo $employer['location_id'] == $loc['location_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['barangay'] . ', ' . $loc['city_municipality']); ?></option>
                                    <?php endwhile; } ?>
                                </select>
                            </div>
                        </div>
                        
                        <h4 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #374151;">II. Contact Information</h4>
                        <div class="form-grid">
                            <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($employer['owner_name']); ?>"></div>
                            <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person_name" class="form-control" value="<?php echo htmlspecialchars($employer['contact_person_name']); ?>" required></div>
                            <div class="form-group"><label>Position</label><input type="text" name="contact_person_position" class="form-control" value="<?php echo htmlspecialchars($employer['contact_person_position']); ?>" required></div>
                            <div class="form-group"><label>Mobile Number</label><input type="text" name="mobile_number" class="form-control" value="<?php echo htmlspecialchars($employer['mobile_number']); ?>" required></div>
                            <div class="form-group"><label>Telephone</label><input type="text" name="telephone_number" class="form-control" value="<?php echo htmlspecialchars($employer['telephone_number']); ?>"></div>
                            <div class="form-group"><label>Fax</label><input type="text" name="fax_number" class="form-control" value="<?php echo htmlspecialchars($employer['fax_number']); ?>"></div>
                            <div class="form-group"><label>Email Address</label><input type="email" name="email_address" class="form-control" value="<?php echo htmlspecialchars($employer['email_address']); ?>" required></div>
                        </div>

                        <div class="form-group" style="margin-top: 1rem;">
                            <label>Company Description</label>
                            <textarea name="company_description" class="form-control" rows="5"><?php echo htmlspecialchars($employer['company_description']); ?></textarea>
                        </div>

                        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                            <button type="button" class="btn-edit" onclick="toggleEditMode()">Cancel</button>
                            <button type="submit" class="btn-primary">Save Profile Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Preview Modal -->
    <div id="imagePreviewModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0; margin-bottom: 1rem; color: #1f2937;">Confirm Photo</h3>
            <div style="margin-bottom: 1.5rem; max-height: 300px; overflow: hidden; border-radius: 8px; border: 1px solid #e5e7eb; display: flex; justify-content: center; align-items: center; background: #f9fafb;">
                <img id="previewImage" src="" alt="Preview" style="max-width: 100%; max-height: 300px; object-fit: contain;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" class="btn-edit" onclick="cancelUpload()">Cancel</button>
                <button type="button" class="btn-primary" onclick="confirmUpload()">Upload Now</button>
            </div>
        </div>
    </div>

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

        function toggleEditMode() {
            const view = document.getElementById('profile-view');
            const edit = document.getElementById('profile-edit');
            if (view.style.display === 'none') {
                view.style.display = 'block';
                edit.style.display = 'none';
            } else {
                view.style.display = 'none';
                edit.style.display = 'block';
            }
        }

        // Logic for Image Upload Preview
        let targetFormToSubmit = null;
        let currentFileInput = null;

        function showImagePreview(input, formId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('imagePreviewModal').style.display = 'flex';
                    targetFormToSubmit = formId;
                    currentFileInput = input;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function cancelUpload() {
            document.getElementById('imagePreviewModal').style.display = 'none';
            if (currentFileInput) currentFileInput.value = ''; // Reset input
            targetFormToSubmit = null; currentFileInput = null;
        }

        function confirmUpload() {
            if (targetFormToSubmit) document.getElementById(targetFormToSubmit).submit();
        }
    </script>
</body>
</html>