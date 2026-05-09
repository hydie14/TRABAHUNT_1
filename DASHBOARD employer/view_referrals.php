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

// Verify job belongs to employer
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

$referrals_stmt = $conn->prepare("
    SELECT ra.application_id, js.first_name, js.last_name, uc.contact_value as email, js.contact_no, ra.created_at as application_date, ra.status, ra.resume_file, ra.interview_date, rr.remarks, rr.proof_file
    FROM referrals_applications ra 
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id 
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    LEFT JOIN resignation_reports rr ON ra.application_id = rr.application_id
    WHERE ra.job_id = ? AND ra.status NOT IN ('Pending', 'Pending_Docs', 'Verified')
    ORDER BY ra.created_at DESC
");
$referrals_stmt->bind_param("i", $job_id);
$referrals_stmt->execute();
$referrals_result = $referrals_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Referrals</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { color: #1e40af; margin: 0; font-size: 1.5rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .status-referral { background: #dbeafe; color: #1e40af; }
        .status-hired { background: #d1fae5; color: #059669; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        
        .contact-info { font-size: 0.85rem; color: #6b7280; }
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }

        .btn-hired { background-color: #10b981; color: white; margin-right: 0.5rem; }
        .btn-hired:hover { background-color: #059669; }
        .btn-rejected { background-color: #ef4444; color: white; }
        .btn-rejected:hover { background-color: #dc2626; }
        .btn-schedule { background-color: #3b82f6; color: white; margin-right: 0.5rem; }
        .btn-schedule:hover { background-color: #2563eb; }
        .status-interview { background: #fef3c7; color: #d97706; }
        .action-group { display: flex; align-items: center; }
        .btn svg { width: 1.2em; height: 1.2em; vertical-align: text-bottom; margin-right: 0.25rem; }

        @media (max-width: 768px) {
            html { font-size: 14px; }
            h1 { font-size: 1.25rem; }
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header .btn { width: 100%; text-align: center; box-sizing: border-box; margin: 0; }
            .action-group { flex-direction: column; width: 100%; gap: 0.5rem; align-items: stretch; }
            .btn { width: 100%; text-align: center; box-sizing: border-box; margin: 0 !important; }
            table th, table td { white-space: nowrap; }
            div[style*="overflow-x: auto"] { -webkit-overflow-scrolling: touch; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Referrals for <?php echo htmlspecialchars($job['job_title']); ?></h1>
            <a href="employer_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <div style="background-color: #fff8f1; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 2rem; border-radius: 6px;">
            <h4 style="color: #92400e; margin-top: 0; margin-bottom: 0.5rem; font-size: 1rem;">How the process works:</h4>
            <p style="color: #92400e; font-size: 0.9rem; margin: 0; line-height: 1.6;">
                <strong>1.</strong> PESO issues a referral letter to the applicant.<br>
                <strong>2.</strong> The applicant will visit your office in person for an interview and present the signed <strong>physical referral letter</strong>.<br>
                <strong>3.</strong> After your interview/assessment, please log in and update their final status here to <strong>Hired</strong> or <strong>Rejected</strong>.
            </p>
        </div>

        <?php if($referrals_result->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Contact Info</th>
                        <th>Date Referred</th>
                        <th>Status</th>
                        <th>Resume</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($referral = $referrals_result->fetch_assoc()): ?>
                        <tr id="app-<?php echo $referral['application_id']; ?>">
                            <td>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']); ?></div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <?php echo htmlspecialchars($referral['email']); ?><br>
                                    <?php echo htmlspecialchars($referral['contact_no']); ?>
                                </div>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($referral['application_date'])); ?></td>
                            <td>
                                <?php 
                                    $statusClass = 'status-referral';
                                    if(strpos($referral['status'], 'Hired') !== false || $referral['status'] === 'Accepted') {
                                        $statusClass = 'status-hired';
                                    } elseif(strpos($referral['status'], 'Reject') !== false || in_array($referral['status'], ['Terminated', 'Resigned'])) {
                                        $statusClass = 'status-rejected';
                                    } elseif($referral['status'] === 'Pending Interview') {
                                        $statusClass = 'status-interview';
                                    } elseif($referral['status'] === 'For Deployment') {
                                        $statusClass = 'status-hired';
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($referral['status']); ?></span>
                                <?php if(in_array($referral['status'], ['Terminated', 'Resigned']) && !empty($referral['remarks'])): ?>
                                    <div style="font-size: 0.8rem; color: #ef4444; margin-top: 0.5rem; line-height: 1.2;">
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($referral['remarks']); ?>
                                        <?php if(!empty($referral['proof_file'])): ?>
                                            <br><a href="<?php echo htmlspecialchars($referral['proof_file']); ?>" target="_blank" style="color: #1e40af; text-decoration: underline; display: inline-block; margin-top: 4px;">📄 View Attached Proof</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($referral['resume_file'])): ?>
                                    <a href="<?php echo htmlspecialchars($referral['resume_file']); ?>" target="_blank" style="color: #1e40af; text-decoration: underline; font-size: 0.9rem;">View Resume</a>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.9rem;">No file</span>
                                <?php endif; ?>
                            </td>
                            <td>
                    <?php if(in_array($referral['status'], ['Referral_Issued', 'Issue Referral Letter', 'Pending Interview'])): ?>
                                <?php 
                                    date_default_timezone_set('Asia/Manila');
                                    $show_decisions = false;
                                    if ($referral['status'] === 'Pending Interview' && !empty($referral['interview_date'])) {
                                        if (time() >= strtotime($referral['interview_date'])) {
                                            $show_decisions = true;
                                        }
                                    }
                                ?>
                                    <div class="action-group">
                                        <?php if (!$show_decisions): ?>
                                        <button class="btn btn-schedule" onclick="openScheduleModal(<?php echo $referral['application_id']; ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg> <?php echo ($referral['status'] === 'Pending Interview') ? 'Reschedule' : 'Schedule'; ?>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($show_decisions): ?>
                                        <button class="btn btn-hired" onclick="updateStatus(<?php echo $referral['application_id']; ?>, 'Hired')">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Hired
                                        </button>
                                        <button class="btn btn-rejected" onclick="updateStatus(<?php echo $referral['application_id']; ?>, 'Rejected')">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> Reject
                                        </button>
                                        <?php else: ?>
                                            <span style="font-size: 0.8rem; color: #6b7280; font-style: italic; margin-left: 0.5rem; align-self: center;">
                                                <?php echo ($referral['status'] === 'Pending Interview') ? 'Wait until after interview schedule' : 'Please schedule an interview first'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif(in_array($referral['status'], ['Hired', 'Hired / Placed', 'For Deployment'])): ?>
                                    <div class="action-group">
                                        <button class="btn btn-primary" style="background: #8b5cf6; color: white;" onclick="openDeploymentModal(<?php echo $referral['application_id']; ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg> 
                                            <?php echo ($referral['status'] === 'For Deployment') ? 'Reschedule Deployment' : 'Schedule Deployment'; ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.85rem;">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No active referrals for this job yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; width: 100%; max-width: 400px;">
            <h3 style="margin-top: 0; color: #1f2937;">Schedule Interview</h3>
            <input type="hidden" id="sched_app_id">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Date</label>
                <input type="date" id="sched_date" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Time</label>
                <input type="time" id="sched_time" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Message / Location (Optional)</label>
                <textarea id="sched_msg" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; resize: vertical;" placeholder="e.g., Please proceed to the 2nd floor HR office."></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('scheduleModal').style.display='none'" style="padding: 0.5rem 1rem; background: #e5e7eb; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="button" onclick="sendSchedule()" class="btn btn-schedule" style="border: none; margin: 0;">Send Schedule</button>
            </div>
        </div>
    </div>

    <!-- Deployment Modal -->
    <div id="deploymentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; width: 100%; max-width: 400px;">
            <h3 style="margin-top: 0; color: #1f2937;">Schedule Deployment</h3>
            <input type="hidden" id="dep_app_id">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Date of Deployment</label>
                <input type="date" id="dep_date" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Time</label>
                <input type="time" id="dep_time" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Instructions / Location (Optional)</label>
                <textarea id="dep_msg" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; resize: vertical;" placeholder="e.g., Please bring your requirements to the main office."></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('deploymentModal').style.display='none'" style="padding: 0.5rem 1rem; background: #e5e7eb; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="button" onclick="sendDeployment()" class="btn btn-primary" style="background: #8b5cf6; border: none; margin: 0;">Schedule</button>
            </div>
        </div>
    </div>

    <script>
        function openScheduleModal(appId) {
            document.getElementById('sched_app_id').value = appId;
            document.getElementById('scheduleModal').style.display = 'flex';
        }
        function sendSchedule() {
            const appId = document.getElementById('sched_app_id').value;
            const date = document.getElementById('sched_date').value;
            const time = document.getElementById('sched_time').value;
            const msg = document.getElementById('sched_msg').value;
            if(!date || !time) { alert("Please select both date and time."); return; }

            const selectedDateTime = new Date(date + 'T' + time);
            if (selectedDateTime < new Date()) {
                alert("Error: The selected time has already passed. Please choose a future schedule.");
                return;
            }

            const btn = document.querySelector('#scheduleModal .btn-schedule');
            const originalText = btn.innerText;
            btn.innerText = "Sending...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append('application_id', appId); formData.append('date', date);
            formData.append('time', time); formData.append('message', msg);

            fetch('schedule_interview.php', { method: 'POST', body: formData })
            .then(r => r.text()).then(data => { alert(data); location.reload(); }).catch(e => { alert('Error.'); btn.innerText = originalText; btn.disabled = false; });
        }

        function openDeploymentModal(appId) {
            document.getElementById('dep_app_id').value = appId;
            document.getElementById('deploymentModal').style.display = 'flex';
        }
        function sendDeployment() {
            const appId = document.getElementById('dep_app_id').value;
            const date = document.getElementById('dep_date').value;
            const time = document.getElementById('dep_time').value;
            const msg = document.getElementById('dep_msg').value;
            if(!date || !time) { alert("Please select both date and time."); return; }
            
            const selectedDateTime = new Date(date + 'T' + time);
            if (selectedDateTime < new Date()) {
                alert("Error: The selected time has already passed. Please choose a future schedule.");
                return;
            }

            const btn = document.querySelector('#deploymentModal .btn-primary');
            const originalText = btn.innerText;
            btn.innerText = "Processing...";
            btn.disabled = true;

            const formData = new FormData();
            formData.append('application_id', appId); formData.append('date', date); formData.append('time', time); formData.append('message', msg);
            fetch('schedule_deployment.php', { method: 'POST', body: formData }).then(r => r.text()).then(data => { alert(data); location.reload(); }).catch(e => { alert('Error.'); btn.innerText = originalText; btn.disabled = false; });
        }

        function updateStatus(appId, status) {
            if(!confirm(`Are you sure you want to mark this applicant as ${status}?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('application_id', appId);
            formData.append('status', status);

            fetch('update_application_status.php', {
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
                alert('An error occurred while updating status.');
            });
        }
    </script>
</body>
</html>
