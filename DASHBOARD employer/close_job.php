<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';
require '../DATABASE/csrf.php';

// Ensure Employer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die("Invalid security token. Please go back and try again.");
    }

    $job_id = (int)$_POST['job_id'];
    $close_reason = trim($_POST['close_reason']);
    $employer_id = $_SESSION['user_id'];

    if ($job_id > 0 && !empty($close_reason)) {
        $stmt = $conn->prepare("UPDATE job_postings SET status = 'Closed', close_reason = ? WHERE job_id = ? AND employer_id = ?");
        $stmt->bind_param("sii", $close_reason, $job_id, $employer_id);
        $stmt->execute();
        $stmt->close();
        
        // Notify PESO Admin
        $adm_q = $conn->query("SELECT u.user_id, uc.contact_value as email FROM users u LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1 WHERE u.role = 'Admin' LIMIT 1");
        if ($adm_q && $adm_q->num_rows > 0) {
            $adm_data = $adm_q->fetch_assoc();
            
            // Fetch Job Info
            $job_q = $conn->prepare("SELECT jp.job_title, e.company_name FROM job_postings jp JOIN employers e ON jp.employer_id = e.employer_id WHERE jp.job_id = ?");
            $job_q->bind_param("i", $job_id);
            $job_q->execute();
            $job_data = $job_q->get_result()->fetch_assoc();
            $job_q->close();

            if ($job_data) {
                $adm_msg = "{$job_data['company_name']} has closed their job posting for {$job_data['job_title']}. Reason: {$close_reason}.";
                
                // Admin In-App
                $conn->query("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES ({$adm_data['user_id']}, 'job_close', $job_id, 'Job Posting Closed', '" . $conn->real_escape_string($adm_msg) . "')");
                
                // Admin Email
                if (!empty($adm_data['email'])) {
                    sendEmail($adm_data['email'], "Job Posting Closed - PESO Bongabon", "<h3>Job Posting Closed</h3><p>$adm_msg</p>");
                }
            }
        }
    }
}

// Redirect back to the jobs section
header("Location: employer_dashboard.php#jobs");
exit();
?>