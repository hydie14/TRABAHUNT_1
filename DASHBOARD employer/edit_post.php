<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['job_id']) || !filter_var($_GET['job_id'], FILTER_VALIDATE_INT)) {
    header("Location: employer_dashboard.php");
    exit();
}

$job_id = (int)$_GET['job_id'];
$error = '';
$success = '';

$job_query = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ? AND employer_id = ?");
$job_query->bind_param("ii", $job_id, $_SESSION['user_id']);
$job_query->execute();
$job_result = $job_query->get_result();
$job = $job_result->fetch_assoc();
$job_query->close();

if (!$job) {
    header("Location: employer_dashboard.php");
    exit();
}

// Fetch dropdown options
$education_levels = $conn->query("SELECT * FROM education_levels ORDER BY education_id ASC");
$courses = $conn->query("SELECT * FROM courses ORDER BY course_name ASC");
$skills_result = $conn->query("SELECT * FROM skills ORDER BY skill_name");

// Fetch selected skills
$selected_skills = [];
$ss_stmt = $conn->prepare("SELECT skill_id FROM job_skills WHERE job_id = ?");
$ss_stmt->bind_param("i", $job_id);
$ss_stmt->execute();
$ss_res = $ss_stmt->get_result();
while($row = $ss_res->fetch_assoc()) $selected_skills[] = $row['skill_id'];
$ss_stmt->close();

