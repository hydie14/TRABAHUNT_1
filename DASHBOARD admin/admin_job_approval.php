<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Fetch pending job posts
$stmt = $conn->prepare("
    SELECT jp.*, e.company_name
    FROM job_postings jp
    JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.status = 'Pending_Approval'
    ORDER BY jp.created_at ASC
");
$stmt->execute();
$pending_jobs = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Post Approval - Admin</title>
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
        .btn-secondary { background: #e5e7eb; color: #374151; }
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
            <h1>Job Post Approval</h1>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <?php if ($pending_jobs->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Job Title</th>
                        <th>Date Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($job = $pending_jobs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo ucwords(htmlspecialchars($job['company_name'])); ?></td>
                            <td><?php echo ucwords(htmlspecialchars($job['job_title'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                            <td class="actions">
                                <a href="admin_view_job.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-info">View Details</a>
                                <form action="admin_verify_job.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this job post?');">Approve</button>
                                </form>
                                <form action="admin_verify_job.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this job post?');">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No pending job posts for approval.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>