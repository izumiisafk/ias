-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 11, 2026 at 12:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12



/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ias_subsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--
CREATE DATABASE ias_subsystem;

CREATE TABLE `academic_terms` (
  `term_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester','Summer') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `academic_terms`
--

INSERT INTO `academic_terms` (`term_id`, `academic_year`, `semester`, `is_active`, `created_at`) VALUES
(1, '2025-2026', '1st Semester', 0, '2026-03-07 11:07:29'),
(2, '2025-2026', '2nd Semester', 1, '2026-03-09 04:06:09');

-- --------------------------------------------------------

--
-- Table structure for table `conflicts`
--

CREATE TABLE `conflicts` (
  `conflict_id` int(11) NOT NULL,
  `conflict_type` enum('Faculty','Room','Section') NOT NULL,
  `schedule_id_1` int(11) NOT NULL,
  `schedule_id_2` int(11) NOT NULL,
  `description` text NOT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Unresolved','Resolved') DEFAULT 'Unresolved',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `conflicts`
--

INSERT INTO `conflicts` (`conflict_id`, `conflict_type`, `schedule_id_1`, `schedule_id_2`, `description`, `detected_at`, `status`, `resolved_at`, `resolved_note`) VALUES
(1, 'Faculty', 23, 24, 'Faculty conflict: Christoper Atinado is double-booked on Monday â€” teaching \'Computer Architecture\' (BSIT - 2201) 06:00 AMâ€“07:00 AM overlaps with \'Computer Architecture\' (BSIT - 2217) 06:00 AMâ€“09:00 PM', '2026-03-11 08:50:47', 'Resolved', '2026-03-11 16:51:25', 'Auto-resolved: schedule was fixed in the timetable');

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `faculty_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `max_teaching_hours` int(11) DEFAULT 24,
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `faculty_code`, `first_name`, `last_name`, `department`, `email`, `phone`, `max_teaching_hours`, `status`, `created_at`, `updated_at`) VALUES
(8, 'FAC-001', 'Christoper', 'Atinado', 'College of Computer Studies (CCS)', 'usera@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:04:54', '2026-03-09 06:04:54'),
(9, 'FAC-002', 'Arvie', 'Pintucan', 'College of Hospitality and Tourism Management (CHTM)', 'userb@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:05:09', '2026-03-09 06:05:09'),
(10, 'FAC-003', 'Justin', 'Ansilig', 'College of Business Administration (CBA)', 'userc@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:05:23', '2026-03-09 06:05:23'),
(11, 'FAC-004', 'Dave', 'Silva', 'College of Criminal Justice Education (CCJE)', 'usere@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:05:41', '2026-03-09 06:05:41'),
(12, 'FAC-005', 'Den', 'Rell', 'College of Engineering (COE)', 'userf@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:06:05', '2026-03-09 06:06:05'),
(13, 'FAC-006', 'Arvie', 'Atinado', 'College of Computer Studies (CCS)', 'userq@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:06:45', '2026-03-09 06:06:45'),
(14, 'FAC-007', 'Dave', 'Ansilig', 'College of Engineering (COE)', 'userr@gmail.com', '09834773', 24, 'Active', '2026-03-09 07:07:46', '2026-03-09 07:08:05');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_code` varchar(20) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `room_type` enum('Lecture','Laboratory','Conference','Auditorium') NOT NULL,
  `building` varchar(50) NOT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('Available','Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `allowed_program` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_code`, `room_name`, `room_type`, `building`, `floor`, `capacity`, `status`, `created_at`, `updated_at`, `allowed_program`) VALUES
