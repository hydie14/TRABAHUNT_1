<?php
session_start();
include '../DATABASE/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employer') {
    header("Location: ../LOGIN SIGNUP/new_login.php");
    exit();
}

if (!isset($_GET['job_id']) || !filter_var($_GET['job_id'], FILTER_VALIDATE_INT)) {
    header("Location: employer_dashboard.php");
    exit();
}

$job_id = (int)$_GET['job_id'];
$employer_id = $_SESSION['user_id'];

// Fetch job details with joins for readable names
$stmt = $conn->prepare("
    SELECT jp.*, el.level_name, c.course_name 
    FROM job_postings jp 
    LEFT JOIN education_levels el ON jp.education_id = el.education_id 
    LEFT JOIN courses c ON jp.course_id = c.course_id 
    WHERE jp.job_id = ? AND jp.employer_id = ?
");
$stmt->bind_param("ii", $job_id, $employer_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: employer_dashboard.php");
    exit();
}

// Fetch disabilities
$stmt_dis = $conn->prepare("SELECT * FROM job_posting_disabilities WHERE job_id = ?");
$stmt_dis->bind_param("i", $job_id);
$stmt_dis->execute();
$dis_result = $stmt_dis->get_result();
$disabilities = [];
while($row = $dis_result->fetch_assoc()) {
    $disabilities[] = $row['disability_type'] . ($row['other_description'] ? ': ' . $row['other_description'] : '');
}
$stmt_dis->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Job - <?php echo htmlspecialchars($job['job_title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 1rem; }
        h1 { color: #1e40af; margin: 0; font-size: 1.75rem; }
        .section-title { font-size: 1.1rem; color: #1e40af; margin: 1.5rem 0 1rem; font-weight: 600; }
        .detail-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1rem; }
        .detail-group label { display: block; color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .detail-group div { color: #1f2937; font-weight: 500; font-size: 1rem; }
        .description-box { background: #f9fafb; padding: 1.5rem; border-radius: 8px; border: 1px solid #e5e7eb; white-space: pre-wrap; line-height: 1.5; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #dbeafe; color: #1e40af; margin-right: 0.5rem; margin-top: 0.25rem; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-secondary { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        .btn-secondary:hover { background: #e5e7eb; color: #1f2937; }
        .btn-primary { background: #1e40af; color: white; margin-left: 0.5rem; }
        .btn-primary:hover { background: #1e3a8a; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .container { padding: 1.5rem; }
            .header-flex { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-flex div { width: 100%; display: flex; flex-direction: column; gap: 0.5rem; }
            .detail-row { grid-template-columns: 1fr; gap: 1rem; }
            .btn { width: 100%; text-align: center; box-sizing: border-box; margin: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h1><?php echo htmlspecialchars($job['job_title']); ?></h1>
            <div>
                <a href="employer_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="edit_post.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary">Edit Job</a>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-group">
                <label>Status</label>
                <div><?php echo htmlspecialchars($job['status']); ?></div>
            </div>
            <div class="detail-group">
                <label>Date Posted</label>
                <div><?php echo date('F d, Y', strtotime($job['posting_date'])); ?></div>
            </div>
        </div>

        <h3 class="section-title">Vacancy Details</h3>
        <div class="detail-row">
            <div class="detail-group">
                <label>Nature of Work</label>
                <div><?php echo htmlspecialchars($job['employment_type']); ?></div>
            </div>
            <div class="detail-group">
                <label>Place of Work</label>
                <div><?php echo htmlspecialchars($job['place_of_work']); ?></div>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-group">
                <label>Salary Range</label>
                <div>
                    <?php 
                    if ($job['salary_min'] && $job['salary_max']) {
                        echo '₱' . number_format($job['salary_min'], 2) . ' - ₱' . number_format($job['salary_max'], 2);
                    } elseif ($job['salary_min']) {
                        echo '₱' . number_format($job['salary_min'], 2) . '+';
                    } else {
                        echo 'Not specified';
                    }
                    ?>
                </div>
            </div>
            <div class="detail-group">
                <label>Vacancies</label>
                <div><?php echo htmlspecialchars($job['vacancies_count']); ?></div>
            </div>
        </div>
        
        <div class="detail-group" style="margin-bottom: 1.5rem;">
            <label>Job Description</label>
            <div class="description-box"><?php echo htmlspecialchars($job['description']); ?></div>
        </div>

        <h3 class="section-title">Qualification Requirements</h3>
        <div class="detail-row">
            <div class="detail-group">
                <label>Education Level</label>
                <div><?php echo htmlspecialchars($job['level_name'] ?? 'Not specified'); ?></div>
            </div>
            <div class="detail-group">
                <label>Course/Strand</label>
                <div><?php echo htmlspecialchars($job['course_name'] ?? 'Not specified'); ?></div>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-group">
                <label>Work Experience</label>
                <div><?php echo htmlspecialchars($job['experience_required'] ?? 'None'); ?></div>
            </div>
        </div>
        
        <?php if($job['other_qualifications']): ?>
        <div class="detail-group" style="margin-bottom: 1.5rem;">
            <label>Other Qualifications</label>
            <div class="description-box"><?php echo htmlspecialchars($job['other_qualifications']); ?></div>
        </div>
        <?php endif; ?>

        <div class="detail-row">
            <div class="detail-group">
                <label>Accepts PWD?</label>
                <div>
                    <?php echo $job['accepts_pwd'] ? 'Yes' : 'No'; ?>
                    <?php if($job['accepts_pwd'] && !empty($disabilities)): ?>
                        <br>
                        <?php foreach($disabilities as $dis): ?>
                            <span class="badge"><?php echo htmlspecialchars($dis); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-group">
                <label>Accepts Returning OFWs?</label>
                <div><?php echo $job['accepts_returning_ofws'] ? 'Yes' : 'No'; ?></div>
            </div>
        </div>

        <h3 class="section-title">Posting Details</h3>
        <div class="detail-row">
            <div class="detail-group">
                <label>Valid Until</label>
                <div><?php echo $job['valid_until'] ? date('F d, Y', strtotime($job['valid_until'])) : 'Indefinite'; ?></div>
            </div>
        </div>
    </div>
</body>
</html>