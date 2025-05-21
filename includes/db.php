<?php
// Database connection settings
$host = 'localhost';
$dbname = 'auth_system';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create PDO connection
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // If connection fails, create database and tables
    try {
        $pdo = new PDO("mysql:host=$host", $username, $password, $options);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");

        // Create users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(100) DEFAULT NULL,
            `email` varchar(150) DEFAULT NULL,
            `auth0_id` varchar(255) DEFAULT NULL,
            `auth_method` varchar(50) DEFAULT NULL,
            `avatar_url` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `is_admin` tinyint(1) DEFAULT 0,
            `2fa_required_by` datetime DEFAULT NULL COMMENT 'Deadline for enabling 2FA after registration',
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
            `extra_profile_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_profile_data`)),
            `plan` varchar(50) DEFAULT 'free',
            `used_space` bigint(20) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `auth0_id` (`auth0_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create user_files table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `filename` varchar(255) NOT NULL,
            `original_name` varchar(255) NOT NULL,
            `filesize` bigint(20) NOT NULL,
            `filetype` varchar(100) NOT NULL,
            `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `download_count` int(11) DEFAULT 0,
            `last_download` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `user_files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create encrypted_files table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `encrypted_files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `file_id` int(11) NOT NULL,
            `file_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of the original file',
            `digital_signature` text NOT NULL COMMENT 'Digital signature of the hash',
            `signature_algorithm` varchar(50) NOT NULL DEFAULT 'SHA256',
            `encryption_algorithm` varchar(50) NOT NULL DEFAULT 'AES-256-CBC',
            `iv` varchar(64) NOT NULL COMMENT 'Initialization vector for AES encryption',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `last_verified` timestamp NULL DEFAULT NULL,
            `verification_status` enum('verified','failed') NOT NULL DEFAULT 'verified',
            PRIMARY KEY (`id`),
            UNIQUE KEY `file_id` (`file_id`),
            CONSTRAINT `encrypted_files_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `user_files` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create file_logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `file_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `action` varchar(50) NOT NULL,
            `filename` varchar(255) NOT NULL,
            `timestamp` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `file_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create login_logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `login_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `login_time` datetime DEFAULT current_timestamp(),
            `status` varchar(20) DEFAULT 'success',
            `auth_method` varchar(50) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create failed_logins table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `failed_logins` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ip_address` varchar(45) NOT NULL,
            `attempt_time` datetime DEFAULT current_timestamp(),
            `username` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ip_address` (`ip_address`),
            KEY `attempt_time` (`attempt_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create two_factor_auth table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `two_factor_auth` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `secret_key` varchar(32) NOT NULL,
            `is_enabled` tinyint(1) DEFAULT 0,
            `created_at` datetime DEFAULT current_timestamp(),
            `last_used` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_id` (`user_id`),
            CONSTRAINT `two_factor_auth_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Rest of the file remains unchanged