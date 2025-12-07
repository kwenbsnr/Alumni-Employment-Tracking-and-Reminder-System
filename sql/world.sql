-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 07, 2025 at 08:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `world`
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
(2, 34, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-29 05:53:48'),
(3, 34, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 05:53:48'),
(4, 34, 'profile_updated', 'Updated personal information and address', '2025-11-29 05:53:48'),
(5, 18, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 11:03:29'),
(6, 18, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-29 11:03:29'),
(7, 18, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 11:03:29'),
(8, 18, 'profile_updated', 'Updated personal information and address', '2025-11-29 11:03:29'),
(9, 21, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 11:10:08'),
(10, 21, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-29 11:10:08'),
(11, 21, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-29 11:10:08'),
(12, 21, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 11:10:08'),
(13, 21, 'profile_updated', 'Updated personal information and address', '2025-11-29 11:10:08'),
(14, 21, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 11:12:06'),
(15, 21, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-29 11:12:06'),
(16, 21, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-29 11:12:06'),
(17, 21, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 11:12:06'),
(18, 21, 'profile_updated', 'Updated personal information and address', '2025-11-29 11:12:06'),
(19, 16, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 11:26:55'),
(20, 16, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-29 11:26:55'),
(21, 16, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-29 11:26:55'),
(22, 16, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 11:26:55'),
(23, 16, 'profile_updated', 'Updated personal information and address', '2025-11-29 11:26:55'),
(24, 37, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 11:49:33'),
(25, 37, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-29 11:49:33'),
(26, 37, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 11:49:33'),
(27, 37, 'profile_updated', 'Updated personal information and address', '2025-11-29 11:49:33'),
(28, 37, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 11:50:25'),
(29, 37, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-29 11:50:25'),
(30, 37, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 11:50:25'),
(31, 37, 'profile_updated', 'Updated personal information and address', '2025-11-29 11:50:25'),
(32, 14, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-29 12:01:06'),
(33, 14, 'profile_photo_updated', 'Updated profile picture', '2025-11-29 12:01:06'),
(34, 14, 'profile_updated', 'Updated personal information and address', '2025-11-29 12:01:06'),
(35, 22, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 08:47:35'),
(36, 22, 'document_uploaded', 'Uploaded Business Certificate', '2025-11-30 08:47:35'),
(37, 22, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 08:47:35'),
(38, 22, 'profile_updated', 'Updated personal information and address', '2025-11-30 08:47:35'),
(39, 22, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 08:50:13'),
(40, 22, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 08:50:13'),
(41, 22, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 08:50:13'),
(42, 22, 'profile_updated', 'Updated personal information and address', '2025-11-30 08:50:13'),
(43, 22, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 08:53:36'),
(44, 22, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 08:53:36'),
(45, 22, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 08:53:36'),
(46, 22, 'profile_updated', 'Updated personal information and address', '2025-11-30 08:53:36'),
(47, 22, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 08:54:39'),
(48, 22, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 08:54:39'),
(49, 22, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 08:54:39'),
(50, 22, 'profile_updated', 'Updated personal information and address', '2025-11-30 08:54:39'),
(51, 22, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 08:58:09'),
(52, 22, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 08:58:09'),
(53, 22, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 08:58:09'),
(54, 22, 'profile_updated', 'Updated personal information and address', '2025-11-30 08:58:09'),
(55, 22, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:05:24'),
(56, 22, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 09:05:24'),
(57, 22, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:05:24'),
(58, 22, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:05:24'),
(59, 35, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:07:22'),
(60, 35, 'document_uploaded', 'Uploaded Business Certificate', '2025-11-30 09:07:22'),
(61, 35, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:07:22'),
(62, 35, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:07:22'),
(63, 11, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:13:33'),
(64, 11, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-30 09:13:33'),
(65, 11, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:13:33'),
(66, 11, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:13:33'),
(67, 23, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:31:50'),
(68, 23, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 09:31:50'),
(69, 23, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-30 09:31:50'),
(70, 23, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:31:50'),
(71, 23, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:31:50'),
(72, 34, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:35:04'),
(73, 34, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 09:35:04'),
(74, 34, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:35:04'),
(75, 34, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:35:04'),
(76, 8, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:36:12'),
(77, 8, 'document_uploaded', 'Uploaded Business Certificate', '2025-11-30 09:36:12'),
(78, 8, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:36:12'),
(79, 8, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:36:12'),
(80, 40, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 09:36:56'),
(81, 40, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 09:36:56'),
(82, 40, 'profile_updated', 'Updated personal information and address', '2025-11-30 09:36:56'),
(83, 14, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 10:11:02'),
(84, 14, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 10:11:02'),
(85, 14, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 10:11:02'),
(86, 14, 'profile_updated', 'Updated personal information and address', '2025-11-30 10:11:02'),
(87, 12, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:17:16'),
(88, 12, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 13:17:16'),
(89, 12, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 13:17:16'),
(90, 12, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:17:16'),
(91, 10, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:20:08'),
(92, 10, 'document_uploaded', 'Uploaded Business Certificate', '2025-11-30 13:20:08'),
(93, 10, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 13:20:08'),
(94, 10, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:20:08'),
(95, 10, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:20:56'),
(96, 10, 'document_uploaded', 'Uploaded Business Certificate', '2025-11-30 13:20:56'),
(97, 10, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:20:56'),
(98, 33, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:34:11'),
(99, 33, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-30 13:34:11'),
(100, 33, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 13:34:11'),
(101, 33, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:34:12'),
(102, 13, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:46:37'),
(103, 13, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 13:46:37'),
(104, 13, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-30 13:46:37'),
(105, 13, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 13:46:37'),
(106, 13, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:46:37'),
(107, 6, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:54:11'),
(108, 6, 'profile_photo_updated', 'Updated profile picture', '2025-11-30 13:54:11'),
(109, 6, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:54:11'),
(110, 6, 'profile_submitted', 'Alumni submitted profile for review', '2025-11-30 13:56:54'),
(111, 6, 'document_uploaded', 'Uploaded Certificate of Employment (COE)', '2025-11-30 13:56:54'),
(112, 6, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-11-30 13:56:54'),
(113, 6, 'profile_updated', 'Updated personal information and address', '2025-11-30 13:56:54'),
(114, 2, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-04 08:13:08'),
(115, 2, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-04 08:20:39'),
(116, 2, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-04 08:36:09'),
(117, 2, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-12-04 08:36:16'),
(118, 2, 'profile_photo_updated', 'Updated profile picture', '2025-12-04 08:36:16'),
(119, 2, 'profile_updated', 'Updated personal information and worldwide address', '2025-12-04 08:36:16'),
(120, 13, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-04 09:20:34'),
(121, 13, 'profile_updated', 'Updated personal information and worldwide address', '2025-12-04 09:20:40'),
(122, 17, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-04 12:21:11'),
(123, 17, 'profile_photo_updated', 'Updated profile picture', '2025-12-04 12:21:22'),
(124, 17, 'profile_updated', 'Updated personal information and worldwide address', '2025-12-04 12:21:22'),
(130, 25, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-05 14:15:57'),
(131, 25, 'document_uploaded', 'Uploaded Business Certificate', '2025-12-05 14:16:03'),
(132, 25, 'profile_photo_updated', 'Updated profile picture', '2025-12-05 14:16:03'),
(133, 25, 'profile_updated', 'Updated personal information and worldwide address', '2025-12-05 14:16:03'),
(134, 9, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-05 16:39:23'),
(135, 9, 'profile_submitted', 'Alumni submitted profile for review', '2025-12-05 16:53:24'),
(136, 9, 'document_uploaded', 'Uploaded Certificate of Registration (COR)', '2025-12-05 16:53:30'),
(137, 9, 'profile_photo_updated', 'Updated profile picture', '2025-12-05 16:53:30'),
(138, 9, 'profile_updated', 'Updated personal information and worldwide address', '2025-12-05 16:53:30');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_documents`
--

CREATE TABLE `alumni_documents` (
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('COR','COE','B_CERT') NOT NULL,
  `file_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alumni_documents`
--

INSERT INTO `alumni_documents` (`doc_id`, `user_id`, `document_type`, `file_path`) VALUES
(113, 2, 'COR', 'uploads/cor/Bisnar_COR.pdf'),
(117, 25, 'B_CERT', 'uploads/business/Dablo_b_cert.pdf'),
(118, 9, 'COR', 'uploads/cor/Omar_COR.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_profile`
--

CREATE TABLE `alumni_profile` (
  `user_id` int(11) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `employment_status` enum('Employed','Self-Employed','Unemployed','Student','Employed & Student') DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `last_profile_update` timestamp NULL DEFAULT NULL,
  `submission_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumni_profile`
--

INSERT INTO `alumni_profile` (`user_id`, `contact_number`, `employment_status`, `photo_path`, `last_profile_update`, `submission_status`, `rejection_reason`, `rejected_at`, `submitted_at`) VALUES
(2, '09367891027', 'Student', 'uploads/photos/Bisnar_profile.png', '2025-12-04 08:36:09', 'Rejected', 'Degree pursued information unclear', '2025-12-04 13:13:07', '2025-12-04 08:36:09'),
(9, '09514715204', 'Student', 'uploads/photos/Omar_profile.png', '2025-12-05 16:53:24', 'Pending', NULL, NULL, '2025-12-05 16:53:24'),
(17, '09514715204', 'Unemployed', 'uploads/photos/Tabaranza_profile.png', '2025-12-04 12:21:11', 'Approved', NULL, NULL, '2025-12-05 16:55:43'),
(25, '09121112124', 'Self-Employed', 'uploads/photos/Dablo_profile.png', '2025-12-05 14:15:57', 'Pending', NULL, NULL, '2025-12-05 14:15:57');

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
(45, 2, 'University of Sto. Tomas', 'gfvdfv', '2025', '2027'),
(47, 9, 'University of Sto. Tomas', 'PhD', '2023', '2028');

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
(86, 25, NULL, '', '₱30,000–₱40,000', 'Construction / Carpentry / Electrical', '');

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
-- Table structure for table `submission_status`
--

CREATE TABLE `submission_status` (
  `submission_id` int(11) NOT NULL,
  `is_open` tinyint(1) DEFAULT 0,
  `manual_override` tinyint(1) DEFAULT 0,
  `open_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `last_updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submission_status`
--

INSERT INTO `submission_status` (`submission_id`, `is_open`, `manual_override`, `open_date`, `close_date`, `created_by`, `last_updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL, 47, NULL, '2025-12-05 08:20:35', '2025-12-05 13:58:02'),
(2, 1, 1, NULL, NULL, 1, NULL, '2025-12-05 09:46:06', '2025-12-05 13:58:02');

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
(1, 3, 2, '', NULL, '2025-10-17 13:12:26'),
(2, 3, 2, '', NULL, '2025-10-17 13:12:34'),
(3, 3, 1, '', NULL, '2025-10-17 13:14:10'),
(4, 3, 1, '', NULL, '2025-10-17 13:32:13'),
(5, 3, 1, '', NULL, '2025-10-17 13:36:26'),
(6, 3, 2, '', NULL, '2025-10-17 14:04:09'),
(7, 3, 2, '', NULL, '2025-10-17 14:13:06'),
(8, 3, 2, '', NULL, '2025-10-17 14:13:12'),
(9, 3, 1, '', NULL, '2025-10-17 14:13:30'),
(10, 3, 1, '', NULL, '2025-10-17 14:15:29'),
(11, 3, 5, '', NULL, '2025-10-17 14:37:06'),
(12, 3, 5, '', NULL, '2025-10-17 15:02:26'),
(13, 3, 5, '', NULL, '2025-10-17 15:02:32'),
(14, 3, 5, '', NULL, '2025-10-17 23:22:03'),
(15, 4, 2, '', NULL, '2025-10-28 08:26:57'),
(16, 4, 14, 'approve', NULL, '2025-11-10 08:33:49'),
(17, 4, 10, 'approve', NULL, '2025-11-10 08:34:42'),
(18, 3, 7, 'approve', NULL, '2025-11-15 11:06:22'),
(19, 3, 16, 'approve', NULL, '2025-11-15 13:22:28'),
(20, 3, 12, 'approve', NULL, '2025-11-15 13:27:54'),
(21, 3, 20, 'approve', NULL, '2025-11-15 13:33:07'),
(22, 3, 7, 'reject', NULL, '2025-11-16 13:03:33'),
(23, 3, 18, 'reject', NULL, '2025-11-16 13:05:27'),
(24, 3, 18, 'reject', NULL, '2025-11-18 01:38:19'),
(25, 3, 1, 'reject', NULL, '2025-11-18 01:39:37'),
(26, 3, 8, 'reject', NULL, '2025-11-18 01:41:39'),
(27, 3, 8, 'approve', NULL, '2025-11-18 01:46:57'),
(28, 3, 18, 'approve', NULL, '2025-11-18 02:20:46'),
(29, 3, 7, 'reject', NULL, '2025-11-18 02:21:09'),
(30, 3, 7, 'reject', NULL, '2025-11-18 04:17:56'),
(31, 3, 7, 'approve', NULL, '2025-11-18 06:25:57'),
(32, 3, 8, 'reject', NULL, '2025-11-19 10:31:08'),
(33, 3, 8, 'reject', NULL, '2025-11-19 13:00:14'),
(34, 3, 8, 'reject', NULL, '2025-11-19 13:37:24'),
(35, 3, 12, 'reject', NULL, '2025-11-19 15:05:56'),
(36, 3, 2, 'approve', NULL, '2025-11-19 15:06:10'),
(37, 3, 14, 'reject', NULL, '2025-11-20 04:06:55'),
(38, 3, 9, 'reject', NULL, '2025-11-20 04:07:11'),
(39, 3, 11, 'reject', NULL, '2025-11-21 02:14:13'),
(40, 3, 25, 'approve', NULL, '2025-11-21 02:14:41'),
(41, 3, 13, 'approve', NULL, '2025-11-21 05:15:06'),
(42, 3, 8, 'approve', NULL, '2025-11-21 09:13:37'),
(43, 3, 12, 'approve', NULL, '2025-11-21 09:25:06'),
(44, 3, 13, 'reject', NULL, '2025-11-21 09:25:28'),
(45, 3, 22, 'reject', NULL, '2025-11-21 09:28:30'),
(46, 3, 7, 'approve', NULL, '2025-11-21 09:43:45'),
(47, 3, 21, 'reject', NULL, '2025-11-21 09:44:41'),
(48, 3, 2, 'approve', NULL, '2025-11-21 09:56:00'),
(49, 3, 18, 'reject', NULL, '2025-11-21 09:56:46'),
(50, 3, 1, 'approve', NULL, '2025-11-21 10:11:04'),
(51, 3, 2, 'approve', 'Approved alumni profile', '2025-11-26 07:05:26'),
(52, 4, 2, 'update', 'Undo approval - Reverted to pending status', '2025-11-27 12:35:13'),
(53, 4, 2, 'reject', 'Rejected alumni profile - Reason: Missing enrollment', '2025-11-27 12:35:32'),
(54, 3, 8, 'approve', 'Approved alumni profile', '2025-11-28 04:26:04'),
(55, 3, 20, 'approve', 'Approved alumni profile', '2025-11-28 09:18:29'),
(56, 4, 8, 'reject', 'Rejected alumni profile - Reason: Incomplete company info', '2025-11-28 09:39:06'),
(57, 4, 2, 'update', 'Undo rejection - Reverted to pending status', '2025-11-28 09:39:19'),
(58, 4, 2, 'approve', 'Approved alumni profile', '2025-11-28 09:39:21'),
(59, 4, 12, 'reject', 'Rejected alumni profile - Reason: dsdsadsad', '2025-11-28 09:41:47'),
(60, 4, 34, 'approve', 'Approved alumni profile', '2025-11-29 05:55:10'),
(61, 5, 39, 'update', 'Changed status to pending', '2025-11-29 06:26:11'),
(62, 5, 17, 'update', 'Changed status to pending', '2025-11-29 06:26:14'),
(63, 5, 17, 'update', 'Changed status to pending', '2025-11-29 06:26:20'),
(64, 4, 8, 'reject', 'Rejected alumni profile - Reason: Insufficient business proof', '2025-11-30 11:54:23'),
(65, 4, 21, 'approve', 'Approved alumni profile', '2025-11-30 11:54:47'),
(66, 4, 18, 'reject', 'Rejected alumni profile - Reason: blabla', '2025-11-30 11:55:02'),
(67, 4, 23, 'reject', 'Rejected alumni profile - Reason: Insufficient/incorrect supporting documents for both statuses', '2025-11-30 12:51:25'),
(68, 3, 2, 'reject', 'Rejected alumni profile - Reason: Degree pursued information unclear', '2025-12-04 13:13:07'),
(69, 3, 18, 'reject', 'Rejected alumni profile - Reason: hi mayeng hahah', '2025-12-04 14:56:42'),
(70, 3, 22, 'approve', 'Approved alumni profile', '2025-12-04 14:56:55'),
(71, 5, 48, 'reject', 'Rejected alumni profile - Reason: Missing Certificate of Employment document', '2025-12-05 16:55:32'),
(72, 5, 17, 'approve', 'Approved alumni profile', '2025-12-05 16:55:43');

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
  `batch_year` year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `first_name`, `middle_name`, `last_name`, `suffix`, `created_at`, `student_id`, `date_of_birth`, `gender`, `program`, `batch_year`) VALUES
(1, 'josieoliveros013@gmail.com', '$2y$10$ehX/E2zWveVcemhEh6PmLOGJM8HlV2g5jePA7LEfwgftW1DJZ8Uz.', 'alumni', 'Josie', 'Gumera', 'Oliveros', '', '2025-10-13 13:59:40', '2020-00123', '1997-01-11', 'Female', 'Bachelor of Science in Information Technology', '2020'),
(2, 'quienbisnar@gmail.com', '$2y$10$iVt2fcpR/Z19c8jTMKyA5OgBFCYb5GK44KCQMXmagMMxDstzdparC', 'alumni', 'Quien', 'Bendula', 'Bisnar', '', '2025-10-13 11:48:34', '2020-00124', '2002-12-02', 'Female', 'Bachelor of Science in Information Technology', '2020'),
(3, 'aseneroglaiza@gmail.com', '$2y$10$UHN1b.vJAkh26l4TdpkxT.Zfsvi3DgvgH5m41PRIGAnMefSpfufhO', 'admin', 'Glaiza', 'Ewayan', 'Aseñero', '', '2025-10-13 14:01:10', NULL, NULL, NULL, NULL, NULL),
(4, 'glowentanamanmil08@gmail.com', '$2y$10$.X6JG2ZcAC.Oi3RLDciATehWeH1FxfvrB4NBhnT8Eqwy9dkcT1TL.', 'admin', 'Glowen', '', 'Tanaman', '', '2025-10-17 08:59:39', NULL, NULL, NULL, NULL, NULL),
(5, 'repe.ronaldojr@gmail.com', '$2y$10$AV5HSa53xpJRPykHLCQhuei9q5Rtk7SMFfs.yS9riewWH/d0hylKC', 'admin', 'Ronaldo', 'Montemor', 'Repe', 'Jr.', '2025-10-17 08:59:39', NULL, NULL, NULL, NULL, NULL),
(6, 'davelabadan1@gmail.com', '$2y$10$h6Xx10eFsuv0vhUk9ApM/OmLJ1YHyYRGx.lAb.0iSmuHFZ8NpwjO2', 'alumni', 'China Dave', 'Jumuad', 'Labadan', '', '2025-10-17 08:59:39', '2020-00004', '2000-03-11', 'Male', 'Bachelor of Science in Information Technology', '2021'),
(7, 'joangracep@gmail.com', '$2y$10$M1kkyVDtSJHEBwXmuEwNmO.IHkK/S5jmHU7Xtx9lTJthD3qOuPZmG', 'alumni', 'Joan Grace', 'Mancera', 'Patalinghug', '', '2025-10-17 08:59:39', '2020-00005', '1999-11-22', 'Female', 'Bachelor of Science in Information Technology', '2019'),
(8, 'marchanmayang687@gmail.com', '$2y$10$PvYQQ4DZnVHa8Z5zqYxEEOGq7.5yI2TkUPbIoTVlPzcjCXzwX8OMG', 'alumni', 'Marian', 'Getigan', 'Marchan', '', '2025-10-17 08:59:39', '2020-00006', '2003-12-25', 'Female', 'Bachelor of Science in Information Technology', '2024'),
(9, 'jaafarj.omar@gmail.com', '$2y$10$aBrYT3wN51F1yKGoV/2age.qI5Mz3JXzD0j//TazqSLjsmsfBouMe', 'alumni', 'Jaafar', '', 'Omar', '', '2025-11-05 13:21:52', '2020-00007', '1997-03-09', 'Male', 'Bachelor of Science in Information Technology', '2019'),
(10, 'buhianreymark@gmail.com', '$2y$10$rkACLYX4XRZkULTFpJefx..w..Cc0rre7xgsv0FpGc4Y070R6Aaiu', 'alumni', 'Reymark', '', 'Buhian', '', '2025-10-17 09:01:25', '2020-00008', '1997-08-08', 'Male', 'Bachelor of Science in Information Technology', '2020'),
(11, 'sairabelarmino1@gmail.com', '$2y$10$pnjkHN4SA.MxIkO2YV3xFeHK7vTemeFy/FnswuVfDp4n/J8jDjG12', 'alumni', 'Saira', 'Lambayan', 'Belarmino', '', '2025-10-17 09:05:44', '2020-00009', '1999-05-24', 'Female', 'Bachelor of Science in Information Technology', '2021'),
(12, 'asutillajohn445@gmail.com', '$2y$10$D9Y6u6QAcQfq6ZhPHOGwyOpeuQSBg.qXkzqdsrqZPtZR9ZtvSXVhS', 'alumni', 'John Marnell', 'Lamban', 'Asutilla', '', '2025-10-17 09:05:44', '2020-00010', '1999-04-01', 'Male', 'Bachelor of Science in Information Technology', '2019'),
(13, 'chlsywtnb001@gmail.com', '$2y$10$EvSxw6YxgkaV.E1NuSD1PetRgxiTdbDONJftJIEgevEZI65b3s7Te', 'alumni', 'Maureen', 'Perdiguez', 'Guadalquiver', '', '2025-10-17 09:05:44', '2020-00011', '2003-10-17', 'Female', 'Bachelor of Science in Information Technology', '2024'),
(14, 'madumbkiki@gmail.com', '$2y$10$HWCc9A8dNCQqEbE80YBQ/ead4ShMD7kRb0REUZq1U7xVDeZu9Asii', 'alumni', 'Kia', 'Banac', 'Balucos', '', '2025-10-17 09:09:02', '2020-00012', '2001-04-12', 'Female', 'Bachelor of Science in Information Technology', '2022'),
(15, 'jesseltapdasan3@gmail.com', '$2y$10$LRuq4uVRQAHsnXUalvlV6uU139FmSMDUlT.B4LV/kb3u9m7TLyiOa', 'alumni', 'Jessel Rose', 'Arroyo', 'Tapdasan', '', '2025-10-17 09:09:02', '2020-00013', '2002-05-19', 'Female', 'Bachelor of Science in Information Technology', '2022'),
(16, 'yolemkieth@gmail.com', '$2y$10$LsoAByT1LT.asfHz.YNCAulAkYFF/255qOnX/ItjzCdbCDWemnJi2', 'alumni', 'Yolem Kieth', 'Martil', 'Salarda', '', '2025-10-17 09:09:02', '2020-00014', '2001-04-13', 'Male', 'Bachelor of Science in Information Technology', '2021'),
(17, 'fuyaaa123@gmail.com', '$2y$10$z1SWM1/Da6eCA6rovryLk.hP3151/ug51ecvxo4I5R72rGt9BTjRG', 'alumni', 'Famme', 'Oculam', 'Tabaranza', '', '2025-10-17 09:09:02', '2020-00015', '2004-08-18', 'Female', 'Bachelor of Science in Information Technology', '2024'),
(18, 'ticmonmariel9@gmail.com', '$2y$10$v29n.z1o20nQbZRGhWGfSOKtzd0SVuRH7kuU.MXr/6lIbiocOA5Sa', 'alumni', 'Mariel', 'Manaba', 'Ticmon', '', '2025-10-17 09:23:41', '2020-00016', '2003-02-03', 'Female', 'Bachelor of Science in Information Technology', '2023'),
(19, 'jennethcorcelles@gmail.com', '$2y$10$EIS.HCxWmN0N0agHPDy2BeVvz/vvkaDm1D7GBi309m7KbPEMAArke', 'alumni', 'Jenneth', 'Donoso', 'Corcelles', '', '2025-10-17 12:01:39', '2020-00017', '2000-08-03', 'Female', 'Bachelor of Science in Information Technology', '2023'),
(20, 'drexzelescoreal@gmail.com', '$2y$10$nj7WTWt9ueBZF5YmZHa.F.9WGyNSzFh6WH93WFYAaIX1ZJSY7kHJ.', 'alumni', 'Drexzel', 'Corcelles', 'Escoreal', '', '2025-10-17 12:01:39', '2020-00018', '2001-04-06', 'Male', 'Bachelor of Science in Information Technology', '2024'),
(21, 'danrylboncales@gmail.com', '$2y$10$EkIQueUtH4eI.KK.Ef49seYbAu6XCStVKjVk16X/33BuzsQTgt0s2', 'alumni', 'Danryl James', 'Boncales', 'Usa', '', '2025-10-17 12:01:39', '2020-00019', '2003-04-25', 'Male', 'Bachelor of Science in Information Technology', '2023'),
(22, 'davemadrazo7@gmail.com', '$2y$10$6U8i3VrEMwuhzax5GKUOi.JOZbWqok4/6hKld3CL44i.aYxTe12Mq', 'alumni', 'Dave Jay', 'Quimada', 'Madrazo', '', '2025-10-17 12:01:39', '2020-00020', '1996-02-27', 'Male', 'Bachelor of Science in Information Technology', '2018'),
(23, 'salvadorvincecyrus@gmail.com', '$2y$10$2rxmPdtdOr8NgmNmvVPiEOgxKeAC.OQTQw0C58EY5/CnEzoeCrKDi', 'alumni', 'Vince Cyrus', '', 'Salvador', '', '2025-10-17 15:13:32', '2020-00021', '2002-02-13', 'Male', 'Bachelor of Science in Information Technology', '2023'),
(25, 'rthdbl672@gmail.com', '$2y$10$5MS6n8OUf8kJ2D3eHCAfwecHkuIAXKlkB8vrq5dCIwitcNfdyvcni', 'alumni', 'Arth', 'Alimpos', 'Dablo', '', '2025-11-10 13:24:20', '2020-00022', '1996-10-10', 'Male', 'Bachelor of Science in Information Technology', '2019'),
(26, 'dexeneblisk@gmail.com', '$2y$10$lDxS7aqu/dWetRJ8sOV6BOCFxNHsqv2N8osGzM7cGTEXw4h2/LR4K', 'alumni', 'Dexene Bliss', '', 'Kilat', '', '2025-11-10 13:24:20', '2020-00023', '2002-03-16', 'Female', 'Bachelor of Science in Information Technology', '2023'),
(27, 'anjofernandez0705@gmail.com', '$2y$10$mGcZLCHvtbYb0GpPPnq3v.eUFzKx.LxoPEtOBNUIg4dVSOSlt3YqW', 'alumni', 'Anjo', 'Abella', 'Fernandez', '', '2025-11-10 13:24:20', '2020-00024', '2000-06-19', 'Male', 'Bachelor of Science in Information Technology', '2020'),
(28, 'jancarlorabe9@gmail.com', '$2y$10$JjvjQDB5HnysBAaExlAuvekp7UjkvxGJqaJt4FUe/Qr9OuDmiCLLy', 'alumni', 'Jan Carlo', 'Bernacibo', 'Rabe', '', '2025-11-10 13:24:20', '2020-00025', '1998-05-06', 'Female', 'Bachelor of Science in Information Technology', '2021'),
(29, 'catalanvincent222@gmail.com', '$2y$10$EjziH7HJLkjcO/t5w5CXy.a4mmAnYO..nZAbgDXLidU9iomVkcl3m', 'alumni', 'Vincent', 'Dico', 'Catalan', '', '2025-11-10 13:24:20', '2020-00026', '1999-01-08', 'Male', 'Bachelor of Science in Information Technology', '2022'),
(30, 'keishasoler05@gmail.com', '$2y$10$4l6x1jQnh1fG3hThk/XGI.MjDqcygy44M./ZUC6HbEOd5Z/MaSg8e', 'alumni', 'Keisha Nicole', 'Palino', 'Soler', '', '2025-11-11 01:57:53', '2020-00027', '2003-06-21', 'Male', 'Bachelor of Science in Information Technology', '2023'),
(31, 'reginpunay@gmail.com', '$2y$10$RPk4PT7LMcjzM9qhmqipuedvzOR5.CKxaJmGRZlkY7bbNlJ/FbvpO', 'alumni', 'Regin', 'Punay', 'Angala', '', '2025-11-11 01:57:53', '2020-00028', '2000-06-24', 'Female', 'Bachelor of Science in Information Technology', '2020'),
(32, 'jylsam123@gmail.com', '$2y$10$EtUiAEv/t5icCn/S.0qbie3F2hSzJDmzPBrEW9Hbpj0zYYTvd2hrW', 'alumni', 'Jylsam', '', 'Quirog', '', '2025-11-11 01:57:53', '2020-00029', '1999-09-27', 'Female', 'Bachelor of Science in Information Technology', '2022'),
(33, 'carlowedeala2020@gmail.com', '$2y$10$izxs4rpeIj7BKtUblvckLehRqyG8vdZ14MF8QK5eMdHTtUmk5hPuy', 'alumni', 'Carlowe', 'Delusa', 'Deala', '', '2025-11-11 01:57:53', '2020-00030', '2000-06-06', 'Male', 'Bachelor of Science in Information Technology', '2022'),
(34, 'nathanielpiraman@gmail.com', '$2y$10$HgZbThTHwqouQvhzhBWXGOKaQu/AgjMReSuRUb1gOEdnWegme15zm', 'alumni', 'Nathaniel', '', 'Piraman', '', '2025-11-11 01:57:53', '2020-00031', '2000-09-14', 'Male', 'Bachelor of Science in Information Technology', '2022'),
(35, 'sebreroaxcylxyron@gmail.com', '$2y$10$Kr.PEnBlDQorPRkskBkGquNaB/cLV4u0Cq/J1NuSL.4zv0PasqJc2', 'alumni', 'Axcyl Xyron', '', 'Sebrero', '', '2025-11-11 01:57:53', '2020-00032', '2001-03-03', 'Male', 'Bachelor of Science in Information Technology', '2022'),
(36, 'Khristenecruz@gmail.com', '$2y$10$zxAZtG0erkGI2BVIgl7mEuPWVDKUBd/fluIOmZmvJYHkyjM/kXmTm', 'alumni', 'Khristene', '', 'Suyang', '', '2025-11-11 01:57:53', '2020-00033', '1996-11-28', 'Male', 'Bachelor of Science in Information Technology', '2019'),
(37, 'gaminghr209@gmail.com', '$2y$10$RNGAubZ1zm9LHYxZpbBKsuC.plR0ghVmB4SCYytILRtGwe8KEx5Ru', 'alumni', 'Raymart', 'Timogan', 'Upao', '', '2025-11-11 01:57:53', '2020-00034', '2001-01-20', 'Female', 'Bachelor of Science in Information Technology', '2021'),
(38, 'lloydandiason45@gmail.com', '$2y$10$ylZvPSFZyBhvORRKeFR3KOjpXZbZTLghao48NI0qWLZsaEye7vV9K', 'alumni', 'Lloyd', '', 'Andiason', '', '2025-11-11 02:08:08', '2020-00035', '1998-06-06', 'Male', 'Bachelor of Science in Information Technology', '2019'),
(39, 'andrinairagrace@gmail.com', '$2y$10$ILdyOKViWrV68U5qt84iIe2C40709YneF/FxO.rq0Hwj2XOctTcgi', 'alumni', 'Aira Grace', '', 'Andrin', '', '2025-11-11 02:08:08', '2020-00036', '1999-12-18', 'Female', 'Bachelor of Science in Information Technology', '2021'),
(40, 'liborwilfred@gmail.com', '$2y$10$mpsuvqiB/uw/aK0bRV5l4eS.IS9XepggwdQ4cx1U5JovWeXfHQB7S', 'alumni', 'Wilfredo', 'Dajao', 'Libor', 'Jr.', '2025-11-11 02:08:08', '2020-00037', '2002-05-05', 'Male', 'Bachelor of Science in Information Technology', '2022'),
(41, 'jonalyntabunyag5@gmail.com', '$2y$10$0Bz6kzL/yb7mQuRTR5jxJevKbsxIGJWdPv9bjoy8evLAegELgVib.', 'alumni', 'Jonalyn', 'Umambac', 'Tabunyag', '', '2025-11-11 02:08:08', '2020-00038', '2000-09-14', 'Female', 'Bachelor of Science in Information Technology', '2022'),
(42, 'rivenllego@gmail.com', '$2y$10$gFXPvwr4JX2UqZTVdhfNZuDaT/AzNwXbYay8vedvT0hEAErZAVpZG', 'alumni', 'Rizal Ven', '', 'Llego', '', '2025-11-11 02:08:08', '2020-00039', '2002-10-12', 'Male', 'Bachelor of Science in Information Technology', '2023'),
(43, 'Renchauxtero24@gmail.com', '$2y$10$dHuhcVAve1NDNpgZ4Z9JoO1t6/r4z75kaDl6H47piwXw6Pgyomn2C', 'alumni', 'Rench', '', 'Auxtero', '', '2025-11-11 02:08:08', '2020-00040', '2000-11-22', 'Male', 'Bachelor of Science in Information Technology', '2023'),
(44, 'ivannjadecmartel@gmail.com', '$2y$10$QiTGkhw4eZPgMOm/dfckQ.10QOwfE.cAFYBILTnXFw1rFyN/vGCc.', 'alumni', 'Ivann Jade', '', 'Martel', '', '2025-11-11 02:08:08', '2020-00041', '1997-05-23', 'Male', 'Bachelor of Science in Information Technology', '2018'),
(45, 'ngllrosall@gmail.com', '$2y$10$k6yAD3V9isaZ/cO5cqtVzuMfs4pxCkxbmS0Iy4RUq0id.hSfOwZq2', 'alumni', 'Angel', 'Estallo', 'Rosal', '', '2025-11-11 02:08:08', '2020-00042', '1995-12-09', 'Female', 'Bachelor of Science in Information Technology', '2018'),
(46, 'johnmira911@gmail.com', '$2y$10$fFq50O9MRFubzmzh8Ad2m.Z7kKCgxfOX2Fx24agrRtnTTjMbnh3Cy', 'alumni', 'John Kristoffer', 'Payapa', 'Mira', '', '2025-11-11 02:08:08', '2020-00043', '1996-09-03', 'Male', 'Bachelor of Science in Information Technology', '2018'),
(47, 'alumtrak@gmail.com', '$2y$10$Q6RXYjOxE2GPu1OI.JHVnO.wPjxRTXvUfmsqMVPDM3xbzNdRAyWCe', 'admin', 'Alum', '', 'Trak', '', '2025-11-26 14:15:17', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `worldwide_address`
--

CREATE TABLE `worldwide_address` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `formatted_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `worldwide_address`
--

INSERT INTO `worldwide_address` (`address_id`, `user_id`, `city`, `state_province`, `country`, `latitude`, `longitude`, `formatted_address`, `created_at`, `updated_at`) VALUES
(2, 2, 'San Francisco', 'California', 'USA', 37.79327500, -122.39635900, 'San Francisco, California, USA', '2025-12-04 08:36:09', '2025-12-04 08:36:09'),
(4, 17, 'Carterton District', 'Wellington', 'New Zealand', -40.81932600, 175.44291700, 'Carterton District, Wellington, New Zealand', '2025-12-04 12:21:11', '2025-12-04 12:21:11'),
(6, 25, 'Mahakam Ulu', 'Kalimantan Timur', 'Indonesia', 0.69342900, 114.34398200, 'Mahakam Ulu, Kalimantan Timur, Indonesia', '2025-12-05 14:15:57', '2025-12-05 14:15:57'),
(8, 9, 'Pastoral Unincorporated Area', 'South Australia', 'Australia', -30.78383700, 137.43248900, 'Pastoral Unincorporated Area, South Australia, Australia', '2025-12-05 16:53:24', '2025-12-05 16:53:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_activity_created_at` (`created_at`);

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
  ADD KEY `idx_submission_status` (`submission_status`),
  ADD KEY `idx_employment_status` (`employment_status`),
  ADD KEY `idx_profile_last_update` (`last_profile_update`);

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
  ADD KEY `idx_title` (`title`);

--
-- Indexes for table `submission_status`
--
ALTER TABLE `submission_status`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `last_updated_by` (`last_updated_by`);

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
  ADD KEY `idx_role_batch` (`role`,`batch_year`);

--
-- Indexes for table `worldwide_address`
--
ALTER TABLE `worldwide_address`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `alumni_documents`
--
ALTER TABLE `alumni_documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `education_info`
--
ALTER TABLE `education_info`
  MODIFY `education_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `employment_info`
--
ALTER TABLE `employment_info`
  MODIFY `employment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `job_titles`
--
ALTER TABLE `job_titles`
  MODIFY `job_title_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `submission_status`
--
ALTER TABLE `submission_status`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `update_log`
--
ALTER TABLE `update_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `worldwide_address`
--
ALTER TABLE `worldwide_address`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  ADD CONSTRAINT `fk_alumni_activity_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alumni_documents`
--
ALTER TABLE `alumni_documents`
  ADD CONSTRAINT `fk_doc_alumni` FOREIGN KEY (`user_id`) REFERENCES `alumni_profile` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alumni_profile`
--
ALTER TABLE `alumni_profile`
  ADD CONSTRAINT `fk_alumni_profile_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `education_info`
--
ALTER TABLE `education_info`
  ADD CONSTRAINT `fk_education_alumni` FOREIGN KEY (`user_id`) REFERENCES `alumni_profile` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employment_info`
--
ALTER TABLE `employment_info`
  ADD CONSTRAINT `fk_employment_alumni` FOREIGN KEY (`user_id`) REFERENCES `alumni_profile` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_employment_job` FOREIGN KEY (`job_title_id`) REFERENCES `job_titles` (`job_title_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `submission_status`
--
ALTER TABLE `submission_status`
  ADD CONSTRAINT `fk_submission_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_submission_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `update_log`
--
ALTER TABLE `update_log`
  ADD CONSTRAINT `fk_update_log_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `worldwide_address`
--
ALTER TABLE `worldwide_address`
  ADD CONSTRAINT `fk_worldwide_address_user` FOREIGN KEY (`user_id`) REFERENCES `alumni_profile` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
