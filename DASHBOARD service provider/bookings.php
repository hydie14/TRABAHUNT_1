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

$action_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    if ($provider['admin_verification_status'] === 'Verified') {
        $req_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        
        if ($action === 'complete') {
            $amount_charged = isset($_POST['amount_charged']) && is_numeric($_POST['amount_charged']) ? (float)$_POST['amount_charged'] : null;
            
            // Get client details for notification
            $get_req = $conn->prepare("SELECT sr.client_id, COALESCE(ps.service_name, sr.service_needed) AS service_name FROM service_requests sr LEFT JOIN provider_services ps ON sr.service_id = ps.service_id WHERE sr.request_id = ?");
            $get_req->bind_param("i", $req_id);
            $get_req->execute();
            $req_data = $get_req->get_result()->fetch_assoc();
            $get_req->close();

            // Update status to Completed and store the amount
            $update_stmt = $conn->prepare("UPDATE service_requests SET status = 'Completed', amount_charged = ? WHERE request_id = ? AND provider_id = ? AND status IN ('Accepted', 'Ongoing')");
            $update_stmt->bind_param("dii", $amount_charged, $req_id, $user_id);
            
            if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                $action_message = "<div style='padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;'>Job successfully marked as completed!</div>";
                
                // Insert notification for client
                if ($req_data) {
                    $notif_title = "Service Completed";
                    $notif_msg = "Your service '" . $req_data['service_name'] . "' has been marked as completed. You can now leave a review.";
                    $notif_type = "service_booking";
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, ?, ?, ?, ?)");
                    $notif_stmt->bind_param("isiss", $req_data['client_id'], $notif_type, $req_id, $notif_title, $notif_msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            } else {
                $action_message = "<div style='padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;'>Failed to complete job. It might have already been updated.</div>";
            }
            $update_stmt->close();
        }
    }
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch bookings
$query = "
    SELECT sr.*, ps.base_rate, ps.service_name,
           COALESCE(js.first_name, emp.company_name, sp.first_name, 'Client') as client_first_name, 
           COALESCE(js.last_name, '', sp.last_name, '') as client_last_name,
           COALESCE(uc.contact_value, 'N/A') as client_contact
    FROM service_requests sr
    LEFT JOIN provider_services ps ON sr.service_id = ps.service_id
    LEFT JOIN jobseekers js ON sr.client_id = js.seeker_id
    LEFT JOIN employers emp ON sr.client_id = emp.employer_id
    LEFT JOIN service_providers sp ON sr.client_id = sp.provider_id
    LEFT JOIN user_contacts uc ON sr.client_id = uc.user_id AND uc.contact_type = 'Mobile'
    WHERE sr.provider_id = ?
";

if (!empty($start_date)) {
    $query .= " AND DATE(sr.scheduled_date) >= ?";
}
if (!empty($end_date)) {
    $query .= " AND DATE(sr.scheduled_date) <= ?";
}
$query .= " ORDER BY sr.created_at DESC";

$stmt_bookings = $conn->prepare($query);

if (!empty($start_date) && !empty($end_date)) {
    $stmt_bookings->bind_param("iss", $user_id, $start_date, $end_date);
} elseif (!empty($start_date)) {
    $stmt_bookings->bind_param("is", $user_id, $start_date);
} elseif (!empty($end_date)) {
    $stmt_bookings->bind_param("is", $user_id, $end_date);
} else {
    $stmt_bookings->bind_param("i", $user_id);
}
$stmt_bookings->execute();
$bookings_result = $stmt_bookings->get_result();

$categorized_bookings = [
    'pending' => [],
    'ongoing' => [],
    'completed' => [],
    'history' => []
];

