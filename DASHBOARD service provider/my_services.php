<?php
session_start();
include '../DATABASE/db_connect.php';

// Check if user is logged in and is a Service Provider
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ServiceProvider') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch service provider details
$stmt = $conn->prepare("SELECT * FROM service_providers WHERE provider_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    session_destroy();
    header("Location: ../LOGIN%20SIGNUP/new_login.php?error=profile_not_found");
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    // Check if provider is verified before allowing to add
    if ($provider['admin_verification_status'] === 'Verified') {
        $service_name = ucwords(trim($_POST['service_name']));
        $category = ucwords(trim($_POST['category']));
        $description = ucfirst(trim($_POST['description']));
        $base_rate = trim($_POST['base_rate']);

        if (!empty($service_name) && !empty($category) && !empty($description) && !empty($base_rate)) {
            $stmt_insert = $conn->prepare("INSERT INTO provider_services (provider_id, service_name, category, description, base_rate, status) VALUES (?, ?, ?, ?, ?, 'Pending_Approval')");
            $stmt_insert->bind_param("issss", $user_id, $service_name, $category, $description, $base_rate);

            if ($stmt_insert->execute()) {
                $message = "<div class='alert alert-success'>Service added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error adding service. Please try again.</div>";
            }
            $stmt_insert->close();
        } else {
            $message = "<div class='alert alert-danger'>All fields are required.</div>";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    if ($provider['admin_verification_status'] === 'Verified') {
        $service_id = (int)$_POST['service_id'];
        $service_name = ucwords(trim($_POST['service_name']));
        $category = ucwords(trim($_POST['category']));
        $description = ucfirst(trim($_POST['description']));
        $base_rate = trim($_POST['base_rate']);

        if (!empty($service_name) && !empty($category) && !empty($description) && !empty($base_rate)) {
            $stmt_update = $conn->prepare("UPDATE provider_services SET service_name = ?, category = ?, description = ?, base_rate = ?, status = 'Pending_Approval' WHERE service_id = ? AND provider_id = ?");
            $stmt_update->bind_param("ssssii", $service_name, $category, $description, $base_rate, $service_id, $user_id);

            if ($stmt_update->execute()) {
                $message = "<div class='alert alert-success'>Service updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error updating service. Please try again.</div>";
            }
            $stmt_update->close();
        } else {
            $message = "<div class='alert alert-danger'>All fields are required.</div>";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    if ($provider['admin_verification_status'] === 'Verified') {
        $service_id = (int)$_POST['service_id'];
        
        $stmt_delete = $conn->prepare("DELETE FROM provider_services WHERE service_id = ? AND provider_id = ?");
        $stmt_delete->bind_param("ii", $service_id, $user_id);
        
        if ($stmt_delete->execute()) {
            $message = "<div class='alert alert-success'>Service deleted successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting service. Please try again.</div>";
        }
        $stmt_delete->close();
    }
}

$stmt_services = $conn->prepare("SELECT * FROM provider_services WHERE provider_id = ? ORDER BY created_at DESC");
$stmt_services->bind_param("i", $user_id);
$stmt_services->execute();
$services = $stmt_services->get_result();
$stmt_services->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Services - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; display: flex; min-height: 100vh; }
        
        /* Sidebar */
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
        .sidebar-badge { background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; margin-left: auto; }
        .profile-dropdown { position: absolute; bottom: 100%; left: 0; width: 100%; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.05); display: none; z-index: 100; margin-bottom: 0.5rem; }
        .profile-dropdown.active { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; text-decoration: none; color: #ef4444; font-weight: 500; transition: background 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; border-radius: 8px; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 2rem; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 2rem; font-weight: 800; color: #111827; }
        
        .section { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 2rem; }

        .btn-add { background: #2563eb; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; transition: background 0.2s; }
        .btn-add:hover { background: #1d4ed8; }

        /* Alert Styles */
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; }
        .alert-success { background-color: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border-color: #fecaca; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; margin: auto; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); animation: fadeIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h2 { font-size: 1.5rem; font-weight: 700; color: #111827; }
        .close-btn { color: #9ca3af; font-size: 2rem; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: #111827; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; }
        .form-group input[type="text"], .form-group textarea, .form-group input[list] { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-group input[type="text"]:focus, .form-group textarea:focus, .form-group input[list]:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2); outline: none; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
        .form-actions button { padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.9rem; border: none; }
        .btn-cancel { background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db; }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-submit { background: #2563eb; color: white; }
        .btn-submit:hover { background: #1d4ed8; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        /* Service Card Styles */
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .service-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; }
        .service-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; }
        .service-title { font-size: 1.25rem; font-weight: 700; color: #111827; }
        .service-status { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f3f4f6; color: #4b5563; }
        .status-pending_approval { background: #fef3c7; color: #d97706; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .service-category { font-size: 0.875rem; font-weight: 500; color: #2563eb; margin-bottom: 1rem; }
        .service-description { color: #4b5563; font-size: 0.9rem; line-height: 1.6; flex-grow: 1; margin-bottom: 1.5rem; }
        .service-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-top: auto; }
        .service-rate { font-weight: 700; color: #111827; font-size: 1.1rem; }
        .service-actions a { text-decoration: none; font-size: 0.875rem; font-weight: 600; padding: 0.4rem 0.8rem; border-radius: 6px; transition: background 0.2s; margin-left: 0.5rem; }
        .btn-edit { color: #1d4ed8; background: #eff6ff; }
        .btn-edit:hover { background: #dbeafe; }
        .btn-delete { color: #b91c1c; background: #fee2e2; }
        .btn-delete:hover { background: #fecaca; }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../BONGABON.png" alt="Logo" class="logo">
            <span class="brand-name">PESO BONGABON</span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></span> Dashboard
            </a>
            <a href="my_services.php" class="nav-item active">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg></span> My Services
            </a>
            <a href="bookings.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></span> Bookings
            </a>
            <a href="reviews.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg></span> Reviews
            </a>
            <a href="settings.php" class="nav-item">
                <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span> Profile Settings
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
                <?php echo strtoupper(substr($provider['first_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($provider['first_name'] . ' ' . $provider['last_name']); ?></div>
                <div class="user-role">Service Provider</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; margin-left: auto; color: #9ca3af;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">My Services</h1>
            <?php if ($provider['admin_verification_status'] === 'Verified'): ?>
                <button class="btn-add">+ Add New Service</button>
            <?php endif; ?>
        </div>
        
        <?php echo $message; ?>
        
        <?php if ($provider['admin_verification_status'] === 'Verified'): ?>
            <div class="section">
                <?php if ($services->num_rows > 0): ?>
                    <div class="services-grid">
                        <?php while($service = $services->fetch_assoc()): ?>
                            <div class="service-card">
                                <div class="service-card-header">
                                    <h3 class="service-title"><?php echo htmlspecialchars($service['service_name']); ?></h3>
                                    <span class="service-status status-<?php echo strtolower($service['status']); ?>"><?php echo str_replace('_', ' ', htmlspecialchars($service['status'])); ?></span>
                                </div>
                                <p class="service-category"><?php echo htmlspecialchars($service['category']); ?></p>
                                <p class="service-description"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                                <div class="service-footer">
                                    <span class="service-rate"><?php echo htmlspecialchars($service['base_rate']); ?></span>
                                    <div class="service-actions">
                                        <a href="javascript:void(0);" class="btn-edit" 
                                           data-id="<?php echo $service['service_id']; ?>"
                                           data-name="<?php echo htmlspecialchars($service['service_name']); ?>"
                                           data-category="<?php echo htmlspecialchars($service['category']); ?>"
                                           data-desc="<?php echo htmlspecialchars($service['description']); ?>"
                                           data-rate="<?php echo htmlspecialchars($service['base_rate']); ?>"
                                           onclick="openEditModal(this)">Edit</a>
                                        <a href="javascript:void(0);" class="btn-delete" onclick="confirmDelete(<?php echo $service['service_id']; ?>)">Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6b7280; text-align: center; padding: 2rem 0;">You have not added any services yet. Click the "Add New Service" button to start offering your services.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="section" style="text-align: center; padding: 3rem 1.5rem; background: #fffbeb; border-color: #fde68a;">
                <h3 style="color: #b45309; margin-bottom: 0.5rem;">Account Under Review</h3>
                <p style="color: #b45309;">Your account is pending verification. This feature is currently disabled.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" action="my_services.php" style="display: none;">
        <input type="hidden" name="delete_service" value="1">
        <input type="hidden" name="service_id" id="delete_service_id">
    </form>

    <!-- Add Service Modal -->
    <div id="addServiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Service</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="my_services.php">
                <input type="hidden" name="add_service" value="1">
                <div class="form-group">
                    <label for="service_name">Service Name</label>
                    <input type="text" id="service_name" name="service_name" placeholder="e.g., Professional House Cleaning" required style="text-transform: capitalize;">
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" list="service-categories" placeholder="e.g., Home Services" required style="text-transform: capitalize;">
                    <datalist id="service-categories">
                        <option value="Accounting & Bookkeeping">
                        <option value="Aircon & Refrigeration Repair">
                        <option value="Automotive & Mechanic Services">
                        <option value="Barber & Salon Services">
                        <option value="Beauty & Makeup">
                        <option value="Carpentry & Woodwork">
                        <option value="Catering & Food Services">
                        <option value="Cleaning & Housekeeping">
                        <option value="Computer & IT Support">
                        <option value="Construction & Masonry">
                        <option value="Consulting & Business Services">
                        <option value="Creative, Design & Multimedia">
                        <option value="Delivery, Logistics & Moving">
                        <option value="Education, Tutoring & Training">
                        <option value="Electrical Services">
                        <option value="Event Planning & Management">
                        <option value="Farming & Agriculture Services">
                        <option value="Health, Wellness & Fitness">
                        <option value="Landscaping & Gardening">
                        <option value="Laundry & Dry Cleaning">
                        <option value="Legal Services">
                        <option value="Massage Therapy">
                        <option value="Nanny & Babysitting">
                        <option value="Pet Care & Grooming">
                        <option value="Photography & Videography">
                        <option value="Plumbing Services">
                        <option value="Security & Guard Services">
                        <option value="Tailoring & Alterations">
                        <option value="Translation & Writing">
                        <option value="Welding & Fabrication">
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4" placeholder="Describe the service you offer, what's included, etc." required></textarea>
                </div>
                <div class="form-group">
                    <label for="base_rate">Base Rate / Pricing</label>
                    <input type="text" id="base_rate" name="base_rate" placeholder="e.g., ₱500 per hour, ₱2,000 per project" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Add Service</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Service Modal -->
    <div id="editServiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Service</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="my_services.php">
                <input type="hidden" name="edit_service" value="1">
                <input type="hidden" name="service_id" id="edit_service_id">
                
                <div class="form-group">
                    <label for="edit_service_name">Service Name</label>
                    <input type="text" id="edit_service_name" name="service_name" required style="text-transform: capitalize;">
                </div>
                <div class="form-group">
                    <label for="edit_category">Category</label>
                    <input type="text" id="edit_category" name="category" list="service-categories" required style="text-transform: capitalize;">
                    <!-- Uses the same datalist defined in the Add Modal -->
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_base_rate">Base Rate / Pricing</label>
                    <input type="text" id="edit_base_rate" name="base_rate" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

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

        const modal = document.getElementById('addServiceModal');
        const editModal = document.getElementById('editServiceModal');
        const addBtn = document.querySelector('.btn-add');

        if (addBtn) {
            addBtn.onclick = function() {
                modal.style.display = 'flex';
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }
        
        function openEditModal(btn) {
            document.getElementById('edit_service_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_service_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_category').value = btn.getAttribute('data-category');
            document.getElementById('edit_description').value = btn.getAttribute('data-desc');
            document.getElementById('edit_base_rate').value = btn.getAttribute('data-rate');
            editModal.style.display = 'flex';
        }
        
        function closeEditModal() {
            editModal.style.display = 'none';
        }
        
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this service? This action cannot be undone.")) {
                document.getElementById('delete_service_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>