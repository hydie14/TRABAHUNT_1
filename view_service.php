<?php
session_start();
include 'DATABASE/db_connect.php';

// Get the service ID from the URL
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($service_id === 0) {
    die("Invalid service ID.");
}

// Fetch Service and Provider Details
$stmt = $conn->prepare("
    SELECT ps.*, sp.provider_id, sp.first_name, sp.last_name, sp.barangay, sp.skills_description as provider_skills,
           (SELECT AVG(rating) FROM service_reviews WHERE provider_id = ps.provider_id) as avg_rating,
           (SELECT COUNT(*) FROM service_reviews WHERE provider_id = ps.provider_id) as review_count
    FROM provider_services ps
    JOIN service_providers sp ON ps.provider_id = sp.provider_id
    WHERE ps.service_id = ? AND sp.admin_verification_status = 'Verified' AND ps.status = 'Active'
");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    die("Service not found or is no longer available.");
}

// Determine the correct dashboard path based on user role
$dashboard_path = 'DASHBOARD%20jobseeker'; // Default
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Employer') {
    $dashboard_path = 'DASHBOARD%20employer';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['service_name']); ?> - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; line-height: 1.5; }
        
        .navbar { background: white; padding: 1rem 2rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; }
        .navbar a { text-decoration: none; color: #4b5563; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .navbar a:hover { color: #111827; }
        
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1.5rem; }
        
        .card { background: white; border-radius: 12px; padding: 2rem; border: 1px solid #e5e7eb; margin-bottom: 2rem; }
        .card-title { font-size: 1.5rem; font-weight: 700; color: #111827; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f3f4f6; }
        
        .service-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 2rem; }
        .service-info { flex: 1; }
        .service-name { font-size: 2.5rem; font-weight: 800; color: #111827; margin-bottom: 0.3rem; }
        .service-category { font-size: 1.1rem; font-weight: 600; color: #2563eb; margin-bottom: 0.5rem; }
        .service-rate { font-size: 1.5rem; font-weight: 800; color: #111827; margin-bottom: 1.5rem; }
        .service-description { color: #4b5563; line-height: 1.6; }

        .btn-book { background: #2563eb; color: white; border: none; padding: .5rem 2rem; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .btn-book:hover { background: #1d4ed8; }

        .provider-card { display: flex; gap: 1.5rem; align-items: center; border: 1px solid #e5e7eb; padding: 1.5rem; border-radius: 12px; background: #f9fafb; }
        .provider-avatar { width: 60px; height: 60px; background: #eff6ff; color: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; flex-shrink: 0; }
        .provider-name { font-size: 1.1rem; font-weight: 700; color: #111827; }
        .provider-rating { display: flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem; }
        .stars { color: #f59e0b; font-size: 1rem; }
        .rating-text { font-size: 0.85rem; color: #4b5563; font-weight: 500; }
        .btn-view-profile { text-decoration: none; background: #e5e7eb; color: #374151; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; margin-left: auto; }
        .btn-view-profile:hover { background: #d1d5db; }

        @media (max-width: 768px) {
            .service-header { flex-direction: column; }
            .provider-card { flex-direction: column; text-align: center; }
            .btn-view-profile { margin-left: 0; margin-top: 1rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="javascript:history.back()">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.2rem; height: 1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            Back to Services
        </a>
    </nav>

    <div class="container">
        <div class="card">
            <div class="service-header">
                <div class="service-info">
                    <h1 class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></h1>
                    <p class="service-category"><?php echo htmlspecialchars($service['category']); ?></p>
                    <p class="service-rate">₱<?php echo number_format((float) filter_var($service['base_rate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 2); ?></p>
                    <p class="service-description"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                </div>
                <div style="flex-shrink: 0;">
                    <a href="<?php echo $dashboard_path; ?>/book_service.php?id=<?php echo $service['service_id']; ?>" class="btn-book">Book This Service</a>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">About the Provider</h3>
            <div class="provider-card">
                <div class="provider-avatar"><?php echo strtoupper(substr($service['first_name'], 0, 1)); ?></div>
                <div>
                    <div class="provider-name"><?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?></div>
                    <div class="provider-rating">
                        <span class="stars">
                            <?php 
                                $rating = $service['avg_rating'] ? round($service['avg_rating'], 1) : 0;
                                for($i=1; $i<=5; $i++) { echo $i <= round($rating) ? '★' : '<span style="color:#e5e7eb">★</span>'; }
                            ?>
                        </span>
                        <span class="rating-text"><?php echo $rating; ?> (<?php echo $service['review_count']; ?> reviews)</span>
                    </div>
                </div>
                <a href="view_provider.php?id=<?php echo $service['provider_id']; ?>" class="btn-view-profile">View Full Profile</a>
            </div>
        </div>
    </div>
</body>
</html>