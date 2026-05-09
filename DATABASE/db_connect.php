<?php
require_once __DIR__ . '/config.php';

$servername = env('DB_HOST', '127.0.0.1');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');
$dbname = env('DB_NAME', 'peso_db');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

$conn->set_charset("utf8mb4");

// Create otps table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `otps` ( `otp_id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `otp` varchar(10) NOT NULL, `is_used` TINYINT(1) NOT NULL DEFAULT 0, `created_at` timestamp NOT NULL DEFAULT current_timestamp(), `expires_at` datetime NOT NULL, PRIMARY KEY (`otp_id`), KEY `user_id` (`user_id`), CONSTRAINT `otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Add is_email_verified column to users table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'is_email_verified'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `is_email_verified` BOOLEAN NOT NULL DEFAULT FALSE AFTER `role`");
}

// Add is_archived and archived_at columns to users table if they don't exist
$result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'is_archived'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `is_archived` TINYINT(1) NOT NULL DEFAULT 0");
    $conn->query("ALTER TABLE `users` ADD `archived_at` DATETIME NULL");
}

// Add ServiceProvider role to users table
$conn->query("ALTER TABLE `users` MODIFY `role` ENUM('Admin','Employer','JobSeeker','ServiceProvider') NOT NULL");

// Add is_used column to otps table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM `otps` LIKE 'is_used'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `otps` ADD `is_used` TINYINT(1) NOT NULL DEFAULT 0 AFTER `otp`");
}



