<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $upload_dir = "../UPLOADS/profile_pictures/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    
    if (in_array($file_ext, $allowed_ext)) {
        if ($_FILES['profile_pic']['size'] <= 5242880) { // 5MB limit
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $stmt = $conn->prepare("UPDATE jobseekers SET profile_picture = ? WHERE seeker_id = ?");
                $stmt->bind_param("si", $target_file, $user_id);
                $stmt->execute();
                header("Location: my_profile.php");
                exit();
            }
        }
    }
}

// Handle Skill Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_skill_id'])) {
    $skill_id_del = (int)$_POST['delete_skill_id'];
    $del_stmt = $conn->prepare("DELETE FROM jobseeker_skills WHERE seeker_id = ? AND skill_id = ?");
    $del_stmt->bind_param("ii", $user_id, $skill_id_del);
    $del_stmt->execute();
    header("Location: my_profile.php");
    exit();
}

// Count unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
$unread_stmt->close();

// Fetch user details with joins for readable names
$stmt = $conn->prepare("
    SELECT js.*, l.barangay, l.city_municipality, el.level_name, c.course_name, uc.contact_value as email
    FROM jobseekers js
    LEFT JOIN locations l ON js.location_id = l.location_id
    LEFT JOIN education_levels el ON js.education_id = el.education_id
    LEFT JOIN courses c ON js.course_id = c.course_id
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    WHERE js.seeker_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

// Fetch Educational Background
$edu_stmt = $conn->prepare("SELECT * FROM educational_background WHERE seeker_id = ?");
$edu_stmt->bind_param("i", $user_id);
$edu_stmt->execute();
$educations = $edu_stmt->get_result();

// Fetch skills
$skill_stmt = $conn->prepare("SELECT s.skill_id, s.skill_name FROM jobseeker_skills js JOIN skills s ON js.skill_id = s.skill_id WHERE js.seeker_id = ? ORDER BY s.skill_name");
$skill_stmt->bind_param("i", $user_id);
$skill_stmt->execute();
$s_res = $skill_stmt->get_result();
$skills_list = [];
while($row = $s_res->fetch_assoc()) $skills_list[] = $row;

// Fetch work experience
$work_stmt = $conn->prepare("SELECT * FROM work_experience WHERE seeker_id = ? ORDER BY start_date DESC");
$work_stmt->bind_param("i", $user_id);
$work_stmt->execute();
$work_experience = $work_stmt->get_result();

// Fetch seminars
$sem_stmt = $conn->prepare("SELECT * FROM seminars_trainings WHERE seeker_id = ? ORDER BY date_attended DESC");
$sem_stmt->bind_param("i", $user_id);
$sem_stmt->execute();
$seminars = $sem_stmt->get_result();

// Fetch character references
$ref_stmt = $conn->prepare("SELECT * FROM character_references WHERE seeker_id = ?");
$ref_stmt->bind_param("i", $user_id);
$ref_stmt->execute();
$references = $ref_stmt->get_result();

// Use profile data for sidebar user info
$user = $profile; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
        
        /* Profile Design */
        .profile-container { max-width: 1000px; margin: 0 auto; }
        
        .profile-header-card { 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
        }
        
        .profile-cover {
            height: 180px;
            background: linear-gradient(120deg, #1e40af, #3b82f6);
        }
        
        .profile-header-content {
            padding: 1.5rem 2rem 1rem;
            position: relative;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
        }
        
        .profile-avatar-container {
            position: relative;
            margin-top: -80px;
            margin-bottom: 0;
            order: 2;
            flex-shrink: 0;
        }
        
        .profile-avatar-lg {
            width: 150px;
            height: 150px;
            background-color: white;
            border: 4px solid white;
            border-radius: 4px; /* 2x2 Style */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            color: #1e40af;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .profile-avatar-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #1e40af;
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: background 0.2s;
        }
        .upload-btn:hover {
            background: #1d4ed8;
        }
        
        .profile-name-section {
            order: 1;
            padding-top: 0.5rem;
            flex: 1;
            padding-right: 2rem;
        }

        .profile-name-section h2 { font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 0.5rem; line-height: 1.2; }
        .contact-info { display: flex; flex-direction: column; gap: 0.25rem; color: #4b5563; font-size: 0.95rem; margin-bottom: 1.5rem; }
        .contact-item { display: flex; align-items: center; gap: 0.5rem; }
        
        .action-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .btn-action { padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; }
        .btn-light { background: white; border-color: #d1d5db; color: #374151; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-light:hover { background: #f9fafb; border-color: #9ca3af; }
        .btn-primary { background: #1e40af; color: white; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #059669; color: white; border: none; }
        .btn-success:hover { background: #047857; }

        .profile-section { margin-bottom: 2.5rem; padding-bottom: 2rem; border-bottom: 1px solid #f3f4f6; }
        .profile-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }

        .info-card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #eaeaea; }
        .info-card-title { font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #eff6ff; display: flex; align-items: center; gap: 0.75rem; }
        .info-card-title svg { width: 24px; height: 24px; color: #1e40af; }
        
        .info-item { margin-bottom: 1rem; }
        .info-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.35rem; font-weight: 600; }
        .info-value { font-size: 1rem; color: #1f2937; font-weight: 500; line-height: 1.5; }
        
        .info-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 640px) { .info-grid-2 { grid-template-columns: 1fr; gap: 1rem; } }
        
        .skill-tag {
            display: inline-block;
            background-color: #eff6ff;
            color: #1e40af;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #dbeafe;
        }

        /* Timeline Styles */
        .timeline-item { position: relative; padding-left: 1.5rem; border-left: 2px solid #e5e7eb; margin-left: 0.5rem; margin-bottom: 1.5rem; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-item::before { content: ''; position: absolute; left: -5px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: #1e40af; border: 2px solid white; box-shadow: 0 0 0 1px #e5e7eb; }
        .timeline-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 0.25rem; }
        .timeline-role { font-weight: 600; font-size: 1.1rem; color: #1f2937; }
        .timeline-date { font-size: 0.875rem; color: #6b7280; }
        .timeline-company { color: #1e40af; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .timeline-desc { color: #4b5563; font-size: 0.95rem; line-height: 1.6; }

        .references-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .reference-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; }
        
        /* Hamburger & Mobile Sidebar */
        .hamburger { display: none; background: none; border: none; cursor: pointer; color: #1f2937; margin-right: 1rem; padding: 0; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; }

        @media (max-width: 768px) {
            .sidebar { display: flex; transform: translateX(-100%); transition: transform 0.3s ease; z-index: 50; width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .hamburger { display: block; }
            .main-content { margin-left: 0; }
            .profile-header-content { flex-direction: column; align-items: flex-start; text-align: left; }
            .profile-avatar-container { order: 0; margin-top: -75px; margin-bottom: 1rem; align-self: flex-start; }
            .profile-name-section { order: 1; padding-top: 0; padding-right: 0; width: 100%; }
            .action-buttons { justify-content: flex-start; }
            .contact-info { align-items: flex-start; }
            html { font-size: 14px; }
            .profile-name-section h2 { font-size: 1.5rem; }
            
            /* Mobile UI Adjustments */
            .btn-action { width: 100%; text-align: center; justify-content: center; margin-bottom: 0.5rem; }
        }
        
        @media print {
            body { background-color: white; font-family: Arial, Helvetica, sans-serif; color: #000; }
            .sidebar, .nav-menu, .upload-btn, .btn-primary, .btn-secondary, button, .profile-cover, .action-buttons, .modal { display: none !important; }
            .main-content { margin: 0; padding: 0; width: 100%; }
            .profile-container { max-width: 100%; margin: 0; }
            
            /* Header Reset */
            .profile-header-card { box-shadow: none; border: none; margin-bottom: 20px; background: none; }
            .profile-header-content { padding: 0; display: flex; flex-direction: row; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 20px; }
            .profile-avatar-container { margin-top: 0; margin-bottom: 0; order: 2; }
            .profile-avatar-lg { width: 120px; height: 120px; border: 1px solid #000; box-shadow: none; border-radius: 0; }
            .profile-name-section { text-align: left; order: 1; }
            .profile-name-section h2 { font-size: 24pt; margin-bottom: 10px; color: #000; text-transform: uppercase; }
            .contact-info { color: #000; font-size: 11pt; margin-bottom: 0; }
            .contact-item span { display: none; }
            
            /* Content Reset */
            .info-card { box-shadow: none; border: none; padding: 0; margin-bottom: 20px; page-break-inside: avoid; }
            .info-card-title { 
                font-size: 14pt; 
                text-transform: uppercase; 
                border-bottom: 1px solid #000; 
                padding-bottom: 5px; 
                margin-bottom: 10px; 
                color: #000; 
                font-weight: bold;
                display: block;
                display: flex;
            }
            .info-card-title svg { display: none; }
            
            .info-item { margin-bottom: 5px; display: flex; }
            .info-label { width: 180px; font-weight: bold; color: #000; font-size: 10pt; text-transform: none; letter-spacing: normal; }
            .info-value { flex: 1; color: #000; }
            
            /* Skills as list */
            .skill-tag { 
                background: none; 
                border: none; 
                padding: 0; 
                color: #000; 
                display: inline; 
                margin: 0;
                font-weight: normal;
            }
            .skill-tag::after { content: ", "; }
            .skill-tag:last-child::after { content: ""; }
            .skill-tag form { display: none; }
            
            /* Timeline items for Work/Seminars */
            .timeline-item { margin-bottom: 15px; page-break-inside: avoid; border-left: none; padding-left: 0; margin-left: 0; }
            .timeline-item::before { display: none; }
            .timeline-role { font-weight: bold; color: #000; }
            .timeline-company { color: #000; font-style: italic; }

            /* References for print */
            .references-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
            .reference-item { border: none; padding: 0; margin-bottom: 1rem; page-break-inside: avoid; }
            .empty-field { display: none !important; }
            .print-hidden { display: none !important; }
            .profile-section { border-bottom: none !important; margin-bottom: 20px !important; padding-bottom: 0 !important; page-break-inside: avoid; }
            .info-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
            .info-grid-2 .info-item { display: block; margin-bottom: 10px; page-break-inside: avoid; }
            .info-grid-2 .info-label { width: auto; margin-bottom: 2px; }
        }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; padding: 20px; border: 1px solid #888; width: 90%; max-width: 400px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-title { font-size: 1.25rem; font-weight: 600; color: #1f2937; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .checkbox-group { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem; }
        .checkbox-item { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.95rem; color: #374151; }
        .icon-svg { width: 1.25rem; height: 1.25rem; stroke-width: 1.5; }
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
            <a href="my_profile.php" class="nav-item active">
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
        <button class="hamburger" onclick="toggleSidebar()" style="margin-bottom: 1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
        <div class="profile-container">
            <!-- Header Card -->
            <div class="profile-header-card">
                <div class="profile-cover"></div>
                <div class="profile-header-content">
                    <div class="profile-avatar-container">
                        <div class="profile-avatar-lg">
                            <?php if (!empty($profile['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($profile['profile_picture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <?php echo strtoupper(substr($profile['first_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <form action="my_profile.php" method="POST" enctype="multipart/form-data" id="avatarForm">
                            <label for="profile_pic" class="upload-btn" title="Upload Profile Picture">
                                +
                            </label>
                            <input type="file" name="profile_pic" id="profile_pic" style="display: none;" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                        </form>
                    </div>
                    <div class="profile-name-section">
                        <h2><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['middle_name'] . ' ' . $profile['last_name'] . ' ' . $profile['suffix']); ?></h2>
                        <div class="contact-info">
                            <?php if(!empty($profile['email'])): ?>
                                <div class="contact-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg> Email: <?php echo htmlspecialchars($profile['email']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if(!empty($profile['contact_no'])): ?>
                                <div class="contact-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg> Mobile: <?php echo htmlspecialchars($profile['contact_no']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="action-buttons">
                            <a href="edit_profile.php" class="btn-action btn-light">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg> Edit Profile
                            </a>
                            <button onclick="openCustomizeModal()" class="btn-action btn-light">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg> Customize Resume
                            </button>
                            <button onclick="window.print()" class="btn-action btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h10.5v2.25h-10.5V6.75zm0 8.25h10.5v2.25h-10.5V15zm-3 2.25h16.5a2.25 2.25 0 002.25-2.25V9a2.25 2.25 0 00-2.25-2.25H3.75A2.25 2.25 0 001.5 9v6a2.25 2.25 0 002.25 2.25z" /></svg> Print
                            </button>
                            <button onclick="downloadResume()" class="btn-action btn-success">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg> Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <!-- Summary Section -->
                <?php if(!empty($profile['summary'])): ?>
                <div id="sec-summary" class="profile-section">
                    <div class="info-card-title">Summary / Objective</div>
                    <div class="info-value" style="font-weight: 400; line-height: 1.6; color: #374151;">
                        <?php echo nl2br(htmlspecialchars($profile['summary'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Personal Information -->
                <div id="sec-personal" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        Personal Information
                    </div>
                    <div class="info-grid-2">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['middle_name'] . ' ' . $profile['last_name'] . ' ' . $profile['suffix']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Mobile Number</div>
                            <div class="info-value"><?php echo htmlspecialchars(!empty($profile['contact_no']) ? $profile['contact_no'] : 'N/A'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo htmlspecialchars($profile['gender']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Civil Status</div>
                            <div class="info-value"><?php echo htmlspecialchars($profile['civil_status']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Birthdate</div>
                            <div class="info-value"><?php echo date('F d, Y', strtotime($profile['birthdate'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age</div>
                            <div class="info-value">
                                <?php 
                                    $dob = new DateTime($profile['birthdate']);
                                    $now = new DateTime();
                                    echo $now->diff($dob)->y;
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Place of Birth</div>
                            <div class="info-value"><?php echo htmlspecialchars($profile['place_of_birth']); ?></div>
                        </div>
                        <?php 
                            $languages = !empty($profile['languages']) ? $profile['languages'] : 'N/A';
                            $lang_class = ($languages === 'N/A') ? 'empty-field' : '';
                        ?>
                        <div class="info-item <?php echo $lang_class; ?>">
                            <div class="info-label">Languages</div>
                            <div class="info-value"><?php echo htmlspecialchars($languages); ?></div>
                        </div>
                        <?php 
                            $disability = !empty($profile['disability']) && $profile['disability'] !== 'None' ? $profile['disability'] : 'N/A';
                            $disability_class = ($disability === 'N/A') ? 'empty-field' : '';
                        ?>
                        <div class="info-item <?php echo $disability_class; ?>">
                            <div class="info-label">Disability</div>
                            <div class="info-value"><?php echo htmlspecialchars($disability); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Contact & Education -->
                <div id="sec-education" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Address & Education
                    </div>
                    <div class="info-grid-2">
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($profile['street_address']); ?></div>
                            <div class="info-value" style="margin-top: 0.25rem; color: #4b5563; font-size: 0.95rem;"><?php echo htmlspecialchars($profile['barangay'] . ', ' . $profile['city_municipality']); ?></div>
                        </div>
                        <?php 
                            $edu_level = !empty($profile['level_name']) ? $profile['level_name'] : 'N/A';
                            $edu_class = ($edu_level === 'N/A') ? 'empty-field' : '';
                        ?>
                        <div class="info-item <?php echo $edu_class; ?>">
                            <div class="info-label">Education Level</div>
                            <div class="info-value"><?php echo htmlspecialchars($edu_level); ?></div>
                        </div>
                        <?php 
                            $course = !empty($profile['course_name']) ? $profile['course_name'] : 'N/A';
                            $course_class = ($course === 'N/A') ? 'empty-field' : '';
                        ?>
                        <div class="info-item <?php echo $course_class; ?>">
                            <div class="info-label">Course / Major</div>
                            <div class="info-value"><?php echo htmlspecialchars($course); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Employment Status</div>
                            <div class="info-value">
                                <span style="background: #eff6ff; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($profile['employment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Schools Section -->
                <div id="sec-schools" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                        Schools Attended
                    </div>
                    <?php if($educations->num_rows > 0): ?>
                        <?php while($edu = $educations->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-header">
                                    <div class="timeline-role"><?php echo htmlspecialchars($edu['school_name']); ?></div>
                                    <div class="timeline-date"><?php echo htmlspecialchars($edu['school_year']); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #9ca3af; font-style: italic;">No schools added.</p>
                    <?php endif; ?>
                </div>

                <!-- Skills Section -->
                <div id="sec-skills" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Skills
                    </div>
                    <div class="info-value">
                        <?php if(!empty($skills_list)): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <?php foreach($skills_list as $skill): ?>
                                    <span class="skill-tag">
                                        <?php echo htmlspecialchars($skill['skill_name']); ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this skill?');">
                                            <input type="hidden" name="delete_skill_id" value="<?php echo $skill['skill_id']; ?>">
                                            <button type="submit" style="background:none; border:none; color: #ef4444; cursor:pointer; font-size: 1.1em; line-height: 1; padding: 0 0 0 4px; vertical-align: middle;" title="Remove skill">&times;</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #9ca3af;">No skills added yet</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Work Experience Section -->
                <div id="sec-work" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Work Experience
                    </div>
                    <?php if($work_experience->num_rows > 0): ?>
                        <?php while($work = $work_experience->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-header">
                                    <div class="timeline-role"><?php echo htmlspecialchars($work['job_title']); ?></div>
                                    <div class="timeline-date">
                                        <?php 
                                            echo date('M Y', strtotime($work['start_date'])) . ' - ';
                                            echo $work['end_date'] ? date('M Y', strtotime($work['end_date'])) : 'Present';
                                        ?>
                                    </div>
                                </div>
                                <div class="timeline-company"><?php echo htmlspecialchars($work['company_name']); ?></div>
                                <?php if(!empty($work['description'])): ?>
                                    <div class="timeline-desc">
                                        <?php echo nl2br(htmlspecialchars($work['description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #9ca3af; font-style: italic;">No work experience added.</p>
                    <?php endif; ?>
                </div>

                <!-- Seminars & Trainings Section -->
                <div id="sec-seminars" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Seminars & Trainings
                    </div>
                    <?php if($seminars->num_rows > 0): ?>
                        <?php while($sem = $seminars->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-header">
                                    <div class="timeline-role"><?php echo htmlspecialchars($sem['title']); ?></div>
                                    <div class="timeline-date">
                                        <?php echo $sem['date_attended'] ? date('M d, Y', strtotime($sem['date_attended'])) : ''; ?>
                                    </div>
                                </div>
                                <div class="timeline-company" style="color: #4b5563;">
                                    Provider: <?php echo htmlspecialchars($sem['provider']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #9ca3af; font-style: italic;">No seminars or trainings added.</p>
                    <?php endif; ?>
                </div>

                <!-- Character References Section -->
                <div id="sec-references" class="profile-section">
                    <div class="info-card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Character References
                    </div>
                    <?php if($references->num_rows > 0): ?>
                        <div class="references-grid">
                            <?php while($ref = $references->fetch_assoc()): ?>
                                <div class="reference-item">
                                    <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($ref['name']); ?></div>
                                    <div style="color: #1e40af; font-size: 0.9rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($ref['position']); ?></div>
                                    <div style="color: #4b5563; font-size: 0.875rem; font-style: italic; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($ref['company']); ?></div>
                                    <div style="font-size: 0.875rem; color: #4b5563;">
                                        <?php if(!empty($ref['contact_no'])): ?>
                                            <div>📞 <?php echo htmlspecialchars($ref['contact_no']); ?></div>
                                        <?php endif; ?>
                                        <?php if(!empty($ref['email'])): ?>
                                            <div>✉️ <?php echo htmlspecialchars($ref['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #9ca3af; font-style: italic;">No character references added.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Customize Resume Modal -->
    <div id="customizeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Customize Resume Sections</h3>
                <button class="close-btn" onclick="closeCustomizeModal()">&times;</button>
            </div>
            <div class="checkbox-group">
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-summary', this.checked)"> Summary / Objective</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-personal', this.checked)"> Personal Information</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-education', this.checked)"> Address & Education</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-schools', this.checked)"> Schools Attended</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-skills', this.checked)"> Skills</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-work', this.checked)"> Work Experience</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-seminars', this.checked)"> Seminars & Trainings</label>
                <label class="checkbox-item"><input type="checkbox" checked onchange="toggleSection('sec-references', this.checked)"> Character References</label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button class="btn-action btn-light" onclick="closeCustomizeModal()">Done</button>
            </div>
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

        function openCustomizeModal() {
            document.getElementById('customizeModal').style.display = 'flex';
        }

        function closeCustomizeModal() {
            document.getElementById('customizeModal').style.display = 'none';
        }

        function toggleSection(id, isChecked) {
            const el = document.getElementById(id);
            if (el) {
                if (isChecked) {
                    el.classList.remove('print-hidden');
                } else {
                    el.classList.add('print-hidden');
                }
            }
        }

        function downloadResume() {
            // Select the element to convert
            const element = document.querySelector('.profile-container');
            
            // Clone the element to modify it for PDF generation without affecting the view
            const clone = element.cloneNode(true);
            
            // Remove elements that shouldn't be in the PDF
            const emptyFields = clone.querySelectorAll('.empty-field');
            emptyFields.forEach(el => el.remove());
            
            // Remove hidden sections
            const hiddenSections = clone.querySelectorAll('.print-hidden');
            hiddenSections.forEach(el => el.remove());
            
            const buttons = clone.querySelectorAll('.profile-name-section a, .profile-name-section button, .upload-btn');
            buttons.forEach(el => el.remove());
            
            // Remove SVGs
            const svgs = clone.querySelectorAll('svg');
            svgs.forEach(el => el.remove());

            const actionBtns = clone.querySelectorAll('.action-buttons');
            actionBtns.forEach(el => el.remove());

            // Remove delete buttons from skills tags
            const skillForms = clone.querySelectorAll('.skill-tag form');
            skillForms.forEach(el => el.remove());

            // Inject print styles for PDF
            const style = document.createElement('style');
            style.innerHTML = `
                body { font-family: Arial, Helvetica, sans-serif; color: #000; }
                .profile-header-card { box-shadow: none !important; border: none !important; margin-bottom: 20px !important; }
                .profile-header-content { border-bottom: 2px solid #000 !important; padding-bottom: 20px !important; display: flex !important; flex-direction: row !important; justify-content: space-between !important; align-items: flex-start !important; }
                .profile-avatar-container { margin-top: 0 !important; margin-bottom: 0 !important; order: 2 !important; }
                .profile-avatar-lg { border: 1px solid #000 !important; box-shadow: none !important; border-radius: 0 !important; }
                .profile-name-section { text-align: left !important; order: 1 !important; padding-top: 0 !important; }
                .profile-name-section h2 { font-size: 24pt !important; margin-bottom: 10px !important; text-transform: uppercase !important; color: #000 !important; }
                .contact-info { color: #000 !important; font-size: 11pt !important; margin-bottom: 0 !important; }
                .contact-item span { display: none !important; }
                .profile-cover { display: none !important; }
                
                .info-card { box-shadow: none !important; border: none !important; padding: 0 !important; margin-bottom: 20px !important; }
                .info-card-title { font-size: 14pt !important; text-transform: uppercase !important; border-bottom: 1px solid #000 !important; padding-bottom: 5px !important; margin-bottom: 10px !important; color: #000 !important; font-weight: bold !important; display: block !important; }
                .info-card-title svg { display: none !important; }
                
                .info-item { margin-bottom: 5px !important; display: flex !important; }
                .info-label { width: 180px !important; font-weight: bold !important; color: #000 !important; font-size: 10pt !important; text-transform: none !important; letter-spacing: normal !important; }
                .info-value { flex: 1 !important; color: #000 !important; font-size: 10pt !important; font-weight: normal !important; }
                
                /* Use Flexbox for grid-like layout in PDF */
                .info-grid-2 { display: flex !important; flex-wrap: wrap !important; gap: 0 !important; }
                .info-grid-2 .info-item { width: 50% !important; display: block !important; margin-bottom: 10px !important; box-sizing: border-box !important; padding-right: 10px !important; }
                .info-grid-2 .info-label { width: auto !important; margin-bottom: 2px !important; }
                
                .timeline-item { margin-bottom: 15px !important; border-left: none !important; padding-left: 0 !important; margin-left: 0 !important; }
                .timeline-item::before { display: none !important; }
                .timeline-role { font-weight: bold !important; color: #000 !important; font-size: 11pt !important; }
                .timeline-company { color: #000 !important; font-style: italic !important; font-size: 10pt !important; }
                
                .skill-tag { background: none !important; border: none !important; padding: 0 !important; color: #000 !important; display: inline !important; margin: 0 !important; font-weight: normal !important; }
                .skill-tag::after { content: ", " !important; }
                .skill-tag:last-child::after { content: "" !important; }
                .skill-tag form { display: none !important; }
                
                .references-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 1rem !important; }
                .reference-item { border: none !important; padding: 0 !important; margin-bottom: 1rem !important; }
                
                .profile-section { border-bottom: none !important; margin-bottom: 20px !important; padding-bottom: 0 !important; page-break-inside: avoid !important; }
            `;
            clone.appendChild(style);

            // Options for html2pdf
            const opt = {
                margin: [0.3, 0.3], // top, left, bottom, right
                filename: 'Resume_<?php echo htmlspecialchars($user['last_name']); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            // Generate PDF
            html2pdf().set(opt).from(clone).save();
        }
    </script>
</body>
</html>