<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id']) && isset($_POST['action'])) {
    $app_id = (int)$_POST['app_id'];
    $action = $_POST['action'];
    
    // Fetch details for notification first, as we need them in both cases
    $info_stmt = $conn->prepare("
        SELECT 
            js.first_name,
            js.seeker_id,
            uc.contact_value as email,
            u.email_notifications,
            jp.job_title,
            e.company_name
        FROM referrals_applications ra
        JOIN jobseekers js ON ra.seeker_id = js.seeker_id
        JOIN users u ON js.seeker_id = u.user_id
        JOIN job_postings jp ON ra.job_id = jp.job_id
        JOIN employers e ON jp.employer_id = e.employer_id
        LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1
        WHERE ra.application_id = ?
    ");
    $info_stmt->bind_param("i", $app_id);
    $info_stmt->execute();
    $info = $info_stmt->get_result()->fetch_assoc();
    $info_stmt->close();
    
    if ($action === 'approve') {
        $reason = $_POST['reason'] ?? '';
        
        // intelligently assign official status based on user's reason string
        $new_status = 'Resigned';
        if (stripos($reason, 'terminat') !== false || stripos($reason, 'fired') !== false || stripos($reason, 'end of contract') !== false) {
            $new_status = 'Terminated';
        }
        
        $stmt = $conn->prepare("UPDATE referrals_applications SET status = ? WHERE application_id = ?");
        $stmt->bind_param("si", $new_status, $app_id);
        $stmt->execute();
        $stmt->close();

        // Send email and dashboard notification to Job Seeker
        if ($info) {
            $notif_title = "Resignation Approved";
            $notif_msg = "Your resignation from " . htmlspecialchars($info['company_name']) . " has been verified. You can now apply for new jobs.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'resignation_approved', ?, ?, ?)");
            $notif_stmt->bind_param("iiss", $info['seeker_id'], $app_id, $notif_title, $notif_msg);
            $notif_stmt->execute();
            $notif_stmt->close();

            if ($info['email_notifications'] && !empty($info['email'])) {
                $body = "<h3>Resignation Report Approved</h3><p>Dear " . htmlspecialchars($info['first_name']) . ",</p><p>Your resignation report for the position of <strong>" . htmlspecialchars($info['job_title']) . "</strong> at <strong>" . htmlspecialchars($info['company_name']) . "</strong> has been approved by the PESO Admin.</p><p>Your employment status has been updated to '" . $new_status . "'. You are now able to apply for new jobs through the portal.</p><br><p>Best regards,<br>PESO Bongabon</p>";
                sendEmail($info['email'], "Resignation Report Approved", $body);
            }
        }
        
    } elseif ($action === 'reject') {
        // Revert back to Hired
        $stmt = $conn->prepare("UPDATE referrals_applications SET status = 'Hired' WHERE application_id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete the invalid report so they can submit a new one
        $stmt2 = $conn->prepare("DELETE FROM resignation_reports WHERE application_id = ?");
        $stmt2->bind_param("i", $app_id);
        $stmt2->execute();
        $stmt2->close();

        // Send email and dashboard notification to Job Seeker
        if ($info) {
            $notif_title = "Resignation Report Rejected";
            $notif_msg = "Your resignation report was rejected. Please check your documents and resubmit.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'resignation_rejected', ?, ?, ?)");
            $notif_stmt->bind_param("iiss", $info['seeker_id'], $app_id, $notif_title, $notif_msg);
            $notif_stmt->execute();
            $notif_stmt->close();

            if ($info['email_notifications'] && !empty($info['email'])) {
                $body = "<h3>Resignation Report Rejected</h3><p>Dear " . htmlspecialchars($info['first_name']) . ",</p><p>Your resignation report for the position of <strong>" . htmlspecialchars($info['job_title']) . "</strong> at <strong>" . htmlspecialchars($info['company_name']) . "</strong> has been rejected by the PESO Admin.</p><p>This may be due to an invalid or unclear proof document. Your employment status has been reverted to 'Hired'. Please submit a new, valid report to update your status.</p><br><p>Best regards,<br>PESO Bongabon</p>";
                sendEmail($info['email'], "Resignation Report Rejected", $body);
            }
        }
    }
}

header("Location: resignation_reports.php");
exit();
?>