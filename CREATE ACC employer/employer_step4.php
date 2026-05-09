<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

if (!isset($_SESSION['employer_step3'])) {
    header("Location: employer_step3.php");
    exit();
}

$error = '';
$companyLocation = $_SESSION['employer_step1']['companyLocation'] ?? 'Local';

function handle_upload($file_key, $user_id, &$error_message) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] == UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$file_key]['error'] != UPLOAD_ERR_OK) {
        $error_message = "Error uploading file. Please try again.";
        return null;
    }

    if ($_FILES[$file_key]['size'] > 5 * 1024 * 1024) {
        $error_message = "File is too large. Maximum size is 5MB.";
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
    finfo_close($finfo);
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime_type, $allowed_types)) {
        $error_message = "Invalid file type. Allowed types: PDF, JPG, PNG.";
        return null;
    }

    $target_dir = "../uploads/employer_documents/" . $user_id . "/";
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $error_message = "Failed to create directory for uploads.";
            return null;
        }
    }
    
    $original_filename = basename($_FILES[$file_key]["name"]);
    $safe_filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $original_filename);
    $file_extension = pathinfo($safe_filename, PATHINFO_EXTENSION);
    $unique_filename = uniqid($file_key . '_', true) . '.' . $file_extension;
    $target_file = $target_dir . $unique_filename;

    if (move_uploaded_file($_FILES[$file_key]["tmp_name"], $target_file)) {
        return $target_file;
    } else {
        $error_message = "Failed to save uploaded file.";
        return null;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $step1 = $_SESSION['employer_step1'];
        $step3 = $_SESSION['employer_step3'];
        $step4 = $_POST;

        // Validation for TIN is now handled in Step 1, but we can double check existence if needed.
        // Proceeding with registration
        if (true) {
            $conn->begin_transaction();

            try {
                // Prevent Duplicate Entry Error: Clean up any previous UNVERIFIED attempts using this email/mobile
                $email = $step1['emailAddress'];
                $mobile = $step1['mobileNumber'];

                $check_stmt = $conn->prepare("SELECT contact_id, user_id FROM user_contacts WHERE contact_value IN (?, ?)");
                $check_stmt->bind_param("ss", $email, $mobile);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                $unverified_ids = [];
                $orphaned_contacts = [];
                
                while ($row = $check_res->fetch_assoc()) {
                    $uid = $row['user_id'];
                    $cid = $row['contact_id'];
                    
                    $u_stmt = $conn->prepare("SELECT is_email_verified FROM users WHERE user_id = ?");
                    $u_stmt->bind_param("i", $uid);
                    $u_stmt->execute();
                    $u_res = $u_stmt->get_result();
                    if ($u_row = $u_res->fetch_assoc()) {
                        if ($u_row['is_email_verified'] == 1) {
                            throw new Exception("The email address or mobile number is already registered and verified.");
                        } else {
                            $unverified_ids[] = $uid;
                        }
                    } else {
                        // The contact exists but the user is missing (orphaned/ghost record)
                        $orphaned_contacts[] = $cid;
                    }
                    $u_stmt->close();
                }
                $check_stmt->close();

                if (!empty($unverified_ids)) {
                    foreach (array_unique($unverified_ids) as $uid) {
                        $del_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                        $del_stmt->bind_param("i", $uid);
                        $del_stmt->execute();
                        $del_stmt->close();
                    }
                }

                if (!empty($orphaned_contacts)) {
                    foreach ($orphaned_contacts as $cid) {
                        $del_stmt = $conn->prepare("DELETE FROM user_contacts WHERE contact_id = ?");
                        $del_stmt->bind_param("i", $cid);
                        $del_stmt->execute();
                        $del_stmt->close();
                    }
                }

                $password_hash = password_hash($step3['password'], PASSWORD_DEFAULT);
                
                $admin_email = 'admin@peso.com';
                if (strtolower($step1['emailAddress']) === $admin_email) {
                    $role = 'Admin';
                    $is_verified = 1;
                } else {
                    $role = 'Employer';
                    $is_verified = 0;
                }
            
                $stmt = $conn->prepare("INSERT INTO users (password_hash, role, is_email_verified) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $password_hash, $role, $is_verified);
                $stmt->execute();
                $user_id = $stmt->insert_id;
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO user_contacts (user_id, contact_type, contact_value, is_primary) VALUES (?, 'Email', ?, 1)");
                $stmt->bind_param("is", $user_id, $step1['emailAddress']);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO user_contacts (user_id, contact_type, contact_value, is_primary) VALUES (?, 'Mobile', ?, 0)");
                $stmt->bind_param("is", $user_id, $step1['mobileNumber']);
                $stmt->execute();
                $stmt->close();

                if ($step1['companyLocation'] == 'Local') {
                    $barangay = $step1['barangay'];
                    $city_municipality = 'Bongabon';
                    $province = 'Nueva Ecija';
                    $street_address = $step1['localStreet'];
                } else {
                    $barangay = '';
                    $city_municipality = $step1['city'];
                    $province = $step1['country'];
                    $street_address = $step1['internationalStreet'];
                }
                
                $stmt = $conn->prepare("INSERT INTO locations (barangay, city_municipality, province) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $barangay, $city_municipality, $province);
                $stmt->execute();
                $location_id = $stmt->insert_id;
                $stmt->close();

                $stmt = $conn->prepare("SELECT business_line_id FROM business_lines WHERE business_name = ? LIMIT 1");
                $stmt->bind_param("s", $step1['businessLine']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $business_line_id = $result->fetch_assoc()['business_line_id'];
                } else {
                    $stmt_insert = $conn->prepare("INSERT INTO business_lines (business_name) VALUES (?)");
                    $stmt_insert->bind_param("s", $step1['businessLine']);
                    $stmt_insert->execute();
                    $business_line_id = $stmt_insert->insert_id;
                    $stmt_insert->close();
                }
                $stmt->close();
                
                if($role == 'Employer') {
                    // Retrieve values from Step 1 Session
                    $tin_number = $step1['tinNumber'] ?? '';
                    $trade_name = $step1['tradeName'] ?? '';
                    $acronym = $step1['acronym'] ?? '';
                    $employer_subtype = $step1['employerSubtype'] ?? '';
                    $office_type = $step1['officeType'] ?? '';
                    $owner_name = $step1['ownerName'] ?? '';
                    $telephone_number = $step1['telephoneNumber'] ?? '';
                    $fax_number = $step1['faxNumber'] ?? '';
                    $email_address = $step1['emailAddress'] ?? '';
                    $mobile_number = $step1['mobileNumber'] ?? '';
                    
                    $company_description = $step4['companyDescription'];
                    $admin_status = 'Pending';
                    $stmt = $conn->prepare("INSERT INTO employers (employer_id, company_name, trade_name, acronym, employer_type, employer_subtype, office_type, total_work_force, business_line_id, tin_number, street_address, location_id, owner_name, contact_person_name, contact_person_position, telephone_number, mobile_number, fax_number, email_address, company_description, admin_verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssssississsssssss", $user_id, $step1['companyName'], $trade_name, $acronym, $step1['employerType'], $employer_subtype, $office_type, $step1['totalWorkForce'], $business_line_id, $tin_number, $street_address, $location_id, $owner_name, $step1['contactPerson'], $step1['contactPosition'], $telephone_number, $mobile_number, $fax_number, $email_address, $company_description, $admin_status);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($role == 'Employer') {
                    $upload_error = null;
                    
                    if ($companyLocation === 'Local') {
                        // Local: 1 to 3 Documents
                        
                        // Document 1 (Required)
                        $doc_type1 = $step4['localDocType1'] ?? null;
                        $doc_path1 = handle_upload('localDocFile1', $user_id, $upload_error);
                        if ($upload_error) throw new Exception($upload_error);
                        
                        if ($doc_path1 && $doc_type1) {
                            $stmt = $conn->prepare("INSERT INTO employer_documents (employer_id, document_type, file_path) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $user_id, $doc_type1, $doc_path1);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            throw new Exception("Please upload at least one required business document (Document 1).");
                        }

                        // Document 2 (Optional)
                        $upload_error = null;
                        if (isset($_FILES['localDocFile2']) && $_FILES['localDocFile2']['error'] != UPLOAD_ERR_NO_FILE) {
                            $doc_type2 = $step4['localDocType2'] ?? 'Other';
                            $doc_path2 = handle_upload('localDocFile2', $user_id, $upload_error);
                            if ($upload_error) throw new Exception("Document 2 Error: " . $upload_error);
                            if ($doc_path2) {
                                $stmt = $conn->prepare("INSERT INTO employer_documents (employer_id, document_type, file_path) VALUES (?, ?, ?)");
                                $stmt->bind_param("iss", $user_id, $doc_type2, $doc_path2);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }

                        // Document 3 (Optional)
                        $upload_error = null;
                        if (isset($_FILES['localDocFile3']) && $_FILES['localDocFile3']['error'] != UPLOAD_ERR_NO_FILE) {
                            $doc_type3 = $step4['localDocType3'] ?? 'Other';
                            $doc_path3 = handle_upload('localDocFile3', $user_id, $upload_error);
                            if ($upload_error) throw new Exception("Document 3 Error: " . $upload_error);
                            if ($doc_path3) {
                                $stmt = $conn->prepare("INSERT INTO employer_documents (employer_id, document_type, file_path) VALUES (?, ?, ?)");
                                $stmt->bind_param("iss", $user_id, $doc_type3, $doc_path3);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    } else {
                        // International: DMW, SRA, Job Orders (Optional)
                        $dmw_path = handle_upload('dmwLicense', $user_id, $upload_error);
                        if ($upload_error) throw new Exception($upload_error);
                        if ($dmw_path) {
                            $doc_type = 'DMW / POEA License';
                            $stmt = $conn->prepare("INSERT INTO employer_documents (employer_id, document_type, file_path) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $user_id, $doc_type, $dmw_path);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            throw new Exception("DMW / POEA License is required.");
                        }

                        $sra_path = handle_upload('sraPermit', $user_id, $upload_error);
                        if ($upload_error) throw new Exception($upload_error);
                        if ($sra_path) {
                            $doc_type = 'Special Recruitment Authority (SRA)';
                            $stmt = $conn->prepare("INSERT INTO employer_documents (employer_id, document_type, file_path) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $user_id, $doc_type, $sra_path);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            throw new Exception("Special Recruitment Authority (SRA) is required.");
                        }

                        $jo_path = handle_upload('jobOrders', $user_id, $upload_error);
                        if ($upload_error) throw new Exception($upload_error);
                        if ($jo_path) {
                            $doc_type = 'Approved Job Orders';
                            $stmt = $conn->prepare("INSERT INTO employer_documents (employer_id, document_type, file_path) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $user_id, $doc_type, $jo_path);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                $conn->commit();
                
                if ($role === 'Admin') {
                    unset($_SESSION['employer_step1'], $_SESSION['employer_step3']);
                    header("Location: ../LOGIN%20SIGNUP/new_login.php");
                    exit();
                }

                include_once '../OTP VERIFY LABORATORY/send_otp.php';
                $otp_error = '';
                if (send_otp($step1['emailAddress'], $user_id, $conn, $otp_error)) {
                    unset($_SESSION['employer_step1'], $_SESSION['employer_step3']);
                    
                    $_SESSION['otp_user_id'] = $user_id;
                    $_SESSION['otp_user_role'] = $role;
                    header("Location: ../OTP%20VERIFY%20LABORATORY/otp_verification.php");
                    exit();
                } else {
                    throw new Exception("Failed to send verification email. " . ($otp_error ? "Error: " . $otp_error : "Please try again."));
                }

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Employer registration failed: " . $e->getMessage());
                if (strpos($e->getMessage(), "Duplicate entry") !== false) {
                    preg_match("/Duplicate entry '(.*?)'/", $e->getMessage(), $matches);
                    $dup_val = $matches[1] ?? 'input';
                    $error = "Registration failed: The exact value '{$dup_val}' is already registered in our system.";
                } else {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Registration - Step 4</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); min-height: 100vh; padding: 1rem; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { text-align: center; color: white; margin-bottom: 1rem; }
        .logo { width: 80px; height: 80px; margin: 0 auto 0.5rem; display: block; }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.25rem; }
        .header p { opacity: 0.9; font-size: 0.875rem; }
        .progress-container { background: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .progress-steps { display: flex; justify-content: space-between; position: relative; }
        .progress-steps::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #e5e7eb; z-index: 0; }
        .progress-line { position: absolute; top: 20px; left: 0; height: 3px; background: #fbbf24; z-index: 1; transition: width 0.3s; width: 100%; }
        .step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; flex: 1; max-width: 33%; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-bottom: 0.5rem; transition: all 0.3s; }
        .step.active .step-circle { background: #1e40af; color: white; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2); }
        .step.completed .step-circle { background: #fbbf24; color: #1e40af; }
        .step-label { font-size: 0.7rem; color: #6b7280; font-weight: 500; text-align: center; }
        .step.active .step-label { color: #1e40af; font-weight: 600; }
        .form-card { background: white; padding: 1.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.25rem; color: #1f2937; margin-bottom: 0.25rem; }
        .form-subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-info { background: #dbeafe; border-left: 4px solid #1e40af; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-info p { color: #1e3a8a; font-size: 0.8rem; line-height: 1.4; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; font-size: 0.875rem; }
        .required { color: #ef4444; }
        .file-upload { border: 2px dashed #d1d5db; border-radius: 8px; padding: 1rem; text-align: center; cursor: pointer; transition: all 0.2s; background: #f9fafb; }
        .file-upload:hover { border-color: #1e40af; background: #dbeafe; }
        .file-upload input { display: none; }
        .file-upload-icon { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .file-upload-text { color: #6b7280; font-size: 0.8rem; }
        .file-upload-text strong { color: #1e40af; }
        .file-name { margin-top: 0.5rem; padding: 0.5rem; background: #dbeafe; border-radius: 6px; color: #1e40af; font-size: 0.875rem; display: none; }
        .helper-text { font-size: 0.75rem; color: #6b7280; margin-top: 0.375rem; }
        .form-group input[type="text"] { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; }
        .form-group input[type="text"]:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .form-group textarea { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; resize: vertical; font-family: inherit; }
        .form-group textarea:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .btn-container { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; }
        .btn { padding: 0.875rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 1rem; border: none; }
        .btn-back { background: #f3f4f6; color: #6b7280; }
        .btn-back:hover { background: #e5e7eb; }
        .btn-submit { background: #1e40af; color: white; flex: 1; }
        .btn-submit:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        @media (max-width: 768px) { .form-card { padding: 1rem; } .step-label { font-size: 0.6rem; } .container { max-width: 100%; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../BONGABON.png" alt="Bongabon Logo" class="logo">
            <h1>Employer Registration</h1>
            <p>PESO Bongabon - Register your company (Local & International)</p>
        </div>
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line" style="width: 100%;"></div>
                <div class="step completed">
                    <div class="step-circle">✓</div>
                    <div class="step-label">Establishment Details</div>
                </div>
                <div class="step completed">
                    <div class="step-circle">✓</div>
                    <div class="step-label">Credentials</div>
                </div>
                <div class="step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Documents</div>
                </div>
            </div>
        </div>
        <div class="form-card">
            <h2 class="form-title">Verification Documents</h2>
            <p class="form-subtitle">Upload required business documents</p>
            <?php if($error): ?>
                <p style="color: red;"><?php echo $error; ?></p>
            <?php endif; ?>
            <div class="alert-info">
                <p><strong>📋 Important:</strong> Your account will be pending approval until PESO Bongabon verifies your documents. Accepted formats: PDF, JPG, PNG (Max 5MB each).</p>
                <?php if ($companyLocation === 'Local'): ?>
                    <p style="margin-top: 0.5rem;">For <strong>Local Employers</strong>, please provide 1 to 3 valid business documents (e.g., Business Permit, BIR Certificate, SEC/DTI, DOLE License, Mayor's Permit, or Other).</p>
                <?php else: ?>
                    <p style="margin-top: 0.5rem;">For <strong>International Agencies</strong>, please provide your DMW/POEA License and Special Recruitment Authority (SRA).</p>
                <?php endif; ?>
            </div>
            <form id="step4Form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" enctype="multipart/form-data" onsubmit="document.querySelector('.btn-submit').disabled=true; document.querySelector('.btn-submit').innerText='Submitting...';">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="companyDescription">Company Profile / Description <span class="required">*</span></label>
                    <textarea id="companyDescription" name="companyDescription" rows="4" placeholder="Brief description of your company, mission, and vision..." required></textarea>
                </div>

                <?php if ($companyLocation === 'Local'): ?>
                <!-- Document 1 (Required) -->
                <div class="form-group">
                    <label>Select Document 1 to Upload <span class="required">*</span></label>
                    <select name="localDocType1" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem;" required>
                        <option value="" disabled selected>Select Document Type</option>
                        <option value="Business permit">Business permit</option>
                        <option value="BIR certificate">BIR certificate</option>
                        <option value="SEC/DTI registration">SEC/DTI registration</option>
                        <option value="DOLE license">DOLE license</option>
                        <option value="Mayor's Permit">Mayor's Permit</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="file-upload" onclick="document.getElementById('localDocFile1').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload or drag and drop<br><strong>Document 1</strong></div>
                        <input type="file" id="localDocFile1" name="localDocFile1" accept=".pdf,.jpg,.jpeg,.png" required onchange="showFileName(this, 'localDocFileName1')">
                    </div>
                    <div class="file-name" id="localDocFileName1"></div>
                </div>

                <!-- Document 2 (Optional) -->
                <div class="form-group">
                    <label>Select Document 2 to Upload (Optional)</label>
                    <select name="localDocType2" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem;">
                        <option value="" disabled selected>Select Document Type</option>
                        <option value="Business permit">Business permit</option>
                        <option value="BIR certificate">BIR certificate</option>
                        <option value="SEC/DTI registration">SEC/DTI registration</option>
                        <option value="DOLE license">DOLE license</option>
                        <option value="Mayor's Permit">Mayor's Permit</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="file-upload" onclick="document.getElementById('localDocFile2').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload or drag and drop<br><strong>Document 2</strong></div>
                        <input type="file" id="localDocFile2" name="localDocFile2" accept=".pdf,.jpg,.jpeg,.png" onchange="showFileName(this, 'localDocFileName2')">
                    </div>
                    <div class="file-name" id="localDocFileName2"></div>
                </div>

                <!-- Document 3 (Optional) -->
                <div class="form-group">
                    <label>Select Document 3 to Upload (Optional)</label>
                    <select name="localDocType3" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem;">
                        <option value="" disabled selected>Select Document Type</option>
                        <option value="Business permit">Business permit</option>
                        <option value="BIR certificate">BIR certificate</option>
                        <option value="SEC/DTI registration">SEC/DTI registration</option>
                        <option value="DOLE license">DOLE license</option>
                        <option value="Mayor's Permit">Mayor's Permit</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="file-upload" onclick="document.getElementById('localDocFile3').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload or drag and drop<br><strong>Document 3</strong></div>
                        <input type="file" id="localDocFile3" name="localDocFile3" accept=".pdf,.jpg,.jpeg,.png" onchange="showFileName(this, 'localDocFileName3')">
                    </div>
                    <div class="file-name" id="localDocFileName3"></div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label>DMW / POEA License <span class="required">*</span></label>
                    <div class="file-upload" onclick="document.getElementById('dmwLicense').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload or drag and drop<br><strong>DMW / POEA License</strong></div>
                        <input type="file" id="dmwLicense" name="dmwLicense" accept=".pdf,.jpg,.jpeg,.png" required onchange="showFileName(this, 'dmwLicenseName')">
                    </div>
                    <div class="file-name" id="dmwLicenseName"></div>
                </div>
                <div class="form-group">
                    <label>Special Recruitment Authority (SRA) <span class="required">*</span></label>
                    <div class="file-upload" onclick="document.getElementById('sraPermit').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload or drag and drop<br><strong>SRA Permit</strong></div>
                        <input type="file" id="sraPermit" name="sraPermit" accept=".pdf,.jpg,.jpeg,.png" required onchange="showFileName(this, 'sraPermitName')">
                    </div>
                    <div class="file-name" id="sraPermitName"></div>
                </div>
                <div class="form-group">
                    <label>Approved Job Orders (Optional)</label>
                    <div class="file-upload" onclick="document.getElementById('jobOrders').click()">
                        <div class="file-upload-icon">📄</div>
                        <div class="file-upload-text">Click to upload or drag and drop<br><strong>Job Orders</strong></div>
                        <input type="file" id="jobOrders" name="jobOrders" accept=".pdf,.jpg,.jpeg,.png" onchange="showFileName(this, 'jobOrdersName')">
                    </div>
                    <div class="file-name" id="jobOrdersName"></div>
                </div>
                <?php endif; ?>

                <div class="btn-container">
                    <button type="button" class="btn btn-back" onclick="window.location.href='employer_step3.php'">← Back</button>
                    <button type="submit" class="btn btn-submit">Submit for Approval</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function showFileName(input, displayId) {
            const display = document.getElementById(displayId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                if (fileSize > 5) {
                    alert('File size must be less than 5MB');
                    input.value = '';
                    display.style.display = 'none';
                    return;
                }
                display.textContent = `✓ ${file.name} (${fileSize} MB)`;
                display.style.display = 'block';
            }
        }
    </script>
</body>
</html>
