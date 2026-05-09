<?php
session_start();
include '../DATABASE/db_connect.php';

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

// Fetch all resignation reports
$query = "
    SELECT rr.*, js.first_name, js.last_name, js.contact_no, uc.contact_value as email, jp.job_title, e.company_name, ra.status as app_status
    FROM resignation_reports rr
    JOIN jobseekers js ON rr.seeker_id = js.seeker_id
    LEFT JOIN user_contacts uc ON js.seeker_id = uc.user_id AND uc.contact_type = 'Email'
    JOIN referrals_applications ra ON rr.application_id = ra.application_id
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    ORDER BY rr.created_at DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resignation Reports - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; padding: 2rem; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { color: #111827; margin: 0; font-size: 1.5rem; font-weight: 700; }
        
        .btn-back { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: white; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.2s; }
        .btn-back:hover { background: #f9fafb; }

        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; font-size: 0.875rem; text-transform: uppercase; }
        tr:hover { background-color: #f9fafb; }
        
        .empty-state { text-align: center; padding: 3rem; color: #6b7280; }
        
        .remarks-box { font-size: 0.85rem; color: #4b5563; margin-top: 0.5rem; background: #f9fafb; padding: 0.75rem; border-radius: 6px; border-left: 3px solid #dc2626; line-height: 1.5; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; background: #fee2e2; color: #dc2626; }

        .btn-view { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.5rem 0.75rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: 0.2s; white-space: nowrap; }
        .btn-view:hover { background: #dbeafe; }
        .btn-success { background: #10b981; color: white; border: none; } .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; border: none; } .btn-danger:hover { background: #dc2626; }
        .btn-info { background: #3b82f6; color: white; border: none; } .btn-info:hover { background: #2563eb; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-title { font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0; }
        .close-btn { background: none; border: none; font-size: 1.75rem; cursor: pointer; color: #6b7280; line-height: 1; padding: 0; }
        .close-btn:hover { color: #111827; }
        .modal-body { margin-bottom: 1.5rem; color: #374151; font-size: 0.95rem; }
        .modal-footer { display: flex; gap: 1rem; justify-content: flex-end; border-top: 1px solid #e5e7eb; padding-top: 1.5rem; }
        .proof-preview { width: 100%; height: 350px; border: 1px solid #d1d5db; border-radius: 8px; margin-top: 0.5rem; background: #f3f4f6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .proof-preview img, .proof-preview iframe { width: 100%; height: 100%; object-fit: contain; border: none; }

        @media (max-width: 768px) {
            html { font-size: 14px; }
            .container { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Resignation & Termination Reports</h1>
                <p style="color: #6b7280; margin-top: 0.5rem; font-size: 0.9rem;">Monitor employment statuses and view attached proofs of resignation or termination.</p>
            </div>
            <a href="admin_dashboard.php#reports" class="btn-back">← Back to Dashboard</a>
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date Reported</th>
                        <th>Job Seeker</th>
                        <th>Employment Details</th>
                        <th>Reason & Remarks</th>
                        <th>Proof Document</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="color: #6b7280; font-size: 0.9rem; white-space: nowrap;"><?php echo date('M d, Y', strtotime($row['created_at'])); ?><br><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($row['contact_no'] ?? 'N/A'); ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: #1f2937; text-transform: capitalize;"><?php echo htmlspecialchars($row['job_title']); ?></div>
                                <div style="font-size: 0.85rem; color: #1e40af; text-transform: capitalize;"><?php echo htmlspecialchars($row['company_name']); ?></div>
                            </td>
                            <td>
                                <span class="status-badge"><?php echo htmlspecialchars($row['reason']); ?></span>
                                <?php if(!empty($row['remarks'])): ?>
                                    <div class="remarks-box"><?php echo nl2br(htmlspecialchars($row['remarks'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($row['proof_file'])): ?>
                                    <a href="<?php echo htmlspecialchars($row['proof_file']); ?>" target="_blank" class="btn-view">📄 View Document</a>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.85rem; font-style: italic;">No document</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['app_status'] === 'Pending_Resignation'): ?>
                                    <button type="button" class="btn-view btn-info" style="width: 100%; justify-content: center; cursor: pointer;" 
                                        data-appid="<?php echo $row['application_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>"
                                        data-job="<?php echo htmlspecialchars($row['job_title']); ?>"
                                        data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                                        data-reason="<?php echo htmlspecialchars($row['reason']); ?>"
                                        data-remarks="<?php echo htmlspecialchars($row['remarks']); ?>"
                                        data-proof="<?php echo htmlspecialchars($row['proof_file']); ?>"
                                        onclick="openReviewModal(this)">
                                        Review Report
                                    </button>
                                <?php else: ?>
                                    <span class="status-badge" style="background: #e5e7eb; color: #374151; border: 1px solid #d1d5db;"><?php echo htmlspecialchars($row['app_status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No resignation or termination reports filed yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Review Employment Report</h3>
                <button class="close-btn" onclick="closeReviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem; background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                    <div style="margin-bottom: 0.5rem;"><strong>Applicant:</strong> <span id="modalName" style="font-weight: 600; color: #111827; text-transform: capitalize;"></span></div>
                    <div><strong>Position:</strong> <span id="modalJob" style="text-transform: capitalize;"></span> at <span id="modalCompany" style="text-transform: capitalize; color: #1e40af; font-weight: 500;"></span></div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <strong style="display: block; margin-bottom: 0.25rem;">Reported Status:</strong> 
                    <span id="modalReason" class="status-badge" style="margin-left: 0; font-size: 0.85rem;"></span>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <strong style="display: block; margin-bottom: 0.25rem;">Remarks / Message:</strong>
                    <div id="modalRemarks" class="remarks-box" style="margin-top: 0; border-left-color: #f59e0b;"></div>
                </div>
                
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                        <strong>Proof Document Preview:</strong>
                        <a id="modalProofLink" href="#" target="_blank" style="font-size: 0.85rem; color: #2563eb; text-decoration: none; font-weight: 500;">Open full size ↗</a>
                    </div>
                    <div id="modalProofContainer" class="proof-preview">
                        <!-- Image or iframe injected here via JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form action="admin_verify_resignation.php" method="POST" style="margin: 0;">
                    <input type="hidden" name="app_id" id="modalAppIdReject">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-view btn-danger" onclick="return confirm('Are you sure you want to reject this report? The user will revert to Hired status.');">Reject & Discard</button>
                </form>
                <form action="admin_verify_resignation.php" method="POST" style="margin: 0;">
                    <input type="hidden" name="app_id" id="modalAppIdApprove">
                    <input type="hidden" name="reason" id="modalReasonInput">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-view btn-success" onclick="return confirm('Are you sure you want to approve this resignation report?');">Verify & Approve</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openReviewModal(btn) {
            document.getElementById('modalAppIdApprove').value = btn.getAttribute('data-appid');
            document.getElementById('modalAppIdReject').value = btn.getAttribute('data-appid');
            document.getElementById('modalReasonInput').value = btn.getAttribute('data-reason');
            
            document.getElementById('modalName').textContent = btn.getAttribute('data-name');
            document.getElementById('modalJob').textContent = btn.getAttribute('data-job');
            document.getElementById('modalCompany').textContent = btn.getAttribute('data-company');
            document.getElementById('modalReason').textContent = btn.getAttribute('data-reason');
            document.getElementById('modalRemarks').textContent = btn.getAttribute('data-remarks') || 'No remarks provided.';

            const proof = btn.getAttribute('data-proof');
            const proofContainer = document.getElementById('modalProofContainer');
            const proofLink = document.getElementById('modalProofLink');
            
            proofContainer.innerHTML = '';
            if (proof) {
                proofLink.href = proof;
                proofLink.style.display = 'inline';
                const ext = proof.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    proofContainer.innerHTML = `<img src="${proof}" alt="Proof Document">`;
                } else if (ext === 'pdf') {
                    proofContainer.innerHTML = `<iframe src="${proof}"></iframe>`;
                } else {
                    proofContainer.innerHTML = `<span style="color: #6b7280;">Preview not available. Please open in new tab.</span>`;
                }
            } else {
                proofLink.style.display = 'none';
                proofContainer.innerHTML = `<span style="color: #9ca3af; font-style: italic;">No document attached</span>`;
            }

            document.getElementById('reviewModal').style.display = 'flex';
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
            document.getElementById('modalProofContainer').innerHTML = ''; // Clean up iframe to stop resources
        }

        window.onclick = function(event) {
            const modal = document.getElementById('reviewModal');
            if (event.target == modal) {
                closeReviewModal();
            }
        }
    </script>
</body>
</html>