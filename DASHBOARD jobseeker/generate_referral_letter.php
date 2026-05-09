<?php
session_start();
include '../DATABASE/db_connect.php';

// Ensure Job Seeker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'JobSeeker') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch referral details
$query = "
    SELECT ra.created_at as referral_date, 
           js.first_name, js.last_name, js.street_address, l_js.barangay as js_brgy, l_js.city_municipality as js_city,
           jp.job_title, 
           e.company_name, e.contact_person_name, e.street_address as emp_address, l_emp.barangay as emp_brgy, l_emp.city_municipality as emp_city
    FROM referrals_applications ra
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN locations l_js ON js.location_id = l_js.location_id
    LEFT JOIN locations l_emp ON e.location_id = l_emp.location_id
    WHERE ra.application_id = ? AND ra.seeker_id = ? AND ra.status IN ('Referral_Issued', 'Pending Interview', 'Hired', 'Hired / Placed')
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $app_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Referral letter not found or not yet issued.");
}

$data = $result->fetch_assoc();
$date = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Letter - <?php echo htmlspecialchars($data['first_name']); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Times New Roman', serif; background: #525659; padding: 2rem; display: flex; justify-content: center; }
        .letter-container { background: white; width: 8.5in; min-height: 11in; padding: 1in; box-shadow: 0 4px 8px rgba(0,0,0,0.2); position: relative; }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #000; padding-bottom: 1rem; }
        .header img { height: 80px; vertical-align: middle; }
        .header-text { display: inline-block; vertical-align: middle; margin-left: 1rem; text-align: center; }
        .header-text h2 { margin: 0; font-size: 14pt; text-transform: uppercase; }
        .header-text p { margin: 0; font-size: 10pt; }
        
        .content { font-size: 12pt; line-height: 1.6; text-align: justify; }
        .date { text-align: right; margin-bottom: 2rem; }
        .recipient { margin-bottom: 1.5rem; }
        .subject { font-weight: bold; margin-bottom: 1.5rem; text-align: center; text-transform: uppercase; }
        
        .signature-section { margin-top: 3rem; }
        .signature-block { float: right; width: 250px; text-align: center; }
        .signature-line { border-top: 1px solid #000; margin-top: 3rem; margin-bottom: 0.25rem; }
        
        .footer { margin-top: 4rem; font-size: 9pt; text-align: center; color: #555; border-top: 1px solid #ccc; padding-top: 1rem; }
        
        .btn-download {
            position: fixed; top: 20px; right: 20px;
            background: #1e40af; color: white; padding: 10px 20px;
            border: none; border-radius: 5px; cursor: pointer;
            font-family: sans-serif; font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-download:hover { background: #1d4ed8; }
        
        @media print {
            body { background: white; padding: 0; }
            .letter-container { box-shadow: none; width: 100%; height: auto; padding: 0; }
            .btn-download { display: none; }
        }
        
        .instruction-box {
            background-color: #fff3cd;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin-bottom: 2rem;
            font-family: sans-serif;
            color: #92400e;
            font-size: 11pt;
        }
    </style>
</head>
<body>
    <button class="btn-download" onclick="downloadPDF()">📥 Download PDF</button>

    <div class="letter-container" id="letter">
        <div class="instruction-box" data-html2canvas-ignore="true">
            <strong>⚠️ NEXT STEP:</strong> Please print this referral letter and visit the PESO Bongabon office to get the <strong>physical signature</strong> of the PESO Manager. This document is not valid for your interview without the official signature.
        </div>

        <div class="header">
            <!-- Replace with actual logo path -->
            <img src="../BONGABON.png" alt="PESO Logo"> 
            <div class="header-text">
                <h2>Public Employment Service Office</h2>
                <p>Municipality of Bongabon</p>
                <p>Province of Nueva Ecija</p>
            </div>
        </div>

        <div class="content">
            <div class="date"><?php echo $date; ?></div>

            <div class="recipient">
                <strong><?php echo htmlspecialchars($data['contact_person_name'] ?? 'The Manager'); ?></strong><br>
                <?php echo htmlspecialchars($data['company_name']); ?><br>
                <?php echo htmlspecialchars($data['emp_address'] . ', ' . $data['emp_brgy'] . ', ' . $data['emp_city']); ?>
            </div>

            <div class="subject">REFERRAL LETTER</div>

            <p>Sir/Madam:</p>

            <p>We are pleased to refer to you <strong>Mr./Ms. <?php echo htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></strong>, 
            a resident of <?php echo htmlspecialchars($data['street_address'] . ', ' . $data['js_brgy'] . ', ' . $data['js_city']); ?>, 
            who is applying for the position of <strong><?php echo htmlspecialchars($data['job_title']); ?></strong> in your company.</p>

            <p>This referral is part of our employment facilitation services to assist job seekers in finding employment opportunities. 
            We have verified the applicant's basic documentary requirements.</p>

            <p>We would appreciate your feedback regarding the outcome of this referral. Please fill out the attached feedback slip (if any) 
            or update the status in the PESO System.</p>

            <p>Thank you for your continued partnership.</p>

            <div class="signature-section">
                <div class="signature-block">
                    <p>Very truly yours,</p>
                    <div class="signature-line"></div>
                    <strong>PESO MANAGER</strong><br>
                    Public Employment Service Office
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>

        <div class="footer">
            <p>PESO Bongabon System Generated Document | <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('letter');
            const opt = {
                margin: 0.5,
                filename: 'Referral_Letter_<?php echo htmlspecialchars($data['last_name']); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>