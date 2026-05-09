<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-capitalize names (Validation & Formatting)
    $first_name = ucwords(strtolower(trim($_POST['first_name'])));
    $middle_name = !empty($_POST['middle_name']) ? ucwords(strtolower(trim($_POST['middle_name']))) : '';
    $last_name = ucwords(strtolower(trim($_POST['last_name'])));
    $suffix = $_POST['suffix'];
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $civil_status = $_POST['civil_status'];
    $place_of_birth = $_POST['place_of_birth'];
    $street_address = $_POST['street_address'];
    $location_id = $_POST['location_id'];
    $education_id = !empty($_POST['education_id']) ? $_POST['education_id'] : NULL;
    $course_id = !empty($_POST['course_id']) ? $_POST['course_id'] : NULL;
    $employment_status = $_POST['employment_status'];
    $disability = $_POST['disability'];
    $summary = $_POST['summary'];
    $contact_no = $_POST['contact_no'];
    $languages = $_POST['languages'];

    $stmt = $conn->prepare("UPDATE jobseekers SET 
        first_name=?, middle_name=?, last_name=?, suffix=?, 
        gender=?, birthdate=?, civil_status=?, place_of_birth=?, 
        street_address=?, location_id=?, education_id=?, course_id=?, 
        employment_status=?, disability=?, summary=?, contact_no=?, languages=? 
        WHERE seeker_id=?");
    
    $stmt->bind_param("sssssssssiiisssssi", 
        $first_name, $middle_name, $last_name, $suffix, 
        $gender, $birthdate, $civil_status, $place_of_birth, 
        $street_address, $location_id, $education_id, $course_id, 
        $employment_status, $disability, $summary, $contact_no, $languages, $user_id);

    if ($stmt->execute()) {
        // Update Skills
        $conn->query("DELETE FROM jobseeker_skills WHERE seeker_id = $user_id");
        
        $skills_to_add = [];
        if (isset($_POST['skills']) && is_array($_POST['skills'])) {
            foreach ($_POST['skills'] as $sid) {
                $skills_to_add[] = (int)$sid;
            }
        }

        // Handle new skills (comma separated)
        if (!empty($_POST['new_skill'])) {
            $new_skills_input = explode(',', $_POST['new_skill']);
            foreach ($new_skills_input as $ns) {
                $new_skill_name = ucwords(strtolower(trim($ns)));
                if (!empty($new_skill_name)) {
                    $check_skill = $conn->prepare("SELECT skill_id FROM skills WHERE skill_name = ?");
                    $check_skill->bind_param("s", $new_skill_name);
                    $check_skill->execute();
                    $res = $check_skill->get_result();
                    if ($res->num_rows > 0) {
                        $skills_to_add[] = $res->fetch_assoc()['skill_id'];
                    } else {
                        $ins_new_skill = $conn->prepare("INSERT INTO skills (skill_name) VALUES (?)");
                        $ins_new_skill->bind_param("s", $new_skill_name);
                        if ($ins_new_skill->execute()) {
                            $skills_to_add[] = $ins_new_skill->insert_id;
                        }
                    }
                }
            }
        }

        if (!empty($skills_to_add)) {
            $skills_to_add = array_unique($skills_to_add);
            $ins_skill = $conn->prepare("INSERT INTO jobseeker_skills (seeker_id, skill_id) VALUES (?, ?)");
            foreach ($skills_to_add as $sid) {
                $ins_skill->bind_param("ii", $user_id, $sid);
                $ins_skill->execute();
            }
        }

        // Update Educational Background
        $conn->query("DELETE FROM educational_background WHERE seeker_id = $user_id");
        if (isset($_POST['edu_school_name']) && is_array($_POST['edu_school_name'])) {
            $ins_edu = $conn->prepare("INSERT INTO educational_background (seeker_id, school_name, school_year) VALUES (?, ?, ?)");
            foreach ($_POST['edu_school_name'] as $k => $v) {
                $s_name = trim($v);
                $s_year = trim($_POST['edu_school_year'][$k]);
                
                if(!empty($s_name)) {
                    $ins_edu->bind_param("iss", $user_id, $s_name, $s_year);
                    $ins_edu->execute();
                }
            }
        }

        // Update Work Experience
        $conn->query("DELETE FROM work_experience WHERE seeker_id = $user_id");
        if (isset($_POST['work_title']) && is_array($_POST['work_title'])) {
            $ins_work = $conn->prepare("INSERT INTO work_experience (seeker_id, job_title, company_name, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['work_title'] as $k => $v) {
                $title = trim($v);
                $company = trim($_POST['work_company'][$k]);
                $start = $_POST['work_start'][$k];
                $end = !empty($_POST['work_end'][$k]) ? $_POST['work_end'][$k] : NULL;
                $desc = trim($_POST['work_desc'][$k]);
                
                if(!empty($title) && !empty($company) && !empty($start)) {
                    $ins_work->bind_param("isssss", $user_id, $title, $company, $start, $end, $desc);
                    $ins_work->execute();
                }
            }
        }

        // Update Seminars
        $conn->query("DELETE FROM seminars_trainings WHERE seeker_id = $user_id");
        if (isset($_POST['sem_title']) && is_array($_POST['sem_title'])) {
            $ins_sem = $conn->prepare("INSERT INTO seminars_trainings (seeker_id, title, provider, date_attended) VALUES (?, ?, ?, ?)");
            foreach ($_POST['sem_title'] as $k => $v) {
                $title = trim($v);
                $provider = trim($_POST['sem_provider'][$k]);
                $date = !empty($_POST['sem_date'][$k]) ? $_POST['sem_date'][$k] : NULL;
                
                if(!empty($title)) {
                    $ins_sem->bind_param("isss", $user_id, $title, $provider, $date);
                    $ins_sem->execute();
                }
            }
        }

        // Update References
        $conn->query("DELETE FROM character_references WHERE seeker_id = $user_id");
        if (isset($_POST['ref_name']) && is_array($_POST['ref_name'])) {
            $ins_ref = $conn->prepare("INSERT INTO character_references (seeker_id, name, company, position, contact_no, email) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['ref_name'] as $k => $v) {
                $name = trim($v);
                $company = trim($_POST['ref_company'][$k]);
                $position = trim($_POST['ref_position'][$k]);
                $contact = trim($_POST['ref_contact'][$k]);
                $email = trim($_POST['ref_email'][$k]);
                
                if(!empty($name)) {
                    $ins_ref->bind_param("isssss", $user_id, $name, $company, $position, $contact, $email);
                    $ins_ref->execute();
                }
            }
        }
        $success = "Profile updated successfully!";
    } else {
        $error = "Error updating profile: " . $conn->error;
    }
    $stmt->close();
}

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM jobseekers WHERE seeker_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch dropdown data
$locations_result = $conn->query("SELECT * FROM locations ORDER BY city_municipality, barangay");
$education_levels_result = $conn->query("SELECT * FROM education_levels");
$courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name");
$skills_result = $conn->query("SELECT * FROM skills ORDER BY skill_name");

