-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 21, 2025 at 04:00 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `auth_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `encrypted_files`
--

CREATE TABLE `encrypted_files` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `file_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of the original file',
  `digital_signature` text NOT NULL COMMENT 'Digital signature of the hash',
  `signature_algorithm` varchar(50) NOT NULL DEFAULT 'SHA256withRSA',
  `encryption_algorithm` varchar(50) NOT NULL DEFAULT 'AES-256-CBC',
  `iv` varchar(32) NOT NULL COMMENT 'Initialization vector for AES encryption',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_verified` timestamp NULL DEFAULT NULL,
  `verification_status` tinyint(1) DEFAULT 1 COMMENT '1=verified, 0=failed',
  `hmac` varchar(64) NOT NULL COMMENT 'HMAC-SHA256 of the encrypted file',
  `crc32` varchar(8) NOT NULL COMMENT 'CRC32 checksum of the original file',
  `integrity_status` enum('verified','hmac_failed','crc_failed','both_failed') NOT NULL DEFAULT 'verified'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encrypted_files`
--

INSERT INTO `encrypted_files` (`id`, `file_id`, `file_hash`, `digital_signature`, `signature_algorithm`, `encryption_algorithm`, `iv`, `created_at`, `last_verified`, `verification_status`, `hmac`, `crc32`, `integrity_status`) VALUES
(5, 32, 'd2c9b0ff60f8801ee9bb34ee7cd13c978d1003e1ad4f5a2b877801faf2d05cf5', 'A/aYBtmp/syByMgMEAWwtrIMtIE5Mi3s785ZMnFD2zOVzCOcoOaq3PBxVBhLqelBAuRu7bOoNgYZCH6XRQwxs00AKcKItGNwetgNOR/h8iAJd7ZPOaoVsmsufS1GbSiabBV6ArZvOPZO1Rcxm032EAQpD4WpKhSwiQJpghcs+PA6FNmmjP1Mvv4MLdVS9Y0bS+rqkyROdZVVGXYI9USbKiSs1ZlnsjEB8whMc4S0dz6n+kp7FhLFE20tuM6aXUEzOn1tl7SsrkWW/PCJxqXcPCLMfk+0ys1X0gjYXgoQ6KQUOerCsRPruQ6oEXpLGCW8N1fh1c6wyARfcZ7gM+GaRg==', 'SHA256withRSA', 'AES-256-CBC', '1dbe151031e83bd54313fffa22c9d467', '2025-05-21 13:29:34', NULL, 1, '5fbcadb931770c99268420e48af9b3eb5e1846d8264f11be8f9b000f996f8d30', '9aaf9445', 'verified');

-- --------------------------------------------------------

--
-- Table structure for table `failed_logins`
--

CREATE TABLE `failed_logins` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `username` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_logs`
--

CREATE TABLE `file_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `file_logs`
--

INSERT INTO `file_logs` (`id`, `user_id`, `action`, `filename`, `timestamp`) VALUES
(4, 1, 'upload', 'Data Integrity and Authentication Final Project - Spring 2024-2025.pdf', '2025-05-16 19:51:43'),
(5, 1, 'upload', 'Data Integrity and Authentication Final Project - Spring 2024-2025.pdf', '2025-05-16 19:53:09'),
(6, 1, 'upload', '1_1747414439_Data_Integrity_and_Authentication_Final_Project_-_Spring_2024-2025.pdf.enc', '2025-05-16 19:53:59'),
(7, 1, 'upload', '1_1747414456_Data_Integrity_and_Authentication_Final_Project_-_Spring_2024-2025.pdf.enc', '2025-05-16 19:54:16'),
(8, 5, 'rename', 'Gym report.pdf', '2025-05-21 01:32:52'),
(9, 5, 'rename', 'dadwa.png', '2025-05-21 01:34:56'),
(10, 5, 'rename', 'Sheet OS22.pdf', '2025-05-21 01:35:03'),
(11, 5, 'rename', 'Image222.jpg', '2025-05-21 01:35:26'),
(12, 5, 'upload', '682dd53e78acc_Cybersecurity Concept With A Hacker In A Hood Using A Laptop And The Text On A Dark Blue Background Wallpaper Image For Free Download - Pngtree.jpg', '2025-05-21 16:29:34'),
(13, 5, 'rename', 'Cybersecurity  Image .jpg', '2025-05-21 16:30:58');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `login_time` datetime DEFAULT NULL,
  `login_method` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `ip_address`, `login_time`, `login_method`) VALUES
