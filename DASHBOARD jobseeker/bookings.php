<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Check for a success message from the booking page
if (isset($_SESSION['booking_success'])) {
    $message = "<div class='alert alert-success'>" . $_SESSION['booking_success'] . "</div>";
    unset($_SESSION['booking_success']);
}

// --- Handle POST Actions (Review & Cancel) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle leaving a review
    if (isset($_POST['leave_review'])) {
        $request_id = (int)$_POST['request_id'];
        $provider_id = (int)$_POST['provider_id'];
        $rating = (int)$_POST['rating'];
        $comment = trim($_POST['comment']);

        if ($rating >= 1 && $rating <= 5) {
            $stmt = $conn->prepare("INSERT INTO service_reviews (request_id, provider_id, client_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $request_id, $provider_id, $user_id, $rating, $comment);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Thank you for your feedback!</div>";
            } else {
                $message = "<div class='alert alert-danger'>You have already submitted a review for this booking.</div>";
            }
            $stmt->close();
        }
    }

    // Handle cancelling a booking
    if (isset($_POST['cancel_booking'])) {
        $request_id = (int)$_POST['request_id'];
        $provider_id = (int)$_POST['provider_id'];
        $cancel_reason = trim($_POST['cancel_reason']);
        $appended_reason = "\n\n[Cancellation Reason]: " . $cancel_reason;

        // Fetch the scheduled date to ensure it's in the future
        $date_check_stmt = $conn->prepare("SELECT scheduled_date FROM service_requests WHERE request_id = ? AND client_id = ?");
        $date_check_stmt->bind_param("ii", $request_id, $user_id);
        $date_check_stmt->execute();
        $date_result = $date_check_stmt->get_result()->fetch_assoc();
        $date_check_stmt->close();

        if ($date_result && strtotime($date_result['scheduled_date']) > time()) {
            // Allow cancellation for Pending, Accepted, or Ongoing bookings
            $stmt = $conn->prepare("UPDATE service_requests SET status = 'Cancelled', client_message = CONCAT(COALESCE(client_message, ''), ?) WHERE request_id = ? AND client_id = ? AND status IN ('Pending', 'Accepted', 'Ongoing')");
            $stmt->bind_param("sii", $appended_reason, $request_id, $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "<div class='alert alert-success'>Booking successfully cancelled.</div>";

                // Notify the service provider
                $get_req = $conn->prepare("SELECT COALESCE(ps.service_name, sr.service_needed) AS service_name FROM service_requests sr LEFT JOIN provider_services ps ON sr.service_id = ps.service_id WHERE sr.request_id = ?");
                $get_req->bind_param("i", $request_id);
                $get_req->execute();
                $req_data = $get_req->get_result()->fetch_assoc();
                $get_req->close();

                $notif_title = "Booking Cancelled by Client";
                $notif_msg = "A booking for '" . ($req_data['service_name'] ?? 'a service') . "' has been cancelled by the client. Reason: " . $cancel_reason;
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'service_booking', ?, ?, ?)");
                $notif_stmt->bind_param("iiss", $provider_id, $request_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
            } else {
                $message = "<div class='alert alert-danger'>This booking cannot be cancelled.</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-danger'>This booking cannot be cancelled as it is already past its scheduled time.</div>";
        }
    }
}

