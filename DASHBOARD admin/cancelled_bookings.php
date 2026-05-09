<?php
session_start();
include '../DATABASE/db_connect.php';

// Siguraduhing admin ang naka-login. I-uncomment ito kapag may admin session na.
/*
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}
*/

// Fetch cancelled bookings
$stmt = $conn->prepare("
    SELECT 
        sr.request_id,
        sr.service_needed,
        sr.scheduled_date,
        sr.client_message,
        sr.updated_at as cancellation_date,
        p.first_name AS provider_first_name,
        p.last_name AS provider_last_name,
        COALESCE(js.first_name, emp.company_name, 'Unknown Client') AS client_first_name,
        COALESCE(js.last_name, '') AS client_last_name,
        ps.service_name
    FROM service_requests sr
    JOIN service_providers p ON sr.provider_id = p.provider_id
    LEFT JOIN provider_services ps ON sr.service_id = ps.service_id
    LEFT JOIN jobseekers js ON sr.client_id = js.seeker_id
    LEFT JOIN employers emp ON sr.client_id = emp.employer_id
    WHERE sr.status = 'Cancelled'
    ORDER BY sr.updated_at DESC
");
$stmt->execute();
$cancelled_bookings = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelled Bookings - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; }
        .main-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 1.5rem; }
        .section { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        .reason { color: #b91c1c; font-style: italic; max-width: 300px; }
    </style>
</head>
<body>
    <main class="main-content">
        <h1 class="page-title">Cancelled Service Bookings Report</h1>
        <div class="section">
            <?php if ($cancelled_bookings->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Client</th><th>Provider</th><th>Service</th><th>Scheduled Date</th><th>Cancellation Date</th><th>Reason</th></tr>
                        </thead>
                        <tbody>
                            <?php while($booking = $cancelled_bookings->fetch_assoc()): ?>
                                <?php
                                    $full_message = $booking['client_message'];
                                    $reason_pos = strpos($full_message, '[Cancellation Reason]:');
                                    $reason = ($reason_pos !== false) ? trim(substr($full_message, $reason_pos + strlen('[Cancellation Reason]:'))) : 'Not specified by client.';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['client_first_name'] . ' ' . $booking['client_last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['provider_first_name'] . ' ' . $booking['provider_last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service_name'] ?? $booking['service_needed']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($booking['scheduled_date'])); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($booking['cancellation_date'])); ?></td>
                                    <td class="reason"><?php echo htmlspecialchars($reason); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; text-align: center; padding: 2rem 0;">No cancelled bookings found.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>