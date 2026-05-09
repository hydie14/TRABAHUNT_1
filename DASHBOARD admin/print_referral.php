<?php
session_start();
include '../DATABASE/db_connect.php';

// Ensure Admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../LOGIN%20SIGNUP/new_login.php");
    exit();
}

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch referral details
$query = "
    SELECT ra.created_at as referral_date, 
           js.first_name, js.last_name, js.street_address, js.gender, js.civil_status, l_js.barangay as js_brgy, l_js.city_municipality as js_city,
           jp.job_title, 
           e.company_name, e.contact_person_name, e.office_type, e.street_address as emp_address, l_emp.barangay as emp_brgy, l_emp.city_municipality as emp_city
    FROM referrals_applications ra
    JOIN jobseekers js ON ra.seeker_id = js.seeker_id
    JOIN job_postings jp ON ra.job_id = jp.job_id
    JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN locations l_js ON js.location_id = l_js.location_id
    LEFT JOIN locations l_emp ON e.location_id = l_emp.location_id
    WHERE ra.application_id = ? AND ra.status IN ('Referral_Issued', 'Pending Interview', 'Hired', 'Hired / Placed')
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Referral letter not found or not yet issued.");
}

$data = $result->fetch_assoc();
$date = date('F d, Y', strtotime($data['referral_date']));

$title = '';
$pronoun_obj = '';
$pronoun_pos = '';
if ($data['gender'] === 'Male') {
    $title = 'Mr.';
    $pronoun_obj = 'him';
    $pronoun_pos = 'his';
} else {
    $title = ($data['civil_status'] === 'Married') ? 'Mrs.' : 'Ms.';
    $pronoun_obj = 'her';
    $pronoun_pos = 'her';
}

// Calculate referral sequence number for the month
$month = date('n', strtotime($data['referral_date']));
$year = date('Y', strtotime($data['referral_date']));

