<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

// Handle Restore Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_user_id'])) {
    $restore_id = (int)$_POST['restore_user_id'];
    $restore_stmt = $conn->prepare("UPDATE users SET is_archived = 0, archived_at = NULL WHERE user_id = ?");
    $restore_stmt->bind_param("i", $restore_id);
    
    if ($restore_stmt->execute()) {
        $_SESSION['success_msg'] = "User account successfully restored!";
    }
    $restore_stmt->close();
    header("Location: admin_users_archive.php");
    exit();
}

// Fetch Archived Users
$query = "
    SELECT u.user_id, u.role, u.archived_at,
           COALESCE(js.first_name, sp.first_name) AS first_name,
           COALESCE(js.last_name, sp.last_name) AS last_name,
           e.company_name,
           uc.contact_value AS email
    FROM users u
    LEFT JOIN jobseekers js ON u.user_id = js.seeker_id AND u.role = 'JobSeeker'
    LEFT JOIN service_providers sp ON u.user_id = sp.provider_id AND u.role = 'ServiceProvider'
    LEFT JOIN employers e ON u.user_id = e.employer_id AND u.role = 'Employer'
    LEFT JOIN user_contacts uc ON u.user_id = uc.user_id AND uc.contact_type = 'Email' AND uc.is_primary = 1
    WHERE u.is_archived = 1
    ORDER BY u.archived_at DESC
";
$archived_users = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Users - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; margin: 0; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        
        .search-box { width: 100%; padding: 0.85rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; margin-bottom: 1.5rem; box-sizing: border-box; font-size: 1rem; }
        .search-box:focus { outline: none; border-color: #1e40af; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #fef2f2; font-weight: 600; color: #991b1b; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f8fafc; }
        
        .btn-restore { background-color: #10b981; color: white; padding: 0.4rem 0.75rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.8rem; transition: background 0.2s; border: none; cursor: pointer; }
        .btn-restore:hover { background-color: #059669; }

        .role-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #e5e7eb; color: #374151; }
        
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; font-weight: 500; background: #d1fae5; color: #065f46; border: 1px solid #34d399; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>User Accounts Recycle Bin</h1>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <?php if(isset($_SESSION['success_msg'])): ?>
            <div class="message"><?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?></div>
        <?php endif; ?>

        <input type="text" id="searchInput" class="search-box" onkeyup="filterTable()" placeholder="Search archived users by name, email, or role...">

        <div style="overflow-x: auto; border: 1px solid #fecaca; border-radius: 8px;">
            <table id="dataTable">
                <thead>
                    <tr>
                        <th>Account Type</th>
                        <th>Name / Company</th>
                        <th>Email Address</th>
                        <th>Date Archived</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($archived_users->num_rows > 0): ?>
                        <?php while($row = $archived_users->fetch_assoc()): ?>
                        <tr>
                            <td><span class="role-badge"><?php echo htmlspecialchars($row['role']); ?></span></td>
                            <td style="font-weight: 600; color: #111827;">
                                <?php 
                                    if($row['role'] === 'Employer') {
                                        echo htmlspecialchars($row['company_name']);
                                    } else {
                                        echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); 
                                    }
                                ?>
                            </td>
                            <td style="color: #4b5563;"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                            <td style="color: #dc2626; font-weight: 500;"><?php echo date("M d, Y h:i A", strtotime($row['archived_at'])); ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="restore_user_id" value="<?php echo $row['user_id']; ?>">
                                    <button type="submit" class="btn-restore" onclick="return confirm('Are you sure you want to restore this account? They will be able to log in again.');">⟲ Restore Account</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #6b7280; padding: 2rem;">No archived accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('dataTable');
            const tr = table.getElementsByTagName('tr');
            for (let i = 1; i < tr.length; i++) {
                let visible = false;
                const tds = tr[i].getElementsByTagName('td');
                for (let j = 0; j < tds.length; j++) {
                    if (tds[j] && tds[j].innerText.toLowerCase().indexOf(filter) > -1) { visible = true; break; }
                }
                tr[i].style.display = visible ? '' : 'none';
            }
        }
    </script>
</body>
</html>