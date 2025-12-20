-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 20, 2025 at 10:46 AM
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
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('new_submission','resubmission','update') NOT NULL,
  `alumni_name` varchar(255) NOT NULL,
  `employment_status` varchar(50) NOT NULL,
  `batch_year` year(4) DEFAULT NULL,
  `submission_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`notification_id`, `user_id`, `notification_type`, `alumni_name`, `employment_status`, `batch_year`, `submission_time`, `is_read`) VALUES
(1, 6, 'new_submission', 'China Dave Jumuad Labadan', 'Unemployed', '2021', '2025-12-17 00:18:35', 1),
(2, 7, 'new_submission', 'Joan Grace Mancera Patalinghug', 'Employed', '2024', '2025-12-17 00:19:02', 1),
(3, 3, 'new_submission', 'George Corge Jorge', 'Self-Employed', '2024', '2025-12-17 00:19:26', 1),
(4, 16, 'new_submission', 'Yolem Kieth Martil Salarda', 'Student', '2021', '2025-12-17 00:20:00', 1),
(5, 9, 'new_submission', 'Jaafar Omar', 'Employed & Student', '2024', '2025-12-17 00:20:29', 1),
(6, 21, 'new_submission', 'Danryl James Boncales Usa', 'Employed', '2023', '2025-12-17 00:59:04', 1),
(7, 21, 'new_submission', 'Danryl James Boncales Usa', 'Employed', '2023', '2025-12-17 01:01:28', 1),
(8, 2, 'new_submission', 'Quien Bendula Bisnar', 'Self-Employed', '2023', '2025-12-17 01:32:02', 1),
(9, 2, 'new_submission', 'Quien Bendula Bisnar', 'Employed', '2023', '2025-12-17 02:25:11', 1),
(10, 2, 'new_submission', 'Quien Bendula Bisnar', 'Employed', '2023', '2025-12-17 02:26:48', 1),
(11, 4, 'new_submission', 'Glowen Tanaman', 'Unemployed', '2024', '2025-12-17 02:37:39', 1),
(12, 39, 'new_submission', 'Aira Grace Andrin', 'Employed & Student', '2021', '2025-12-17 02:38:57', 1),
(13, 10, 'new_submission', 'Reymark Buhian', 'Self-Employed', '2020', '2025-12-17 02:39:34', 1),
(14, 5, 'new_submission', 'Ronaldo Montemor Repe Jr.', 'Student', '2024', '2025-12-17 02:41:26', 1),
(15, 6, 'new_submission', 'China Dave Jumuad Labadan', 'Employed & Student', '2021', '2025-12-17 02:42:25', 1),
(16, 12, 'new_submission', 'John Marnell Lamban Asutilla', 'Unemployed', '2019', '2025-12-17 02:44:46', 1),
(17, 13, 'new_submission', 'Maureen Perdiguez Guadalquiver', 'Unemployed', '2024', '2025-12-17 03:03:55', 0),
(18, 14, 'new_submission', 'Kia Banac Balucos', 'Self-Employed', '2022', '2025-12-17 03:04:24', 0),
(19, 15, 'new_submission', 'Jessel Rose Arroyo Tapdasan', 'Unemployed', '2022', '2025-12-17 03:05:08', 0),
(20, 18, 'new_submission', 'Mariel Manaba Ticmon', 'Student', '2023', '2025-12-17 03:06:09', 0),
(21, 19, 'new_submission', 'Jenneth Donoso Corcelles', 'Unemployed', '2023', '2025-12-17 03:06:37', 0),
(22, 20, 'new_submission', 'Drexzel Corcelles Escoreal', 'Unemployed', '2024', '2025-12-17 03:06:58', 0),
(23, 23, 'new_submission', 'Vince Cyrus Salvador', 'Student', '2023', '2025-12-17 03:08:14', 0),
(24, 25, 'new_submission', 'Arth Alimpos Dablo', 'Employed & Student', '2019', '2025-12-17 03:09:54', 0),
(25, 26, 'new_submission', 'Dexene Bliss Kilat Andrin', 'Unemployed', '2023', '2025-12-17 03:13:29', 0),
(26, 27, 'new_submission', 'Anjo Abella Fernandez', 'Employed', '2020', '2025-12-17 03:14:26', 0),
(27, 28, 'new_submission', 'Jan Carlo Bernacibo Rabe', 'Student', '2021', '2025-12-17 03:15:04', 0),
(28, 29, 'new_submission', 'Vincent Dico Catalan', 'Employed & Student', '2022', '2025-12-17 03:15:54', 0),
(29, 30, 'new_submission', 'Keisha Nicole Palino Soler', 'Self-Employed', '2023', '2025-12-17 03:16:27', 0),
(30, 31, 'new_submission', 'Regin Punay Angala', 'Employed', '2020', '2025-12-17 03:17:15', 0),
(31, 32, 'new_submission', 'Jylsam Quirog', 'Employed', '2022', '2025-12-17 03:17:50', 0),
(32, 33, 'new_submission', 'Carlowe Delusa Deala', 'Employed', '2022', '2025-12-17 03:18:31', 0),
(33, 34, 'new_submission', 'Nathaniel Piraman', 'Employed', '2022', '2025-12-17 03:19:17', 0),
(34, 35, 'new_submission', 'Axcyl Xyron Sebrero', 'Employed', '2022', '2025-12-17 03:20:47', 0),
(35, 36, 'new_submission', 'Khristene Suyang', 'Employed', '2019', '2025-12-17 03:21:29', 0),
(36, 17, 'new_submission', 'Famme Oculam Tabaranza', 'Employed', '2024', '2025-12-17 03:58:08', 1),
(37, 9, 'new_submission', 'Jaafar Omar', 'Employed', '2024', '2025-12-17 11:41:16', 0);

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
(227, 7, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 17:30:04'),
(228, 7, 'employment_updated', 'Updated employment information', '2025-12-16 21:01:18'),
(229, 9, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 21:01:42'),
(230, 9, 'employment_updated', 'Updated employment information', '2025-12-16 21:02:01'),
(231, 1, 'employment_updated', 'Updated employment information', '2025-12-16 22:06:35'),
(232, 1, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 22:06:42'),
(233, 8, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 22:07:06'),
(234, 8, 'employment_updated', 'Updated employment information', '2025-12-16 22:07:37'),
(235, 27, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 23:47:42'),
(236, 27, 'employment_updated', 'Updated employment information', '2025-12-16 23:47:58'),
(237, 22, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-16 23:57:18'),
(238, 22, 'employment_updated', 'Updated employment information', '2025-12-16 23:57:25'),
(239, 33, 'employment_updated', 'Updated employment information', '2025-12-17 00:01:12'),
(240, 4, 'employment_updated', 'Updated employment information', '2025-12-17 00:16:55'),
(241, 6, 'employment_updated', 'Updated employment information', '2025-12-17 00:18:35'),
(242, 7, 'employment_updated', 'Updated employment information', '2025-12-17 00:19:02'),
(243, 3, 'employment_updated', 'Updated employment information', '2025-12-17 00:19:26'),
(244, 16, 'employment_updated', 'Updated employment information', '2025-12-17 00:20:00'),
(245, 9, 'employment_updated', 'Updated employment information', '2025-12-17 00:20:29'),
(246, 21, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 00:58:40'),
(247, 21, 'employment_updated', 'Updated employment information', '2025-12-17 00:59:04'),
(248, 21, 'employment_updated', 'Updated employment information', '2025-12-17 01:01:28'),
(249, 2, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 01:31:48'),
(250, 2, 'employment_updated', 'Updated employment information', '2025-12-17 01:32:02'),
(251, 2, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:24:50'),
(252, 2, 'employment_updated', 'Updated employment information', '2025-12-17 02:25:11'),
(253, 2, 'employment_updated', 'Updated employment information', '2025-12-17 02:26:48'),
(254, 4, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:37:32'),
(255, 4, 'employment_updated', 'Updated employment information', '2025-12-17 02:37:39'),
(256, 3, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:37:53'),
(257, 39, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:38:17'),
(258, 39, 'employment_updated', 'Updated employment information', '2025-12-17 02:38:57'),
(259, 10, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:39:22'),
(260, 10, 'employment_updated', 'Updated employment information', '2025-12-17 02:39:34'),
(261, 1, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:40:26'),
(262, 5, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:40:53'),
(263, 5, 'employment_updated', 'Updated employment information', '2025-12-17 02:41:26'),
(264, 6, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:41:49'),
(265, 6, 'employment_updated', 'Updated employment information', '2025-12-17 02:42:25'),
(266, 7, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:43:06'),
(267, 8, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:43:33'),
(268, 9, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:43:54'),
(269, 12, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 02:44:29'),
(270, 12, 'employment_updated', 'Updated employment information', '2025-12-17 02:44:46'),
(271, 13, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:03:49'),
(272, 13, 'employment_updated', 'Updated employment information', '2025-12-17 03:03:55'),
(273, 14, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:04:11'),
(274, 14, 'employment_updated', 'Updated employment information', '2025-12-17 03:04:24'),
(275, 15, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:04:42'),
(276, 15, 'employment_updated', 'Updated employment information', '2025-12-17 03:05:08'),
(277, 16, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:05:19'),
(278, 18, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:05:43'),
(279, 18, 'employment_updated', 'Updated employment information', '2025-12-17 03:06:09'),
(280, 19, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:06:29'),
(281, 19, 'employment_updated', 'Updated employment information', '2025-12-17 03:06:37'),
(282, 20, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:06:53'),
(283, 20, 'employment_updated', 'Updated employment information', '2025-12-17 03:06:58'),
(284, 22, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:07:20'),
(285, 23, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:07:40'),
(286, 23, 'employment_updated', 'Updated employment information', '2025-12-17 03:08:14'),
(287, 25, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:09:16'),
(288, 25, 'employment_updated', 'Updated employment information', '2025-12-17 03:09:54'),
(289, 26, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:13:24'),
(290, 26, 'employment_updated', 'Updated employment information', '2025-12-17 03:13:29'),
(291, 27, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:14:01'),
(292, 27, 'employment_updated', 'Updated employment information', '2025-12-17 03:14:26'),
(293, 28, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:14:43'),
(294, 28, 'employment_updated', 'Updated employment information', '2025-12-17 03:15:04'),
(295, 29, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:15:25'),
(296, 29, 'employment_updated', 'Updated employment information', '2025-12-17 03:15:54'),
(297, 30, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:16:13'),
(298, 30, 'employment_updated', 'Updated employment information', '2025-12-17 03:16:27'),
(299, 31, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:16:45'),
(300, 31, 'employment_updated', 'Updated employment information', '2025-12-17 03:17:15'),
(301, 32, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:17:34'),
(302, 32, 'employment_updated', 'Updated employment information', '2025-12-17 03:17:50'),
(303, 33, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:18:14'),
(304, 33, 'employment_updated', 'Updated employment information', '2025-12-17 03:18:31'),
(305, 34, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:18:54'),
(306, 34, 'employment_updated', 'Updated employment information', '2025-12-17 03:19:17'),
(307, 35, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:20:25'),
(308, 35, 'employment_updated', 'Updated employment information', '2025-12-17 03:20:47'),
(309, 36, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:21:08'),
(310, 36, 'employment_updated', 'Updated employment information', '2025-12-17 03:21:29'),
(311, 17, 'profile_updated', 'Updated personal information and uploaded new profile photo', '2025-12-17 03:57:27'),
(312, 17, 'employment_updated', 'Updated employment information', '2025-12-17 03:58:08'),
(313, 9, 'employment_updated', 'Updated employment information', '2025-12-17 11:41:16');

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
(35, 2, 'Aurora', 'Zamboanga del Sur', '24 Aguinaldo Street', 'Philippines', '2025-12-14 16:24:07', '2025-12-17 02:24:50'),
(36, 40, 'Molave', 'Zamboanga del Sur', '309 Mabini Street', 'Philippines', '2025-12-14 16:43:06', '2025-12-15 04:56:43'),
(37, 26, 'San Miguel', 'Zamboanga del Sur', '909 Del Pilar Street', 'Philippines', '2025-12-15 03:41:27', '2025-12-17 03:13:24'),
(38, 22, 'Dumingag', 'Zamboanga del Sur', '981 Mabini Street', 'Philippines', '2025-12-15 04:06:43', '2025-12-17 03:07:20'),
(39, 1, 'Labangan', 'Zamboanga del Sur', '607 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:40:26'),
(40, 3, 'San Miguel', 'Zamboanga del Sur', '412 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:37:53'),
(41, 4, 'Dumingag', 'Zamboanga del Sur', '13 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:37:32'),
(42, 5, 'Molave', 'Zamboanga del Sur', '880 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:40:53'),
(43, 6, 'Mahayag', 'Zamboanga del Sur', '932 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:41:49'),
(44, 7, 'Aurora', 'Zamboanga del Sur', '608 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:43:06'),
(45, 8, 'Dumingag', 'Zamboanga del Sur', '891 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:43:33'),
(46, 9, 'Pagadian', 'Zamboanga del Sur', '672 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:43:54'),
(47, 10, 'Pagadian', 'Zamboanga del Sur', '247 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:39:22'),
(48, 11, 'Aurora', 'Zamboanga del Sur', '167 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(49, 12, 'Dumingag', 'Zamboanga del Sur', '160 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:44:29'),
(50, 13, 'Mahayag', 'Zamboanga del Sur', '132 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:03:49'),
(51, 14, 'Mahayag', 'Zamboanga del Sur', '285 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:04:11'),
(52, 15, 'Dumingag', 'Zamboanga del Sur', 'Rizal Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:04:42'),
(53, 16, 'Labangan', 'Zamboanga del Sur', '23 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:05:19'),
(54, 17, 'Aurora', 'Zamboanga del Sur', '86 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:57:27'),
(55, 18, 'Molave', 'Zamboanga del Sur', '530 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:05:43'),
(56, 19, 'Tukuran', 'Zamboanga del Sur', '745 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:06:29'),
(57, 20, 'Pagadian', 'Zamboanga del Sur', '837 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:06:53'),
(58, 21, 'Labangan', 'Zamboanga del Sur', '892 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 00:58:40'),
(59, 23, 'Dumingag', 'Zamboanga del Sur', '226 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:07:40'),
(60, 25, 'Mahayag', 'Zamboanga del Sur', '185 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:09:16'),
(61, 27, 'Dumingag', 'Zamboanga del Sur', '73 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:14:01'),
(62, 28, 'Molave', 'Zamboanga del Sur', '797 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:14:43'),
(63, 29, 'San Miguel', 'Zamboanga del Sur', '724 Rizal Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:15:25'),
(64, 30, 'San Miguel', 'Zamboanga del Sur', '833 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:16:13'),
(65, 31, 'San Miguel', 'Zamboanga del Sur', '324 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:16:45'),
(66, 32, 'Tukuran', 'Zamboanga del Sur', '681 Quezon Avenue', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:17:34'),
(67, 33, 'Aurora', 'Zamboanga del Sur', '232 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:18:14'),
(68, 34, 'Labangan', 'Zamboanga del Sur', '364 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:18:54'),
(69, 35, 'Labangan', 'Zamboanga del Sur', '164 Aguinaldo Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:20:25'),
(70, 36, 'Aurora', 'Zamboanga del Sur', '667 Bonifacio Drive', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 03:21:08'),
(71, 37, 'Mahayag', 'Zamboanga del Sur', 'Poblacion', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:34:05'),
(72, 38, 'Mahayag', 'Zamboanga del Sur', '6 Del Pilar Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-15 05:26:06'),
(73, 39, 'Aurora', 'Zamboanga del Sur', '377 Mabini Street', 'Philippines', '2025-12-15 05:26:06', '2025-12-17 02:38:16'),
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
(169, 3, 'B_CERT', 'uploads/documents/jorge_bcert_cV3LL.pdf', NULL, NULL, 'Pending'),
(170, 16, 'COR', 'uploads/documents/salarda_cor_R4PF2.pdf', NULL, NULL, 'Pending'),
(174, 21, 'COE', 'uploads/documents/usa_coe_lEggH.pdf', NULL, NULL, 'Approved'),
(177, 2, 'COE', 'uploads/documents/bisnar_coe_1OCHc.pdf', NULL, NULL, 'Approved'),
(178, 39, 'COE', 'uploads/documents/andrin_coe_QbSbs.pdf', NULL, NULL, 'Pending'),
(179, 39, 'COR', 'uploads/documents/andrin_cor_pvy5A.pdf', NULL, NULL, 'Pending'),
(180, 10, 'B_CERT', 'uploads/documents/buhian_bcert_XANy1.pdf', NULL, NULL, 'Pending'),
(181, 5, 'COR', 'uploads/documents/repe_cor_XIDE3.pdf', NULL, NULL, 'Pending'),
(182, 6, 'COE', 'uploads/documents/labadan_coe_OlIsK.pdf', NULL, NULL, 'Pending'),
(183, 6, 'COR', 'uploads/documents/labadan_cor_p584s.pdf', NULL, NULL, 'Pending'),
(184, 14, 'B_CERT', 'uploads/documents/balucos_bcert_dqxxV.pdf', NULL, NULL, 'Pending'),
(185, 18, 'COR', 'uploads/documents/ticmon_cor_kzL96.pdf', NULL, NULL, 'Pending'),
(186, 23, 'COR', 'uploads/documents/salvador_cor_uD3Sk.pdf', NULL, NULL, 'Pending'),
(190, 28, 'COR', 'uploads/documents/rabe_cor_PclQw.pdf', NULL, NULL, 'Pending'),
(191, 29, 'COE', 'uploads/documents/catalan_coe_UFGEx.pdf', NULL, NULL, 'Pending'),
(192, 29, 'COR', 'uploads/documents/catalan_cor_9COYa.pdf', NULL, NULL, 'Pending'),
(193, 30, 'B_CERT', 'uploads/documents/soler_bcert_TMc9f.pdf', NULL, NULL, 'Pending'),
(194, 31, 'COE', 'uploads/documents/angala_coe_6Hrqs.pdf', NULL, NULL, 'Pending'),
(196, 33, 'COE', 'uploads/documents/deala_coe_J7JX3.pdf', NULL, NULL, 'Pending'),
(197, 34, 'COE', 'uploads/documents/piraman_coe_NqtDN.pdf', NULL, NULL, 'Pending'),
(198, 35, 'COE', 'uploads/documents/sebrero_coe_nQqFN.pdf', NULL, NULL, 'Pending'),
(199, 36, 'COE', 'uploads/documents/suyang_coe_bkIN0.pdf', NULL, NULL, 'Pending'),
(200, 17, 'COE', 'uploads/documents/tabaranza_coe_It2GC.pdf', NULL, NULL, 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_notifications`
--

