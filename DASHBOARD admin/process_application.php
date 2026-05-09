<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    if (!filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        header("Location: admin_dashboard.php");
        exit();
    }
    
    $application_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $new_status = '';

    if ($action == 'approve') {
        $new_status = 'Issue Referral Letter';
    } elseif ($action == 'reject') {
        $new_status = 'Rejected / Not Qualified';
    }

    if ($new_status) {
        $stmt = $conn->prepare("UPDATE referrals_applications SET status = ? WHERE application_id = ?");
        $stmt->bind_param("si", $new_status, $application_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: admin_dashboard.php");
exit();
?>
