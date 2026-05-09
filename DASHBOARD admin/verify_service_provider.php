<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provider_id']) && isset($_POST['action'])) {
    $provider_id = (int)$_POST['provider_id'];
    $action = $_POST['action'];
    
    $status = '';
    if ($action === 'approve') {
        $status = 'Verified';
    } elseif ($action === 'reject') {
        $status = 'Rejected';
    }

    if ($status && $provider_id > 0) {
        // Update provider status
        $stmt = $conn->prepare("UPDATE service_providers SET admin_verification_status = ? WHERE provider_id = ?");
        $stmt->bind_param("si", $status, $provider_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Service Provider has been " . strtolower($status) . ".";

            // Fetch provider details for email notification
            $info_stmt = $conn->prepare("
                SELECT sp.first_name, uc.contact_value as email
                FROM service_providers sp
                JOIN user_contacts uc ON sp.provider_id = uc.user_id
                WHERE sp.provider_id = ? AND uc.contact_type = 'Email'
            ");
            $info_stmt->bind_param("i", $provider_id);
            $info_stmt->execute();
            $info = $info_stmt->get_result()->fetch_assoc();
            $info_stmt->close();

            if ($info) {
                $to = $info['email'];
                $subject = "Your PESO Service Provider Account Status";
                $body = "<h3>Account Status Update</h3>";
                $body .= "<p>Dear " . htmlspecialchars($info['first_name']) . ",</p>";
                
                if ($status === 'Verified') {
                    $body .= "<p>Congratulations! Your Service Provider account with PESO Bongabon has been <strong>verified</strong>. You can now log in to your dashboard to manage your profile and accept booking requests.</p>";
                } else {
                    $body .= "<p>We regret to inform you that your Service Provider account application has been <strong>rejected</strong>. This may be due to incomplete or invalid documents. Please contact the PESO Bongabon office for more details.</p>";
                }
                
                $body .= "<br><p>Best regards,<br>PESO Bongabon</p>";
                
                sendEmail($to, $subject, $body);
            }

        } else {
            $_SESSION['error_msg'] = "Error updating status.";
        }
        $stmt->close();
    }
}

header("Location: admin_sp_approval.php");
exit();