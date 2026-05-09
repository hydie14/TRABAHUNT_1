<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Fetch all pending employers
$stmt = $conn->prepare("
    SELECT e.*, u.created_at
    FROM employers e
    JOIN users u ON e.employer_id = u.user_id
    WHERE e.admin_verification_status = 'Pending'
    ORDER BY u.created_at ASC
");
$stmt->execute();
$pending_employers = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Approval - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; font-size: 0.875rem; display: inline-block; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-info { background-color: #3b82f6; color: white; }
        .btn-info:hover { background-color: #2563eb; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }

        @media (max-width: 768px) {
            html { font-size: 14px; }
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Employer Registration Approval</h1>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <?php if ($pending_employers->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Employer Type</th>
                        <th>Contact Person</th>
                        <th>Date Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($employer = $pending_employers->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($employer['company_name']); ?></div>
                                <?php if($employer['trade_name']): ?>
                                    <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($employer['trade_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($employer['employer_type']); ?></td>
                            <td><?php echo htmlspecialchars($employer['contact_person_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($employer['created_at'])); ?></td>
                            <td>
                                <a href="view_employer.php?id=<?php echo $employer['employer_id']; ?>" class="btn btn-info">View & Verify</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No pending employer registrations for approval.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>