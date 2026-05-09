<?php
session_start();
include '../DATABASE/db_connect.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Include Composer's autoloader

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

// --- 1. NSRP Report (National Skills Registration Program) ---

// Fetch Gender Breakdown
$gender_query = "SELECT gender, COUNT(*) as count FROM jobseekers GROUP BY gender";
$gender_result = $conn->query($gender_query);

$gender_labels = [];
$gender_counts = [];
$total_applicants = 0;

if ($gender_result) {
    while ($row = $gender_result->fetch_assoc()) {
        // Handle cases where gender might be empty or null
        $label = !empty($row['gender']) ? $row['gender'] : 'Unspecified';
        $gender_labels[] = $label;
        $gender_counts[] = (int)$row['count'];
        $total_applicants += (int)$row['count'];
    }
}

// --- 2. LMI Monthly Report (Labor Market Information) ---

// Total Vacancies Solicited (Sum of vacancies_count)
$vacancies_query = "SELECT SUM(vacancies_count) as total_vacancies FROM job_postings";
$vacancies_result = $conn->query($vacancies_query);
$total_vacancies = $vacancies_result->fetch_assoc()['total_vacancies'] ?? 0;

// Total Applicants Referred (Status = 'Referral_Issued')
$referred_query = "SELECT COUNT(*) as total_referred FROM referrals_applications WHERE status IN ('Issue Referral Letter', 'Referral_Issued', 'Hired', 'Hired / Placed', 'Accepted', 'Pending Interview')";
$referred_result = $conn->query($referred_query);
$total_referred = $referred_result->fetch_assoc()['total_referred'] ?? 0;

// Total Applicants Placed (Status = 'Hired' or 'Hired / Placed')
$placed_query = "SELECT COUNT(*) as total_placed FROM referrals_applications WHERE status IN ('Hired', 'Hired / Placed')";
$placed_result = $conn->query($placed_query);
$total_placed = $placed_result->fetch_assoc()['total_placed'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PESO Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; color: #1f2937; display: flex; min-height: 100vh; }
        
        /* Sidebar (Consistent with Dashboard) */
        .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header { padding: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f3f4f6; }
        .logo { height: 40px; width: 40px; }
        .brand-name { font-weight: 700; font-size: 1.25rem; color: #1e40af; }
        .nav-menu { padding: 1.5rem 1rem; flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #4b5563; border-radius: 8px; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: #eff6ff; color: #1e40af; }
        
        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.875rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem; }
        
        /* Report Cards */
        .report-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; margin-bottom: 2rem; }
        .section-title { font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 1.5rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 1rem; }
        
        .summary-card { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 12px; padding: 1.5rem; text-align: center; margin-bottom: 2rem; max-width: 300px; }
        .summary-value { font-size: 2.5rem; font-weight: 700; color: #1e40af; }
        .summary-label { color: #6b7280; font-weight: 500; font-size: 0.9rem; }
        
        .chart-wrapper { position: relative; height: 300px; width: 100%; display: flex; justify-content: center; }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../BONGABON.png" alt="Logo" class="logo">
            <span class="brand-name">PESO Admin</span>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php?tab=verification" class="nav-item">📄 Verification</a>
            <a href="admin_dashboard.php?tab=matching" class="nav-item">🤝 Job Matching</a>
            <a href="reports.php" class="nav-item active">📊 Reports</a>
            <a href="../LOGIN%20SIGNUP/logout.php" class="nav-item" style="color: #ef4444; margin-top: auto;">🚪 Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">PESO Statistics & Reports</h1>
            <p style="color: #6b7280;">Visualizing NSRP and LMI data.</p>
        </div>

        <!-- 1. NSRP Report -->
        <div class="report-section">
            <h2 class="section-title">NSRP Report (National Skills Registration Program)</h2>
            
            <div style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: center;">
                <div class="summary-card">
                    <div class="summary-value"><?php echo number_format($total_applicants); ?></div>
                    <div class="summary-label">Total Applicants Registered</div>
                </div>
                
                <div class="chart-wrapper" style="max-width: 400px;">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 2. LMI Report -->
        <div class="report-section">
            <h2 class="section-title">LMI Monthly Report (Labor Market Information)</h2>
            <div class="chart-wrapper">
                <canvas id="lmiChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        // --- NSRP: Gender Pie Chart ---
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($gender_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($gender_counts); ?>,
                    backgroundColor: ['#3b82f6', '#ec4899', '#10b981', '#f59e0b'], // Blue, Pink, Green, Amber
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    title: { display: true, text: 'Registered Applicants by Gender' }
                }
            }
        });

        // --- LMI: Monthly Performance Bar Chart ---
        const lmiCtx = document.getElementById('lmiChart').getContext('2d');
        new Chart(lmiCtx, {
            type: 'bar',
            data: {
                labels: ['Vacancies Solicited', 'Applicants Referred', 'Applicants Placed'],
                datasets: [{
                    label: 'Total Count',
                    data: [
                        <?php echo $total_vacancies; ?>, 
                        <?php echo $total_referred; ?>, 
                        <?php echo $total_placed; ?>
                    ],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)', // Blue
                        'rgba(245, 158, 11, 0.8)', // Amber
                        'rgba(16, 185, 129, 0.8)'  // Green
                    ],
                    borderColor: [
                        'rgb(59, 130, 246)',
                        'rgb(245, 158, 11)',
                        'rgb(16, 185, 129)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Recruitment & Placement Performance' }
                }
            }
        });
    </script>
</body>
</html>