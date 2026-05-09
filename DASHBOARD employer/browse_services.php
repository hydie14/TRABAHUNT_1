<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in and is an Employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch employer details for the sidebar
$stmt_user = $conn->prepare("SELECT company_name, profile_picture FROM employers WHERE employer_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

// Search and Filter logic (same as jobseeker)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$query = "SELECT ps.*, sp.first_name, sp.last_name, sp.barangay,
          (SELECT AVG(rating) FROM service_reviews sr WHERE sr.provider_id = ps.provider_id) as avg_rating,
          (SELECT COUNT(*) FROM service_reviews sr WHERE sr.provider_id = ps.provider_id) as review_count
          FROM provider_services ps
          JOIN service_providers sp ON ps.provider_id = sp.provider_id
          WHERE sp.admin_verification_status = 'Verified' AND ps.status = 'Active'";

$params = [];
$types = "";

if ($search !== '') {
    $query .= " AND (ps.service_name LIKE ? OR ps.description LIKE ? OR sp.first_name LIKE ? OR sp.last_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if ($category !== '') {
    $query .= " AND ps.category = ?";
    $params[] = $category;
    $types .= "s";
}

$query .= " ORDER BY sp.provider_id DESC, ps.created_at DESC";

$stmt = $conn->prepare($query);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$services = $stmt->get_result();
$stmt->close();

$categories = [
    "Accounting & Bookkeeping", "Aircon & Refrigeration Repair", "Automotive & Mechanic Services", 
    "Barber & Salon Services", "Beauty & Makeup", "Carpentry & Woodwork", "Catering & Food Services", 
    "Cleaning & Housekeeping", "Computer & IT Support", "Construction & Masonry", "Consulting & Business Services", 
    "Creative, Design & Multimedia", "Delivery, Logistics & Moving", "Education, Tutoring & Training", 
    "Electrical Services", "Event Planning & Management", "Farming & Agriculture Services", "Health, Wellness & Fitness", 
    "Landscaping & Gardening", "Laundry & Dry Cleaning", "Legal Services", "Massage Therapy", "Nanny & Babysitting", 
    "Pet Care & Grooming", "Photography & Videography", "Plumbing Services", "Security & Guard Services", 
    "Tailoring & Alterations", "Translation & Writing", "Welding & Fabrication"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services - Employer Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; display: flex; min-height: 100vh; }
        
        /* Sidebar (Employer) */
        .sidebar { width: 260px; background: white; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100%; }
        .sidebar-header { padding: 1rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid #f3f4f6; }
        .logo { height: 60px; width: 60px; object-fit: contain; }
        .brand-name { font-weight: 800; font-size: 1.1rem; color: #1e40af; letter-spacing: -0.01em; }
        .nav-menu { padding: 1rem 0.75rem; flex: 1; display: flex; flex-direction: column; gap: 0.15rem; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; text-decoration: none; color: #64748b; border-radius: 8px; font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: #f8fafc; color: #0f172a; border-left-color: #cbd5e1; }
        .nav-item.active { background: #eff6ff; color: #2563eb; border-left-color: #2563eb; font-weight: 600; }
        .nav-icon svg { width: 1.1rem !important; height: 1.1rem !important; }
        .user-profile { padding: 0.75rem; border-top: 1px solid #f3f4f6; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; position: relative; transition: background 0.2s; }
        .user-profile:hover { background: #f8fafc; }
        .avatar { width: 32px; height: 32px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; color: #6b7280; flex-shrink: 0; overflow: hidden; }
        .user-info { flex: 1; overflow: hidden; }
        .user-name { font-weight: 600; font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #111827; }
        .user-role { font-size: 0.7rem; color: #94a3b8; font-weight: 500; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 2rem; font-weight: 800; color: #111827; margin-bottom: 0.5rem; }
        .page-desc { color: #6b7280; font-size: 1rem; }

        /* Filters */
        .filter-bar { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-size: 0.85rem; font-weight: 600; color: #374151; }
        .filter-group input, .filter-group select { padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .filter-group input:focus, .filter-group select:focus { border-color: #2563eb; }
        .btn-search { background: #2563eb; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; align-self: flex-end; height: 44px; transition: background 0.2s; }
        .btn-search:hover { background: #1d4ed8; }

        /* Grid */
        .provider-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .provider-card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .provider-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        
        .card-header { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
        .provider-avatar { width: 50px; height: 50px; background: #eff6ff; color: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 700; flex-shrink: 0; }
        .provider-details { flex: 1; }
        .provider-name { font-size: 1.1rem; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 0.25rem; }
        .verified-icon { color: #10b981; width: 1.1rem; height: 1.1rem; }
        .provider-location { font-size: 0.85rem; color: #6b7280; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.25rem; }
        
        .provider-rating { display: flex; align-items: center; gap: 0.25rem; margin-bottom: 1rem; }
        .stars { color: #f59e0b; font-size: 1rem; }
        .rating-text { font-size: 0.85rem; color: #4b5563; font-weight: 500; }
        
        .provider-desc { font-size: 0.9rem; color: #4b5563; line-height: 1.5; margin-bottom: 1.5rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        
        .card-footer { border-top: 1px solid #f3f4f6; padding-top: 1rem; display: flex; justify-content: space-between; align-items: center; }
        .provider-rate { font-weight: 700; color: #111827; font-size: 0.95rem; }
        .btn-view { text-decoration: none; background: #eff6ff; color: #1d4ed8; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; }
        .btn-view:hover { background: #dbeafe; }

        .empty-state { text-align: center; padding: 4rem 2rem; background: white; border-radius: 12px; border: 1px solid #e5e7eb; color: #6b7280; grid-column: 1 / -1; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
            .filter-bar { flex-direction: column; }
            .btn-search { width: 100%; align-self: auto; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../BONGABON.png" alt="Logo" class="logo">
            <span class="brand-name">PESO BONGABON EMPLOYER</span>
        </div>
         <nav class="nav-menu">
            <a href="employer_dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span> Dashboard
            </a>
            <a href="browse_services.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg></span> Browse Services
            </a>
            <a href="my_service_bookings.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></span> My Bookings
            </a>
        </nav>
        <div class="user-profile" onclick="toggleProfileDropdown()">
            <div class="profile-dropdown" id="profileDropdown">
                <a href="../LOGIN%20SIGNUP/logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg> 
                    Logout
                </a>
            </div>
            <div class="avatar">
                <?php if(!empty($user_data['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                <?php else: ?>
                    <?php echo isset($user_data['company_name']) ? strtoupper(substr($user_data['company_name'], 0, 1)) : 'E'; ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_data['company_name'] ?? 'Employer'); ?></div>
                <div class="user-role">Employer</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; margin-left: auto; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Find a Service Provider</h1>
            <p class="page-desc">Browse through PESO Bongabon's directory of verified informal workers and freelancers.</p>
        </div>

        <form method="GET" action="browse_services.php" class="filter-bar">
            <div class="filter-group" style="flex: 2;">
                <label for="search">Search Keywords</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g. Electrician, Plumbing, Juan...">
            </div>
            <div class="filter-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search">Search</button>
        </form>

        <div class="provider-grid">
            <?php if ($services->num_rows > 0): ?>
                <?php while($service = $services->fetch_assoc()): ?>
                    <div class="provider-card">
                        <div class="card-header">
                            <div class="provider-avatar"><?php echo strtoupper(substr($service['first_name'], 0, 1)); ?></div>
                            <div class="provider-details">
                                <div class="provider-name">
                                    <?php echo htmlspecialchars($service['service_name']); ?>
                                    <svg class="verified-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                                </div>
                                <div class="provider-location">
                                    Offered by: <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="provider-rating">
                            <span class="stars">
                                <?php 
                                    $rating = $service['avg_rating'] ? round($service['avg_rating'], 1) : 0;
                                    for($i=1; $i<=5; $i++) { echo $i <= round($rating) ? '★' : '<span style="color:#e5e7eb">★</span>'; }
                                ?>
                            </span>
                            <span class="rating-text"><?php echo $rating; ?> (<?php echo $service['review_count']; ?> reviews)</span>
                        </div>

                        <div class="provider-desc">
                            <?php echo htmlspecialchars($service['description']); ?>
                        </div>

                        <div class="card-footer">
                            <div class="provider-rate">₱<?php echo number_format((float) filter_var($service['base_rate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 2); ?></div>
                            <a href="../view_service.php?id=<?php echo $service['service_id']; ?>" class="btn-view">View Service</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 3rem; height: 3rem; margin: 0 auto 1rem; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    <h3>No Services Found</h3>
                    <p style="margin-top: 0.5rem;">We couldn't find any services matching your search or filter criteria. Try adjusting your keywords.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        function toggleProfileDropdown() {
            document.getElementById('profileDropdown').classList.toggle('active');
        }
        document.addEventListener('click', function(event) {
            const profile = document.querySelector('.user-profile');
            if (profile && !profile.contains(event.target)) {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) dropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>