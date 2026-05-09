<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT sp.provider_id, sp.first_name, sp.last_name, sp.barangay, u.created_at,
           uc_email.contact_value as email, uc_mobile.contact_value as phone_number
    FROM service_providers sp
    JOIN users u ON sp.provider_id = u.user_id
    LEFT JOIN user_contacts uc_email ON sp.provider_id = uc_email.user_id AND uc_email.contact_type = 'Email'
    LEFT JOIN user_contacts uc_mobile ON sp.provider_id = uc_mobile.user_id AND uc_mobile.contact_type = 'Mobile'
    WHERE sp.admin_verification_status = 'Verified' AND u.is_archived = 0
    ORDER BY u.created_at DESC
");
$stmt->execute();
$providers = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Providers - Admin</title>
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
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f8fafc; }
        
        .btn-info { background-color: #e5e7eb; color: #374151; padding: 0.4rem 0.75rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.8rem; transition: background 0.2s; display: inline-block; }
        .btn-info:hover { background-color: #d1d5db; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verified Service Providers</h1>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>

        <input type="text" id="searchInput" class="search-box" onkeyup="filterTable()" placeholder="Search by name, email, contact number, or barangay...">

        <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
            <table id="dataTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email Address</th>
                        <th>Contact No.</th>
                        <th>Barangay</th>
                        <th>Date Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $providers->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td style="color: #4b5563;"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                        <td style="color: #4b5563;"><?php echo htmlspecialchars($row['phone_number'] ?? 'N/A'); ?></td>
                        <td style="color: #4b5563;"><?php echo htmlspecialchars($row['barangay']); ?></td>
                        <td style="color: #4b5563;"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="view_service_provider.php?id=<?php echo $row['provider_id']; ?>" target="_blank" class="btn-info">View Profile</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
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
                    if (tds[j] && tds[j].innerHTML.toLowerCase().indexOf(filter) > -1) {
                        visible = true; break;
                    }
                }
                tr[i].style.display = visible ? '' : 'none';
            }
        }
    </script>
</body>
</html>