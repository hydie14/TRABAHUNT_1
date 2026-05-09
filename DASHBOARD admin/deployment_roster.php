<?php
session_start();
include '../DATABASE/db_connect.php';

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

// Fetch all upcoming deployments
$query = "
    SELECT ra.application_id, js.first_name, js.last_name, js.contact_no, uc.contact_value as email,
           jp.job_title, e.company_name, ra.deployment_date, ra.deployment_message
    FROM referrals_applications ra
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    WHERE ra.status = 'For Deployment' AND ra.deployment_date IS NOT NULL
    ORDER BY ra.deployment_date ASC
";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Roster - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { color: #111827; margin: 0; font-size: 1.5rem; font-weight: 700; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-back:hover { background: #f9fafb; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .deployment-badge { background: #dbeafe; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        
        .remarks-box { font-size: 0.85rem; color: #4b5563; margin-top: 0.5rem; background: #f9fafb; padding: 0.75rem; border-radius: 6px; border-left: 3px solid #3b82f6; }

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
            <div>
                <h1>Deployment Roster</h1>
                <p style="color: #6b7280; margin-top: 0.5rem; font-size: 0.9rem;">Monitor all upcoming job seeker deployments scheduled by employers.</p>
            </div>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Job Seeker</th>
                        <th>Position</th>
                        <th>Employer</th>
                        <th>Deployment Schedule</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: #1f2937; text-transform: capitalize;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($row['contact_no'] ?? 'N/A'); ?></div>
                            </td>
                            <td style="text-transform: capitalize;"><?php echo htmlspecialchars($row['job_title']); ?></td>
                            <td style="font-weight: 500; color: #1e40af; text-transform: capitalize;"><?php echo htmlspecialchars($row['company_name']); ?></td>
                            <td>
                                <span class="deployment-badge"><?php echo date('F d, Y - h:i A', strtotime($row['deployment_date'])); ?></span>
                                <?php if(!empty($row['deployment_message'])): ?>
                                    <div class="remarks-box">
                                        <strong>Instructions:</strong> <?php echo nl2br(htmlspecialchars($row['deployment_message'])); ?>
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
                <p>No upcoming deployments scheduled.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>