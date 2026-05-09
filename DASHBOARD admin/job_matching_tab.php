<?php
session_start();
include '../DATABASE/db_connect.php';

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

// Handle Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';

$where_clause = "";
if ($filter == 'pending') {
    $where_clause = "WHERE ra.status IN ('Pending_Docs', 'Verified', 'Issue Referral Letter')";
} elseif ($filter == 'referred') {
    $where_clause = "WHERE ra.status IN ('Referral_Issued')";
} elseif ($filter == 'rejected') {
    $where_clause = "WHERE ra.status IN ('Rejected', 'Rejected / Not Qualified')";
} elseif ($filter == 'all') {
    $where_clause = "WHERE 1=1"; // Show all
}

$query = "
    SELECT ra.application_id, ra.seeker_id, js.first_name, js.last_name, js.is_verified,
           jp.job_title, e.company_name, ra.created_at, ra.resume_file, ra.status,
           el.level_name, c.course_name,
           IF(jp.course_id IS NOT NULL AND jp.course_id = js.course_id, 1, 0) as course_match,
           IF(jp.education_id IS NOT NULL AND jp.education_id = js.education_id, 1, 0) as edu_match,
           (SELECT COUNT(*) FROM job_skills jps JOIN jobseeker_skills jss ON jps.skill_id = jss.skill_id WHERE jps.job_id = jp.job_id AND jss.seeker_id = ra.seeker_id) as skill_match,
           (SELECT jp2.employment_type 
            FROM referrals_applications ra2 
            JOIN job_postings jp2 ON ra2.job_id = jp2.job_id 
            WHERE ra2.seeker_id = ra.seeker_id 
            AND ra2.status IN ('Hired', 'Hired / Placed', 'Accepted', 'For Deployment', 'Pending_Resignation') 
            AND jp2.employment_type NOT IN ('Permanent', 'Contractual') LIMIT 1) as underemployed_type
    FROM referrals_applications ra
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN education_levels el ON js.education_id = el.education_id
    LEFT JOIN courses c ON js.course_id = c.course_id
    $where_clause
    ORDER BY (course_match + edu_match + IF(skill_match > 0, 1, 0)) DESC, ra.created_at ASC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Matching - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #111827; margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: 700; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; font-size: 0.875rem; }
        .btn-issue { background-color: #059669; color: white; }
        .btn-issue:hover { background-color: #047857; }
        .btn-verify { background-color: #1e40af; color: white; }
        .btn-verify:hover { background-color: #1e3a8a; }
        .btn-notify { background-color: #f59e0b; color: white; margin-right: 0.5rem; }
        .btn-notify:hover { background-color: #d97706; }
        .btn-view { background-color: #e5e7eb; color: #374151; margin-right: 0.5rem; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .btn-back:hover { background: #f9fafb; }
        
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
        .form-control { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; }
        .modal-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
        .btn-secondary { background-color: #e5e7eb; color: #374151; }
        .btn-primary { background-color: #1e40af; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <div>
                <h1>Job Matching & Verification</h1>
                <p style="color: #6b7280;">Manage applicant document verification and issue referral letters.</p>
            </div>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="font-weight: 500; margin-right: 0.5rem;">Filter Status:</label>
            <select onchange="window.location.href='?filter='+this.value" style="padding: 0.5rem; border-radius: 6px; border: 1px solid #d1d5db;">
                <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending & Verified (To-Do)</option>
                <option value="referred" <?php echo $filter == 'referred' ? 'selected' : ''; ?>>Referral Issued</option>
                <option value="rejected" <?php echo $filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>Show All</option>
            </select>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Qualifications</th>
                        <th>Job Applied For</th>
                        <th>Company</th>
                        <th>Date Applied</th>
                        <th>Status</th>
                        <th>Profile</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr id="row-<?php echo $row['application_id']; ?>">
                        <td>
                            <div style="text-transform: capitalize; font-weight: 500; color: #111827;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                            <?php if(!empty($row['underemployed_type'])): ?>
                                <span style="background-color: #fef3c7; color: #92400e; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.65rem; font-weight: 600; border: 1px solid #fde68a; display: inline-flex; align-items: center; gap: 0.15rem; margin-top: 0.25rem;" title="Currently employed as <?php echo htmlspecialchars($row['underemployed_type']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 0.75rem; height: 0.75rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    Underemployed (<?php echo htmlspecialchars($row['underemployed_type']); ?>)
                                </span>
                            <?php endif; ?>
                        </td>
                            <td>
                                <div style="font-size: 0.85rem; color: #1f2937; font-weight: 500;">
                                    <?php echo htmlspecialchars($row['level_name'] ?? 'N/A'); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #6b7280;">
                                    <?php echo htmlspecialchars($row['course_name'] ?? ''); ?>
                                </div>
                            </td>
                            <td>
                                <div style="text-transform: capitalize;"><?php echo htmlspecialchars($row['job_title']); ?></div>
                                <?php if($row['course_match'] || $row['edu_match'] || $row['skill_match'] > 0): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 0.5rem;">
                                    <?php if($row['course_match']): ?>
                                        <span style="background: #1e40af; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 600;">Course Match</span>
                                    <?php endif; ?>
                                    <?php if($row['edu_match']): ?>
                                        <span style="background: #3b82f6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 600;">Edu Match</span>
                                    <?php endif; ?>
                                    <?php if($row['skill_match'] > 0): ?>
                                        <span style="background: #059669; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 600;">Skills Match (<?php echo $row['skill_match']; ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-transform: capitalize;"><?php echo htmlspecialchars($row['company_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <a href="view_jobseeker.php?id=<?php echo $row['seeker_id']; ?>" target="_blank" class="btn btn-view" style="margin: 0; text-align: center; white-space: nowrap;">View Profile</a>
                                    <?php if(!empty($row['resume_file'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['resume_file']); ?>" target="_blank" class="btn btn-view" style="margin: 0; text-align: center; white-space: nowrap;">View Resume</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if($row['status'] == 'Pending_Docs' || $row['status'] == 'Pending'): ?>
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <?php if ($row['is_verified'] == 0): ?>
                                            <button type="button" class="btn btn-notify" style="margin: 0; padding: 0.4rem; font-size: 0.8rem;" onclick="openNotifyModal(<?php echo $row['seeker_id']; ?>, <?php echo $row['application_id']; ?>)">1. Notify & Schedule</button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-verify" style="margin: 0; padding: 0.4rem; font-size: 0.8rem;" onclick="verifyApplicant(<?php echo $row['application_id']; ?>, <?php echo $row['seeker_id']; ?>, this)"><?php echo ($row['is_verified'] == 0) ? '2. Verify Docs (Done)' : 'Approve Match'; ?></button>
                                    </div>
                                <?php else: ?>
                                    <?php
                                        // Check for OTHER Active Referral OR Active Employment
                                        // Restrict if they have a 'Permanent' or 'Contractual' job.
                                        // Allow up to 2 active 'Part-time' / 'Project-based' jobs.
                                        $check_restriction_stmt = $conn->prepare("
                                            SELECT ra.status, jp.employment_type 
                                            FROM referrals_applications ra 
                                            JOIN job_postings jp ON ra.job_id = jp.job_id 
                                            WHERE ra.seeker_id = ? AND ra.application_id != ? 
                                            AND ra.status IN ('Referral_Issued', 'Issue Referral Letter', 'Hired', 'Hired / Placed', 'Accepted', 'For Deployment') 
                                        ");
                                        $check_restriction_stmt->bind_param("ii", $row['seeker_id'], $row['application_id']);
                                        $check_restriction_stmt->execute();
                                        $restriction_result = $check_restriction_stmt->get_result();
                                        
                                        $has_fulltime = false;
                                        $fulltime_status = '';
                                        $parttime_count = 0;
                                        
                                        while ($r = $restriction_result->fetch_assoc()) {
                                            if (in_array($r['employment_type'], ['Permanent', 'Contractual'])) {
                                                $has_fulltime = true;
                                                $fulltime_status = $r['status'];
                                            } else {
                                                $parttime_count++;
                                            }
                                        }
                                        $check_restriction_stmt->close();
                                    ?>
                                    <?php if ($row['status'] == 'Verified' || $row['status'] == 'Issue Referral Letter'): ?>
                                        <?php if ($has_fulltime): ?>
                                            <?php if (in_array($fulltime_status, ['Referral_Issued', 'Issue Referral Letter'])): ?>
                                            <span style="color: #f59e0b; font-size: 0.8rem; font-style: italic;">Has active full-time referral</span>
                                            <?php else: ?>
                                            <span style="color: #10b981; font-size: 0.8rem; font-style: italic;">Currently Employed (Full-time)</span>
                                            <?php endif; ?>
                                        <?php elseif ($parttime_count >= 2): ?>
                                            <span style="color: #ef4444; font-size: 0.8rem; font-style: italic; font-weight: 500;">Max limit (2) part-time jobs reached</span>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-issue" onclick="openIssueModal(<?php echo $row['application_id']; ?>)">Issue Referral</button>
                                        <?php endif; ?>
                                    <?php elseif ($row['status'] == 'Referral_Issued'): ?>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <span style="color: #059669; font-size: 0.875rem; font-weight: 600;">Referral Issued</span>
                                            <a href="print_referral.php?id=<?php echo $row['application_id']; ?>" target="_blank" class="btn btn-view" style="margin: 0; text-align: center; color: #1e40af; border: 1px solid #1e40af; background: white; padding: 0.25rem 0.5rem;">🖨️ Print Letter</a>
                                        </div>
                                    <?php elseif ($row['status'] == 'Pending Interview'): ?>
                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <span style="color: #d97706; font-size: 0.875rem; font-weight: 600;">Interview Scheduled</span>
                                            <a href="print_referral.php?id=<?php echo $row['application_id']; ?>" target="_blank" class="btn btn-view" style="margin: 0; text-align: center; color: #1e40af; border: 1px solid #1e40af; background: white; padding: 0.25rem 0.5rem;">🖨️ Print Letter</a>
                                        </div>
                                    <?php else: ?>
                                    <span style="color: #6b7280; font-size: 0.875rem; font-style: italic;">
                                        <?php 
                                            if (strpos($row['status'], 'Reject') !== false) echo "Application Rejected";
                                            elseif (strpos($row['status'], 'Hired') !== false || $row['status'] === 'Accepted') echo "Applicant Hired";
                                            else echo "No actions";
                                        ?>
                                    </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No applications found for this filter.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Issue Referral & Schedule Modal -->
    <div id="issueModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0; margin-bottom: 0.5rem; color: #1f2937;">Issue Referral</h3>
            <p style="color: #6b7280; font-size: 0.85rem; margin-bottom: 1.5rem;">Generate the referral letter now, or optionally require a schedule if the manager is unavailable to sign today.</p>
            <input type="hidden" id="issue_app_id">
            
            <div style="margin-bottom: 1rem; padding: 1rem; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500; color: #1e40af; cursor: pointer;">
                    <input type="checkbox" id="require_schedule" onchange="toggleScheduleFields(this.checked)"> 
                    Require Applicant to Return Later (Set Schedule)
                </label>
            </div>

            <div id="schedule_fields" style="display: none;">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="issue_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="time" id="issue_time" class="form-control">
                </div>
            </div>
            <div class="modal-actions">
                <button onclick="closeIssueModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="sendIssueReferral()" class="btn btn-primary">Issue Referral</button>
            </div>
        </div>
    </div>

    <!-- Notify Verification Modal -->
    <div id="notifyModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0; margin-bottom: 0.5rem; color: #1f2937;">Schedule Document Verification</h3>
            <p style="color: #6b7280; font-size: 0.85rem; margin-bottom: 1.5rem;">Set a date and time for the applicant to submit their physical requirements to the PESO office.</p>
            <input type="hidden" id="notify_seeker_id">
            <input type="hidden" id="notify_app_id">
            <div class="form-group">
                <label>Date</label>
                <input type="date" id="notify_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Time</label>
                <input type="time" id="notify_time" class="form-control">
            </div>
            <div class="modal-actions">
                <button onclick="closeNotifyModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="sendNotifyVerification()" class="btn btn-primary">Send Notification</button>
            </div>
        </div>
    </div>

    <script>
        function openNotifyModal(seekerId, appId) {
            document.getElementById('notify_seeker_id').value = seekerId;
            document.getElementById('notify_app_id').value = appId;
            document.getElementById('notifyModal').style.display = 'flex';
        }

        function closeNotifyModal() {
            document.getElementById('notifyModal').style.display = 'none';
        }

        function sendNotifyVerification() {
            const seekerId = document.getElementById('notify_seeker_id').value;
            const appId = document.getElementById('notify_app_id').value;
            const date = document.getElementById('notify_date').value;
            const time = document.getElementById('notify_time').value;

            if(!date || !time) {
                alert("Please select both date and time.");
                return;
            }

            const selectedDateTime = new Date(date + 'T' + time);
            if (selectedDateTime < new Date()) {
                alert("Error: The selected time has already passed. Please choose a future schedule.");
                return;
            }

            const btn = document.querySelector('#notifyModal .btn-primary');
            const originalText = btn.innerText;
            btn.innerText = "Sending...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append('seeker_id', seekerId);
            formData.append('application_id', appId);
            formData.append('date', date);
            formData.append('time', time);

            fetch('notify_verification.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                closeNotifyModal();
                btn.innerText = originalText;
                btn.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }

        function openIssueModal(appId) {
            document.getElementById('issue_app_id').value = appId;
            document.getElementById('issueModal').style.display = 'flex';
        }

        function closeIssueModal() {
            document.getElementById('issueModal').style.display = 'none';
        }

        function toggleScheduleFields(isChecked) {
            document.getElementById('schedule_fields').style.display = isChecked ? 'block' : 'none';
        }

        function sendIssueReferral() {
            const appId = document.getElementById('issue_app_id').value;
            const requireSchedule = document.getElementById('require_schedule').checked;
            const date = document.getElementById('issue_date').value;
            const time = document.getElementById('issue_time').value;

            if(requireSchedule && (!date || !time)) {
                alert("Please select both date and time for the schedule.");
                return;
            }

            if (requireSchedule) {
                const selectedDateTime = new Date(date + 'T' + time);
                if (selectedDateTime < new Date()) {
                    alert("Error: The selected time has already passed. Please choose a future schedule.");
                    return;
                }
            }

            const btn = document.querySelector('#issueModal .btn-primary');
            const originalText = btn.innerText;
            btn.innerText = "Processing...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append('application_id', appId);
            if (requireSchedule) {
                formData.append('date', date);
                formData.append('time', time);
            }

            fetch('issue_referral.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }

        function verifyApplicant(appId, seekerId, btn) {
            if(!confirm("Are you sure you want to verify and approve this specific application?")) {
                return;
            }

            const originalText = btn.innerText;
            btn.innerText = "Verifying...";
            btn.disabled = true;
            btn.style.opacity = "0.7";

            const formData = new FormData();
            formData.append('application_id', appId);
            formData.append('seeker_id', seekerId);

            fetch('verify_applicant.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Server response:", text);
                    throw new Error("Invalid server response. Check console.");
                }
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                    btn.innerText = originalText;
                    btn.disabled = false;
                    btn.style.opacity = "1";
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message);
                btn.innerText = originalText;
                btn.disabled = false;
                btn.style.opacity = "1";
            });
        }
    </script>
</body>
</html>