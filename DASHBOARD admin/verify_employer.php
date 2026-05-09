<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $employer_id = $_GET['id'];
    $action = $_GET['action'];
    
    $status = '';
    if ($action === 'approve') {
        $status = 'Verified';
    } elseif ($action === 'reject') {
        $status = 'Rejected';
    }

    if ($status) {
        // Update employer status
        $stmt = $conn->prepare("UPDATE employers SET admin_verification_status = ? WHERE employer_id = ?");
        $stmt->bind_param("si", $status, $employer_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Employer has been " . strtolower($status) . ".";
        } else {
            $_SESSION['error_msg'] = "Error updating status.";
        }
        $stmt->close();
    }
}

header("Location: admin_dashboard.php");
exit();
?>