(1, 'RM-101', 'Room 101', 'Lecture', 'Main Building', '1st', 40, 'Available', '2026-03-08 09:01:41', '2026-03-08 09:01:41', NULL),
(2, 'RM-102', 'Room 102', 'Lecture', 'Main Building', '1st', 40, 'Available', '2026-03-08 09:01:41', '2026-03-08 09:01:41', NULL),
(3, 'RM-201', 'Room 201', 'Lecture', 'Main Building', '2nd', 40, 'Available', '2026-03-08 09:01:41', '2026-03-08 09:01:41', NULL),
(4, 'LAB-101', 'Computer Lab 1', 'Laboratory', 'Tech Building', '1st', 40, 'Available', '2026-03-08 09:01:41', '2026-03-08 11:36:24', 'BSIT'),
(5, 'LAB-102', 'Computer Lab 2', 'Laboratory', 'Tech Building', '1st', 40, 'Available', '2026-03-08 09:01:41', '2026-03-08 11:36:24', 'BSIT'),
(6, 'RM-103', 'Room 103', 'Lecture', 'Main Building', '1st', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 09:02:42', NULL),
(7, 'RM-104', 'Room 104', 'Lecture', 'Main Building', '2nd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 09:02:42', NULL),
(8, 'RM-105', 'Room 105', 'Lecture', 'Science Building', '2nd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 09:02:42', NULL),
(9, 'RM-106', 'Room 106', 'Lecture', 'Science Building', '3rd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 09:02:42', NULL),
(10, 'RM-107', 'Room 107', 'Lecture', 'Admin Building', '1st', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 09:02:42', NULL),
(11, 'LAB-201', 'Tourism Lab 1', 'Laboratory', 'Tourism Building', '1st', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:24', 'BSTM'),
(12, 'LAB-202', 'Tourism Lab 2', 'Laboratory', 'Tourism Building', '2nd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:24', 'BSTM'),
(13, 'LAB-301', 'Business Lab 1', 'Laboratory', 'Admin Building', '1st', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:24', 'BSBA'),
(14, 'LAB-302', 'Business Lab 2', 'Laboratory', 'Admin Building', '2nd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:24', 'BSBA'),
(15, 'LAB-401', 'Criminology Lab 1', 'Laboratory', 'Crim Building', '1st', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:25', 'BSCRIM'),
(16, 'LAB-402', 'Criminology Lab 2', 'Laboratory', 'Crim Building', '2nd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:25', 'BSCRIM'),
(17, 'LAB-501', 'Civil Eng Lab 1', 'Laboratory', 'Engineering Building', '1st', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:25', 'BSCE'),
(18, 'LAB-502', 'Civil Eng Lab 2', 'Laboratory', 'Engineering Building', '2nd', 40, 'Available', '2026-03-08 09:02:42', '2026-03-08 11:36:25', 'BSCE');

-- --------------------------------------------------------

--
-- Table structure for table `room_assignments`
--

CREATE TABLE `room_assignments` (
  `assignment_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `room_assignments`
--

INSERT INTO `room_assignments` (`assignment_id`, `section_id`, `room_id`, `assigned_at`) VALUES
(4, 3, 2, '2026-03-08 06:48:26'),
(6, 6, 18, '2026-03-08 11:49:00'),
(7, 5, 5, '2026-03-08 11:49:33'),
(8, 8, 4, '2026-03-08 11:49:46'),
(10, 20, 15, '2026-03-09 06:08:14'),
(11, 21, 17, '2026-03-09 06:45:19'),
(12, 19, 13, '2026-03-09 06:45:29'),
(13, 18, 12, '2026-03-11 06:54:16'),
(15, 17, 5, '2026-03-11 07:16:32'),
(16, 17, 2, '2026-03-11 08:06:33'),
(19, 22, 2, '2026-03-11 08:22:01');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `term_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Active','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `section_id`, `subject_id`, `faculty_id`, `room_id`, `term_id`, `day_of_week`, `start_time`, `end_time`, `status`, `created_at`, `updated_at`) VALUES
(15, 20, 95, 11, 15, 2, 'Monday', '06:00:00', '09:00:00', 'Active', '2026-03-09 06:08:00', '2026-03-09 12:13:01'),
(18, 19, 65, 10, 2, 2, 'Tuesday', '06:00:00', '12:30:00', 'Active', '2026-03-09 06:43:46', '2026-03-11 08:32:09'),
(19, 20, 94, 11, 15, 2, 'Saturday', '06:00:00', '07:30:00', 'Active', '2026-03-09 07:59:10', '2026-03-10 11:41:05'),
(20, 21, 107, 12, 18, 2, 'Monday', '20:00:00', '21:00:00', 'Active', '2026-03-09 12:05:53', '2026-03-09 12:05:53'),
(22, 20, 95, 11, 15, 2, 'Wednesday', '16:00:00', '21:00:00', 'Active', '2026-03-11 07:09:47', '2026-03-11 08:30:10'),
(23, 22, 11, 8, 2, 2, 'Monday', '06:00:00', '07:00:00', 'Active', '2026-03-11 08:11:39', '2026-03-11 08:28:01'),
(24, 17, 11, 13, 5, 2, 'Monday', '06:00:00', '09:30:00', 'Active', '2026-03-11 08:36:14', '2026-03-11 08:51:23'),
(25, 21, 107, 12, 17, 2, 'Tuesday', '06:00:00', '21:00:00', 'Active', '2026-03-11 08:39:06', '2026-03-11 08:39:06');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `program` varchar(100) NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
  `total_students` int(11) DEFAULT 0,
  `adviser_id` int(11) DEFAULT NULL,
  `term_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive','Archived') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `program`, `year_level`, `total_students`, `adviser_id`, `term_id`, `status`, `created_at`, `updated_at`) VALUES
(17, 'BSIT - 2217', 'BSIT', '2nd Year', 40, 13, 2, 'Active', '2026-03-09 04:11:22', '2026-03-09 06:25:55'),
(18, 'BSTM - 1214', 'BSTM', '1st Year', 40, 9, 2, 'Active', '2026-03-09 04:11:38', '2026-03-09 06:25:42'),
(19, 'BSBA - 3217', 'BSBA', '3rd Year', 40, 10, 2, 'Active', '2026-03-09 04:11:51', '2026-03-09 06:25:39'),
(20, 'BSCRIM - 4216', 'BSCRIM', '4th Year', 40, 11, 2, 'Active', '2026-03-09 04:12:06', '2026-03-09 06:25:36'),
(21, 'BSCE - 2214', 'BSCE', '2nd Year', 40, 12, 2, 'Active', '2026-03-09 04:12:19', '2026-03-09 06:25:06'),
(22, 'BSIT - 2201', 'BSIT', '2nd Year', 40, 13, 2, 'Active', '2026-03-09 06:25:32', '2026-03-09 06:25:32'),
(23, 'BSTM - 1216', 'BSTM', '1st Year', 40, 9, 2, 'Active', '2026-03-11 08:11:26', '2026-03-11 08:11:26');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` int(11) NOT NULL,
  `hours_per_week` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `units`, `hours_per_week`, `department`, `year_level`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BSIT101', 'Introduction to Computing', 3, 3, 'BS Information Technology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(2, 'BSIT102', 'Computer Programming 1', 3, 3, 'BS Information Technology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(3, 'BSIT103', 'Computer Fundamentals', 3, 3, 'BS Information Technology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(4, 'BSIT111', 'Discrete Mathematics', 3, 3, 'BS Information Technology', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(5, 'BSIT112', 'Introduction to Networking', 3, 3, 'BS Information Technology', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(6, 'BSIT113', 'IT Ethics', 3, 3, 'BS Information Technology', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(7, 'BSIT201', 'Data Structures and Algorithms', 3, 3, 'BS Information Technology', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(8, 'BSIT202', 'Object Oriented Programming', 3, 3, 'BS Information Technology', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(9, 'BSIT203', 'Database Systems', 3, 3, 'BS Information Technology', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(10, 'BSIT211', 'Web Development', 3, 3, 'BS Information Technology', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(11, 'BSIT212', 'Computer Architecture', 3, 3, 'BS Information Technology', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(12, 'BSIT213', 'Network Security', 3, 3, 'BS Information Technology', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(13, 'BSIT301', 'Systems Analysis and Design', 3, 3, 'BS Information Technology', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(14, 'BSIT302', 'Software Engineering', 3, 3, 'BS Information Technology', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(15, 'BSIT303', 'Mobile App Development', 3, 3, 'BS Information Technology', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(16, 'BSIT311', 'Cloud Computing', 3, 3, 'BS Information Technology', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(17, 'BSIT312', 'Network Administration', 3, 3, 'BS Information Technology', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(18, 'BSIT313', 'Data Analytics', 3, 3, 'BS Information Technology', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(19, 'BSIT401', 'Capstone Project', 3, 3, 'BS Information Technology', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(20, 'BSIT402', 'Artificial Intelligence', 3, 3, 'BS Information Technology', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(21, 'BSIT403', 'Enterprise Systems', 3, 3, 'BS Information Technology', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(22, 'BSIT411', 'Information Systems Audit', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(23, 'BSIT412', 'Project Management', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(24, 'BSIT413', 'Cloud Security', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(25, 'BSTM101', 'Introduction to Tourism', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(26, 'BSTM102', 'Tourism Geography', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(27, 'BSTM103', 'Hospitality Fundamentals', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(28, 'BSTM111', 'Tourism Laws and Ethics', 3, 3, 'BS Tourism Management', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(29, 'BSTM112', 'Cultural Tourism', 3, 3, 'BS Tourism Management', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(30, 'BSTM113', 'Tourism Marketing Basics', 3, 3, 'BS Tourism Management', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(31, 'BSTM201', 'Travel Agency Operations', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(32, 'BSTM202', 'Tourism Marketing', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(33, 'BSTM203', 'Event Management', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(34, 'BSTM211', 'Hospitality Management', 3, 3, 'BS Tourism Management', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(35, 'BSTM212', 'Tourism Policy and Planning', 3, 3, 'BS Tourism Management', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(36, 'BSTM213', 'Ecotourism', 3, 3, 'BS Tourism Management', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(37, 'BSTM301', 'Travel and Transport Management', 3, 3, 'BS Tourism Management', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(38, 'BSTM302', 'Tour Operations Management', 3, 3, 'BS Tourism Management', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(39, 'BSTM303', 'Tourism Research Methods', 3, 3, 'BS Tourism Management', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(40, 'BSTM311', 'Sustainable Tourism', 3, 3, 'BS Tourism Management', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(41, 'BSTM312', 'Tourism Entrepreneurship', 3, 3, 'BS Tourism Management', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(42, 'BSTM313', 'International Tourism', 3, 3, 'BS Tourism Management', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(43, 'BSTM401', 'Capstone Project in Tourism', 3, 3, 'BS Tourism Management', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(44, 'BSTM402', 'Tourism Marketing Strategy', 3, 3, 'BS Tourism Management', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(45, 'BSTM403', 'Tourism Policy and Development', 3, 3, 'BS Tourism Management', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(46, 'BSTM411', 'Ecotourism Strategies', 3, 3, 'BS Tourism Management', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(47, 'BSTM412', 'Travel Agency Management', 3, 3, 'BS Tourism Management', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(48, 'BSTM413', 'Tourism Research Project', 3, 3, 'BS Tourism Management', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(49, 'BSBA101', 'Principles of Management', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(50, 'BSBA102', 'Business Mathematics', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(51, 'BSBA103', 'Introduction to Economics', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(52, 'BSBA111', 'Business Communication', 3, 3, 'BS Business Administration', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(53, 'BSBA112', 'Financial Accounting', 3, 3, 'BS Business Administration', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(54, 'BSBA113', 'Business Ethics', 3, 3, 'BS Business Administration', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(55, 'BSBA201', 'Marketing Management', 3, 3, 'BS Business Administration', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(56, 'BSBA202', 'Human Resource Management', 3, 3, 'BS Business Administration', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(57, 'BSBA203', 'Managerial Accounting', 3, 3, 'BS Business Administration', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(58, 'BSBA211', 'Operations Management', 3, 3, 'BS Business Administration', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(59, 'BSBA212', 'Business Law', 3, 3, 'BS Business Administration', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(60, 'BSBA213', 'Entrepreneurship', 3, 3, 'BS Business Administration', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(61, 'BSBA301', 'Strategic Management', 3, 3, 'BS Business Administration', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(62, 'BSBA302', 'International Business', 3, 3, 'BS Business Administration', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(63, 'BSBA303', 'Financial Management', 3, 3, 'BS Business Administration', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(64, 'BSBA311', 'Business Analytics', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(65, 'BSBA312', 'Leadership and Management', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(66, 'BSBA313', 'Investment Management', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(67, 'BSBA401', 'Capstone Project in Business', 3, 3, 'BS Business Administration', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(68, 'BSBA402', 'Marketing Strategy', 3, 3, 'BS Business Administration', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(69, 'BSBA403', 'International Finance', 3, 3, 'BS Business Administration', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(70, 'BSBA411', 'Project Management', 3, 3, 'BS Business Administration', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(71, 'BSBA412', 'Business Research Methods', 3, 3, 'BS Business Administration', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(72, 'BSBA413', 'Corporate Governance', 3, 3, 'BS Business Administration', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(73, 'BSCR101', 'Introduction to Criminology', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(74, 'BSCR102', 'Criminal Law', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(75, 'BSCR103', 'Sociology of Crime', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(76, 'BSCR111', 'Psychology of Crime', 3, 3, 'BS Criminology', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(77, 'BSCR112', 'Criminal Investigation 1', 3, 3, 'BS Criminology', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(78, 'BSCR113', 'Ethics and Law Enforcement', 3, 3, 'BS Criminology', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(79, 'BSCR201', 'Police Administration', 3, 3, 'BS Criminology', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(80, 'BSCR202', 'Forensic Science', 3, 3, 'BS Criminology', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(81, 'BSCR203', 'Criminalistics', 3, 3, 'BS Criminology', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(82, 'BSCR211', 'Correctional Administration', 3, 3, 'BS Criminology', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(83, 'BSCR212', 'Criminal Investigation 2', 3, 3, 'BS Criminology', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(84, 'BSCR213', 'Cybercrime Investigation', 3, 3, 'BS Criminology', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(85, 'BSCR301', 'Criminology Research', 3, 3, 'BS Criminology', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(86, 'BSCR302', 'Criminal Profiling', 3, 3, 'BS Criminology', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(87, 'BSCR303', 'Law Enforcement Practices', 3, 3, 'BS Criminology', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(88, 'BSCR311', 'Victimology', 3, 3, 'BS Criminology', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(89, 'BSCR312', 'Advanced Forensic Science', 3, 3, 'BS Criminology', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(90, 'BSCR313', 'Criminal Justice Administration', 3, 3, 'BS Criminology', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(91, 'BSCR401', 'Capstone Project in Criminology', 3, 3, 'BS Criminology', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(92, 'BSCR402', 'Criminal Law Review', 3, 3, 'BS Criminology', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(93, 'BSCR403', 'Advanced Criminal Investigation', 3, 3, 'BS Criminology', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(94, 'BSCR411', 'Criminal Justice Policies', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(95, 'BSCR412', 'Special Topics in Criminology', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(96, 'BSCR413', 'Forensic Psychology', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(97, 'BSCE101', 'Engineering Mathematics 1', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(98, 'BSCE102', 'Engineering Drawing', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(99, 'BSCE103', 'Statics', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(100, 'BSCE111', 'Mechanics of Materials', 3, 3, 'BS Civil Engineering', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(101, 'BSCE112', 'Introduction to Civil Engineering', 3, 3, 'BS Civil Engineering', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(102, 'BSCE113', 'Engineering Ethics', 3, 3, 'BS Civil Engineering', '1st Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(103, 'BSCE201', 'Structural Analysis', 3, 3, 'BS Civil Engineering', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(104, 'BSCE202', 'Construction Materials', 3, 3, 'BS Civil Engineering', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(105, 'BSCE203', 'Surveying 1', 3, 3, 'BS Civil Engineering', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(106, 'BSCE211', 'Fluid Mechanics', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(107, 'BSCE212', 'Geotechnical Engineering 1', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(108, 'BSCE213', 'Construction Project Management', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(109, 'BSCE301', 'Structural Design', 3, 3, 'BS Civil Engineering', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(110, 'BSCE302', 'Transportation Engineering', 3, 3, 'BS Civil Engineering', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(111, 'BSCE303', 'Hydraulics', 3, 3, 'BS Civil Engineering', '3rd Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(112, 'BSCE311', 'Surveying 2', 3, 3, 'BS Civil Engineering', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(113, 'BSCE312', 'Structural Engineering 2', 3, 3, 'BS Civil Engineering', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(114, 'BSCE313', 'Environmental Engineering', 3, 3, 'BS Civil Engineering', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(115, 'BSCE401', 'Capstone Project in Civil Engineering', 3, 3, 'BS Civil Engineering', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(116, 'BSCE402', 'Construction Law', 3, 3, 'BS Civil Engineering', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(117, 'BSCE403', 'Advanced Geotechnical Engineering', 3, 3, 'BS Civil Engineering', '4th Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(118, 'BSCE411', 'Project Management in Civil Engineering', 3, 3, 'BS Civil Engineering', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(119, 'BSCE412', 'Bridge Design', 3, 3, 'BS Civil Engineering', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(120, 'BSCE413', 'Construction Safety', 3, 3, 'BS Civil Engineering', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56');

-- --------------------------------------------------------

--
-- Table structure for table `system_accounts`
--

CREATE TABLE `system_accounts` (
  `account_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` enum('registrar') NOT NULL DEFAULT 'registrar',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `system_accounts`
--

INSERT INTO `system_accounts` (`account_id`, `username`, `password`, `full_name`, `email`, `phone`, `department`, `role`, `status`, `created_at`, `updated_at`) VALUES
(5, 'user1', '$2y$10$NAyETrRLiIzlBDCkuhIc5evKM9sBZx6gaIC.EPCJHNQCSiM8BgEUK', 'Milchor Buctot', 'user1@gmail.com', '09089389232', 'College of Computer Studies (CCS)', 'registrar', 'Active', '2026-03-11 06:23:06', '2026-03-11 06:23:06'),
(6, 'user2', '$2y$10$sHlf7m4qnI5uU/yB6KqLXOvwOhiHaCheKRxwle/SAAOVeJow0uDaC', 'Christopher Atinado', 'user2@gmail.com', '09089389232', 'College of Business Administration (CBA)', 'registrar', 'Active', '2026-03-11 08:34:31', '2026-03-11 08:34:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD PRIMARY KEY (`term_id`),
  ADD UNIQUE KEY `unique_term` (`academic_year`,`semester`);

--
-- Indexes for table `conflicts`
--
ALTER TABLE `conflicts`
  ADD PRIMARY KEY (`conflict_id`),
  ADD KEY `schedule_id_1` (`schedule_id_1`),
  ADD KEY `schedule_id_2` (`schedule_id_2`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `faculty_code` (`faculty_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_code` (`room_code`);

--
-- Indexes for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD PRIMARY KEY (`assignment_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `term_id` (`term_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `section_name` (`section_name`),
  ADD KEY `adviser_id` (`adviser_id`),
  ADD KEY `term_id` (`term_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `system_accounts`
--
ALTER TABLE `system_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_terms`
--
ALTER TABLE `academic_terms`
  MODIFY `term_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `conflicts`
--
ALTER TABLE `conflicts`
  MODIFY `conflict_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `room_assignments`
--
ALTER TABLE `room_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `system_accounts`
--
ALTER TABLE `system_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `conflicts`
--
ALTER TABLE `conflicts`
  ADD CONSTRAINT `conflicts_ibfk_1` FOREIGN KEY (`schedule_id_1`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conflicts_ibfk_2` FOREIGN KEY (`schedule_id_2`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_5` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`term_id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`adviser_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`term_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

ALTER TABLE faculty 
  ADD COLUMN job_type VARCHAR(20) NOT NULL DEFAULT 'Full-time',
  ADD COLUMN total_units INT NOT NULL DEFAULT 39;