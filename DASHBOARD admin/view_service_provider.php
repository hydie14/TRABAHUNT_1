<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: admin_sp_approval.php");
    exit();
}

$provider_id = (int)$_GET['id'];

// Fetch provider details
$stmt = $conn->prepare("
    SELECT sp.*, uc_email.contact_value as email, uc_mobile.contact_value as phone_number
    FROM service_providers sp
    LEFT JOIN user_contacts uc_email ON sp.provider_id = uc_email.user_id AND uc_email.contact_type = 'Email'
    LEFT JOIN user_contacts uc_mobile ON sp.provider_id = uc_mobile.user_id AND uc_mobile.contact_type = 'Mobile'
    WHERE sp.provider_id = ?
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$result = $stmt->get_result();
$provider = $result->fetch_assoc();
$stmt->close();

if (!$provider) {
    header("Location: admin_sp_approval.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Service Provider - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; }
        h1 { color: #1e40af; margin: 0; font-size: 1.75rem; }
        .section-title { font-size: 1.1rem; color: #1e40af; margin: 1.5rem 0 1rem; font-weight: 600; }
        .detail-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1rem; }
        .detail-group label { display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .detail-group div { color: #1f2937; font-weight: 500; font-size: 1rem; }
        .doc-link { display: inline-flex; align-items: center; gap: 0.5rem; background: #eff6ff; color: #1e40af; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
        .doc-link:hover { background: #dbeafe; }
        .doc-link.optional { background: #f1f5f9; color: #475569; }
        .doc-link.optional:hover { background: #e2e8f0; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        .btn-success { background-color: #10b981; color: white; margin-left: 0.5rem; }
        .btn-success:hover { background-color: #059669; }
        .btn-danger { background-color: #ef4444; color: white; margin-left: 0.5rem; }
        .btn-danger:hover { background-color: #dc2626; }
        .actions-form { display: inline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <div>
                <h1><?php echo htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']); ?></h1>
                <div class="company-subtitle" style="color: #6b7280; font-size: 1.1rem; margin-top: 0.5rem; font-weight: 500;">Service Provider Applicant</div>
            </div>
            <div>
                <a href="admin_sp_approval.php" class="btn-back">← Back</a>
                <?php if ($provider['admin_verification_status'] === 'Pending'): ?>
                <form action="verify_service_provider.php" method="POST" class="actions-form">
                    <input type="hidden" name="provider_id" value="<?php echo $provider['provider_id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this applicant?');">Approve</button>
                </form>
                <form action="verify_service_provider.php" method="POST" class="actions-form">
                    <input type="hidden" name="provider_id" value="<?php echo $provider['provider_id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this applicant?');">Reject</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="section-title">Personal Information</h3>
        <div class="detail-row">
            <div class="detail-group"><label>Birthdate</label><div><?php echo date('F d, Y', strtotime($provider['birthdate'])); ?></div></div>
            <div class="detail-group"><label>Gender</label><div><?php echo htmlspecialchars($provider['gender']); ?></div></div>
            <div class="detail-group"><label>Email</label><div><?php echo htmlspecialchars($provider['email']); ?></div></div>
            <div class="detail-group"><label>Phone</label><div><?php echo htmlspecialchars($provider['phone_number']); ?></div></div>
        </div>
        <div class="detail-row">
            <div class="detail-group"><label>Address</label><div><?php echo htmlspecialchars($provider['street_address'] . ', ' . $provider['barangay']); ?></div></div>
        </div>

        <h3 class="section-title">Uploaded Documents</h3>
        <div class="detail-row">
            <div class="detail-group">
                <label>Valid Government ID (Required)</label>
                <a href="<?php echo htmlspecialchars($provider['valid_id_path']); ?>" target="_blank" class="doc-link">📄 View ID</a>
            </div>
            <div class="detail-group">
                <label>Barangay Residency (Required)</label>
                <a href="<?php echo htmlspecialchars($provider['brgy_residency_path']); ?>" target="_blank" class="doc-link">📄 View Residency</a>
            </div>
            <?php if ($provider['tesda_cert_path']): ?>
            <div class="detail-group">
                <label>TESDA Certificate (Optional)</label>
                <a href="<?php echo htmlspecialchars($provider['tesda_cert_path']); ?>" target="_blank" class="doc-link optional">📄 View Certificate</a>
            </div>
            <?php endif; ?>
            <?php if ($provider['portfolio_path']): ?>
            <div class="detail-group">
                <label>Portfolio (Optional)</label>
                <a href="<?php echo htmlspecialchars($provider['portfolio_path']); ?>" target="_blank" class="doc-link optional">📄 View Portfolio</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>