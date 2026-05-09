-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 11:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peso_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `business_lines`
--

CREATE TABLE `business_lines` (
  `business_line_id` int(11) NOT NULL,
  `business_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_lines`
--

INSERT INTO `business_lines` (`business_line_id`, `business_name`) VALUES
(1, 'Food &amp; Beverage');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `education_levels`
--

CREATE TABLE `education_levels` (
  `education_id` int(11) NOT NULL,
  `level_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `employer_id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `trade_name` varchar(150) DEFAULT NULL,
  `acronym` varchar(50) DEFAULT NULL,
  `employer_type` enum('Public','Private') NOT NULL,
  `employer_subtype` varchar(100) DEFAULT NULL,
  `office_type` enum('Main office','Branch') NOT NULL,
  `total_work_force` enum('Micro (1-9)','Small (10-99)','Medium (100-199)','Large (200 and up)') NOT NULL,
  `business_line_id` int(11) NOT NULL,
  `tin_number` varchar(50) NOT NULL,
  `street_address` varchar(255) NOT NULL,
  `location_id` int(11) NOT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `contact_person_name` varchar(150) NOT NULL,
  `contact_person_position` varchar(100) NOT NULL,
  `telephone_number` varchar(50) DEFAULT NULL,
  `mobile_number` varchar(50) DEFAULT NULL,
  `fax_number` varchar(50) DEFAULT NULL,
  `email_address` varchar(150) DEFAULT NULL,
  `company_description` text DEFAULT NULL,
  `admin_verification_status` enum('Pending','Verified','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`employer_id`, `company_name`, `trade_name`, `acronym`, `employer_type`, `employer_subtype`, `office_type`, `total_work_force`, `business_line_id`, `tin_number`, `street_address`, `location_id`, `owner_name`, `contact_person_name`, `contact_person_position`, `telephone_number`, `mobile_number`, `fax_number`, `email_address`, `company_description`, `admin_verification_status`) VALUES
(22, 'Jollibee', 'Jollibee bongabon', 'JB', 'Private', 'Direct Hire', 'Branch', 'Large (200 and up)', 1, '000-123-456-789', 'brgy Poblacion', 13, 'Tony Tan Caktiong', 'Hydie Conde', 'HR Officer', '044-987-6543', '09171234567', '044-987-6543', 'contact@jollibee.com.ph', 'JOLLIBEE IS NOW OPEN', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `employer_documents`
--

CREATE TABLE `employer_documents` (
  `document_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `document_type` enum('Mayor''s Permit','DTI','SEC','DOLE Registration','BMBE Certificate','DMW/POEA License','SRA','Approved Job Orders') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `is_verified_by_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employer_documents`
--

INSERT INTO `employer_documents` (`document_id`, `employer_id`, `document_type`, `file_path`, `is_verified_by_admin`) VALUES
(1, 22, '', '../uploads/employer_documents/22/localDocFile_69a3e095aebf89.84625523.jpg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `jobseekers`
--

CREATE TABLE `jobseekers` (
  `seeker_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `civil_status` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `place_of_birth` varchar(255) NOT NULL,
  `street_address` varchar(255) NOT NULL,
  `location_id` int(11) NOT NULL,
  `education_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `employment_status` enum('Unemployed','Underemployed','Employed') DEFAULT 'Unemployed',
  `preferred_occupation_id` int(11) DEFAULT NULL,
  `disability` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobseekers`
--

INSERT INTO `jobseekers` (`seeker_id`, `first_name`, `middle_name`, `last_name`, `suffix`, `gender`, `civil_status`, `birthdate`, `place_of_birth`, `street_address`, `location_id`, `education_id`, `course_id`, `employment_status`, `preferred_occupation_id`, `disability`) VALUES
(17, 'Hydie', 'malawit', 'Conde', '', 'Female', 'Single', '2004-02-14', 'Cabanatuan City', '', 12, NULL, NULL, 'Unemployed', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `job_title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `vacancies_count` int(11) NOT NULL DEFAULT 1,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `place_of_work` varchar(255) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `employment_type` enum('Permanent','Contractual','Internship/OJT','Part-time','Project-based','Work from home/online job') DEFAULT 'Permanent',
  `education_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `experience_required` varchar(100) DEFAULT NULL,
  `other_qualifications` text DEFAULT NULL,
  `accepts_pwd` tinyint(1) DEFAULT 0,
  `accepts_returning_ofws` tinyint(1) DEFAULT 0,
  `license_required` varchar(255) DEFAULT NULL,
  `eligibility_required` varchar(255) DEFAULT NULL,
  `certification_required` varchar(255) DEFAULT NULL,
  `language_spoken` varchar(255) DEFAULT NULL,
  `status` enum('Active','Closed') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `posting_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_posting_disabilities`
--

CREATE TABLE `job_posting_disabilities` (
  `job_id` int(11) NOT NULL,
  `disability_type` enum('Visual','Hearing','Speech','Physical','Mental','Others') NOT NULL,
  `other_description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_seeker_documents`
--

CREATE TABLE `job_seeker_documents` (
  `document_id` int(11) NOT NULL,
  `seeker_id` int(11) NOT NULL,
  `document_type` enum('Resume/CV','Certificate','ID','Diploma','NBI Clearance') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_skills`
--

CREATE TABLE `job_skills` (
  `job_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city_municipality` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `barangay`, `city_municipality`, `province`) VALUES
(3, 'Macabaclay', 'Bongabon', 'Nueva Ecija'),
(4, 'San Marcelino', 'Bongabon', 'Nueva Ecija'),
(5, 'Pesa', 'Bongabon', 'Nueva Ecija'),
(10, 'San Juan', 'Bongabon', 'Nueva Ecija'),
(12, 'Labi', 'Bongabon', 'Nueva Ecija'),
(13, 'Poblacion', 'Bongabon', 'Nueva Ecija');

-- --------------------------------------------------------

--
-- Table structure for table `occupations`
--

CREATE TABLE `occupations` (
  `occupation_id` int(11) NOT NULL,
  `occupation_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `otp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otps`
--

INSERT INTO `otps` (`otp_id`, `user_id`, `otp`, `is_used`, `created_at`, `expires_at`) VALUES
(2, 17, '116048', 1, '2026-03-01 02:38:05', '2026-03-01 03:48:05'),
(3, 17, '511625', 0, '2026-03-01 03:25:56', '2026-03-01 04:35:56'),
(4, 17, '989642', 1, '2026-03-01 03:29:39', '2026-03-01 04:39:39'),
(5, 22, '893867', 1, '2026-03-01 06:45:43', '2026-03-01 07:55:43');

-- --------------------------------------------------------

--
-- Table structure for table `peso_events`
--

CREATE TABLE `peso_events` (
  `event_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `event_type` enum('LRA','Job Fair') NOT NULL,
  `title` varchar(150) NOT NULL,
  `event_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals_applications`
--

CREATE TABLE `referrals_applications` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `seeker_id` int(11) NOT NULL,
  `status` enum('Pending','Issue Referral Letter','Pending Interview','Hired / Placed','Rejected / Not Qualified') DEFAULT 'Pending',
  `application_date` date NOT NULL DEFAULT curdate(),
  `referral_date` date DEFAULT NULL,
  `placement_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Employer','JobSeeker') NOT NULL,
  `is_email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `password_hash`, `role`, `is_email_verified`, `created_at`) VALUES
(17, '$2y$10$DpDd3F/RDBGkt5WvqJnrHub.8UhBK6AjuiekiDdcE2qlBII35mjEi', 'JobSeeker', 1, '2026-03-01 02:38:05'),
(18, '$2y$10$LlCSOrklHfI4QEB.1oYUz.u4JUKTgqJrvUAvEbVGofEtBm.nwpfUi', 'Admin', 1, '2026-03-01 03:54:15'),
(22, '$2y$10$shF.sp.615PnUElOCEImJOi7LAwI8tIuan38KPSwoH2c53rx8MAcS', 'Employer', 1, '2026-03-01 06:45:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_contacts`
--

CREATE TABLE `user_contacts` (
  `contact_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contact_type` enum('Email','Mobile') NOT NULL,
  `contact_value` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_contacts`
--

INSERT INTO `user_contacts` (`contact_id`, `user_id`, `contact_type`, `contact_value`, `is_primary`) VALUES
(29, 17, 'Email', 'condehydie14@gmail.com', 1),
(30, 17, 'Mobile', '09317905961', 0),
(31, 18, 'Email', 'admin@peso.com', 1),
(35, 22, 'Email', 'condehydie00@gmail.com', 1),
(36, 22, 'Mobile', '09926978880', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `business_lines`
--
ALTER TABLE `business_lines`
  ADD PRIMARY KEY (`business_line_id`),
  ADD UNIQUE KEY `business_name` (`business_name`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_name` (`course_name`);

--
-- Indexes for table `education_levels`
--
ALTER TABLE `education_levels`
  ADD PRIMARY KEY (`education_id`),
  ADD UNIQUE KEY `level_name` (`level_name`);

--
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`employer_id`),
  ADD KEY `business_line_id` (`business_line_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `employer_documents`
--
ALTER TABLE `employer_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `jobseekers`
--
ALTER TABLE `jobseekers`
  ADD PRIMARY KEY (`seeker_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `employer_id` (`employer_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `education_id` (`education_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `job_seeker_documents`
--
ALTER TABLE `job_seeker_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `seeker_id` (`seeker_id`);

--
-- Indexes for table `job_skills`
--
ALTER TABLE `job_skills`
  ADD PRIMARY KEY (`job_id`,`skill_id`),
  ADD KEY `skill_id` (`skill_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `occupations`
--
ALTER TABLE `occupations`
  ADD PRIMARY KEY (`occupation_id`),
  ADD UNIQUE KEY `occupation_name` (`occupation_name`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `peso_events`
--
ALTER TABLE `peso_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `referrals_applications`
--
ALTER TABLE `referrals_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `seeker_id` (`seeker_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD UNIQUE KEY `skill_name` (`skill_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_contacts`
--
ALTER TABLE `user_contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD UNIQUE KEY `contact_value` (`contact_value`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `business_lines`
--
ALTER TABLE `business_lines`
  MODIFY `business_line_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `education_levels`
--
ALTER TABLE `education_levels`
  MODIFY `education_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employer_documents`
--
ALTER TABLE `employer_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_seeker_documents`
--
ALTER TABLE `job_seeker_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `occupations`
--
ALTER TABLE `occupations`
  MODIFY `occupation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `peso_events`
--
ALTER TABLE `peso_events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals_applications`
--
ALTER TABLE `referrals_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `user_contacts`
--
ALTER TABLE `user_contacts`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employers`
--
ALTER TABLE `employers`
  ADD CONSTRAINT `employers_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employers_ibfk_2` FOREIGN KEY (`business_line_id`) REFERENCES `business_lines` (`business_line_id`),
  ADD CONSTRAINT `employers_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`);

--
-- Constraints for table `employer_documents`
--
ALTER TABLE `employer_documents`
  ADD CONSTRAINT `employer_documents_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `jobseekers`
--
ALTER TABLE `jobseekers`
  ADD CONSTRAINT `jobseekers_ibfk_1` FOREIGN KEY (`seeker_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_postings_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`),
  ADD CONSTRAINT `job_postings_ibfk_3` FOREIGN KEY (`education_id`) REFERENCES `education_levels` (`education_id`),
  ADD CONSTRAINT `job_postings_ibfk_4` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE SET NULL;

--
-- Constraints for table `job_seeker_documents`
--
ALTER TABLE `job_seeker_documents`
  ADD CONSTRAINT `job_seeker_documents_ibfk_1` FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers` (`seeker_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_skills`
--
ALTER TABLE `job_skills`
  ADD CONSTRAINT `job_skills_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_posting_disabilities`
--
ALTER TABLE `job_posting_disabilities`
  ADD CONSTRAINT `job_posting_disabilities_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE;

--
-- Indexes for table `job_posting_disabilities`
--
ALTER TABLE `job_posting_disabilities`
  ADD PRIMARY KEY (`job_id`,`disability_type`);

--
-- Constraints for table `otps`
--
ALTER TABLE `otps`
  ADD CONSTRAINT `otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `peso_events`
--
ALTER TABLE `peso_events`
  ADD CONSTRAINT `peso_events_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `referrals_applications`
--
ALTER TABLE `referrals_applications`
  ADD CONSTRAINT `referrals_applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_applications_ibfk_2` FOREIGN KEY (`seeker_id`) REFERENCES `jobseekers` (`seeker_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_contacts`
--
ALTER TABLE `user_contacts`
  ADD CONSTRAINT `user_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