// Create jobseekers table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `jobseekers` ( 
    `seeker_id` int(11) NOT NULL, 
    `first_name` varchar(100) NOT NULL, 
    `middle_name` varchar(100) DEFAULT NULL, 
    `last_name` varchar(100) NOT NULL, 
    `suffix` varchar(10) DEFAULT NULL, 
    `gender` enum('Male','Female') NOT NULL, 
    `birthdate` date NOT NULL, 
    `civil_status` varchar(50) NOT NULL, 
    `place_of_birth` varchar(255) NOT NULL, 
    `street_address` varchar(255) NOT NULL, 
    `location_id` int(11) NOT NULL, 
    `education_id` int(11) DEFAULT NULL,
    `course_id` int(11) DEFAULT NULL,
    `employment_status` enum('Unemployed','Underemployed','Employed') DEFAULT 'Unemployed',
    `preferred_occupation_id` int(11) DEFAULT NULL,
    `disability` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`seeker_id`), 
    KEY `location_id` (`location_id`), 
    CONSTRAINT `jobseekers_ibfk_1` FOREIGN KEY (`seeker_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename jobseeker_id to seeker_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'jobseeker_id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `jobseekers` CHANGE `jobseeker_id` `seeker_id` INT(11) NOT NULL");
}

// Modify columns if they exist with old names
$check = $conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'sex'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `jobseekers` CHANGE `sex` `gender` ENUM('Male','Female') NOT NULL");
}

$check = $conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'dob'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `jobseekers` CHANGE `dob` `birthdate` DATE NOT NULL");
}

// Add is_verified column to jobseekers table if it doesn't exist (For Hardcopy Verification)
$check = $conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'is_verified'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `jobseekers` ADD `is_verified` TINYINT(1) DEFAULT 0 AFTER `seeker_id`");
}

// Create skills table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `skills` (
  `skill_id` int(11) NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(100) NOT NULL,
  PRIMARY KEY (`skill_id`),
  UNIQUE KEY `skill_name` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Pre-populate default skills if the table is empty
$check_skills = $conn->query("SELECT COUNT(*) as count FROM skills");
if ($check_skills && $check_skills->fetch_assoc()['count'] == 0) {
    $default_skills = [
        // Office & Admin
        'Data Entry', 'Typing', 'Bookkeeping', 'Accounting', 'Office Administration', 'Clerical Skills', 'Microsoft Office', 'Records Management',
        
        // IT & Tech
        'Computer Literacy', 'IT Support / Helpdesk', 'Web Development', 'Software Development', 'Graphic Design', 'Network Administration', 'Troubleshooting', 'AutoCAD', 'Video Editing',
        
        // Customer Service & Sales
        'Customer Service', 'Call Center / BPO', 'Retail Sales', 'Cashiering', 'Sales Management', 'Telemarketing', 'Account Management',
        
        // Hospitality & Tourism
        'Cooking / Culinary', 'Food & Beverage Service', 'Bartending', 'Housekeeping', 'Front Desk / Reception', 'Event Planning', 'Tour Guiding',
        
        // Trade & Technical (Blue Collar)
        'Driving (Pro/Non-Pro)', 'Heavy Equipment Operation', 'Welding', 'Plumbing', 'Carpentry', 'Masonry', 'Electrical Works', 'Automotive Mechanic', 'Aircon/Refrigeration Tech',
        
        // Health & Medical
        'Nursing / Caregiving', 'First Aid / CPR', 'Pharmacy Assistant', 'Medical Billing', 'Dental Assistant',
        
        // Logistics & Manufacturing
        'Warehouse Operations', 'Inventory Management', 'Quality Control', 'Machine Operation', 'Packaging', 'Logistics',
        
        // Education & Language
        'Teaching / Tutoring', 'English Proficiency', 'Translation', 'Content Writing', 'Copywriting',
        
        // Soft Skills / Management
        'Leadership', 'Project Management', 'Problem Solving', 'Communication Skills', 'Time Management', 'Human Resources', 'Marketing'
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO skills (skill_name) VALUES (?)");
    foreach ($default_skills as $skill) {
        $stmt->bind_param("s", $skill);
        $stmt->execute();
    }
    $stmt->close();
}

// Add new columns if they don't exist
$new_columns = ['education_id' => 'INT(11) DEFAULT NULL', 'course_id' => 'INT(11) DEFAULT NULL', 'employment_status' => "ENUM('Unemployed','Underemployed','Employed') DEFAULT 'Unemployed'", 'disability' => 'VARCHAR(100) DEFAULT NULL', 'summary' => 'TEXT DEFAULT NULL', 'contact_no' => 'VARCHAR(50) DEFAULT NULL', 'languages' => 'VARCHAR(255) DEFAULT NULL', 'school_name' => 'VARCHAR(255) DEFAULT NULL', 'school_year' => 'VARCHAR(50) DEFAULT NULL'];
foreach ($new_columns as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM `jobseekers` LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE `jobseekers` ADD `$col` $def");
    }
}

// Create jobseeker_skills table for multiple skills
$conn->query("CREATE TABLE IF NOT EXISTS `jobseeker_skills` (
  `seeker_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  PRIMARY KEY (`seeker_id`,`skill_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `jobseeker_skills_ibfk_1` FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers` (`seeker_id`) ON DELETE CASCADE,
  CONSTRAINT `jobseeker_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Remove single skill_id column if it exists (Migration to multiple skills)
if ($conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'skill_id'")->num_rows > 0) {
    // Try to drop FK first (might fail if constraint name differs, but we try standard naming or previous naming)
    $conn->query("ALTER TABLE `jobseekers` DROP FOREIGN KEY `jobseekers_ibfk_skill`"); 
    $conn->query("ALTER TABLE `jobseekers` DROP COLUMN `skill_id`");
}

// Remove preferred_occupation_id if it exists
if ($conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'preferred_occupation_id'")->num_rows > 0) {
    $conn->query("ALTER TABLE `jobseekers` DROP COLUMN `preferred_occupation_id`");
}

// Create job_postings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `job_postings` (
    `job_id` INT AUTO_INCREMENT PRIMARY KEY,
    `employer_id` INT NOT NULL,
    `job_title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `employment_type` VARCHAR(50),
    `location_id` INT,
    `status` VARCHAR(50) DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employer_id`) REFERENCES `employers`(`employer_id`) ON DELETE CASCADE,
    FOREIGN KEY (`location_id`) REFERENCES `locations`(`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add vacancies_count column to job_postings table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM `job_postings` LIKE 'vacancies_count'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `job_postings` ADD `vacancies_count` INT DEFAULT 1 AFTER `job_title`");
}

// Add accepts_pwd column to job_postings table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM `job_postings` LIKE 'accepts_pwd'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `job_postings` ADD `accepts_pwd` TINYINT(1) DEFAULT 0");
}

// Create referrals_applications table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `referrals_applications` (
    `application_id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` INT NOT NULL,
    `seeker_id` INT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `job_postings`(`job_id`) ON DELETE CASCADE,
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename id to application_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `referrals_applications` LIKE 'id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `referrals_applications` CHANGE `id` `application_id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Add resume_file column to referrals_applications table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM `referrals_applications` LIKE 'resume_file'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `referrals_applications` ADD `resume_file` VARCHAR(255) NULL AFTER `status`");
}

// Add profile_picture column to jobseekers table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM `jobseekers` LIKE 'profile_picture'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `jobseekers` ADD `profile_picture` VARCHAR(255) DEFAULT NULL");
}