$count_query = "
    SELECT COUNT(*) as current_count
    FROM referrals_applications 
    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? AND application_id <= ? AND status IN ('Referral_Issued', 'Pending Interview', 'Hired', 'Hired / Placed', 'Accepted')
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("iii", $month, $year, $app_id);
$count_stmt->execute();
$count_data = $count_stmt->get_result()->fetch_assoc();
$sequence_number = str_pad($count_data['current_count'], 3, '0', STR_PAD_LEFT);
$count_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Referral Letter - <?php echo htmlspecialchars($data['first_name']); ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; background: #525659; padding: 2rem; display: flex; justify-content: center; }
        .letter-container { background: white; width: 8.5in; min-height: 11in; padding: 1in; box-shadow: 0 4px 8px rgba(0,0,0,0.2); position: relative; }
        .header { text-align: center; margin-bottom: 2rem; border-bottom: 5px solid #ff0000; padding-bottom: 1rem; }
        .header img { height: 65px; vertical-align: middle; }
        .header-text { display: inline-block; vertical-align: middle; margin-left: 1rem; text-align: center; }
        .header-text h2 { margin: 0; font-size: 14pt; text-transform: uppercase; }
        .header-text p { margin: 0; font-size: 10pt; }
        
        .content { font-size: 12pt; line-height: 1.6; text-align: justify; }
        .date { text-align: right; margin-bottom: 2rem; }
        .recipient { margin-bottom: 1.5rem; }
        .subject { font-weight: bold; margin-bottom: 1.5rem; }
        
        .signature-section { margin-top: 3rem; }
        .signature-block { float: right; width: 250px; text-align: center; }
        .signature-line { border-top: 1px solid #000; margin-top: 3rem; margin-bottom: 0.25rem; }
        
        .footer { margin-top: 4rem; font-size: 9pt; text-align: center; color: #555; border-top: 1px solid #ccc; padding-top: 1rem; }
        
        .btn-print {
            position: fixed; top: 20px; right: 20px;
            background: #1e40af; color: white; padding: 10px 20px;
            border: none; border-radius: 5px; cursor: pointer;
            font-family: sans-serif; font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-print:hover { background: #1d4ed8; }
        
        .instruction-box { background-color: #e0e7ff; border-left: 4px solid #4338ca; padding: 1rem; margin-bottom: 2rem; font-family: sans-serif; color: #312e81; font-size: 11pt; }
        
        @media print {
            body { background: white; padding: 0; }
            .letter-container { box-shadow: none; width: 100%; height: auto; padding: 0; margin: 0; border: none; }
            .btn-print, .instruction-box { display: none !important; }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">🖨️ Print Letter</button>

    <div class="letter-container" id="letter">
        <div class="instruction-box">
            <strong>📋 ADMIN INSTRUCTION:</strong> Please print this referral letter, have it signed by the PESO Manager, and hand it to the applicant. <i>(This blue box will not appear in the printed copy.)</i>
        </div>

        <div class="header">
            <img src="bongabon municipal logo.png" alt="Bongabon municipal Logo" onerror="this.style.display='none'"> 
            <img src="Bagong_Pilipinas_logo.png" alt="Bagong Pilipinas Logo" onerror="this.style.display='none'"> 
            <div class="header-text">
                <p>Republic of the Philippines</p>
                <p>Province of Nueva Ecija</p>
                <p>Municipality of Bongabon</p>
                <p>-oOo-</p>
                <h2><b>PUBLIC EMPLOYMENT SERVICE OFFICE</b></h2>
            <p><B>pesobongabon@gmail.com / lorelienery@yahoo.com</B></p>

            </div>
            <img src="DOLE LOGO.png" alt="DOLE Logo" onerror="this.style.display='none'"> 
            <img src="Peso-1.png" alt="Peso Logo" onerror="this.style.display='none'"> 
        </div>

        <div class="content">
            <div class="date"><?php echo $date; ?></div>
            <div class="recipient">
                <strong><?php echo htmlspecialchars($data['company_name']); ?></strong><br>
                <?php if (!empty($data['office_type'])) echo htmlspecialchars($data['emp_city'] . ' ' . $data['office_type']) . '<br>'; ?>
                <?php echo htmlspecialchars( $data['emp_brgy'] . ', ' . $data['emp_city'] . ', Nueva Ecija'); ?><br><br>
                Thru: <strong><?php echo !empty($data['office_type']) ? htmlspecialchars($data['office_type']) : 'Branch'; ?> Manager</strong>
            </div>
            
            <div class="subject" style="display: flex;">
                <span>Subject:</span>
                <span style="flex: 1; text-align: center;">Referral Letter for <?php echo $title . ' ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></span>
            </div>
            
            <p>Dear Ma'am/Sir,</p>
            <p style="text-indent: 50px;">I am pleased to formally refer <strong><?php echo $title . ' ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></strong> for employment with your esteemed company. As the PESO Manager of LGU Bongabon, I have had the opportunity to assess <?php echo $pronoun_pos; ?> qualifications, and I confidently recommend <?php echo $pronoun_obj; ?> as a competent and capable candidate for your available position. Should you require further information, kindly see attached copy of <?php echo $pronoun_pos; ?> resume and reach out. We greatly appreciate your consideration of <strong><?php echo $title . ' ' . htmlspecialchars($data['last_name']); ?></strong> and hope for a successful partnership in securing meaningful employment opportunities.</p>
            
            <div class="signature-section">
                <p>Respectfully,</p>
                <br><br>
                <div>
                    <strong>LORELIE F. NERY</strong><br>
                    PESO Manager
                </div>
            </div>
            <div style="clear: both;"></div>

            <div style="margin-top: 3rem; font-size: 11pt; font-weight: bold; color: #999999;">
                -<?php echo $sequence_number; ?>-
            </div>
        </div>

    </div>
</body>
</html>