// Fetch disabilities
$stmt_dis = $conn->prepare("SELECT * FROM job_posting_disabilities WHERE job_id = ?");
$stmt_dis->bind_param("i", $job_id);
$stmt_dis->execute();
$dis_result = $stmt_dis->get_result();
$disabilities = [];
$other_disability_desc = '';
while($row = $dis_result->fetch_assoc()) {
    $disabilities[] = $row['disability_type'];
    if ($row['disability_type'] == 'Others') {
        $other_disability_desc = $row['other_description'];
    }
}
$stmt_dis->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } elseif (isset($_POST['close_job'])) {
        $conn->begin_transaction();
        try {
            $close_reason = $_POST['close_reason'];
            $stmt = $conn->prepare("UPDATE job_postings SET status = 'Closed', close_reason = ? WHERE job_id = ? AND employer_id = ?");
            $stmt->bind_param("sii", $close_reason, $job_id, $_SESSION['user_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Error closing job: " . $stmt->error);
            }
            $stmt->close();

            $conn->commit();
            header("Location: employer_dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        // III. VACANCY DETAILS
            $job_title = ucwords(trim($_POST['job_title']));
            $description = ucfirst(trim($_POST['description']));
        $employment_type = $_POST['employment_type'];
            $place_of_work = ucwords(trim($_POST['place_of_work']));
        $salary_min = !empty($_POST['salary_min']) ? $_POST['salary_min'] : NULL;
        $salary_max = !empty($_POST['salary_max']) ? $_POST['salary_max'] : NULL;
        $vacancies_count = (int)$_POST['vacancies_count'];

        // IV. QUALIFICATION REQUIREMENTS
            $experience_required = ucfirst(trim($_POST['experience_required']));
            $other_qualifications = ucfirst(trim($_POST['other_qualifications']));
        $accepts_pwd = (isset($_POST['accepts_pwd']) && $_POST['accepts_pwd'] == 1) ? 1 : 0;
        $accepts_returning_ofws = (isset($_POST['accepts_returning_ofws']) && $_POST['accepts_returning_ofws'] == 1) ? 1 : 0;
        $education_id = !empty($_POST['education_id']) ? $_POST['education_id'] : NULL;
        $course_id = !empty($_POST['course_id']) ? $_POST['course_id'] : NULL;
            $license_required = ucwords(trim($_POST['license_required']));
            $eligibility_required = ucwords(trim($_POST['eligibility_required']));
            $certification_required = ucwords(trim($_POST['certification_required']));
            $language_spoken = ucwords(trim($_POST['language_spoken']));

        // V. POSTING DETAILS
        $posting_date = $job['posting_date'];
        $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : NULL;

        if ($valid_until && $valid_until < $posting_date) {
            $error = "Validity date cannot be before the posting date.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE job_postings SET job_title=?, description=?, vacancies_count=?, salary_min=?, salary_max=?, place_of_work=?, employment_type=?, education_id=?, course_id=?, experience_required=?, other_qualifications=?, accepts_pwd=?, accepts_returning_ofws=?, license_required=?, eligibility_required=?, certification_required=?, language_spoken=?, posting_date=?, valid_until=? WHERE job_id=?");
                
                $stmt->bind_param("ssiddssiissiissssssi", 
                    $job_title, $description, $vacancies_count, $salary_min, $salary_max, 
                    $place_of_work, $employment_type, $education_id, $course_id, $experience_required, 
                    $other_qualifications, $accepts_pwd, $accepts_returning_ofws, $license_required, 
                    $eligibility_required, $certification_required, $language_spoken, $posting_date, $valid_until, $job_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating job: " . $stmt->error);
                }
                $stmt->close();

                // Handle Skills
                $stmt_del_skills = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
                $stmt_del_skills->bind_param("i", $job_id);
                $stmt_del_skills->execute();
                $stmt_del_skills->close();

                if (isset($_POST['skills']) && is_array($_POST['skills'])) {
                    $stmt_skill = $conn->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)");
                    foreach ($_POST['skills'] as $skill_id) {
                        $skill_id = (int)$skill_id;
                        $stmt_skill->bind_param("ii", $job_id, $skill_id);
                        $stmt_skill->execute();
                    }
                    $stmt_skill->close();
                }

                // Handle Disabilities
                $stmt_del = $conn->prepare("DELETE FROM job_posting_disabilities WHERE job_id = ?");
                $stmt_del->bind_param("i", $job_id);
                $stmt_del->execute();
                $stmt_del->close();

                if ($accepts_pwd && isset($_POST['disability_type']) && is_array($_POST['disability_type'])) {
                    $stmt_dis = $conn->prepare("INSERT INTO job_posting_disabilities (job_id, disability_type, other_description) VALUES (?, ?, ?)");
                    foreach ($_POST['disability_type'] as $type) {
                        $other_desc = ($type == 'Others') ? $_POST['disability_others_desc'] : NULL;
                        $stmt_dis->bind_param("iss", $job_id, $type, $other_desc);
                        $stmt_dis->execute();
                    }
                    $stmt_dis->close();
                }

                $conn->commit();
                $success = "Job post updated successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
        // Refresh job data
        $job_query = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ?");
            $job_query->bind_param("i", $job_id);
            $job_query->execute();
            $job_result = $job_query->get_result();
            $job = $job_result->fetch_assoc();
            $job_query->close();
            
            // Refresh selected skills array after update
            $selected_skills = [];
            $ss_stmt = $conn->prepare("SELECT skill_id FROM job_skills WHERE job_id = ?");
            $ss_stmt->bind_param("i", $job_id);
            $ss_stmt->execute();
            $ss_res = $ss_stmt->get_result();
            while($row = $ss_res->fetch_assoc()) $selected_skills[] = $row['skill_id'];
            $ss_stmt->close();
            
            // Refresh disabilities
            $stmt_dis = $conn->prepare("SELECT * FROM job_posting_disabilities WHERE job_id = ?");
            $stmt_dis->bind_param("i", $job_id);
            $stmt_dis->execute();
            $dis_result = $stmt_dis->get_result();
            $disabilities = [];
            $other_disability_desc = '';
            while($row = $dis_result->fetch_assoc()) {
                $disabilities[] = $row['disability_type'];
                if ($row['disability_type'] == 'Others') {
                    $other_disability_desc = $row['other_description'];
                }
            }
            $stmt_dis->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Post</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { color: #1e40af; margin: 0; font-size: 1.75rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        input[type="text"], input[type="number"], input[type="date"], textarea, select { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid #d1d5db; font-family: inherit; }
        textarea { resize: vertical; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-primary { background: #1e40af; color: white; box-shadow: 0 2px 4px rgba(30, 64, 175, 0.2); }
        .btn-primary:hover { background: #1e3a8a; transform: translateY(-1px); }
        .btn-secondary { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        .btn-secondary:hover { background: #e5e7eb; color: #1f2937; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .section-title { font-size: 1.1rem; color: #1e40af; margin: 2rem 0 1rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
        .row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .checkbox-group { display: flex; gap: 1rem; align-items: center; margin-top: 0.5rem; }
        .checkbox-group label { margin-bottom: 0; font-weight: normal; }
        .disability-options { margin-left: 1.5rem; margin-top: 0.5rem; display: none; }
        .disability-options label { display: inline-block; margin-right: 1rem; font-weight: normal; }
        .form-actions { margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; display: flex; gap: 1rem; justify-content: flex-end; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .container { padding: 1.5rem; }
            .row { grid-template-columns: 1fr; gap: 1rem; }
            .header-flex { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .form-actions { flex-direction: column; gap: 0.5rem; }
            div[style*="text-align: right"] { flex-direction: column; gap: 0.5rem; }
            .btn { width: 100%; text-align: center; box-sizing: border-box; margin-left: 0; }
            .checkbox-group { flex-wrap: wrap; gap: 0.5rem; }
            .disability-options label { display: block; margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h1>Edit Job Post</h1>
            <a href="employer_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
        <?php if($error): ?><p style="color: red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: green;"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>
        <form method="post" action="edit_post.php?job_id=<?php echo (int)$job_id; ?>">
            <?php echo csrf_field(); ?>
            
            <!-- III. VACANCY DETAILS -->
            <h3 class="section-title">III. VACANCY DETAILS</h3>
            <div class="form-group">
                <label for="job_title">Position Title <span style="color:red">*</span></label>
                <input type="text" id="job_title" name="job_title" value="<?php echo htmlspecialchars($job['job_title']); ?>" required style="text-transform: capitalize;">
            </div>
            <div class="form-group">
                <label for="description">Job Description <span style="color:red">*</span></label>
                <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($job['description']); ?></textarea>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="employment_type">Nature of Work <span style="color:red">*</span></label>
                    <select id="employment_type" name="employment_type" required>
                        <option value="">Select Type</option>
                        <?php
                        $types = ["Permanent", "Contractual", "Internship/OJT", "Part-time", "Project-based", "Work from home/online job"];
                        foreach ($types as $type) {
                            $selected = ($job['employment_type'] == $type) ? 'selected' : '';
                            echo "<option value=\"$type\" $selected>$type</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="place_of_work">Place of Work</label>
                    <input type="text" id="place_of_work" name="place_of_work" value="<?php echo htmlspecialchars($job['place_of_work']); ?>" placeholder="e.g., Bongabon, Nueva Ecija" style="text-transform: capitalize;">
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Salary Range</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" name="salary_min" placeholder="Min" step="0.01" value="<?php echo htmlspecialchars($job['salary_min']); ?>">
                        <input type="number" name="salary_max" placeholder="Max" step="0.01" value="<?php echo htmlspecialchars($job['salary_max']); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="vacancies_count">Vacancy Count <span style="color:red">*</span></label>
                    <input type="number" id="vacancies_count" name="vacancies_count" min="1" value="<?php echo htmlspecialchars($job['vacancies_count']); ?>" required>
                </div>
            </div>

            <!-- IV. QUALIFICATION REQUIREMENTS -->
            <h3 class="section-title">IV. QUALIFICATION REQUIREMENTS</h3>
            <div class="form-group">
                <label for="experience_required">Work Experience (month/s)</label>
                <input type="text" id="experience_required" name="experience_required" value="<?php echo htmlspecialchars($job['experience_required']); ?>" placeholder="e.g., 6 months, 1 year">
            </div>
            <div class="form-group">
                <label for="other_qualifications">Other qualifications</label>
                <textarea id="other_qualifications" name="other_qualifications" rows="3"><?php echo htmlspecialchars($job['other_qualifications']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Required Skills</label>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #d1d5db; padding: 0.75rem; border-radius: 6px; background: white;">
                    <?php 
                    if($skills_result) $skills_result->data_seek(0);
                    while($skill = $skills_result->fetch_assoc()): ?>
                        <div style="margin-bottom: 0.25rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 400; cursor: pointer;">
                                <input type="checkbox" name="skills[]" value="<?php echo $skill['skill_id']; ?>" <?php echo in_array($skill['skill_id'], $selected_skills) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Accepts persons with disabilities (PWD)?</label>
                <div class="checkbox-group">
                    <label><input type="radio" name="accepts_pwd" value="1" onclick="toggleDisability(true)" <?php echo ($job['accepts_pwd'] == 1) ? 'checked' : ''; ?>> Yes</label>
                    <label><input type="radio" name="accepts_pwd" value="0" onclick="toggleDisability(false)" <?php echo ($job['accepts_pwd'] == 0) ? 'checked' : ''; ?>> No</label>
                </div>
                <div id="disability_options" class="disability-options" style="display: <?php echo ($job['accepts_pwd'] == 1) ? 'block' : 'none'; ?>;">
                    <p style="margin: 0.5rem 0; font-size: 0.9rem; color: #6b7280;">If "yes", please specify:</p>
                    <?php
                    $dis_types = ["Visual", "Hearing", "Speech", "Physical", "Mental", "Others"];
                    foreach ($dis_types as $dtype) {
                        $checked = in_array($dtype, $disabilities) ? 'checked' : '';
                        $onclick = ($dtype == 'Others') ? 'onclick="toggleOthers(this)"' : '';
                        echo "<label><input type=\"checkbox\" name=\"disability_type[]\" value=\"$dtype\" $checked $onclick> $dtype</label>";
                    }
                    ?>
                    <input type="text" id="disability_others_desc" name="disability_others_desc" value="<?php echo htmlspecialchars($other_disability_desc); ?>" placeholder="Please specify" style="display: <?php echo in_array('Others', $disabilities) ? 'block' : 'none'; ?>; margin-top: 0.5rem;">
                </div>
            </div>

            <div class="form-group">
                <label>Accepts returning OFWs?</label>
                <div class="checkbox-group">
                    <label><input type="radio" name="accepts_returning_ofws" value="1" <?php echo ($job['accepts_returning_ofws'] == 1) ? 'checked' : ''; ?>> Yes</label>
                    <label><input type="radio" name="accepts_returning_ofws" value="0" <?php echo ($job['accepts_returning_ofws'] == 0) ? 'checked' : ''; ?>> No</label>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="education_id">Educational Level</label>
                    <select id="education_id" name="education_id">
                        <option value="">Select Level</option>
                        <?php while($row = $education_levels->fetch_assoc()): ?>
                            <option value="<?php echo $row['education_id']; ?>" <?php echo ($job['education_id'] == $row['education_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['level_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_id">Course/SHS Strand</label>
                    <select id="course_id" name="course_id">
                        <option value="">Select Course</option>
                        <?php while($row = $courses->fetch_assoc()): ?>
                            <option value="<?php echo $row['course_id']; ?>" <?php echo ($job['course_id'] == $row['course_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['course_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="form-group">
                    <label for="license_required">License</label>
                    <input type="text" id="license_required" name="license_required" value="<?php echo htmlspecialchars($job['license_required']); ?>" style="text-transform: capitalize;">
                </div>
                <div class="form-group">
                    <label for="eligibility_required">Eligibility</label>
                    <input type="text" id="eligibility_required" name="eligibility_required" value="<?php echo htmlspecialchars($job['eligibility_required']); ?>" style="text-transform: capitalize;">
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label for="certification_required">Certification</label>
                    <input type="text" id="certification_required" name="certification_required" value="<?php echo htmlspecialchars($job['certification_required']); ?>" style="text-transform: capitalize;">
                </div>
                <div class="form-group">
                    <label for="language_spoken">Language/dialect Spoken</label>
                    <input type="text" id="language_spoken" name="language_spoken" value="<?php echo htmlspecialchars($job['language_spoken']); ?>" style="text-transform: capitalize;">
                </div>
            </div>

            <!-- V. POSTING DETAILS -->
            <h3 class="section-title">V. POSTING DETAILS</h3>
            <div class="row">
                <div class="form-group">
                    <label for="valid_until">Valid Until</label>
                    <input type="date" id="valid_until" name="valid_until" value="<?php echo htmlspecialchars($job['valid_until']); ?>" min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-danger" onclick="openCloseModal()">Close Job</button>
                <a href="employer_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Job</button>
            </div>

            <!-- Close Reason Modal inside Form (Hidden Inputs wrapper) -->
            <div id="closeJobModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
                <div style="background: white; padding: 2rem; border-radius: 12px; width: 100%; max-width: 500px;">
                    <h2 style="margin-top: 0; color: #1e40af;">Close Job Post</h2>
                    <p style="color: #6b7280;">Why are you closing this job?</p>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <select name="close_reason" id="modalCloseReason" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;">
                            <option value="Quota Reached">Quota Reached (Nakuha na ang quota)</option>
                            <option value="Position Cancelled">Position Cancelled</option>
                            <option value="Internal Hire">Internal Hire</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div style="text-align: right; display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('closeJobModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                        <button type="submit" name="close_job" value="1" class="btn btn-danger">Confirm Close</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function openCloseModal() {
            document.getElementById('closeJobModal').style.display = 'flex';
        }
        function toggleDisability(show) {
            document.getElementById('disability_options').style.display = show ? 'block' : 'none';
        }
        function toggleOthers(checkbox) {
            document.getElementById('disability_others_desc').style.display = checkbox.checked ? 'block' : 'none';
        }
    </script>
</body>
</html>
