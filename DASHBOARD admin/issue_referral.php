<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $app_id = (int)$_POST['application_id'];
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    $is_scheduled = !empty($date) && !empty($time);
    if ($is_scheduled) {
        date_default_timezone_set('Asia/Manila');
        if (strtotime("$date $time") < time()) {
            http_response_code(400); // Bad Request
            exit("Error: The selected schedule has already passed. Please choose a future time.");
        }
        
        $schedule = date('F d, Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($time));
    }

    // Update status to 'Referral_Issued'
    $stmt = $conn->prepare("UPDATE referrals_applications SET status = 'Referral_Issued' WHERE application_id = ?");
    $stmt->bind_param("i", $app_id);
    
    if ($stmt->execute()) {
        // Fetch details for email notification
        $info_stmt = $conn->prepare("
            SELECT 
                e.company_name, 
                e.email_address, 
                uc.contact_value as employer_login_email,
                jp.job_title, 
                js.first_name, 
                js.last_name,
                js.seeker_id,
                js.is_verified,
                uc_js.contact_value as seeker_email,
                u_js.email_notifications as seeker_email_notifications
            FROM referrals_applications ra
            JOIN job_postings jp ON ra.job_id = jp.job_id
            JOIN employers e ON jp.employer_id = e.employer_id
            JOIN jobseekers js ON ra.seeker_id = js.seeker_id
            JOIN users u_js ON js.seeker_id = u_js.user_id
            LEFT JOIN user_contacts uc ON e.employer_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1
            LEFT JOIN user_contacts uc_js ON js.seeker_id = uc_js.user_id AND uc_js.contact_type = 'Email' AND uc_js.is_primary = 1
            WHERE ra.application_id = ?
        ");
        $info_stmt->bind_param("i", $app_id);
        $info_stmt->execute();
        $info = $info_stmt->get_result()->fetch_assoc();
        $info_stmt->close();

        if ($info) {
            // 1. Notify Employer
            $emp_to = !empty($info['employer_login_email']) ? $info['employer_login_email'] : $info['email_address'];
            
            if (!empty($emp_to)) {
                $subject = "PESO Referral Notification - " . $info['first_name'] . " " . $info['last_name'];
                $body = "<h3>Referral Notification</h3>";
                $body .= "<p>Dear " . htmlspecialchars($info['company_name']) . ",</p>";
                $body .= "<p>PESO Bongabon has referred an applicant for your job posting: <strong>" . htmlspecialchars($info['job_title']) . "</strong>.</p>";
                $body .= "<p><strong>Applicant:</strong> " . htmlspecialchars($info['first_name'] . " " . $info['last_name']) . "</p>";
                $body .= "<p>The applicant will visit your office in person for an interview and present the official <strong>physical Referral Letter</strong> signed by the PESO Manager.</p>";
                $body .= "<p>After concluding your interview and assessment, please log in to your employer dashboard to view their resume and update their final status to <strong>Hired</strong> or <strong>Rejected</strong>.</p>";
                $body .= "<br><p>Best regards,<br>PESO Bongabon</p>";
                
                sendEmail($emp_to, $subject, $body);
            }

            // 2. Notify Job Seeker
            $req_msg = ($info['is_verified'] == 0) ? "submit your hardcopy requirements and claim your Referral Letter" : "claim your official signed Referral Letter";
            $seeker_title = "Referral Issued";
            if ($is_scheduled) {
                $seeker_title .= " & Visit Schedule";
                $seeker_message = "Good news! You have been referred for " . $info['job_title'] . " at " . $info['company_name'] . ". Please visit the PESO Office on " . $schedule . " to " . $req_msg . ".";
            } else {
                $seeker_message = "Congratulations! You have been referred for " . $info['job_title'] . " at " . $info['company_name'] . ". Your official signed Referral Letter is now ready, which you will use as your credential for the employer.";
            }

            // Insert Notification in Database
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'referral', ?, ?, ?)");
            $notif_stmt->bind_param("iiss", $info['seeker_id'], $app_id, $seeker_title, $seeker_message);
            $notif_stmt->execute();
            $notif_stmt->close();

            // Send Email to Job Seeker
            if ($info['seeker_email_notifications'] && !empty($info['seeker_email'])) {
                $subject_js = "PESO Bongabon - " . $seeker_title;
                if ($is_scheduled) {
                    $body_js = "<h3>Good news, " . htmlspecialchars($info['first_name']) . "!</h3>";
                    $body_js .= "<p>You have been referred for the position of <strong>" . htmlspecialchars($info['job_title']) . "</strong> at <strong>" . htmlspecialchars($info['company_name']) . "</strong>.</p>";
                    $body_js .= "<p><strong>Your PESO Visit Schedule:</strong> " . $schedule . "</p>";
                    
                    if ($info['is_verified'] == 0) {
                        $body_js .= "<p>Please visit the PESO Bongabon office on your scheduled date to submit your hardcopy requirements and to claim your official signed Referral Letter.</p>";
                        $body_js .= "<p><strong>Please bring the following requirements:</strong></p>";
                        $body_js .= "<ul><li>Updated Resume / CV</li><li>Valid Government-issued ID</li><li>Diploma / Transcript of Records (TOR)</li><li>PSA Birth Certificate</li><li>Certificate of Employment (if applicable)</li><li>2x2 ID Picture</li><li>Barangay Residency</li></ul>";
                    } else {
                        $body_js .= "<p>Please visit the PESO Bongabon office on your scheduled date to claim your official signed Referral Letter before proceeding to the employer for your interview.</p>";
                        $body_js .= "<p><strong>Please bring a Valid ID for verification purposes.</strong></p>";
                    }
                } else {
                    $body_js = "<h3>Congratulations, " . htmlspecialchars($info['first_name']) . "!</h3>";
                    $body_js .= "<p>You have been successfully referred for the position of <strong>" . htmlspecialchars($info['job_title']) . "</strong> at <strong>" . htmlspecialchars($info['company_name']) . "</strong>.</p>";
                    $body_js .= "<p>Your official signed Referral Letter is now ready!</p>";
                    $body_js .= "<p>Please present this letter to the employer as your official credential from PESO Bongabon for your application and interview.</p>";
                    
                    if ($info['is_verified'] == 0) {
                        $body_js .= "<p><em>Note: Since your account is not yet fully verified, please bring your hardcopy requirements (Resume, Valid ID, TOR/Diploma, Birth Certificate, etc.) when you claim your letter.</em></p>";
                    }
                }
                
                $body_js .= "<br><p>Best regards,<br>PESO Bongabon</p>";
                sendEmail($info['seeker_email'], $subject_js, $body_js);
            }
        }

        echo "Referral Issued! The Job Seeker has been scheduled and notified.";
    } else {
        http_response_code(500);
        echo "Error issuing referral: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}
?>