<?php
include '../DATABASE/db_connect.php';
require '../DATABASE/config.php';

$admin_email = env('ADMIN_EMAIL');
$admin_password = env('ADMIN_PASSWORD');

if (!$admin_email || !$admin_password) {
    die("Error: ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env file");
}

$admin_role = 'Admin';

// Hash the password
$password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

// Check if the admin user already exists
$stmt = $conn->prepare("SELECT u.user_id FROM users u JOIN user_contacts uc ON u.user_id = uc.user_id WHERE uc.contact_value = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // User exists, update the password
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
    
    $update_stmt = $conn->prepare("UPDATE users SET role = ?, password_hash = ?, is_email_verified = 1 WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $admin_role, $password_hash, $user_id);
    if ($update_stmt->execute()) {
        echo "Admin password updated successfully.";
    } else {
        echo "Error updating admin password: " . $update_stmt->error;
    }
    $update_stmt->close();
} else {
    // User does not exist, create a new admin user
    $conn->begin_transaction();
    
    try {
        // Clean up any orphaned contacts first to prevent errors
        $cleanup_stmt = $conn->prepare("DELETE FROM user_contacts WHERE contact_value = ?");
        $cleanup_stmt->bind_param("s", $admin_email);
        $cleanup_stmt->execute();
        $cleanup_stmt->close();

        // Insert into users table
        $insert_user_stmt = $conn->prepare("INSERT INTO users (role, password_hash, is_email_verified) VALUES (?, ?, 1)");
        $insert_user_stmt->bind_param("ss", $admin_role, $password_hash);
        $insert_user_stmt->execute();
        $user_id = $insert_user_stmt->insert_id;
        $insert_user_stmt->close();

        // Insert into user_contacts table
        $insert_contact_stmt = $conn->prepare("INSERT INTO user_contacts (user_id, contact_type, contact_value, is_primary) VALUES (?, 'Email', ?, 1)");
        $insert_contact_stmt->bind_param("is", $user_id, $admin_email);
        $insert_contact_stmt->execute();
        $insert_contact_stmt->close();
        
        $conn->commit();
        echo "Admin user created successfully.";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo "Error creating admin user: " . $exception->getMessage();
    }
}

$stmt->close();
$conn->close();
?>