// Fetch user details for the sidebar
$stmt_user = $conn->prepare("SELECT first_name, last_name FROM jobseekers WHERE seeker_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch all bookings made by the user
$query = "
    SELECT sr.request_id, sr.provider_id, sr.client_id, sr.service_needed, sr.scheduled_date, sr.client_address, sr.client_message, sr.status, sr.amount_charged, sr.created_at, sp.first_name, sp.last_name, sp.base_rate as general_rate, 
           ps.base_rate, ps.service_name,
           (SELECT review_id FROM service_reviews WHERE request_id = sr.request_id) as review_id,
           COALESCE(uc.contact_value, 'N/A') as provider_contact
    FROM service_requests sr
    JOIN service_providers sp ON sr.provider_id = sp.provider_id
    LEFT JOIN provider_services ps ON sr.service_id = ps.service_id
    LEFT JOIN user_contacts uc ON sp.provider_id = uc.user_id AND uc.contact_type = 'Mobile'
    WHERE sr.client_id = ?
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
$bookings = $stmt_bookings->get_result();
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
        .avatar { width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; color: #6b7280; flex-shrink: 0; overflow: hidden; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #111827; }
        .user-role { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 2rem; font-weight: 800; color: #111827; }
        
        .section { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 2rem; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; }
        .alert-success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border-color: #fecaca; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .btn-action { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-review { background: #f59e0b; color: white; }
        .btn-cancel { background: #ef4444; color: white; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .close-btn { color: #9ca3af; font-size: 2rem; font-weight: bold; cursor: pointer; }
        .rating-stars { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem; font-size: 2rem; color: #d1d5db; }
        .rating-stars .star { cursor: pointer; }
        .rating-stars .star.selected, .rating-stars .star:hover { color: #f59e0b; }
        .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 1rem; resize: vertical; }
        .form-actions { display: flex; justify-content: flex-end; margin-top: 1.5rem; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
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
            <a href="jobseeker_dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span> Dashboard
            </a>
            <a href="browse_services.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></span> Browse Services
            </a>
            <a href="bookings.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></span> My Bookings
            </a>
        </nav>
        <div class="user-profile" onclick="toggleProfileDropdown()">
            <div class="profile-dropdown" id="profileDropdown">
                <a href="../LOGIN%20SIGNUP/logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg> 
                    Logout
                </a>
            </div>
            <div class="avatar"><?php echo isset($user_data['first_name']) ? strtoupper(substr($user_data['first_name'], 0, 1)) : 'J'; ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . ($user_data['last_name'] ?? '')); ?></div>
                <div class="user-role">Job Seeker</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; margin-left: auto; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header"><h1 class="page-title">My Bookings</h1></div>
        <?php echo $message; ?>
        <div class="section">
            <?php if ($bookings->num_rows > 0): ?>
                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center;">
                    <input type="text" id="bookingSearch" onkeyup="filterBookings()" placeholder="Search by provider, service, or status..." style="flex: 1; min-width: 200px; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 0.95rem;">
                    
                    <form method="GET" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; margin: 0;">
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 0.95rem;">
                        <span style="color: #6b7280; font-weight: 500;">to</span>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 0.95rem;">
                        <button type="submit" style="background: #2563eb; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.95rem;">Filter Date</button>
                        <?php if(!empty($start_date) || !empty($end_date)): ?>
                            <a href="bookings.php" style="color: #ef4444; text-decoration: none; font-weight: 600; font-size: 0.9rem; margin-left: 0.5rem;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Provider</th><th>Service</th><th>Schedule</th><th>Status</th><th>Amount</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($booking = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 500;">
                                        <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                        <?php if (in_array($booking['status'], ['Accepted', 'Ongoing'])): ?>
                                            <div style="font-size: 0.85rem; color: #6b7280; font-weight: normal; margin-top: 0.25rem;">📞 <?php echo htmlspecialchars($booking['provider_contact']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="color: #111827; font-weight: 500;"><?php echo htmlspecialchars($booking['service_name'] ?? $booking['service_needed']); ?></div>
                                        <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem;">
                                            📍 <?php echo htmlspecialchars($booking['client_address'] ?? 'N/A'); ?>
                                        </div>
                                        <?php if(!empty($booking['client_message'])): ?>
                                            <div style="font-size: 0.85rem; color: #6b7280; font-style: italic; margin-top: 0.25rem;">
                                                "<?php echo nl2br(htmlspecialchars($booking['client_message'])); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($booking['scheduled_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $status_color = '#6b7280'; $status_bg = '#f3f4f6';
                                            if($booking['status'] == 'Pending') { $status_color = '#d97706'; $status_bg = '#fef3c7'; }
                                            elseif(in_array($booking['status'], ['Accepted', 'Ongoing'])) { $status_color = '#1d4ed8'; $status_bg = '#dbeafe'; }
                                            elseif($booking['status'] == 'Completed') { $status_color = '#059669'; $status_bg = '#d1fae5'; }
                                            elseif(in_array($booking['status'], ['Declined', 'Cancelled'])) { $status_color = '#dc2626'; $status_bg = '#fee2e2'; }
                                        ?>
                                        <span class="status-badge" style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>;"><?php echo htmlspecialchars($booking['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($booking['amount_charged']) {
                                                echo '₱' . number_format($booking['amount_charged'], 2);
                                            } elseif (!empty($booking['base_rate'])) {
                                                echo htmlspecialchars($booking['base_rate']);
                                            } elseif (!empty($booking['general_rate'])) {
                                                echo htmlspecialchars($booking['general_rate']);
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['status'] === 'Completed' && is_null($booking['review_id'])): ?>
                                            <button class="btn-action btn-review" onclick="openReviewModal(<?php echo $booking['request_id']; ?>, <?php echo $booking['provider_id']; ?>)">Leave a Review</button>
                                        <?php elseif (in_array($booking['status'], ['Pending', 'Accepted', 'Ongoing']) && strtotime($booking['scheduled_date']) > time()): ?>
                                            <button type="button" class="btn-action btn-cancel" onclick="openCancelModal(<?php echo $booking['request_id']; ?>, <?php echo $booking['provider_id']; ?>)">Cancel</button>
                                        <?php else: echo '<span style="color:#9ca3af;">-</span>'; endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; text-align: center; padding: 2rem 0;">You haven't booked any services yet. <a href="browse_services.php" style="color: #2563eb; font-weight: 600;">Browse services now</a>.</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Leave a Review</h2>
                <span class="close-btn" onclick="closeReviewModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="leave_review" value="1">
                <input type="hidden" name="request_id" id="review_request_id">
                <input type="hidden" name="provider_id" id="review_provider_id">
                <input type="hidden" name="rating" id="rating_value" value="0">
                <div class="form-group">
                    <label style="display: block; text-align: center; margin-bottom: 1rem; font-weight: 600;">Your Rating</label>
                    <div class="rating-stars" id="ratingStars">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Comment (Optional)</label>
                    <textarea name="comment" rows="4" placeholder="Share your experience..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-action btn-review" style="padding: 0.75rem 1.5rem;">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cancel Booking</h2>
                <span class="close-btn" onclick="closeCancelModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="cancel_booking" value="1">
                <input type="hidden" name="request_id" id="cancel_request_id">
                <input type="hidden" name="provider_id" id="cancel_provider_id">
                <div class="form-group">
                    <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Reason for Cancellation <span style="color: #ef4444;">*</span></label>
                    <textarea name="cancel_reason" rows="4" placeholder="Please state your reason for cancelling this booking..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-action btn-cancel" style="padding: 0.75rem 1.5rem;">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const reviewModal = document.getElementById('reviewModal');
        const cancelModal = document.getElementById('cancelModal');
        const ratingStars = document.querySelectorAll('#ratingStars .star');
        const ratingValueInput = document.getElementById('rating_value');

        function openReviewModal(requestId, providerId) {
            document.getElementById('review_request_id').value = requestId;
            document.getElementById('review_provider_id').value = providerId;
            reviewModal.style.display = 'flex';
        }

        function closeReviewModal() {
            reviewModal.style.display = 'none';
            ratingValueInput.value = 0;
            ratingStars.forEach(s => s.classList.remove('selected'));
        }

        function openCancelModal(requestId, providerId) {
            document.getElementById('cancel_request_id').value = requestId;
            document.getElementById('cancel_provider_id').value = providerId;
            cancelModal.style.display = 'flex';
        }

        function closeCancelModal() {
            cancelModal.style.display = 'none';
        }

        ratingStars.forEach(star => {
            star.addEventListener('click', () => {
                const value = star.getAttribute('data-value');
                ratingValueInput.value = value;
                ratingStars.forEach(s => {
                    s.classList.toggle('selected', s.getAttribute('data-value') <= value);
                });
            });
        });

        function filterBookings() {
            const input = document.getElementById('bookingSearch');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row => {
                const textContent = row.textContent.toLowerCase();
                if (textContent.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        window.onclick = function(event) {
            if (event.target == reviewModal) {
                closeReviewModal();
            }
            if (event.target == cancelModal) {
                closeCancelModal();
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