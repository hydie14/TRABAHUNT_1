<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: admin_dashboard.php");
    exit();
}

$application_id = (int)$_GET['id'];

// Fetch detailed application info with joins
$stmt = $conn->prepare("
    SELECT ra.*, 
           js.first_name, js.last_name, js.contact_no, js.street_address,
           jp.job_title, 
           e.company_name,
           uc.contact_value as email
    FROM referrals_applications ra
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    WHERE ra.application_id = ?
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();
$stmt->close();

if (!$application) {
    echo "Application not found.";
    exit();
}

// Check if the applicant is Currently Underemployed (Hired but Part-time/Project-based)
$is_underemployed = false;
$underemployed_type = '';

$check_underemployed = $conn->prepare("
    SELECT jp.employment_type 
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    WHERE ra.seeker_id = ? 
    AND ra.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment', 'Pending_Resignation')
    AND jp.employment_type NOT IN ('Permanent', 'Contractual')
    LIMIT 1
");
$check_underemployed->bind_param("i", $application['seeker_id']);
$check_underemployed->execute();
$underemployed_result = $check_underemployed->get_result();

if ($underemployed_result->num_rows > 0) {
    $is_underemployed = true;
    $underemployed_type = $underemployed_result->fetch_assoc()['employment_type'];
}
$check_underemployed->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; color: #1f2937; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0; }
        
        .section { margin-bottom: 2rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: #374151; margin-bottom: 1rem; }
        
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .info-group label { display: block; font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; }
        .info-group div { font-weight: 500; color: #111827; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #d1fae5; color: #059669; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        
        .btn { display: inline-block; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; text-decoration: none; transition: background 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #1e40af; color: white; }
        .btn-primary:hover { background: #1e3a8a; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        
        .actions { display: flex; gap: 1rem; margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e5e7eb; }
        .resume-link { color: #1e40af; text-decoration: underline; }
        .btn svg { width: 1.2em; height: 1.2em; vertical-align: text-bottom; margin-right: 0.25rem; }

        @media (max-width: 640px) {
            body { padding: 1rem; }
            .container { padding: 1.5rem; }
            .grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .actions { flex-direction: column; }
            .btn { width: 100%; text-align: center; margin-bottom: 0.5rem; box-sizing: border-box; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Application Details</h1>
            <?php 
                $statusClass = 'status-pending';
                if(strpos($application['status'], 'Refer') !== false || strpos($application['status'], 'Hired') !== false) $statusClass = 'status-approved';
                if(strpos($application['status'], 'Reject') !== false) $statusClass = 'status-rejected';
            ?>
            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($application['status']); ?></span>
        </div>

        <div class="section">
            <h3 class="section-title">Applicant Information</h3>
            <div class="grid">
                <div class="info-group">
                    <label>Full Name</label>
                    <div style="text-transform: capitalize; display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                        <?php if ($is_underemployed): ?>
                            <span style="background-color: #fef3c7; color: #92400e; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #fde68a; display: inline-flex; align-items: center; gap: 0.25rem; text-transform: none;" title="Currently employed as <?php echo htmlspecialchars($underemployed_type); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 0.85rem; height: 0.85rem;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                Underemployed (<?php echo htmlspecialchars($underemployed_type); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-group">
                    <label>Email Address</label>
                    <div><?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-group">
                    <label>Contact Number</label>
                    <div><?php echo htmlspecialchars($application['contact_no'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-group">
                    <label>Address</label>
                    <div><?php echo htmlspecialchars($application['street_address'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Job Application Info</h3>
            <div class="grid">
                <div class="info-group">
                    <label>Applying For</label>
                    <div style="text-transform: capitalize;"><?php echo htmlspecialchars($application['job_title']); ?></div>
                </div>
                <div class="info-group">
                    <label>Company</label>
                    <div style="text-transform: capitalize;"><?php echo htmlspecialchars($application['company_name']); ?></div>
                </div>
                <div class="info-group">
                    <label>Date Applied</label>
                    <div><?php echo date('F d, Y h:i A', strtotime($application['created_at'])); ?></div>
                </div>
                <div class="info-group">
                    <label>Resume / CV</label>
                    <div>
                        <?php if(!empty($application['resume_file'])): ?>
                            <a href="<?php echo htmlspecialchars($application['resume_file']); ?>" target="_blank" class="resume-link">View Resume Document</a>
                        <?php else: ?>
                            <span style="color: #9ca3af;">No resume uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="actions">
            <?php if(in_array($application['status'], ['Pending', 'Pending_Docs', 'Verified'])): ?>
                <a href="process_application.php?id=<?php echo $application['application_id']; ?>&action=approve" class="btn btn-success" onclick="return confirm('Approve this application and issue referral?')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Approve & Refer</a>
                <a href="process_application.php?id=<?php echo $application['application_id']; ?>&action=reject" class="btn btn-danger" onclick="return confirm('Reject this application?')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> Reject</a>
            <?php endif; ?>
            <a href="admin_dashboard.php" class="btn-back" style="margin-left: auto;">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
