-- ============================================================
-- MASTER DATABASE SETUP - IAS SUBSYSTEM
-- ============================================================
-- This file consolidates schema definitions, initial data, 
-- and common reference queries.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS ias_subsystem;
USE ias_subsystem;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. TABLE STRUCTURES
-- --------------------------------------------------------

-- Table structure for table `academic_terms`
CREATE TABLE IF NOT EXISTS `academic_terms` (
  `term_id` int(11) NOT NULL AUTO_INCREMENT,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester','Summer') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`term_id`),
  UNIQUE KEY `unique_term` (`academic_year`,`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `conflicts`
CREATE TABLE IF NOT EXISTS `conflicts` (
  `conflict_id` int(11) NOT NULL AUTO_INCREMENT,
  `conflict_type` enum('Faculty','Room','Section') NOT NULL,
  `schedule_id_1` int(11) NOT NULL,
  `schedule_id_2` int(11) NOT NULL,
  `description` text NOT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Unresolved','Resolved') DEFAULT 'Unresolved',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`conflict_id`),
  KEY `schedule_id_1` (`schedule_id_1`),
  KEY `schedule_id_2` (`schedule_id_2`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `faculty`
CREATE TABLE IF NOT EXISTS `faculty` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `max_teaching_hours` int(11) DEFAULT 24,
  `status` enum('Active','Inactive','On Leave') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `job_type` varchar(20) NOT NULL DEFAULT 'Full-time',
  `total_units` int(11) NOT NULL DEFAULT 39,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `faculty_code` (`faculty_code`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `rooms`
CREATE TABLE IF NOT EXISTS `rooms` (
  `room_id` int(11) NOT NULL AUTO_INCREMENT,
  `room_code` varchar(20) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `room_type` enum('Lecture','Laboratory','Conference','Auditorium') NOT NULL,
  `building` varchar(50) NOT NULL,
  `floor` varchar(10) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('Available','Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `allowed_program` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`room_id`),
  UNIQUE KEY `room_code` (`room_code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `room_assignments`
CREATE TABLE IF NOT EXISTS `room_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assignment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `schedules`
CREATE TABLE IF NOT EXISTS `schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `section_id` (`section_id`),
  KEY `subject_id` (`subject_id`),
  KEY `faculty_id` (`faculty_id`),
  KEY `room_id` (`room_id`),
  KEY `term_id` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `sections`
CREATE TABLE IF NOT EXISTS `sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `section_name` varchar(50) NOT NULL,
  `program` varchar(100) NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
  `total_students` int(11) DEFAULT 0,
  `adviser_id` int(11) DEFAULT NULL,
  `term_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive','Archived') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `section_name` (`section_name`),
  KEY `adviser_id` (`adviser_id`),
  KEY `term_id` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `subjects`
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` int(11) NOT NULL,
  `hours_per_week` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_code` (`subject_code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Table structure for table `system_accounts`
CREATE TABLE IF NOT EXISTS `system_accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` enum('registrar') NOT NULL DEFAULT 'registrar',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- 2. DUMPING DATA
-- --------------------------------------------------------

-- Dumping data for table `academic_terms`
INSERT INTO `academic_terms` (`term_id`, `academic_year`, `semester`, `is_active`, `created_at`) VALUES
(1, '2025-2026', '1st Semester', 0, '2026-03-07 11:07:29'),
(2, '2025-2026', '2nd Semester', 1, '2026-03-09 04:06:09');

-- Dumping data for table `conflicts`
INSERT INTO `conflicts` (`conflict_id`, `conflict_type`, `schedule_id_1`, `schedule_id_2`, `description`, `detected_at`, `status`, `resolved_at`, `resolved_note`) VALUES
(1, 'Faculty', 23, 24, 'Faculty conflict: Christoper Atinado is double-booked on Monday', '2026-03-11 08:50:47', 'Resolved', '2026-03-11 16:51:25', 'Auto-resolved: schedule was fixed in the timetable');

-- Dumping data for table `faculty`
INSERT INTO `faculty` (`faculty_id`, `faculty_code`, `first_name`, `last_name`, `department`, `email`, `phone`, `max_teaching_hours`, `status`, `created_at`, `updated_at`, `job_type`, `total_units`) VALUES
(8, 'FAC-001', 'Christoper', 'Atinado', 'College of Computer Studies (CCS)', 'usera@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:04:54', '2026-03-09 06:04:54', 'Full-time', 39),
(9, 'FAC-002', 'Arvie', 'Pintucan', 'College of Hospitality and Tourism Management (CHTM)', 'userb@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:05:09', '2026-03-09 06:05:09', 'Full-time', 39),
(10, 'FAC-003', 'Justin', 'Ansilig', 'College of Business Administration (CBA)', 'userc@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:05:23', '2026-03-09 06:05:23', 'Full-time', 39),
(11, 'FAC-004', 'Dave', 'Silva', 'College of Criminal Justice Education (CCJE)', 'usere@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:05:41', '2026-03-09 06:05:41', 'Full-time', 39),
(12, 'FAC-005', 'Den', 'Rell', 'College of Engineering (COE)', 'userf@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:06:05', '2026-03-09 06:06:05', 'Full-time', 39),
(13, 'FAC-006', 'Arvie', 'Atinado', 'College of Computer Studies (CCS)', 'userq@gmail.com', '09834773', 24, 'Active', '2026-03-09 06:06:45', '2026-03-09 06:06:45', 'Full-time', 39),
(14, 'FAC-007', 'Dave', 'Ansilig', 'College of Engineering (COE)', 'userr@gmail.com', '09834773', 24, 'Active', '2026-03-09 07:07:46', '2026-03-09 07:08:05', 'Full-time', 39);

-- Dumping data for table `rooms`
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

-- Dumping data for table `room_assignments`
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

-- Dumping data for table `schedules`
INSERT INTO `schedules` (`schedule_id`, `section_id`, `subject_id`, `faculty_id`, `room_id`, `term_id`, `day_of_week`, `start_time`, `end_time`, `status`, `created_at`, `updated_at`) VALUES
(15, 20, 95, 11, 15, 2, 'Monday', '06:00:00', '09:00:00', 'Active', '2026-03-09 06:08:00', '2026-03-09 12:13:01'),
(18, 19, 65, 10, 2, 2, 'Tuesday', '06:00:00', '12:30:00', 'Active', '2026-03-09 06:43:46', '2026-03-11 08:32:09'),
(19, 20, 94, 11, 15, 2, 'Saturday', '06:00:00', '07:30:00', 'Active', '2026-03-09 07:59:10', '2026-03-10 11:41:05'),
(20, 21, 107, 12, 18, 2, 'Monday', '20:00:00', '21:00:00', 'Active', '2026-03-09 12:05:53', '2026-03-09 12:05:53'),
(22, 20, 95, 11, 15, 2, 'Wednesday', '16:00:00', '21:00:00', 'Active', '2026-03-11 07:09:47', '2026-03-11 08:30:10'),
(23, 22, 11, 8, 2, 2, 'Monday', '06:00:00', '07:00:00', 'Active', '2026-03-11 08:11:39', '2026-03-11 08:28:01'),
(24, 17, 11, 13, 5, 2, 'Monday', '06:00:00', '09:30:00', 'Active', '2026-03-11 08:36:14', '2026-03-11 08:51:23'),
(25, 21, 107, 12, 17, 2, 'Tuesday', '06:00:00', '21:00:00', 'Active', '2026-03-11 08:39:06', '2026-03-11 08:39:06');

-- Dumping data for table `sections`
INSERT INTO `sections` (`section_id`, `section_name`, `program`, `year_level`, `total_students`, `adviser_id`, `term_id`, `status`, `created_at`, `updated_at`) VALUES
(17, 'BSIT - 2217', 'BSIT', '2nd Year', 40, 13, 2, 'Active', '2026-03-09 04:11:22', '2026-03-09 06:25:55'),
(18, 'BSTM - 1214', 'BSTM', '1st Year', 40, 9, 2, 'Active', '2026-03-09 04:11:38', '2026-03-09 06:25:42'),
(19, 'BSBA - 3217', 'BSBA', '3rd Year', 40, 10, 2, 'Active', '2026-03-09 04:11:51', '2026-03-09 06:25:39'),
(20, 'BSCRIM - 4216', 'BSCRIM', '4th Year', 40, 11, 2, 'Active', '2026-03-09 04:12:06', '2026-03-09 06:25:36'),
(21, 'BSCE - 2214', 'BSCE', '2nd Year', 40, 12, 2, 'Active', '2026-03-09 04:12:19', '2026-03-09 06:25:06'),
(22, 'BSIT - 2201', 'BSIT', '2nd Year', 40, 13, 2, 'Active', '2026-03-09 06:25:32', '2026-03-09 06:25:32'),
(23, 'BSTM - 1216', 'BSTM', '1st Year', 40, 9, 2, 'Active', '2026-03-11 08:11:26', '2026-03-11 08:11:26');

-- Dumping data for table `subjects`
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
(23, 'BSIT413', 'Cloud Security', 3, 3, 'BS Information Technology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(25, 'BSTM101', 'Introduction to Tourism', 3, 3, 'BS Tourism Management', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(31, 'BSTM201', 'Travel Agency Operations', 3, 3, 'BS Tourism Management', '2nd Year (1st Sem)', 'Active', '2026-03-08 09:17:14', '2026-03-08 09:17:14'),
(49, 'BSBA101', 'Principles of Management', 3, 3, 'BS Business Administration', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(65, 'BSBA312', 'Leadership and Management', 3, 3, 'BS Business Administration', '3rd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(73, 'BSCR101', 'Introduction to Criminology', 3, 3, 'BS Criminology', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(94, 'BSCR411', 'Criminal Justice Policies', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(95, 'BSCR412', 'Special Topics in Criminology', 3, 3, 'BS Criminology', '4th Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(97, 'BSCE101', 'Engineering Mathematics 1', 3, 3, 'BS Civil Engineering', '1st Year (1st Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56'),
(107, 'BSCE212', 'Geotechnical Engineering 1', 3, 3, 'BS Civil Engineering', '2nd Year (2nd Sem)', 'Active', '2026-03-08 09:17:56', '2026-03-08 09:17:56');

-- Dumping data for table `system_accounts`
INSERT INTO `system_accounts` (`account_id`, `username`, `password`, `full_name`, `email`, `phone`, `department`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'registrar', 'registrar123', 'Registrar Officer', 'registrar@school.edu', NULL, NULL, 'registrar', 'Active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
(5, 'user1', '$2y$10$NAyETrRLiIzlBDCkuhIc5evKM9sBZx6gaIC.EPCJHNQCSiM8BgEUK', 'Milchor Buctot', 'user1@gmail.com', '09089389232', 'College of Computer Studies (CCS)', 'registrar', 'Active', '2026-03-11 06:23:06', '2026-03-11 06:23:06'),
(6, 'user2', '$2y$10$sHlf7m4qnI5uU/yB6KqLXOvwOhiHaCheKRxwle/SAAOVeJow0uDaC', 'Christopher Atinado', 'user2@gmail.com', '09089389232', 'College of Business Administration (CBA)', 'registrar', 'Active', '2026-03-11 08:34:31', '2026-03-11 08:34:31');

-- --------------------------------------------------------
-- 3. CONSTRAINTS & AUTO_INCREMENT
-- --------------------------------------------------------

-- AUTO_INCREMENT for table `academic_terms`
ALTER TABLE `academic_terms` MODIFY `term_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- AUTO_INCREMENT for table `conflicts`
ALTER TABLE `conflicts` MODIFY `conflict_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- AUTO_INCREMENT for table `faculty`
ALTER TABLE `faculty` MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

-- AUTO_INCREMENT for table `rooms`
ALTER TABLE `rooms` MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

-- AUTO_INCREMENT for table `room_assignments`
ALTER TABLE `room_assignments` MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

-- AUTO_INCREMENT for table `schedules`
ALTER TABLE `schedules` MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

-- AUTO_INCREMENT for table `sections`
ALTER TABLE `sections` MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

-- AUTO_INCREMENT for table `subjects`
ALTER TABLE `subjects` MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

-- AUTO_INCREMENT for table `system_accounts`
ALTER TABLE `system_accounts` MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

-- Constraints for dumped tables
ALTER TABLE `conflicts`
  ADD CONSTRAINT `conflicts_ibfk_1` FOREIGN KEY (`schedule_id_1`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conflicts_ibfk_2` FOREIGN KEY (`schedule_id_2`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE;

ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_5` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`term_id`) ON DELETE CASCADE;

ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`adviser_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`term_id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- 4. REFERENCE QUERIES (From sql_queries.sql)
-- ============================================================
/*
-- Active Sections Count
SELECT COUNT(*) as active_sections FROM sections WHERE status = 'Active';

-- Scheduled Classes Count
SELECT COUNT(*) as scheduled_classes FROM schedules WHERE status = 'Active';

-- Available Rooms Count
SELECT COUNT(*) as available_rooms FROM rooms WHERE status = 'Available';

-- Active Faculty Count
SELECT COUNT(*) as active_faculty FROM faculty WHERE status = 'Active';

-- Get Faculty Teaching Hours Summary
SELECT 
    f.faculty_id,
    f.faculty_code,
    CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
    f.department,
    f.max_teaching_hours,
    COUNT(DISTINCT s.subject_id) as subject_count,
    COUNT(DISTINCT s.section_id) as section_count,
    COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) as teaching_hours,
    ROUND((COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) / f.max_teaching_hours) * 100, 0) as load_percentage,
    CASE 
        WHEN COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) > f.max_teaching_hours THEN 'Overloaded'
        WHEN COALESCE(SUM(TIMESTAMPDIFF(HOUR, s.start_time, s.end_time)), 0) >= (f.max_teaching_hours * 0.85) THEN 'Full Load'
        ELSE 'Underloaded'
    END as load_status
FROM faculty f
LEFT JOIN schedules s ON f.faculty_id = s.faculty_id AND s.status = 'Active'
WHERE f.status = 'Active'
GROUP BY f.faculty_id, f.faculty_code, f.first_name, f.last_name, f.department, f.max_teaching_hours
ORDER BY teaching_hours DESC;
*/

-- ============================================================
-- 5. NOTES ON LOGIN (From users_table.sql)
-- ============================================================
-- Hardcoded credentials (in login.php):
--   admin      / admin123
--   registrar  / registrar123