(1, 1, 'localhost', '2025-05-16 18:44:16', ''),
(2, 1, 'localhost', '2025-05-16 18:46:15', ''),
(3, 1, 'localhost', '2025-05-16 18:54:49', '');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `storage_limit` bigint(20) NOT NULL COMMENT 'Storage limit in bytes',
  `max_file_size` bigint(20) NOT NULL COMMENT 'Maximum file size in bytes',
  `price` decimal(10,2) DEFAULT 0.00,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `storage_limit`, `max_file_size`, `price`, `description`) VALUES
(1, 'free', 1073741824, 10485760, 0.00, 'Free plan with 1GB storage and 10MB file size limit'),
(2, 'premium', 5368709120, 52428800, 9.99, 'Premium plan with 5GB storage and 50MB file size limit'),
(3, 'enterprise', 10737418240, 104857600, 29.99, 'Enterprise plan with 10GB storage and 100MB file size limit');

-- --------------------------------------------------------

--
-- Table structure for table `two_factor_auth`
--

CREATE TABLE `two_factor_auth` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `secret_key` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `two_factor_auth`
--

INSERT INTO `two_factor_auth` (`id`, `user_id`, `secret_key`, `is_enabled`, `created_at`, `last_used`) VALUES
(1, 1, 'B4GC3BIEIZXAFZUK', 1, '2025-05-19 15:33:18', '2025-05-19 15:54:51'),
(2, 1, 'PROML4UHYKCZLETQ', 1, '2025-05-19 15:41:27', '2025-05-19 15:54:51'),
(3, 1, 'YZMSK7ISY33CMIWQ', 1, '2025-05-19 15:54:40', '2025-05-19 15:54:51'),
(4, 3, 'SBWXRK5ZQQMOE2ZA', 1, '2025-05-19 17:46:11', '2025-05-19 17:46:36'),
(5, 4, 'HZNJHAVO5MY5R5MD', 1, '2025-05-19 18:43:14', '2025-05-19 18:43:56'),
(6, 5, 'CVVQVPKGKYGDDYMS', 1, '2025-05-19 21:05:23', '2025-05-21 13:34:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `auth0_id` varchar(255) DEFAULT NULL,
  `auth_method` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0,
  `birthday` varchar(20) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `github_username` varchar(255) DEFAULT NULL,
  `github_company` varchar(255) DEFAULT NULL,
  `github_location` varchar(255) DEFAULT NULL,
  `github_blog` varchar(255) DEFAULT NULL,
  `github_bio` text DEFAULT NULL,
  `facebook_permissions` text DEFAULT NULL,
  `github_permissions` text DEFAULT NULL,
  `extra_profile_data` longtext DEFAULT NULL CHECK (json_valid(`extra_profile_data`)),
  `plan` varchar(50) DEFAULT 'free',
  `storage_limit` int(11) NOT NULL DEFAULT 500000,
  `used_space` bigint(20) DEFAULT 0,
  `password_hash` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `2fa_required_by` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `auth0_id`, `auth_method`, `avatar_url`, `created_at`, `is_admin`, `birthday`, `gender`, `location`, `github_username`, `github_company`, `github_location`, `github_blog`, `github_bio`, `facebook_permissions`, `github_permissions`, `extra_profile_data`, `plan`, `storage_limit`, `used_space`, `password_hash`, `is_active`, `2fa_required_by`) VALUES
