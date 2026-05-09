<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id']) && isset($_POST['action'])) {
    $job_id = (int)$_POST['job_id'];
    $action = $_POST['action'];
    
    $new_status = '';
    if ($action === 'approve') {
        $new_status = 'Active';
    } elseif ($action === 'reject') {
        $new_status = 'Rejected';
    }

    if ($new_status && $job_id > 0) {
        $stmt = $conn->prepare("UPDATE job_postings SET status = ? WHERE job_id = ?");
        $stmt->bind_param("si", $new_status, $job_id);
        
        if ($stmt->execute()) {
            // Fetch employer and job details for email notification
            $info_stmt = $conn->prepare("
                SELECT 
                    e.company_name, 
                    e.email_address, 
                    uc.contact_value as login_email,
                    jp.job_title,
                    u.email_notifications
                FROM job_postings jp
                JOIN employers e ON jp.employer_id = e.employer_id
                JOIN users u ON e.employer_id = u.user_id
                LEFT JOIN user_contacts uc ON e.employer_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1
                WHERE jp.job_id = ?
            ");
            $info_stmt->bind_param("i", $job_id);
            $info_stmt->execute();
            $info = $info_stmt->get_result()->fetch_assoc();
            $info_stmt->close();

            if ($info && $info['email_notifications']) {
                $to = !empty($info['login_email']) ? $info['login_email'] : $info['email_address'];
                
                if (!empty($to)) {
                    $subject = ($new_status === 'Active') ? "Job Post Approved - " . $info['job_title'] : "Job Post Rejected - " . $info['job_title'];
                    $action_text = ($new_status === 'Active') ? "approved by the PESO Admin and is now active for job seekers to view and apply." : "rejected by the PESO Admin. Please contact PESO Bongabon for more information.";
                    
                    $body = "<h3>Job Post " . ($new_status === 'Active' ? "Approved" : "Rejected") . "</h3>";
                    $body .= "<p>Dear " . htmlspecialchars($info['company_name']) . ",</p>";
                    $body .= "<p>Your job post for <strong>" . htmlspecialchars($info['job_title']) . "</strong> has been " . $action_text . "</p>";
                    $body .= "<br><p>Best regards,<br>PESO Bongabon</p>";
                    
                    sendEmail($to, $subject, $body);
                }
            }
        }
        $stmt->close();
    }
}

header("Location: admin_job_approval.php");
exit();
?>