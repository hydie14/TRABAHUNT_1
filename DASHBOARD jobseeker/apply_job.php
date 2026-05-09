<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$seeker_id = $_SESSION['user_id'];
$error = '';
$success = '';
$job = null;
$existing_resume = null;

if (!isset($_GET['job_id']) || !filter_var($_GET['job_id'], FILTER_VALIDATE_INT)) {
    header("Location: browse_jobs.php");
    exit();
}

$job_id = (int)$_GET['job_id'];

// Fetch job details
$stmt = $conn->prepare("
    SELECT jp.*, e.company_name, e.company_description, e.employer_type, l.barangay, l.city_municipality,
           el.level_name AS education_level, c.course_name AS course_name, bl.business_name
    FROM job_postings jp
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN locations l ON jp.location_id = l.location_id
    LEFT JOIN education_levels el ON jp.education_id = el.education_id
    LEFT JOIN courses c ON jp.course_id = c.course_id
    LEFT JOIN business_lines bl ON e.business_line_id = bl.business_line_id
    WHERE jp.job_id = ? 
    AND jp.status = 'Active'
    AND (jp.posting_date IS NULL OR jp.posting_date <= CURDATE())
    AND (jp.valid_until IS NULL OR jp.valid_until >= CURDATE())
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $error = "Job not found or is no longer active.";
} else {
    $job = $result->fetch_assoc();
}
$stmt->close();

// Fetch specific disabilities if PWD is accepted
$pwd_types = [];
if ($job && !empty($job['accepts_pwd'])) {
    $pwd_stmt = $conn->prepare("SELECT disability_type, other_description FROM job_posting_disabilities WHERE job_id = ?");
    $pwd_stmt->bind_param("i", $job_id);
    $pwd_stmt->execute();
    $pwd_result = $pwd_stmt->get_result();
    while ($row = $pwd_result->fetch_assoc()) {
        $type = $row['disability_type'];
        if ($type === 'Others' && !empty($row['other_description'])) {
            $type .= ': ' . $row['other_description'];
        }
        $pwd_types[] = $type;
    }
    $pwd_stmt->close();
}

// Fetch job skills
$job_skills = [];
if ($job) {
    $skill_stmt = $conn->prepare("SELECT s.skill_name FROM job_skills js JOIN skills s ON js.skill_id = s.skill_id WHERE js.job_id = ?");
    $skill_stmt->bind_param("i", $job_id);
    $skill_stmt->execute();
    $skill_result = $skill_stmt->get_result();
    while ($row = $skill_result->fetch_assoc()) {
        $job_skills[] = $row['skill_name'];
    }
    $skill_stmt->close();
}

$already_applied = false;
if ($job) {
    // Check if already applied
    $check_stmt = $conn->prepare("SELECT * FROM referrals_applications WHERE job_id = ? AND seeker_id = ?");
    $check_stmt->bind_param("ii", $job_id, $seeker_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $already_applied = true;
        $error = "You have already applied for this job.";
    }
    $check_stmt->close();
}

