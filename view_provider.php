<?php
session_start();
include 'DATABASE/db_connect.php'; // Adjust this path if you move the file into a subfolder

// Get the provider ID from the URL
$provider_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($provider_id === 0) {
    die("Invalid provider ID.");
}

// 1. Fetch Verified Provider Details
$stmt = $conn->prepare("SELECT * FROM service_providers WHERE provider_id = ? AND admin_verification_status = 'Verified'");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    die("Service provider not found or their account is still under review.");
}

// 2. Fetch Active Services
$stmt_services = $conn->prepare("SELECT * FROM provider_services WHERE provider_id = ? AND status = 'Active' ORDER BY created_at DESC");
$stmt_services->bind_param("i", $provider_id);
$stmt_services->execute();
$services = $stmt_services->get_result();
$stmt_services->close();

// 3. Fetch Ratings & Reviews
$stmt_rating = $conn->prepare("SELECT AVG(rating) as avg_rate, COUNT(*) as total_reviews FROM service_reviews WHERE provider_id = ?");
$stmt_rating->bind_param("i", $provider_id);
$stmt_rating->execute();
$rating_data = $stmt_rating->get_result()->fetch_assoc();
$avg_rating = $rating_data['avg_rate'] ? round($rating_data['avg_rate'], 1) : 0;
$total_reviews = $rating_data['total_reviews'];
$stmt_rating->close();

