-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2026 at 09:04 AM
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
-- Database: `scholarswap`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_activity`
--

CREATE TABLE `admin_login_activity` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `login_time` datetime DEFAULT NULL,
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login_activity`
--

INSERT INTO `admin_login_activity` (`id`, `admin_id`, `login_time`, `logout_time`, `ip_address`, `user_agent`, `status`) VALUES
(13, 1, '2026-03-10 08:11:15', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'success'),
(14, 1, '2026-03-11 08:21:48', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'success'),
(15, 1, '2026-03-11 22:16:16', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(16, 1, '2026-03-12 11:20:55', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(17, 1, '2026-03-12 11:43:38', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(18, 1, '2026-03-12 11:45:03', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(19, 1, '2026-03-12 11:46:25', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(20, 1, '2026-03-12 11:47:44', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(21, 1, '2026-03-12 11:48:17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(22, 1, '2026-03-12 11:50:52', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(23, 1, '2026-03-12 11:55:40', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(24, 1, '2026-03-12 21:28:46', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(25, 1, '2026-03-12 22:01:24', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(26, 1, '2026-03-12 22:02:11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(27, 1, '2026-03-12 22:02:44', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(28, 1, '2026-03-12 22:07:21', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(29, 1, '2026-03-13 20:50:55', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(30, 1, '2026-03-13 22:53:19', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(31, 1, '2026-03-14 10:50:01', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(32, 1, '2026-03-14 19:50:45', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(33, 1, '2026-03-14 20:18:38', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(34, 1, '2026-03-14 20:44:31', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(35, 1, '2026-03-14 22:11:41', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(36, 1, '2026-03-15 11:09:36', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(37, 3, '2026-03-15 12:33:52', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(38, 1, '2026-03-15 13:05:34', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(39, 3, '2026-03-15 13:11:24', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(40, 1, '2026-03-16 12:11:19', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(41, 1, '2026-03-16 22:23:07', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(42, 1, '2026-03-17 11:22:37', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success'),
(43, 1, '2026-03-17 11:30:39', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'success');

-- --------------------------------------------------------

--
-- Table structure for table `admin_role`
--

CREATE TABLE `admin_role` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(60) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6366f1',
  `icon` varchar(60) DEFAULT 'fa-shield-halved',
  `level` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_role`
--

INSERT INTO `admin_role` (`role_id`, `role_name`, `display_name`, `description`, `color`, `icon`, `level`, `is_active`, `created_at`) VALUES
(1, 'superadmin', 'Super Admin', 'Full system access — can manage roles, admins, all content and settings.', '#7c3aed', 'fa-crown', 100, 1, '2026-03-14 20:28:00'),
(2, 'admin', 'Admin', 'Content and user management — approvals, reports, notifications.', '#2563eb', 'fa-shield-halved', 50, 1, '2026-03-14 20:28:00'),
(3, 'moderator', 'Moderator', 'Content review only — approve or reject pending notes, books and newspapers.', '#0d9488', 'fa-user-shield', 25, 1, '2026-03-14 20:28:00'),
(4, 'viewer', 'Viewer', 'Read-only access — dashboard and content lists, no actions.', '#94a3b8', 'fa-eye', 10, 1, '2026-03-14 20:28:00');

-- --------------------------------------------------------

--
-- Table structure for table `admin_user`
--

CREATE TABLE `admin_user` (
  `admin_id` int(11) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `subjects` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_user`
--

INSERT INTO `admin_user` (`admin_id`, `first_name`, `last_name`, `username`, `profile_image`, `dob`, `gender`, `state`, `district`, `email`, `phone`, `role`, `course`, `institution`, `subjects`, `bio`, `current_address`, `permanent_address`, `password`, `status`, `created_at`, `updated_at`) VALUES
(1, 'DIVAKAR', 'RAJPUT', 'divakar9758', 'uploads/admin_profiles/admin_1_1773499560.jpg', '2004-01-04', 'male', 'Uttar Pradesh', 'aligarh', 'divakarchauhan9758@gmail.com', '+919758296457', 'superadmin', 'BCA', 'RAJA MAHENDRA PRATAP SINGH UNIVERSITY, ALIGARH', 'MATH, ENGLISH, COMPUTER SCIENCE', 'I AM A SUPER ADMIN.', 'Maa Jagdamba Usha Devi Mandir Jartauli', 'Maa Jagdamba Usha Devi Mandir Jartauli', '$2y$10$8MI2NUjtAwZVo05MnRZmKusjUTqdU.RvJLO1N/H4D5g7v2X/euGuO', 'approved', '2026-03-06 03:25:14', '2026-03-14 14:47:44'),
(3, 'shiva', 'thakur', 'shivathakur9758', 'uploads/admin_profiles/admin_3_1773558315.jpeg', '2004-01-04', 'male', 'Uttar Pradesh', 'aligarh', 'shivathakur9758@gmail.com', '+919758296457', 'moderator', 'B.TECH 4th Year', 'shivathakur9758@gmail.com', 'MATH, ENGLISH, COMPUTER SCIENCE', 'dfs', 'VILL SHAHANAGAR SOROLA POST JARTAULI ALIGARH UTTAR PRADESH 202137', 'VILL SHAHANAGAR SOROLA POST JARTAULI ALIGARH UTTAR PRADESH 202137', '$2y$10$CoKO0TH2sm8Kmnu/Rm0bDuNK2.GdsjAZ4Q5hzpCy9kPgs/9mAspAK', 'approved', '2026-03-15 07:00:48', '2026-03-15 07:40:55');

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `bookmark_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookmarks`
--

INSERT INTO `bookmarks` (`bookmark_id`, `user_id`, `document_type`, `document_id`, `created_at`) VALUES
(32, 6, 'book', 'BK20260313061858226', '2026-03-17 06:55:41'),
(34, 6, 'note', 'NT20260316102415503', '2026-03-17 07:36:59');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `b_code` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `subject` varchar(150) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(20) DEFAULT 'pdf',
  `document_type` varchar(255) NOT NULL DEFAULT 'book',
  `approval_status` varchar(255) DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `rating` int(11) NOT NULL,
  `publication_name` varchar(255) NOT NULL,
  `published_year` year(4) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `b_code`, `user_id`, `title`, `author`, `description`, `subject`, `class_level`, `file_path`, `cover_image`, `file_size`, `file_type`, `document_type`, `approval_status`, `approved_by`, `approved_at`, `is_featured`, `view_count`, `download_count`, `rating`, `publication_name`, `published_year`, `created_at`, `updated_at`) VALUES
(1, 'BK20260313061820966', 3, 'MATHEMATICS -\nTextbook for Class IX', 'NCERT', 'This is NCERT Mathematics subject which cover all the topics of ncert.', 'mathematics', 'Class 9', 'admin/user_pages/uploads/books/pdf/book_69af91ae96d24.pdf', 'cover_69af91ae970e0.jpg', 18326162, 'application/pdf', 'book', 'approved', 1, '2026-03-10 04:36:32', 0, 2, 0, 0, 'ncert', '2006', '2026-03-10 09:06:14', '2026-03-13 10:48:36'),
(2, 'BK20260313061843490', 3, 'C Programming', 'VARDHAMAN COLLEGE OF ENGINEERING', 'A Computer is an electronic device which performs operations such as accepts data\r\nAs an input, store the data, manipulate or process the data and produce the results an output.\r\nMain task performed by a computer', 'computer science', 'Undergraduate', 'admin/user_pages/uploads/books/pdf/book_69af9dfa9e082.pdf', 'cover_69af9dfa9e54c.png', 3752847, 'application/pdf', 'book', 'approved', 1, '2026-03-10 05:28:59', 0, 43, 8, 4, 'VARDHAMAN COLLEGE OF ENGINEERING', '2026', '2026-03-10 09:58:42', '2026-03-16 15:03:03'),
(3, 'BK20260313061850433', 3, 'HTML', 'Tutorials Point', 'HTML was created by Berners-Lee in late 1991 but \"HTML 2.0\" was the first standard HTML\r\nspecification which was published in 1995. HTML 4.01 was a major version of HTML and it\r\nwas published in late 1999. Though HTML 4.01 version is widely used but currently we are\r\nhaving HTML-5 version which is an extension to HTML 4.01, and this version was published\r\nin 2012.', 'computer science', 'Undergraduate', 'admin/user_pages/uploads/books/pdf/book_69afa422c6ca5.pdf', 'cover_69afa422c71fa.png', 2116291, 'application/pdf', 'book', 'approved', 1, '2026-03-10 05:55:16', 0, 13, 1, 3, 'Tutorials Point', '2026', '2026-03-10 10:24:58', '2026-03-16 14:31:21'),
(4, 'BK20260313061858226', 3, 'CONCEPTS OF PROGRAMMING LANGUAGES', 'ROBERT W. SEBESTA', 'This book describes the fundamental concepts of programming languages by\r\ndiscussing the design issues of the various language constructs, examining the\r\ndesign choices for these constructs in some of the most common languages,\r\nand critically comparing design alternatives.', 'computer science', 'General', 'admin/user_pages/uploads/books/pdf/book_69afa5bfbc581.pdf', 'cover_69afa5bfbc9a6.png', 4048281, 'application/pdf', 'book', 'approved', 1, '2026-03-10 06:02:09', 1, 26, 2, 5, 'University of Colorado at Colorado Springs', '2026', '2026-03-10 10:31:51', '2026-03-17 12:26:11'),
(5, 'BK20260316175251121', 3, 'Computer Organization and Architecture', 'VARDHAMAN COLLEGE OF ENGINEERING', 'Computer Organization and Architecture (COA) defines the functional design and physical implementation of computer systems. Architecture refers to programmer-visible attributes like instruction sets (ISA), while Organization focuses on operational units—CPU, memory, I/O—that realize these specs. Key concepts include pipelining, memory hierarchy, and data paths. \r\nGeeksforGeeks\r\nGeeksforGeeks\r\n +3', 'computer science', 'General', 'admin/user_pages/uploads/books/pdf/book_69b83563d0d30.pdf', 'cover_69b83563d10ca.png', 3390117, 'application/pdf', 'book', 'approved', 1, '2026-03-16 18:13:58', 0, 22, 1, 5, 'VARDHAMAN COLLEGE OF ENGINEERING', '2022', '2026-03-16 22:22:51', '2026-03-17 21:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(10) UNSIGNED NOT NULL,
  `resource_id` varchar(255) NOT NULL,
  `document_type` varchar(20) NOT NULL DEFAULT 'note',
  `user_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `body` text NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`comment_id`, `resource_id`, `document_type`, `user_id`, `parent_id`, `body`, `is_deleted`, `created_at`, `updated_at`) VALUES
(11, 'BK20260313061843490', 'book', 4, NULL, 'nice', 0, '2026-03-13 21:04:24', '2026-03-13 21:04:24');

-- --------------------------------------------------------

--
-- Table structure for table `downloads`
--

CREATE TABLE `downloads` (
  `download_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_type` enum('note','book','newspaper') NOT NULL,
  `document_id` varchar(255) NOT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `downloads`
--

INSERT INTO `downloads` (`download_id`, `user_id`, `document_type`, `document_id`, `downloaded_at`, `created_at`) VALUES
(8, 6, 'note', 'NT20260316102415503', '2026-03-17 07:30:31', '2026-03-17 07:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `encryption_keys`
--

CREATE TABLE `encryption_keys` (
  `key_id` int(10) UNSIGNED NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `secret_key` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rotated_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `rotated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encryption_keys`
--

INSERT INTO `encryption_keys` (`key_id`, `key_name`, `secret_key`, `is_active`, `created_at`, `rotated_at`, `created_by`, `rotated_by`) VALUES
(1, 'primary', 'ScholarSwap@SecretKey#2026', 1, '2026-03-12 23:00:23', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `category` enum('general','bug_report','feature_request','content_quality','ui_ux','performance','other') NOT NULL DEFAULT 'general',
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `rating` tinyint(3) UNSIGNED DEFAULT NULL CHECK (`rating` between 1 and 5),
  `page_context` varchar(100) DEFAULT NULL,
  `status` enum('new','in_review','resolved','closed') NOT NULL DEFAULT 'new',
  `admin_reply` text DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `replied_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `user_id`, `category`, `subject`, `message`, `rating`, `page_context`, `status`, `admin_reply`, `replied_at`, `replied_by`, `created_at`, `updated_at`) VALUES
(3, 6, 'general', 'Nice Platform', 'this is a very nice platform for students to study the notes', 5, 'homepage', 'resolved', 'thankyou  for your valuable feedback ', '2026-03-15 11:47:28', 1, '2026-03-14 22:09:42', '2026-03-15 11:59:43'),
(4, 4, '', 'Great platform for student', 'this is a student and tutor friendly platform here student can read and study material without download.', 4, 'homepage', 'resolved', 'Thank you for taking the time to share your feedback! We genuinely appreciate hearing from our users — your insights help us improve ScholarSwap for everyone. We have noted your feedback and our team will review it carefully. Feel free to reach out if you have any other suggestions or concerns.', '2026-03-15 13:44:51', 3, '2026-03-15 12:43:39', '2026-03-15 13:44:51');

-- --------------------------------------------------------

--
-- Table structure for table `follows`
--

CREATE TABLE `follows` (
  `follow_id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `follows`
--

INSERT INTO `follows` (`follow_id`, `follower_id`, `following_id`, `created_at`) VALUES
(18, 4, 3, '2026-03-10 05:33:06'),
(23, 3, 4, '2026-03-16 16:42:24'),
(45, 6, 3, '2026-03-17 05:33:38'),
(47, 3, 6, '2026-03-17 06:38:44');

-- --------------------------------------------------------

--
-- Table structure for table `newspapers`
--

CREATE TABLE `newspapers` (
  `newspaper_id` int(11) NOT NULL,
  `n_code` varchar(255) DEFAULT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `language` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT 'newspaper',
  `approval_status` varchar(255) DEFAULT 'pending',
  `is_featured` tinyint(1) DEFAULT 0,
  `rating` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `newspapers`
--

INSERT INTO `newspapers` (`newspaper_id`, `n_code`, `admin_id`, `title`, `publisher`, `language`, `region`, `publication_date`, `file_path`, `file_size`, `file_type`, `document_type`, `approval_status`, `is_featured`, `rating`, `view_count`, `download_count`, `created_at`) VALUES
(1, 'NP20260312174439568', 1, 'Amar Ujala - Dehli', 'amar ujala', 'Hindi', 'delhi', '2026-03-10', 'admin/uploads/newspapers/1773110526_c00a2c5b88_Amar_Ujala_Delhi_20260310.pdf', 39418409, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-10 02:42:06'),
(2, 'NP20260312174526381', 1, 'Dainik Bhasakar', 'dainik bhasakar', 'Hindi', 'delhi', '2026-03-10', 'admin/uploads/newspapers/1773110740_257b9679ed_Dainik_Bhaskar___________________________20260310.pdf', 21923429, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-10 02:45:40'),
(3, 'NP20260312174602441', 1, 'Hindustan', 'hindustan', 'Hindi', 'delhi', '2026-03-10', 'admin/uploads/newspapers/1773110980_d191e2c3b5_Hindustan_Delhi_20260310.pdf', 15850681, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-10 02:49:40'),
(4, 'NP20260312174610397', 1, 'Hindustan times', 'himdustan times', 'English', 'delhi', '2026-03-10', 'admin/uploads/newspapers/1773111173_0d1f542d01_Hindustan_Times_Delhi_20260310.pdf', 33974709, 'application/pdf', 'newspaper', 'approved', 0, NULL, 2, 0, '2026-03-10 02:52:53'),
(5, 'NP20260312174618862', 1, 'the economics times', 'the economics times', 'English', 'delhi', '2026-03-10', 'admin/uploads/newspapers/1773111533_ba29ea4646_The_Economic_Times_Delhi_20260310.pdf', 29236831, 'application/pdf', 'newspaper', 'approved', 0, NULL, 1, 0, '2026-03-10 02:58:53'),
(6, 'NP20260312174628969', 1, 'amar ujala', 'amar ujala', 'Hindi', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773206791_be7c50180c_Amar_Ujala_Delhi_20260311.pdf', 50949876, 'application/pdf', 'newspaper', 'approved', 0, NULL, 1, 0, '2026-03-11 05:26:31'),
(7, 'NP20260312174636472', 1, 'danik jagran', 'danik jagran', 'Hindi', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773207050_6883735dbe_Dainik_Jagran_Delhi_City_20260311.pdf', 39775258, 'application/pdf', 'newspaper', 'approved', 0, NULL, 1, 0, '2026-03-11 05:30:50'),
(8, 'NP20260312174645781', 1, 'dainik bhaskar', 'dainik bhaskar', 'Hindi', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773247913_6467145e9d_Dainik_Bhaskar___________________________20260311.pdf', 24301453, 'application/pdf', 'newspaper', 'approved', 0, NULL, 1, 0, '2026-03-11 16:51:53'),
(9, 'NP20260312174653234', 1, 'hindustan', 'hindustan', 'Hindi', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773248217_9d36ff444c_Hindustan_Delhi_20260311.pdf', 15226359, 'application/pdf', 'newspaper', 'approved', 0, NULL, 1, 0, '2026-03-11 16:56:57'),
(10, 'NP20260312174701124', 1, 'hindustan times', 'himdustan times', 'English', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773248461_e432df7040_Hindustan_Times_Delhi_20260311.pdf', 34257381, 'application/pdf', 'newspaper', 'approved', 0, 4, 1, 0, '2026-03-11 17:01:01'),
(11, 'NP20260312174709465', 1, 'the economic times', 'the economics times', 'English', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773249160_0e7e2eb813_The_Economic_Times_Delhi_20260311.pdf', 34625242, 'application/pdf', 'newspaper', 'approved', 0, NULL, 2, 0, '2026-03-11 17:12:40'),
(12, 'NP20260312174719997', 1, 'mint', 'mint', 'English', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773249249_903a88806c_Mint_Delhi_20260311.pdf', 23413092, 'application/pdf', 'newspaper', 'approved', 0, NULL, 3, 0, '2026-03-11 17:14:09'),
(13, 'NP20260312174730206', 1, 'the financial express', 'the financial express', 'English', 'delhi', '2026-03-11', 'admin/uploads/newspapers/1773249937_608ba00616_The_Financial_Express_Delhi_20260311.pdf', 15467184, 'application/pdf', 'newspaper', 'approved', 0, 5, 9, 1, '2026-03-11 17:25:37'),
(14, 'NP20260312174741251', 1, 'amar ujala', 'amar ujala', 'Hindi', 'aligarh', '2026-03-12', 'admin/uploads/newspapers/1773305414_c65f9132bd_Amar_Ujala_Aligarh_city_20260312.pdf', 22045776, 'application/pdf', 'newspaper', 'approved', 0, NULL, 3, 0, '2026-03-12 08:50:14'),
(15, 'NP20260312174749264', 1, 'amar ujala', 'amar ujala', 'Hindi', 'aligarh dehat', '2026-03-12', 'admin/uploads/newspapers/1773305595_492c0bc982_Amar_Ujala_Aligarh_dehat_20260312.pdf', 22060906, 'application/pdf', 'newspaper', 'approved', 0, 4, 25, 3, '2026-03-12 08:53:15'),
(16, 'NP20260312175351151', 1, 'dainik bhaskar', 'dainik bhaskar', 'Hindi', 'new delhi', '2026-03-12', 'admin/uploads/newspapers/1773334431_e2579b3e5c_Dainik_Bhaskar___________________________20260312.pdf', 21444177, 'application/pdf', 'newspaper', 'approved', 0, NULL, 13, 2, '2026-03-12 16:53:51'),
(17, 'NP20260313182416747', 1, 'hindustan', 'hindustan', 'Hindi', 'delhi', '2026-03-13', 'admin/uploads/newspapers/1773422656_3be6794b6d_Hindustan_Delhi_20260313.pdf', 19024723, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-13 17:24:16'),
(18, 'NP20260313182521554', 1, 'dainik jagran', 'danik jagran', 'Hindi', 'delhi city', '2026-03-13', 'admin/uploads/newspapers/1773422721_49c9e9cb70_Dainik_Jagran_Delhi_City_20260313.pdf', 36183423, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-13 17:25:21'),
(19, 'NP20260313182607799', 1, 'dainik bhaskar', 'dainik bhaskar', 'Hindi', 'new delhi', '2026-03-13', 'admin/uploads/newspapers/1773422767_a843fa2644_Dainik_Bhaskar___________________________20260313.pdf', 21489381, 'application/pdf', 'newspaper', 'approved', 0, NULL, 1, 0, '2026-03-13 17:26:07'),
(20, 'NP20260313182641301', 1, 'amar ujala', 'amar ujala', 'Hindi', 'delhi', '2026-03-13', 'admin/uploads/newspapers/1773422801_ed501ed3c0_Amar_Ujala_Delhi_20260313.pdf', 29522116, 'application/pdf', 'newspaper', 'approved', 0, NULL, 2, 0, '2026-03-13 17:26:41'),
(21, 'NP202603141048326263', 1, 'amar ujala', 'amar ujala', 'Hindi', 'delhi', '2026-03-14', 'admin/uploads/newspapers/1773481712_4d142112e6_Amar_Ujala_Delhi_20260314.pdf', 44381740, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-14 09:48:32'),
(22, 'NP202603141111095738', 1, 'times of india', 'times of india', 'English', 'delhi', '2026-03-14', 'admin/uploads/newspapers/1773483069_e6361bd04a_Times_of_India_Delhi_20260314.pdf', 65896392, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-14 10:11:09'),
(23, 'NP202603141111593253', 1, 'dainik jagran', 'dainik bhaskar', 'Hindi', 'delhi', '2026-03-14', 'admin/uploads/newspapers/1773483119_6c86b65445_Dainik_Jagran_Delhi_City_20260314.pdf', 37053208, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-14 10:11:59'),
(24, 'NP202603141112336188', 1, 'hindustan times', 'himdustan times', 'English', 'delhi', '2026-03-14', 'admin/uploads/newspapers/1773483153_31ea6a2bfa_Hindustan_Times_Delhi_20260314.pdf', 40835856, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-14 10:12:33'),
(25, 'NP202603141114429149', 1, 'dainik bhaskar', 'dainik bhaskar', 'Hindi', 'delhi', '2026-03-14', 'admin/uploads/newspapers/1773483282_f920575617_Dainik_Bhaskar___________________________20260314.pdf', 23577219, 'application/pdf', 'newspaper', 'approved', 0, NULL, 0, 0, '2026-03-14 10:14:42'),
(26, 'NP202603141115237910', 1, 'mint', 'mint', 'English', 'delhi', '2026-03-14', 'admin/uploads/newspapers/1773483323_11a4576d1c_Mint_Delhi_20260314.pdf', 21254739, 'application/pdf', 'newspaper', 'approved', 0, 4, 7, 0, '2026-03-14 10:15:23'),
(27, 'NP202603161030416900', 1, 'amar ujala', 'amar ujala', 'Hindi', 'delhi', '2026-03-16', 'admin/uploads/newspapers/1773653441_58212299fd_Amar_Ujala_Delhi_20260316.pdf', 24526405, 'application/pdf', 'newspaper', 'approved', 0, 5, 15, 2, '2026-03-16 09:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `note_id` int(11) NOT NULL,
  `n_code` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `subject` varchar(150) NOT NULL,
  `course` varchar(150) DEFAULT NULL,
  `notes_level` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(20) DEFAULT 'pdf',
  `document_type` varchar(255) NOT NULL DEFAULT 'note',
  `approval_status` varchar(255) DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `rating` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`note_id`, `n_code`, `user_id`, `title`, `description`, `subject`, `course`, `notes_level`, `uploaded_by`, `file_path`, `file_size`, `file_type`, `document_type`, `approval_status`, `approved_by`, `approved_at`, `is_featured`, `download_count`, `view_count`, `rating`, `created_at`, `updated_at`) VALUES
(13, 'NT20260313061940741', 3, '100+ JavaScript Interview & Viva Questions with Detailed Answers for software developer', 'PDF notes of JavaScript interview and viva questions with answers over 50 essential questions and detailed answers. Covering fundamental and advanced concepts like closures, promises, ES6 features, asynchronous JavaScript, and much more. This guide is perfect for both beginners and advanced developers aiming to ace their next interview or viva in JavaScript.', 'computer science', 'engineering', 'class notes', 'student', 'admin/user_pages/uploads/notes/note_69afaa88494cf.pdf', 483178, 'application/pdf', 'note', 'approved', 1, '2026-03-10 06:23:03', 0, 1, 18, 0, '2026-03-10 10:52:16', '2026-03-16 14:19:15'),
(14, 'NT20260313061950923', 3, '. Net framework and rest api report', 'Semester report for . Net framework and REST API\'S free notes for B.Tech students from AKTU, MJPRU, KNIT, and LIT and RRSIMT Amethi (B.Tech).', 'computer science', 'engineering', 'class notes', 'student', 'admin/user_pages/uploads/notes/note_69b10379c3302.pdf', 250488, 'application/pdf', 'note', 'approved', 1, '2026-03-11 06:54:15', 0, 0, 8, 4, '2026-03-11 11:24:01', '2026-03-13 21:38:10'),
(15, 'NT20260313062005124', 4, 'Clinical Research in Pharmacology: Key Phases, Roles, and Trends Explained', 'PDF notes of clinical research in pharmacology, including trial phases, ethical considerations, regulatory bodies, and emerging trends like virtual trials and AI-driven studies. A must-read for aspiring healthcare professionals free notes for B.Tech students from AKTU, MJPRU, KNIT, and LIT and RRSIMT Amethi (B.Tech).!', 'medical', 'medical', 'class notes', 'tutor', 'admin/user_pages/uploads/notes/note_69b27dfbbbfac.pdf', 83675, 'application/pdf', 'note', 'approved', 1, '2026-03-12 09:49:14', 0, 0, 1, 0, '2026-03-12 14:18:59', '2026-03-13 10:50:13'),
(16, 'NT20260313062017511', 4, 'प्रतिदर्श प्रश्न पत्र 2024–25 कक्षा 12 सामान्य हिंदी | Class 12 General Hindi Sample Paper 2024-25', 'कक्षा 12 सामान्य हिंदी का प्रतिदर्श प्रश्न पत्र (2024–25) अब उपलब्ध है। यह पेपर 3 घंटे 15 मिनट में हल करना है और कुल अंक 100 निर्धारित हैं। बोर्ड परीक्षा की तैयारी के लिए यह सैंपल पेपर उपयोगी साबित होगा।', 'hindi', 'class 12', 'question_bank', 'tutor', 'admin/user_pages/uploads/notes/note_69b27eae2c1b5.pdf', 86914, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:05:19', 0, 0, 1, 0, '2026-03-12 14:21:58', '2026-03-13 10:50:22'),
(17, 'NT20260313062024770', 4, 'Database Management System GATE Notes', 'Complete DBMS notes | Introduction of DBMS Functional dependency GATE 2025-26 PYQ free notes for B.Tech students from AKTU, MJPRU, KNIT, and LIT and RRSIMT Amethi (B.Tech).', 'computer science', 'gate', 'hand written', 'tutor', 'admin/user_pages/uploads/notes/note_69b27f92173d4.pdf', 5492976, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:05:19', 0, 0, 1, 0, '2026-03-12 14:25:46', '2026-03-13 10:50:29'),
(18, 'NT20260313070158690', 4, '150+ Ultimate Web Development Questions and Answers for interviews and viva: HTML, CSS, JavaScript, Frameworks, and Best Practices', 'Unlock your web development potential with 150+ comprehensive questions with answers. Covers HTML, CSS, JavaScript, React, Angular, Vue.js, performance optimization, security, and modern tools like Webpack and Node.js. Ideal for interviews, learning, and career growth.', 'computer science', 'html, css, javascript', 'question_bank', 'tutor', 'admin/user_pages/uploads/notes/note_69b28106bff99.pdf', 238765, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:05:19', 0, 0, 0, 0, '2026-03-12 14:31:58', '2026-03-13 11:32:05'),
(19, 'NT20260313070206908', 4, 'Computer Network TCP UDP', 'Short notes TCP UDP header | Computer network free notes for B.Tech students from AKTU, MJPRU, KNIT, and LIT and RRSIMT Amethi (B.Tech).', 'computer science', 'engineering', 'hand written', 'tutor', 'admin/user_pages/uploads/notes/note_69b2821fc51d4.pdf', 4592989, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:06:50', 0, 0, 0, 0, '2026-03-12 14:36:39', '2026-03-13 11:32:13'),
(20, 'NT20260313070215312', 4, 'Python for Beginners: A Complete Guide to Get Started with Programming', 'Handwritten notes of Python for development and covering complete syllbus of B.Tech students from AKTU, MJPRU, KNIT, and LIT and RRSIMT Amethi (B.Tech).', 'computer science', 'engineering', 'hand written', 'tutor', 'admin/user_pages/uploads/notes/note_69b282c02a944.pdf', 3383093, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:15:31', 0, 0, 0, 0, '2026-03-12 14:39:20', '2026-03-13 11:32:22'),
(21, 'NT20260313070223304', 4, 'Manual software testing interview notes with Answers', 'There is the list of Manual testing interview Questions and Answers notes pdf', 'computer science', 'engineering', 'question_bank', 'tutor', 'admin/user_pages/uploads/notes/note_69b28334529ca.pdf', 274559, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:15:31', 0, 0, 1, 0, '2026-03-12 14:41:16', '2026-03-14 22:05:32'),
(22, 'NT20260313070233699', 6, '50 Important English Questions for UP Board 10th Class with Answers', 'Notes for This comprehensive collection of 50 important English questions, with detailed answers, is designed for students preparing for the UP Board 10th Class exam. The questions cover a wide range of topics, including grammar, vocabulary, literature, writing skills, comprehension, and more. Each question is followed by an answer to help students understand key concepts, improve their language skills, and ace the exam. Perfect for quick revision and self-assessment!', 'english', 'class 10', 'question_bank', 'tutor', 'admin/user_pages/uploads/notes/note_69b28425b2834.pdf', 94021, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:15:31', 0, 0, 3, 4, '2026-03-12 14:45:17', '2026-03-13 21:38:17'),
(23, 'NT20260313070241482', 6, 'Artificial Intelligence: Techniques, Applications, and Key Concepts interview questions and answers with numericals', 'cheatsheet notes pdf Artificial Intelligence (AI) with this comprehensive guide. Learn about AI techniques, machine learning, natural language processing, computer vision, and intelligent agents. Discover the history, applications, and challenges of AI, along with practical examples and solutions. Perfect for beginners and experts alike, this guide provides in-depth knowledge on AI and its impact on various industries', 'computer science', 'AI', 'class notes', 'tutor', 'admin/user_pages/uploads/notes/note_69b2885e91a24.pdf', 677565, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:36:16', 0, 0, 1, 0, '2026-03-12 15:03:18', '2026-03-14 21:21:29'),
(24, 'NT20260313070248742', 6, 'C++ Handwritten Notes PDF (OOPs Part 1) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More', 'Download free handwritten C++ notes PDF (OOPs Concept Part 1) covering the full syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI, DTU, Amity, RGIPT, MMMUT, Invertis, CSJMU & more. Ideal for B.Tech & CS students!', 'computer science', 'C++', 'hand written', 'tutor', 'admin/user_pages/uploads/notes/note_69b2889fdea6d.pdf', 9306787, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:36:16', 0, 0, 3, 0, '2026-03-12 15:04:23', '2026-03-16 22:50:37'),
(25, 'NT20260313070257523', 6, 'C++ Handwritten Notes PDF (OOPs Part 2) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More', 'Download free handwritten C++ notes PDF (OOPs Concept Part 2) covering the full syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI, DTU, Amity, RGIPT, MMMUT, Invertis, CSJMU & more. Perfect for B.Tech & CS students!', 'computer science', 'C++', 'hand written', 'tutor', 'admin/user_pages/uploads/notes/note_69b288d25d380.pdf', 2425802, 'application/pdf', 'note', 'approved', 1, '2026-03-12 10:36:16', 1, 0, 11, 0, '2026-03-12 15:05:14', '2026-03-16 22:38:56'),
(26, 'NT20260313070305255', 4, 'OSI model of operating system and design', 'Detailed notes for OSI model of operating system and design free notes for B.Tech students from AKTU, MJPRU, KNIT, and LIT and RRSIMT Amethi (B.Tech).', 'computer science', 'B.tech ,MCA and Gate exams', 'hand written', 'tutor', 'admin/user_pages/uploads/notes/note_69b2e3fc27d40.pdf', 5801806, 'application/pdf', 'note', 'approved', 1, '2026-03-12 17:04:25', 1, 0, 37, 0, '2026-03-12 21:34:12', '2026-03-17 12:13:39'),
(27, 'NT20260316102415510', 6, 'Digital Marketing class leacture notes for AKTU and MJPRU : Strategies, SEO, Social Media, and More', 'Digital Marketing notes pdf with a comprehensive guide covering key strategies, SEO techniques, social media marketing, email marketing, mobile marketing, and more. Learn how to grow your brand, reach target audiences, and optimize online presence for success in the digital landscape', 'business', 'Digital Marketing', 'class notes', 'tutor', 'admin/user_pages/uploads/notes/note_69b538581563e.pdf', 316602, 'application/pdf', 'note', 'approved', 1, '2026-03-14 11:29:20', 0, 0, 0, 0, '2026-03-14 15:58:40', '2026-03-16 14:55:11'),
(28, 'NT20260316102415501', 6, '150+ Essential Basic Electrical Engineering Viva Questions and Answers', 'PDF notes of electrical engineering with 150+ comprehensive questions with detailed answers. Covers fundamental concepts, practical applications, and critical topics like transformers, circuits, motors, power systems, and more. Ideal for B.Tech students and interview preparations.', 'Viva Questions and Answers', 'Electrical Engineering', 'question_bank', 'tutor', 'admin/user_pages/uploads/notes/note_69b5918033b32.pdf', 216009, 'application/pdf', 'note', 'approved', 3, '2026-03-15 08:04:52', 0, 0, 1, 0, '2026-03-14 22:19:04', '2026-03-17 12:08:12'),
(30, 'NT20260316102415503', 3, 'Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams', 'Download comprehensive English notes covering grammar, vocabulary, writing skills, literature summaries, and comprehension techniques. Includes essential topics like tenses, active-passive voice, sentence structure, essay writing, letter writing, and literary analysis. Suitable for students of CBSE, ICSE, State Boards, and competitive exams like SSC, UPSC, and bank exams. Perfect for improving language skills and exam preparation.', 'english', 'CBSE, ICSE, Competitive Exams', 'hand written', 'student', 'admin/user_pages/uploads/notes/note_69b7cc3f2cfd6.pdf', 5490267, 'application/pdf', 'note', 'approved', 1, '2026-03-16 10:25:24', 0, 1, 9, 4, '2026-03-16 14:54:15', '2026-03-17 13:06:54'),
(31, 'NT20260316174921588', 3, 'Blockchain Architecture Design class lecture notes for AKTU and MJPRU : Key Concepts, Protocols, Consensus & Use Cases', 'fundamentals of Blockchain Architecture Design notes pdf , including key concepts like cryptographic primitives, consensus mechanisms, and permissioned blockchains. Explore use cases in financial software, trade, government, and more. Understand how Blockchain technology is reshaping industries with secure, decentralized systems.', 'computer science', 'Blockchain Architecture', 'class notes', 'student', 'admin/user_pages/uploads/notes/note_69b8349132a35.pdf', 318448, 'application/pdf', 'note', 'approved', 1, '2026-03-16 18:07:50', 0, 0, 11, 5, '2026-03-16 22:19:21', '2026-03-17 14:22:40');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) DEFAULT 0,
  `type` enum('warning','admin_message','upload_approved','upload_rejected','new_upload','banned_content') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `resource_type` enum('note','book','newspaper') DEFAULT NULL,
  `resource_id` varchar(50) DEFAULT NULL,
  `resource_title` varchar(255) DEFAULT NULL,
  `from_user_id` int(10) UNSIGNED DEFAULT NULL,
  `from_name` varchar(150) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `admin_id`, `type`, `title`, `message`, `resource_type`, `resource_id`, `resource_title`, `from_user_id`, `from_name`, `is_read`, `read_at`, `created_at`) VALUES
(75, 3, 0, 'admin_message', 'Your Report Has Been Received', 'Thank you for helping keep ScholarSwap safe and high-quality. Your report has been received and is being reviewed by our moderation team. We take all reports seriously and will take appropriate action if a violation is confirmed. We appreciate your contribution to the community.', 'note', 'NT20260313070305255', 'OSI model of operating system and design', NULL, 'DIVAKAR RAJPUT', 1, '2026-03-16 13:43:29', '2026-03-16 13:43:19'),
(76, 3, 0, 'admin_message', 'Action Taken on Your Report', 'We wanted to let you know that action has been taken on the content you reported. Our moderation team reviewed the report and found it valid. The appropriate measures have been applied. Thank you for helping us maintain a safe and quality learning environment on ScholarSwap.', 'note', 'NT20260313070305255', 'OSI model of operating system and design', NULL, 'DIVAKAR RAJPUT', 1, '2026-03-16 13:52:24', '2026-03-16 13:52:12'),
(77, 3, 0, 'upload_approved', 'Your upload has been approved', 'Your note \"Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams\" is now live and visible to everyone.', 'note', '', 'Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams', NULL, NULL, 1, '2026-03-16 14:22:30', '2026-03-16 14:22:22'),
(78, 4, 0, 'new_upload', 'DIVAKAR SINGH uploaded a new note', '\"Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams\" is now available to read and download.', 'note', '', 'Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams', 3, 'DIVAKAR SINGH', 1, '2026-03-16 14:23:13', '2026-03-16 14:22:22'),
(79, 6, 0, 'admin_message', 'Your Note has been reported', 'Your note \"C++ Handwritten Notes PDF (OOPs Part 2) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More\" has been reported for: Spam. Our team will review it shortly.', 'note', 'NT20260313070257523', 'C++ Handwritten Notes PDF (OOPs Part 2) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More', NULL, NULL, 1, '2026-03-16 23:00:34', '2026-03-16 14:32:21'),
(80, 3, 0, 'admin_message', 'Your Report Has Been Received', 'Thank you for helping keep ScholarSwap safe and high-quality. Your report has been received and is being reviewed by our moderation team. We take all reports seriously and will take appropriate action if a violation is confirmed. We appreciate your contribution to the community.', 'note', 'NT20260313070257523', 'C++ Handwritten Notes PDF (OOPs Part 2) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More', NULL, 'DIVAKAR RAJPUT', 1, '2026-03-16 14:32:56', '2026-03-16 14:32:47'),
(81, 3, 0, 'admin_message', 'Action Taken on Your Report', 'We wanted to let you know that action has been taken on the content you reported. Our moderation team reviewed the report and found it valid. The appropriate measures have been applied. Thank you for helping us maintain a safe and quality learning environment on ScholarSwap.', 'note', 'NT20260313070257523', 'C++ Handwritten Notes PDF (OOPs Part 2) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More', NULL, 'DIVAKAR RAJPUT', 1, '2026-03-16 14:33:48', '2026-03-16 14:33:40'),
(82, 3, 0, 'upload_approved', 'Your upload has been approved', 'Your note \"Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams\" is now live and visible to everyone.', 'note', 'NT20260316102415503', 'Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams', NULL, NULL, 1, '2026-03-16 14:55:34', '2026-03-16 14:55:24'),
(83, 4, 0, 'new_upload', 'DIVAKAR SINGH uploaded a new note', '\"Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams\" is now available to read and download.', 'note', 'NT20260316102415503', 'Complete English Notes PDF |CBSE, ICSE, State Boards, and competitive Exams', 3, 'DIVAKAR SINGH', 0, NULL, '2026-03-16 14:55:24'),
(84, 3, 0, 'upload_approved', 'Your upload has been approved', 'Your note \"Blockchain Architecture Design class lecture notes for AKTU and MJPRU : Key Concepts, Protocols, Consensus & Use Cases\" is now live and visible to everyone.', 'note', 'NT20260316174921588', 'Blockchain Architecture Design class lecture notes for AKTU and MJPRU : Key Concepts, Protocols, Consensus & Use Cases', NULL, NULL, 1, '2026-03-16 22:46:02', '2026-03-16 22:37:50'),
(85, 4, 0, 'new_upload', 'DIVAKAR SINGH uploaded a new note', '\"Blockchain Architecture Design class lecture notes for AKTU and MJPRU : Key Concepts, Protocols, Consensus & Use Cases\" is now available to read and download.', 'note', 'NT20260316174921588', 'Blockchain Architecture Design class lecture notes for AKTU and MJPRU : Key Concepts, Protocols, Consensus & Use Cases', 3, 'DIVAKAR SINGH', 0, NULL, '2026-03-16 22:37:50'),
(86, 6, 0, 'admin_message', '🌟 Your note has been featured!', 'Congratulations! Your note \"C++ Handwritten Notes PDF (OOPs Part 2) | Full Syllabus for AKTU, MJPRU, Jadavpur, SRM, JMI & More\" has been selected as a featured resource and is now highlighted for all users on ScholarSwap.', NULL, NULL, NULL, 1, 'DIVAKAR RAJPUT', 1, '2026-03-16 23:00:28', '2026-03-16 22:38:56'),
(87, 3, 0, 'upload_approved', 'Your upload has been approved', 'Your book \"Computer Organization and Architecture\" is now live and visible to everyone.', 'book', 'BK20260316175251121', 'Computer Organization and Architecture', NULL, NULL, 1, '2026-03-16 22:45:55', '2026-03-16 22:43:58'),
(88, 4, 0, 'new_upload', 'DIVAKAR SINGH uploaded a new book', '\"Computer Organization and Architecture\" is now available to read and download.', 'book', 'BK20260316175251121', 'Computer Organization and Architecture', 3, 'DIVAKAR SINGH', 0, NULL, '2026-03-16 22:43:58'),
(89, 3, 0, 'admin_message', 'bhojraj chauhan started following you', 'bhojraj chauhan is now following your uploads and activity.', NULL, NULL, NULL, 6, 'bhojraj chauhan', 1, '2026-03-16 23:31:37', '2026-03-16 23:31:29'),
(90, 3, 0, 'admin_message', 'bhojraj chauhan started following you', 'bhojraj chauhan is now following your uploads and activity.', NULL, NULL, NULL, 6, 'bhojraj chauhan', 1, '2026-03-16 23:32:13', '2026-03-16 23:32:04'),
(91, 3, 0, 'admin_message', 'bhojraj chauhan started following you', 'bhojraj chauhan is now following your uploads and activity.', NULL, NULL, NULL, 6, 'bhojraj chauhan', 1, '2026-03-17 11:40:46', '2026-03-17 11:03:38'),
(92, 6, 0, 'admin_message', 'DIVAKAR SINGH started following you', 'DIVAKAR SINGH is now following your uploads and activity.', NULL, NULL, NULL, 3, 'DIVAKAR SINGH', 1, '2026-03-17 12:23:19', '2026-03-17 12:08:38'),
(93, 6, 0, 'admin_message', 'DIVAKAR SINGH started following you', 'DIVAKAR SINGH is now following your uploads and activity.', NULL, NULL, NULL, 3, 'DIVAKAR SINGH', 1, '2026-03-17 12:23:15', '2026-03-17 12:08:44');

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `rating_id` int(11) NOT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `document_type` varchar(20) NOT NULL DEFAULT 'note',
  `user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`rating_id`, `resource_id`, `document_type`, `user_id`, `rating`, `created_at`) VALUES
(58, 'BK20260313061843490', 'book', 4, 4, '2026-03-13 15:34:19'),
(59, 'NP202603161030416900', 'newspaper', 3, 5, '2026-03-16 09:33:29'),
(60, 'NT20260316102415503', 'note', 3, 4, '2026-03-16 16:26:45'),
(61, 'NT20260316174921588', 'note', 3, 4, '2026-03-16 17:16:27'),
(62, 'BK20260316175251121', 'book', 6, 5, '2026-03-17 05:34:40'),
(63, 'NT20260316102415503', 'note', 6, 4, '2026-03-17 07:37:12'),
(64, 'NP202603161030416900', 'newspaper', 6, 4, '2026-03-17 07:38:21'),
(65, 'NT20260316174921588', 'note', 6, 5, '2026-03-17 08:48:02'),
(66, 'NP202603141115237910', 'newspaper', 6, 4, '2026-03-17 16:33:12');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(10) UNSIGNED NOT NULL,
  `reporter_id` int(10) UNSIGNED NOT NULL,
  `resource_id` varchar(255) NOT NULL,
  `document_type` enum('note','book','newspaper') NOT NULL,
  `reason` enum('spam','inappropriate_content','copyright_violation','misleading_information','wrong_category','other') NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','reviewed','dismissed','actioned') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `reporter_id`, `resource_id`, `document_type`, `reason`, `details`, `status`, `created_at`, `reviewed_at`, `reviewed_by`) VALUES
(11, 3, 'NP202603161030416900', 'newspaper', 'copyright_violation', 'copyright remove this paper', 'dismissed', '2026-03-16 15:02:52', '2026-03-16 15:06:20', 1),
(12, 3, 'BK20260313061843490', 'book', 'spam', 'spam', 'dismissed', '2026-03-16 15:03:13', '2026-03-16 15:06:11', 1);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `state` varchar(50) NOT NULL,
  `district` varchar(50) NOT NULL,
  `course` varchar(100) NOT NULL,
  `institution` varchar(150) NOT NULL,
  `subjects_of_interest` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `current_address` text NOT NULL,
  `permanent_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `last_name`, `dob`, `gender`, `state`, `district`, `course`, `institution`, `subjects_of_interest`, `bio`, `current_address`, `permanent_address`, `created_at`) VALUES
(1, 3, 'DIVAKAR', 'SINGH', '2006-01-04', 'male', 'Uttar Pradesh', 'aligarh', 'BCA 2 Year', 'RAJA MAHENDRA PRATAP SINGH UNIVERSITY, ALIGARH', 'MATH, ENGLISH, COMPUTER SECIENCE\'\'\'', 'I AM A FULL STACK WEB DEVELOPER', 'JARTAULI ALIGARH UTTAR PARDESH INDIA 202137', 'JARTAULI ALIGARH UTTAR PARDESH INDIA 202137', '2026-03-02 04:26:08');

-- --------------------------------------------------------

--
-- Table structure for table `tutors`
--

CREATE TABLE `tutors` (
  `tutor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `state` varchar(50) NOT NULL,
  `district` varchar(50) NOT NULL,
  `qualification` varchar(150) NOT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `subjects_taught` text NOT NULL,
  `experience_years` int(11) DEFAULT 0,
  `bio` text DEFAULT NULL,
  `current_address` text NOT NULL,
  `permanent_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tutors`
--

INSERT INTO `tutors` (`tutor_id`, `user_id`, `first_name`, `last_name`, `dob`, `gender`, `state`, `district`, `qualification`, `institution`, `subjects_taught`, `experience_years`, `bio`, `current_address`, `permanent_address`, `created_at`) VALUES
(1, 4, 'kumkum', 'chauhan', '2005-01-05', 'female', 'Andhra Pradesh', 'agra', 'B.TECH 4th Year', 'GALGOTIYA UNIVERSITY NOIDA, UTTAR PRADESH', 'MATH, JAVA, C, C++, PHP\'\'\'\'', 0, 'I AM A FULL STACK APP & WEB DEVELOPER', 'Maa Jagdamba Usha Devi Mandir Jartauli', 'Maa Jagdamba Usha Devi Mandir Jartauli', '2026-03-02 04:29:51'),
(3, 6, 'bhojraj', 'chauhan', '2004-01-04', 'male', 'Uttar Pradesh', 'aligarh', 'B.TECH 4th Year', 'GALGOTIYA UNIVERSITY NOIDA, UTTAR PRADESH', 'MATH, ENGLISH, COMPUTER SECINCR', 2, 'jhj', 'JARTAULI ALIGAH UTTAR PRADESH 202137', 'JARTAULI ALIGAH UTTAR PRADESH 202137', '2026-03-11 03:22:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('student','tutor') NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `phone`, `password_hash`, `profile_image`, `role`, `is_verified`, `is_active`, `created_at`, `updated_at`, `last_login`) VALUES
(3, 'divakar9758', 'divakarchauhan9758@gmail.com', '9758296457', '$2y$10$a7OKHAriewhbU7.Fmi0XnOEy1BxU9xUSGfDfK1nyebIuBDjFARsSS', 'admin/user_pages/uploads/profile_images/profile_3_1773077560.jpg', 'student', 1, 1, '2026-03-02 04:26:08', '2026-03-09 17:32:40', NULL),
(4, 'kumkum9758', 'kumkumrajput9758@gmail.com', '9758296457', '$2y$10$OnAcfTucFEhbJLtuWIS8ie5.d59nTmj4FfOQbQtO.mzQn13eAANXS', 'admin/user_pages/uploads/profile_images/profile_4_1773079378.jpg', 'tutor', 1, 1, '2026-03-02 04:29:51', '2026-03-09 18:02:58', NULL),
(6, 'bhojrajsingh9758', 'bhojrajsingh9758@gmail.com', '7668381816', '$2y$10$Z1OxKhJXrO62tr0EIy4N/OVZyA62l4bMUA./3Le74nL0jijX7uX5a', NULL, 'tutor', 1, 1, '2026-03-11 03:22:05', '2026-03-15 08:20:52', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_login_activity`
--
ALTER TABLE `admin_login_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_role`
--
ALTER TABLE `admin_role`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_role_name` (`role_name`);

--
-- Indexes for table `admin_user`
--
ALTER TABLE `admin_user`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`bookmark_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`),
  ADD UNIQUE KEY `b_code` (`b_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `idx_resource` (`resource_id`,`document_type`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `downloads`
--
ALTER TABLE `downloads`
  ADD PRIMARY KEY (`download_id`);

--
-- Indexes for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  ADD PRIMARY KEY (`key_id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `follows`
--
ALTER TABLE `follows`
  ADD PRIMARY KEY (`follow_id`),
  ADD UNIQUE KEY `follower_id` (`follower_id`,`following_id`),
  ADD KEY `fk_following` (`following_id`);

--
-- Indexes for table `newspapers`
--
ALTER TABLE `newspapers`
  ADD PRIMARY KEY (`newspaper_id`),
  ADD UNIQUE KEY `n_code` (`n_code`),
  ADD KEY `fk_newspaper_admin` (`admin_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`note_id`),
  ADD UNIQUE KEY `n_code` (`n_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD UNIQUE KEY `idx_ratings_user_res` (`user_id`,`resource_id`,`document_type`),
  ADD KEY `idx_ratings_res` (`resource_id`,`document_type`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `uq_user_resource` (`reporter_id`,`resource_id`,`document_type`),
  ADD KEY `idx_resource` (`resource_id`,`document_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_reporter` (`reporter_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tutors`
--
ALTER TABLE `tutors`
  ADD PRIMARY KEY (`tutor_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_login_activity`
--
ALTER TABLE `admin_login_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `admin_role`
--
ALTER TABLE `admin_role`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_user`
--
ALTER TABLE `admin_user`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `bookmark_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `downloads`
--
ALTER TABLE `downloads`
  MODIFY `download_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  MODIFY `key_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `follows`
--
ALTER TABLE `follows`
  MODIFY `follow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `newspapers`
--
ALTER TABLE `newspapers`
  MODIFY `newspaper_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tutors`
--
ALTER TABLE `tutors`
  MODIFY `tutor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_login_activity`
--
ALTER TABLE `admin_login_activity`
  ADD CONSTRAINT `admin_login_activity_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_user` (`admin_id`);

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `follows`
--
ALTER TABLE `follows`
  ADD CONSTRAINT `fk_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `newspapers`
--
ALTER TABLE `newspapers`
  ADD CONSTRAINT `fk_newspaper_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_user` (`admin_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tutors`
--
ALTER TABLE `tutors`
  ADD CONSTRAINT `tutors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
