<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php'; // Include email sender

// Ensure Job Seeker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = (int)$_POST['app_id'];
    $reason = trim($_POST['end_reason']);
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $seeker_id = $_SESSION['user_id'];

    if (empty($reason)) {
        header("Location: my_applications.php?error=Reason is required");
        exit();
    }

    // Set to Pending Resignation for Admin Approval
    $new_status = 'Pending_Resignation';

    // Handle File Upload
    $proof_path = null;
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = "../UPLOADS/resignation_proofs/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($file_ext, $allowed_ext) && $_FILES['proof_file']['size'] <= 5242880) { // 5MB max
            $new_filename = "proof_" . $app_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $target_file)) {
                $proof_path = $target_file;
            } else {
                header("Location: my_applications.php?error=Failed to upload proof");
                exit();
            }
        } else {
            header("Location: my_applications.php?error=Invalid file type or size too large");
            exit();
        }
    } else {
        header("Location: my_applications.php?error=Proof document is required");
        exit();
    }

    // 1. Fetch Job & Employer Details for Notification
    $query = "
        SELECT ra.job_id, jp.job_title, e.employer_id, e.company_name, js.first_name, js.last_name,
               (SELECT contact_value FROM user_contacts WHERE user_id = e.employer_id AND contact_type = 'Email' LIMIT 1) as employer_email
        FROM referrals_applications ra 
        JOIN job_postings jp ON ra.job_id = jp.job_id 
        JOIN employers e ON jp.employer_id = e.employer_id 
        JOIN jobseekers js ON ra.seeker_id = js.seeker_id
        WHERE ra.application_id = ? AND ra.seeker_id = ?
    ";
    $stmt_details = $conn->prepare($query);
    $stmt_details->bind_param("ii", $app_id, $seeker_id);
    $stmt_details->execute();
    $details = $stmt_details->get_result()->fetch_assoc();
    $stmt_details->close();

    // Update Status
    $stmt = $conn->prepare("UPDATE referrals_applications SET status = ?, end_employment_reason = ? WHERE application_id = ? AND seeker_id = ?");
    $stmt->bind_param("ssii", $new_status, $reason, $app_id, $seeker_id);
    
    if ($stmt->execute()) {
        // Insert message into the new table
        $stmt_msg = $conn->prepare("INSERT INTO resignation_reports (application_id, seeker_id, reason, remarks, proof_file) VALUES (?, ?, ?, ?, ?)");
        $stmt_msg->bind_param("iisss", $app_id, $seeker_id, $reason, $remarks, $proof_path);
        $stmt_msg->execute();
        $stmt_msg->close();

        // 2. Notify Employer (Dashboard Notification)
        if ($details) {
            $notif_title = "Employee Resignation: " . $details['first_name'] . " " . $details['last_name'];
            $notif_msg = "An employee has updated their status to {$reason} for the position of {$details['job_title']}.";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'resignation', ?, ?, ?)");
            $notif_stmt->bind_param("iiss", $details['employer_id'], $app_id, $notif_title, $notif_msg);
            $notif_stmt->execute();
            $notif_stmt->close();

            // 3. Send Email to Employer
            if (!empty($details['employer_email'])) {
                $subject = "PESO Notification: Employee Resignation - " . $details['first_name'];
                $body = "<h3>Employee Status Update</h3>";
                $body .= "<p><strong>Employee:</strong> " . htmlspecialchars($details['first_name'] . " " . $details['last_name']) . "</p>";
                $body .= "<p><strong>Position:</strong> " . htmlspecialchars($details['job_title']) . "</p>";
                $body .= "<p><strong>Status:</strong> " . htmlspecialchars($reason) . "</p>";
                if (!empty($remarks)) {
                    $body .= "<p><strong>Remarks/Message from Employee:</strong><br><em>" . nl2br(htmlspecialchars($remarks)) . "</em></p>";
                }
                
                if (!empty($proof_path)) {
                    // Construct the full URL to the file dynamically based on localhost/server
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                    $full_proof_url = $base_url . "/CAPSTONE%20PESO1/" . ltrim($proof_path, "../");
                    $body .= "<p><strong>Proof Document:</strong> <a href='" . htmlspecialchars($full_proof_url) . "'>View Attached Resignation/Termination Letter</a></p>";
                }
                
                $body .= "<p>Please check your dashboard for more details.</p>";
                
                sendEmail($details['employer_email'], $subject, $body);
            }
            
            // 4. Notify PESO Admin
            $adm_q = $conn->query("SELECT u.user_id, uc.contact_value as email FROM users u LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1 WHERE u.role = 'Admin' LIMIT 1");
            if ($adm_q && $adm_q->num_rows > 0) {
                $adm_data = $adm_q->fetch_assoc();
                $adm_msg = "Applicant {$details['first_name']} {$details['last_name']} has reported a status of {$reason} from their employment at {$details['company_name']} for the position of {$details['job_title']}.";
                
                // Admin In-App
                $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ({$adm_data['user_id']}, 'resignation', $app_id, 'Employment Status Update', '" . $conn->real_escape_string($adm_msg) . "')");
                
                // Admin Email
                if (!empty($adm_data['email'])) {
                    sendEmail($adm_data['email'], "Employee Resignation Report - PESO Bongabon", "<h3>Employment Status Update</h3><p>$adm_msg</p><p>Please review the details in your Admin Dashboard.</p>");
                }
            }
        }

        header("Location: my_applications.php?msg=Status updated");
    } else {
        header("Location: my_applications.php?error=Update failed");
    }
    $stmt->close();
}
?>