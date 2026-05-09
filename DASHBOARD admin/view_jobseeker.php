<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$seeker_id = $_GET['id'];

// Fetch Jobseeker Details
$stmt = $conn->prepare("
    SELECT js.*, l.barangay, l.city_municipality, l.province, 
           el.level_name, c.course_name, uc.contact_value as email
    FROM jobseekers js
    LEFT JOIN locations l ON js.location_id = l.location_id
    LEFT JOIN education_levels el ON js.education_id = el.education_id
    LEFT JOIN courses c ON js.course_id = c.course_id
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    WHERE js.seeker_id = ?
");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$seeker = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seeker) {
    die("Jobseeker not found.");
}

// Check if the applicant is Currently Underemployed (Hired but Part-time/Project-based)
$is_underemployed = false;
$underemployed_type = '';

$check_underemployed = $conn->prepare("
    SELECT jp.employment_type 
    FROM referrals_applications ra
    JOIN job_postings jp ON ra.job_id = jp.job_id
    WHERE ra.seeker_id = ? 
    AND ra.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment', 'Pending_Resignation')
    AND jp.employment_type NOT IN ('Permanent', 'Contractual')
    LIMIT 1
");
$check_underemployed->bind_param("i", $seeker_id);
$check_underemployed->execute();
$underemployed_result = $check_underemployed->get_result();

if ($underemployed_result->num_rows > 0) {
    $is_underemployed = true;
    $underemployed_type = $underemployed_result->fetch_assoc()['employment_type'];
}
$check_underemployed->close();

// Fetch Skills
$stmt = $conn->prepare("
    SELECT s.skill_name 
    FROM jobseeker_skills jss 
    JOIN skills s ON jss.skill_id = s.skill_id 
    WHERE jss.seeker_id = ?
");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$skills_result = $stmt->get_result();
$skills = [];
while($row = $skills_result->fetch_assoc()) {
    $skills[] = $row['skill_name'];
}
$stmt->close();

// Fetch Work Experience
$stmt = $conn->prepare("SELECT * FROM work_experience WHERE seeker_id = ? ORDER BY start_date DESC");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$work_exp = $stmt->get_result();
$stmt->close();

// Fetch Seminars
$stmt = $conn->prepare("SELECT * FROM seminars_trainings WHERE seeker_id = ? ORDER BY date_attended DESC");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$seminars = $stmt->get_result();
$stmt->close();

// Fetch Documents
$stmt = $conn->prepare("SELECT * FROM jobseeker_documents WHERE seeker_id = ?");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$documents = $stmt->get_result();
$stmt->close();

// Fetch Educational Background
$stmt = $conn->prepare("SELECT * FROM educational_background WHERE seeker_id = ?");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$edu_bg = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobseeker Profile - <?php echo htmlspecialchars($seeker['first_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; color: #1f2937; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        .header { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 2rem; }
        .avatar { width: 100px; height: 100px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 600; color: #6b7280; overflow: hidden; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .header-info h1 { margin: 0 0 0.5rem 0; color: #111827; }
        .header-info p { margin: 0; color: #6b7280; }
        
        .section { margin-bottom: 2.5rem; }
        .section-title { font-size: 1.25rem; font-weight: 600; color: #111827; margin-bottom: 1rem; border-bottom: 2px solid #f3f4f6; padding-bottom: 0.5rem; }
        
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .info-group label { display: block; font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem; }
        .info-group div { font-weight: 500; color: #111827; }
        
        .tag { display: inline-block; background: #eff6ff; color: #1e40af; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        
        .card { background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 1rem; }
        .card h4 { margin: 0 0 0.25rem 0; color: #1f2937; }
        .card .subtitle { color: #4b5563; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .card .meta { color: #6b7280; font-size: 0.875rem; }
        
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; text-decoration: none; background: #1e40af; color: white; font-size: 0.875rem; }
        .btn:hover { background: #1e3a8a; }
        
        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="avatar">
                <?php if (!empty($seeker['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($seeker['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($seeker['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="header-info">
                <h1 style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <?php echo htmlspecialchars($seeker['first_name'] . ' ' . $seeker['last_name']); ?>
                    <?php if ($is_underemployed): ?>
                        <span style="background-color: #fef3c7; color: #92400e; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; border: 1px solid #fde68a; display: inline-flex; align-items: center; gap: 0.25rem;" title="Currently employed as <?php echo htmlspecialchars($underemployed_type); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Underemployed (<?php echo htmlspecialchars($underemployed_type); ?>)
                        </span>
                    <?php endif; ?>
                </h1>
                <p><?php echo htmlspecialchars($seeker['email']); ?></p>
                <p><?php echo htmlspecialchars($seeker['contact_no']); ?></p>
                <p><?php echo htmlspecialchars($seeker['barangay'] . ', ' . $seeker['city_municipality'] . ', ' . $seeker['province']); ?></p>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Personal Information</h2>
            <div class="grid">
                <div class="info-group">
                    <label>Birthdate</label>
                    <div><?php echo date('F d, Y', strtotime($seeker['birthdate'])); ?> (<?php echo date_diff(date_create($seeker['birthdate']), date_create('today'))->y; ?> years old)</div>
                </div>
                <div class="info-group">
                    <label>Gender</label>
                    <div><?php echo htmlspecialchars($seeker['gender']); ?></div>
                </div>
                <div class="info-group">
                    <label>Civil Status</label>
                    <div><?php echo htmlspecialchars($seeker['civil_status']); ?></div>
                </div>
                <div class="info-group">
                    <label>Employment Status</label>
                    <div><?php echo htmlspecialchars($seeker['employment_status']); ?></div>
                </div>
                <?php if(!empty($seeker['disability'])): ?>
                <div class="info-group">
                    <label>Disability</label>
                    <div><?php echo htmlspecialchars($seeker['disability']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Education</h2>
            <div class="grid">
                <div class="info-group">
                    <label>Highest Educational Level</label>
                    <div><?php echo htmlspecialchars($seeker['level_name'] ?? 'Not specified'); ?></div>
                </div>
                <div class="info-group">
                    <label>Course / Program</label>
                    <div><?php echo htmlspecialchars($seeker['course_name'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Schools Attended</h2>
            <?php while($edu = $edu_bg->fetch_assoc()): ?>
                <div class="card">
                    <h4><?php echo htmlspecialchars($edu['school_name']); ?></h4>
                    <?php if(!empty($edu['school_year'])): ?>
                        <div class="meta">School Year: <?php echo htmlspecialchars($edu['school_year']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
            <?php if($edu_bg->num_rows == 0): ?>
                <p style="color: #6b7280;">No schools listed.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Skills</h2>
            <div>
                <?php foreach($skills as $skill): ?>
                    <span class="tag"><?php echo htmlspecialchars($skill); ?></span>
                <?php endforeach; ?>
                <?php if(empty($skills)): ?>
                    <p style="color: #6b7280;">No skills listed.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title">Work Experience</h2>
            <?php while($work = $work_exp->fetch_assoc()): ?>
                <div class="card">
                    <h4><?php echo htmlspecialchars($work['job_title']); ?></h4>
                    <div class="subtitle"><?php echo htmlspecialchars($work['company_name']); ?></div>
                    <div class="meta">
                        <?php echo date('M Y', strtotime($work['start_date'])); ?> - 
                        <?php echo $work['end_date'] ? date('M Y', strtotime($work['end_date'])) : 'Present'; ?>
                    </div>
                    <?php if(!empty($work['description'])): ?>
                        <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #4b5563;"><?php echo nl2br(htmlspecialchars($work['description'])); ?></p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
            <?php if($work_exp->num_rows == 0): ?>
                <p style="color: #6b7280;">No work experience listed.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Seminars & Trainings</h2>
            <?php while($sem = $seminars->fetch_assoc()): ?>
                <div class="card">
                    <h4><?php echo htmlspecialchars($sem['title']); ?></h4>
                    <div class="subtitle"><?php echo htmlspecialchars($sem['provider']); ?></div>
                    <div class="meta">Date: <?php echo date('F d, Y', strtotime($sem['date_attended'])); ?></div>
                </div>
            <?php endwhile; ?>
            <?php if($seminars->num_rows == 0): ?>
                <p style="color: #6b7280;">No seminars listed.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Documents</h2>
            <?php while($doc = $documents->fetch_assoc()): ?>
                <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4><?php echo htmlspecialchars($doc['doc_type']); ?></h4>
                        <div class="meta">Uploaded: <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></div>
                    </div>
                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn">View File</a>
                </div>
            <?php endwhile; ?>
            <?php if($documents->num_rows == 0): ?>
                <p style="color: #6b7280;">No documents uploaded.</p>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <button onclick="window.close()" class="btn" style="background: #6b7280;">Close Window</button>
        </div>
    </div>
</body>
</html>