<?php
session_start();
include '../DATABASE/db_connect.php';
include '../DATABASE/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$employer_id = $_GET['id'];

// Fetch Employer Details
$stmt = $conn->prepare("
    SELECT e.*, l.barangay, l.city_municipality, l.province, bl.business_name 
    FROM employers e 
    LEFT JOIN locations l ON e.location_id = l.location_id 
    LEFT JOIN business_lines bl ON e.business_line_id = bl.business_line_id
    WHERE e.employer_id = ?
");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employer) {
    die("Employer not found.");
}

// Handle Email Sending for Missing Documents
$message_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $to = $employer['email_address'];
    $subject = "PESO Bongabon - Action Required: Missing/Invalid Documents";
    
    $body = "<h3>Action Required: Missing or Invalid Documents</h3>";
    $body .= "<p>Dear " . htmlspecialchars($employer['contact_person_name']) . ",</p>";
    $body .= "<p>We are reviewing your employer registration at PESO Bongabon. However, we found an issue with your submitted documents:</p>";
    $body .= "<blockquote style='background: #f9fafb; padding: 15px; border-left: 4px solid #d97706; margin: 15px 0;'><strong>" . nl2br(htmlspecialchars($_POST['email_message'])) . "</strong></blockquote>";
    $body .= "<p>Please provide the requested valid documents promptly to continue your registration. Failure to do so may result in the rejection of your account verification.</p>";
    $body .= "<br><p>Thank you,<br>PESO Bongabon Admin</p>";

    $mailError = '';
    if (sendEmail($to, $subject, $body, $mailError)) {
        $message_status = "<div class='alert alert-success'>Email successfully sent to the employer.</div>";
    } else {
        $message_status = "<div class='alert alert-danger'>Failed to send email. Error: " . htmlspecialchars($mailError) . "</div>";
    }
}