// Create saved_jobs table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `saved_jobs` (
    `saved_job_id` INT AUTO_INCREMENT PRIMARY KEY,
    `seeker_id` INT NOT NULL,
    `job_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `job_postings`(`job_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_save` (`seeker_id`, `job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename id to saved_job_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `saved_jobs` LIKE 'id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `saved_jobs` CHANGE `id` `saved_job_id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Create notifications table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `reference_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename id to notification_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `notifications` CHANGE `id` `notification_id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Create work_experience table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `work_experience` (
    `work_id` INT AUTO_INCREMENT PRIMARY KEY,
    `seeker_id` INT NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `job_title` VARCHAR(255) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE,
    `description` TEXT,
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename id to work_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `work_experience` LIKE 'id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `work_experience` CHANGE `id` `work_id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Create seminars_trainings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `seminars_trainings` (
    `seminar_id` INT AUTO_INCREMENT PRIMARY KEY,
    `seeker_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `provider` VARCHAR(255) NOT NULL,
    `date_attended` DATE,
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename id to seminar_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `seminars_trainings` LIKE 'id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `seminars_trainings` CHANGE `id` `seminar_id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Create character_references table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `character_references` (
    `reference_id` INT AUTO_INCREMENT PRIMARY KEY,
    `seeker_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `company` VARCHAR(255),
    `position` VARCHAR(255),
    `contact_no` VARCHAR(50),
    `email` VARCHAR(255),
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename id to reference_id if it exists (Migration)
$check = $conn->query("SHOW COLUMNS FROM `character_references` LIKE 'id'");
if ($check->num_rows > 0) {
    $conn->query("ALTER TABLE `character_references` CHANGE `id` `reference_id` INT(11) NOT NULL AUTO_INCREMENT");
}

// Create jobseeker_documents table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `jobseeker_documents` (
    `document_id` INT AUTO_INCREMENT PRIMARY KEY,
    `seeker_id` INT NOT NULL,
    `doc_type` VARCHAR(50) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add email_notifications column to users table if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'email_notifications'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `users` ADD `email_notifications` TINYINT(1) NOT NULL DEFAULT 1");
}

// Create job_posting_disabilities table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `job_posting_disabilities` (
    `job_id` INT NOT NULL,
    `disability_type` ENUM('Visual','Hearing','Speech','Physical','Mental','Others') NOT NULL,
    `other_description` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`job_id`, `disability_type`),
    FOREIGN KEY (`job_id`) REFERENCES `job_postings`(`job_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Create job_skills table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `job_skills` (
    `job_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    PRIMARY KEY (`job_id`, `skill_id`),
    FOREIGN KEY (`job_id`) REFERENCES `job_postings`(`job_id`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `skills`(`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// FIX: Change status column in referrals_applications to VARCHAR to accept 'Pending_Docs' and 'Verified'
$conn->query("ALTER TABLE `referrals_applications` MODIFY `status` VARCHAR(50) DEFAULT 'Pending'");

// FIX: Update any blank statuses (caused by ENUM error) to 'Pending_Docs'
$conn->query("UPDATE `referrals_applications` SET `status` = 'Pending_Docs' WHERE `status` = ''");

// FIX: Change status column in job_postings to VARCHAR to accept 'Pending_Approval' and 'Rejected'
$conn->query("ALTER TABLE `job_postings` MODIFY `status` VARCHAR(50) DEFAULT 'Pending_Approval'");

// FIX: Update any blank statuses in job_postings (caused by ENUM error) to 'Pending_Approval'
$conn->query("UPDATE `job_postings` SET `status` = 'Pending_Approval' WHERE `status` = ''");

// Add placement_date column to referrals_applications table if it doesn't exist (For Hired Reports)
$check = $conn->query("SHOW COLUMNS FROM `referrals_applications` LIKE 'placement_date'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `referrals_applications` ADD `placement_date` DATETIME DEFAULT NULL AFTER `resume_file`");
}

// Retroactively set placement_date for existing hired records using their application date as a fallback.
// This helps make historical data appear in reports.
$conn->query("
    UPDATE referrals_applications 
    SET placement_date = created_at 
    WHERE placement_date IS NULL AND status IN ('Hired', 'Hired / Placed', 'Accepted')
");

// Add close_reason column to job_postings table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM `job_postings` LIKE 'close_reason'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `job_postings` ADD `close_reason` VARCHAR(255) DEFAULT NULL AFTER `status`");
}

// Add end_employment_reason column to referrals_applications table if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM `referrals_applications` LIKE 'end_employment_reason'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE `referrals_applications` ADD `end_employment_reason` VARCHAR(255) DEFAULT NULL AFTER `status`");
}

