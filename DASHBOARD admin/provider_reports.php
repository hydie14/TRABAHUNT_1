<?php
session_start();
include '../DATABASE/db_connect.php';

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['report_id'])) {
    $report_id = (int)$_POST['report_id'];
    $provider_id = (int)$_POST['provider_id'];
    $action = $_POST['action'];

    if ($action === 'suspend') {
        $conn->begin_transaction();
        try {
            // Update provider status
            $stmt = $conn->prepare("UPDATE service_providers SET admin_verification_status = 'Suspended' WHERE provider_id = ?");
            $stmt->bind_param("i", $provider_id);
            $stmt->execute();
            $stmt->close();

            // Update report status
            $stmt2 = $conn->prepare("UPDATE provider_reports SET status = 'Resolved (Suspended)' WHERE report_id = ?");
            $stmt2->bind_param("i", $report_id);
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();
            $message = "<div style='background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #bbf7d0;'>Provider suspended successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div style='background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca;'>Error processing request.</div>";
        }
    } elseif ($action === 'dismiss') {
        $stmt = $conn->prepare("UPDATE provider_reports SET status = 'Dismissed' WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) {
            $message = "<div style='background: #f3f4f6; color: #374151; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #d1d5db;'>Report dismissed.</div>";
        }
        $stmt->close();
    }
}

// Fetch all provider reports
$query = "
    SELECT pr.*, 
           sp.first_name as sp_first, sp.last_name as sp_last, sp.admin_verification_status,
           u.role as reporter_role,
           COALESCE(js.first_name, e.company_name, sp_rep.first_name, 'User') as reporter_name,
           COALESCE(js.last_name, '', sp_rep.last_name, '') as reporter_last
    FROM provider_reports pr
    JOIN service_providers sp ON pr.provider_id = sp.provider_id
    JOIN users u ON pr.reporter_id = u.user_id
    LEFT JOIN jobseekers js ON pr.reporter_id = js.seeker_id
    LEFT JOIN employers e ON pr.reporter_id = e.employer_id
    LEFT JOIN service_providers sp_rep ON pr.reporter_id = sp_rep.provider_id
    ORDER BY pr.created_at DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Provider Grievances - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { color: #111827; margin: 0; font-size: 1.5rem; font-weight: 700; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-back:hover { background: #f9fafb; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        
        .desc-box { font-size: 0.85rem; color: #4b5563; margin-top: 0.5rem; background: #f9fafb; padding: 0.75rem; border-radius: 6px; border-left: 3px solid #ef4444; line-height: 1.5; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-suspended { background: #fee2e2; color: #dc2626; }
        .status-dismissed { background: #f3f4f6; color: #4b5563; }

        .btn-action { padding: 0.5rem 0.75rem; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: 0.2s; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        .btn-suspend { background: #ef4444; color: white; }
        .btn-suspend:hover { background: #dc2626; }
        .btn-dismiss { background: #e5e7eb; color: #374151; }
        .btn-dismiss:hover { background: #d1d5db; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Service Provider Grievances</h1>
                <p style="color: #6b7280; margin-top: 0.5rem; font-size: 0.9rem;">Review reports submitted against service providers to maintain platform trust.</p>
            </div>
            <a href="admin_dashboard.php#reports" class="btn-back">← Back to Dashboard</a>
        </div>
        
        <?php echo $message; ?>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date Reported</th>
                        <th>Reported By</th>
                        <th>Service Provider (Target)</th>
                        <th>Complaint Details</th>
                        <th>Status & Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php 
                            $status_class = 'status-pending';
                            if (strpos($row['status'], 'Suspended') !== false) $status_class = 'status-suspended';
                            if ($row['status'] === 'Dismissed') $status_class = 'status-dismissed';
                        ?>
                        <tr>
                            <td style="color: #6b7280; font-size: 0.9rem; white-space: nowrap;"><?php echo date('M d, Y', strtotime($row['created_at'])); ?><br><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($row['reporter_name'] . ' ' . $row['reporter_last']); ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($row['reporter_role']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #1e40af;"><?php echo htmlspecialchars($row['sp_first'] . ' ' . $row['sp_last']); ?></div>
                                <div style="font-size: 0.8rem; color: #dc2626;">Account: <?php echo htmlspecialchars($row['admin_verification_status']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #111827; font-size: 0.9rem;"><?php echo htmlspecialchars($row['reason']); ?></div>
                                <div class="desc-box"><?php echo nl2br(htmlspecialchars($row['description'])); ?></div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>" style="margin-bottom: 0.75rem;"><?php echo htmlspecialchars($row['status']); ?></span>
                                
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <div style="display: flex; flex-wrap: wrap; margin-top: 0.5rem;">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="report_id" value="<?php echo $row['report_id']; ?>">
                                            <input type="hidden" name="provider_id" value="<?php echo $row['provider_id']; ?>">
                                            <input type="hidden" name="action" value="suspend">
                                            <button type="submit" class="btn-action btn-suspend" onclick="return confirm('Are you sure you want to suspend this Service Provider? This will restrict their access.');">Suspend Provider</button>
                                        </form>
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="report_id" value="<?php echo $row['report_id']; ?>">
                                            <input type="hidden" name="action" value="dismiss">
                                            <button type="submit" class="btn-action btn-dismiss" onclick="return confirm('Dismiss this report?');">Dismiss</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 3rem; height: 3rem; margin: 0 auto 1rem; color: #10b981;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p>No grievance reports filed. The platform is safe!</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>