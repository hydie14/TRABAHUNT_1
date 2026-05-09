<?php
session_start();
include '../DATABASE/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$job_id = isset($input['job_id']) ? (int)$input['job_id'] : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Job ID']);
    exit();
}

// Check if already saved
$check = $conn->prepare("SELECT saved_job_id FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
$check->bind_param("ii", $user_id, $job_id);
$check->execute();
$result = $check->get_result();
$is_saved = $result->num_rows > 0;
$check->close();

if ($is_saved) {
    // Unsave
    $stmt = $conn->prepare("DELETE FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
    $stmt->bind_param("ii", $user_id, $job_id);
    $stmt->execute();
    $action = 'unsaved';
} else {
    // Save
    $stmt = $conn->prepare("INSERT INTO saved_jobs (seeker_id, job_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $job_id);
    $stmt->execute();
    $action = 'saved';
}

echo json_encode(['success' => true, 'action' => $action]);
?>