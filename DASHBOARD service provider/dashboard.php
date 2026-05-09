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
    // Handle case where provider data is missing, maybe log them out.
    session_destroy();
    header("Location: ../LOGIN%20SIGNUP/new_login.php?error=profile_not_found");
    exit();
}

// --- START: Handle Booking Actions ---
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id']) && isset($_POST['action'])) {
    if ($provider['admin_verification_status'] === 'Verified') {
        $req_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        
        $new_status = ($action === 'accept') ? 'Accepted' : (($action === 'decline') ? 'Declined' : '');
        
        if ($new_status) {
            // Get client details for notification
            $get_req = $conn->prepare("SELECT sr.client_id, COALESCE(ps.service_name, sr.service_needed) AS service_name FROM service_requests sr LEFT JOIN provider_services ps ON sr.service_id = ps.service_id WHERE sr.request_id = ?");
            $get_req->bind_param("i", $req_id);
            $get_req->execute();
            $req_data = $get_req->get_result()->fetch_assoc();
            $get_req->close();

            // Only update if it is currently 'Pending' to avoid race conditions
            $update_stmt = $conn->prepare("UPDATE service_requests SET status = ? WHERE request_id = ? AND provider_id = ? AND status = 'Pending'");
            $update_stmt->bind_param("sii", $new_status, $req_id, $user_id);
            if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                $action_message = "<div style='padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;'>Booking successfully " . strtolower($new_status) . "!</div>";
                
                // Insert notification for client
                if ($req_data) {
                    $notif_title = "Service Booking " . $new_status;
                    $notif_msg = "Your booking for '" . $req_data['service_name'] . "' has been " . strtolower($new_status) . " by the provider.";
                    $notif_type = "service_booking";
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, ?, ?, ?, ?)");
                    $notif_stmt->bind_param("isiss", $req_data['client_id'], $notif_type, $req_id, $notif_title, $notif_msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            } else {
                $action_message = "<div style='padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;'>Failed to update booking. It may have already been processed.</div>";
            }
            $update_stmt->close();
        }
    }
}
// --- END: Handle Booking Actions ---

// --- START: Load Real Data from Database ---
// Initialize variables
$new_requests_count = 0;
$completed_jobs_count = 0;
$overall_rating = 0;
$total_earnings_month = 0;
$new_requests = [];
$reviews = [];

