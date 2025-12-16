-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 16, 2025 at 05:33 PM
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
-- Database: `alumni_tracking`
--

-- --------------------------------------------------------

--
-- Table structure for table `alumni_activity_log`
--

CREATE TABLE `alumni_activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumni_activity_log`
--

INSERT INTO `alumni_activity_log` (`log_id`, `user_id`, `action_type`, `description`, `created_at`) VALUES
(227, 7, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 04:45:09'),
(228, 7, 'employment_updated', 'Updated employment information', '2025-12-16 04:45:30'),
(229, 9, 'employment_updated', 'Updated employment information', '2025-12-16 04:46:30'),
(230, 9, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 04:46:44'),
(231, 17, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 12:51:09'),
(232, 17, 'employment_updated', 'Updated employment information', '2025-12-16 12:51:25'),
(233, 17, 'employment_updated', 'Updated employment information', '2025-12-16 12:53:47');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_address`
--

CREATE TABLE `alumni_address` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alumni_address`
--

INSERT INTO `alumni_address` (`address_id`, `user_id`, `city`, `state_province`, `street`, `country`, `created_at`, `updated_at`) VALUES
(35, 2, 'Aurora', 'Zamboanga del Sur', '24 Aguinaldo Street', 'Philippines', '2025-12-14 16:24:07', '2025-12-15 04:56:43'),
(36, 40, 'Molave', 'Zamboanga del Sur', '309 Mabini Street', 'Philippines', '2025-12-14 16:43:06', '2025-12-15 04:56:43'),
(37, 26, 'San Miguel', 'Zamboanga del Sur', '909 Del Pilar Street', 'Philippines', '2025-12-15 03:41:27', '2025-12-15 04:56:43'),
(38, 22, 'Dumingag', 'Zamboanga del Sur', '981 Mabini Street', 'Philippines', '2025-12-15 04:06:43', '2025-12-15 04:56:43'),
(39, 1, 'Labangan', 'Zamboanga del Sur', '607 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(40, 3, 'San Miguel', 'Zamboanga del Sur', '412 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 06:52:00'),
(41, 4, 'Dumingag', 'Zamboanga del Sur', '13 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(42, 5, 'Molave', 'Zamboanga del Sur', '880 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(43, 6, 'Mahayag', 'Zamboanga del Sur', '932 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(44, 7, 'Aurora', 'Zamboanga del Sur', '608 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-16 04:45:09'),
(45, 8, 'Dumingag', 'Zamboanga del Sur', '891 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(46, 9, 'Pagadian', 'Zamboanga del Sur', '672 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-16 04:46:44'),
(47, 10, 'Pagadian', 'Zamboanga del Sur', '247 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(48, 11, 'Aurora', 'Zamboanga del Sur', '167 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(49, 12, 'Dumingag', 'Zamboanga del Sur', '160 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(50, 13, 'Mahayag', 'Zamboanga del Sur', '132 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(51, 14, 'Mahayag', 'Zamboanga del Sur', '285 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(52, 15, 'Dumingag', 'Zamboanga del Sur', 'Rizal Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 06:20:52'),
(53, 16, 'Labangan', 'Zamboanga del Sur', '23 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(54, 17, 'Aurora', 'Zamboanga del Sur', '86 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-16 12:51:09'),
(55, 18, 'Molave', 'Zamboanga del Sur', '530 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(56, 19, 'Tukuran', 'Zamboanga del Sur', '745 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(57, 20, 'Pagadian', 'Zamboanga del Sur', '837 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(58, 21, 'Labangan', 'Zamboanga del Sur', '892 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(59, 23, 'Dumingag', 'Zamboanga del Sur', '226 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(60, 25, 'Mahayag', 'Zamboanga del Sur', '185 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(61, 27, 'Dumingag', 'Zamboanga del Sur', '73 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(62, 28, 'Molave', 'Zamboanga del Sur', '797 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(63, 29, 'San Miguel', 'Zamboanga del Sur', '724 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(64, 30, 'San Miguel', 'Zamboanga del Sur', '833 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(65, 31, 'San Miguel', 'Zamboanga del Sur', '324 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(66, 32, 'Tukuran', 'Zamboanga del Sur', '681 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(67, 33, 'Aurora', 'Zamboanga del Sur', '232 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(68, 34, 'Labangan', 'Zamboanga del Sur', '364 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(69, 35, 'Labangan', 'Zamboanga del Sur', '164 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(70, 36, 'Aurora', 'Zamboanga del Sur', '667 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(71, 37, 'Mahayag', 'Zamboanga del Sur', 'Poblacion', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:34:05'),
(72, 38, 'Mahayag', 'Zamboanga del Sur', '6 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(73, 39, 'Aurora', 'Zamboanga del Sur', '377 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(74, 41, 'Dumingag', 'Zamboanga del Sur', '669 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(75, 42, 'Labangan', 'Zamboanga del Sur', '114 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(76, 43, 'Mahayag', 'Zamboanga del Sur', '901 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(77, 44, 'Mahayag', 'Zamboanga del Sur', '895 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(78, 45, 'Pagadian', 'Zamboanga del Sur', '153 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(79, 46, 'Pagadian', 'Zamboanga del Sur', '978 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(80, 49, 'SA LUGAR NGA WALA MO', 'Zamboanga del Sur', '149 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 07:03:37'),
(102, 51, 'DiMakita City', 'Tokyo', 'Barangay Liko', 'Japan', '2025-12-15 07:06:45', '2025-12-15 07:06:45');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_documents`
--

CREATE TABLE `alumni_documents` (
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('COR','COE','B_CERT') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `document_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alumni_documents`
--

INSERT INTO `alumni_documents` (`doc_id`, `user_id`, `document_type`, `file_path`, `rejection_reason`, `rejected_at`, `document_status`) VALUES
(160, 7, 'COE', 'uploads/documents/patalinghug_coe_eu2ek.pdf', NULL, NULL, 'Pending'),
(161, 9, 'COE', 'uploads/documents/omar_coe_oWs1s.pdf', NULL, NULL, 'Pending'),
(162, 9, 'COR', 'uploads/documents/omar_cor_OIRB1.pdf', NULL, NULL, 'Pending'),
(164, 17, 'COE', 'uploads/documents/tabaranza_coe_k5bpD.pdf', NULL, NULL, 'Pending'),
(165, 17, 'COR', 'uploads/documents/tabaranza_cor_V055r.pdf', NULL, NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_profile`
--

CREATE TABLE `alumni_profile` (
  `user_id` int(11) NOT NULL,
  `employment_status` enum('Employed','Self-Employed','Unemployed','Student','Employed & Student') DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `last_profile_update` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submission_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `last_submission_review` timestamp NULL DEFAULT NULL,
  `admin_reviewer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumni_profile`
--

INSERT INTO `alumni_profile` (`user_id`, `employment_status`, `photo_path`, `last_profile_update`, `submitted_at`, `submission_status`, `last_submission_review`, `admin_reviewer_id`) VALUES
(7, 'Employed', 'uploads/profile_photos/patalinghug_profile_63299.jpg', '2025-12-16 04:45:30', '2025-12-16 04:47:13', 'Pending', NULL, NULL),
(9, 'Employed & Student', 'uploads/profile_photos/omar_profile_d2ab6.jpg', '2025-12-16 04:46:44', '2025-12-16 15:11:01', 'Pending', NULL, NULL),
(17, 'Employed & Student', 'uploads/profile_photos/tabaranza_profile_b9c8d.jpg', '2025-12-16 12:53:47', '2025-12-16 15:58:02', 'Pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `education_info`
--

CREATE TABLE `education_info` (
  `education_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `degree_pursued` varchar(255) DEFAULT NULL,
  `start_year` year(4) DEFAULT NULL,
  `end_year` year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `education_info`
--

INSERT INTO `education_info` (`education_id`, `user_id`, `school_name`, `degree_pursued`, `start_year`, `end_year`) VALUES
(66, 9, 'Ateneo de Zamboanga', 'Masters in Kuan', '2025', '2027'),
(67, 17, 'Ateneo de Zamboanga', 'Masters in Kuan', '2025', '2027');

-- --------------------------------------------------------

--
-- Table structure for table `employment_info`
--

CREATE TABLE `employment_info` (
  `employment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_title_id` int(11) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `salary_range` enum('Below ₱10,000','₱10,000–₱20,000','₱20,000–₱30,000','₱30,000–₱40,000','₱40,000–₱50,000','Above ₱50,000') DEFAULT NULL,
  `business_type` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employment_info`
--

INSERT INTO `employment_info` (`employment_id`, `user_id`, `job_title_id`, `company_name`, `salary_range`, `business_type`, `company_address`) VALUES
(113, 7, 17, 'Marchan Tech Corporation', '₱10,000–₱20,000', NULL, 'LIGH 428, APHB Colony, Moula Ali'),
(114, 9, 6, 'Marchan Tech Corporation', '₱40,000–₱50,000', NULL, 'LIGH 428, APHB Colony, Moula Ali'),
(116, 17, 11, 'Marchan Tech Corporation', '₱20,000–₱30,000', NULL, 'LIGH 428, APHB Colony, Moula Ali');

-- --------------------------------------------------------

--
-- Table structure for table `job_titles`
--

CREATE TABLE `job_titles` (
  `job_title_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_titles`
--

INSERT INTO `job_titles` (`job_title_id`, `title`) VALUES
(1, 'AI Engineer'),
(11, 'Animator'),
(15, 'Business Analyst'),
(2, 'Cloud Engineer'),
(10, 'Cybersecurity Specialist'),
(5, 'Data Analyst'),
(9, 'Database Administrator'),
(13, 'DevOps Engineer'),
(22, 'Freelancer'),
(14, 'Front-End Developer'),
(18, 'Full Stack Developer'),
(17, 'Graphic Artist'),
(16, 'IT Consultant'),
(6, 'IT Support Specialist'),
(23, 'Marketing'),
(12, 'Mobile App Developer'),
(7, 'Network Administrator'),
(3, 'Software Engineer'),
(8, 'Systems Analyst'),
(4, 'Web Developer');

-- --------------------------------------------------------

--
-- Table structure for table `profile_rejection_reasons`
--

CREATE TABLE `profile_rejection_reasons` (
  `user_id` int(11) NOT NULL,
  `rejection_reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `update_log`
--

CREATE TABLE `update_log` (
  `log_id` int(11) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_id` int(11) NOT NULL,
  `update_type` enum('update','approve','reject') DEFAULT NULL,
  `update_details` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `update_log`
--

INSERT INTO `update_log` (`log_id`, `updated_by`, `updated_id`, `update_type`, `update_details`, `updated_at`) VALUES
(78, 47, 7, 'approve', 'Approved alumni documents', '2025-12-16 04:47:13'),
(79, 47, 9, 'reject', 'Rejected alumni documents - Reason: Upload again.', '2025-12-16 04:47:48'),
(80, 47, 9, 'update', 'Undo rejection - Reverted documents to pending status', '2025-12-16 05:00:32'),
(81, 47, 9, 'reject', 'Rejected alumni documents - Reason: Provide a clear file.', '2025-12-16 05:03:40'),
(82, 47, 7, 'update', 'Undo approval - Reverted documents to pending status', '2025-12-16 05:10:52'),
(83, 47, 9, 'update', 'Undo rejection - Reverted documents to pending status', '2025-12-16 05:13:40'),
(84, 47, 9, 'reject', 'Rejected alumni documents - Reason: Basta', '2025-12-16 05:13:58'),
(85, 47, 9, 'update', 'Undo rejection - Reverted documents to pending status', '2025-12-16 05:18:13'),
(86, 47, 0, 'reject', 'Rejected alumni documents - Reason: Document is expired or not current.', '2025-12-16 06:47:10'),
(87, 47, 0, 'reject', 'Rejected alumni documents - Reason: Document is blurry or illegible.', '2025-12-16 06:47:19'),
(88, 47, 0, 'approve', 'Approved alumni documents', '2025-12-16 06:47:25'),
(89, 47, 0, 'approve', 'Approved alumni documents', '2025-12-16 06:47:28'),
(90, 47, 0, 'reject', 'Rejected alumni documents - Reason: Document is expired or not current.', '2025-12-16 12:37:54'),
(91, 47, 0, 'reject', 'Rejected alumni documents - Reason: hi', '2025-12-16 12:54:32'),
(92, 47, 0, 'approve', 'Approved alumni documents', '2025-12-16 12:54:37'),
(93, 47, 17, 'approve', 'Approved specific document (ID: 164)', '2025-12-16 13:01:21'),
(94, 47, 17, 'approve', 'Approved specific document (ID: 165)', '2025-12-16 13:03:02'),
(95, 47, 17, 'update', 'Undo approval - Reverted document to pending status (ID: 165)', '2025-12-16 13:03:05'),
(96, 47, 17, 'reject', 'Rejected specific document (ID: 165) - Reason: Basta.', '2025-12-16 13:03:18'),
(97, 47, 17, 'update', 'Undo approval - Reverted document to pending status (ID: 164)', '2025-12-16 13:14:28'),
(98, 47, 17, 'update', 'Undo rejection - Reverted document to pending status (ID: 165)', '2025-12-16 13:24:45'),
(99, 47, 17, 'approve', 'Approved specific document (ID: Array)', '2025-12-16 13:30:51'),
(100, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 13:32:28'),
(101, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 13:32:35'),
(102, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 13:34:07'),
(103, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 13:34:14'),
(104, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 13:52:30'),
(105, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 13:55:31'),
(106, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 13:55:36'),
(107, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 13:55:45'),
(108, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 14:45:55'),
(109, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 14:46:11'),
(110, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 15:01:19'),
(111, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 15:01:26'),
(112, 47, 9, 'approve', 'Approved all alumni documents', '2025-12-16 15:11:01'),
(113, 47, 9, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 15:57:09'),
(114, 47, 17, 'approve', 'Approved all alumni documents', '2025-12-16 15:58:02'),
(115, 47, 17, 'update', 'Undo approval - Reverted all documents to pending status', '2025-12-16 15:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(200) NOT NULL,
  `role` enum('admin','alumni') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_id` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `program` varchar(100) DEFAULT 'BSIT',
  `batch_year` year(4) DEFAULT NULL,
  `citizenship` varchar(100) NOT NULL DEFAULT 'Filipino',
  `civil_status` enum('Single','Married','Widowed','Separated','Divorced') NOT NULL DEFAULT 'Single',
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `first_name`, `middle_name`, `last_name`, `suffix`, `created_at`, `student_id`, `date_of_birth`, `gender`, `program`, `batch_year`, `citizenship`, `civil_status`, `contact_number`) VALUES
(1, 'josieoliveros013@gmail.com', '$2y$10$ehX/E2zWveVcemhEh6PmLOGJM8HlV2g5jePA7LEfwgftW1DJZ8Uz.', 'alumni', 'Josie', 'Gumera', 'Oliveros', '', '2025-10-13 13:59:40', '2020-00123', '1997-01-11', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09163046602'),
(2, 'quienbisnar@gmail.com', '$2y$10$iVt2fcpR/Z19c8jTMKyA5OgBFCYb5GK44KCQMXmagMMxDstzdparC', 'alumni', 'Quien', 'Bendula', 'Bisnar', '', '2025-10-13 11:48:34', '2020-00124', '2002-12-02', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09857825230'),
(3, 'aseneroglaiza@gmail.com', '$2y$10$UHN1b.vJAkh26l4TdpkxT.Zfsvi3DgvgH5m41PRIGAnMefSpfufhO', 'alumni', 'George', 'Corge', 'Jorge', '', '2025-10-13 14:01:10', '2020-01011', '2000-11-02', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09532677351'),
(4, 'glowentanamanmil08@gmail.com', '$2y$10$.X6JG2ZcAC.Oi3RLDciATehWeH1FxfvrB4NBhnT8Eqwy9dkcT1TL.', 'alumni', 'Glowen', '', 'Tanaman', '', '2025-10-17 08:59:39', '2020-11141', '2001-07-16', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09426456224'),
(5, 'repe.ronaldojr@gmail.com', '$2y$10$AV5HSa53xpJRPykHLCQhuei9q5Rtk7SMFfs.yS9riewWH/d0hylKC', 'alumni', 'Ronaldo', 'Montemor', 'Repe', 'Jr.', '2025-10-17 08:59:39', '2020-09898', '2000-04-14', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09732322022'),
(6, 'davelabadan1@gmail.com', '$2y$10$h6Xx10eFsuv0vhUk9ApM/OmLJ1YHyYRGx.lAb.0iSmuHFZ8NpwjO2', 'alumni', 'China Dave', 'Jumuad', 'Labadan', '', '2025-10-17 08:59:39', '2020-00004', '2000-03-11', 'Male', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Single', '09382241467'),
(7, 'joangracep@gmail.com', '$2y$10$M1kkyVDtSJHEBwXmuEwNmO.IHkK/S5jmHU7Xtx9lTJthD3qOuPZmG', 'alumni', 'Joan Grace', 'Mancera', 'Patalinghug', '', '2025-10-17 08:59:39', '2020-00005', '1999-11-22', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09714241301'),
(8, 'marchanmayang687@gmail.com', '$2y$10$PvYQQ4DZnVHa8Z5zqYxEEOGq7.5yI2TkUPbIoTVlPzcjCXzwX8OMG', 'alumni', 'Marian', 'Getigan', 'Marchan', '', '2025-10-17 08:59:39', '2020-00006', '2003-12-25', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09424482135'),
(9, 'jaafarj.omar@gmail.com', '$2y$10$aBrYT3wN51F1yKGoV/2age.qI5Mz3JXzD0j//TazqSLjsmsfBouMe', 'alumni', 'Jaafar', '', 'Omar', '', '2025-11-05 13:21:52', '2020-00007', '1997-03-09', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Divorced', '09979686804'),
(10, 'buhianreymark@gmail.com', '$2y$10$rkACLYX4XRZkULTFpJefx..w..Cc0rre7xgsv0FpGc4Y070R6Aaiu', 'alumni', 'Reymark', '', 'Buhian', '', '2025-10-17 09:01:25', '2020-00008', '1997-08-08', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09624987644'),
(11, 'sairabelarmino1@gmail.com', '$2y$10$pnjkHN4SA.MxIkO2YV3xFeHK7vTemeFy/FnswuVfDp4n/J8jDjG12', 'alumni', 'Saira', 'Lambayan', 'Belarmino', '', '2025-10-17 09:05:44', '2020-00009', '1999-05-24', 'Female', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09185877842'),
(12, 'asutillajohn445@gmail.com', '$2y$10$D9Y6u6QAcQfq6ZhPHOGwyOpeuQSBg.qXkzqdsrqZPtZR9ZtvSXVhS', 'alumni', 'John Marnell', 'Lamban', 'Asutilla', '', '2025-10-17 09:05:44', '2020-00010', '1999-04-01', 'Male', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Single', '09054426306'),
(13, 'chlsywtnb001@gmail.com', '$2y$10$EvSxw6YxgkaV.E1NuSD1PetRgxiTdbDONJftJIEgevEZI65b3s7Te', 'alumni', 'Maureen', 'Perdiguez', 'Guadalquiver', '', '2025-10-17 09:05:44', '2020-00011', '2003-10-17', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09714498039'),
(14, 'madumbkiki@gmail.com', '$2y$10$HWCc9A8dNCQqEbE80YBQ/ead4ShMD7kRb0REUZq1U7xVDeZu9Asii', 'alumni', 'Kia', 'Banac', 'Balucos', '', '2025-10-17 09:09:02', '2020-00012', '2001-04-12', 'Female', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09409211305'),
(15, 'jesseltapdasan3@gmail.com', '$2y$10$LRuq4uVRQAHsnXUalvlV6uU139FmSMDUlT.B4LV/kb3u9m7TLyiOa', 'alumni', 'Jessel Rose', 'Arroyo', 'Tapdasan', '', '2025-10-17 09:09:02', '2020-00013', '2002-05-19', 'Female', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09902562440'),
(16, 'yolemkieth@gmail.com', '$2y$10$LsoAByT1LT.asfHz.YNCAulAkYFF/255qOnX/ItjzCdbCDWemnJi2', 'alumni', 'Yolem Kieth', 'Martil', 'Salarda', '', '2025-10-17 09:09:02', '2020-00014', '2001-04-13', 'Male', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09285178316'),
(17, 'fuyaaa123@gmail.com', '$2y$10$z1SWM1/Da6eCA6rovryLk.hP3151/ug51ecvxo4I5R72rGt9BTjRG', 'alumni', 'Famme', 'Oculam', 'Tabaranza', '', '2025-10-17 09:09:02', '2020-00015', '2004-08-18', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09718204290'),
(18, 'ticmonmariel9@gmail.com', '$2y$10$v29n.z1o20nQbZRGhWGfSOKtzd0SVuRH7kuU.MXr/6lIbiocOA5Sa', 'alumni', 'Mariel', 'Manaba', 'Ticmon', '', '2025-10-17 09:23:41', '2020-00016', '2003-02-03', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09735486534'),
(19, 'jennethcorcelles@gmail.com', '$2y$10$EIS.HCxWmN0N0agHPDy2BeVvz/vvkaDm1D7GBi309m7KbPEMAArke', 'alumni', 'Jenneth', 'Donoso', 'Corcelles', '', '2025-10-17 12:01:39', '2020-00017', '2000-08-03', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09522819830'),
(20, 'drexzelescoreal@gmail.com', '$2y$10$nj7WTWt9ueBZF5YmZHa.F.9WGyNSzFh6WH93WFYAaIX1ZJSY7kHJ.', 'alumni', 'Drexzel', 'Corcelles', 'Escoreal', '', '2025-10-17 12:01:39', '2020-00018', '2001-04-06', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09407639581'),
(21, 'danrylboncales@gmail.com', '$2y$10$EkIQueUtH4eI.KK.Ef49seYbAu6XCStVKjVk16X/33BuzsQTgt0s2', 'alumni', 'Danryl James', 'Boncales', 'Usa', '', '2025-10-17 12:01:39', '2020-00019', '2003-04-25', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09469738444'),
(22, 'davemadrazo7@gmail.com', '$2y$10$6U8i3VrEMwuhzax5GKUOi.JOZbWqok4/6hKld3CL44i.aYxTe12Mq', 'alumni', 'Dave Jay', 'Quimada', 'Madrazo', '', '2025-10-17 12:01:39', '2020-00020', '1996-02-27', 'Male', 'Bachelor of Science in Information Technology', '2018', 'Filipino', 'Single', '09125773511'),
(23, 'salvadorvincecyrus@gmail.com', '$2y$10$2rxmPdtdOr8NgmNmvVPiEOgxKeAC.OQTQw0C58EY5/CnEzoeCrKDi', 'alumni', 'Vince Cyrus', '', 'Salvador', '', '2025-10-17 15:13:32', '2020-00021', '2002-02-13', 'Male', 'Bachelor of Science in Information Technology', '2019', 'Filipino', 'Single', '09219652251'),
(25, 'rthdbl672@gmail.com', '$2y$10$5MS6n8OUf8kJ2D3eHCAfwecHkuIAXKlkB8vrq5dCIwitcNfdyvcni', 'alumni', 'Arth', 'Alimpos', 'Dablo', '', '2025-11-10 13:24:20', '2020-00022', '1996-10-10', 'Male', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09720940755'),
(26, 'dexeneblisk@gmail.com', '$2y$10$lDxS7aqu/dWetRJ8sOV6BOCFxNHsqv2N8osGzM7cGTEXw4h2/LR4K', 'alumni', 'Dexene Bliss', 'Kilat', 'Andrin', '', '2025-11-10 13:24:20', '2020-00023', '2002-03-16', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09945747053'),
(27, 'anjofernandez0705@gmail.com', '$2y$10$mGcZLCHvtbYb0GpPPnq3v.eUFzKx.LxoPEtOBNUIg4dVSOSlt3YqW', 'alumni', 'Anjo', 'Abella', 'Fernandez', '', '2025-11-10 13:24:20', '2020-00024', '2000-06-19', 'Male', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Single', '09565913030'),
(28, 'jancarlorabe9@gmail.com', '$2y$10$JjvjQDB5HnysBAaExlAuvekp7UjkvxGJqaJt4FUe/Qr9OuDmiCLLy', 'alumni', 'Jan Carlo', 'Bernacibo', 'Rabe', '', '2025-11-10 13:24:20', '2020-00025', '1998-05-06', 'Female', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09992324023'),
(29, 'catalanvincent222@gmail.com', '$2y$10$EjziH7HJLkjcO/t5w5CXy.a4mmAnYO..nZAbgDXLidU9iomVkcl3m', 'alumni', 'Vincent', 'Dico', 'Catalan', '', '2025-11-10 13:24:20', '2020-00026', '1999-01-08', 'Male', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09263881056'),
(30, 'keishasoler05@gmail.com', '$2y$10$4l6x1jQnh1fG3hThk/XGI.MjDqcygy44M./ZUC6HbEOd5Z/MaSg8e', 'alumni', 'Keisha Nicole', 'Palino', 'Soler', '', '2025-11-11 01:57:53', '2020-00027', '2003-06-21', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09342433244'),
(31, 'reginpunay@gmail.com', '$2y$10$RPk4PT7LMcjzM9qhmqipuedvzOR5.CKxaJmGRZlkY7bbNlJ/FbvpO', 'alumni', 'Regin', 'Punay', 'Angala', '', '2025-11-11 01:57:53', '2020-00028', '2000-06-24', 'Female', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Single', '09920523081'),
(32, 'jylsam123@gmail.com', '$2y$10$EtUiAEv/t5icCn/S.0qbie3F2hSzJDmzPBrEW9Hbpj0zYYTvd2hrW', 'alumni', 'Jylsam', '', 'Quirog', '', '2025-11-11 01:57:53', '2020-00029', '1999-09-27', 'Female', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09575315706'),
(33, 'carlowedeala2020@gmail.com', '$2y$10$izxs4rpeIj7BKtUblvckLehRqyG8vdZ14MF8QK5eMdHTtUmk5hPuy', 'alumni', 'Carlowe', 'Delusa', 'Deala', '', '2025-11-11 01:57:53', '2020-00030', '2000-06-06', 'Male', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09115009320'),
(34, 'nathanielpiraman@gmail.com', '$2y$10$HgZbThTHwqouQvhzhBWXGOKaQu/AgjMReSuRUb1gOEdnWegme15zm', 'alumni', 'Nathaniel', '', 'Piraman', '', '2025-11-11 01:57:53', '2020-00031', '2000-09-14', 'Male', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09849099510'),
(35, 'sebreroaxcylxyron@gmail.com', '$2y$10$Kr.PEnBlDQorPRkskBkGquNaB/cLV4u0Cq/J1NuSL.4zv0PasqJc2', 'alumni', 'Axcyl Xyron', '', 'Sebrero', '', '2025-11-11 01:57:53', '2020-00032', '2001-03-03', 'Male', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09900469621'),
(36, 'Khristenecruz@gmail.com', '$2y$10$zxAZtG0erkGI2BVIgl7mEuPWVDKUBd/fluIOmZmvJYHkyjM/kXmTm', 'alumni', 'Khristene', '', 'Suyang', '', '2025-11-11 01:57:53', '2020-00033', '1996-11-28', 'Male', 'Bachelor of Science in Information Technology', '2019', 'Filipino', 'Single', '09955049607'),
(37, 'gaminghr209@gmail.com', '$2y$10$RNGAubZ1zm9LHYxZpbBKsuC.plR0ghVmB4SCYytILRtGwe8KEx5Ru', 'alumni', 'Raymart', 'Timogan', 'Upao', '', '2025-11-11 01:57:53', '2020-00034', '2001-01-20', 'Female', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09073839205'),
(38, 'lloydandiason45@gmail.com', '$2y$10$ylZvPSFZyBhvORRKeFR3KOjpXZbZTLghao48NI0qWLZsaEye7vV9K', 'alumni', 'Lloyd', '', 'Andiason', '', '2025-11-11 02:08:08', '2020-00035', '1998-06-06', 'Male', 'Bachelor of Science in Information Technology', '2019', 'Filipino', 'Single', '09504047234'),
(39, 'andrinairagrace@gmail.com', '$2y$10$ILdyOKViWrV68U5qt84iIe2C40709YneF/FxO.rq0Hwj2XOctTcgi', 'alumni', 'Aira Grace', '', 'Andrin', '', '2025-11-11 02:08:08', '2020-00036', '1999-12-18', 'Female', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09298718584'),
(40, 'liborwilfred@gmail.com', '$2y$10$mpsuvqiB/uw/aK0bRV5l4eS.IS9XepggwdQ4cx1U5JovWeXfHQB7S', 'alumni', 'Wilfredo', 'Dajao', 'Libor', 'Jr.', '2025-11-11 02:08:08', '2020-00037', '2002-05-05', 'Male', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09981451253'),
(41, 'jonalyntabunyag5@gmail.com', '$2y$10$0Bz6kzL/yb7mQuRTR5jxJevKbsxIGJWdPv9bjoy8evLAegELgVib.', 'alumni', 'Jonalyn', 'Umambac', 'Tabunyag', '', '2025-11-11 02:08:08', '2020-00038', '2000-09-14', 'Female', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09011100542'),
(42, 'rivenllego@gmail.com', '$2y$10$gFXPvwr4JX2UqZTVdhfNZuDaT/AzNwXbYay8vedvT0hEAErZAVpZG', 'alumni', 'Rizal Ven', '', 'Llego', '', '2025-11-11 02:08:08', '2020-00039', '2002-10-12', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09111148984'),
(43, 'Renchauxtero24@gmail.com', '$2y$10$dHuhcVAve1NDNpgZ4Z9JoO1t6/r4z75kaDl6H47piwXw6Pgyomn2C', 'alumni', 'Rench', '', 'Auxtero', '', '2025-11-11 02:08:08', '2020-00040', '2000-11-22', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09522443323'),
(44, 'ivannjadecmartel@gmail.com', '$2y$10$QiTGkhw4eZPgMOm/dfckQ.10QOwfE.cAFYBILTnXFw1rFyN/vGCc.', 'alumni', 'Ivann Jade', '', 'Martel', '', '2025-11-11 02:08:08', '2020-00041', '1997-05-23', 'Male', 'Bachelor of Science in Information Technology', '2018', 'Filipino', 'Single', '09278769697'),
(45, 'ngllrosall@gmail.com', '$2y$10$k6yAD3V9isaZ/cO5cqtVzuMfs4pxCkxbmS0Iy4RUq0id.hSfOwZq2', 'alumni', 'Angel', 'Estallo', 'Rosal', '', '2025-11-11 02:08:08', '2020-00042', '1995-12-09', 'Female', 'Bachelor of Science in Information Technology', '2018', 'Filipino', 'Single', '09826518545'),
(46, 'johnmira911@gmail.com', '$2y$10$fFq50O9MRFubzmzh8Ad2m.Z7kKCgxfOX2Fx24agrRtnTTjMbnh3Cy', 'alumni', 'John Kristoffer', 'Payapa', 'Mira', '', '2025-11-11 02:08:08', '2020-00043', '1996-09-03', 'Male', 'Bachelor of Science in Information Technology', '2018', 'Filipino', 'Single', '09296283667'),
(47, 'alumtrak@gmail.com', '$2y$10$l7kVB6F/10PK5er5GZZEO.bV1L2RyqQ5HlW4NjrS.8es46OQKr97C', 'admin', 'Jayson', 'Rabe', 'Ungang', '', '2025-12-01 14:15:17', NULL, NULL, NULL, '', NULL, '', '', NULL),
(49, 'betatest@test.com', '$2y$10$q9dNK1iwAowaCaqhJfZaA.l8IsdoyJ8vnHX9vPrGQDiiyVEmW.65m', 'alumni', 'Alumni Test', 'Salazar', 'Garcia', 'Jr', '2025-12-11 07:18:33', '2025-0000', '2001-12-18', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09001862729'),
(50, 'betauser1@test.com', '$2y$10$q9dNK1iwAowaCaqhJfZaA.l8IsdoyJ8vnHX9vPrGQDiiyVEmW.65m', 'alumni', 'Beta', NULL, 'Testing', NULL, '2025-12-15 06:50:46', '2025-1002', '2001-12-13', NULL, 'Bachelor of Science in Information Technology', '2019', 'Filipino', 'Single', '09879879877'),
(51, 'betauser2@test.com', '$2y$10$q9dNK1iwAowaCaqhJfZaA.l8IsdoyJ8vnHX9vPrGQDiiyVEmW.65m', 'alumni', 'tan', 'nat', 'ant', '', '2025-12-15 06:58:35', '2010-0001', '2001-12-05', NULL, 'Bachelor of Science in Information Technology', '2018', 'Filipino', 'Single', '09987321654');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `alumni_address`
--
ALTER TABLE `alumni_address`
  ADD PRIMARY KEY (`address_id`),
  ADD UNIQUE KEY `idx_unique_user_address` (`user_id`);

--
-- Indexes for table `alumni_documents`
--
ALTER TABLE `alumni_documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `alumni_profile`
--
ALTER TABLE `alumni_profile`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_employment_status` (`employment_status`),
  ADD KEY `fk_admin_reviewer` (`admin_reviewer_id`);

--
-- Indexes for table `education_info`
--
ALTER TABLE `education_info`
  ADD PRIMARY KEY (`education_id`),
  ADD KEY `idx_education_user` (`user_id`);

--
-- Indexes for table `employment_info`
--
ALTER TABLE `employment_info`
  ADD PRIMARY KEY (`employment_id`),
  ADD KEY `fk_employment_alumni` (`user_id`),
  ADD KEY `fk_employment_job` (`job_title_id`);

--
-- Indexes for table `job_titles`
--
ALTER TABLE `job_titles`
  ADD PRIMARY KEY (`job_title_id`),
  ADD UNIQUE KEY `title` (`title`);

--
-- Indexes for table `profile_rejection_reasons`
--
ALTER TABLE `profile_rejection_reasons`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `update_log`
--
ALTER TABLE `update_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_user_updt` (`updated_by`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_role_batch` (`role`,`batch_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=234;

--
-- AUTO_INCREMENT for table `alumni_address`
--
ALTER TABLE `alumni_address`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `alumni_documents`
--
ALTER TABLE `alumni_documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `education_info`
--
ALTER TABLE `education_info`
  MODIFY `education_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `employment_info`
--
ALTER TABLE `employment_info`
  MODIFY `employment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `job_titles`
--
ALTER TABLE `job_titles`
  MODIFY `job_title_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `update_log`
--
ALTER TABLE `update_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  ADD CONSTRAINT `fk_alumni_activity_log_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alumni_address`
--
ALTER TABLE `alumni_address`
  ADD CONSTRAINT `fk_alumni_address_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `alumni_documents`
--
ALTER TABLE `alumni_documents`
  ADD CONSTRAINT `fk_alumni_documents_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `alumni_profile`
--
ALTER TABLE `alumni_profile`
  ADD CONSTRAINT `Pkfk_user_alumni` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_reviewer` FOREIGN KEY (`admin_reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `education_info`
--
ALTER TABLE `education_info`
  ADD CONSTRAINT `fk_education_info_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `employment_info`
--
ALTER TABLE `employment_info`
  ADD CONSTRAINT `employment_info_ibfk_1` FOREIGN KEY (`job_title_id`) REFERENCES `job_titles` (`job_title_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_employment_info_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_employment_job` FOREIGN KEY (`job_title_id`) REFERENCES `job_titles` (`job_title_id`);

--
-- Constraints for table `profile_rejection_reasons`
--
ALTER TABLE `profile_rejection_reasons`
  ADD CONSTRAINT `profile_rejection_reasons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `update_log`
--
ALTER TABLE `update_log`
  ADD CONSTRAINT `fk_log_user_updt` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