CREATE TABLE `alumni_notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('success','warning','error','info') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `related_type` enum('profile','document','batch','system') DEFAULT 'profile',
  `related_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alumni_notifications`
--

INSERT INTO `alumni_notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `related_type`, `related_id`, `created_at`) VALUES
(1, 8, 'Documents Status Updated', '2 document(s) have been reverted to pending review.', 'info', 127, 'document', 8, '2025-12-16 22:54:03'),
(2, 8, 'Documents Approved', '2 document(s) have been approved by the administrator.', 'success', 127, 'document', 8, '2025-12-16 22:54:30'),
(3, 8, 'Documents Status Updated', '2 document(s) have been reverted to pending review.', 'info', 127, 'document', 8, '2025-12-16 22:55:01'),
(4, 8, 'Documents Rejected', '2 document(s) have been rejected. Please review the rejection reasons and resubmit.', 'error', 127, 'document', 8, '2025-12-16 22:55:12'),
(5, 8, 'Documents Status Updated', '2 document(s) have been reverted to pending review.', 'info', 127, 'document', 8, '2025-12-16 22:55:41'),
(6, 8, 'Documents Rejected', '1 document(s) have been rejected. Please review the rejection reasons and resubmit.', 'error', 127, 'document', 8, '2025-12-16 22:55:47'),
(7, 8, 'Documents Rejected', '1 document(s) have been rejected. Please review the rejection reasons and resubmit.', 'error', 127, 'document', 8, '2025-12-16 22:55:49'),
(8, 7, 'Documents Approved', '2 document(s) have been approved by the administrator.', 'success', 0, 'document', 7, '2025-12-16 23:14:42'),
(9, 9, 'Documents Rejected', '1 document(s) were rejected. Please review and resubmit.', 'error', 0, 'document', 9, '2025-12-16 21:02:59'),
(10, 21, 'Documents Rejected', '1 document(s) have been rejected. Please review the rejection reasons and resubmit.', 'error', 0, 'document', 21, '2025-12-17 00:59:52'),
(11, 21, 'Documents Approved', '1 document(s) have been approved by the administrator.', 'success', 0, 'document', 21, '2025-12-17 01:01:56'),
(12, 2, 'Documents Rejected', '1 document(s) have been rejected. Please review the rejection reasons and resubmit.', 'error', 0, 'document', 2, '2025-12-17 01:32:58'),
(13, 2, 'Documents Rejected', '1 document(s) have been rejected. Please review the rejection reasons and resubmit.', 'error', 0, 'document', 2, '2025-12-17 02:26:10'),
(14, 2, 'Documents Approved', '1 document(s) have been approved by the administrator.', 'success', 0, 'document', 2, '2025-12-17 02:28:07'),
(15, 17, 'Documents Approved', '1 document(s) have been approved by the administrator.', 'success', 0, 'document', 17, '2025-12-17 04:04:02'),
(16, 17, 'Documents Status Updated', '1 document(s) have been reverted to pending review.', 'info', 0, 'document', 17, '2025-12-17 04:04:08'),
(17, 17, 'Documents Approved', '1 document(s) have been approved by the administrator.', 'success', 0, 'document', 17, '2025-12-17 04:06:17');