// Add interview tracking columns to referrals_applications table if they don't exist
$check_interview = $conn->query("SHOW COLUMNS FROM `referrals_applications` LIKE 'interview_date'");
if ($check_interview->num_rows == 0) {
    $conn->query("ALTER TABLE `referrals_applications` ADD `interview_date` DATETIME DEFAULT NULL AFTER `placement_date`");
    $conn->query("ALTER TABLE `referrals_applications` ADD `interview_message` TEXT DEFAULT NULL AFTER `interview_date`");
}

// Add deployment tracking columns to referrals_applications table if they don't exist
$check_dep = $conn->query("SHOW COLUMNS FROM `referrals_applications` LIKE 'deployment_date'");
if ($check_dep->num_rows == 0) {
    $conn->query("ALTER TABLE `referrals_applications` ADD `deployment_date` DATETIME DEFAULT NULL AFTER `interview_message`");
    $conn->query("ALTER TABLE `referrals_applications` ADD `deployment_message` TEXT DEFAULT NULL AFTER `deployment_date`");
}

// Create resignation_reports table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `resignation_reports` (
    `report_id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `seeker_id` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `remarks` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `referrals_applications`(`application_id`) ON DELETE CASCADE,
    FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add proof_file column to resignation_reports table if it doesn't exist
$check_proof = $conn->query("SHOW COLUMNS FROM `resignation_reports` LIKE 'proof_file'");
if ($check_proof->num_rows == 0) {
    $conn->query("ALTER TABLE `resignation_reports` ADD `proof_file` VARCHAR(255) DEFAULT NULL AFTER `remarks`");
}

// Create service_providers table for the Gatekeeper Verification Phase
$conn->query("CREATE TABLE IF NOT EXISTS `service_providers` (
    `provider_id` INT(11) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `birthdate` DATE NOT NULL,
    `gender` ENUM('Male','Female') NOT NULL,
    `barangay` VARCHAR(100) NOT NULL,
    `street_address` VARCHAR(255) NOT NULL,
    `valid_id_path` VARCHAR(255) NOT NULL,
    `brgy_residency_path` VARCHAR(255) NOT NULL,
    `tesda_cert_path` VARCHAR(255) DEFAULT NULL,
    `portfolio_path` VARCHAR(255) DEFAULT NULL,
    `admin_verification_status` VARCHAR(50) DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`provider_id`),
    FOREIGN KEY (`provider_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Rename brgy_clearance_path to brgy_residency_path if it exists (Migration)
$check_brgy = $conn->query("SHOW COLUMNS FROM `service_providers` LIKE 'brgy_clearance_path'");
if ($check_brgy->num_rows > 0) {
    $conn->query("ALTER TABLE `service_providers` CHANGE `brgy_clearance_path` `brgy_residency_path` VARCHAR(255) NOT NULL");
}

// Rename status to admin_verification_status in service_providers if it exists (Migration)
$check_sp_status = $conn->query("SHOW COLUMNS FROM `service_providers` LIKE 'status'");
if ($check_sp_status->num_rows > 0) {
    $conn->query("ALTER TABLE `service_providers` CHANGE `status` `admin_verification_status` VARCHAR(50) DEFAULT 'Pending'");
}

// Add skills_description column to service_providers if it doesn't exist
$check_skills_desc = $conn->query("SHOW COLUMNS FROM `service_providers` LIKE 'skills_description'");
if ($check_skills_desc->num_rows == 0) {
    $conn->query("ALTER TABLE `service_providers` ADD `skills_description` TEXT DEFAULT NULL AFTER `portfolio_path`");
}

// Add base_rate column to service_providers if it doesn't exist
$check_base_rate = $conn->query("SHOW COLUMNS FROM `service_providers` LIKE 'base_rate'");
if ($check_base_rate->num_rows == 0) {
    $conn->query("ALTER TABLE `service_providers` ADD `base_rate` VARCHAR(100) DEFAULT NULL AFTER `skills_description`");
}