// Fetch Documents
$stmt = $conn->prepare("SELECT * FROM employer_documents WHERE employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$documents = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Employer - <?php echo htmlspecialchars($employer['company_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1e40af; margin-bottom: 1.5rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .detail-item label { display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .detail-item div { color: #1f2937; font-weight: 500; }
        .documents-section { margin-top: 2rem; }
        .document-card { background: #f9fafb; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        .actions { margin-top: 1rem; display: flex; gap: 1rem; border-top: 2px solid #e5e7eb; padding-top: 1.5rem; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; vertical-align: middle; margin-left: 1rem; }
        .status-verified { background: #d1fae5; color: #059669; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .email-section { background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 2rem; }
        .email-section textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; margin-top: 0.5rem; margin-bottom: 1rem; font-family: 'Inter', sans-serif; resize: vertical; }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="display: flex; align-items: center;">
            Employer Details
            <?php 
                $statusClass = 'status-pending';
                if ($employer['admin_verification_status'] === 'Verified') $statusClass = 'status-verified';
                elseif ($employer['admin_verification_status'] === 'Rejected') $statusClass = 'status-rejected';
            ?>
            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($employer['admin_verification_status']); ?></span>
        </h1>

        <?php echo $message_status; ?>
        
        <h3 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; margin-bottom: 1rem; margin-top: 1.5rem;">I. Establishment Details</h3>
        <div class="details-grid">
            <div class="detail-item">
                <label>Company Name</label>
                <div><?php echo htmlspecialchars($employer['company_name']); ?></div>
            </div>
            <div class="detail-item">
                <label>Trade Name</label>
                <div><?php echo htmlspecialchars($employer['trade_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="detail-item">
                <label>Acronym / Abbreviation</label>
                <div><?php echo htmlspecialchars($employer['acronym'] ?: 'N/A'); ?></div>
            </div>
            <div class="detail-item">
                <label>Office Type</label>
                <div><?php echo htmlspecialchars($employer['office_type'] ?: 'N/A'); ?></div>
            </div>
            <div class="detail-item">
                <label>TIN</label>
                <div><?php echo htmlspecialchars($employer['tin_number']); ?></div>
            </div>
            <div class="detail-item">
                <label>Type</label>
                <div><?php echo htmlspecialchars($employer['employer_type']); ?> (<?php echo htmlspecialchars($employer['employer_subtype']); ?>)</div>
            </div>
            <div class="detail-item">
                <label>Total Work Force</label>
                <div><?php echo htmlspecialchars($employer['total_work_force'] ?: 'N/A'); ?></div>
            </div>
            <div class="detail-item">
                <label>Line of Business/Industry</label>
                <div><?php echo htmlspecialchars($employer['business_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="detail-item">
                <label>Address</label>
                <div>
                    <?php 
                    if ($employer['location_id']) {
                        echo htmlspecialchars($employer['street_address'] . ', ' . $employer['barangay'] . ', ' . $employer['city_municipality'] . ', ' . $employer['province']);
                    } else {
                        echo htmlspecialchars($employer['street_address']); // For international
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <h3 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; margin-bottom: 1rem; margin-top: 1.5rem;">II. Contact Details</h3>
        <div class="details-grid">
            <div class="detail-item"><label>Name of Owner/President</label><div><?php echo htmlspecialchars($employer['owner_name'] ?: 'N/A'); ?></div></div>
            <div class="detail-item"><label>Contact Person</label><div><?php echo htmlspecialchars($employer['contact_person_name']); ?></div></div>
            <div class="detail-item"><label>Position</label><div><?php echo htmlspecialchars($employer['contact_person_position'] ?: 'N/A'); ?></div></div>
            <div class="detail-item"><label>Email Address</label><div><?php echo htmlspecialchars($employer['email_address']); ?></div></div>
            <div class="detail-item"><label>Mobile Number</label><div><?php echo htmlspecialchars($employer['mobile_number']); ?></div></div>
            <div class="detail-item"><label>Telephone Number</label><div><?php echo htmlspecialchars($employer['telephone_number'] ?: 'N/A'); ?></div></div>
            <div class="detail-item"><label>Fax Number</label><div><?php echo htmlspecialchars($employer['fax_number'] ?: 'N/A'); ?></div></div>
        </div>

        <h3 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; margin-bottom: 1rem; margin-top: 1.5rem;">III. Company Profile</h3>
        <div style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; border: 1px solid #e5e7eb; color: #374151; line-height: 1.6; white-space: pre-wrap; margin-bottom: 2rem;">
<?php echo htmlspecialchars($employer['company_description'] ?: 'No description provided.'); ?>
        </div>

        <div class="documents-section">
            <h3 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; margin-bottom: 1rem;">Submitted Documents</h3>
            <?php while($doc = $documents->fetch_assoc()): ?>
                <div class="document-card">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.5rem;">📄</span>
                        <div>
                            <strong style="display: block; color: #1e40af;"><?php echo htmlspecialchars($doc['document_type']); ?></strong>
                            <span style="font-size: 0.8rem; color: #6b7280;">Uploaded File</span>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-info" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; font-size: 0.875rem;">Preview Document</a>
                </div>
            <?php endwhile; ?>
            <?php if($documents->num_rows == 0): ?>
                <p style="color: #ef4444; font-weight: 500;">No documents uploaded by this employer.</p>
            <?php endif; ?>
        </div>

        <?php if ($employer['admin_verification_status'] === 'Pending'): ?>
        <div class="email-section">
            <h3 style="color: #d97706; margin-bottom: 0.5rem;">⚠️ Request Document Correction</h3>
            <p style="font-size: 0.875rem; color: #4b5563;">Are the documents blurry, missing, or invalid? Send an email to the employer before rejecting them.</p>
            <form method="POST">
                <textarea name="email_message" rows="4" required placeholder="E.g., Your BIR Certificate is blurry. Please email a clearer copy to admin@pesobongabon.com. Failure to do so will result in your account being rejected..."></textarea>
                <button type="submit" name="send_email" class="btn btn-warning">✉️ Email Employer</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="actions">
            <?php if ($employer['admin_verification_status'] === 'Pending'): ?>
                <a href="verify_employer.php?id=<?php echo $employer_id; ?>&action=approve" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this employer?');">✓ Approve Registration</a>
                <a href="verify_employer.php?id=<?php echo $employer_id; ?>&action=reject" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this employer?');">✗ Reject Registration</a>
            <?php endif; ?>
            <a href="admin_dashboard.php" class="btn-back" style="margin-left: auto;">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>