-- --------------------------------------------------------

--
-- Table structure for table `alumni_profile`
--

CREATE TABLE `alumni_profile` (
  `user_id` int(11) NOT NULL,
  `employment_status` enum('Employed','Self-Employed','Unemployed','Student','Employed & Student') DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `last_profile_update` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alumni_profile`
--

INSERT INTO `alumni_profile` (`user_id`, `employment_status`, `photo_path`, `last_profile_update`, `submitted_at`) VALUES
(2, 'Employed', 'uploads/profile_photos/bisnar_profile_cb5fb.png', '2025-12-17 02:26:46', '2025-12-17 02:27:23'),
(3, 'Self-Employed', 'uploads/profile_photos/jorge_profile_d8e53.jpg', '2025-12-17 02:37:53', '2025-12-17 00:19:24'),
(4, 'Unemployed', 'uploads/profile_photos/tanaman_profile_1d418.jpg', '2025-12-17 02:37:37', '2025-12-17 02:37:37'),
(5, 'Student', 'uploads/profile_photos/repe_profile_a2d97.png', '2025-12-17 02:41:25', '2025-12-17 02:41:25'),
(6, 'Employed & Student', 'uploads/profile_photos/labadan_profile_c15ad.png', '2025-12-17 02:42:23', '2025-12-17 02:42:23'),
(9, 'Employed', 'uploads/profile_photos/omar_profile_d2254.png', '2025-12-17 11:41:12', '2025-12-17 11:41:12'),
(10, 'Self-Employed', 'uploads/profile_photos/buhian_profile_73881.png', '2025-12-17 02:39:33', '2025-12-17 02:39:33'),
(12, 'Unemployed', 'uploads/profile_photos/asutilla_profile_dde8e.jpg', '2025-12-17 02:44:44', '2025-12-17 02:44:44'),
(13, 'Unemployed', 'uploads/profile_photos/guadalquiver_profile_b4f66.jpg', '2025-12-17 03:03:53', '2025-12-17 03:03:53'),
(14, 'Self-Employed', 'uploads/profile_photos/balucos_profile_31906.png', '2025-12-17 03:04:22', '2025-12-17 03:04:22'),
(15, 'Unemployed', 'uploads/profile_photos/tapdasan_profile_0bcf9.png', '2025-12-17 03:04:47', '2025-12-17 03:04:47'),
(16, 'Student', 'uploads/profile_photos/salarda_profile_a2e06.png', '2025-12-17 03:05:19', '2025-12-17 00:19:57'),
(18, 'Student', 'uploads/profile_photos/ticmon_profile_ce8e2.png', '2025-12-17 03:06:08', '2025-12-17 03:06:08'),
(19, 'Unemployed', 'uploads/profile_photos/corcelles_profile_2ebf2.png', '2025-12-17 03:06:35', '2025-12-17 03:06:35'),
(20, 'Unemployed', 'uploads/profile_photos/escoreal_profile_741c3.png', '2025-12-17 03:06:57', '2025-12-17 03:06:57'),
(21, 'Employed', 'uploads/profile_photos/usa_profile_60e1c.png', '2025-12-17 01:01:25', '2025-12-17 01:01:54'),
(22, 'Unemployed', 'uploads/profile_photos/madrazo_profile_28f9d.png', '2025-12-17 03:07:20', '2025-12-16 23:57:23'),
(23, 'Student', 'uploads/profile_photos/salvador_profile_a093e.png', '2025-12-17 03:08:12', '2025-12-17 03:08:12'),
(26, 'Unemployed', 'uploads/profile_photos/andrin_profile_96e05.png', '2025-12-17 03:13:28', '2025-12-17 03:13:28'),
(28, 'Student', 'uploads/profile_photos/rabe_profile_0e12d.png', '2025-12-17 03:15:02', '2025-12-17 03:15:02'),
(29, 'Employed & Student', 'uploads/profile_photos/catalan_profile_1d0c8.png', '2025-12-17 03:15:52', '2025-12-17 03:15:52'),
(30, 'Self-Employed', 'uploads/profile_photos/soler_profile_03e5b.png', '2025-12-17 03:16:25', '2025-12-17 03:16:25'),
(31, 'Employed', 'uploads/profile_photos/angala_profile_d1cbb.png', '2025-12-17 03:17:13', '2025-12-17 03:17:13'),
(33, 'Employed', 'uploads/profile_photos/deala_profile_5d7af.png', '2025-12-17 03:18:30', '2025-12-17 03:18:30'),
(34, 'Employed', 'uploads/profile_photos/piraman_profile_c1eb7.png', '2025-12-17 03:19:16', '2025-12-17 03:19:16'),
(35, 'Employed', 'uploads/profile_photos/sebrero_profile_cdc87.png', '2025-12-17 03:20:45', '2025-12-17 03:20:45'),
(36, 'Employed', 'uploads/profile_photos/suyang_profile_b24aa.png', '2025-12-17 03:21:27', '2025-12-17 03:21:27'),
(39, 'Employed & Student', 'uploads/profile_photos/andrin_profile_f1f2c.png', '2025-12-17 02:38:55', '2025-12-17 02:38:55');

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
(68, 16, 'Ateneo de Zamboanga', 'Master of Science in Data Science', '2024', '2026'),
(70, 39, 'Zamboanga del Sur State University', 'Master of Science in Cloud Engineering', '2022', '2024'),
(71, 5, 'Zamboanga del Sur State University', 'Master of Science in Information Technology', '2025', '2027'),
(72, 6, 'University of Sto. Tomas', 'Master of Science in Cybersecurity', '2023', '2025'),
(73, 18, 'Zamboanga del Sur State University', 'Doctor of Engineering Sciences', '2023', '2029'),
(74, 23, 'University of Sto. Tomas', 'Master of Science in Information Technology', '2025', '2028'),
(76, 28, 'Zamboanga del Sur State University', 'PhD', '2024', '2028'),
(77, 29, 'Zamboanga del Sur State University', 'PhD', '2024', '2027');

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
(120, 3, NULL, '', 'Below ₱10,000', 'Freelancer', ''),
(123, 21, 3, 'Meta', '₱30,000–₱40,000', NULL, 'USA'),
(126, 2, 3, 'Meta', '₱10,000–₱20,000', NULL, 'USA'),
(127, 39, 23, 'Meta', '₱10,000–₱20,000', NULL, 'USA'),
(128, 10, NULL, '', '₱30,000–₱40,000', 'Retail / Online Selling', ''),
(129, 6, 18, 'Aztec Civilization', '₱10,000–₱20,000', NULL, 'USA'),
(130, 14, NULL, '', '₱10,000–₱20,000', 'Food Service / Catering', ''),
(133, 29, 4, 'Meta', 'Below ₱10,000', NULL, 'USA'),
(134, 30, NULL, '', '₱10,000–₱20,000', 'Freelancer', ''),
(135, 31, 23, 'Disney', '₱20,000–₱30,000', NULL, 'HongKong'),
(137, 33, 4, 'Facebook', '₱20,000–₱30,000', NULL, 'USA'),
(138, 34, 4, 'LatentView Analytics', '₱30,000–₱40,000', NULL, 'Iligan City'),
(139, 35, 7, 'Meta', '₱20,000–₱30,000', NULL, 'USA'),
(140, 36, 7, 'Cisco', '₱10,000–₱20,000', NULL, 'USA'),
(142, 9, 18, 'GitHub', 'Above ₱50,000', NULL, 'LIGH 428, APHB Colony, Moula Ali');

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
  `id` int(11) NOT NULL,
  `is_open` tinyint(1) DEFAULT 0,
  `manual_override` tinyint(1) DEFAULT 0,
  `open_date` datetime DEFAULT NULL,
  `close_date` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employment_submission_open` tinyint(1) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submission_status`
--

INSERT INTO `submission_status` (`id`, `is_open`, `manual_override`, `open_date`, `close_date`, `updated_at`, `employment_submission_open`, `user_id`) VALUES
(1, 1, 1, NULL, NULL, '2025-12-17 01:01:04', 0, NULL);

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
(78, 47, 7, 'approve', 'Approved 2 document(s) via bulk action', '2025-12-16 21:02:31'),
(79, 47, 7, 'update', 'Reverted 2 document(s) to pending status via bulk action', '2025-12-16 21:02:37'),
(80, 47, 7, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-16 21:02:50'),
(81, 47, 9, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-16 21:02:59'),
(82, 47, 8, 'approve', 'Approved 2 document(s) via bulk action', '2025-12-16 22:45:29'),
(83, 47, 8, 'update', 'Reverted 2 document(s) to pending status via bulk action', '2025-12-16 22:54:03'),
(84, 47, 8, 'approve', 'Approved 2 document(s) via bulk action', '2025-12-16 22:54:30'),
(85, 47, 8, 'update', 'Reverted 2 document(s) to pending status via bulk action', '2025-12-16 22:55:01'),
(86, 47, 8, 'reject', 'Rejected 2 document(s) via bulk rejection', '2025-12-16 22:55:12'),
(87, 47, 8, 'update', 'Reverted 2 document(s) to pending status via bulk action', '2025-12-16 22:55:41'),
(88, 47, 8, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-16 22:55:47'),
(89, 47, 8, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-16 22:55:49'),
(90, 47, 7, 'approve', 'Approved 2 document(s) via bulk action', '2025-12-16 23:14:42'),
(91, 47, 21, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-17 00:59:52'),
(92, 47, 21, 'approve', 'Approved 1 document(s) via bulk action', '2025-12-17 01:01:56'),
(93, 47, 2, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-17 01:32:58'),
(94, 47, 2, 'reject', 'Rejected 1 document(s) via bulk rejection', '2025-12-17 02:26:10'),
(95, 47, 2, 'approve', 'Approved 1 document(s) via bulk action', '2025-12-17 02:28:07'),
(96, 47, 17, 'approve', 'Approved 1 document(s) via bulk action', '2025-12-17 04:04:02'),
(97, 47, 17, 'update', 'Reverted 1 document(s) to pending status via bulk action', '2025-12-17 04:04:08'),
(98, 47, 17, 'approve', 'Approved 1 document(s) via bulk action', '2025-12-17 04:06:17');

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
(1, 'josieoliveros013@gmail.com', '$2y$10$ehX/E2zWveVcemhEh6PmLOGJM8HlV2g5jePA7LEfwgftW1DJZ8Uz.', 'alumni', 'Josie', 'Gumera', 'Oliveros', '', '2025-10-13 13:59:40', '2020-00123', '1997-01-11', 'Female', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Single', '09163046602'),
(2, 'quienbisnar@gmail.com', '$2y$10$iVt2fcpR/Z19c8jTMKyA5OgBFCYb5GK44KCQMXmagMMxDstzdparC', 'alumni', 'Quien', 'Bendula', 'Bisnar', '', '2025-10-13 11:48:34', '2020-00124', '2002-12-02', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09857825230'),
(3, 'aseneroglaiza@gmail.com', '$2y$10$UHN1b.vJAkh26l4TdpkxT.Zfsvi3DgvgH5m41PRIGAnMefSpfufhO', 'alumni', 'George', 'Corge', 'Jorge', '', '2025-10-13 14:01:10', '2020-01011', '2000-11-02', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09532677351'),
(4, 'glowentanamanmil08@gmail.com', '$2y$10$.X6JG2ZcAC.Oi3RLDciATehWeH1FxfvrB4NBhnT8Eqwy9dkcT1TL.', 'alumni', 'Glowen', '', 'Tanaman', '', '2025-10-17 08:59:39', '2020-11141', '2001-07-16', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09426456224'),
(5, 'repe.ronaldojr@gmail.com', '$2y$10$AV5HSa53xpJRPykHLCQhuei9q5Rtk7SMFfs.yS9riewWH/d0hylKC', 'alumni', 'Ronaldo', 'Montemor', 'Repe', 'Jr.', '2025-10-17 08:59:39', '2020-09898', '2000-04-14', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09732322022'),
(6, 'davelabadan1@gmail.com', '$2y$10$h6Xx10eFsuv0vhUk9ApM/OmLJ1YHyYRGx.lAb.0iSmuHFZ8NpwjO2', 'alumni', 'China Dave', 'Jumuad', 'Labadan', '', '2025-10-17 08:59:39', '2020-00004', '2000-03-11', 'Male', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09382241467'),
(7, 'joangracep@gmail.com', '$2y$10$M1kkyVDtSJHEBwXmuEwNmO.IHkK/S5jmHU7Xtx9lTJthD3qOuPZmG', 'alumni', 'Joan Grace', 'Mancera', 'Patalinghug', '', '2025-10-17 08:59:39', '2020-00005', '1999-11-22', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09714241301'),
(8, 'marchanmayang687@gmail.com', '$2y$10$PvYQQ4DZnVHa8Z5zqYxEEOGq7.5yI2TkUPbIoTVlPzcjCXzwX8OMG', 'alumni', 'Marian', 'Getigan', 'Marchan', '', '2025-10-17 08:59:39', '2020-00006', '2003-12-25', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09424482135'),
(9, 'jaafarj.omar@gmail.com', '$2y$10$aBrYT3wN51F1yKGoV/2age.qI5Mz3JXzD0j//TazqSLjsmsfBouMe', 'alumni', 'Jaafar', '', 'Omar', '', '2025-11-05 13:21:52', '2020-00007', '1997-03-09', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09979686804'),
(10, 'buhianreymark@gmail.com', '$2y$10$rkACLYX4XRZkULTFpJefx..w..Cc0rre7xgsv0FpGc4Y070R6Aaiu', 'alumni', 'Reymark', '', 'Buhian', '', '2025-10-17 09:01:25', '2020-00008', '1997-08-08', 'Male', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Single', '09624987644'),
(11, 'sairabelarmino1@gmail.com', '$2y$10$pnjkHN4SA.MxIkO2YV3xFeHK7vTemeFy/FnswuVfDp4n/J8jDjG12', 'alumni', 'Saira', 'Lambayan', 'Belarmino', '', '2025-10-17 09:05:44', '2020-00009', '1999-05-24', 'Female', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09185877842'),
(12, 'asutillajohn445@gmail.com', '$2y$10$D9Y6u6QAcQfq6ZhPHOGwyOpeuQSBg.qXkzqdsrqZPtZR9ZtvSXVhS', 'alumni', 'John Marnell', 'Lamban', 'Asutilla', '', '2025-10-17 09:05:44', '2020-00010', '1999-04-01', 'Male', 'Bachelor of Science in Information Technology', '2019', 'Filipino', 'Single', '09054426306'),
(13, 'chlsywtnb001@gmail.com', '$2y$10$EvSxw6YxgkaV.E1NuSD1PetRgxiTdbDONJftJIEgevEZI65b3s7Te', 'alumni', 'Maureen', 'Perdiguez', 'Guadalquiver', '', '2025-10-17 09:05:44', '2020-00011', '2003-10-17', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09714498039'),
(14, 'madumbkiki@gmail.com', '$2y$10$HWCc9A8dNCQqEbE80YBQ/ead4ShMD7kRb0REUZq1U7xVDeZu9Asii', 'alumni', 'Kia', 'Banac', 'Balucos', '', '2025-10-17 09:09:02', '2020-00012', '2001-04-12', 'Female', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09409211305'),
(15, 'jesseltapdasan3@gmail.com', '$2y$10$LRuq4uVRQAHsnXUalvlV6uU139FmSMDUlT.B4LV/kb3u9m7TLyiOa', 'alumni', 'Jessel Rose', 'Arroyo', 'Tapdasan', '', '2025-10-17 09:09:02', '2020-00013', '2002-05-19', 'Female', 'Bachelor of Science in Information Technology', '2022', 'Filipino', 'Single', '09902562440'),
(16, 'yolemkieth@gmail.com', '$2y$10$LsoAByT1LT.asfHz.YNCAulAkYFF/255qOnX/ItjzCdbCDWemnJi2', 'alumni', 'Yolem Kieth', 'Martil', 'Salarda', '', '2025-10-17 09:09:02', '2020-00014', '2001-04-13', 'Male', 'Bachelor of Science in Information Technology', '2021', 'Filipino', 'Single', '09285178316'),
(17, 'fuyaaa123@gmail.com', '$2y$10$z1SWM1/Da6eCA6rovryLk.hP3151/ug51ecvxo4I5R72rGt9BTjRG', 'alumni', 'Famme', 'Oculam', 'Tabaranza', '', '2025-10-17 09:09:02', '2020-00015', '2004-08-18', 'Female', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09718204290'),
(18, 'ticmonmariel9@gmail.com', '$2y$10$v29n.z1o20nQbZRGhWGfSOKtzd0SVuRH7kuU.MXr/6lIbiocOA5Sa', 'alumni', 'Mariel', 'Manaba', 'Ticmon', '', '2025-10-17 09:23:41', '2020-00016', '2003-02-03', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09735486534'),
(19, 'jennethcorcelles@gmail.com', '$2y$10$EIS.HCxWmN0N0agHPDy2BeVvz/vvkaDm1D7GBi309m7KbPEMAArke', 'alumni', 'Jenneth', 'Donoso', 'Corcelles', '', '2025-10-17 12:01:39', '2020-00017', '2000-08-03', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09522819830'),
(20, 'drexzelescoreal@gmail.com', '$2y$10$nj7WTWt9ueBZF5YmZHa.F.9WGyNSzFh6WH93WFYAaIX1ZJSY7kHJ.', 'alumni', 'Drexzel', 'Corcelles', 'Escoreal', '', '2025-10-17 12:01:39', '2020-00018', '2001-04-06', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09407639581'),
(21, 'danrylboncales@gmail.com', '$2y$10$EkIQueUtH4eI.KK.Ef49seYbAu6XCStVKjVk16X/33BuzsQTgt0s2', 'alumni', 'Danryl James', 'Boncales', 'Usa', '', '2025-10-17 12:01:39', '2020-00019', '2003-04-25', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09469738444'),
(22, 'davemadrazo7@gmail.com', '$2y$10$6U8i3VrEMwuhzax5GKUOi.JOZbWqok4/6hKld3CL44i.aYxTe12Mq', 'alumni', 'Dave Jay', 'Quimada', 'Madrazo', '', '2025-10-17 12:01:39', '2020-00020', '1996-02-27', 'Male', 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09125773511'),
(23, 'salvadorvincecyrus@gmail.com', '$2y$10$2rxmPdtdOr8NgmNmvVPiEOgxKeAC.OQTQw0C58EY5/CnEzoeCrKDi', 'alumni', 'Vince Cyrus', '', 'Salvador', '', '2025-10-17 15:13:32', '2020-00021', '2002-02-13', 'Male', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09219652251'),
(25, 'rthdbl672@gmail.com', '$2y$10$5MS6n8OUf8kJ2D3eHCAfwecHkuIAXKlkB8vrq5dCIwitcNfdyvcni', 'alumni', 'Arth', 'Alimpos', 'Dablo', '', '2025-11-10 13:24:20', '2020-00022', '1996-10-10', 'Male', 'Bachelor of Science in Information Technology', '2019', 'Filipino', 'Single', '09720940755'),
(26, 'dexeneblisk@gmail.com', '$2y$10$lDxS7aqu/dWetRJ8sOV6BOCFxNHsqv2N8osGzM7cGTEXw4h2/LR4K', 'alumni', 'Dexene Bliss', 'Kilat', 'Andrin', '', '2025-11-10 13:24:20', '2020-00023', '2002-03-16', 'Female', 'Bachelor of Science in Information Technology', '2023', 'Filipino', 'Single', '09945747053'),
(27, 'anjofernandez0705@gmail.com', '$2y$10$mGcZLCHvtbYb0GpPPnq3v.eUFzKx.LxoPEtOBNUIg4dVSOSlt3YqW', 'alumni', 'Anjo', 'Abella', 'Fernandez', '', '2025-11-10 13:24:20', '2020-00024', '2000-06-19', 'Male', 'Bachelor of Science in Information Technology', '2020', 'Filipino', 'Married', '09565913030'),
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
(51, 'betauser2@test.com', '$2y$10$q9dNK1iwAowaCaqhJfZaA.l8IsdoyJ8vnHX9vPrGQDiiyVEmW.65m', 'alumni', 'tan', 'nat', 'ant', '', '2025-12-15 06:58:35', '2010-0001', '2001-12-05', NULL, 'Bachelor of Science in Information Technology', '2024', 'Filipino', 'Single', '09987321654');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`),
  ADD KEY `idx_notifications_time` (`submission_time`);

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
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_doc_status` (`document_status`);

--
-- Indexes for table `alumni_notifications`
--
ALTER TABLE `alumni_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `alumni_profile`
--
ALTER TABLE `alumni_profile`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_employment_status` (`employment_status`);

--
-- Indexes for table `education_info`
--
ALTER TABLE `education_info`
  ADD PRIMARY KEY (`education_id`),
  ADD KEY `idx_education_user` (`user_id`),
  ADD KEY `idx_school` (`school_name`);

--
-- Indexes for table `employment_info`
--
ALTER TABLE `employment_info`
  ADD PRIMARY KEY (`employment_id`),
  ADD KEY `fk_employment_alumni` (`user_id`),
  ADD KEY `fk_employment_job` (`job_title_id`),
  ADD KEY `idx_company` (`company_name`);

--
-- Indexes for table `job_titles`
--
ALTER TABLE `job_titles`
  ADD PRIMARY KEY (`job_title_id`),
  ADD UNIQUE KEY `title` (`title`);

--
-- Indexes for table `submission_status`
--
ALTER TABLE `submission_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_submission_status_user` (`user_id`);

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
  ADD KEY `idx_role_batch` (`role`,`batch_year`),
  ADD KEY `idx_user_names` (`last_name`,`first_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `alumni_activity_log`
--
ALTER TABLE `alumni_activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=314;

--
-- AUTO_INCREMENT for table `alumni_address`
--
ALTER TABLE `alumni_address`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `alumni_documents`
--
ALTER TABLE `alumni_documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;

--
-- AUTO_INCREMENT for table `alumni_notifications`
--
ALTER TABLE `alumni_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `education_info`
--
ALTER TABLE `education_info`
  MODIFY `education_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `employment_info`
--
ALTER TABLE `employment_info`
  MODIFY `employment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT for table `job_titles`
--
ALTER TABLE `job_titles`
  MODIFY `job_title_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `submission_status`
--
ALTER TABLE `submission_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `update_log`
--
ALTER TABLE `update_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Constraints for table `alumni_notifications`
--
ALTER TABLE `alumni_notifications`
  ADD CONSTRAINT `fk_alumni_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `alumni_profile`
--
ALTER TABLE `alumni_profile`
  ADD CONSTRAINT `Pkfk_user_alumni` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
-- Constraints for table `submission_status`
--
ALTER TABLE `submission_status`
  ADD CONSTRAINT `fk_submission_status_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `update_log`
--
ALTER TABLE `update_log`
  ADD CONSTRAINT `fk_log_user_updt` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
