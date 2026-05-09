<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in and is a Job Seeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($service_id === 0) {
    header("Location: browse_services.php?error=invalid_service");
    exit();
}

// Fetch Service and Provider Details
$stmt = $conn->prepare("
    SELECT ps.service_id, ps.service_name, ps.provider_id, sp.first_name, sp.last_name
    FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.provider_id
    WHERE ps.service_id = ? AND sp.admin_verification_status = 'Verified' AND ps.status = 'Active'
");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    header("Location: browse_services.php?error=service_not_found");
    exit();
}

$message = '';
// Handle Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    date_default_timezone_set('Asia/Manila');
    $schedule = $_POST['schedule'];
    $details = trim($_POST['details']);
    $address = trim($_POST['address']);
    $message_note = trim($_POST['message']);

    // Check for existing ongoing/accepted booking for the same service and provider
    $check_stmt = $conn->prepare("
        SELECT request_id FROM service_requests 
        WHERE client_id = ? AND provider_id = ? AND status IN ('Accepted', 'Ongoing')
    ");
    $check_stmt->bind_param("ii", $user_id, $service['provider_id']);
    $check_stmt->execute();
    $existing_booking = $check_stmt->get_result();
    $check_stmt->close();

    if ($existing_booking->num_rows > 0) {
        $message = "<div class='alert alert-danger'>You already have an ongoing or accepted booking with this provider. Please wait for it to be completed before booking another service from them.</div>";
    } elseif (empty($schedule) || empty($details) || empty($address)) {
        $message = "<div class='alert alert-danger'>Please provide a schedule, details, and your exact address.</div>";
    } elseif (strtotime($schedule) < time()) {
        $message = "<div class='alert alert-danger'>You cannot book a schedule in the past. Please select a future date and time.</div>";
    } else {
        // Pagsamahin ang details at message note
        $final_message = $details;
        if (!empty($message_note)) {
            $final_message .= "\n\nAdditional Note: " . $message_note;
        }

        $stmt_insert = $conn->prepare("INSERT INTO service_requests (provider_id, client_id, service_id, service_needed, scheduled_date, client_address, client_message, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt_insert->bind_param("iiissss", $service['provider_id'], $user_id, $service['service_id'], $service['service_name'], $schedule, $address, $final_message);
        
        if ($stmt_insert->execute()) {
            // Redirect to bookings page with a success message
            $_SESSION['booking_success'] = "Your request for '" . htmlspecialchars($service['service_name']) . "' has been sent to the provider!";
            header("Location: jobseeker_dashboard.php");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>There was an error sending your request. Please try again.</div>";
        }
        $stmt_insert->close();
    }
}

// Fetch user details for the sidebar
$stmt_user = $conn->prepare("SELECT first_name, last_name FROM jobseekers WHERE seeker_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Service - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }
        
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 2rem; font-weight: 800; color: #111827; }
        .section { background: white; padding: 2rem; border-radius: 12px; border: 1px solid #e5e7eb; max-width: 700px; margin: 0 auto; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 1rem; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .btn-submit { background: #2563eb; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 1rem; width: 100%; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border-color: #fecaca; }
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
            <a href="browse_services.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></span> Browse Services
            </a>
            <a href="bookings.php" class="nav-item">
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
        <div class="page-header"><h1 class="page-title">Book a Service</h1></div>
        
        <div class="section">
            <div style="margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($service['service_name']); ?></h2>
                <p style="color: #6b7280;">by <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?></p>
            </div>

            <?php echo $message; ?>

            <form method="POST" onsubmit="return validateBookingTime()">
                <input type="hidden" name="submit_booking" value="1">
                <div class="form-group">
                    <label for="schedule">Preferred Date & Time</label>
                    <input type="datetime-local" id="schedule" name="schedule" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="details">Service Details / What do you need?</label>
                    <textarea id="details" name="details" rows="4" placeholder="e.g., 'My kitchen sink is clogged and leaking.' or 'I need a haircut for an event.'" required></textarea>
                </div>
                <div class="form-group">
                    <label for="address">Exact Address / Landmark</label>
                    <input type="text" id="address" name="address" placeholder="e.g., 123 Rizal St., Brgy. San Juan, near the plaza" required>
                </div>
                <div class="form-group">
                    <label for="message">Additional Message for Provider (Optional)</label>
                    <textarea id="message" name="message" rows="3" placeholder="Any other details you want the provider to know..."></textarea>
                </div>
                <button type="submit" class="btn-submit">Send Booking Request</button>
            </form>
        </div>
    </main>
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

        function validateBookingTime() {
            const scheduleInput = document.getElementById('schedule').value;
            if (scheduleInput) {
                const selectedDate = new Date(scheduleInput);
                if (selectedDate < new Date()) {
                    alert("Error: The selected time has already passed. Please choose a future schedule.");
                    return false;
                }
            }
            return true;
        }
    </script>
</body>
</html>