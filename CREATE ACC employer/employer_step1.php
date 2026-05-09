<?php
session_start();
include '../DATABASE/db_connect.php';
require '../DATABASE/csrf.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Validate TIN format (000-000-000-000)
        if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d{3}$/', $_POST['tinNumber'])) {
            $error = "Invalid TIN format. Please use the format 000-000-000-000.";
        } elseif (!preg_match('/^09\d{9}$/', $_POST['mobileNumber'])) {
            $error = "Invalid Mobile Number. It must start with 09 and be 11 digits long.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT u.is_email_verified FROM user_contacts uc JOIN users u ON uc.user_id = u.user_id WHERE uc.contact_value = ?");
            $stmt->bind_param("s", $_POST['emailAddress']);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if ($row['is_email_verified'] == 1) {
                    $error = "Email address is already registered.";
                }
            }
            $stmt->close();

            if (empty($error)) {
                $stmt = $conn->prepare("SELECT u.is_email_verified FROM user_contacts uc JOIN users u ON uc.user_id = u.user_id WHERE uc.contact_value = ?");
                $stmt->bind_param("s", $_POST['mobileNumber']);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if ($row['is_email_verified'] == 1) {
                        $error = "Mobile number is already registered.";
                    }
                }
                $stmt->close();
            }

            if (empty($error)) {
                $step1_data = $_POST;
                // Handle "Other" line of business
                if (isset($step1_data['businessLine']) && $step1_data['businessLine'] === 'Other' && !empty($step1_data['otherBusinessLine'])) {
                    $step1_data['businessLine'] = trim($step1_data['otherBusinessLine']);
                }
                $_SESSION['employer_step1'] = $step1_data;
                header("Location: employer_step3.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Registration - Step 1</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); min-height: 100vh; padding: 1rem; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { text-align: center; color: white; margin-bottom: 1rem; }
        .logo { width: 80px; height: 80px; margin: 0 auto 0.5rem; display: block; }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.25rem; }
        .header p { opacity: 0.9; font-size: 0.875rem; }
        .progress-container { background: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .progress-steps { display: flex; justify-content: space-between; position: relative; }
        .progress-steps::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #e5e7eb; z-index: 0; }
        .progress-line { position: absolute; top: 20px; left: 0; height: 3px; background: #fbbf24; z-index: 1; transition: width 0.3s; width: 0%; }
        .step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 2; flex: 1; max-width: 33%; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; color: #9ca3af; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-bottom: 0.5rem; transition: all 0.3s; }
        .step.active .step-circle { background: #1e40af; color: white; box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.2); }
        .step.completed .step-circle { background: #fbbf24; color: #1e40af; }
        .step-label { font-size: 0.7rem; color: #6b7280; font-weight: 500; text-align: center; }
        .step.active .step-label { color: #1e40af; font-weight: 600; }
        .form-card { background: white; padding: 1.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.25rem; color: #1f2937; margin-bottom: 0.25rem; }
        .form-subtitle { color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; font-size: 0.875rem; }
        .required { color: #ef4444; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .helper-text { font-size: 0.75rem; color: #6b7280; margin-top: 0.375rem; }
        .btn-container { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; }
        .btn { padding: 0.875rem 2rem; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 1rem; border: none; }
        .btn-back { background: #f3f4f6; color: #6b7280; }
        .btn-back:hover { background: #e5e7eb; }
        .btn-next { background: #1e40af; color: white; flex: 1; }
        .btn-next:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        @media (max-width: 768px) { .form-card { padding: 1rem; } .form-row { grid-template-columns: 1fr; } .step-label { font-size: 0.6rem; } .container { max-width: 100%; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../BONGABON.png" alt="Bongabon Logo" class="logo">
            <h1>Employer Registration</h1>
            <p>PESO Bongabon - Register your company (Local & International)</p>
        </div>
        <div class="progress-container">
            <div class="progress-steps">
                <div class="progress-line"></div>
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Establishment Details</div>
                </div>
                <div class="step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Credentials</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Documents</div>
                </div>
            </div>
        </div>
        <div class="form-card">
            <h2 class="form-title">Company Information</h2>
            <p class="form-subtitle">Tell us about your business</p>
            <?php if($error): ?>
                <div style="background-color: #fee2e2; color: #ef4444; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fca5a5;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form id="step1Form" method="post" action="employer_step1.php">
                <?php echo csrf_field(); ?>
                
                <!-- I. ESTABLISHMENT DETAILS -->
                <h3 class="form-title" style="font-size: 1rem; margin-top: 1rem;">I. Establishment Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Business Name <span class="required">*</span></label>
                        <input type="text" id="companyName" name="companyName" placeholder="Business Name per DTI/SEC" required>
                    </div>
                    <div class="form-group">
                        <label>Trade Name</label>
                        <input type="text" id="tradeName" name="tradeName" placeholder="Trade Name">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Acronym/Abbreviation</label>
                        <input type="text" id="acronym" name="acronym" placeholder="e.g., ABC">
                    </div>
                    <div class="form-group">
                        <label>Office Type <span class="required">*</span></label>
                        <select id="officeType" name="officeType" required>
                            <option value="">Select Office Type</option>
                            <option value="Main office">Main office</option>
                            <option value="Branch">Branch</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tax Identification Number (TIN) <span class="required">*</span></label>
                        <input type="text" id="tinNumber" name="tinNumber" placeholder="000-000-000-000" pattern="\d{3}-\d{3}-\d{3}-\d{3}" maxlength="15" oninput="formatTIN(this)" required>
                        <div class="helper-text">Format: 000-000-000-000</div>
                    </div>
                    <div class="form-group">
                        <label>Employer Type <span class="required">*</span></label>
                        <select id="employerType" name="employerType" required onchange="toggleEmployerSubtype()">
                            <option value="">Select type</option>
                            <option value="Public">Public</option>
                            <option value="Private">Private</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Employer Sub-Type <span class="required">*</span></label>
                        <select id="employerSubtype" name="employerSubtype" required>
                            <option value="">Select sub-type</option>
                            <!-- Options populated by JS -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Total Work Force <span class="required">*</span></label>
                        <select id="totalWorkForce" name="totalWorkForce" required>
                            <option value="">Select size</option>
                            <option value="Micro (1-9)">Micro (1-9)</option>
                            <option value="Small (10-99)">Small (10-99)</option>
                            <option value="Medium (100-199)">Medium (100-199)</option>
                            <option value="Large (200 and up)">Large (200 and up)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Line of Business/Industry <span class="required">*</span></label>
                        <select id="businessLine" name="businessLine" required onchange="toggleOtherBusinessLine()">
                            <option value="">Select type</option>
                            <option value="Agriculture">Agriculture</option>
                            <option value="Construction">Construction</option>
                            <option value="Education">Education</option>
                            <option value="Food and Beverage">Food and Beverage</option>
                            <option value="Healthcare">Healthcare</option>
                            <option value="IT/Technology">IT/Technology</option>
                            <option value="Manufacturing">Manufacturing</option>
                            <option value="Retail">Retail</option>
                            <option value="Services">Services</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Other">Other</option>
                        </select>
                        <div id="otherBusinessLineContainer" style="display: none; margin-top: 0.75rem;">
                            <input type="text" id="otherBusinessLine" name="otherBusinessLine" placeholder="Please specify your industry">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Company Location <span class="required">*</span></label>
                        <select id="companyLocation" name="companyLocation" required onchange="toggleAddressFields()">
                            <option value="">Select location</option>
                            <option value="Local">Local (Bongabon, Nueva Ecija)</option>
                            <option value="International">International/Overseas</option>
                        </select>
                    </div>
                </div>
                <!-- Local Address Fields -->
                <div id="localAddressFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Barangay <span class="required">*</span></label>
                            <select id="barangay" name="barangay">
                           <option value="">Select your barangay</option>
                        <option value="Antipolo">Antipolo</option>
                        <option value="Ariendo">Ariendo</option>
                        <option value="Bantug">Bantug</option>
                        <option value="Calaanan">Calaanan</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Cruz">Cruz</option>
                        <option value="Curva">Curva</option>
                        <option value="Digmala">Digmala</option>
                        <option value="Kaingin">Kaingin</option>
                        <option value="Labi">Labi</option>
                        <option value="Larcon">Larcon</option>
                        <option value="Lusok">Lusok</option>
                        <option value="Macabaclay">Macabaclay</option>
                        <option value="Magtanggol">Magtanggol</option>
                        <option value="Mantile">Mantile</option>
                        <option value="Olivete">Olivete</option>
                        <option value="Palo Maria">Palo Maria</option>
                        <option value="Pesa">Pesa</option>
                        <option value="Pook Rizal">Pook Rizal</option>
                        <option value="Sampalucan">Sampalucan</option>
                        <option value="San Roque">San Roque</option>
                        <option value="Santor">Santor</option>
                        <option value="Sinipit">Sinipit</option>
                        <option value="Sisilang na Ligaya">Sisilang na Ligaya</option>
                        <option value="Social">Social</option>
                        <option value="Tugatog">Tugatog</option>
                        <option value="Tulay na Bato">Tulay na Bato</option>
                        <option value="Vega">Vega</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Street/Building/House No. <span class="required">*</span></label>
                            <input type="text" id="localStreet" name="localStreet" placeholder="e.g., 123 Main Street, Building A">
                        </div>
                    </div>
                </div>
                <!-- International Address Fields -->
                <div id="internationalAddressFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Country <span class="required">*</span></label>
                            <select id="country" name="country" onchange="updateCities()">
                            <option value="">Select Country</option>
                            <option value="USA">United States</option>
                            <option value="Canada">Canada</option>
                            <option value="UK">United Kingdom</option>
                            <option value="Australia">Australia</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Japan">Japan</option>
                            <option value="South Korea">South Korea</option>
                            <option value="UAE">United Arab Emirates</option>
                            <option value="Saudi Arabia">Saudi Arabia</option>
                            <option value="Qatar">Qatar</option>
                            <option value="Kuwait">Kuwait</option>
                            <option value="Hong Kong">Hong Kong</option>
                            <option value="Taiwan">Taiwan</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>City <span class="required">*</span></label>
                            <input type="text" id="city" name="city" placeholder="Enter city name">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Street Address <span class="required">*</span></label>
                            <input type="text" id="internationalStreet" name="internationalStreet" placeholder="Building, Street, District">
                        </div>
                        <div class="form-group">
                            <label>Postal/Zip Code</label>
                            <input type="text" id="postalCode" name="postalCode" placeholder="Enter postal code">
                        </div>
                    </div>
                </div>

                <!-- II. ESTABLISHMENT CONTACT DETAILS -->
                <h3 class="form-title" style="font-size: 1rem; margin-top: 1.5rem;">II. Establishment Contact Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Name of Owner/President <span class="required">*</span></label>
                        <input type="text" id="ownerName" name="ownerName" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Person <span class="required">*</span></label>
                        <input type="text" id="contactPerson" name="contactPerson" placeholder="Full Name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Position <span class="required">*</span></label>
                        <input type="text" id="contactPosition" name="contactPosition" placeholder="Position" required>
                    </div>
                    <div class="form-group">
                        <label>Telephone Number</label>
                        <input type="tel" id="telephoneNumber" name="telephoneNumber" placeholder="Telephone Number">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Mobile Number <span class="required">*</span></label>
                        <input type="tel" id="mobileNumber" name="mobileNumber" placeholder="09XX XXX XXXX" pattern="09[0-9]{9}" maxlength="11" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)" required>
                    </div>
                    <div class="form-group">
                        <label>Fax Number</label>
                        <input type="tel" id="faxNumber" name="faxNumber" placeholder="Fax Number">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail Address <span class="required">*</span></label>
                        <input type="email" id="emailAddress" name="emailAddress" placeholder="company@email.com" required>
                    </div>
                </div>

                <div class="btn-container">
                    <button type="button" class="btn btn-back" onclick="window.location.href='../LOGIN SIGNUP/new_signup.php'">Back</button>
                    <button type="submit" class="btn btn-next">Next Step →</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function toggleOtherBusinessLine() {
            const businessLine = document.getElementById('businessLine').value;
            const otherContainer = document.getElementById('otherBusinessLineContainer');
            const otherInput = document.getElementById('otherBusinessLine');
            
            if (businessLine === 'Other') {
                otherContainer.style.display = 'block';
                otherInput.required = true;
            } else {
                otherContainer.style.display = 'none';
                otherInput.required = false;
            }
        }

        function toggleAddressFields() {
            const location = document.getElementById('companyLocation').value;
            const localFields = document.getElementById('localAddressFields');
            const intlFields = document.getElementById('internationalAddressFields');
            
            if (location === 'Local') {
                localFields.style.display = 'block';
                intlFields.style.display = 'none';
                document.getElementById('barangay').required = true;
                document.getElementById('localStreet').required = true;
                document.getElementById('country').required = false;
                document.getElementById('city').required = false;
                document.getElementById('internationalStreet').required = false;
            } else if (location === 'International') {
                localFields.style.display = 'none';
                intlFields.style.display = 'block';
                document.getElementById('barangay').required = false;
                document.getElementById('localStreet').required = false;
                document.getElementById('country').required = true;
                document.getElementById('city').required = true;
                document.getElementById('internationalStreet').required = true;
            } else {
                localFields.style.display = 'none';
                intlFields.style.display = 'none';
            }
        }

        function toggleEmployerSubtype() {
            const type = document.getElementById('employerType').value;
            const subtypeSelect = document.getElementById('employerSubtype');
            subtypeSelect.innerHTML = '<option value="">Select sub-type</option>';
            
            let options = [];
            if (type === 'Public') {
                options = [
                    'National Government Agency',
                    'Local Government Unit',
                    'Government-owned and Controlled Corporation',
                    'State/Local University or College'
                ];
            } else if (type === 'Private') {
                options = [
                    'Direct Hire',
                    'Local Recruitment Agency',
                    'Overseas Recruitment Agency',
                    'D.O.174'
                ];
            }
            
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                subtypeSelect.appendChild(option);
            });
        }

        function updateCities() {
            // Cities can be typed freely for flexibility
        }

        function formatTIN(input) {
            // Remove non-numeric characters
            let value = input.value.replace(/\D/g, '');
            
            // Limit to 12 digits
            if (value.length > 12) {
                value = value.substring(0, 12);
            }
            
            // Add dashes every 3 digits
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 3 === 0) {
                    formatted += '-';
                }
                formatted += value[i];
            }
            
            input.value = formatted;
        }
    </script>
</body>
</html>
