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
        $deployment_datetime = date('Y-m-d H:i:s', strtotime("$date $time"));
        
        $upd_stmt = $conn->prepare("UPDATE referrals_applications SET status = 'For Deployment', deployment_date = ?, deployment_message = ? WHERE application_id = ?");
        $upd_stmt->bind_param("ssi", $deployment_datetime, $message_text, $application_id);
        $upd_stmt->execute();
        $upd_stmt->close();

        // Notification and Email for Job Seeker
        $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ({$app_data['seeker_id']}, 'referral', $application_id, 'Deployment Scheduled', 'You have been scheduled for deployment for {$app_data['job_title']} at {$app_data['company_name']} on $schedule.')");
        
        if ($app_data['email_notifications'] && !empty($app_data['email'])) {
            $body = "<h3>Deployment Scheduled</h3><p>Dear {$app_data['first_name']},</p><p>You have been scheduled for deployment for the position of <strong>{$app_data['job_title']}</strong> at <strong>{$app_data['company_name']}</strong>.</p><p><strong>Date & Time:</strong> $schedule</p>";
            if (!empty($message_text)) { $body .= "<p><strong>Instructions:</strong><br>" . nl2br(htmlspecialchars($message_text)) . "</p>"; }
            $body .= "<br><p>Best regards,<br>{$app_data['company_name']}</p>";
            sendEmail($app_data['email'], "Deployment Schedule - {$app_data['company_name']}", $body);
        }
        
        // Notify PESO Admin
        $adm_q = $conn->query("SELECT u.user_id, uc.contact_value FROM users u LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1 WHERE u.role = 'Admin' LIMIT 1");
        if ($adm_q && $adm_q->num_rows > 0) {
            $adm_data = $adm_q->fetch_assoc();
            $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ({$adm_data['user_id']}, 'hired', $application_id, 'Deployment Scheduled', 'Employer {$app_data['company_name']} scheduled {$app_data['first_name']} ({$app_data['job_title']}) for deployment on $schedule.')");
            if (!empty($adm_data['contact_value'])) sendEmail($adm_data['contact_value'], "Applicant Scheduled for Deployment - {$app_data['company_name']}", "<h3>Deployment Notification</h3><p>An employer has scheduled a PESO referred applicant for deployment.</p><p><strong>Applicant:</strong> " . htmlspecialchars($app_data['first_name']) . "</p><p><strong>Employer:</strong> " . htmlspecialchars($app_data['company_name']) . "</p><p><strong>Position:</strong> " . htmlspecialchars($app_data['job_title']) . "</p><p><strong>Date & Time:</strong> $schedule</p>");
        }

        echo "Deployment schedule sent to the applicant and PESO Admin!";
    } else { echo "Application not found or unauthorized."; }
} else { echo "Invalid request."; }
?>