$is_currently_hired = false;
if ($job && !$already_applied) {
    // Only block if they are hired for a Permanent or Contractual (Full-time) role
    $check_emp = $conn->prepare("
        SELECT jp.employment_type 
        FROM referrals_applications ra
        JOIN job_postings jp ON ra.job_id = jp.job_id
        WHERE ra.seeker_id = ? 
        AND ra.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment', 'Pending_Resignation')
    ");
    $check_emp->bind_param("i", $seeker_id);
    $check_emp->execute();
    $emp_result = $check_emp->get_result();
    
    $part_time_count = 0;
    while ($row = $emp_result->fetch_assoc()) {
        if (in_array($row['employment_type'], ['Permanent', 'Contractual'])) {
            $is_currently_hired = true;
        } else {
            $part_time_count++;
        }
    }
    $check_emp->close();

    if ($is_currently_hired) {
        $error = "You are currently employed full-time or have a pending resignation review. Please wait for PESO to verify your resignation before applying for new jobs.";
    } elseif ($part_time_count >= 2) {
        $is_currently_hired = true;
        $error = "You have reached the maximum limit of 2 active part-time/project-based jobs. Please report a resignation to apply for new ones.";
    }
}

// Check for existing resume in My Documents
$doc_stmt = $conn->prepare("SELECT file_path FROM jobseeker_documents WHERE seeker_id = ? AND doc_type = 'Resume'");
$doc_stmt->bind_param("i", $seeker_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();
if ($doc_result->num_rows > 0) {
    $existing_resume = $doc_result->fetch_assoc()['file_path'];
}

// Check if job is saved
$is_saved = false;
$save_check = $conn->prepare("SELECT saved_job_id FROM saved_jobs WHERE seeker_id = ? AND job_id = ?");
$save_check->bind_param("ii", $seeker_id, $job_id);
$save_check->execute();
if ($save_check->get_result()->num_rows > 0) $is_saved = true;
$save_check->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $job && !$already_applied && !$is_currently_hired) {
    $target_file = '';
    
    // Case 1: User uploaded a new file
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = "../UPLOADS/resumes/";
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                 $error = "Failed to create upload directory. Please contact support.";
            }
        }

        if (empty($error)) {
            $file_extension = strtolower(pathinfo($_FILES["resume"]["name"], PATHINFO_EXTENSION));
            
            $original_filename = basename($_FILES["resume"]["name"]);
            $safe_filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $original_filename);
            $new_filename = "resume_" . $seeker_id . "_" . time() . "_" . $safe_filename;
            $target_file = $upload_dir . $new_filename;
            
            $allowed_types = ['pdf', 'doc', 'docx'];
            if (!in_array($file_extension, $allowed_types)) {
                $error = "Sorry, only PDF, DOC, & DOCX files are allowed.";
            } elseif ($_FILES["resume"]["size"] > 5242880) { // 5MB limit
                $error = "Sorry, your file is too large. Maximum size is 5MB.";
            } else {
                if (move_uploaded_file($_FILES["resume"]["tmp_name"], $target_file)) {
                    // Also save to jobseeker_documents for future use
                    $doc_type = 'Resume';
                    $check = $conn->prepare("SELECT document_id FROM jobseeker_documents WHERE seeker_id = ? AND doc_type = ?");
                    $check->bind_param("is", $seeker_id, $doc_type);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $upd = $conn->prepare("UPDATE jobseeker_documents SET file_path = ?, uploaded_at = NOW() WHERE seeker_id = ? AND doc_type = ?");
                        $upd->bind_param("sis", $target_file, $seeker_id, $doc_type);
                        $upd->execute();
                    } else {
                        $ins = $conn->prepare("INSERT INTO jobseeker_documents (seeker_id, doc_type, file_path) VALUES (?, ?, ?)");
                        $ins->bind_param("iss", $seeker_id, $doc_type, $target_file);
                        $ins->execute();
                    }
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            }
        }
    } 
    // Case 2: User chose to use existing resume
    elseif (isset($_POST['use_existing']) && $_POST['use_existing'] == '1' && $existing_resume) {
        $target_file = $existing_resume;
    }
    // Case 3: No file and no existing resume selected
    else {
        $error = "Resume is required. Please upload one or use your existing resume.";
    }

    if (empty($error) && !empty($target_file)) {
        $initial_status = 'Pending_Docs'; // All applications require PESO Admin review to match

        $stmt = $conn->prepare("INSERT INTO referrals_applications (job_id, seeker_id, status, resume_file) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $job_id, $seeker_id, $initial_status, $target_file);
        if ($stmt->execute()) {
            $success = "Application submitted successfully!";
            $already_applied = true;
        } else {
            $error = "Error submitting application to the database.";
        }
        $stmt->close();
    } else {
        switch (isset($_FILES['resume']['error']) ? $_FILES['resume']['error'] : UPLOAD_ERR_NO_FILE) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = "File is too large.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = "Resume is required. Please select a file to upload.";
                break;
            default:
                $error = "An unknown error occurred during file upload.";
                break;
        }
    }
}