// Fetch selected skills
$selected_skills = [];
$ss_stmt = $conn->prepare("SELECT skill_id FROM jobseeker_skills WHERE seeker_id = ?");
$ss_stmt->bind_param("i", $user_id);
$ss_stmt->execute();
$ss_res = $ss_stmt->get_result();
while($row = $ss_res->fetch_assoc()) $selected_skills[] = $row['skill_id'];

// Fetch Educational Background
$edu_bg_data = [];
$e_res = $conn->query("SELECT * FROM educational_background WHERE seeker_id = $user_id");
while($row = $e_res->fetch_assoc()) $edu_bg_data[] = $row;

// Fetch Work Experience
$work_data = [];
$w_res = $conn->query("SELECT * FROM work_experience WHERE seeker_id = $user_id ORDER BY start_date DESC");
while($row = $w_res->fetch_assoc()) $work_data[] = $row;

// Fetch Seminars
$sem_data = [];
$s_res = $conn->query("SELECT * FROM seminars_trainings WHERE seeker_id = $user_id ORDER BY date_attended DESC");
while($row = $s_res->fetch_assoc()) $sem_data[] = $row;

// Fetch References
$ref_data = [];
$r_res = $conn->query("SELECT * FROM character_references WHERE seeker_id = $user_id");
while($row = $r_res->fetch_assoc()) $ref_data[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - PESO Bongabon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; color: #1f2937; display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 3rem 2rem; }
        
        .form-card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #eaeaea; max-width: 800px; margin: 0 auto; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-primary { background: #2563eb; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 4px -1px rgba(37, 99, 235, 0.1); }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); }
        .btn-secondary { background: white; color: #374151; padding: 0.75rem 1.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #f9fafb; }
        
        .message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; }
        .success { background: #d1fae5; color: #065f46; }
        .error { background: #fee2e2; color: #991b1b; }

        .dynamic-row { background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 1rem; position: relative; }
        .remove-row-btn { position: absolute; top: 0.5rem; right: 0.5rem; color: #ef4444; background: none; border: none; cursor: pointer; font-size: 1.25rem; line-height: 1; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; margin-top: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
        .section-header h3 { font-size: 1.1rem; font-weight: 600; color: #1f2937; margin: 0; }
        .add-btn { background: #eff6ff; color: #1e40af; border: 1px solid #dbeafe; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.875rem; cursor: pointer; font-weight: 500; type: button; }
        .add-btn:hover { background: #dbeafe; }

        @media (max-width: 768px) {
            
            /* Mobile UI Adjustments */
            .form-card { padding: 1.5rem; }
            .btn-primary, .btn-secondary { width: 100%; text-align: center; box-sizing: border-box; margin-bottom: 0.5rem; }
            div[style*="margin-top: 2rem; display: flex; gap: 1rem;"] { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

    <main class="main-content">
        <div class="form-card">
            <h2 style="margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: 700;">Edit Profile</h2>
            
            <?php if($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required style="text-transform: capitalize;"></div>
                    <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($user['middle_name']); ?>" style="text-transform: capitalize;"></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required style="text-transform: capitalize;"></div>
                    <div class="form-group"><label>Suffix</label><input type="text" name="suffix" class="form-control" value="<?php echo htmlspecialchars($user['suffix']); ?>"></div>
                    <div class="form-group"><label>Gender</label><select name="gender" class="form-control" required><option value="Male" <?php echo $user['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option><option value="Female" <?php echo $user['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option></select></div>
                    <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" class="form-control" value="<?php echo $user['birthdate']; ?>" required></div>
                    <div class="form-group"><label>Civil Status</label><select name="civil_status" class="form-control" required><option value="Single" <?php echo $user['civil_status'] == 'Single' ? 'selected' : ''; ?>>Single</option><option value="Married" <?php echo $user['civil_status'] == 'Married' ? 'selected' : ''; ?>>Married</option><option value="Widowed" <?php echo $user['civil_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option><option value="Separated" <?php echo $user['civil_status'] == 'Separated' ? 'selected' : ''; ?>>Separated</option></select></div>
                    <div class="form-group"><label>Place of Birth</label><input type="text" name="place_of_birth" class="form-control" value="<?php echo htmlspecialchars($user['place_of_birth']); ?>" required></div>
                    <div class="form-group" style="grid-column: 1 / -1;"><label>Street Address</label><input type="text" name="street_address" class="form-control" value="<?php echo htmlspecialchars($user['street_address']); ?>" required></div>
                    <div class="form-group"><label>Location</label><select name="location_id" class="form-control" required><?php while($loc = $locations_result->fetch_assoc()): ?><option value="<?php echo $loc['location_id']; ?>" <?php echo $user['location_id'] == $loc['location_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['barangay'] . ', ' . $loc['city_municipality']); ?></option><?php endwhile; ?></select></div>
                    <div class="form-group"><label>Employment Status</label><select name="employment_status" class="form-control"><option value="Unemployed" <?php echo $user['employment_status'] == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option><option value="Underemployed" <?php echo $user['employment_status'] == 'Underemployed' ? 'selected' : ''; ?>>Underemployed</option><option value="Employed" <?php echo $user['employment_status'] == 'Employed' ? 'selected' : ''; ?>>Employed</option></select></div>
                    
                    <!-- New Fields -->
                    <div class="form-group">
                        <label>Education Level</label>
                        <select name="education_id" class="form-control">
                            <option value="">Select Education Level</option>
                            <?php while($edu = $education_levels_result->fetch_assoc()): ?>
                                <option value="<?php echo $edu['education_id']; ?>" <?php echo $user['education_id'] == $edu['education_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($edu['level_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Course / Major</label>
                        <select name="course_id" class="form-control">
                            <option value="">Select Course</option>
                            <?php while($course = $courses_result->fetch_assoc()): ?>
                                <option value="<?php echo $course['course_id']; ?>" <?php echo $user['course_id'] == $course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($user['contact_no'] ?? ''); ?>" placeholder="e.g. 09123456789">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Languages Spoken</label>
                        <input type="text" name="languages" class="form-control" value="<?php echo htmlspecialchars($user['languages'] ?? ''); ?>" placeholder="e.g. English, Tagalog, Ilocano">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Summary / Objective</label>
                        <textarea name="summary" class="form-control" rows="4" placeholder="Briefly describe your professional background and career goals..."><?php echo htmlspecialchars($user['summary'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Skills</label>
                        <div style="max-height: 150px; overflow-y: auto; border: 1px solid #d1d5db; padding: 0.75rem; border-radius: 6px; background: white;">
                            <?php while($skill = $skills_result->fetch_assoc()): ?>
                                <div style="margin-bottom: 0.25rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 400; cursor: pointer;">
                                        <input type="checkbox" name="skills[]" value="<?php echo $skill['skill_id']; ?>" <?php echo in_array($skill['skill_id'], $selected_skills) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($skill['skill_name']); ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <input type="text" name="new_skill" class="form-control" placeholder="Add new skills (comma-separated, e.g. Typing, Driving)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Disability (if any)</label>
                        <input type="text" name="disability" class="form-control" value="<?php echo htmlspecialchars($user['disability'] ?? ''); ?>" placeholder="e.g. None, Hearing Impairment">
                    </div>
                </div>

                <!-- Educational Background Section -->
                <div class="section-header">
                    <h3>Schools Attended</h3>
                    <button type="button" class="add-btn" onclick="addEducationRow()">+ Add School</button>
                </div>
                <div id="education-container">
                    <?php foreach($edu_bg_data as $edu): ?>
                    <div class="dynamic-row">
                        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                        <div class="form-grid">
                            <div class="form-group"><label>School Name</label><input type="text" name="edu_school_name[]" class="form-control" value="<?php echo htmlspecialchars($edu['school_name']); ?>" required placeholder="e.g. Bongabon National High School"></div>
                            <div class="form-group"><label>School Year / Year Graduated</label><input type="text" name="edu_school_year[]" class="form-control" value="<?php echo htmlspecialchars($edu['school_year']); ?>" placeholder="e.g. 2018 - 2022"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Work Experience Section -->
                <div class="section-header">
                    <h3>Work Experience</h3>
                    <button type="button" class="add-btn" onclick="addWorkRow()">+ Add Work</button>
                </div>
                <div id="work-container">
                    <?php foreach($work_data as $work): ?>
                    <div class="dynamic-row">
                        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                        <div class="form-grid">
                            <div class="form-group"><label>Job Title</label><input type="text" name="work_title[]" class="form-control" value="<?php echo htmlspecialchars($work['job_title']); ?>" required></div>
                            <div class="form-group"><label>Company</label><input type="text" name="work_company[]" class="form-control" value="<?php echo htmlspecialchars($work['company_name']); ?>" required></div>
                            <div class="form-group"><label>Start Date</label><input type="date" name="work_start[]" class="form-control" value="<?php echo $work['start_date']; ?>" required></div>
                            <div class="form-group"><label>End Date (Leave empty if Present)</label><input type="date" name="work_end[]" class="form-control" value="<?php echo $work['end_date']; ?>"></div>
                            <div class="form-group" style="grid-column: 1 / -1;"><label>Description</label><textarea name="work_desc[]" class="form-control" rows="2"><?php echo htmlspecialchars($work['description']); ?></textarea></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Seminars Section -->
                <div class="section-header">
                    <h3>Seminars & Trainings</h3>
                    <button type="button" class="add-btn" onclick="addSeminarRow()">+ Add Seminar</button>
                </div>
                <div id="seminar-container">
                    <?php foreach($sem_data as $sem): ?>
                    <div class="dynamic-row">
                        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                        <div class="form-grid">
                            <div class="form-group"><label>Title</label><input type="text" name="sem_title[]" class="form-control" value="<?php echo htmlspecialchars($sem['title']); ?>" required></div>
                            <div class="form-group"><label>Provider</label><input type="text" name="sem_provider[]" class="form-control" value="<?php echo htmlspecialchars($sem['provider']); ?>" required></div>
                            <div class="form-group"><label>Date Attended</label><input type="date" name="sem_date[]" class="form-control" value="<?php echo $sem['date_attended']; ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- References Section -->
                <div class="section-header">
                    <h3>Character References</h3>
                    <button type="button" class="add-btn" onclick="addRefRow()">+ Add Reference</button>
                </div>
                <div id="ref-container">
                    <?php foreach($ref_data as $ref): ?>
                    <div class="dynamic-row">
                        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                        <div class="form-grid">
                            <div class="form-group"><label>Name</label><input type="text" name="ref_name[]" class="form-control" value="<?php echo htmlspecialchars($ref['name']); ?>" required></div>
                            <div class="form-group"><label>Company</label><input type="text" name="ref_company[]" class="form-control" value="<?php echo htmlspecialchars($ref['company']); ?>"></div>
                            <div class="form-group"><label>Position</label><input type="text" name="ref_position[]" class="form-control" value="<?php echo htmlspecialchars($ref['position']); ?>"></div>
                            <div class="form-group"><label>Contact No.</label><input type="text" name="ref_contact[]" class="form-control" value="<?php echo htmlspecialchars($ref['contact_no']); ?>"></div>
                            <div class="form-group"><label>Email</label><input type="email" name="ref_email[]" class="form-control" value="<?php echo htmlspecialchars($ref['email']); ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="my_profile.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Validation to prevent numbers and special characters in name fields
        document.addEventListener('DOMContentLoaded', function() {
            const nameInputs = document.querySelectorAll('input[name="first_name"], input[name="middle_name"], input[name="last_name"]');
            nameInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^a-zA-Z\s\u00f1\u00d1]/g, '');
                });
            });
        });

        function addEducationRow() {
            const container = document.getElementById('education-container');
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                <div class="form-grid">
                    <div class="form-group"><label>School Name</label><input type="text" name="edu_school_name[]" class="form-control" required placeholder="e.g. Bongabon National High School"></div>
                    <div class="form-group"><label>School Year / Year Graduated</label><input type="text" name="edu_school_year[]" class="form-control" placeholder="e.g. 2018 - 2022"></div>
                </div>
            `;
            container.appendChild(div);
        }

        function addWorkRow() {
            const container = document.getElementById('work-container');
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                <div class="form-grid">
                    <div class="form-group"><label>Job Title</label><input type="text" name="work_title[]" class="form-control" required></div>
                    <div class="form-group"><label>Company</label><input type="text" name="work_company[]" class="form-control" required></div>
                    <div class="form-group"><label>Start Date</label><input type="date" name="work_start[]" class="form-control" required></div>
                    <div class="form-group"><label>End Date</label><input type="date" name="work_end[]" class="form-control"></div>
                    <div class="form-group" style="grid-column: 1 / -1;"><label>Description</label><textarea name="work_desc[]" class="form-control" rows="2"></textarea></div>
                </div>
            `;
            container.appendChild(div);
        }

        function addSeminarRow() {
            const container = document.getElementById('seminar-container');
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                <div class="form-grid">
                    <div class="form-group"><label>Title</label><input type="text" name="sem_title[]" class="form-control" required></div>
                    <div class="form-group"><label>Provider</label><input type="text" name="sem_provider[]" class="form-control" required></div>
                    <div class="form-group"><label>Date Attended</label><input type="date" name="sem_date[]" class="form-control"></div>
                </div>
            `;
            container.appendChild(div);
        }

        function addRefRow() {
            const container = document.getElementById('ref-container');
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()">&times;</button>
                <div class="form-grid">
                    <div class="form-group"><label>Name</label><input type="text" name="ref_name[]" class="form-control" required></div>
                    <div class="form-group"><label>Company</label><input type="text" name="ref_company[]" class="form-control"></div>
                    <div class="form-group"><label>Position</label><input type="text" name="ref_position[]" class="form-control"></div>
                    <div class="form-group"><label>Contact No.</label><input type="text" name="ref_contact[]" class="form-control"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="ref_email[]" class="form-control"></div>
                </div>
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>