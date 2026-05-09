<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    http_response_code(403);
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $message_text = $_POST['message'] ?? '';
    $employer_id = $_SESSION['user_id'];

    if ($application_id <= 0 || empty($date) || empty($time)) { exit('Please provide valid date and time.'); }

    date_default_timezone_set('Asia/Manila');
    if (strtotime("$date $time") < time()) {
        echo "Error: The selected schedule has already passed. Please choose a future time.";
        exit;
    }

    // Verify ownership and get details
    $stmt = $conn->prepare("
        SELECT ra.seeker_id, js.first_name, uc.contact_value as email, jp.job_title, e.company_name, u.email_notifications
        FROM referrals_applications ra
        JOIN job_postings jp ON ra.job_id = jp.job_id
        JOIN employers e ON jp.employer_id = e.employer_id
        JOIN jobseekers js ON ra.seeker_id = js.seeker_id
        JOIN users u ON ra.seeker_id = u.user_id
        LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email'
        WHERE ra.application_id = ? AND e.employer_id = ?
    ");
    $stmt->bind_param("ii", $application_id, $employer_id);
    $stmt->execute();
    $app_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($app_data) {
        $schedule = date('F d, Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($time));
        $interview_datetime = date('Y-m-d H:i:s', strtotime("$date $time"));
        
        $upd_stmt = $conn->prepare("UPDATE referrals_applications SET status = 'Pending Interview', interview_date = ?, interview_message = ? WHERE application_id = ?");
        $upd_stmt->bind_param("ssi", $interview_datetime, $message_text, $application_id);
        $upd_stmt->execute();
        $upd_stmt->close();

        // Notification and Email
        $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ({$app_data['seeker_id']}, 'referral', $application_id, 'Interview Scheduled', 'You have an interview scheduled for {$app_data['job_title']} at {$app_data['company_name']} on $schedule.')");
        
        if ($app_data['email_notifications'] && !empty($app_data['email'])) {
            $body = "<h3>Interview Scheduled</h3><p>Dear {$app_data['first_name']},</p><p>You have been scheduled for an interview for the position of <strong>{$app_data['job_title']}</strong> at <strong>{$app_data['company_name']}</strong>.</p><p><strong>Date & Time:</strong> $schedule</p>";
            if (!empty($message_text)) { $body .= "<p><strong>Message / Details:</strong><br>" . nl2br(htmlspecialchars($message_text)) . "</p>"; }
            $body .= "<p>Please ensure you bring your official Referral Letter obtained from the PESO Office and other necessary documents.</p><br><p>Best regards,<br>{$app_data['company_name']}</p>";
            sendEmail($app_data['email'], "Interview Schedule - {$app_data['company_name']}", $body);
        }
        
        // Notify PESO Admin
        $admin_stmt = $conn->prepare("SELECT u.user_id, uc.contact_value FROM users u LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1 WHERE u.role = 'Admin' LIMIT 1");
        $admin_stmt->execute();
        $admin_res = $admin_stmt->get_result();
        if ($admin_res->num_rows > 0) {
            $admin_row = $admin_res->fetch_assoc();
            $admin_user_id = $admin_row['user_id'];
            $admin_email = $admin_row['contact_value'];

            $admin_subject = "Interview Scheduled - {$app_data['company_name']}";
            $admin_body = "<h3>Interview Notification</h3>";
            $admin_body .= "<p>An employer has scheduled an interview for a PESO referred applicant.</p>";
            $admin_body .= "<p><strong>Applicant:</strong> " . htmlspecialchars($app_data['first_name']) . "</p>";
            $admin_body .= "<p><strong>Employer:</strong> " . htmlspecialchars($app_data['company_name']) . "</p>";
            $admin_body .= "<p><strong>Position:</strong> " . htmlspecialchars($app_data['job_title']) . "</p>";
            $admin_body .= "<p><strong>Date & Time:</strong> $schedule</p>";
            
            if (!empty($admin_email)) {
                sendEmail($admin_email, $admin_subject, $admin_body);
            }

            // Admin In-App Notification
            $notif_msg = "Employer {$app_data['company_name']} scheduled an interview for {$app_data['first_name']} ({$app_data['job_title']}) on $schedule.";
            $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ($admin_user_id, 'interview', $application_id, 'Interview Scheduled', '" . $conn->real_escape_string($notif_msg) . "')");
        }
        $admin_stmt->close();

        echo "Interview schedule sent to the applicant!";
    } else { echo "Application not found or unauthorized."; }
} else { echo "Invalid request."; }
?>