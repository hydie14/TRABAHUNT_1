<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id']) && isset($_POST['action'])) {
    $service_id = (int)$_POST['service_id'];
    $action = $_POST['action'];
    
    $new_status = '';
    if ($action === 'approve') {
        $new_status = 'Active';
    } elseif ($action === 'reject') {
        $new_status = 'Rejected';
    }

    if ($new_status && $service_id > 0) {
        $stmt = $conn->prepare("UPDATE provider_services SET status = ? WHERE service_id = ?");
        $stmt->bind_param("si", $new_status, $service_id);
        
        if ($stmt->execute()) {
            // Fetch provider details for email notification
            $info_stmt = $conn->prepare("
                SELECT 
                    sp.first_name,
                    uc.contact_value as login_email,
                    ps.service_name,
                    u.email_notifications
                FROM provider_services ps
                JOIN service_providers sp ON ps.provider_id = sp.provider_id
                JOIN users u ON sp.provider_id = u.user_id
                LEFT JOIN user_contacts uc ON sp.provider_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1
                WHERE ps.service_id = ?
            ");
            $info_stmt->bind_param("i", $service_id);
            $info_stmt->execute();
            $info = $info_stmt->get_result()->fetch_assoc();
            $info_stmt->close();

            if ($info && $info['email_notifications'] && !empty($info['login_email'])) {
                $subject = ($new_status === 'Active') ? "Service Post Approved - " . $info['service_name'] : "Service Post Rejected - " . $info['service_name'];
                $action_text = ($new_status === 'Active') ? "approved by the PESO Admin and is now active for job seekers to view and book." : "rejected by the PESO Admin. Please contact PESO Bongabon for more information or to adjust your posting.";
                
                $body = "<h3>Service Post " . ($new_status === 'Active' ? "Approved" : "Rejected") . "</h3>";
                $body .= "<p>Dear " . htmlspecialchars($info['first_name']) . ",</p>";
                $body .= "<p>Your service post for <strong>" . htmlspecialchars($info['service_name']) . "</strong> has been " . $action_text . "</p>";
                $body .= "<br><p>Best regards,<br>PESO Bongabon</p>";
                
                sendEmail($info['login_email'], $subject, $body);
            }
        }
        $stmt->close();
    }
}

header("Location: admin_service_approval.php");
exit();
?>