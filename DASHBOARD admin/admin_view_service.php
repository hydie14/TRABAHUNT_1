<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['service_id']) || !filter_var($_GET['service_id'], FILTER_VALIDATE_INT)) {
    header("Location: admin_service_approval.php");
    exit();
}

$service_id = (int)$_GET['service_id'];

$stmt = $conn->prepare("
    SELECT ps.*, sp.first_name, sp.last_name, sp.barangay, sp.street_address 
    FROM provider_services ps 
    JOIN service_providers sp ON ps.provider_id = sp.provider_id
    WHERE ps.service_id = ?
");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

if (!$service) {
    header("Location: admin_service_approval.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Service - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; }
        h1 { color: #1e40af; margin: 0; font-size: 1.75rem; }
        .company-subtitle { color: #6b7280; font-size: 1.1rem; margin-top: 0.5rem; font-weight: 500; }
        .section-title { font-size: 1.1rem; color: #1e40af; margin: 1.5rem 0 1rem; font-weight: 600; }
        .detail-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1rem; }
        .detail-group label { display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .detail-group div { color: #1f2937; font-weight: 500; font-size: 1rem; }
        .description-box { background: #f9fafb; padding: 1.5rem; border-radius: 8px; border: 1px solid #e5e7eb; white-space: pre-wrap; line-height: 1.5; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-success { background-color: #10b981; color: white; margin-left: 0.5rem; }
        .btn-danger { background-color: #ef4444; color: white; margin-left: 0.5rem; }
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .actions-form { display: inline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <div>
                <h1><?php echo htmlspecialchars($service['service_name']); ?></h1>
                <div class="company-subtitle">Provider: <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?></div>
            </div>
            <div>
                <a href="admin_service_approval.php" class="btn-back">← Back</a>
                <?php if ($service['status'] === 'Pending_Approval'): ?>
                <form action="admin_verify_service.php" method="POST" class="actions-form">
                    <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this service post?');">Approve</button>
                </form>
                <form action="admin_verify_service.php" method="POST" class="actions-form">
                    <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this service post?');">Reject</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-group">
                <label>Status</label>
                <div><?php echo htmlspecialchars($service['status']); ?></div>
            </div>
            <div class="detail-group">
                <label>Date Posted</label>
                <div><?php echo date('F d, Y', strtotime($service['created_at'])); ?></div>
            </div>
        </div>

        <h3 class="section-title">Service Details</h3>
        <div class="detail-row">
            <div class="detail-group">
                <label>Category</label>
                <div><?php echo htmlspecialchars($service['category']); ?></div>
            </div>
            <div class="detail-group">
                <label>Base Rate</label>
                <div><?php echo htmlspecialchars($service['base_rate']); ?></div>
            </div>
        </div>
        
        <div class="detail-group" style="margin-bottom: 1.5rem;">
            <label>Description</label>
            <div class="description-box"><?php echo htmlspecialchars($service['description']); ?></div>
        </div>

        <h3 class="section-title">Provider Location</h3>
        <div class="detail-row">
            <div class="detail-group">
                <label>Address</label>
                <div><?php echo htmlspecialchars($service['street_address'] . ', ' . $service['barangay']); ?></div>
            </div>
        </div>
    </div>
</body>
</html>