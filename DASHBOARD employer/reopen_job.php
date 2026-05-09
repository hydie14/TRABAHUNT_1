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
    $employer_id = $_SESSION['user_id'];

    if ($job_id > 0) {
        // Fetch job info for email
        $info_stmt = $conn->prepare("SELECT jp.job_title, e.company_name FROM job_postings jp JOIN employers e ON jp.employer_id = e.employer_id WHERE jp.job_id = ? AND jp.employer_id = ?");
        $info_stmt->bind_param("ii", $job_id, $employer_id);
        $info_stmt->execute();
        $info_res = $info_stmt->get_result();
        if ($info_res->num_rows > 0) {
            $info = $info_res->fetch_assoc();
            
            // Set status to Pending_Approval, clear the close reason, and reset valid_until to prevent immediate re-expiration
            $stmt = $conn->prepare("UPDATE job_postings SET status = 'Pending_Approval', close_reason = NULL, valid_until = NULL WHERE job_id = ? AND employer_id = ?");
            $stmt->bind_param("ii", $job_id, $employer_id);
            if ($stmt->execute()) {
                // Notify PESO Admin
                $admin_stmt = $conn->prepare("SELECT uc.contact_value FROM users u JOIN user_contacts uc ON u.user_id = uc.user_id WHERE u.role = 'Admin' AND uc.contact_type = 'Email' AND uc.is_primary = 1 LIMIT 1");
                $admin_stmt->execute();
                $admin_res = $admin_stmt->get_result();
                if ($admin_res->num_rows > 0) {
                    $admin_email = $admin_res->fetch_assoc()['contact_value'];
                    $subject = "Job Reopened - Pending Approval";
                    $body = "<h3>Job Reopened</h3><p>An employer has reopened a previously closed or expired job posting. It is now pending your approval to become active again.</p>";
                    $body .= "<p><strong>Employer:</strong> " . htmlspecialchars($info['company_name']) . "</p><p><strong>Job Title:</strong> " . htmlspecialchars($info['job_title']) . "</p>";
                    $body .= "<p>Please log in to the Admin Dashboard to review and approve this job posting.</p><br><p>PESO Bongabon System</p>";
                    sendEmail($admin_email, $subject, $body);
                }
                $admin_stmt->close();
            }
            $stmt->close();
        }
        $info_stmt->close();
    }
}

// Redirect back to the jobs section
header("Location: employer_dashboard.php#jobs");
exit();
?>
