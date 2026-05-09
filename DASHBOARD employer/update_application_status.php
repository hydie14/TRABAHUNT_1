<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

// Ensure Employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    http_response_code(403);
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['status'])) {
    $application_id = (int)$_POST['application_id'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];

    // Validate status
    if (!in_array($status, ['Hired', 'Rejected'])) {
        http_response_code(400);
        exit('Invalid status provided.');
    }

    // Determine Employer ID
    // (Handles cases where employer_id might be different from user_id, or same)
    $employer_id = $user_id;
    $check_emp = $conn->query("SHOW COLUMNS FROM employers LIKE 'user_id'");
    if ($check_emp->num_rows > 0) {
        $get_id = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
        $get_id->bind_param("i", $user_id);
        $get_id->execute();
        $res = $get_id->get_result();
        if ($res->num_rows > 0) {
            $employer_id = $res->fetch_assoc()['employer_id'];
        }
        $get_id->close();
    }

    $final_status = $status;
    // Prepare placement date update if status is Hired
    $placement_sql = "";
    if ($status === 'Hired') {
        $final_status = 'Hired / Placed'; // Standardize status for consistency across dashboards
        $placement_sql = ", ra.placement_date = NOW()";
    }

    // Update Status
    // We join with job_postings to ensure the employer owns the job for this application
    $stmt = $conn->prepare("
        UPDATE referrals_applications ra
        JOIN job_postings jp ON ra.job_id = jp.job_id
        SET ra.status = ? $placement_sql
        WHERE ra.application_id = ? AND jp.employer_id = ?
    ");
    $stmt->bind_param("sii", $final_status, $application_id, $employer_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "Application status updated to " . htmlspecialchars($status);
            
            // Notify Job Seeker
            $notif_query = $conn->prepare("
                SELECT ra.seeker_id, js.first_name, js.last_name, uc.contact_value as email, 
                       jp.job_title, jp.employment_type, e.company_name, u.email_notifications
                FROM referrals_applications ra
                JOIN jobseekers js ON ra.seeker_id = js.seeker_id
                JOIN job_postings jp ON ra.job_id = jp.job_id
                JOIN employers e ON jp.employer_id = e.employer_id
                JOIN users u ON ra.seeker_id = u.user_id
                LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email'
                WHERE ra.application_id = ?
            ");
            $notif_query->bind_param("i", $application_id);
            $notif_query->execute();
            $app_data = $notif_query->get_result()->fetch_assoc();
            $notif_query->close();

            if ($app_data) {
                $seeker_id = $app_data['seeker_id'];
                $title = ($status == 'Hired') ? "Congratulations! You're Hired" : "Application Update";
                $message = ($status == 'Hired') 
                    ? "Congratulations! You have been hired for the position of " . $app_data['job_title'] . " at " . $app_data['company_name'] . "."
                    : "Your application for " . $app_data['job_title'] . " at " . $app_data['company_name'] . " was not successful.";
                $type = ($status == 'Hired') ? 'hired' : 'rejected';

                // Insert Notification
                $ins_notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, ?, ?, ?, ?)");
                $ins_notif->bind_param("isiss", $seeker_id, $type, $application_id, $title, $message);
                $ins_notif->execute();
                $ins_notif->close();

                // Send Email
                if ($app_data['email_notifications'] && !empty($app_data['email'])) {
                    $email_subject = "PESO Bongabon - " . $title;
                    $email_body = "<h3>Hello " . htmlspecialchars($app_data['first_name']) . ",</h3><p>" . htmlspecialchars($message) . "</p><br><p>Best regards,<br>PESO Bongabon</p>";
                    sendEmail($app_data['email'], $email_subject, $email_body);
                }

                // If hired for a Full-Time role, Auto-Cancel other pending applications
                if ($status === 'Hired' && in_array($app_data['employment_type'], ['Permanent', 'Contractual'])) {
                    $cancel_stmt = $conn->prepare("
                        UPDATE referrals_applications 
                        SET status = 'Cancelled' 
                        WHERE seeker_id = ? AND application_id != ? 
                        AND status IN ('Pending', 'Pending_Docs', 'Verified', 'Referral_Issued', 'Issue Referral Letter', 'Pending Interview')
                    ");
                    $cancel_stmt->bind_param("ii", $seeker_id, $application_id);
                    $cancel_stmt->execute();
                    $cancel_stmt->close();
                }

                // Notify Admin for ALL status updates (Hired or Rejected)
                $adm_q = $conn->query("SELECT u.user_id, uc.contact_value as email FROM users u LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1 WHERE u.role = 'Admin' LIMIT 1");
                if ($adm_q && $adm_q->num_rows > 0) {
                    $adm_data = $adm_q->fetch_assoc();
                    $action_word = ($status == 'Hired') ? 'Hired' : 'Rejected';
                    $adm_msg = "Applicant {$app_data['first_name']} {$app_data['last_name']} has been {$action_word} by {$app_data['company_name']} for the position of {$app_data['job_title']}.";
                    
                    // Admin In-App
                    $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ({$adm_data['user_id']}, 'status_update', $application_id, 'Applicant {$action_word}', '" . $conn->real_escape_string($adm_msg) . "')");
                    
                    // Admin Email
                    if (!empty($adm_data['email'])) {
                        sendEmail($adm_data['email'], "Applicant {$action_word} - PESO Bongabon", "<h3>Applicant {$action_word} Notification</h3><p>$adm_msg</p><p>Please review the details in your Admin Dashboard.</p>");
                    }
                }
            }
        } else {
            // This happens if the application_id doesn't exist or doesn't belong to this employer
            http_response_code(403);
            echo "No changes made. You may not be authorized to update this application.";
        }
    } else {
        http_response_code(500);
        echo "Database error: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}
?>