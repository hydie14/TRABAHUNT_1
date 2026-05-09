 <?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seeker_id = isset($_POST['seeker_id']) ? (int)$_POST['seeker_id'] : 0;
    $app_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    if ($seeker_id <= 0 || $app_id <= 0 || empty($date) || empty($time)) {
        echo "Please provide valid details (ID, Date, Time).";
        exit;
    }

    // Validate if the selected time is in the past
    date_default_timezone_set('Asia/Manila');
    if (strtotime("$date $time") < time()) {
        echo "Error: The selected schedule has already passed. Please choose a future time.";
        exit;
    }

    // Fetch seeker email and name
    $stmt = $conn->prepare("
        SELECT js.first_name, js.last_name, uc.contact_value as email 
        FROM jobseekers js 
        JOIN user_contacts uc ON js.seeker_id = uc.user_id 
        WHERE js.seeker_id = ? AND uc.contact_type = 'Email'
    ");
    $stmt->bind_param("i", $seeker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $seeker = $result->fetch_assoc();
    $stmt->close();

    if ($seeker && !empty($seeker['email'])) {
        $schedule = date('F d, Y', strtotime($date)) . ' at ' . date('h:i A', strtotime($time));
        
        $subject = "Schedule for Document Verification - PESO Bongabon";
        $body = "<h3>Document Verification & Interview Schedule</h3>";
        $body .= "<p>Dear " . htmlspecialchars($seeker['first_name']) . ",</p>";
        $body .= "<p>We have received your application. To proceed with the verification process, you are required to submit the hard copies of your documents and undergo an on-the-spot interview at the PESO Bongabon Office.</p>";
        $body .= "<p><strong>Scheduled Date & Time:</strong> " . $schedule . "</p>";
        $body .= "<p><strong>Please bring the following requirements:</strong></p>";
        $body .= "<ul>";
        $body .= "<li>Updated Resume / CV</li>";
        $body .= "<li>Valid Government-issued ID</li>";
        $body .= "<li>Diploma / Transcript of Records (TOR)</li>";
        $body .= "<li>PSA Birth Certificate</li>";
        $body .= "<li>Certificate of Employment (if applicable)</li>";
        $body .= "<li>2x2 ID Picture</li>";
        $body .= "<li>Barangay Residency Certificate</li>";
        $body .= "</ul>";
        $body .= "<p>Please be on time. Failure to appear may delay your application process.</p>";
        $body .= "<br><p>Best regards,<br>PESO Bongabon</p>";

        $mailError = '';
        if (sendEmail($seeker['email'], $subject, $body, $mailError)) {
            
            // Insert Notification into database
            $notif_title = "Document Verification Schedule";
            $notif_msg = "Please visit the PESO Bongabon Office on " . $schedule . " to submit your hard copy requirements and undergo an interview.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'referral', ?, ?, ?)");
            $notif_stmt->bind_param("iiss", $seeker_id, $app_id, $notif_title, $notif_msg);
            $notif_stmt->execute();
            $notif_stmt->close();

            echo "Notification sent successfully!";
        } else {
            echo "Failed to send email notification. Error: " . $mailError;
        }
    } else {
        echo "Seeker email not found.";
    }
} else {
    echo "Invalid request.";
}
?>