// Create provider_services table for multiple service postings
$conn->query("CREATE TABLE IF NOT EXISTS `provider_services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `base_rate` varchar(50) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`service_id`),
  FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Create service_requests table for Booking Management
$conn->query("CREATE TABLE IF NOT EXISTS `service_requests` (
    `request_id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `service_needed` VARCHAR(255) NOT NULL,
    `scheduled_date` DATETIME NOT NULL,
    `status` ENUM('Pending', 'Accepted', 'Declined', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Pending',
    `amount_charged` DECIMAL(10,2) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`provider_id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add service_id to service_requests to link to the specific service
$check_req_service_id = $conn->query("SHOW COLUMNS FROM `service_requests` LIKE 'service_id'");
if ($check_req_service_id->num_rows == 0) {
    // Add the column, make it nullable for existing records
    $conn->query("ALTER TABLE `service_requests` ADD `service_id` INT(11) NULL DEFAULT NULL AFTER `client_id`");
    $conn->query("ALTER TABLE `service_requests` ADD CONSTRAINT `fk_service_id` FOREIGN KEY (`service_id`) REFERENCES `provider_services`(`service_id`) ON DELETE SET NULL");
}

// Update service_requests table to include Address and Message (Step 2 of Booking Flow)
$check_req_addr = $conn->query("SHOW COLUMNS FROM `service_requests` LIKE 'client_address'");
if ($check_req_addr->num_rows == 0) {
    $conn->query("ALTER TABLE `service_requests` ADD `client_address` VARCHAR(255) DEFAULT NULL AFTER `scheduled_date`");
}
$check_req_msg = $conn->query("SHOW COLUMNS FROM `service_requests` LIKE 'client_message'");
if ($check_req_msg->num_rows == 0) {
    $conn->query("ALTER TABLE `service_requests` ADD `client_message` TEXT DEFAULT NULL AFTER `client_address`");
}

// Add updated_at column to service_requests table to track changes like cancellations
$check_req_updated = $conn->query("SHOW COLUMNS FROM `service_requests` LIKE 'updated_at'");
if ($check_req_updated->num_rows == 0) {
    $conn->query("ALTER TABLE `service_requests` ADD `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
}

// Create service_reviews table for Reputation & Performance Board
$conn->query("CREATE TABLE IF NOT EXISTS `service_reviews` (
    `review_id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT DEFAULT NULL,
    `provider_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `rating` INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    `comment` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`provider_id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`request_id`) REFERENCES `service_requests`(`request_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add UNIQUE constraint to request_id in service_reviews to prevent duplicate reviews
$check_unique_review = $conn->query("
    SELECT COUNT(*) as count 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = '{$dbname}' 
    AND TABLE_NAME = 'service_reviews' 
    AND INDEX_NAME = 'request_id_unique'
");
if ($check_unique_review && $check_unique_review->fetch_assoc()['count'] == 0) {
    $conn->query("ALTER TABLE `service_reviews` ADD UNIQUE `request_id_unique` (`request_id`)");
}

// Create provider_reports table for the Grievance Phase / Trust & Safety
$conn->query("CREATE TABLE IF NOT EXISTS `provider_reports` (
    `report_id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT NOT NULL,
    `reporter_id` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`provider_id`) ON DELETE CASCADE,
    FOREIGN KEY (`reporter_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// FIX: Change status column in provider_services to VARCHAR to accept 'Pending_Approval' and 'Rejected'
$conn->query("ALTER TABLE `provider_services` MODIFY `status` VARCHAR(50) DEFAULT 'Pending_Approval'");
$conn->query("UPDATE `provider_services` SET `status` = 'Pending_Approval' WHERE `status` = ''");

// Create educational_background table for multiple schools
$check_edu_table = $conn->query("SHOW TABLES LIKE 'educational_background'");
if ($check_edu_table->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `educational_background` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `seeker_id` INT NOT NULL,
        `school_name` VARCHAR(255) NOT NULL,
        `school_year` VARCHAR(50) NOT NULL,
        FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers`(`seeker_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // Migrate existing single records if any
    $conn->query("INSERT INTO `educational_background` (seeker_id, school_name, school_year) SELECT seeker_id, school_name, school_year FROM `jobseekers` WHERE school_name IS NOT NULL AND school_name != ''");
}

// Add profile_picture and cover_photo to employers table
$check_emp_pic = $conn->query("SHOW COLUMNS FROM `employers` LIKE 'profile_picture'");
if ($check_emp_pic && $check_emp_pic->num_rows == 0) {
    $conn->query("ALTER TABLE `employers` ADD `profile_picture` VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE `employers` ADD `cover_photo` VARCHAR(255) DEFAULT NULL");
}

?>