if (empty($job) && empty($error)) {
    $error = "Job not found or is no longer available.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?php echo $job ? htmlspecialchars($job['job_title']) : 'Job'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; padding: 2rem; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; margin: 0; }
        .container { max-width: 900px; width: 100%; background: #ffffff; padding: 2.5rem 3rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #eaeaea; }
        
        .job-header { margin-bottom: 2.5rem; border-bottom: 2px solid #f0f4f8; padding-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; position: relative; }
        .job-header-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .job-header h1 { color: #111827; font-size: 2.25rem; font-weight: 800; margin: 0; line-height: 1.2; letter-spacing: -0.02em; }
        .job-header .company { color: #2563eb; font-weight: 700; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; }
        .job-header .meta { display: flex; flex-wrap: wrap; gap: 1rem; color: #64748b; font-size: 0.95rem; align-items: center; margin-top: 1rem; }
        .job-header .meta span { display: flex; align-items: center; gap: 0.35rem; }
        
        .details-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; padding: 1.5rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; }
        .detail-item label { display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem; }
        .detail-item span { display: block; color: #0f172a; font-weight: 600; font-size: 1rem; }
        
        .tag { background-color: #dbeafe; color: #1e40af; padding: 0.35rem 0.85rem; border-radius: 9999px; font-weight: 600; font-size: 0.85rem; }
        
        .section-title { font-size: 1.35rem; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f0f4f8; padding-bottom: 0.5rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .job-description-content { color: #334155; line-height: 1.7; font-size: 1rem; margin-bottom: 2.5rem; }
        
        .skills-container { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .skill-badge { background-color: #f1f5f9; color: #475569; padding: 0.4rem 1rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; border: 1px solid #e2e8f0; transition: all 0.2s ease; }
        .skill-badge:hover { background-color: #e2e8f0; color: #0f172a; }

        .about-company { margin-bottom: 2.5rem; background: linear-gradient(to right bottom, #ffffff, #f8fafc); padding: 1.75rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .about-company p { color: #475569; line-height: 1.6; font-size: 0.95rem; margin: 0; }
        
        .application-section { background: #f8fafc; padding: 2rem; border-radius: 12px; border: 1px dashed #cbd5e1; margin-top: 3rem; }
        .requirements p { color: #475569; margin-bottom: 1.5rem; line-height: 1.6; font-size: 0.95rem; margin-top: 0; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #1e293b; }
        .form-group input[type="file"] { width: 100%; padding: 0.85rem; border: 2px dashed #cbd5e1; border-radius: 8px; background: #ffffff; box-sizing: border-box; color: #475569; cursor: pointer; transition: border-color 0.2s; }
        .form-group input[type="file"]:hover { border-color: #94a3b8; }
        .form-group input[type="file"]:focus { outline: none; border-color: #3b82f6; }
        
        .btn { display: inline-flex; justify-content: center; align-items: center; background-color: #2563eb; color: white; padding: 1rem 2rem; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; text-decoration: none; transition: all 0.2s ease; width: 100%; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2), 0 2px 4px -1px rgba(37, 99, 235, 0.1); }
        .btn:hover { background-color: #1d4ed8; transform: translateY(-1px); box-shadow: 0 6px 8px -1px rgba(37, 99, 235, 0.3), 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .btn:active { transform: translateY(0); }
        
        .message { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; text-align: center; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .message.success { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .message.error { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        
        .back-link { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 2rem; color: #64748b; text-decoration: none; font-weight: 600; transition: color 0.2s; width: 100%; text-align: center; }
        .back-link:hover { color: #0f172a; }

        .save-btn { background: white; border: 1px solid #cbd5e1; color: #475569; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); height: fit-content; flex-shrink: 0; margin-left: auto; }
        .save-btn:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        .save-btn.saved { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .save-btn.saved:hover { background: #fee2e2; }
        
        .existing-resume-box { background: #eff6ff; padding: 1.25rem; border-radius: 8px; border: 1px solid #bfdbfe; display: flex; flex-direction: column; gap: 0.5rem; }
        .existing-resume-label { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-weight: 600; color: #1e40af; }
        .existing-resume-label input { width: 1.25rem; height: 1.25rem; cursor: pointer; accent-color: #2563eb; }
        .view-resume-link { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; color: #3b82f6; text-decoration: none; font-weight: 500; margin-left: 2rem; }
        .view-resume-link:hover { text-decoration: underline; color: #2563eb; }
        
        .or-divider { display: flex; align-items: center; text-align: center; color: #94a3b8; font-weight: 600; font-size: 0.875rem; margin: 1.5rem 0; }
        .or-divider::before, .or-divider::after { content: ''; flex: 1; border-bottom: 1px solid #e2e8f0; }
        .or-divider:not(:empty)::before { margin-right: .5em; }
        .or-divider:not(:empty)::after { margin-left: .5em; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .container { padding: 1.5rem; }
            .job-header-top { flex-direction: column; align-items: stretch; }
            .job-header h1 { font-size: 1.5rem; }
            html { font-size: 14px; }
            .save-btn { align-self: flex-start; margin-left: 0; }
            .application-section { padding: 1.5rem; }
            
            /* Mobile UI Adjustments */
            .details-grid { grid-template-columns: 1fr; }
            .btn { width: 100%; box-sizing: border-box; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($job): ?>
            <div class="job-header">
                <div class="job-header-top">
                    <h1 style="text-transform: capitalize;"><?php echo htmlspecialchars($job['job_title']); ?></h1>
                    <button class="save-btn <?php echo $is_saved ? 'saved' : ''; ?>" onclick="toggleSave(<?php echo $job_id; ?>, this)">
                        <?php echo $is_saved ? '♥ Saved' : '♡ Save Job'; ?>
                    </button>
                </div>
                <div class="company" style="text-transform: capitalize;"><?php echo htmlspecialchars($job['company_name']); ?></div>
                <div class="meta">
                    <span>
                        <?php 
                            if(!empty($job['barangay'])) echo htmlspecialchars($job['barangay'] . ', ' . $job['city_municipality']);
                            elseif(!empty($job['place_of_work'])) echo htmlspecialchars($job['place_of_work']);
                            else echo 'N/A';
                        ?>
                    </span>
                    <span class="tag"><?php echo htmlspecialchars($job['employment_type']); ?></span>
                    <span>Posted <?php echo date('M d, Y', strtotime($job['posting_date'] ?? $job['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <label>Vacancies</label>
                    <span><?php echo htmlspecialchars($job['vacancies_count'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Salary Range</label>
                    <span>
                        <?php 
                        if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
                            echo '₱' . number_format($job['salary_min']) . ' - ₱' . number_format($job['salary_max']);
                        } elseif (!empty($job['salary_min'])) {
                            echo '₱' . number_format($job['salary_min']) . '+';
                        } elseif (!empty($job['salary_max'])) {
                            echo 'Up to ₱' . number_format($job['salary_max']);
                        } else {
                            echo 'Not specified';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Place of Work</label>
                    <span><?php echo htmlspecialchars($job['place_of_work'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Experience Required</label>
                    <span><?php echo htmlspecialchars($job['experience_required'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Education</label>
                    <span><?php echo htmlspecialchars($job['education_level'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Course</label>
                    <span><?php echo htmlspecialchars($job['course_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Valid Until</label>
                    <span><?php echo !empty($job['valid_until']) ? date('M d, Y', strtotime($job['valid_until'])) : 'Until Filled'; ?></span>
                </div>
            </div>

            <div class="job-description-content">
                <h2 class="section-title">Job Description</h2>
                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
            </div>

            <?php if (!empty($job_skills)): ?>
                <div style="margin-bottom: 2rem;">
                    <h2 class="section-title">Required Skills</h2>
                    <div class="skills-container">
                        <?php foreach($job_skills as $skill): ?>
                            <span class="skill-badge"><?php echo htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php 
            $has_add_quals = !empty($job['license_required']) || !empty($job['eligibility_required']) || !empty($job['certification_required']) || !empty($job['language_spoken']) || !empty($job['accepts_pwd']) || !empty($job['accepts_returning_ofws']) || !empty($job['other_qualifications']);
            ?>
            <?php if ($has_add_quals): ?>
                <div style="margin-bottom: 2rem;">
                    <h2 class="section-title">Additional Qualifications</h2>
                    <ul style="list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 0.5rem; color: #374151;">
                        <?php if(!empty($job['license_required'])): ?>
                            <li><strong>License:</strong> <?php echo htmlspecialchars($job['license_required']); ?></li>
                        <?php endif; ?>
                        <?php if(!empty($job['eligibility_required'])): ?>
                            <li><strong>Eligibility:</strong> <?php echo htmlspecialchars($job['eligibility_required']); ?></li>
                        <?php endif; ?>
                        <?php if(!empty($job['certification_required'])): ?>
                            <li><strong>Certification:</strong> <?php echo htmlspecialchars($job['certification_required']); ?></li>
                        <?php endif; ?>
                        <?php if(!empty($job['language_spoken'])): ?>
                            <li><strong>Language:</strong> <?php echo htmlspecialchars($job['language_spoken']); ?></li>
                        <?php endif; ?>
                        <?php if(!empty($job['accepts_pwd'])): ?>
                            <li>
                                <strong>Accepts PWD</strong>
                                <?php if (!empty($pwd_types)): ?>
                                    <span style="font-weight: normal; color: #6b7280;">(<?php echo htmlspecialchars(implode(', ', $pwd_types)); ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        <?php if(!empty($job['accepts_returning_ofws']) && $job['accepts_returning_ofws']): ?>
                            <li><strong>Accepts Returning OFWs</strong></li>
                        <?php endif; ?>
                    </ul>
                    <?php if(!empty($job['other_qualifications'])): ?>
                        <div style="margin-top: 1rem; color: #374151;">
                            <strong>Other Qualifications:</strong><br>
                            <div style="margin-top: 0.25rem;"><?php echo nl2br(htmlspecialchars($job['other_qualifications'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="about-company">
                <h2 class="section-title" style="border-bottom: none; padding-bottom: 0; margin-bottom: 1rem;">About <?php echo htmlspecialchars($job['company_name']); ?></h2>
                <?php if(!empty($job['business_name']) || !empty($job['employer_type'])): ?>
                    <p style="font-size: 0.9rem; color: #64748b; margin-bottom: 1rem; font-weight: 500;">
                        <?php if(!empty($job['employer_type'])) echo htmlspecialchars($job['employer_type']) . " Employer"; ?>
                        <?php if(!empty($job['employer_type']) && !empty($job['business_name'])) echo " • "; ?>
                        <?php if(!empty($job['business_name'])) echo htmlspecialchars($job['business_name']) . " Industry"; ?>
                    </p>
                <?php endif; ?>
                <?php if(!empty($job['company_description'])): ?>
                    <div style="color: #374151; line-height: 1.6; font-size: 0.95rem;">
                        <?php echo nl2br(htmlspecialchars($job['company_description'])); ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6b7280; font-style: italic; font-size: 0.9rem;">No company description provided.</p>
                <?php endif; ?>
            </div>

            <?php if ($success): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
                <p style="text-align: center;">You can view the status of your application on your dashboard.</p>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$already_applied && !$success && !$is_currently_hired): ?>
                <div class="application-section">
                    <div class="requirements">
                        <h2 class="section-title">Application Requirements</h2>
                        <p>To apply for this position, please upload your most recent resume. Accepted file formats are PDF, DOC, and DOCX (max size: 5MB).</p>
                    </div>
                    <form action="apply_job.php?job_id=<?php echo $job_id; ?>" method="post" enctype="multipart/form-data">
                    <?php if ($existing_resume): ?>
                        <div class="form-group existing-resume-box">
                            <label class="existing-resume-label">
                                <input type="checkbox" name="use_existing" value="1" checked>
                                Use my existing resume
                            </label>
                            <a href="<?php echo htmlspecialchars($existing_resume); ?>" target="_blank" class="view-resume-link">View Current Resume</a>
                        </div>
                        <div class="or-divider">OR</div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="resume">Upload New Resume</label>
                        <input type="file" name="resume" id="resume" <?php echo $existing_resume ? '' : 'required'; ?>>
                    </div>
                    <button type="submit" class="btn">Submit Application</button>
                    </form>
                </div>
            <?php endif; ?>

            <a href="browse_jobs.php" class="back-link">&larr; Back to Job Listings</a>
        <?php else: ?>
            <h1 style="text-align: center;">Job Not Found</h1>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <a href="browse_jobs.php" class="back-link">&larr; Back to Job Listings</a>
        <?php endif; ?>
    </div>
    <script>
        function toggleSave(jobId, btn) {
            fetch('toggle_save_job.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ job_id: jobId })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    btn.classList.toggle('saved');
                    btn.innerHTML = btn.classList.contains('saved') ? '♥ Saved' : '♡ Save Job';
                }
            });
        }
    </script>
</body>
</html>