// Only fetch detailed data if the provider is verified
if ($provider && $provider['admin_verification_status'] === 'Verified') {
    // New Booking Requests Count
    $stmt_req_count = $conn->prepare("SELECT COUNT(*) as count FROM service_requests WHERE provider_id = ? AND status = 'Pending'");
    $stmt_req_count->bind_param("i", $user_id);
    $stmt_req_count->execute();
    $new_requests_count = $stmt_req_count->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_req_count->close();

    // Completed Jobs Count
    $stmt_comp_count = $conn->prepare("SELECT COUNT(*) as count FROM service_requests WHERE provider_id = ? AND status = 'Completed'");
    $stmt_comp_count->bind_param("i", $user_id);
    $stmt_comp_count->execute();
    $completed_jobs_count = $stmt_comp_count->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_comp_count->close();

    // Overall Rating
    $stmt_rating = $conn->prepare("SELECT AVG(rating) as avg_rating FROM service_reviews WHERE provider_id = ?");
    $stmt_rating->bind_param("i", $user_id);
    $stmt_rating->execute();
    $avg_rating_result = $stmt_rating->get_result()->fetch_assoc()['avg_rating'];
    $overall_rating = $avg_rating_result ? round($avg_rating_result, 1) : 0;
    $stmt_rating->close();

    // This Month's Earnings
    $stmt_earnings = $conn->prepare("SELECT SUM(amount_charged) as total FROM service_requests WHERE provider_id = ? AND status = 'Completed' AND MONTH(scheduled_date) = MONTH(CURRENT_DATE()) AND YEAR(scheduled_date) = YEAR(CURRENT_DATE())");
    $stmt_earnings->bind_param("i", $user_id);
    $stmt_earnings->execute();
    $total_earnings_month = $stmt_earnings->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_earnings->close();

    // Fetch recent booking requests (limit 2 for dashboard)
    $stmt_requests = $conn->prepare("
        SELECT sr.request_id, sr.service_needed, sr.scheduled_date, sr.client_address, sr.client_message,
               COALESCE(js.first_name, emp.company_name, sp_client.first_name, 'Registered') as client_first_name, 
               COALESCE(js.last_name, '', sp_client.last_name, 'User') as client_last_name,
               COALESCE(uc.contact_value, 'N/A') as client_contact,
               ps.service_name
        FROM service_requests sr 
        LEFT JOIN provider_services ps ON sr.service_id = ps.service_id
        LEFT JOIN jobseekers js ON sr.client_id = js.seeker_id 
        LEFT JOIN employers emp ON sr.client_id = emp.employer_id
        LEFT JOIN service_providers sp_client ON sr.client_id = sp_client.provider_id
        LEFT JOIN user_contacts uc ON sr.client_id = uc.user_id AND uc.contact_type = 'Mobile'
        WHERE sr.provider_id = ? AND sr.status = 'Pending' ORDER BY sr.created_at DESC LIMIT 2");
    $stmt_requests->bind_param("i", $user_id);
    $stmt_requests->execute();
    $new_requests_result = $stmt_requests->get_result();
    while ($row = $new_requests_result->fetch_assoc()) { $new_requests[] = $row; }
    $stmt_requests->close();

    // Fetch recent reviews (limit 2 for dashboard)
    $stmt_reviews = $conn->prepare("SELECT rev.rating, rev.comment, COALESCE(js.first_name, emp.company_name, sp.first_name, 'Registered') as client_first_name, COALESCE(js.last_name, '', sp.last_name, 'User') as client_last_name FROM service_reviews rev LEFT JOIN jobseekers js ON rev.client_id = js.seeker_id LEFT JOIN employers emp ON rev.client_id = emp.employer_id LEFT JOIN service_providers sp ON rev.client_id = sp.provider_id WHERE rev.provider_id = ? ORDER BY rev.created_at DESC LIMIT 2");
    $stmt_reviews->bind_param("i", $user_id);
    $stmt_reviews->execute();
    $reviews_result = $stmt_reviews->get_result();
    while ($row = $reviews_result->fetch_assoc()) { $reviews[] = $row; }
    $stmt_reviews->close();
}
// --- END: Load Real Data from Database ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PESO Bongabon</title>
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
        .verified-badge { background: #dcfce7; color: #166534; padding: 0.25rem 0.75rem; font-size: 0.8rem; font-weight: 600; border-radius: 99px; display: inline-flex; align-items: center; gap: 0.25rem; }

        /* Verification Banner */
        .verification-banner { padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid; }
        .banner-pending { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .banner-rejected { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .verification-banner h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem; }
        .verification-banner p { font-size: 0.9rem; }

        /* Disabled Content */
        .content-disabled { opacity: 0.5; pointer-events: none; filter: blur(2px); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; }
        .stat-card h4 { font-size: 0.875rem; color: #6b7280; font-weight: 500; margin-bottom: 0.5rem; }
        .stat-value { font-size: 2rem; font-weight: 800; color: #111827; }
        .stat-card .rating { display: flex; align-items: center; gap: 0.5rem; }
        .stat-card .stars { color: #f59e0b; }

        /* Section */
        .section { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 2rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-title { font-size: 1.25rem; font-weight: 700; }
        .view-all-btn { text-decoration: none; color: #2563eb; font-weight: 600; font-size: 0.875rem; }

        /* Booking & Review Cards */
        .booking-card, .review-card { border: 1px solid #e5e7eb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .booking-card:last-child, .review-card:last-child { margin-bottom: 0; }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .client-name { font-weight: 600; color: #111827; }
        .service-date { font-size: 0.8rem; color: #6b7280; }
        .service-details { font-size: 0.9rem; color: #4b5563; margin-bottom: 1rem; }
        .card-actions { display: flex; gap: 0.5rem; }
        .btn-action { padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-accept { background: #10b981; color: white; }
        .btn-decline { background: #f3f4f6; color: #4b5563; }

        .review-card .rating { color: #f59e0b; margin-bottom: 0.5rem; }
        .review-comment { font-style: italic; color: #4b5563; }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
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
            <a href="dashboard.php" class="nav-item active">
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <?php if ($provider['admin_verification_status'] === 'Verified'): ?>
                <div class="verified-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1rem; height: 1rem;">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    PESO Verified
                </div>
            <?php endif; ?>
        </div>

        <!-- Verification Banner -->
        <?php if ($provider['admin_verification_status'] === 'Pending'): ?>
            <div class="verification-banner banner-pending" style="text-align: center; padding: 3rem 1.5rem;">
                <h3 style="font-size: 1.5rem;">Account Under Review</h3>
                <p style="font-size: 1rem; max-width: 600px; margin: 0.5rem auto 0;">Your account and submitted documents are currently being reviewed by PESO Bongabon. You will be notified via email once the verification is complete. All features are disabled until your account is approved.</p>
            </div>

        <?php elseif ($provider['admin_verification_status'] === 'Rejected'): ?>
            <div class="verification-banner banner-rejected" style="text-align: center; padding: 3rem 1.5rem;">
                <h3 style="font-size: 1.5rem;">Account Application Rejected</h3>
                <p style="font-size: 1rem; max-width: 600px; margin: 0.5rem auto 0;">We're sorry, but your application was not approved at this time. Please check your email for details or contact PESO Bongabon directly for more information.</p>
            </div>

        <?php elseif ($provider['admin_verification_status'] === 'Verified'): ?>
            <!-- Main Dashboard Content for Verified Users -->
            <div id="dashboard-overview" class="dashboard-section">
            <?php echo $action_message; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>New Booking Requests</h4>
                    <p class="stat-value"><?php echo number_format($new_requests_count); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Overall Rating</h4>
                    <div class="rating">
                        <p class="stat-value"><?php echo $overall_rating; ?></p>
                        <span class="stars">★★★★★</span>
                    </div>
                </div>
                <div class="stat-card">
                    <h4>Jobs Completed</h4>
                    <p class="stat-value"><?php echo number_format($completed_jobs_count); ?></p>
                </div>
                <div class="stat-card">
                    <h4>This Month's Earnings</h4>
                    <p class="stat-value">₱<?php echo number_format($total_earnings_month, 2); ?></p>
                </div>
            </div>

            <!-- Booking Management Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">New Booking Requests (<?php echo count($new_requests); ?> shown)</h2>
                    <a href="#" class="view-all-btn">View All &rarr;</a>
                </div>
                <div class="booking-list">
                    <?php if (!empty($new_requests)): ?>
                        <?php foreach ($new_requests as $request): ?>
                            <div class="booking-card">
                                <div class="card-header">
                                    <span class="client-name"><?php echo htmlspecialchars($request['client_first_name'] . ' ' . $request['client_last_name']); ?></span>
                                    <span class="service-date"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($request['scheduled_date']))); ?></span>
                                </div>
                                <p class="service-details" style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($request['service_name'] ?? $request['service_needed']); ?></p>
                                
                                <div style="background: #f9fafb; border: 1px solid #f3f4f6; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem;">
                                    <p style="margin:0 0 0.5rem 0;"><strong>Address:</strong> <?php echo htmlspecialchars($request['client_address']); ?></p>
                                    <?php if(!empty($request['client_message'])): ?>
                                        <p style="margin:0; font-style: italic; color: #4b5563;"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($request['client_message'])); ?></p>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <div class="card-actions">
                                        <button type="submit" name="action" value="accept" class="btn-action btn-accept" onclick="return confirm('Are you sure you want to accept this booking request?');">Accept Booking</button>
                                        <button type="submit" name="action" value="decline" class="btn-action btn-decline" onclick="return confirm('Are you sure you want to decline this request?');">Decline</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem 0;">No new booking requests.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reputation & Performance Board -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Recent Client Feedback</h2>
                    <a href="#" class="view-all-btn">View All &rarr;</a>
                </div>
                <div class="review-list">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="card-header">
                                    <span class="client-name"><?php echo htmlspecialchars($review['client_first_name'] . ' ' . $review['client_last_name']); ?></span>
                                    <div class="rating">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <span style="color: <?php echo $i < $review['rating'] ? '#f59e0b' : '#d1d5db'; ?>;">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <p class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #6b7280; padding: 2rem 0;">No reviews yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </main>

    <script>
        // Simple script for potential mobile sidebar toggle
        function toggleSidebar() {
            // Logic to show/hide sidebar on mobile would go here
        }
        
        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }

        document.addEventListener('click', function(event) {
            const profile = document.querySelector('.user-profile');
            if (profile && !profile.contains(event.target)) {
                document.getElementById('profileDropdown').classList.remove('active');
            }
        });
    </script>
</body>
</html>



































































































































































































































































































































































































-