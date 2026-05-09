<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Fetch closed/expired job posts
$stmt = $conn->prepare("
    SELECT jp.*, e.company_name
    FROM job_postings jp
    JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.status IN ('Closed', 'Expired')
    ORDER BY jp.created_at DESC
");
$stmt->execute();
$archive_jobs = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Archive - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; font-size: 0.875rem; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-info { background-color: #3b82f6; color: white; }
        .btn-info:hover { background-color: #2563eb; }
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        .actions { display: flex; gap: 0.5rem; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-expired { background: #fee2e2; color: #dc2626; }
        .status-closed { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Job Archive (Closed / Expired)</h1>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <input type="text" id="archiveSearch" onkeyup="filterArchive()" placeholder="Search by job title or company name..." style="flex: 1; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; box-sizing: border-box; min-width: 200px;">
            <select id="statusFilter" onchange="filterArchive()" style="padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; background: white; min-width: 150px;">
                <option value="">All Status</option>
                <option value="closed">Closed</option>
                <option value="expired">Expired</option>
            </select>
        </div>

        <?php if ($archive_jobs->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Job Title</th>
                        <th>Status</th>
                        <th>Reason / Details</th>
                        <th>Date Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($job = $archive_jobs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($job['job_title']); ?></td>
                            <td><span class="status-badge <?php echo $job['status'] === 'Expired' ? 'status-expired' : 'status-closed'; ?>"><?php echo htmlspecialchars($job['status']); ?></span></td>
                            <td style="color: #6b7280; font-size: 0.875rem;">
                                <?php 
                                    if($job['close_reason']) { echo "Reason: " . htmlspecialchars($job['close_reason']); } 
                                    elseif($job['status'] === 'Expired') { echo "Passed validity date: " . date('M d, Y', strtotime($job['valid_until'])); } 
                                    else { echo "N/A"; }
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                            <td class="actions"><a href="admin_view_job.php?job_id=<?php echo $job['job_id']; ?>" class="btn btn-info">View Details</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No closed or expired job posts found in the archive.</p></div>
        <?php endif; ?>
    </div>

    <script>
        function filterArchive() {
            const searchFilter = document.getElementById('archiveSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const table = document.querySelector('table');
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const company = row.cells[0].textContent.toLowerCase();
                const title = row.cells[1].textContent.toLowerCase();
                const status = row.cells[2].textContent.toLowerCase().trim();
                
                const matchesSearch = company.includes(searchFilter) || title.includes(searchFilter);
                const matchesStatus = statusFilter === "" || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>