<?php
session_start();
include '../DATABASE/db_connect.php';
include 'send_otp.php';

header('Content-Type: application/json');

if (!isset($_SESSION['otp_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

if (!isset($_SESSION['last_otp_resend']) || (time() - $_SESSION['last_otp_resend']) >= 30) {
    $user_id = $_SESSION['otp_user_id'];

    $stmt = $conn->prepare("SELECT contact_value FROM user_contacts WHERE user_id = ? AND contact_type = 'Email' LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $email = $row['contact_value'];
        
        $otp_error = '';
        if (send_otp($email, $user_id, $conn, $otp_error)) {
            $_SESSION['last_otp_resend'] = time();
            echo json_encode(['success' => true, 'message' => 'New OTP sent successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
    }
} else {
    $wait_time = 30 - (time() - $_SESSION['last_otp_resend']);
    echo json_encode(['success' => false, 'message' => "Please wait {$wait_time} seconds before requesting another OTP."]);
}
?>