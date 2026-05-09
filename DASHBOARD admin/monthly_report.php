<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$month_name = date('F Y', strtotime($month . '-01'));

// --- LMI REPORT DATA (Labor Market Information) ---

// 1. Total Vacancies Solicited (New Job Postings)
$stmt = $conn->prepare("SELECT COALESCE(SUM(vacancies_count), 0) as count FROM job_postings WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("s", $month);
$stmt->execute();
$lmi_vacancies = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 2. Total Applicants Referred (Status: Issue Referral Letter, Pending Interview, Hired / Placed)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications WHERE status NOT IN ('Pending', 'Pending_Docs', 'Verified', 'Rejected', 'Rejected / Not Qualified') AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("s", $month);
$stmt->execute();
$lmi_referred = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 3. Total Applicants Placed (Status: Hired / Placed)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM referrals_applications WHERE status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment') AND DATE_FORMAT(COALESCE(placement_date, created_at), '%Y-%m') = ?");
$stmt->bind_param("s", $month);
$stmt->execute();
$lmi_placed = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 4. Breakdown of Placements by Employment Type
$stmt = $conn->prepare("
    SELECT jp.employment_type, COUNT(*) as type_count
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    WHERE ra.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment') 
    AND DATE_FORMAT(COALESCE(ra.placement_date, ra.created_at), '%Y-%m') = ?
    GROUP BY jp.employment_type
");
$stmt->bind_param("s", $month);
$stmt->execute();
$placement_breakdown = $stmt->get_result();
$stmt->close();

// --- NSRP REPORT DATA (National Skills Registration Program) ---

// 1. Total Applicants Registered (New Job Seekers)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'JobSeeker' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->bind_param("s", $month);
$stmt->execute();
$nsrp_registered = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 2. Gender Breakdown
$stmt = $conn->prepare("
    SELECT js.gender, COUNT(*) as count 
    FROM users u 
    JOIN jobseekers js ON u.user_id = js.seeker_id 
    WHERE u.role = 'JobSeeker' AND DATE_FORMAT(u.created_at, '%Y-%m') = ? 
    GROUP BY js.gender
");
$stmt->bind_param("s", $month);
$stmt->execute();
$gender_result = $stmt->get_result();
$gender_data = ['Male' => 0, 'Female' => 0];
while($row = $gender_result->fetch_assoc()) {
    $gender_data[$row['gender']] = $row['count'];
}
$stmt->close();


// Top companies by applications
$stmt = $conn->prepare("
    SELECT e.company_name, COUNT(*) as app_count
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    WHERE DATE_FORMAT(ra.created_at, '%Y-%m') = ?
    GROUP BY e.company_name
    ORDER BY app_count DESC
    LIMIT 5
");
$stmt->bind_param("s", $month);
$stmt->execute();
$top_companies = $stmt->get_result();
$stmt->close();

// Detailed applications & placement list
$status_condition = "";
if ($status_filter === 'hired') {
    $status_condition = " AND ra.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment') ";
}

$stmt = $conn->prepare("
    SELECT 
        js.first_name, js.last_name, js.contact_no, uc.contact_value as email, ucm.contact_value as mobile,
        jp.job_title, e.company_name, ra.status, 
        ra.created_at as referral_date, ra.placement_date, ra.deployment_date
    FROM referrals_applications ra
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    LEFT JOIN user_contacts ucm ON js.seeker_id = ucm.user_id AND ucm.contact_type = 'Mobile'
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    WHERE (DATE_FORMAT(ra.created_at, '%Y-%m') = ? OR DATE_FORMAT(ra.placement_date, '%Y-%m') = ? OR DATE_FORMAT(ra.deployment_date, '%Y-%m') = ?)
    $status_condition
    ORDER BY ra.created_at DESC, ra.placement_date DESC
");
$stmt->bind_param("sss", $month, $month, $month);
$stmt->execute();
$applications_list = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report - <?php echo $month_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 3px solid #1e40af; }
        .header img { width: 80px; margin-bottom: 1rem; }
        .header h1 { color: #1e40af; margin-bottom: 0.5rem; }
        .header p { color: #6b7280; }
        .month-selector { margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center; }
        .month-selector input { padding: 0.5rem; border: 2px solid #e5e7eb; border-radius: 6px; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; }
        .btn-primary { background: #1e40af; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        .btn-info { background: #3b82f6; color: white; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; margin-bottom: 2rem; }
        .stat-box { background: #f9fafb; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #1e40af; }
        .stat-box h3 { font-size: 1.5rem; color: #1e40af; margin-bottom: 0.5rem; }
        .stat-box p { color: #6b7280; font-size: 0.8rem; }
        .report-card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1rem; margin-bottom: 2rem; }
        .report-title { font-size: 1.5rem; color: #1e40af; margin-bottom: 1.5rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
        .section { margin-bottom: 2rem; }
        .section h2 { color: #1f2937; margin-bottom: 1rem; font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #d1fae5; color: #059669; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none; }
            .container { box-shadow: none; }
        }
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .container { padding: 1.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .month-selector form { flex-direction: column; align-items: stretch; gap: 0.75rem; }
            .month-selector form label { margin-bottom: -0.25rem; }
            .btn { width: 100%; text-align: center; box-sizing: border-box; }
            .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../BONGABON.png" alt="Logo">
            <h1>PESO BONGABON, NUEVA ECIJA</h1>
            <h2>Monthly Employment Report</h2>
            <p><?php echo $month_name; ?></p>
        </div>

        <div class="month-selector no-print">
            <form method="get" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <label>Select Month:</label>
                <input type="month" name="month" value="<?php echo $month; ?>" required>
                <label>Filter Status:</label>
                <select name="status_filter" style="padding: 0.5rem; border: 2px solid #e5e7eb; border-radius: 6px; font-family: inherit;">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="hired" <?php echo $status_filter === 'hired' ? 'selected' : ''; ?>>Hired / For Deployment Only</option>
                </select>

                <button type="submit" class="btn btn-primary">Generate Report</button>
                <button type="button" class="btn btn-success" onclick="window.print()">Print Report</button>
                <button type="button" class="btn btn-info" onclick="exportTableToCSV('PESO_Monthly_Report_<?php echo $month; ?>.csv')">Export to CSV</button>
                <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
            </form>
        </div>

        <!-- LMI Report Section -->
        <div class="report-card">
            <h2 class="report-title">1. LMI Monthly Report (Labor Market Information)</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3><?php echo $lmi_vacancies; ?></h3>
                    <p>Total Vacancies Solicited</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $lmi_referred; ?></h3>
                    <p>Total Applicants Referred</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $lmi_placed; ?></h3>
                    <p>Total Applicants Placed</p>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                <h4 style="color: #4b5563; margin-bottom: 0.75rem; font-size: 0.95rem;">Placement Breakdown by Employment Type:</h4>
                <ul style="list-style: none; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                    <?php if($placement_breakdown->num_rows > 0): ?>
                        <?php while($pt = $placement_breakdown->fetch_assoc()): ?>
                            <li style="font-size: 0.9rem; color: #1f2937; background: white; padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid #d1d5db;"><strong><?php echo htmlspecialchars($pt['employment_type']); ?>:</strong> <?php echo $pt['type_count']; ?></li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li style="font-size: 0.9rem; color: #6b7280;">No placements for this month.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- NSRP Report Section -->
        <div class="report-card">
            <h2 class="report-title">2. NSRP Report (National Skills Registration Program)</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3><?php echo $nsrp_registered; ?></h3>
                    <p>Total Applicants Registered</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $gender_data['Male']; ?></h3>
                    <p>Male Applicants</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $gender_data['Female']; ?></h3>
                    <p>Female Applicants</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Top Companies by Applications</h2>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Applications Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($company = $top_companies->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                        <td><?php echo $company['app_count']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($top_companies->num_rows == 0): ?>
                    <tr><td colspan="2" style="text-align: center; color: #6b7280;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div class="section">
            <h2>Applicant Tracking & Placement Record</h2>
            <div class="table-responsive">
            <table id="placementTable">
                <thead>
                    <tr>
                        <th>Applicant Name</th>
                        <th>Contact Info</th>
                        <th>Position</th>
                        <th>Establishment</th>
                        <th>Status</th>
                        <th>Referral Date</th>
                        <th>Hired / Placed Date</th>
                        <th>Deployment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($app = $applications_list->fetch_assoc()): 
                        $statusClass = 'status-pending';
                        if(in_array($app['status'], ['Issue Referral Letter', 'Referral_Issued'])) $statusClass = 'status-approved';
                        if(in_array($app['status'], ['Hired', 'Hired / Placed', 'Accepted', 'For Deployment'])) $statusClass = 'status-approved';
                        if(in_array($app['status'], ['Rejected', 'Rejected / Not Qualified', 'Terminated', 'Resigned'])) $statusClass = 'status-rejected';
                        $display_contact = !empty($app['contact_no']) ? $app['contact_no'] : (!empty($app['mobile']) ? $app['mobile'] : 'N/A');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                        <td>
                            <div style="font-size: 0.85rem; color: #1f2937;"><?php echo htmlspecialchars($display_contact); ?></div>
                            <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($app['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($app['referral_date'])); ?></td>
                        <td>
                            <?php echo $app['placement_date'] ? date('M d, Y', strtotime($app['placement_date'])) : '<span style="color:#9ca3af; font-size:0.8rem;">Not yet</span>'; ?>
                        </td>
                        <td>
                            <?php echo $app['deployment_date'] ? date('M d, Y', strtotime($app['deployment_date'])) : '<span style="color:#9ca3af; font-size:0.8rem;">Not set</span>'; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if($applications_list->num_rows == 0): ?>
                    <tr><td colspan="8" style="text-align: center; color: #6b7280;">No records found for this month</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid #e5e7eb;">
            <p style="color: #6b7280; font-size: 0.875rem; text-align: center;">
                <?php echo date('F d, Y h:i A'); ?> | PESO Bongabon, Nueva Ecija
            </p>
        </div>
    </div>

    <script>
        function exportTableToCSV(filename) {
            var csv = [];
            var table = document.getElementById("placementTable");
            var rows = table.querySelectorAll("tr");
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (var j = 0; j < cols.length; j++) {
                    // Remove newlines and extra spaces, escape double quotes
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                csv.push(row.join(","));
            }

            // Add BOM for UTF-8 Excel compatibility
            var csvContent = "\uFEFF" + csv.join("\n");
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            
            var link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
