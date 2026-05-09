<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Fetch pending service posts
$stmt = $conn->prepare("
    SELECT ps.*, sp.first_name, sp.last_name
    FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.provider_id
    WHERE ps.status = 'Pending_Approval'
    ORDER BY ps.created_at ASC
");
$stmt->execute();
$pending_services = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Post Approval - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { color: #111827; font-size: 1.5rem; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; font-size: 0.875rem; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-info { background-color: #3b82f6; color: white; }
        .btn-info:hover { background-color: #2563eb; }
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        .actions { display: flex; gap: 0.5rem; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Service Post Approval</h1>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <?php if ($pending_services->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Date Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($service = $pending_services->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo ucwords(htmlspecialchars($service['first_name'] . ' ' . $service['last_name'])); ?></td>
                            <td style="font-weight: 600;"><?php echo ucwords(htmlspecialchars($service['service_name'])); ?></td>
                            <td><?php echo ucwords(htmlspecialchars($service['category'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($service['created_at'])); ?></td>
                            <td class="actions">
                                <a href="admin_view_service.php?service_id=<?php echo $service['service_id']; ?>" class="btn btn-info">View Details</a>
                                <form action="admin_verify_service.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this service post?');">Approve</button>
                                </form>
                                <form action="admin_verify_service.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this service post?');">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No pending service posts for approval.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>