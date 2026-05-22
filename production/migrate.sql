-- ============================================================
-- Clean-slate migration for ai_auth
-- Run this in phpMyAdmin (select ai_auth DB first)
-- or via: mysql -u root -p ai_auth < migrate.sql
-- WARNING: drops all existing data
-- ============================================================

-- USE `ai_auth`;

-- --------------------------------------------------------
-- Drop everything cleanly (order matters for FK)
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `refresh_tokens`;
DROP TABLE IF EXISTS `license_activity`;
DROP TABLE IF EXISTS `licenses`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Create tables
-- --------------------------------------------------------

CREATE TABLE `licenses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `type` enum('standard','trial') DEFAULT 'standard',
  `status` enum('active','expired','revoked') DEFAULT 'active',
  `assigned_to` varchar(190) DEFAULT NULL,
  `assigned_name` varchar(190) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `license_activity` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `by_user` varchar(190) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `reset_token_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refresh_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','disabled') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `refresh_tokens`
  ADD CONSTRAINT `rt_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Migrate the 9 live users from nupthasa_ai_api
-- avatar is NULL for now (new field, no prior data)
-- --------------------------------------------------------

-- Option A: Cross-DB INSERT (use if both DBs are on the same server)
-- INSERT INTO `ai_auth`.`users`
--   (id, email, password_hash, name, avatar, role, status, created_at, updated_at, last_login)
-- SELECT id, email, password_hash, name, NULL, role, status, created_at, updated_at, last_login
-- FROM `nupthasa_ai_api`.`users`;

-- Option B: Direct INSERT (use on shared hosting where cross-DB access may be restricted)
-- These are the 9 real users from nupthasa_ai_api as of 2026-05-19
INSERT IGNORE INTO `users`
  (`id`, `email`, `password_hash`, `name`, `avatar`, `role`, `status`, `created_at`, `updated_at`, `last_login`)
VALUES
  (11, 'f.m.alharbiy@gmail.com',  '$2y$12$rpbCi2JNFfPNwN.hbeyxg.BdsmdlFR5F5ybXQ34zgoCPqOXEfCt3q', 'Admin',              NULL, 'admin', 'active', '2025-12-08 16:04:57', '2026-05-16 08:42:18', '2026-05-16 08:42:18'),
  (12, 'faesl67@hotmail.com',     '$2y$12$SSiOTCG9viUVos7DdtFFSeIcm7LvYc5WKmx7HU3gmV/gOjTW6urt6', 'Faisal Alharbi',     NULL, 'user',  'active', '2025-12-08 16:08:04', '2026-01-01 15:35:37', '2026-01-01 15:35:37'),
  (13, 's.salfaraj@outlook.com',  '$2y$12$EVivCp.s34z743FPvCp1HugcKMK6gaZH.H7KKSUZsgK6KmiZMeaz6', 'Sultan Alfaraj',     NULL, 'user',  'active', '2025-12-08 16:08:59', '2026-04-14 06:20:07', '2026-04-14 06:20:07'),
  (15, 'test@test.com',           '$2y$12$9SfBZlKhIoBm4Qe8JW2IQe7Bos4FwOvmXuEX/98Cvu4RGVx7pQgam', 'Test User',          NULL, 'user',  'active', '2025-12-25 18:34:43', '2026-01-08 16:52:23', '2026-01-08 16:52:23'),
  (17, 'eng.mohd.a@gmail.com',    '$2y$12$sNmvvpZG3bMQQv30srHG8uVhLJ/0Pn/3s2GKJU89V7bdwFw5r9G6C', 'Mohammed Alasmari',  NULL, 'user',  'active', '2026-01-01 16:35:26', '2026-01-01 16:35:26', NULL),
  (18, 'Cubetechn@outlook.com',   '$2y$12$cq5QmbMdqKPVhuSFJ8pnpOxGkgJfm5nJ9DS3c6huksFvTZSBWyu9u', 'Cube Tech',          NULL, 'admin', 'active', '2026-01-31 14:32:20', '2026-02-24 18:19:43', '2026-02-24 18:19:43'),
  (19, 'Laalmadi@pnu.edu.sa',     '$2y$12$YBe5BsysQsA4mSWVivwig.nB4iSTQcDTHIov.FG3UxX1aYCocz6eG', 'Layla AL-Madi',      NULL, 'user',  'active', '2026-02-01 15:42:39', '2026-02-05 08:25:23', '2026-02-05 08:25:23'),
  (20, 'aalmusamih@gmail.com',    '$2y$12$y6570cTvT0yVHLQwAZd8oOi2pgwhRsIN.e2yAl1S1UkH362k46Eqa', 'Abdulrahman fahad',  NULL, 'user',  'active', '2026-02-10 16:49:21', '2026-02-10 16:49:21', NULL),
  (21, 'eng.ma.426@gmail.com',    '$2y$12$o759mKxheyiuZYzM0VZ3penpxCdUD65ysr6C8BlDx9Zo3pqWvFM5q', 'Mana  alahmari',     NULL, 'user',  'active', '2026-03-15 20:47:27', '2026-03-15 20:47:27', NULL);

-- Set AUTO_INCREMENT above the highest migrated user ID (21)
ALTER TABLE `users` AUTO_INCREMENT = 22;

-- --------------------------------------------------------
-- Verify
-- --------------------------------------------------------
SELECT id, email, name, role, status FROM `users` ORDER BY id;
-- Expected: 9 rows with IDs 11,12,13,15,17,18,19,20,21