while($booking = $bookings_result->fetch_assoc()) {
    if ($booking['status'] == 'Pending') {
        $categorized_bookings['pending'][] = $booking;
    } elseif (in_array($booking['status'], ['Accepted', 'Ongoing'])) {
        $categorized_bookings['ongoing'][] = $booking;
    } elseif ($booking['status'] == 'Completed') {
        $categorized_bookings['completed'][] = $booking;
    } else {
        $categorized_bookings['history'][] = $booking;
    }
}
$stmt_bookings->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - PESO Bongabon</title>
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
        .avatar { width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; color: #6b7280; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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
        
        /* Tabs */
        .tabs { display: flex; border-bottom: 1px solid #e5e7eb; overflow-x: auto; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tab-btn { padding: 1rem 1.5rem; border: none; background: transparent; font-size: 0.95rem; font-weight: 600; color: #6b7280; cursor: pointer; white-space: nowrap; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab-btn:hover { color: #111827; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab-content { display: none; padding: 1.5rem; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: fadeIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h2 { font-size: 1.25rem; font-weight: 700; color: #111827; }
        .close-btn { color: #9ca3af; font-size: 1.5rem; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: #111827; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .form-group input[type="number"] { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 1rem; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
        .btn-cancel { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit { background: #10b981; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; }

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
            <a href="bookings.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></span> Bookings
            </a>
            <a href="reviews.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg></span> Reviews
            </a>
            <a href="settings.php" class="nav-item">
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
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
            <h1 class="page-title" style="margin: 0;">My Bookings</h1>
            <form method="GET" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin: 0;">
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
                <span style="color: #6b7280; font-weight: 500;">to</span>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit;">
                <button type="submit" style="background: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer;">Filter Date</button>
                <?php if(!empty($start_date) || !empty($end_date)): ?>
                    <a href="bookings.php" style="color: #ef4444; text-decoration: none; font-weight: 600; font-size: 0.9rem; margin-left: 0.5rem;">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (!empty($action_message)) echo $action_message; ?>

        <?php if ($provider['admin_verification_status'] === 'Verified'): ?>
            <div class="section" style="padding: 0;">
                <div class="tabs">
                    <button class="tab-btn active" onclick="openTab(event, 'pending')">Pending (<?php echo count($categorized_bookings['pending']); ?>)</button>
                    <button class="tab-btn" onclick="openTab(event, 'ongoing')">Ongoing (<?php echo count($categorized_bookings['ongoing']); ?>)</button>
                    <button class="tab-btn" onclick="openTab(event, 'completed')">Completed (<?php echo count($categorized_bookings['completed']); ?>)</button>
                    <button class="tab-btn" onclick="openTab(event, 'history')">History (<?php echo count($categorized_bookings['history']); ?>)</button>
                </div>

                <?php 
                $tabs = [
                    'pending' => 'Pending Requests',
                    'ongoing' => 'Ongoing Services',
                    'completed' => 'Completed Services',
                    'history' => 'Booking History'
                ];
                
                foreach ($tabs as $tab_id => $tab_title): 
                    $current_bookings = $categorized_bookings[$tab_id];
                ?>
                <div id="<?php echo $tab_id; ?>" class="tab-content <?php echo $tab_id === 'pending' ? 'active' : ''; ?>">
                    <?php if (count($current_bookings) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 1rem; border-bottom: 2px solid #e5e7eb; background: #f9fafb; color: #4b5563;">Client Name</th>
                                        <th style="text-align: left; padding: 1rem; border-bottom: 2px solid #e5e7eb; background: #f9fafb; color: #4b5563;">Service Needed</th>
                                        <th style="text-align: left; padding: 1rem; border-bottom: 2px solid #e5e7eb; background: #f9fafb; color: #4b5563;">Schedule Date & Time</th>
                                        <th style="text-align: left; padding: 1rem; border-bottom: 2px solid #e5e7eb; background: #f9fafb; color: #4b5563;">Status</th>
                                        <th style="text-align: left; padding: 1rem; border-bottom: 2px solid #e5e7eb; background: #f9fafb; color: #4b5563;">Amount (₱)</th>
                                        <?php if ($tab_id === 'ongoing'): ?>
                                            <th style="text-align: left; padding: 1rem; border-bottom: 2px solid #e5e7eb; background: #f9fafb; color: #4b5563;">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($current_bookings as $booking): ?>
                                        <tr style="border-bottom: 1px solid #e5e7eb;">
                                            <td style="padding: 1rem; color: #111827; font-weight: 500;">
                                                <?php echo htmlspecialchars($booking['client_first_name'] . ' ' . $booking['client_last_name']); ?>
                                                <?php if ($tab_id === 'ongoing'): ?>
                                                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: normal; margin-top: 0.25rem;">
                                                        📞 <?php echo htmlspecialchars($booking['client_contact']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem; color: #4b5563;"><?php echo htmlspecialchars($booking['service_name'] ?? $booking['service_needed']); ?></td>
                                            <td style="padding: 1rem; color: #4b5563;"><?php echo date('M d, Y h:i A', strtotime($booking['scheduled_date'])); ?></td>
                                            <td style="padding: 1rem;">
                                                <?php 
                                                    $status_color = '#6b7280'; $status_bg = '#f3f4f6';
                                                    if($booking['status'] == 'Pending') { $status_color = '#d97706'; $status_bg = '#fef3c7'; }
                                                    elseif($booking['status'] == 'Accepted' || $booking['status'] == 'Ongoing') { $status_color = '#1d4ed8'; $status_bg = '#dbeafe'; }
                                                    elseif($booking['status'] == 'Completed') { $status_color = '#059669'; $status_bg = '#d1fae5'; }
                                                    elseif($booking['status'] == 'Declined' || $booking['status'] == 'Cancelled') { $status_color = '#dc2626'; $status_bg = '#fee2e2'; }
                                                ?>
                                                <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                                    <?php echo htmlspecialchars($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; color: #4b5563;">
                                                <?php 
                                                    if ($booking['amount_charged']) {
                                                        echo '₱' . number_format($booking['amount_charged'], 2);
                                                    } elseif (!empty($booking['base_rate'])) {
                                                        echo htmlspecialchars($booking['base_rate']);
                                                    } elseif (!empty($provider['base_rate'])) {
                                                        echo htmlspecialchars($provider['base_rate']);
                                                    } else {
                                                        echo '<span style="color:#9ca3af;font-style:italic;">Not set</span>';
                                                    }
                                                ?>
                                            </td>
                                            <?php if ($tab_id === 'ongoing'): ?>
                                                <td style="padding: 1rem;">
                                                    <button onclick="openCompleteModal(<?php echo $booking['request_id']; ?>)" style="background: #10b981; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; white-space: nowrap;">Mark Complete</button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem 0;">No <?php echo strtolower($tab_title); ?> found.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="section" style="text-align: center; padding: 3rem 1.5rem; background: #fffbeb; border-color: #fde68a;">
                <h3 style="color: #b45309; margin-bottom: 0.5rem;">Account Under Review</h3>
                <p style="color: #b45309;">Your account is pending verification. This feature is currently disabled.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Complete Job Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Complete Job</h2>
                <span class="close-btn" onclick="closeCompleteModal()">&times;</span>
            </div>
            <form method="POST" action="bookings.php">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="request_id" id="complete_request_id">
                <div class="form-group">
                    <label for="amount_charged">Final Amount Charged (₱) - Optional</label>
                    <input type="number" step="0.01" min="0" id="amount_charged" name="amount_charged" placeholder="e.g. 500">
                    <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem;">Enter the final amount paid by the client to track your earnings.</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeCompleteModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Mark as Completed</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }
        
        const completeModal = document.getElementById('completeModal');
        
        function openCompleteModal(requestId) {
            document.getElementById('complete_request_id').value = requestId;
            completeModal.style.display = 'flex';
        }
        
        function closeCompleteModal() {
            completeModal.style.display = 'none';
        }

        document.addEventListener('click', function(event) {
            const profile = document.querySelector('.user-profile');
            if (profile && !profile.contains(event.target)) {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) dropdown.classList.remove('active');
            }
            if (event.target == completeModal) {
                closeCompleteModal();
            }
        });

    </script>
</body>
</html>