(1, 'mohamed', 'mohamed2291971@gmail.com', 'google-oauth2|109259058695854724825', 'auth0', 'https://lh3.googleusercontent.com/a/ACg8ocI7s81VnfX_6qnnrp96rXRub20gYBf7bQXB4ILhZMpdX0PrE_U=s96-c', '2025-05-16 15:44:16', 1, '2025-05-19', 'male', '45', NULL, NULL, NULL, NULL, NULL, 'email', '', '{\"facebook_data\":null,\"github_data\":null,\"raw_userinfo\":{\"sub\":\"google-oauth2|109259058695854724825\",\"given_name\":\"CYBERSEC\",\"family_name\":\"TUTORIALS\",\"nickname\":\"mohamed2291971\",\"name\":\"CYBERSEC TUTORIALS\",\"picture\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocI7s81VnfX_6qnnrp96rXRub20gYBf7bQXB4ILhZMpdX0PrE_U=s96-c\",\"updated_at\":\"2025-05-18T19:09:01.006Z\",\"email\":\"mohamed2291971@gmail.com\",\"email_verified\":true}}', 'enterprise', 5000, 170282, NULL, 1, NULL),
(2, 'abdoh00', 'abdoh2291971@gmail.com', 'google-oauth2|103362928466863176323', 'auth0', 'https://lh3.googleusercontent.com/a/ACg8ocJ90hlsiZjg1T9Yi8Mp0sUn62yYgWIMiII8tQttk670kCqGxXWA=s96-c', '2025-05-16 19:26:27', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'email', '', '{\"facebook_data\":null,\"github_data\":null,\"raw_userinfo\":{\"sub\":\"google-oauth2|103362928466863176323\",\"given_name\":\"Ahmed\",\"family_name\":\"FZ\",\"nickname\":\"abdoh2291971\",\"name\":\"Ahmed FZ\",\"picture\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocJ90hlsiZjg1T9Yi8Mp0sUn62yYgWIMiII8tQttk670kCqGxXWA=s96-c\",\"updated_at\":\"2025-05-16T19:19:32.400Z\",\"email\":\"abdoh2291971@gmail.com\",\"email_verified\":true}}', 'free', 5000, 0, NULL, 1, NULL),
(3, 'me', 'me@gmail.com', NULL, 'manual', NULL, '2025-05-19 17:45:15', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'free', 5000, 0, '$2y$10$LXyNx4bE7tWoj7FuJ4uqv.6EIhfUBFkhn74hYKixzCKs11RU9XPyK', 1, '2025-05-22 19:45:15'),
(4, 'mo ramadan', 'dark90180@gmail.com', 'google-oauth2|111349204789801447710', 'auth0', 'https://lh3.googleusercontent.com/a/ACg8ocIKf667sILrZtFpNyfF8d16VeAj7xr_nTn_82ETUXQyPPjP-g=s96-c', '2025-05-19 18:43:01', 0, '', '', '', NULL, NULL, NULL, NULL, NULL, 'email', '', '{\"facebook_data\":null,\"github_data\":null,\"raw_userinfo\":{\"sub\":\"google-oauth2|111349204789801447710\",\"given_name\":\"dark\",\"family_name\":\"star\",\"nickname\":\"dark90180\",\"name\":\"dark star\",\"picture\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocIKf667sILrZtFpNyfF8d16VeAj7xr_nTn_82ETUXQyPPjP-g=s96-c\",\"updated_at\":\"2025-05-19T18:42:56.138Z\",\"email\":\"dark90180@gmail.com\",\"email_verified\":true}}', 'free', 5000, 234538, NULL, 1, NULL),
(5, '2205043', '2205043@anu.edu.eg', NULL, 'manual', 'uploads/avatars/5_1747689304.jpg', '2025-05-19 21:03:14', 1, '2004-07-03', 'male', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'enterprise', 2147483647, 646749, '$2y$10$TwWdwSpadG4id7w24F/ow.1PSik0Y6f0H6hRCSZQOoD0xYTX6sHjO', 1, '2025-05-22 23:03:14'),
(6, 'Mo3704', 'mo.ahmed3704@gmail.com', 'github|168588452', 'auth0', 'https://avatars.githubusercontent.com/u/168588452?v=4', '2025-05-19 23:26:17', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'email', '', '{\"facebook_data\":null,\"github_data\":null,\"raw_userinfo\":{\"sub\":\"github|168588452\",\"nickname\":\"Mo3704\",\"name\":\"Mohamed Ramadan\",\"picture\":\"https:\\/\\/avatars.githubusercontent.com\\/u\\/168588452?v=4\",\"updated_at\":\"2025-05-21T13:58:42.106Z\",\"email\":\"mo.ahmed3704@gmail.com\",\"email_verified\":true}}', 'free', 500000, 0, NULL, 1, NULL),
(7, 'Mohamed Ahmed', '', 'facebook|3845160739128882', 'auth0', 'https://platform-lookaside.fbsbx.com/platform/profilepic/?asid=3845160739128882&height=50&width=50&ext=1750177645&hash=AT_7IFxO4j7RT8K_zlOHlyel', '2025-05-20 22:12:39', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'email', '', '{\"facebook_data\":null,\"github_data\":null,\"raw_userinfo\":{\"sub\":\"facebook|3845160739128882\",\"given_name\":\"Mohamed\",\"family_name\":\"Ahmed\",\"nickname\":\"Mohamed Ahmed\",\"name\":\"Mohamed Ahmed\",\"picture\":\"https:\\/\\/platform-lookaside.fbsbx.com\\/platform\\/profilepic\\/?asid=3845160739128882&height=50&width=50&ext=1750177645&hash=AT_7IFxO4j7RT8K_zlOHlyel\",\"updated_at\":\"2025-05-18T16:27:25.667Z\",\"email_verified\":true}}', 'free', 500000, 0, NULL, 1, NULL),
(8, 'mo.ahmed3704', 'mo.ahmed3704@gmail.com', 'google-oauth2|104190985583962562042', 'auth0', 'https://lh3.googleusercontent.com/a/ACg8ocK3N5p1Ox1qn5-sz__h6uW-x6dDg4KVHKViIMqmIeBPUkxV3tQ=s96-c', '2025-05-20 22:39:37', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'email', '', '{\"facebook_data\":null,\"github_data\":null,\"raw_userinfo\":{\"sub\":\"google-oauth2|104190985583962562042\",\"given_name\":\"Mohamed\",\"family_name\":\"Ahmed\",\"nickname\":\"mo.ahmed3704\",\"name\":\"Mohamed Ahmed\",\"picture\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocK3N5p1Ox1qn5-sz__h6uW-x6dDg4KVHKViIMqmIeBPUkxV3tQ=s96-c\",\"updated_at\":\"2025-05-20T22:39:33.074Z\",\"email\":\"mo.ahmed3704@gmail.com\",\"email_verified\":true}}', 'free', 500000, 0, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_files`
--

CREATE TABLE `user_files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `filesize` bigint(20) NOT NULL,
  `filetype` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_download` timestamp NULL DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `sha256_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_files`
--

INSERT INTO `user_files` (`id`, `user_id`, `filename`, `original_name`, `filesize`, `filetype`, `uploaded_at`, `last_download`, `download_count`, `sha256_hash`) VALUES
(7, 2, '2_1747431871_ede862a4-02c0-4058-a8ad-8dfb49f74a0c.png.enc', 'dadwa.png', 628928, 'image/png', '2025-05-16 21:44:31', NULL, 0, NULL),
(26, 1, '682b5d99cd08c_Project for Cyber Security students (Info Mgmt Sec) (3).pdf', 'Project for Cyber Security students (Info Mgmt Sec) (3).pdf', 170282, 'application/octet-stream', '2025-05-19 16:34:33', NULL, 0, NULL),
(27, 4, '682b7c7b5d59a_Final sheet.pdf', 'Final sheet.pdf', 66890, 'application/pdf', '2025-05-19 18:46:19', NULL, 0, NULL),
(28, 4, '682b7cb571c40_Sheet OS.pdf', 'Sheet OS22.pdf', 75398, 'application/pdf', '2025-05-19 18:47:17', NULL, 0, NULL),
(29, 4, '682b7ccd14d32_game-preview.png', 'game-preview.png', 92250, 'image/png', '2025-05-19 18:47:41', NULL, 0, NULL),
(30, 5, '682ba3550eb7d_Image2.jpg', 'Image222.jpg', 384374, 'image/jpeg', '2025-05-19 21:32:05', NULL, 0, NULL),
(31, 5, '682ba7732dcf3_Gym Management System - Project Report (4).pdf', 'Gym report.pdf', 600273, 'application/pdf', '2025-05-19 21:49:39', NULL, 0, NULL),
(32, 5, '682dd53e78acc_Cybersecurity Concept With A Hacker In A Hood Using A Laptop And The Text On A Dark Blue Background Wallpaper Image For Free Download - Pngtree.jpg', 'Cybersecurity  Image .jpg', 46476, 'image/jpeg', '2025-05-21 13:29:34', NULL, 0, 'd2c9b0ff60f8801ee9bb34ee7cd13c978d1003e1ad4f5a2b877801faf2d05cf5');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `encrypted_files`
--
ALTER TABLE `encrypted_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indexes for table `failed_logins`
--
ALTER TABLE `failed_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `file_logs`
--
ALTER TABLE `file_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `two_factor_auth`
--
ALTER TABLE `two_factor_auth`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_files`
--
ALTER TABLE `user_files`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `encrypted_files`
--
ALTER TABLE `encrypted_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_logins`
--
ALTER TABLE `failed_logins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_logs`
--
ALTER TABLE `file_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `two_factor_auth`
--
ALTER TABLE `two_factor_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_files`
--
ALTER TABLE `user_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `encrypted_files`
--
ALTER TABLE `encrypted_files`
  ADD CONSTRAINT `encrypted_files_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `user_files` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `two_factor_auth`
--
ALTER TABLE `two_factor_auth`
  ADD CONSTRAINT `two_factor_auth_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