$stmt_reviews = $conn->prepare("
    SELECT rev.rating, rev.comment, rev.created_at, 
           COALESCE(js.first_name, sp.first_name, 'Registered') as client_first_name, 
           COALESCE(js.last_name, sp.last_name, 'User') as client_last_name 
    FROM service_reviews rev 
    LEFT JOIN jobseekers js ON rev.client_id = js.seeker_id 
    LEFT JOIN service_providers sp ON rev.client_id = sp.provider_id 
    WHERE rev.provider_id = ? 
    ORDER BY rev.created_at DESC 
    LIMIT 10
");
$stmt_reviews->bind_param("i", $provider_id);
$stmt_reviews->execute();
$reviews = $stmt_reviews->get_result();
$stmt_reviews->close();

// 4. Handle Report Submission
$report_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    if (isset($_SESSION['user_id'])) {
        $reporter_id = $_SESSION['user_id'];
        $reason = trim($_POST['reason']);
        $description = trim($_POST['description']);
        
        if (!empty($reason) && !empty($description)) {
            $stmt_report = $conn->prepare("INSERT INTO provider_reports (provider_id, reporter_id, reason, description) VALUES (?, ?, ?, ?)");
            $stmt_report->bind_param("iiss", $provider_id, $reporter_id, $reason, $description);
            if ($stmt_report->execute()) {
                $report_message = "<div style='background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;'>Report submitted successfully. The PESO Admin will review this shortly.</div>";
            } else {
                $report_message = "<div style='background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca;'>Failed to submit report. Please try again.</div>";
            }
            $stmt_report->close();
        }
    } else {
        $report_message = "<div style='background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca;'>You must be logged in to submit a report.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']); ?> - Profile | PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; line-height: 1.5; }
        
        .navbar { background: white; padding: 1rem 2rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; }
        .navbar a { text-decoration: none; color: #4b5563; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .navbar a:hover { color: #111827; }
        
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 1.5rem; }
        
        /* Profile Header Card */
        .profile-header { background: white; border-radius: 12px; padding: 2rem; border: 1px solid #e5e7eb; display: flex; gap: 2rem; align-items: center; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .avatar-lg { width: 100px; height: 100px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: #6b7280; flex-shrink: 0; }
        .profile-info { flex: 1; }
        .profile-name { font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .verified-badge { background: #dcfce7; color: #166534; padding: 0.25rem 0.5rem; font-size: 0.75rem; font-weight: 700; border-radius: 99px; display: inline-flex; align-items: center; gap: 0.25rem; vertical-align: middle; }
        .profile-meta { color: #6b7280; font-size: 1rem; margin-bottom: 1rem; }
        
        .rating-stars { color: #f59e0b; font-size: 1.25rem; letter-spacing: 2px; }
        .rating-text { font-weight: 600; color: #374151; margin-left: 0.5rem; }
        
        .btn-book { background: #2563eb; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .btn-book:hover { background: #1d4ed8; }
        
        /* Two Column Layout */
        .layout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }
        @media (max-width: 768px) {
            .layout-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
        }
        
        .card { background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e5e7eb; margin-bottom: 2rem; }
        .card-title { font-size: 1.25rem; font-weight: 700; color: #111827; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f3f4f6; }
        
        /* Services Grid */
        .services-grid { display: grid; gap: 1rem; }
        .service-item { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; background: #f9fafb; }
        .service-name { font-weight: 700; color: #1f2937; font-size: 1.1rem; }
        .service-cat { color: #2563eb; font-size: 0.85rem; font-weight: 500; margin-bottom: 0.5rem; }
        .service-desc { color: #4b5563; font-size: 0.9rem; margin-bottom: 1rem; }
        .service-rate { font-weight: 700; color: #111827; }

        /* Reviews */
        .review-item { border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1rem; }
        .review-item:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 0.25rem; }
        .reviewer-name { font-weight: 600; color: #1f2937; }
        .review-date { font-size: 0.8rem; color: #9ca3af; }
        .review-comment { font-size: 0.95rem; color: #4b5563; font-style: italic; }

        /* Portfolio Link */
        .portfolio-link { display: inline-flex; align-items: center; gap: 0.5rem; background: #eff6ff; color: #1d4ed8; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; border: 1px solid #bfdbfe; transition: all 0.2s; width: 100%; justify-content: center; }
        .portfolio-link:hover { background: #dbeafe; }
        
        .empty-state { text-align: center; color: #6b7280; padding: 1.5rem; font-style: italic; }
        
        /* Modal Styles for Reporting */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: fadeIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h2 { font-size: 1.25rem; font-weight: 700; color: #ef4444; display: flex; align-items: center; gap: 0.5rem; }
        .close-btn { color: #9ca3af; font-size: 1.5rem; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: #111827; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 0.95rem; }
        .form-group select:focus, .form-group textarea:focus { border-color: #ef4444; outline: none; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
        .form-actions button { padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; border: none; }
        .btn-cancel { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-submit-report { background: #ef4444; color: white; }
        .btn-submit-report:hover { background: #dc2626; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

    </style>
</head>
<body>

    <nav class="navbar">
        <!-- Use javascript:history.back() for a generic back button, or hardcode it to the jobseeker dashboard if they are logged in -->
        <a href="javascript:history.back()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.2rem; height: 1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            Back
        </a>
    </nav>

    <div class="container">
        <?php echo $report_message; ?>
        
        <div class="profile-header">
            <div class="avatar-lg">
                <?php echo strtoupper(substr($provider['first_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1 class="profile-name">
                    <?php echo htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']); ?>
                    <span class="verified-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 0.8rem; height: 0.8rem;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                        Verified Provider
                    </span>
                </h1>
                <p class="profile-meta">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem; vertical-align: text-bottom;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                    <?php echo htmlspecialchars($provider['barangay']); ?>, Bongabon
                </p>
                <div style="display: flex; align-items: center; margin-bottom: 1.5rem;">
                    <span class="rating-stars">
                        <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $i <= round($avg_rating) ? '★' : '<span style="color:#e5e7eb">★</span>';
                            }
                        ?>
                    </span>
                    <span class="rating-text"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</span>
                </div>
            </div>
            <div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="javascript:void(0);" onclick="openReportModal()" style="color: #ef4444; font-size: 0.85rem; font-weight: 600; text-decoration: underline;">Report this provider</a>
                </div>
            </div>
        </div>

        <div class="layout-grid">
            <!-- Main Content: About & Services -->
            <div class="main-column">
                <div class="card">
                    <h3 class="card-title">About Me & Skills</h3>
                    <?php if (!empty($provider['skills_description'])): ?>
                        <p style="color: #4b5563; white-space: pre-wrap;"><?php echo htmlspecialchars($provider['skills_description']); ?></p>
                    <?php else: ?>
                        <p class="empty-state">This provider hasn't added a description yet.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3 class="card-title">Services Offered (<?php echo $services->num_rows; ?>)</h3>
                    <?php if ($services->num_rows > 0): ?>
                        <div class="services-grid">
                            <?php while($service = $services->fetch_assoc()): ?>
                                <div class="service-item">
                                    <div class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                    <div class="service-cat"><?php echo htmlspecialchars($service['category']); ?></div>
                                    <div class="service-desc"><?php echo nl2br(htmlspecialchars($service['description'])); ?></div>
                                    <div class="service-rate">Rate: <?php echo htmlspecialchars($service['base_rate']); ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">No specific services listed currently.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar Content: Details & Reviews -->
            <div class="side-column">
                <div class="card">
                    <h3 class="card-title">Provider Details</h3>
                    <div style="margin-bottom: 1rem;">
                        <span style="display: block; font-size: 0.85rem; color: #6b7280; font-weight: 600;">General Base Rate</span>
                        <span style="color: #111827; font-weight: 500;"><?php echo htmlspecialchars($provider['base_rate'] ?: 'Not specified'); ?></span>
                    </div>
                    
                    <?php if (!empty($provider['portfolio_path']) || !empty($provider['tesda_cert_path'])): ?>
                        <div style="margin-top: 1.5rem;">
                            <?php if (!empty($provider['portfolio_path'])): ?>
                                <a href="<?php echo htmlspecialchars(str_replace('../', '', $provider['portfolio_path'])); ?>" target="_blank" class="portfolio-link" style="margin-bottom: 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.2rem; height: 1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                                    View Portfolio
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($provider['tesda_cert_path'])): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; color: #10b981; font-weight: 600; font-size: 0.9rem; padding: 0.5rem 0;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1.2rem; height: 1.2rem;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                                    Has TESDA Certificate
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3 class="card-title">Recent Reviews</h3>
                    <?php if ($reviews->num_rows > 0): ?>
                        <?php while($review = $reviews->fetch_assoc()): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['client_first_name']); ?></span>
                                    <span style="color: #f59e0b; font-size: 0.85rem;">
                                        <?php for($i=0; $i<5; $i++) echo $i < $review['rating'] ? '★' : '<span style="color:#e5e7eb">★</span>'; ?>
                                    </span>
                                </div>
                                <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                <?php if($review['comment']): ?>
                                    <p class="review-comment">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="empty-state">No reviews yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Provider Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    Report Provider
                </h2>
                <span class="close-btn" onclick="closeReportModal()">&times;</span>
            </div>
            <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 1.5rem;">PESO Bongabon takes user safety seriously. Please describe the issue below, and our Admin team will investigate.</p>
            <form method="POST">
                <input type="hidden" name="submit_report" value="1">
                <div class="form-group">
                    <label for="reason">Reason for Report</label>
                    <select id="reason" name="reason" required>
                        <option value="">-- Select a Reason --</option>
                        <option value="Scam / Fraudulent Activity">Scam / Fraudulent Activity</option>
                        <option value="No Show / Did Not Arrive">No Show / Did Not Arrive</option>
                        <option value="Unprofessional Behavior">Unprofessional Behavior</option>
                        <option value="Inappropriate Content">Inappropriate Content</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Detailed Description</label>
                    <textarea id="description" name="description" placeholder="Please provide specific details about what happened..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeReportModal()">Cancel</button>
                    <button type="submit" class="btn-submit-report">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReportModal() { document.getElementById('reportModal').style.display = 'flex'; }
        function closeReportModal() { document.getElementById('reportModal').style.display = 'none'; }
    </script>
</body>
</html>