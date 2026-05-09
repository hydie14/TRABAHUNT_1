<?php
// Turn off error displaying to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start(); // Start output buffering
session_start();
include '../DATABASE/db_connect.php';
ob_clean(); // Clean any output from includes (like whitespace)

header('Content-Type: application/json');

try {
    // Ensure Admin is logged in
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['application_id']) || !isset($_POST['seeker_id'])) {
        throw new Exception('Invalid request');
    }

    $application_id = (int)$_POST['application_id'];
    $seeker_id = (int)$_POST['seeker_id'];
    if ($application_id <= 0 || $seeker_id <= 0) {
        throw new Exception('Invalid Applicant ID');
    }
    
    $conn->begin_transaction();

    // 1. Mark Job Seeker as Verified (Only affects their main profile)
    $stmt = $conn->prepare("UPDATE jobseekers SET is_verified = 1 WHERE seeker_id = ?");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("i", $seeker_id);
    if (!$stmt->execute()) throw new Exception("Error updating jobseeker: " . $stmt->error);
    $stmt->close();
    
    // 2. Update ONLY this specific application status to Verified (Matched)
    $app_stmt = $conn->prepare("UPDATE referrals_applications SET status = 'Verified' WHERE application_id = ? AND status IN ('Pending_Docs', 'Pending')");
    if (!$app_stmt) throw new Exception("Prepare failed (Apps): " . $conn->error);
    $app_stmt->bind_param("i", $application_id);
    if (!$app_stmt->execute()) throw new Exception("Error updating applications: " . $app_stmt->error);
    $app_stmt->close();
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Application verified and matched successfully!']);

} catch (Exception $e) {
    if (isset($conn) && $conn->errno) $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

ob_end_flush();
?>