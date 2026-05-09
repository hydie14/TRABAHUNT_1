<?php
session_start();
include '../DATABASE/db_connect.php';
// Include CSRF helper if it exists
include '../DATABASE/send_email.php';
if (file_exists('../OTP VERIFY LABORATORY/send_otp.php')) {
    include_once '../OTP VERIFY LABORATORY/send_otp.php';
}
if (file_exists('../DATABASE/csrf.php')) {
    include '../DATABASE/csrf.php';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !function_exists('verify_csrf_token') || !verify_csrf_token($_POST['csrf_token'])) {
        die("<script>alert('Invalid request. Please try again.'); window.history.back();</script>");
    }

    // --- START: File Upload Debugging ---
    // Check if files were expected but $_FILES is empty, indicating a php.ini limit issue (e.g., post_max_size)
    if (empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        die("<script>alert('File upload failed. The uploaded file(s) might be too large. Please check your php.ini settings (post_max_size, upload_max_filesize).'); window.history.back();</script>");
    }
    // --- END: File Upload Debugging ---

    // 1. Capture Basic Information
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $barangay = $_POST['barangay'];
    $street_address = trim($_POST['street_address']);
    $phone_number = trim($_POST['phone_number']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate Age (Must be 18+)
    $bday = new DateTime($birthdate);
    $today = new DateTime('today');
    if ($bday->diff($today)->y < 18) {
        die("<script>alert('You must be at least 18 years old to register.'); window.history.back();</script>");
    }

    // Validate Password
    if ($password !== $confirm_password) {
        die("<script>alert('Passwords do not match!'); window.history.back();</script>");
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        die("<script>alert('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.'); window.history.back();</script>");
    }

    // 2. Handle File Uploads (Gatekeeper Requirements)
    $upload_dir = '../uploads/service_providers/';
    if (!is_dir($upload_dir)) {
        // Attempt to create directory, if it fails, log and alert
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            die("<script>alert('Server error: Could not create upload directory. Please contact support.'); window.history.back();</script>");
        }
    }

    function uploadFile($file_input_name, $upload_dir, $is_required = false) {
        if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] == UPLOAD_ERR_NO_FILE) {
            if ($is_required) {
                die("<script>alert('Required file missing: " . htmlspecialchars($file_input_name) . ". Please upload the document.'); window.history.back();</script>");
            }
            return null; // No file uploaded for an optional field
        }

        if ($_FILES[$file_input_name]['error'] != UPLOAD_ERR_OK) {
            $error_code = $_FILES[$file_input_name]['error'];
            $error_message = "An error occurred during file upload for " . htmlspecialchars($file_input_name) . ". Error code: {$error_code}. ";
            switch ($error_code) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message .= "The file is too large.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message .= "The file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message .= "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message .= "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message .= "A PHP extension stopped the file upload.";
                    break;
            }
            die("<script>alert('" . htmlspecialchars($error_message) . "'); window.history.back();</script>");
        }
        
        $file_name = $_FILES[$file_input_name]['name'];
        $file_size = $_FILES[$file_input_name]['size'];
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        
        // Validate file size (e.g., 5MB maximum)
        if ($file_size > 5242880) { // 5MB
            die("<script>alert('File is too large: " . htmlspecialchars($file_name) . ". Maximum size is 5MB.'); window.history.back();</script>");
        }

        // Validate file type
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($file_extension, $allowed_extensions)) {
            die("<script>alert('Invalid file type for: " . htmlspecialchars($file_name) . ". Only JPG, JPEG, PNG, and PDF are allowed.'); window.history.back();</script>");
        }

        $new_file_name = uniqid('', true) . '.' . $file_extension;
        $target_file = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($file_tmp_name, $target_file)) {
            return $target_file;
        } else {
            error_log("Failed to move uploaded file: " . $file_tmp_name . " to " . $target_file);
            die("<script>alert('Failed to save uploaded file. Please check server permissions and try again.'); window.history.back();</script>");
        }
    }

    // Required Documents
    $valid_id_path = uploadFile('valid_id', $upload_dir, true); // Mark as required
    $brgy_residency_path = uploadFile('brgy_residency', $upload_dir, true); // Mark as required

    // Optional Documents
    $tesda_cert_path = uploadFile('tesda_cert', $upload_dir);
    $portfolio_path = uploadFile('portfolio', $upload_dir);

    // 3. Database Insertion (Transactions for safety)
    $conn->begin_transaction();

    try {
        // Prevent Duplicate Entry Error: Clean up any previous UNVERIFIED attempts using this email/mobile
        // This logic is adapted from the employer registration flow.
        $check_stmt = $conn->prepare("SELECT uc.user_id, u.is_email_verified FROM user_contacts uc LEFT JOIN users u ON uc.user_id = u.user_id WHERE uc.contact_value = ? OR uc.contact_value = ?");
        $check_stmt->bind_param("ss", $email, $phone_number);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        $unverified_ids_to_delete = [];

        while ($row = $check_res->fetch_assoc()) {
            if ($row['is_email_verified'] == 1) {
                // If an entry exists and is verified, block registration.
                throw new Exception("The email address or phone number is already registered and verified.");
            } else {
                // If user is missing or not verified, mark for deletion.
                if (!is_null($row['user_id'])) {
                    $unverified_ids_to_delete[] = $row['user_id'];
                }
            }
        }
        $check_stmt->close();

        if (!empty($unverified_ids_to_delete)) {
            foreach (array_unique($unverified_ids_to_delete) as $uid) {
                // Deleting from users will cascade to other tables (service_providers, user_contacts, otps)
                // due to ON DELETE CASCADE constraint.
                $del_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $del_stmt->bind_param("i", $uid);
                $del_stmt->execute();
                $del_stmt->close();
            }
        }

        // Insert into Users Table
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'ServiceProvider';
        
        $stmt_user = $conn->prepare("INSERT INTO users (password_hash, role, is_email_verified) VALUES (?, ?, 0)");
        $stmt_user->bind_param("ss", $password_hash, $role);
        $stmt_user->execute();
        $provider_id = $conn->insert_id;
        $stmt_user->close();

        // Insert into User Contacts (Email & Phone)
        $stmt_contact = $conn->prepare("INSERT INTO user_contacts (user_id, contact_type, contact_value) VALUES (?, 'Email', ?), (?, 'Mobile', ?)");
        $stmt_contact->bind_param("isis", $provider_id, $email, $provider_id, $phone_number);
        $stmt_contact->execute();
        $stmt_contact->close();

        // Insert into Service Providers Table (Status defaults to 'Pending')
        $admin_status = 'Pending';
        $stmt_sp = $conn->prepare("INSERT INTO service_providers 
            (provider_id, first_name, middle_name, last_name, birthdate, gender, barangay, street_address, valid_id_path, brgy_residency_path, tesda_cert_path, portfolio_path, admin_verification_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_sp->bind_param("issssssssssss", 
            $provider_id, 
            $first_name, 
            $middle_name, 
            $last_name, 
            $birthdate, 
            $gender, 
            $barangay, 
            $street_address, 
            $valid_id_path, 
            $brgy_residency_path, 
            $tesda_cert_path, 
            $portfolio_path,
            $admin_status
        );
        
        $stmt_sp->execute();
        $stmt_sp->close();

        // Commit all inserts
        $conn->commit();

        // Use the standardized OTP sending function, like in the employer flow
        if (function_exists('send_otp')) {
            $otp_error = '';
            if (send_otp($email, $provider_id, $conn, $otp_error)) {
                $_SESSION['otp_user_id'] = $provider_id;
                $_SESSION['otp_user_role'] = $role;
                echo "<script>
                    alert('Registration Successful! A verification code has been sent to your email. Please use it to verify your account. Your account will then be pending for PESO staff review.');
                    window.location.href = '../OTP VERIFY LABORATORY/otp_verification.php';
                </script>";
                exit();
            } else {
                throw new Exception("Failed to send verification email. " . ($otp_error ? "Error: " . $otp_error : "Please try again."));
            }
        } else {
            throw new Exception("OTP sending module is not available. Registration cannot be completed.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        // Delete uploaded files if DB insert failed
        if (isset($valid_id_path) && $valid_id_path) unlink($valid_id_path);
        if (isset($brgy_residency_path) && $brgy_residency_path) unlink($brgy_residency_path);
        if (isset($tesda_cert_path) && $tesda_cert_path) unlink($tesda_cert_path);
        if (isset($portfolio_path) && $portfolio_path) unlink($portfolio_path);
        
        error_log("Service Provider Reg Error: " . $e->getMessage());
        $error_message = "An error occurred during registration. Please try again later.";
        if (strpos($e->getMessage(), "already registered and verified") !== false) {
            $error_message = "The email address or phone number is already registered to a verified account.";
        }
        die("<script>alert('" . addslashes($error_message) . "'); window.history.back();</script>");
    }
}

?>