-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2026 at 02:14 AM
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
-- Database: `nile_center`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `action`, `table_name`, `record_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES
(1, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-15 16:53:24'),
(2, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.222.248.213', '2026-06-15 17:10:34'),
(3, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.222.248.213', '2026-06-15 21:57:15'),
(4, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.222.248.213', '2026-06-15 21:58:18'),
(5, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '::1', '2026-06-15 22:03:35'),
(6, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.73.167.118', '2026-06-15 22:55:28'),
(7, 1, 'System Administrator', 'create', 'orders', 5, NULL, '{\"order_number\":\"20260001\"}', '26.201.9.238', '2026-06-16 01:09:16'),
(8, 1, 'System Administrator', 'create', 'orders', 6, NULL, '{\"order_number\":\"20260002\"}', '26.201.9.238', '2026-06-16 01:09:26'),
(9, 1, 'System Administrator', 'delete', 'orders', 6, NULL, NULL, '26.201.9.238', '2026-06-16 01:12:09'),
(10, 1, 'System Administrator', 'create', 'orders', 7, NULL, '{\"order_number\":\"20260002\"}', '26.201.9.238', '2026-06-16 01:12:20'),
(11, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-16 18:44:37'),
(12, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.73.167.118', '2026-06-16 18:48:45'),
(13, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.73.167.118', '2026-06-16 18:48:58'),
(14, 1, 'System Administrator', 'update_status', 'orders', 7, '{\"status_id\":1}', '{\"status_id\":\"2\"}', '26.73.167.118', '2026-06-16 18:49:45'),
(15, 1, 'System Administrator', 'update_status', 'orders', 5, '{\"status_id\":1}', '{\"status_id\":\"4\"}', '26.73.167.118', '2026-06-16 18:49:50'),
(16, 1, 'System Administrator', 'delete', 'branches', 2, NULL, NULL, '26.201.9.238', '2026-06-16 19:45:41'),
(17, 1, 'System Administrator', 'activate', 'branches', 2, NULL, NULL, '26.201.9.238', '2026-06-16 19:45:46'),
(18, 1, 'System Administrator', 'update', 'branches', 1, NULL, NULL, '26.201.9.238', '2026-06-16 19:46:35'),
(19, 1, 'System Administrator', 'update', 'branches', 2, NULL, NULL, '26.201.9.238', '2026-06-16 19:47:11'),
(20, 1, 'System Administrator', 'update', 'branches', 3, NULL, NULL, '26.201.9.238', '2026-06-16 19:47:38'),
(21, 1, 'System Administrator', 'create', 'branches', 4, NULL, NULL, '26.201.9.238', '2026-06-16 19:48:31'),
(22, 1, 'System Administrator', 'update', 'branches', 1, NULL, NULL, '26.201.9.238', '2026-06-16 19:49:00'),
(23, 1, 'System Administrator', 'update', 'branches', 4, NULL, NULL, '26.201.9.238', '2026-06-16 19:49:10'),
(24, 1, 'System Administrator', 'update', 'branches', 3, NULL, NULL, '26.201.9.238', '2026-06-16 19:49:17'),
(25, 1, 'System Administrator', 'create', 'orders', 8, NULL, '{\"order_number\":\"20260003\"}', '26.201.9.238', '2026-06-16 20:01:06'),
(26, 1, 'System Administrator', 'create', 'orders', 9, NULL, '{\"order_number\":\"20260004\"}', '26.201.9.238', '2026-06-16 20:07:13'),
(27, 1, 'System Administrator', 'create', 'orders', 10, NULL, '{\"order_number\":\"20260005\"}', '26.201.9.238', '2026-06-16 20:13:19'),
(28, 1, 'System Administrator', 'create', 'orders', 11, NULL, '{\"order_number\":\"20260006\"}', '26.201.9.238', '2026-06-16 20:14:30'),
(29, 1, 'System Administrator', 'create', 'orders', 12, NULL, '{\"order_number\":\"20260007\"}', '26.73.167.118', '2026-06-16 20:17:31'),
(30, 1, 'System Administrator', 'update_status', 'orders', 7, '{\"status_id\":2}', '{\"status_id\":\"3\"}', '26.73.167.118', '2026-06-16 20:19:40'),
(31, 1, 'System Administrator', 'create', 'orders', 16, NULL, '{\"order_number\":\"20260008\"}', '26.201.9.238', '2026-06-16 21:18:34'),
(32, 1, 'System Administrator', 'delete', 'orders', 16, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:23'),
(33, 1, 'System Administrator', 'delete', 'orders', 12, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:26'),
(34, 1, 'System Administrator', 'delete', 'orders', 11, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:29'),
(35, 1, 'System Administrator', 'delete', 'orders', 10, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:31'),
(36, 1, 'System Administrator', 'delete', 'orders', 9, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:34'),
(37, 1, 'System Administrator', 'delete', 'orders', 8, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:36'),
(38, 1, 'System Administrator', 'delete', 'orders', 7, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:38'),
(39, 1, 'System Administrator', 'delete', 'orders', 5, NULL, NULL, '26.201.9.238', '2026-06-16 21:19:40'),
(40, 1, 'System Administrator', 'create', 'orders', 17, NULL, '{\"order_number\":\"20260001\"}', '26.201.9.238', '2026-06-16 21:28:14'),
(41, 1, 'System Administrator', 'update_status', 'orders', 17, '{\"status_id\":1}', '{\"status_id\":\"3\"}', '26.201.9.238', '2026-06-16 21:31:36'),
(42, 1, 'System Administrator', 'delete', 'orders', 17, NULL, NULL, '26.201.9.238', '2026-06-16 21:35:20'),
(43, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-16 21:41:21'),
(44, 1, 'System Administrator', 'create', 'orders', 20, NULL, '{\"order_number\":\"20260001\"}', '26.201.9.238', '2026-06-16 22:31:17'),
(45, 1, 'System Administrator', 'create', 'orders', 21, NULL, '{\"order_number\":\"20260002\"}', '26.201.9.238', '2026-06-16 22:33:01'),
(46, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-16 23:08:56'),
(47, 1, 'System Administrator', 'create', 'orders', 22, NULL, '{\"order_number\":\"20260003\"}', '26.201.9.238', '2026-06-16 23:22:59'),
(48, 1, 'System Administrator', 'update', 'orders', 22, NULL, '{\"order_number\":\"20260003\"}', '26.201.9.238', '2026-06-16 23:28:20'),
(49, 1, 'System Administrator', 'update', 'orders', 22, NULL, '{\"order_number\":\"20260003\"}', '26.201.9.238', '2026-06-16 23:28:49'),
(50, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.222.248.213', '2026-06-16 23:30:23'),
(51, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.222.248.213', '2026-06-16 23:33:16'),
(52, 1, 'System Administrator', 'create', 'users', 2, NULL, NULL, '26.201.9.238', '2026-06-16 23:45:07'),
(53, 2, 'Ahmed Zain', 'login', 'users', 2, NULL, NULL, '26.201.9.238', '2026-06-16 23:45:16'),
(54, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-16 23:45:34'),
(55, 1, 'System Administrator', 'purchase', 'order_items', 18, NULL, NULL, '26.201.9.238', '2026-06-17 00:00:39'),
(56, 1, 'System Administrator', 'create', 'orders', 23, NULL, '{\"order_number\":\"20260004\"}', '26.201.9.238', '2026-06-17 00:01:07'),
(57, 1, 'System Administrator', 'update', 'orders', 23, NULL, '{\"order_number\":\"20260004\"}', '26.201.9.238', '2026-06-17 00:01:23'),
(58, 1, 'System Administrator', 'purchase', 'order_items', 26, NULL, NULL, '26.201.9.238', '2026-06-17 00:07:16'),
(59, 1, 'System Administrator', 'update', 'orders', 22, NULL, '{\"order_number\":\"20260003\"}', '26.201.9.238', '2026-06-17 00:08:56'),
(60, 1, 'System Administrator', 'update', 'orders', 21, NULL, '{\"order_number\":\"20260002\"}', '26.201.9.238', '2026-06-17 00:09:18'),
(61, 1, 'System Administrator', 'create', 'orders', 24, NULL, '{\"order_number\":\"20260005\"}', '26.201.9.238', '2026-06-17 00:26:29'),
(62, 1, 'System Administrator', 'update', 'orders', 24, NULL, '{\"order_number\":\"20260005\"}', '26.201.9.238', '2026-06-17 00:26:37'),
(63, 1, 'System Administrator', 'update', 'orders', 21, NULL, '{\"order_number\":\"20260002\"}', '26.201.9.238', '2026-06-17 00:30:23'),
(64, 1, 'System Administrator', 'purchase', 'order_items', 34, NULL, NULL, '26.201.9.238', '2026-06-17 00:30:45'),
(65, 1, 'System Administrator', 'update', 'orders', 22, NULL, '{\"order_number\":\"20260003\"}', '26.201.9.238', '2026-06-17 00:31:43'),
(66, 1, 'System Administrator', 'create', 'orders', 25, NULL, '{\"order_number\":\"20260006\"}', '26.201.9.238', '2026-06-17 00:49:22'),
(67, 1, 'System Administrator', 'update', 'orders', 25, NULL, '{\"order_number\":\"20260006\"}', '26.201.9.238', '2026-06-17 00:49:29'),
(68, 1, 'System Administrator', 'update', 'orders', 25, NULL, '{\"order_number\":\"20260006\"}', '26.201.9.238', '2026-06-17 00:51:52'),
(69, 1, 'System Administrator', 'update', 'orders', 25, NULL, '{\"order_number\":\"20260006\"}', '26.201.9.238', '2026-06-17 00:52:04'),
(70, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-17 16:52:02'),
(71, 1, 'System Administrator', 'update', 'orders', 25, NULL, '{\"order_number\":\"20260006\"}', '26.201.9.238', '2026-06-17 16:59:50'),
(72, 1, 'System Administrator', 'update', 'orders', 24, NULL, '{\"order_number\":\"20260005\"}', '26.201.9.238', '2026-06-17 17:01:21'),
(73, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-17 23:29:05'),
(74, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-17 23:29:15'),
(75, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-20 16:00:59'),
(76, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-20 21:00:32'),
(77, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-20 21:02:12'),
(78, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-22 18:15:57'),
(79, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-22 22:22:55'),
(80, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.73.167.118', '2026-06-22 23:49:20'),
(81, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.73.167.118', '2026-06-23 10:57:43'),
(82, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.73.167.118', '2026-06-23 16:13:19'),
(83, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-23 16:38:54'),
(84, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-23 19:25:11'),
(85, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-23 19:25:17'),
(86, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-23 23:57:07'),
(87, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-23 23:57:15'),
(88, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 16:29:45'),
(89, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 17:49:43'),
(90, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 21:59:50'),
(91, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 22:01:40'),
(92, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 22:02:11'),
(93, 1, 'System', 'delete', 'suppliers', 4, NULL, '{\"deleted_at\":\"2026-06-26 01:03:42\",\"is_active\":0}', NULL, '2026-06-25 22:03:42'),
(94, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 22:14:48'),
(95, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 22:20:22'),
(96, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 22:21:13'),
(97, 1, 'System', 'delete', 'suppliers', 5, NULL, '{\"deleted_at\":\"2026-06-26 01:21:21\",\"is_active\":0}', NULL, '2026-06-25 22:21:21'),
(98, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 22:47:41'),
(99, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 23:04:06'),
(100, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 23:14:05'),
(101, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 23:26:42'),
(102, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 23:28:02'),
(103, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 23:31:01'),
(104, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-25 23:45:43'),
(105, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-27 16:34:00'),
(106, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-27 17:11:38'),
(107, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.222.248.213', '2026-06-27 19:00:29'),
(108, 1, 'System Administrator', 'login', 'users', 1, NULL, '{\"product_code\":\"100\"}', '26.201.9.238', '2026-06-28 17:24:44'),
(109, 1, 'System Administrator', 'create', 'customers', 31, NULL, '{\"customer_code\":\"31\"}', '26.201.9.238', '2026-06-27 08:00:00'),
(110, 1, 'System Administrator', 'create', 'suppliers', 17, NULL, '{\"supplier_code\":\"17\"}', '26.201.9.238', '2026-06-27 08:00:00'),
(111, 1, 'System Administrator', 'create', 'inventory_transfers', 4, NULL, '{\"transfer_code\":\"TR-00004\"}', '26.201.9.238', '2026-06-27 08:00:00'),
(112, 1, 'System Administrator', 'update', 'stock_adjustments', 3, '{\"status\":\"draft\"}', '{\"status\":\"completed\"}', '26.201.9.238', '2026-06-27 08:00:00'),
(113, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-28 23:58:05'),
(114, 1, 'System Administrator', 'logout', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-29 19:29:48'),
(115, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-29 19:30:00'),
(116, 1, 'System Administrator', 'login', 'users', 1, NULL, NULL, '26.201.9.238', '2026-06-29 21:44:48');

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `area_code` varchar(20) DEFAULT NULL,
  `area_name_ar` varchar(100) NOT NULL,
  `area_name_en` varchar(100) DEFAULT NULL,
  `governorate_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `area_code`, `area_name_ar`, `area_name_en`, `governorate_id`, `is_active`, `created_at`) VALUES
(1, NULL, 'المعادي', NULL, 1, 1, '2026-06-22 22:09:19'),
(2, NULL, 'مصر الجديدة', NULL, 1, 1, '2026-06-22 22:09:19'),
(3, NULL, 'مدينة نصر', NULL, 1, 1, '2026-06-22 22:09:19'),
(4, NULL, 'الدقي', NULL, 1, 1, '2026-06-22 22:09:19'),
(5, NULL, 'العجوزة', NULL, 1, 1, '2026-06-22 22:09:19'),
(6, NULL, 'الزمالك', NULL, 1, 1, '2026-06-22 22:09:19'),
(7, NULL, 'وسط البلد', NULL, 1, 1, '2026-06-22 22:09:19'),
(8, NULL, 'العباسية', NULL, 1, 1, '2026-06-22 22:09:19'),
(9, NULL, 'السيدة زينب', NULL, 1, 1, '2026-06-22 22:09:19'),
(10, NULL, 'حلوان', NULL, 1, 1, '2026-06-22 22:09:19'),
(11, NULL, 'التجمع الخامس', NULL, 1, 1, '2026-06-22 22:09:19'),
(12, NULL, 'الشيخ زايد', NULL, 2, 1, '2026-06-22 22:09:19'),
(13, NULL, '6 أكتوبر', NULL, 2, 1, '2026-06-22 22:09:19'),
(14, NULL, 'الحوامدية', NULL, 2, 1, '2026-06-22 22:09:19'),
(15, NULL, 'الساحل الشمالي', NULL, 3, 1, '2026-06-22 22:09:19'),
(16, NULL, 'سموحة', NULL, 3, 1, '2026-06-22 22:09:19'),
(17, NULL, 'المنتزه', NULL, 3, 1, '2026-06-22 22:09:19');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `address` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_code`, `branch_name`, `address`, `phone`, `is_active`, `created_at`) VALUES
(1, '1', 'شركة', '29 نوال الدقي', '01065656848', 1, '2026-06-16 19:06:20'),
(2, '2', 'نوال', '29 نوال الدقي', '01010300751', 1, '2026-06-16 19:06:20'),
(3, '3', 'مصدق', '29 نوال الدقي', '01099822899', 1, '2026-06-16 19:06:20'),
(4, '4', 'صيد', '29 نوال الدقي', '01099499478', 1, '2026-06-16 19:48:31');

-- --------------------------------------------------------

--
-- Table structure for table `company_employees`
--

CREATE TABLE `company_employees` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `employee_code` varchar(20) DEFAULT NULL,
  `employee_name` varchar(100) NOT NULL,
  `employee_name_en` varchar(100) DEFAULT NULL,
  `national_id` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `country_codes`
--

CREATE TABLE `country_codes` (
  `id` int(11) NOT NULL,
  `country_name_ar` varchar(100) NOT NULL,
  `country_name_en` varchar(100) NOT NULL,
  `country_code` varchar(10) NOT NULL,
  `flag_emoji` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `country_codes`
--

INSERT INTO `country_codes` (`id`, `country_name_ar`, `country_name_en`, `country_code`, `flag_emoji`, `is_active`) VALUES
(1, 'مصر', 'Egypt', '+20', '🇪🇬', 1),
(2, 'السعودية', 'Saudi Arabia', '+966', '🇸🇦', 1),
(3, 'الإمارات', 'UAE', '+971', '🇦🇪', 1),
(4, 'الكويت', 'Kuwait', '+965', '🇰🇼', 1),
(5, 'قطر', 'Qatar', '+974', '🇶🇦', 1),
(6, 'البحرين', 'Bahrain', '+973', '🇧🇭', 1),
(7, 'عمان', 'Oman', '+968', '🇴🇲', 1),
(8, 'الأردن', 'Jordan', '+962', '🇯🇴', 1),
(9, 'لبنان', 'Lebanon', '+961', '🇱🇧', 1),
(10, 'العراق', 'Iraq', '+964', '🇮🇶', 1),
(11, 'سوريا', 'Syria', '+963', '🇸🇾', 1),
(12, 'اليمن', 'Yemen', '+967', '🇾🇪', 1),
(13, 'ليبيا', 'Libya', '+218', '🇱🇾', 1),
(14, 'السودان', 'Sudan', '+249', '🇸🇩', 1),
(15, 'الجزائر', 'Algeria', '+213', '🇩🇿', 1),
(16, 'المغرب', 'Morocco', '+212', '🇲🇦', 1),
(17, 'تونس', 'Tunisia', '+216', '🇹🇳', 1),
(18, 'فلسطين', 'Palestine', '+970', '🇵🇸', 1);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_name_en` varchar(100) DEFAULT NULL,
  `customer_type` enum('individual','company') DEFAULT 'individual',
  `customer_class_id` int(11) DEFAULT NULL,
  `local_margin` decimal(5,2) DEFAULT 0.00,
  `imported_margin` decimal(5,2) DEFAULT 0.00,
  `local_discount` decimal(5,2) DEFAULT 0.00,
  `imported_discount` decimal(5,2) DEFAULT 0.00,
  `payment_type` enum('cash','credit') DEFAULT 'cash',
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `branch_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone2` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` enum('estock','manual') DEFAULT 'manual',
  `estock_id` decimal(18,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `customer_name`, `customer_name_en`, `customer_type`, `customer_class_id`, `local_margin`, `imported_margin`, `local_discount`, `imported_discount`, `payment_type`, `credit_limit`, `branch_id`, `phone`, `phone2`, `email`, `address`, `branch_code`, `notes`, `is_active`, `created_at`, `updated_at`, `source`, `estock_id`) VALUES
(1, '1', 'عمرو حجازي', 'Amr Hegazy', 'individual', 3, 0.00, 0.00, 0.00, 0.00, 'credit', 100000.00, 1, NULL, NULL, 'Amrstar89@hotmail.com', NULL, NULL, '', 1, '2026-06-22 23:20:33', '2026-06-22 23:20:33', 'manual', NULL),
(2, '2', 'عمرو حجازي', NULL, 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-22 23:33:54', '2026-06-22 23:33:54', 'manual', NULL),
(3, '3', 'احمد زين', 'Ahmed Zain', 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, 3, NULL, NULL, 'Zain@hotmail.com', NULL, NULL, '', 1, '2026-06-23 16:56:20', '2026-06-23 16:56:20', 'manual', NULL),
(4, '4', 'احمد حجازي', NULL, 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-23 16:57:18', '2026-06-23 16:57:18', 'manual', NULL),
(5, '5', 'عميل 4', NULL, 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-23 16:59:30', '2026-06-23 16:59:30', 'manual', NULL),
(6, '6', 'عميل 5', NULL, 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-23 16:59:36', '2026-06-23 16:59:36', 'manual', NULL),
(7, '7', 'عميل 6', NULL, 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-23 16:59:43', '2026-06-23 16:59:43', 'manual', NULL),
(8, '8', 'عمرو حجازي', 'Amr Hegazy', 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-23 17:00:09', '2026-06-23 17:00:09', 'manual', NULL),
(9, '9', 'عمرو حجازي', 'Amr Hegazy', 'company', 2, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, '', 1, '2026-06-23 20:55:01', '2026-06-23 20:55:01', 'manual', NULL),
(10, '10', 'محمد سعد', 'mohmed saad', 'individual', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, NULL, NULL, NULL, 'mohamed@hotmail.com', NULL, NULL, '', 1, '2026-06-23 20:55:38', '2026-06-23 20:55:38', 'manual', NULL),
(11, '11', 'محمد أحمد علي', 'Mohamed Ahmed Ali', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 1, '01011111111', NULL, 'mohamed@email.com', 'شارع التحرير، القاهرة', '1', 'عميل دائم', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(12, '12', 'فاطمة محمود حسن', 'Fatma Mahmoud Hassan', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 2, '01022222222', NULL, 'fatma@email.com', 'شارع النصر، القاهرة', '2', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(13, '13', 'أحمد سعيد إبراهيم', 'Ahmed Saeed Ibrahim', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'credit', 50000.00, 1, '01033333333', '01233333333', 'ahmed@email.com', 'شارع الجمهورية، الجيزة', '1', 'عميل VIP', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(14, '14', 'سارة خالد محمود', 'Sara Khaled Mahmoud', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 3, '01044444444', NULL, 'sara@email.com', 'شارع الهرم، الجيزة', '3', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(15, '15', 'خالد عمر فؤاد', 'Khaled Omar Fouad', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'credit', 30000.00, 2, '01055555555', '01255555555', 'khaled@email.com', 'شارع العروبة، القاهرة', '2', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(16, '16', 'نورا سامي عبدالله', 'Nora Sami Abdullah', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 4, '01066666666', NULL, 'nora@email.com', 'شارع الجلاء، الإسكندرية', '4', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(17, '17', 'عمر حسن علي', 'Omar Hassan Ali', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 1, '01077777777', NULL, 'omar@email.com', 'شارع رمسيس، القاهرة', '1', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(18, '18', 'ليلى محمد سالم', 'Laila Mohamed Salem', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'credit', 25000.00, 3, '01088888888', '01288888888', 'laila@email.com', 'شارع فيصل، الجيزة', '3', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(19, '19', 'يوسف أحمد محمود', 'Youssef Ahmed Mahmoud', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 2, '01099999999', NULL, 'youssef@email.com', 'شارع الميرغني، القاهرة', '2', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(20, '20', 'مريم خالد عمر', 'Mariam Khaled Omar', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 1, '01010101010', NULL, 'mariam@email.com', 'شارع قصر النيل، القاهرة', '1', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(21, '21', 'شركة الأمل للأدوية', 'El Amal Pharma Co.', 'company', 1, 15.00, 20.00, 0.00, 0.00, 'credit', 200000.00, 1, '01111111111', '01211111111', 'amal@company.com', 'المنطقة الصناعية، القاهرة', '1', 'عميل جملة', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(22, '22', 'مستشفى النور', 'El Noor Hospital', 'company', 1, 15.00, 20.00, 0.00, 0.00, 'credit', 500000.00, 2, '01122222222', '01222222222', 'noor@hospital.com', 'شارع الجامعة، القاهرة', '2', 'مستشفى كبير', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(23, '23', 'صيدلية الحياة', 'El Hayat Pharmacy', 'company', 1, 15.00, 20.00, 0.00, 0.00, 'credit', 100000.00, 3, '01133333333', '01233333333', 'hayat@pharmacy.com', 'شارع النصر، الإسكندرية', '3', 'عميل جملة', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(24, '24', 'شركة الصحة الدوائية', 'El Seha Pharma Co.', 'company', 1, 15.00, 20.00, 0.00, 0.00, 'credit', 300000.00, 1, '01144444444', '01244444444', 'seha@company.com', 'المنطقة الصناعية الثانية', '1', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(25, '25', 'مستشفى السلام', 'El Salam Hospital', 'company', 1, 15.00, 20.00, 0.00, 0.00, 'credit', 400000.00, 4, '01155555555', '01255555555', 'salam@hospital.com', 'شارع السلام، الجيزة', '4', '', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(26, '26', 'موظف أحمد سامي', 'Employee Ahmed Samy', 'individual', 3, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, 1, '01012121212', NULL, 'employee1@nile.com', '29 نوال الدقي', '1', 'موظف شركة', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(27, '27', 'موظف فاطمة علي', 'Employee Fatma Ali', 'individual', 3, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, 2, '01023232323', NULL, 'employee2@nile.com', '29 نوال الدقي', '2', 'موظف فرع نوال', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(28, '28', 'موظف خالد محمود', 'Employee Khaled Mahmoud', 'individual', 3, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, 3, '01034343434', NULL, 'employee3@nile.com', '29 نوال الدقي', '3', 'موظف فرع مصدق', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(29, '29', 'موظف سارة عمر', 'Employee Sara Omar', 'individual', 3, 0.00, 0.00, 0.00, 0.00, 'cash', 0.00, 4, '01045454545', NULL, 'employee4@nile.com', '29 نوال الدقي', '4', 'موظف فرع صيد', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(30, '30', 'عميل موقوف 1', 'Inactive Customer 1', 'individual', 2, 0.00, 0.00, 5.00, 10.00, 'cash', 0.00, 1, '01056565656', NULL, 'inactive1@email.com', 'عنوان غير معروف', '1', 'عميل موقوف', 0, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL),
(31, '31', 'عميل موقوف 2', 'Inactive Customer 2', 'company', 1, 15.00, 20.00, 0.00, 0.00, 'credit', 0.00, 2, '01067676767', NULL, 'inactive2@email.com', 'عنوان غير معروف', '2', 'عميل موقوف', 0, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers_backup`
--

CREATE TABLE `customers_backup` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone2` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers_backup`
--

INSERT INTO `customers_backup` (`id`, `customer_code`, `customer_name`, `phone`, `phone2`, `email`, `address`, `branch_code`, `notes`, `is_active`, `created_at`, `updated_at`, `source`, `estock_id`) VALUES
(1, 'CUST001', 'أحمد محمد', '01001234567', NULL, NULL, NULL, 'BR001', 'عميل دائم', 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL),
(2, 'CUST002', 'محمد علي', '01002345678', NULL, NULL, NULL, 'BR001', NULL, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL),
(3, 'CUST003', 'فاطمة أحمد', '01003456789', NULL, NULL, NULL, 'BR001', NULL, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL),
(5, 'CUST1781640433', 'الة', '01067785', '012645564984', NULL, '21لاالي اللاايس', '3', NULL, 1, '2026-06-16 20:07:13', '2026-06-16 20:07:13', 'estock', NULL),
(6, 'CUST1781640799', 'محمد ', '0123456789', '012345678998', NULL, '29 نوال الدقي', '2', NULL, 1, '2026-06-16 20:13:19', '2026-06-16 20:13:19', 'estock', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `address_type` enum('home','work','other') DEFAULT 'home',
  `building_number` varchar(20) DEFAULT NULL,
  `floor_number` varchar(10) DEFAULT NULL,
  `apartment_number` varchar(20) DEFAULT NULL,
  `street_name` varchar(200) DEFAULT NULL,
  `landmark` varchar(200) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `governorate_id` int(11) DEFAULT NULL,
  `delivery_zone_id` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `address_type`, `building_number`, `floor_number`, `apartment_number`, `street_name`, `landmark`, `area_id`, `governorate_id`, `delivery_zone_id`, `is_primary`, `is_active`, `created_at`) VALUES
(1, 1, 'home', '38', '2', '5', 'حي الاشجار', 'طريق الواحات', 13, 2, 4, 1, 1, '2026-06-22 23:20:33'),
(2, 11, 'home', '15', '3', '7', 'شارع التحرير', 'بجوار البنك الأهلي', 1, 1, 1, 1, 1, '2026-06-20 08:00:00'),
(3, 11, 'work', '22', '5', '12', 'شارع رمسيس', 'مبنى الأهرام', 7, 1, 2, 0, 1, '2026-06-20 08:00:00'),
(4, 13, 'home', '8', '2', '4', 'شارع الجمهورية', 'بجوار المسجد', 13, 2, 3, 1, 1, '2026-06-20 08:00:00'),
(5, 13, 'work', '45', '7', '15', 'شارع فيصل', 'برج النصر', 12, 2, 3, 0, 1, '2026-06-20 08:00:00'),
(6, 15, 'home', '12', '1', '3', 'شارع العروبة', 'بجوار المستشفى', 2, 1, 1, 1, 1, '2026-06-20 08:00:00'),
(7, 22, 'home', '30', '4', '10', 'شارع الجامعة', 'جامعة القاهرة', 3, 1, 2, 1, 1, '2026-06-20 08:00:00'),
(8, 22, 'work', '5', '1', '2', 'شارع الجامعة', 'مستشفى الجامعة', 3, 1, 2, 0, 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_areas`
--

CREATE TABLE `customer_areas` (
  `id` int(11) NOT NULL,
  `area_code` varchar(20) DEFAULT NULL,
  `area_name_ar` varchar(100) NOT NULL,
  `area_name_en` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_balances`
--

CREATE TABLE `customer_balances` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد الحالي (مدين إذا موجب، دائن إذا سالب)',
  `total_invoices` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي قيمة الفواتير',
  `total_payments` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المدفوعات',
  `total_returns` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المردودات',
  `last_transaction_date` datetime DEFAULT NULL COMMENT 'تاريخ آخر حركة',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أرصدة العملاء';

--
-- Dumping data for table `customer_balances`
--

INSERT INTO `customer_balances` (`id`, `customer_id`, `balance`, `total_invoices`, `total_payments`, `total_returns`, `last_transaction_date`, `updated_at`) VALUES
(1, 2, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(2, 3, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(3, 4, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(4, 5, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(5, 6, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(6, 7, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(7, 8, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(8, 10, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(9, 9, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(10, 1, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 17:11:16'),
(11, 11, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(12, 12, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(13, 13, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(14, 14, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(15, 15, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(16, 16, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(17, 17, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(18, 18, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(19, 19, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(20, 20, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(21, 21, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(22, 22, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(23, 23, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(24, 24, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(25, 25, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(26, 26, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(27, 27, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(28, 28, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(29, 29, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(30, 30, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(31, 31, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_classes`
--

CREATE TABLE `customer_classes` (
  `id` int(11) NOT NULL,
  `class_code` varchar(50) DEFAULT NULL,
  `class_name_ar` varchar(50) NOT NULL,
  `class_name_en` varchar(50) DEFAULT NULL,
  `class_type` enum('wholesale','retail','cost') DEFAULT 'retail',
  `local_margin` decimal(5,2) DEFAULT 0.00,
  `imported_margin` decimal(5,2) DEFAULT 0.00,
  `local_discount` decimal(5,2) DEFAULT 0.00,
  `imported_discount` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_classes`
--

INSERT INTO `customer_classes` (`id`, `class_code`, `class_name_ar`, `class_name_en`, `class_type`, `local_margin`, `imported_margin`, `local_discount`, `imported_discount`, `is_active`, `source`, `estock_id`, `created_at`) VALUES
(1, NULL, 'جملة', 'Wholesale', 'wholesale', 15.00, 20.00, 0.00, 0.00, 1, 'estock', NULL, '2026-06-22 22:50:25'),
(2, NULL, 'تجزئة', 'Retail', 'retail', 0.00, 0.00, 5.00, 10.00, 1, 'estock', NULL, '2026-06-22 22:50:25'),
(3, NULL, 'تكلفة', 'Cost', 'cost', 0.00, 0.00, 0.00, 0.00, 1, 'estock', NULL, '2026-06-22 22:50:25');

-- --------------------------------------------------------

--
-- Table structure for table `customer_contracts`
--

CREATE TABLE `customer_contracts` (
  `id` int(11) NOT NULL,
  `contract_code` varchar(50) DEFAULT NULL,
  `contract_name` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `contract_number` varchar(50) DEFAULT NULL,
  `contract_type` enum('insurance','company','government','other') DEFAULT 'insurance',
  `card_number` varchar(50) DEFAULT NULL,
  `patient_card_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `coverage_percent` decimal(5,2) DEFAULT 100.00,
  `max_bill_amount` decimal(10,2) DEFAULT 0.00,
  `bill_discount` decimal(18,2) DEFAULT 0.00,
  `customer_pay_rate` decimal(18,2) DEFAULT 0.00,
  `customer_pay_value` decimal(10,2) DEFAULT 0.00,
  `discount_rule` int(11) DEFAULT 1,
  `company_pay_rule` int(11) DEFAULT 1,
  `product_discount` tinyint(1) DEFAULT 0,
  `local_discount` decimal(18,2) DEFAULT 0.00,
  `import_discount` decimal(18,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_phones`
--

CREATE TABLE `customer_phones` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `country_code` varchar(10) DEFAULT '+20',
  `phone_number` varchar(20) NOT NULL,
  `phone_type` enum('mobile','home','work','other') DEFAULT 'mobile',
  `is_primary` tinyint(1) DEFAULT 0,
  `is_whatsapp` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_phones`
--

INSERT INTO `customer_phones` (`id`, `customer_id`, `country_code`, `phone_number`, `phone_type`, `is_primary`, `is_whatsapp`, `created_at`) VALUES
(1, 1, '+20', '01067788553', 'mobile', 1, 1, '2026-06-22 23:20:33'),
(2, 1, '+20', '01007837873', 'mobile', 0, 0, '2026-06-22 23:20:33'),
(3, 2, '+20', '01067788553', 'mobile', 1, 0, '2026-06-22 23:33:54'),
(4, 3, '+20', '01234567891011', 'mobile', 1, 0, '2026-06-23 16:56:20'),
(5, 3, '+20', '010012013014015', 'mobile', 0, 0, '2026-06-23 16:56:20'),
(6, 10, '+20', '0123456789', 'mobile', 1, 1, '2026-06-23 20:55:38'),
(7, 11, '+20', '01011111111', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(8, 11, '+20', '01111111111', 'work', 0, 0, '2026-06-20 08:00:00'),
(9, 12, '+20', '01022222222', 'mobile', 1, 0, '2026-06-20 08:00:00'),
(10, 13, '+20', '01033333333', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(11, 13, '+20', '01233333333', 'home', 0, 0, '2026-06-20 08:00:00'),
(12, 14, '+20', '01044444444', 'mobile', 1, 0, '2026-06-20 08:00:00'),
(13, 15, '+20', '01055555555', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(14, 15, '+20', '01255555555', 'work', 0, 0, '2026-06-20 08:00:00'),
(15, 16, '+20', '01066666666', 'mobile', 1, 0, '2026-06-20 08:00:00'),
(16, 17, '+20', '01077777777', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(17, 18, '+20', '01088888888', 'mobile', 1, 0, '2026-06-20 08:00:00'),
(18, 18, '+20', '01288888888', 'home', 0, 0, '2026-06-20 08:00:00'),
(19, 19, '+20', '01099999999', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(20, 20, '+20', '01010101010', 'mobile', 1, 0, '2026-06-20 08:00:00'),
(21, 21, '+20', '01111111111', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(22, 21, '+20', '01211111111', 'work', 0, 0, '2026-06-20 08:00:00'),
(23, 22, '+20', '01122222222', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(24, 22, '+20', '01222222222', 'home', 0, 0, '2026-06-20 08:00:00'),
(25, 23, '+20', '01133333333', 'mobile', 1, 0, '2026-06-20 08:00:00'),
(26, 24, '+20', '01144444444', 'mobile', 1, 1, '2026-06-20 08:00:00'),
(27, 25, '+20', '01155555555', 'mobile', 1, 0, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `customer_transactions`
--

CREATE TABLE `customer_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transaction_type` enum('invoice','payment','return','refund','adjustment') NOT NULL COMMENT 'نوع الحركة',
  `reference_type` enum('order','invoice','payment','return','manual') DEFAULT 'manual' COMMENT 'نوع المرجع',
  `reference_id` int(11) DEFAULT NULL COMMENT 'رقم المرجع (رقم الفاتورة/الدفعة/المردود)',
  `reference_number` varchar(50) DEFAULT NULL COMMENT 'رقم المرجع النصي',
  `debit` decimal(12,2) DEFAULT 0.00 COMMENT 'مدين (زيادة على العميل)',
  `credit` decimal(12,2) DEFAULT 0.00 COMMENT 'دائن (نقصان من العميل)',
  `balance_after` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد بعد الحركة',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `created_by` int(11) DEFAULT NULL COMMENT 'الموظف الذي أنشأ الحركة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حركات حسابات العملاء';

--
-- Dumping data for table `customer_transactions`
--

INSERT INTO `customer_transactions` (`id`, `customer_id`, `transaction_type`, `reference_type`, `reference_id`, `reference_number`, `debit`, `credit`, `balance_after`, `notes`, `created_by`, `created_at`) VALUES
(1, 13, 'invoice', 'invoice', 1, 'INV-20260001', 5000.00, 0.00, 5000.00, 'فاتورة مبيعات', 1, '2026-06-20 08:00:00'),
(2, 13, 'payment', 'payment', 1, 'PAY-20260001', 0.00, 2000.00, 3000.00, 'دفعة جزئية', 1, '2026-06-21 08:00:00'),
(3, 15, 'invoice', 'invoice', 2, 'INV-20260002', 8000.00, 0.00, 8000.00, 'فاتورة مبيعات', 1, '2026-06-22 08:00:00'),
(4, 15, 'payment', 'payment', 2, 'PAY-20260002', 0.00, 3000.00, 5000.00, 'دفعة جزئية', 1, '2026-06-23 08:00:00'),
(5, 21, 'invoice', 'invoice', 3, 'INV-20260003', 15000.00, 0.00, 15000.00, 'فاتورة جملة', 1, '2026-06-24 08:00:00'),
(6, 21, 'payment', 'payment', 3, 'PAY-20260003', 0.00, 5000.00, 10000.00, 'دفعة جزئية', 1, '2026-06-25 08:00:00'),
(7, 22, 'invoice', 'invoice', 4, 'INV-20260004', 25000.00, 0.00, 25000.00, 'فاتورة مستشفى', 1, '2026-06-26 08:00:00'),
(8, 22, 'payment', 'payment', 4, 'PAY-20260004', 0.00, 10000.00, 15000.00, 'دفعة جزئية', 1, '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_times`
--

CREATE TABLE `delivery_times` (
  `id` int(11) NOT NULL,
  `time_code` varchar(20) NOT NULL,
  `time_name` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_times`
--

INSERT INTO `delivery_times` (`id`, `time_code`, `time_name`, `sort_order`, `is_active`, `created_at`) VALUES
(1, '1H', 'ساعة', 1, 1, '2026-06-17 00:13:03'),
(2, '2H', 'ساعتين', 2, 1, '2026-06-17 00:13:03'),
(3, '4H', '3-4 ساعات', 3, 1, '2026-06-17 00:13:03'),
(4, 'TD', 'نفس اليوم', 4, 1, '2026-06-17 00:13:03'),
(5, '1D', 'يوم عمل', 5, 1, '2026-06-17 00:13:03'),
(6, '1W', 'أسبوع', 6, 1, '2026-06-17 00:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` int(11) NOT NULL,
  `zone_code` varchar(20) DEFAULT NULL,
  `zone_name_ar` varchar(100) NOT NULL,
  `zone_name_en` varchar(100) DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_zones`
--

INSERT INTO `delivery_zones` (`id`, `zone_code`, `zone_name_ar`, `zone_name_en`, `delivery_fee`, `is_active`, `created_at`) VALUES
(1, 'Z1', 'المنطقة الداخلية', NULL, 25.00, 1, '2026-06-22 22:09:19'),
(2, 'Z2', 'المنطقة المتوسطة', NULL, 40.00, 1, '2026-06-22 22:09:19'),
(3, 'Z3', 'المنطقة الخارجية', NULL, 60.00, 1, '2026-06-22 22:09:19'),
(4, 'Z4', 'المنطقة البعيدة', NULL, 80.00, 1, '2026-06-22 22:09:19');

-- --------------------------------------------------------

--
-- Table structure for table `governorates`
--

CREATE TABLE `governorates` (
  `id` int(11) NOT NULL,
  `governorate_code` varchar(20) DEFAULT NULL,
  `governorate_name_ar` varchar(100) NOT NULL,
  `governorate_name_en` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `governorates`
--

INSERT INTO `governorates` (`id`, `governorate_code`, `governorate_name_ar`, `governorate_name_en`, `is_active`, `created_at`) VALUES
(1, 'EG-C', 'القاهرة', 'Cairo', 1, '2026-06-22 22:09:19'),
(2, 'EG-GZ', 'الجيزة', 'Giza', 1, '2026-06-22 22:09:19'),
(3, 'EG-ALX', 'الإسكندرية', 'Alexandria', 1, '2026-06-22 22:09:19'),
(4, 'EG-DK', 'الدقهلية', 'Dakahlia', 1, '2026-06-22 22:09:19'),
(5, 'EG-BHR', 'البحر الأحمر', 'Red Sea', 1, '2026-06-22 22:09:19'),
(6, 'EG-BH', 'البحيرة', 'Beheira', 1, '2026-06-22 22:09:19'),
(7, 'EG-FYM', 'الفيوم', 'Fayoum', 1, '2026-06-22 22:09:19'),
(8, 'EG-GH', 'الغربية', 'Gharbia', 1, '2026-06-22 22:09:19'),
(9, 'EG-IS', 'الإسماعيلية', 'Ismailia', 1, '2026-06-22 22:09:19'),
(10, 'EG-MNF', 'المنوفية', 'Menofia', 1, '2026-06-22 22:09:19'),
(11, 'EG-MN', 'المنيا', 'Minya', 1, '2026-06-22 22:09:19'),
(12, 'EG-KB', 'القليوبية', 'Qalyubia', 1, '2026-06-22 22:09:19'),
(13, 'EG-KN', 'قنا', 'Qena', 1, '2026-06-22 22:09:19'),
(14, 'EG-SHG', 'سوهاج', 'Sohag', 1, '2026-06-22 22:09:19'),
(15, 'EG-SUZ', 'السويس', 'Suez', 1, '2026-06-22 22:09:19'),
(16, 'EG-ASN', 'أسوان', 'Aswan', 1, '2026-06-22 22:09:19'),
(17, 'EG-AST', 'أسيوط', 'Assiut', 1, '2026-06-22 22:09:19'),
(18, 'EG-BNS', 'بني سويف', 'Beni Suef', 1, '2026-06-22 22:09:19'),
(19, 'EG-PTS', 'بورسعيد', 'Port Said', 1, '2026-06-22 22:09:19'),
(20, 'EG-DT', 'دمياط', 'Damietta', 1, '2026-06-22 22:09:19'),
(21, 'EG-JS', 'جنوب سيناء', 'South Sinai', 1, '2026-06-22 22:09:19'),
(22, 'EG-KFS', 'كفر الشيخ', 'Kafr El Sheikh', 1, '2026-06-22 22:09:19'),
(23, 'EG-MT', 'مطروح', 'Matrouh', 1, '2026-06-22 22:09:19'),
(24, 'EG-LX', 'الأقصر', 'Luxor', 1, '2026-06-22 22:09:19'),
(25, 'EG-WAD', 'الوادي الجديد', 'New Valley', 1, '2026-06-22 22:09:19');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` int(11) NOT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,3) DEFAULT 0.000,
  `remaining_qty` decimal(12,3) DEFAULT 0.000,
  `buy_price` decimal(12,2) DEFAULT 0.00,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `sell_price` decimal(12,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 0.00,
  `exp_date` date DEFAULT NULL,
  `production_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `batch_number`, `product_id`, `store_id`, `supplier_id`, `quantity`, `remaining_qty`, `buy_price`, `unit_cost`, `sell_price`, `discount_percent`, `vat_percent`, `exp_date`, `production_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'BATCH-001', 10, 1, 1, 500.000, 450.000, 30.00, 30.00, 45.00, 0.00, 14.00, '2027-12-31', '2026-01-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(2, 'BATCH-002', 10, 1, 1, 300.000, 280.000, 28.00, 28.00, 45.00, 0.00, 14.00, '2027-11-30', '2026-02-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(3, 'BATCH-003', 11, 1, 1, 400.000, 380.000, 55.00, 55.00, 85.00, 0.00, 14.00, '2027-10-31', '2026-03-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(4, 'BATCH-004', 16, 1, 2, 600.000, 550.000, 42.00, 42.00, 65.00, 0.00, 14.00, '2027-09-30', '2026-04-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(5, 'BATCH-005', 17, 1, 2, 800.000, 750.000, 48.00, 48.00, 75.00, 0.00, 14.00, '2027-08-31', '2026-05-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(6, 'BATCH-006', 24, 1, 1, 500.000, 480.000, 55.00, 55.00, 85.00, 0.00, 14.00, '2027-07-31', '2026-06-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(7, 'BATCH-007', 28, 1, 2, 1000.000, 950.000, 16.00, 16.00, 25.00, 0.00, 14.00, '2027-06-30', '2026-07-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(8, 'BATCH-008', 29, 1, 2, 1500.000, 1400.000, 11.00, 11.00, 18.00, 0.00, 14.00, '2027-05-31', '2026-08-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(9, 'BATCH-009', 36, 1, 1, 500.000, 480.000, 62.00, 62.00, 95.00, 0.00, 14.00, '2027-04-30', '2026-09-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(10, 'BATCH-010', 40, 1, 2, 400.000, 380.000, 35.00, 35.00, 65.00, 0.00, 14.00, '2027-03-31', '2026-10-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(11, 'BATCH-011', 10, 2, 1, 200.000, 180.000, 30.00, 30.00, 45.00, 0.00, 14.00, '2027-12-31', '2026-01-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(12, 'BATCH-012', 16, 2, 2, 150.000, 140.000, 42.00, 42.00, 65.00, 0.00, 14.00, '2027-09-30', '2026-04-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(13, 'BATCH-013', 28, 3, 2, 100.000, 95.000, 16.00, 16.00, 25.00, 0.00, 14.00, '2027-06-30', '2026-07-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(14, 'BATCH-014', 29, 3, 2, 200.000, 190.000, 11.00, 11.00, 18.00, 0.00, 14.00, '2027-05-31', '2026-08-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(15, 'BATCH-015', 17, 4, 2, 120.000, 110.000, 48.00, 48.00, 75.00, 0.00, 14.00, '2027-08-31', '2026-05-01', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,3) DEFAULT 0.000,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `sell_price` decimal(12,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 0.00,
  `reorder_point` decimal(12,3) DEFAULT 0.000,
  `max_stock` decimal(12,3) DEFAULT 0.000,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `store_id`, `product_id`, `batch_id`, `quantity`, `unit_cost`, `sell_price`, `discount_percent`, `vat_percent`, `reorder_point`, `max_stock`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 10, 1, 450.000, 30.00, 45.00, 0.00, 14.00, 50.000, 1000.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(2, 1, 10, 2, 280.000, 28.00, 45.00, 0.00, 14.00, 50.000, 1000.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(3, 1, 11, 3, 380.000, 55.00, 85.00, 0.00, 14.00, 40.000, 800.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(4, 1, 16, 4, 550.000, 42.00, 65.00, 0.00, 14.00, 60.000, 1200.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(5, 1, 17, 5, 750.000, 48.00, 75.00, 0.00, 14.00, 80.000, 1500.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(6, 1, 24, 6, 480.000, 55.00, 85.00, 0.00, 14.00, 50.000, 1000.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(7, 1, 28, 7, 950.000, 16.00, 25.00, 0.00, 14.00, 100.000, 2000.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(8, 1, 29, 8, 1400.000, 11.00, 18.00, 0.00, 14.00, 150.000, 3000.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(9, 1, 36, 9, 480.000, 62.00, 95.00, 0.00, 14.00, 50.000, 1000.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(10, 1, 40, 10, 380.000, 35.00, 65.00, 0.00, 14.00, 40.000, 800.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(11, 2, 10, 11, 180.000, 30.00, 45.00, 0.00, 14.00, 20.000, 500.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(12, 2, 16, 12, 140.000, 42.00, 65.00, 0.00, 14.00, 15.000, 400.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(13, 3, 28, 13, 95.000, 16.00, 25.00, 0.00, 14.00, 10.000, 300.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(14, 3, 29, 14, 190.000, 11.00, 18.00, 0.00, 14.00, 20.000, 500.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(15, 4, 17, 15, 110.000, 48.00, 75.00, 0.00, 14.00, 12.000, 350.000, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `transaction_type` enum('opening_balance','purchase','sale','transfer_out','transfer_in','adjustment','return_in','return_out','damage','expired') NOT NULL,
  `reference_type` enum('opening_balance','purchase_invoice','sale_invoice','transfer_order','adjustment','return') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `unit_conversion` decimal(10,3) DEFAULT 1.000,
  `quantity` decimal(12,3) NOT NULL,
  `quantity_base` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `transaction_type`, `reference_type`, `reference_id`, `store_id`, `product_id`, `batch_id`, `unit_id`, `unit_conversion`, `quantity`, `quantity_base`, `unit_cost`, `unit_price`, `discount_percent`, `vat_percent`, `total_cost`, `total_price`, `notes`, `created_by`, `created_at`) VALUES
(1, 'transfer_out', 'transfer_order', 1, 1, 3, NULL, NULL, 1.000, -5.000, -5.000, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'تحويل صادر: TR-00001', 1, '2026-06-27 19:04:44'),
(2, 'opening_balance', 'opening_balance', 1, 1, 10, 1, 1, 1.000, 500.000, 500.000, 30.00, 45.00, 0.00, 14.00, 15000.00, 22500.00, 'رصيد افتتاحي', 1, '2026-06-20 08:00:00'),
(3, 'opening_balance', 'opening_balance', 1, 1, 16, 4, 1, 1.000, 600.000, 600.000, 42.00, 65.00, 0.00, 14.00, 25200.00, 39000.00, 'رصيد افتتاحي', 1, '2026-06-20 08:00:00'),
(4, 'opening_balance', 'opening_balance', 1, 1, 17, 5, 1, 1.000, 800.000, 800.000, 48.00, 75.00, 0.00, 14.00, 38400.00, 60000.00, 'رصيد افتتاحي', 1, '2026-06-20 08:00:00'),
(5, 'opening_balance', 'opening_balance', 1, 1, 28, 7, 1, 1.000, 1000.000, 1000.000, 16.00, 25.00, 0.00, 14.00, 16000.00, 25000.00, 'رصيد افتتاحي', 1, '2026-06-20 08:00:00'),
(6, 'opening_balance', 'opening_balance', 1, 1, 29, 8, 1, 1.000, 1500.000, 1500.000, 11.00, 18.00, 0.00, 14.00, 16500.00, 27000.00, 'رصيد افتتاحي', 1, '2026-06-20 08:00:00'),
(7, 'purchase', 'purchase_invoice', 1, 1, 10, 2, 1, 1.000, 300.000, 300.000, 28.00, 45.00, 0.00, 14.00, 8400.00, 13500.00, 'شراء من مورد', 1, '2026-06-21 08:00:00'),
(8, 'purchase', 'purchase_invoice', 2, 1, 11, 3, 1, 1.000, 400.000, 400.000, 55.00, 85.00, 0.00, 14.00, 22000.00, 34000.00, 'شراء من مورد', 1, '2026-06-22 08:00:00'),
(9, 'transfer_out', 'transfer_order', 2, 1, 10, 1, 1, 1.000, -200.000, -200.000, 30.00, 45.00, 0.00, 14.00, -6000.00, -9000.00, 'تحويل لفرع نوال', 1, '2026-06-23 08:00:00'),
(10, 'transfer_in', 'transfer_order', 2, 2, 10, 11, 1, 1.000, 200.000, 200.000, 30.00, 45.00, 0.00, 14.00, 6000.00, 9000.00, 'استلام من المخزن الرئيسي', 1, '2026-06-23 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transfers`
--

CREATE TABLE `inventory_transfers` (
  `id` int(11) NOT NULL,
  `transfer_code` varchar(20) NOT NULL,
  `from_store_id` int(11) NOT NULL,
  `to_store_id` int(11) NOT NULL,
  `from_branch_id` int(11) DEFAULT NULL,
  `to_branch_id` int(11) DEFAULT NULL,
  `transfer_type` enum('internal','branch_to_branch','central_to_branch','branch_to_central') NOT NULL,
  `status` enum('draft','pending','approved','shipped','partial_received','received','rejected','cancelled') DEFAULT 'draft',
  `total_items` int(11) DEFAULT 0,
  `total_quantity` decimal(12,3) DEFAULT 0.000,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `total_sell` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `shipped_by` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_transfers`
--

INSERT INTO `inventory_transfers` (`id`, `transfer_code`, `from_store_id`, `to_store_id`, `from_branch_id`, `to_branch_id`, `transfer_type`, `status`, `total_items`, `total_quantity`, `total_cost`, `total_sell`, `notes`, `requested_by`, `approved_by`, `shipped_by`, `received_by`, `requested_at`, `approved_at`, `shipped_at`, `received_at`, `transfer_date`, `created_at`) VALUES
(1, 'TR-00001', 1, 3, NULL, 2, 'central_to_branch', 'partial_received', 1, 5.000, 0.00, 0.00, NULL, 1, 1, 1, 1, '2026-06-27 21:04:12', '2026-06-27 21:04:35', '2026-06-27 21:04:44', '2026-06-29 23:46:43', NULL, '2026-06-27 19:04:12'),
(2, 'TR-00002', 1, 2, NULL, 1, 'central_to_branch', 'received', 2, 350.000, 31200.00, 45500.00, 'تحويل للفرع الرئيسي', 1, 1, 1, 1, '2026-06-23 10:00:00', '2026-06-23 10:30:00', '2026-06-23 11:00:00', '2026-06-23 14:00:00', NULL, '2026-06-23 08:00:00'),
(3, 'TR-00003', 1, 3, NULL, 2, 'central_to_branch', 'partial_received', 2, 295.000, 26600.00, 38750.00, 'تحويل لفرع نوال', 1, 1, 1, 1, '2026-06-24 10:00:00', '2026-06-24 10:30:00', '2026-06-24 11:00:00', '2026-06-29 23:22:17', NULL, '2026-06-24 08:00:00'),
(4, 'TR-00004', 2, 1, 1, NULL, 'branch_to_central', 'pending', 1, 50.000, 2100.00, 3250.00, 'إرجاع للمخزن الرئيسي', 1, NULL, NULL, NULL, '2026-06-25 10:00:00', NULL, NULL, NULL, NULL, '2026-06-25 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transfer_items`
--

CREATE TABLE `inventory_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `requested_qty` decimal(12,3) DEFAULT 0.000,
  `shipped_qty` decimal(12,3) DEFAULT 0.000,
  `received_qty` decimal(12,3) DEFAULT 0.000,
  `unit_id` int(11) DEFAULT NULL,
  `unit_conversion` decimal(10,3) DEFAULT 1.000,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `sell_price` decimal(12,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `total_sell` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('pending','shipped','received','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_transfer_items`
--

INSERT INTO `inventory_transfer_items` (`id`, `transfer_id`, `product_id`, `batch_id`, `requested_qty`, `shipped_qty`, `received_qty`, `unit_id`, `unit_conversion`, `unit_cost`, `sell_price`, `total_cost`, `total_sell`, `notes`, `status`) VALUES
(1, 1, 3, NULL, 5.000, 0.000, 0.000, NULL, 1.000, 0.00, 0.00, 0.00, 0.00, NULL, 'pending'),
(2, 2, 16, 4, 150.000, 150.000, 150.000, 1, 1.000, 42.00, 65.00, 6300.00, 9750.00, '', 'received'),
(3, 2, 17, 5, 200.000, 200.000, 200.000, 1, 1.000, 48.00, 75.00, 9600.00, 15000.00, '', 'received'),
(4, 3, 28, 7, 100.000, 100.000, 0.000, 1, 1.000, 16.00, 25.00, 1600.00, 2500.00, '', 'shipped'),
(5, 3, 29, 8, 195.000, 195.000, 0.000, 1, 1.000, 11.00, 18.00, 2145.00, 3510.00, '', 'shipped'),
(6, 4, 16, 12, 50.000, 0.000, 0.000, 1, 1.000, 42.00, 65.00, 2100.00, 3250.00, 'إرجاع', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_code` varchar(50) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_phone2` varchar(20) DEFAULT NULL,
  `customer_address` varchar(255) DEFAULT NULL,
  `branch_code` varchar(20) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `priority` enum('normal','urgent','critical') DEFAULT 'normal',
  `status_id` int(11) DEFAULT 1,
  `total_items` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_discount_type` enum('percentage','fixed') DEFAULT NULL,
  `total_discount_value` decimal(10,2) DEFAULT 0.00,
  `final_total` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `customer_code`, `customer_name`, `customer_phone`, `customer_phone2`, `customer_address`, `branch_code`, `order_date`, `priority`, `status_id`, `total_items`, `notes`, `created_by`, `created_at`, `updated_by`, `updated_at`, `total_discount_type`, `total_discount_value`, `final_total`) VALUES
(20, '20260001', 1, 'CUST001', 'أحمد محمد', '01001234567', '', '', NULL, '2026-06-17 00:00:00', 'normal', 1, 1, '', 1, '2026-06-16 22:31:17', NULL, NULL, '', 0.00, 210.00),
(21, '20260002', 3, 'CUST003', 'فاطمة أحمد', '01003456789', '', '', '', '2026-06-17 00:00:00', 'critical', 1, 2, 'ملاحظات عامىة', 1, '2026-06-16 22:33:01', 1, '2026-06-17 00:30:23', 'fixed', 190.00, 75510.00),
(22, '20260003', 5, 'CUST1781640433', 'الة', '01067785', '012645564984', '21لاالي اللاايس', '4', '2026-06-17 01:22:59', 'urgent', 1, 2, '', 1, '2026-06-16 23:22:59', 1, '2026-06-17 00:31:43', '', 0.00, 72050.00),
(23, '20260004', 2, 'CUST002', 'محمد علي', '01002345678', '', '', '', '2026-06-17 02:01:07', 'normal', 1, 1, '', 1, '2026-06-17 00:01:07', 1, '2026-06-17 00:01:23', '', 0.00, 0.00),
(24, '20260005', 6, 'CUST1781640799', 'محمد', '0123456789', '012345678998', '29 نوال الدقي', '2', '2026-06-17 02:26:29', 'normal', 1, 1, '', 1, '2026-06-17 00:26:29', 1, '2026-06-17 17:01:21', '', 0.00, 144.00),
(25, '20260006', 5, 'CUST1781640433', 'الة', '01067785', '012645564984', '21لاالي اللاايس', '3', '2026-06-17 02:49:22', 'normal', 1, 1, '', 1, '2026-06-17 00:49:22', 1, '2026-06-17 16:59:50', '', 0.00, 144.00),
(26, '20260007', 11, '11', 'محمد أحمد علي', '01011111111', '', 'شارع التحرير، القاهرة', '1', '2026-06-20 10:00:00', 'normal', 1, 2, '', 1, '2026-06-20 08:00:00', NULL, NULL, '', 0.00, 150.00),
(27, '20260008', 13, '13', 'أحمد سعيد إبراهيم', '01033333333', '01233333333', 'شارع الجمهورية، الجيزة', '1', '2026-06-21 10:00:00', 'urgent', 2, 3, 'طلب عاجل', 1, '2026-06-21 08:00:00', 1, '2026-06-21 10:00:00', 'percentage', 5.00, 285.00),
(28, '20260009', 21, '21', 'شركة الأمل للأدوية', '01111111111', '01211111111', 'المنطقة الصناعية، القاهرة', '1', '2026-06-22 10:00:00', 'normal', 1, 5, 'طلب جملة', 1, '2026-06-22 08:00:00', NULL, NULL, '', 0.00, 2500.00),
(29, '20260010', 22, '22', 'مستشفى النور', '01122222222', '01222222222', 'شارع الجامعة، القاهرة', '2', '2026-06-23 10:00:00', 'critical', 1, 8, 'طلب مستشفى', 1, '2026-06-23 08:00:00', NULL, NULL, 'fixed', 500.00, 8000.00),
(30, '20260011', 15, '15', 'خالد عمر فؤاد', '01055555555', '01255555555', 'شارع العروبة، القاهرة', '2', '2026-06-24 10:00:00', 'normal', 3, 1, '', 1, '2026-06-24 08:00:00', 1, '2026-06-24 12:00:00', '', 0.00, 75.00),
(31, '20260012', 26, '26', 'موظف أحمد سامي', '01012121212', '', '29 نوال الدقي', '1', '2026-06-25 10:00:00', 'normal', 4, 1, 'طلب موظف', 1, '2026-06-25 08:00:00', NULL, NULL, '', 0.00, 45.00),
(32, '20260013', 30, '30', 'عميل موقوف 1', '01056565656', '', 'عنوان غير معروف', '1', '2026-06-26 10:00:00', 'normal', 1, 1, '', 1, '2026-06-26 08:00:00', NULL, NULL, '', 0.00, 25.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) DEFAULT 0.00,
  `is_manual` tinyint(1) DEFAULT 0,
  `needs_purchase` tinyint(1) DEFAULT 0,
  `is_manual_product` tinyint(4) DEFAULT 0,
  `manual_product_name` varchar(200) DEFAULT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `supplier_phone` varchar(20) DEFAULT NULL,
  `delivery_time` varchar(50) DEFAULT NULL,
  `purchased_at` datetime DEFAULT NULL,
  `purchased_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_code`, `product_name`, `quantity`, `notes`, `created_at`, `unit_price`, `discount_type`, `discount_value`, `final_price`, `is_manual`, `needs_purchase`, `is_manual_product`, `manual_product_name`, `supplier_name`, `supplier_phone`, `delivery_time`, `purchased_at`, `purchased_by`, `updated_at`) VALUES
(17, 20, NULL, NULL, 'جالفز مت 50/1000', 1, '', '2026-06-16 22:31:17', 210.00, '', 0.00, 210.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 23, NULL, NULL, '123', 2, '', '2026-06-17 00:01:23', 0.00, '', 0.00, 0.00, 0, 0, 0, NULL, NULL, NULL, NULL, '2026-06-17 02:07:16', 1, '2026-06-17 23:08:28'),
(33, 21, NULL, NULL, 'cata', 3, 'lghp/m 1', '2026-06-17 00:30:23', 2000.00, 'percentage', 5.00, 5700.00, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-17 23:08:28'),
(34, 21, NULL, NULL, 'augment', 1, 'ملاحظة 2', '2026-06-17 00:30:23', 70000.00, '', 10.00, 70000.00, 0, 0, 0, NULL, NULL, NULL, NULL, '2026-06-17 02:30:45', 1, '2026-06-17 23:08:28'),
(35, 22, NULL, NULL, 'جالفز مت 50/1000', 2, 'lghp/m 1', '2026-06-17 00:31:43', 36000.00, 'fixed', 10.00, 72000.00, 0, 0, 0, NULL, NULL, NULL, NULL, '2026-06-17 02:36:14', NULL, '2026-06-17 23:08:28'),
(36, 22, NULL, NULL, 'prod', 1, '', '2026-06-17 00:31:43', 50.00, '', 0.00, 50.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-17 23:08:28'),
(41, 25, NULL, NULL, 'str', 1, '', '2026-06-17 16:59:50', 144.00, 'percentage', 10.00, 144.00, 0, 0, 0, NULL, NULL, NULL, NULL, '2026-06-17 19:01:01', NULL, '2026-06-17 23:08:28'),
(42, 24, NULL, NULL, 'galvus me 50/1000', 1, '', '2026-06-17 17:01:21', 144.00, NULL, 0.00, 144.00, 0, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-17 23:08:28'),
(43, 26, 10, '10', 'ميتفورمين 500 مجم 30 قرص', 2, '', '2026-06-20 08:00:00', 45.00, '', 0.00, 90.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(44, 26, 28, '28', 'بروفين 400 مجم 20 قرص', 3, '', '2026-06-20 08:00:00', 25.00, '', 0.00, 75.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(45, 27, 16, '16', 'كونكور 5 مجم 30 قرص', 2, 'طلب عاجل', '2026-06-21 08:00:00', 65.00, 'percentage', 5.00, 123.50, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(46, 27, 17, '17', 'نورفاسك 5 مجم 30 قرص', 2, '', '2026-06-21 08:00:00', 75.00, 'percentage', 5.00, 142.50, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(47, 27, 29, '29', 'بنادول أزرق 500 مجم 24 قرص', 1, '', '2026-06-21 08:00:00', 18.00, '', 0.00, 18.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(48, 28, 10, '10', 'ميتفورمين 500 مجم 30 قرص', 20, 'طلب جملة', '2026-06-22 08:00:00', 38.25, 'percentage', 15.00, 765.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(49, 28, 16, '16', 'كونكور 5 مجم 30 قرص', 15, '', '2026-06-22 08:00:00', 55.25, 'percentage', 15.00, 828.75, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(50, 28, 28, '28', 'بروفين 400 مجم 20 قرص', 30, '', '2026-06-22 08:00:00', 21.25, 'percentage', 15.00, 637.50, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(51, 29, 24, '24', 'أوجمنتين 1جم 14 قرص', 50, 'طلب مستشفى', '2026-06-23 08:00:00', 72.25, 'fixed', 500.00, 3612.50, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(52, 29, 36, '36', 'نكسيوم 40 مجم 14 قرص', 40, '', '2026-06-23 08:00:00', 80.75, 'fixed', 500.00, 3230.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(53, 30, 17, '17', 'نورفاسك 5 مجم 30 قرص', 1, '', '2026-06-24 08:00:00', 75.00, '', 0.00, 75.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(54, 31, 10, '10', 'ميتفورمين 500 مجم 30 قرص', 1, 'طلب موظف', '2026-06-25 08:00:00', 45.00, '', 0.00, 45.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00'),
(55, 32, 28, '28', 'بروفين 400 مجم 20 قرص', 1, '', '2026-06-26 08:00:00', 25.00, '', 0.00, 25.00, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `order_statuses`
--

CREATE TABLE `order_statuses` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `status_color` varchar(20) DEFAULT '#6c757d',
  `sort_order` int(11) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `is_final` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_statuses`
--

INSERT INTO `order_statuses` (`id`, `status_name`, `status_color`, `sort_order`, `is_default`, `is_final`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'تحت البحث', '#ffc107', 1, 1, 0, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16'),
(2, 'جاهز', '#198754', 2, 0, 0, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16'),
(3, 'تم التسليم', '#0d6efd', 3, 0, 1, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16'),
(4, 'إلغي', '#dc3545', 4, 0, 1, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16');

-- --------------------------------------------------------

--
-- Table structure for table `price_adjustments`
--

CREATE TABLE `price_adjustments` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `old_cost_price` decimal(12,2) DEFAULT 0.00,
  `new_cost_price` decimal(12,2) DEFAULT 0.00,
  `old_sell_price` decimal(12,2) DEFAULT 0.00,
  `new_sell_price` decimal(12,2) DEFAULT 0.00,
  `old_profit_margin` decimal(5,2) DEFAULT 0.00,
  `new_profit_margin` decimal(5,2) DEFAULT 0.00,
  `adjustment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `product_name_en` varchar(200) DEFAULT NULL,
  `scientific_name` varchar(200) DEFAULT NULL,
  `product_type_id` int(11) DEFAULT 1,
  `is_service` tinyint(1) DEFAULT 0,
  `hide_in_receipt` tinyint(1) DEFAULT 0,
  `is_shortage` tinyint(1) DEFAULT 0,
  `max_stock` float DEFAULT 0,
  `min_stock` float DEFAULT 0,
  `reorder_point` float DEFAULT 0,
  `category` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `unit2_sell_price` decimal(10,2) DEFAULT 0.00,
  `unit3_sell_price` decimal(10,2) DEFAULT 0.00,
  `has_expire` tinyint(1) DEFAULT 0,
  `is_drug` tinyint(1) DEFAULT 0,
  `can_be_negative` tinyint(1) DEFAULT 0,
  `is_made` tinyint(1) DEFAULT 0,
  `print_barcode` int(11) DEFAULT 0,
  `barcode_type` varchar(20) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `is_imported` tinyint(4) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `print_internal_barcode` tinyint(1) DEFAULT 0,
  `allow_discount` tinyint(1) DEFAULT 1,
  `max_discount` decimal(5,2) DEFAULT 0.00,
  `unit1_id` int(11) DEFAULT NULL,
  `unit2_id` int(11) DEFAULT NULL,
  `unit3_id` int(11) DEFAULT NULL,
  `unit1_to_unit2` decimal(18,0) DEFAULT NULL,
  `unit1_to_unit3` decimal(18,0) DEFAULT NULL,
  `default_sale_unit` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `manual_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `product_name`, `product_name_en`, `scientific_name`, `product_type_id`, `is_service`, `hide_in_receipt`, `is_shortage`, `max_stock`, `min_stock`, `reorder_point`, `category`, `manufacturer`, `category_id`, `company_id`, `sell_price`, `unit2_sell_price`, `unit3_sell_price`, `has_expire`, `is_drug`, `can_be_negative`, `is_made`, `print_barcode`, `barcode_type`, `group_id`, `cost_price`, `is_imported`, `notes`, `print_internal_barcode`, `allow_discount`, `max_discount`, `unit1_id`, `unit2_id`, `unit3_id`, `unit1_to_unit2`, `unit1_to_unit3`, `default_sale_unit`, `is_active`, `created_at`, `updated_at`, `source`, `estock_id`, `manual_code`) VALUES
(1, '1', 'Ozempic', '', '', 1, 0, 0, 0, 0, 0, 0, 'أدوية السكري', 'Novo Nordisk', 0, 0, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, 'طلب متكرر', 0, 1, 0.00, 0, 0, 0, 0, 0, 1, 0, '2026-06-15 16:53:16', '2026-06-22 19:51:16', 'estock', NULL, NULL),
(2, '2', 'Humira', NULL, NULL, 1, 0, 0, 0, 0, 0, 0, 'أدوية المناعة', 'AbbVie', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, NULL, 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-15 16:53:16', '2026-06-22 19:51:16', 'estock', NULL, NULL),
(3, '3', 'Eliquis', NULL, NULL, 1, 0, 0, 0, 0, 0, 0, 'مميعات الدم', 'Bristol Myers Squibb', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, '', 0, 1, 0.00, NULL, NULL, NULL, 1, 1, 1, 1, '2026-06-15 16:53:16', '2026-06-22 19:59:38', 'estock', NULL, 'el'),
(4, '4', 'بيكتول برتقال 24 شريط', 'Pectol Orange 24strips', NULL, 1, 1, 1, 1, 0, 0, 0, NULL, NULL, NULL, NULL, 600.00, 600.00, 25.00, 1, 0, 0, 0, 1, NULL, NULL, 384.00, 0, '', 0, 1, 50.00, NULL, NULL, NULL, 1, 24, 3, 1, '2026-06-20 17:52:05', '2026-06-22 19:51:16', 'manual', NULL, 'M-00004'),
(5, '5', 'اختبار 1', 'test 1', NULL, 1, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 100.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, '', 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-20 21:10:23', '2026-06-22 19:51:16', 'manual', NULL, 'M-00005'),
(6, '6', 'اختبار 2', 'test 2', NULL, 1, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 80.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 60.00, 0, '', 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-20 21:17:18', '2026-06-22 19:51:16', 'manual', NULL, 'M-00006'),
(7, '7', 'اختبار 3', 'TEST 3', 'MELATONIN', 3, 1, 1, 1, 10, 2, 3, NULL, NULL, NULL, NULL, 1000.00, 0.00, 0.00, 1, 1, 1, 1, 0, NULL, NULL, 0.00, 1, '', 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-20 21:25:52', '2026-06-22 19:51:16', 'manual', NULL, 'M-00007'),
(8, '8', 'اسبوسيد اقراص', 'ASPOCID INF', 'acetylsalysilic acid', 1, 1, 1, 1, 0, 0, 0, NULL, NULL, NULL, NULL, 100.00, 100.00, 100.00, 1, 0, 0, 0, 0, NULL, NULL, 37.50, 0, '', 0, 1, 0.00, NULL, NULL, NULL, 1, 1, 1, 1, '2026-06-20 21:27:38', '2026-06-22 19:51:16', 'manual', NULL, 'M-00008'),
(9, '9', 'إنسولين نوفورابيد 100 وحدة', 'Insulin Novorapid 100IU', 'Insulin Aspart', 1, 0, 0, 0, 500, 50, 100, 'أدوية السكري', 'Novo Nordisk', 1, 1, 450.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 320.00, 1, 'يحفظ في الثلاجة', 1, 1, 10.00, 3, 0, 0, 0, 0, 3, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00009'),
(10, '10', 'ميتفورمين 500 مجم 30 قرص', 'Metformin 500mg 30 tabs', 'Metformin HCl', 1, 0, 0, 0, 1000, 100, 200, 'أدوية السكري', 'Merck', 1, 10, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 30.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00010'),
(11, '11', 'جلوكوفاج 1000 مجم 30 قرص', 'Glucophage 1000mg 30 tabs', 'Metformin HCl', 1, 0, 0, 0, 800, 80, 150, 'أدوية السكري', 'Merck', 1, 10, 85.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00011'),
(12, '12', 'أمaryl 2 مجم 30 قرص', 'Amaryl 2mg 30 tabs', 'Glimepiride', 1, 0, 0, 0, 600, 60, 120, 'أدوية السكري', 'Sanofi', 1, 7, 55.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00012'),
(13, '13', 'جانوفيا 100 مجم 30 قرص', 'Januvia 100mg 30 tabs', 'Sitagliptin', 1, 0, 0, 0, 400, 40, 80, 'أدوية السكري', 'Merck', 1, 10, 350.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 250.00, 1, '', 0, 1, 5.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00013'),
(14, '14', 'تريجلكس 5/500 مجم 30 قرص', 'Trajenta Duo 5/500mg', 'Linagliptin/Metformin', 1, 0, 0, 0, 300, 30, 60, 'أدوية السكري', 'Boehringer Ingelheim', 1, 19, 180.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 130.00, 1, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00014'),
(15, '15', 'جالفوس ميت 50/1000 مجم 60 قرص', 'Galvus Met 50/1000mg 60', 'Vildagliptin/Metformin', 1, 0, 0, 0, 500, 50, 100, 'أدوية السكري', 'Novartis', 1, 6, 280.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 200.00, 1, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00015'),
(16, '16', 'كونكور 5 مجم 30 قرص', 'Concor 5mg 30 tabs', 'Bisoprolol', 1, 0, 0, 0, 1000, 100, 200, 'أدوية القلب', 'Merck', 2, 10, 65.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00016'),
(17, '17', 'نورفاسك 5 مجم 30 قرص', 'Norvasc 5mg 30 tabs', 'Amlodipine', 1, 0, 0, 0, 1200, 120, 240, 'أدوية الضغط', 'Pfizer', 3, 4, 75.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 52.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00017'),
(18, '18', 'لازيكس 40 مجم 30 قرص', 'Lasix 40mg 30 tabs', 'Furosemide', 1, 0, 0, 0, 800, 80, 160, 'أدوية القلب', 'Sanofi', 2, 7, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00018'),
(19, '19', 'بلافيكس 75 مجم 30 قرص', 'Plavix 75mg 30 tabs', 'Clopidogrel', 1, 0, 0, 0, 600, 60, 120, 'مميعات الدم', 'Sanofi', 16, 7, 120.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 85.00, 1, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00019'),
(20, '20', 'إليكويس 5 مجم 60 قرص', 'Eliquis 5mg 60 tabs', 'Apixaban', 1, 0, 0, 0, 400, 40, 80, 'مميعات الدم', 'Bristol Myers Squibb', 16, 3, 850.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 620.00, 1, '', 0, 1, 5.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00020'),
(21, '21', 'زاناكس 0.5 مجم 30 قرص', 'Xanax 0.5mg 30 tabs', 'Alprazolam', 1, 0, 0, 0, 300, 30, 60, 'أدوية النفسية', 'Pfizer', 19, 4, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, 'مخدر', 0, 0, 0.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00021'),
(22, '22', 'ليبانتيل 200 مجم 30 قرص', 'Lipanthyl 200mg 30', 'Fenofibrate', 1, 0, 0, 0, 500, 50, 100, 'أدوية الكوليسترول', 'Abbott', 17, 2, 95.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 68.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00022'),
(23, '23', 'سينترويد 50 ميكروجرام 30 قرص', 'Synthroid 50mcg 30', 'Levothyroxine', 1, 0, 0, 0, 700, 70, 140, 'أدوية الغدة الدرقية', 'Abbott', 18, 2, 55.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00023'),
(24, '24', 'أوجمنتين 1جم 14 قرص', 'Augmentin 1g 14 tabs', 'Amoxicillin/Clavulanate', 1, 0, 0, 0, 1000, 100, 200, 'المضادات الحيوية', 'GSK', 4, 9, 85.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00024'),
(25, '25', 'زيثروماكس 500 مجم 3 كبسولات', 'Zithromax 500mg 3 caps', 'Azithromycin', 1, 0, 0, 0, 800, 80, 160, 'المضادات الحيوية', 'Pfizer', 4, 4, 65.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00025'),
(26, '26', 'سيبروفار 500 مجم 10 قرص', 'Ciprofar 500mg 10 tabs', 'Ciprofloxacin', 1, 0, 0, 0, 900, 90, 180, 'المضادات الحيوية', 'Amoun', 4, 26, 40.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 28.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00026'),
(27, '27', 'فلاجيل 500 مجم 20 قرص', 'Flagyl 500mg 20 tabs', 'Metronidazole', 1, 0, 0, 0, 700, 70, 140, 'المضادات الحيوية', 'Sanofi', 4, 7, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00027'),
(28, '28', 'بروفين 400 مجم 20 قرص', 'Brufen 400mg 20 tabs', 'Ibuprofen', 1, 0, 0, 0, 1500, 150, 300, 'أدوية الألم', 'Abbott', 5, 2, 25.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 17.00, 0, '', 0, 1, 25.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00028'),
(29, '29', 'بنادول أزرق 500 مجم 24 قرص', 'Panadol Blue 500mg 24', 'Paracetamol', 1, 0, 0, 0, 2000, 200, 400, 'أدوية الألم', 'GSK', 5, 9, 18.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 12.00, 0, '', 0, 1, 30.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00029'),
(30, '30', 'فولتارين 50 مجم 20 قرص', 'Voltaren 50mg 20 tabs', 'Diclofenac', 1, 0, 0, 0, 1000, 100, 200, 'أدوية الألم', 'Novartis', 5, 6, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00030'),
(31, '31', 'سيليبركس 200 مجم 10 كبسولات', 'Celebrex 200mg 10 caps', 'Celecoxib', 1, 0, 0, 0, 400, 40, 80, 'أدوية الالتهابات', 'Pfizer', 24, 4, 120.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 85.00, 1, '', 0, 1, 10.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00031'),
(32, '32', 'فيتامين د3 1000 وحدة 30 كبسولة', 'Vitamin D3 1000IU 30', 'Cholecalciferol', 1, 0, 0, 0, 800, 80, 160, 'فيتامينات', 'Eva Pharma', 6, 30, 45.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 30.00, 0, '', 0, 1, 20.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00032'),
(33, '33', 'فيتامين سي 500 مجم 20 قرص فوار', 'Vitamin C 500mg 20 eff', 'Ascorbic Acid', 1, 0, 0, 0, 1000, 100, 200, 'فيتامينات', 'Eva Pharma', 6, 30, 25.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 17.00, 0, '', 0, 1, 25.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00033'),
(34, '34', 'أوميغا 3 1000 مجم 30 كبسولة', 'Omega 3 1000mg 30 caps', 'Fish Oil', 1, 0, 0, 0, 600, 60, 120, 'مكملات غذائية', 'Eva Pharma', 25, 30, 85.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00034'),
(35, '35', 'كالسيوم + د3 30 قرص', 'Calcium + D3 30 tabs', 'Calcium Carbonate', 1, 0, 0, 0, 700, 70, 140, 'مكملات غذائية', 'Eva Pharma', 25, 30, 55.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00035'),
(36, '36', 'نكسيوم 40 مجم 14 قرص', 'Nexium 40mg 14 tabs', 'Esomeprazole', 1, 0, 0, 0, 800, 80, 160, 'أدوية الجهاز الهضمي', 'AstraZeneca', 10, 8, 95.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 68.00, 1, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00036'),
(37, '37', 'موتيليوم 10 مجم 30 قرص', 'Motilium 10mg 30 tabs', 'Domperidone', 1, 0, 0, 0, 900, 90, 180, 'أدوية الجهاز الهضمي', 'Janssen', 10, 11, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00037'),
(38, '38', 'لوبروزاك 20 مجم 14 كبسولة', 'Loprazol 20mg 14 caps', 'Omeprazole', 1, 0, 0, 0, 1000, 100, 200, 'أدوية الجهاز الهضمي', 'Eva Pharma', 10, 30, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 25.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00038'),
(39, '39', 'سينتروفيل 4 مجم 20 قرص', 'Singulair 10mg 20 tabs', 'Montelukast', 1, 0, 0, 0, 500, 50, 100, 'أدوية الجهاز التنفسي', 'Merck', 11, 10, 85.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00039'),
(40, '40', 'فنتولين بخاخ 100 ميكروجرام', 'Ventolin Inhaler 100mcg', 'Salbutamol', 1, 0, 0, 0, 600, 60, 120, 'أدوية الجهاز التنفسي', 'GSK', 11, 9, 65.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 15, 0, 0, 0, 0, 15, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00040'),
(41, '41', 'سيريتايد 250 بخاخ', 'Seretide 250 Inhaler', 'Fluticasone/Salmeterol', 1, 0, 0, 0, 400, 40, 80, 'أدوية الجهاز التنفسي', 'GSK', 11, 9, 180.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 130.00, 1, '', 0, 1, 10.00, 15, 0, 0, 0, 0, 15, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00041'),
(42, '42', 'ديفرين 0.1% جل 30 جم', 'Differin 0.1% Gel 30g', 'Adapalene', 1, 0, 0, 0, 300, 30, 60, 'أدوية الجلد', 'Galderma', 12, 14, 95.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 68.00, 1, '', 0, 1, 15.00, 20, 0, 0, 0, 0, 20, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00042'),
(43, '43', 'بيتادين مطهر 30 مل', 'Betadine Antiseptic 30ml', 'Povidone Iodine', 1, 0, 0, 0, 800, 80, 160, 'أدوية الجلد', 'Meda', 12, 15, 35.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00043'),
(44, '44', 'توبريكان قطرة عين 5 مل', 'Tobrex Eye Drops 5ml', 'Tobramycin', 1, 0, 0, 0, 500, 50, 100, 'أدوية العيون', 'Alcon', 13, 16, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 15.00, 14, 0, 0, 0, 0, 14, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00044'),
(45, '45', 'أوتريفين بخاخ أنف 10 مل', 'Otrivin Nasal Spray 10ml', 'Xylometazoline', 1, 0, 0, 0, 600, 60, 120, 'أدوية الجهاز التنفسي', 'Novartis', 11, 6, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 15, 0, 0, 0, 0, 15, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00045'),
(46, '46', 'سيروماكس قطرة أذن 10 مل', 'Syramax Ear Drops 10ml', 'Ciprofloxacin', 1, 0, 0, 0, 400, 40, 80, 'أدوية الأذن', 'Eva Pharma', 14, 30, 28.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 19.00, 0, '', 0, 1, 15.00, 14, 0, 0, 0, 0, 14, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00046'),
(47, '47', 'إيمو 1000 مجم 3 أمبولات', 'Imo 1000mg 3 amps', 'Immunoglobulin', 1, 0, 0, 0, 200, 20, 40, 'أدوية المناعة', 'Eva Pharma', 15, 30, 250.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 180.00, 0, 'يحفظ في الثلاجة', 1, 1, 5.00, 3, 0, 0, 0, 0, 3, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00047'),
(48, '48', 'أكتيمرا 400 مجم 1 أمبول', 'Actemra 400mg 1 vial', 'Tocilizumab', 1, 0, 0, 0, 100, 10, 20, 'أدوية المناعة', 'Roche', 15, 5, 3500.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 2800.00, 1, 'يحفظ في الثلاجة', 1, 0, 0.00, 4, 0, 0, 0, 0, 4, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00048'),
(49, '49', 'فوساماكس 70 مجم 4 أقراص', 'Fosamax 70mg 4 tabs', 'Alendronate', 1, 0, 0, 0, 400, 40, 80, 'أدوية العظام', 'Merck', 20, 10, 120.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 85.00, 0, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00049'),
(50, '50', 'تاموكسيفين 20 مجم 30 قرص', 'Tamoxifen 20mg 30 tabs', 'Tamoxifen Citrate', 1, 0, 0, 0, 200, 20, 40, 'أدوية السرطان', 'AstraZeneca', 21, 8, 85.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00050'),
(51, '51', 'أتيرا 5 مجم 28 قرص', 'Atra 5mg 28 tabs', 'Arsenic Trioxide', 1, 0, 0, 0, 150, 15, 30, 'أدوية السرطان', 'Teva', 21, 16, 450.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 320.00, 1, '', 0, 1, 5.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00051'),
(52, '52', 'تاميفلو 75 مجم 10 كبسولات', 'Tamiflu 75mg 10 caps', 'Oseltamivir', 1, 0, 0, 0, 300, 30, 60, 'أدوية الفيروسات', 'Roche', 22, 5, 280.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 200.00, 1, '', 0, 1, 10.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00052'),
(53, '53', 'ديفرلوكان 150 مجم 1 كبسولة', 'Diflucan 150mg 1 cap', 'Fluconazole', 1, 0, 0, 0, 500, 50, 100, 'أدوية الفطريات', 'Pfizer', 23, 4, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 15.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00053'),
(54, '54', 'لاميزيل كريم 15 جم', 'Lamisil Cream 15g', 'Terbinafine', 1, 0, 0, 0, 600, 60, 120, 'أدوية الفطريات', 'Novartis', 23, 6, 55.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 15.00, 12, 0, 0, 0, 0, 12, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00054'),
(55, '55', 'ألبرازولام 0.5 مجم 30 قرص', 'Alprazolam 0.5mg 30', 'Alprazolam', 1, 0, 0, 0, 300, 30, 60, 'أدوية النفسية', 'Teva', 19, 16, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, 'مخدر', 0, 0, 0.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00055'),
(56, '56', 'سيرترالين 50 مجم 30 قرص', 'Sertraline 50mg 30', 'Sertraline', 1, 0, 0, 0, 400, 40, 80, 'أدوية النفسية', 'Pfizer', 19, 4, 75.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 52.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00056'),
(57, '57', 'ريتالين 10 مجم 30 قرص', 'Ritalin 10mg 30 tabs', 'Methylphenidate', 1, 0, 0, 0, 200, 20, 40, 'أدوية النفسية', 'Novartis', 19, 6, 95.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 68.00, 0, 'مخدر', 0, 0, 0.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00057'),
(58, '58', 'ليريكا 75 مجم 14 كبسولة', 'Lyrica 75mg 14 caps', 'Pregabalin', 1, 0, 0, 0, 500, 50, 100, 'أدوية النفسية', 'Pfizer', 19, 4, 120.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 85.00, 0, '', 0, 1, 10.00, 2, 0, 0, 0, 0, 2, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00058'),
(59, '59', 'زولفت 50 مجم 14 قرص', 'Zoloft 50mg 14 tabs', 'Sertraline', 1, 0, 0, 0, 400, 40, 80, 'أدوية النفسية', 'Pfizer', 19, 4, 65.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00059'),
(60, '60', 'إفكسور 75 مجم 14 قرص', 'Effexor 75mg 14 tabs', 'Venlafaxine', 1, 0, 0, 0, 300, 30, 60, 'أدوية النفسية', 'Pfizer', 19, 4, 85.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00060'),
(61, '61', 'جهاز قياس سكر إلكتروني', 'Blood Glucose Monitor', NULL, 2, 0, 0, 0, 100, 10, 20, 'أجهزة قياس', 'Accu-Chek', 28, 1, 450.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 320.00, 1, '', 0, 1, 10.00, 7, 0, 0, 0, 0, 7, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00061'),
(62, '62', 'شرائط قياس سكر 50 شريط', 'Test Strips 50 pcs', NULL, 2, 0, 0, 0, 200, 20, 40, 'أجهزة قياس', 'Accu-Chek', 28, 1, 180.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 130.00, 1, '', 0, 1, 10.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00062'),
(63, '63', 'جهاز قياس ضغط إلكتروني', 'Blood Pressure Monitor', NULL, 2, 0, 0, 0, 80, 8, 16, 'أجهزة قياس', 'Omron', 28, 20, 650.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 480.00, 1, '', 0, 1, 10.00, 7, 0, 0, 0, 0, 7, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00063'),
(64, '64', 'ميزان حرارة إلكتروني', 'Digital Thermometer', NULL, 2, 0, 0, 0, 150, 15, 30, 'أجهزة قياس', 'Omron', 28, 20, 85.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 60.00, 1, '', 0, 1, 15.00, 7, 0, 0, 0, 0, 7, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00064'),
(65, '65', 'كرسي متحرك', 'Wheelchair', NULL, 2, 0, 0, 0, 20, 2, 4, 'مستلزمات طبية', 'Medline', 8, 21, 2500.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 1800.00, 1, '', 0, 1, 5.00, 7, 0, 0, 0, 0, 7, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00065'),
(66, '66', 'عكاز طبي', 'Medical Crutch', NULL, 2, 0, 0, 0, 50, 5, 10, 'مستلزمات طبية', 'Medline', 8, 21, 180.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 130.00, 1, '', 0, 1, 15.00, 7, 0, 0, 0, 0, 7, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00066'),
(67, '67', 'ضمادة لاصقة 10 سم', 'Adhesive Bandage 10cm', NULL, 2, 0, 0, 0, 500, 50, 100, 'مستلزمات طبية', '3M', 8, 11, 25.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 17.00, 0, '', 0, 1, 25.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00067'),
(68, '68', 'قفازات طبية 100 قطعة', 'Medical Gloves 100 pcs', NULL, 2, 0, 0, 0, 300, 30, 60, 'مستلزمات طبية', 'Medline', 8, 21, 85.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00068'),
(69, '69', 'كمامة طبية 50 قطعة', 'Medical Mask 50 pcs', NULL, 2, 0, 0, 0, 1000, 100, 200, 'مستلزمات طبية', '3M', 8, 11, 45.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00069'),
(70, '70', 'شراب فيتامين سي للأطفال', 'Vitamin C Syrup Children', NULL, 2, 0, 0, 0, 400, 40, 80, 'أدوية الأطفال', 'Eva Pharma', 9, 30, 35.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00070'),
(71, '71', 'ورق طباعة فواتير A4', 'Invoice Paper A4', NULL, 3, 0, 0, 0, 500, 50, 100, 'ورقيات', 'Local', 30, 22, 45.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00071'),
(72, '72', 'ورق ملصقات باركود', 'Barcode Labels', NULL, 3, 0, 0, 0, 300, 30, 60, 'ورقيات', 'Local', 30, 22, 65.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00072'),
(73, '73', 'أكياس بلاستيك صيدلية', 'Pharmacy Plastic Bags', NULL, 3, 0, 0, 0, 2000, 200, 400, 'ورقيات', 'Local', 30, 22, 15.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 10.00, 0, '', 0, 1, 30.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00073'),
(74, '74', 'محلول ملحي 500 مل', 'Saline Solution 500ml', NULL, 4, 0, 0, 0, 600, 60, 120, 'مستلزمات طبية', 'Eva Pharma', 8, 30, 25.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 17.00, 0, '', 0, 1, 25.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00074'),
(75, '75', 'محلول جلوكوز 5% 500 مل', 'Glucose 5% 500ml', NULL, 4, 0, 0, 0, 400, 40, 80, 'مستلزمات طبية', 'Eva Pharma', 8, 30, 35.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00075'),
(76, '76', 'سرنجة 5 مل 100 قطعة', 'Syringe 5ml 100 pcs', NULL, 4, 0, 0, 0, 500, 50, 100, 'مستلزمات طبية', 'BD', 8, 11, 85.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00076'),
(77, '77', 'إبر تفريغ 23G 100 قطعة', 'Needles 23G 100 pcs', NULL, 4, 0, 0, 0, 400, 40, 80, 'مستلزمات طبية', 'BD', 8, 11, 45.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00077'),
(78, '78', 'كانيولا وريدية 100 قطعة', 'IV Cannula 100 pcs', NULL, 4, 0, 0, 0, 300, 30, 60, 'مستلزمات طبية', 'BD', 8, 11, 120.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 85.00, 0, '', 0, 1, 15.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00078'),
(79, '79', 'شاش طبي 100 قطعة', 'Medical Gauze 100 pcs', NULL, 4, 0, 0, 0, 600, 60, 120, 'مستلزمات طبية', 'Eva Pharma', 8, 30, 55.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 20.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00079'),
(80, '80', 'لاصق جراحي 2.5 سم', 'Surgical Tape 2.5cm', NULL, 4, 0, 0, 0, 400, 40, 80, 'مستلزمات طبية', '3M', 8, 11, 35.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00080'),
(81, '81', 'أملور 5 مجم 30 قرص', 'Amlor 5mg 30 tabs', 'Amlodipine', 1, 0, 0, 0, 1000, 100, 200, 'أدوية الضغط', 'Pfizer', 3, 4, 65.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00081'),
(82, '82', 'كابوتن 25 مجم 20 قرص', 'Capoten 25mg 20 tabs', 'Captopril', 1, 0, 0, 0, 800, 80, 160, 'أدوية الضغط', 'BMS', 3, 3, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00082'),
(83, '83', 'زيستوريل 10 مجم 20 قرص', 'Zestril 10mg 20 tabs', 'Lisinopril', 1, 0, 0, 0, 700, 70, 140, 'أدوية الضغط', 'AstraZeneca', 3, 8, 55.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00083'),
(84, '84', 'ديوفان 160 مجم 14 قرص', 'Diovan 160mg 14 tabs', 'Valsartan', 1, 0, 0, 0, 600, 60, 120, 'أدوية الضغط', 'Novartis', 3, 6, 120.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 85.00, 1, '', 0, 1, 10.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00084'),
(85, '85', 'كوزار 50 مجم 30 قرص', 'Cozaar 50mg 30 tabs', 'Losartan', 1, 0, 0, 0, 800, 80, 160, 'أدوية الضغط', 'Merck', 3, 10, 85.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 60.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00085'),
(86, '86', 'تينورمين 100 مجم 14 قرص', 'Tenormin 100mg 14 tabs', 'Atenolol', 1, 0, 0, 0, 700, 70, 140, 'أدوية القلب', 'AstraZeneca', 2, 8, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00086'),
(87, '87', 'لانوكسين 0.25 مجم 30 قرص', 'Lanoxin 0.25mg 30 tabs', 'Digoxin', 1, 0, 0, 0, 400, 40, 80, 'أدوية القلب', 'GSK', 2, 9, 35.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00087'),
(88, '88', 'كوردارون 200 مجم 30 قرص', 'Cordarone 200mg 30 tabs', 'Amiodarone', 1, 0, 0, 0, 300, 30, 60, 'أدوية القلب', 'Sanofi', 2, 7, 95.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 68.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00088'),
(89, '89', 'إندرال 10 مجم 50 قرص', 'Inderal 10mg 50 tabs', 'Propranolol', 1, 0, 0, 0, 600, 60, 120, 'أدوية القلب', 'AstraZeneca', 2, 8, 45.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00089'),
(90, '90', 'أدالا 30 مجم 30 قرص', 'Adalat 30mg 30 tabs', 'Nifedipine', 1, 0, 0, 0, 800, 80, 160, 'أدوية الضغط', 'Bayer', 3, 14, 55.00, 0.00, 0.00, 1, 1, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 15.00, 1, 0, 0, 0, 0, 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00090'),
(91, '91', 'كريم مرطب نيفيا 200 مل', 'Nivea Cream 200ml', NULL, 2, 0, 0, 0, 200, 20, 40, 'مستحضرات تجميل', 'Nivea', 7, 14, 85.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 60.00, 1, '', 0, 1, 15.00, 6, 0, 0, 0, 0, 6, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00091'),
(92, '92', 'شامبو بانتين 400 مل', 'Pantene Shampoo 400ml', NULL, 2, 0, 0, 0, 150, 15, 30, 'منتجات العناية الشخصية', 'P&G', 26, 11, 95.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 68.00, 1, '', 0, 1, 15.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00092'),
(93, '93', 'معجون أسنان كولجيت 100 جم', 'Colgate Toothpaste 100g', NULL, 2, 0, 0, 0, 300, 30, 60, 'منتجات العناية الشخصية', 'Colgate', 26, 11, 35.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 6, 0, 0, 0, 0, 6, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00093'),
(94, '94', 'صابون ديتول 100 جم', 'Dettol Soap 100g', NULL, 2, 0, 0, 0, 400, 40, 80, 'منتجات العناية الشخصية', 'Reckitt', 26, 11, 25.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 17.00, 0, '', 0, 1, 25.00, 6, 0, 0, 0, 0, 6, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00094'),
(95, '95', 'حفاضات أطفال بامبرز 60 قطعة', 'Pampers Diapers 60 pcs', NULL, 2, 0, 0, 0, 100, 10, 20, 'منتجات الأم والطفل', 'P&G', 27, 11, 250.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 180.00, 1, '', 0, 1, 10.00, 9, 0, 0, 0, 0, 9, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00095'),
(96, '96', 'زجاجة رضاعة 250 مل', 'Baby Bottle 250ml', NULL, 2, 0, 0, 0, 80, 8, 16, 'منتجات الأم والطفل', 'Philips', 27, 11, 120.00, 0.00, 0.00, 0, 0, 0, 0, 1, 'EAN13', NULL, 85.00, 1, '', 0, 1, 15.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00096'),
(97, '97', 'كريم حفاضات 50 جم', 'Diaper Cream 50g', NULL, 2, 0, 0, 0, 150, 15, 30, 'منتجات الأم والطفل', 'Johnson', 27, 11, 45.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 32.00, 0, '', 0, 1, 20.00, 6, 0, 0, 0, 0, 6, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00097'),
(98, '98', 'منظف جراحي 500 مل', 'Surgical Cleaner 500ml', NULL, 4, 0, 0, 0, 200, 20, 40, 'مواد تعقيم', 'Eva Pharma', 30, 30, 65.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 45.00, 0, '', 0, 1, 15.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00098'),
(99, '99', 'كحول إيثيلي 70% 500 مل', 'Ethanol 70% 500ml', NULL, 4, 0, 0, 0, 300, 30, 60, 'مواد تعقيم', 'Eva Pharma', 30, 30, 35.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 24.00, 0, '', 0, 1, 20.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00099'),
(100, '100', 'مطهر بيتادين 100 مل', 'Betadine 100ml', NULL, 4, 0, 0, 0, 400, 40, 80, 'مواد تعقيم', 'Meda', 30, 15, 55.00, 0.00, 0.00, 1, 0, 0, 0, 1, 'EAN13', NULL, 38.00, 0, '', 0, 1, 15.00, 5, 0, 0, 0, 0, 5, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 'manual', NULL, 'M-00100');

-- --------------------------------------------------------

--
-- Table structure for table `product_alerts`
--

CREATE TABLE `product_alerts` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `alert_type` enum('HIGH_ALERT','TOXIC','SOUNDALIKE','LOOKALIKE','CONTRAINDICATION','PREGNANCY','OTHER') NOT NULL,
  `alert_message` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_alerts`
--

INSERT INTO `product_alerts` (`id`, `product_id`, `alert_type`, `alert_message`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 0, 'CONTRAINDICATION', 'CONTRAINDICATION - موانع استخدام', 1, '2026-06-20 17:52:05', '2026-06-20 17:52:05'),
(2, 7, 'HIGH_ALERT', 'HIGH ALERT - يتطلب انتباه خاص', 1, '2026-06-20 21:25:52', '2026-06-20 21:25:52'),
(3, 7, 'PREGNANCY', 'PREGNANCY - حذر خلال الحمل', 1, '2026-06-20 21:25:52', '2026-06-20 21:25:52'),
(4, 21, 'HIGH_ALERT', 'HIGH ALERT - مخدر', 1, '2026-06-20 08:00:00', '2026-06-20 08:00:00'),
(5, 55, 'HIGH_ALERT', 'HIGH ALERT - مخدر', 1, '2026-06-20 08:00:00', '2026-06-20 08:00:00'),
(6, 57, 'HIGH_ALERT', 'HIGH ALERT - مخدر', 1, '2026-06-20 08:00:00', '2026-06-20 08:00:00'),
(7, 48, 'CONTRAINDICATION', 'CONTRAINDICATION - يحفظ في الثلاجة', 1, '2026-06-20 08:00:00', '2026-06-20 08:00:00'),
(8, 9, 'CONTRAINDICATION', 'CONTRAINDICATION - يحفظ في الثلاجة', 1, '2026-06-20 08:00:00', '2026-06-20 08:00:00'),
(9, 47, 'CONTRAINDICATION', 'CONTRAINDICATION - يحفظ في الثلاجة', 1, '2026-06-20 08:00:00', '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_barcodes`
--

CREATE TABLE `product_barcodes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `unit_id` int(11) DEFAULT 1,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_barcodes`
--

INSERT INTO `product_barcodes` (`id`, `product_id`, `barcode`, `unit_id`, `is_primary`, `created_at`) VALUES
(1, 0, '8411500101425', 1, 1, '2026-06-20 17:52:05'),
(2, 0, '84115713', 3, 0, '2026-06-20 17:52:05'),
(3, 0, 'sads1231564asdk', 1, 0, '2026-06-20 17:52:05'),
(4, 6, '12345679812564', 1, 1, '2026-06-20 21:17:18'),
(5, 6, '123564897412136', 2, 0, '2026-06-20 21:17:18'),
(6, 6, '879452131654897', 1, 0, '2026-06-20 21:17:18'),
(7, 6, 'hr123456', 1, 0, '2026-06-20 21:17:18'),
(8, 7, '55555555555555', 1, 1, '2026-06-20 21:25:52'),
(9, 10, '6223000010001', 1, 1, '2026-06-20 08:00:00'),
(10, 10, '6223000010002', 7, 0, '2026-06-20 08:00:00'),
(11, 11, '6223000020001', 1, 1, '2026-06-20 08:00:00'),
(12, 16, '6223000160001', 1, 1, '2026-06-20 08:00:00'),
(13, 17, '6223000170001', 1, 1, '2026-06-20 08:00:00'),
(14, 24, '6223000240001', 1, 1, '2026-06-20 08:00:00'),
(15, 28, '6223000280001', 1, 1, '2026-06-20 08:00:00'),
(16, 29, '6223000290001', 1, 1, '2026-06-20 08:00:00'),
(17, 36, '6223000360001', 1, 1, '2026-06-20 08:00:00'),
(18, 40, '6223000400001', 15, 1, '2026-06-20 08:00:00'),
(19, 61, '6223000610001', 7, 1, '2026-06-20 08:00:00'),
(20, 65, '6223000650001', 7, 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `category_name_ar` varchar(100) NOT NULL,
  `category_name_en` varchar(100) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `category_name_ar`, `category_name_en`, `is_active`, `created_at`) VALUES
(1, 'أدوية السكري', 'Diabetes Medicines', 1, '2026-06-20 08:00:00'),
(2, 'أدوية القلب', 'Heart Medicines', 1, '2026-06-20 08:00:00'),
(3, 'أدوية الضغط', 'Blood Pressure Medicines', 1, '2026-06-20 08:00:00'),
(4, 'المضادات الحيوية', 'Antibiotics', 1, '2026-06-20 08:00:00'),
(5, 'أدوية الألم', 'Pain Relief', 1, '2026-06-20 08:00:00'),
(6, 'فيتامينات', 'Vitamins', 1, '2026-06-20 08:00:00'),
(7, 'مستحضرات تجميل', 'Cosmetics', 1, '2026-06-20 08:00:00'),
(8, 'مستلزمات طبية', 'Medical Supplies', 1, '2026-06-20 08:00:00'),
(9, 'أدوية الأطفال', 'Children Medicines', 1, '2026-06-20 08:00:00'),
(10, 'أدوية الجهاز الهضمي', 'Digestive Medicines', 1, '2026-06-20 08:00:00'),
(11, 'أدوية الجهاز التنفسي', 'Respiratory Medicines', 1, '2026-06-20 08:00:00'),
(12, 'أدوية الجلد', 'Skin Medicines', 1, '2026-06-20 08:00:00'),
(13, 'أدوية العيون', 'Eye Medicines', 1, '2026-06-20 08:00:00'),
(14, 'أدوية الأذن', 'Ear Medicines', 1, '2026-06-20 08:00:00'),
(15, 'أدوية المناعة', 'Immunity Medicines', 1, '2026-06-20 08:00:00'),
(16, 'مميعات الدم', 'Blood Thinners', 1, '2026-06-20 08:00:00'),
(17, 'أدوية الكوليسترول', 'Cholesterol Medicines', 1, '2026-06-20 08:00:00'),
(18, 'أدوية الغدة الدرقية', 'Thyroid Medicines', 1, '2026-06-20 08:00:00'),
(19, 'أدوية النفسية', 'Psychiatric Medicines', 1, '2026-06-20 08:00:00'),
(20, 'أدوية العظام', 'Bone Medicines', 1, '2026-06-20 08:00:00'),
(21, 'أدوية السرطان', 'Cancer Medicines', 1, '2026-06-20 08:00:00'),
(22, 'أدوية الفيروسات', 'Antiviral Medicines', 1, '2026-06-20 08:00:00'),
(23, 'أدوية الفطريات', 'Antifungal Medicines', 1, '2026-06-20 08:00:00'),
(24, 'أدوية الالتهابات', 'Anti-inflammatory', 1, '2026-06-20 08:00:00'),
(25, 'مكملات غذائية', 'Supplements', 1, '2026-06-20 08:00:00'),
(26, 'منتجات العناية الشخصية', 'Personal Care', 1, '2026-06-20 08:00:00'),
(27, 'منتجات الأم والطفل', 'Mother & Baby', 1, '2026-06-20 08:00:00'),
(28, 'أجهزة قياس', 'Measuring Devices', 1, '2026-06-20 08:00:00'),
(29, 'أدوات جراحية', 'Surgical Tools', 1, '2026-06-20 08:00:00'),
(30, 'مواد تعقيم', 'Sterilization Materials', 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_companies`
--

CREATE TABLE `product_companies` (
  `id` int(11) NOT NULL,
  `company_name_ar` varchar(100) NOT NULL,
  `company_name_en` varchar(100) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_companies`
--

INSERT INTO `product_companies` (`id`, `company_name_ar`, `company_name_en`, `is_active`, `created_at`) VALUES
(1, 'نوفو نورديسك', 'Novo Nordisk', 1, '2026-06-20 08:00:00'),
(2, 'أبفى', 'AbbVie', 1, '2026-06-20 08:00:00'),
(3, 'بريستول مايرز سكويب', 'Bristol Myers Squibb', 1, '2026-06-20 08:00:00'),
(4, 'فايزر', 'Pfizer', 1, '2026-06-20 08:00:00'),
(5, 'روش', 'Roche', 1, '2026-06-20 08:00:00'),
(6, 'نوفارتس', 'Novartis', 1, '2026-06-20 08:00:00'),
(7, 'سانوفي', 'Sanofi', 1, '2026-06-20 08:00:00'),
(8, 'أسترازينيكا', 'AstraZeneca', 1, '2026-06-20 08:00:00'),
(9, 'جلاكسو سميث كلاين', 'GSK', 1, '2026-06-20 08:00:00'),
(10, 'مرك', 'Merck', 1, '2026-06-20 08:00:00'),
(11, 'جونسون آند جونسون', 'Johnson & Johnson', 1, '2026-06-20 08:00:00'),
(12, 'إيلي ليلي', 'Eli Lilly', 1, '2026-06-20 08:00:00'),
(13, 'أميجن', 'Amgen', 1, '2026-06-20 08:00:00'),
(14, 'باير', 'Bayer', 1, '2026-06-20 08:00:00'),
(15, 'سانوفي باستور', 'Sanofi Pasteur', 1, '2026-06-20 08:00:00'),
(16, 'تيكا فارما', 'Teva Pharma', 1, '2026-06-20 08:00:00'),
(17, 'Mylan', 'Mylan', 1, '2026-06-20 08:00:00'),
(18, 'سيرفير', 'Servier', 1, '2026-06-20 08:00:00'),
(19, 'بوهرنجر إنجلهايم', 'Boehringer Ingelheim', 1, '2026-06-20 08:00:00'),
(20, 'أوفيتو', 'Aveto', 1, '2026-06-20 08:00:00'),
(21, 'مينافارما', 'Minapharma', 1, '2026-06-20 08:00:00'),
(22, 'القاهرة للأدوية', 'Cairo Pharma', 1, '2026-06-20 08:00:00'),
(23, 'النصر للأدوية', 'El Nasr Pharma', 1, '2026-06-20 08:00:00'),
(24, 'الشرقية للأدوية', 'Eastern Pharma', 1, '2026-06-20 08:00:00'),
(25, 'المهن للأدوية', 'El Mohandes Pharma', 1, '2026-06-20 08:00:00'),
(26, 'أمون', 'Amoun', 1, '2026-06-20 08:00:00'),
(27, 'سبيمو', 'Spimaco', 1, '2026-06-20 08:00:00'),
(28, 'العربية للأدوية', 'Arab Pharma', 1, '2026-06-20 08:00:00'),
(29, 'فاركو', 'Pharco', 1, '2026-06-20 08:00:00'),
(30, 'إيفا فارما', 'Eva Pharma', 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_locations`
--

CREATE TABLE `product_locations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `location_code` varchar(50) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `shelf_number` varchar(20) DEFAULT NULL,
  `row_number` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_movements`
--

CREATE TABLE `product_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `movement_type` enum('opening_balance','purchase','sale','transfer_in','transfer_out','adjustment','return','damage') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `batch_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_price_history`
--

CREATE TABLE `product_price_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `old_sell_price` decimal(10,2) DEFAULT NULL,
  `new_sell_price` decimal(10,2) NOT NULL,
  `old_cost_price` decimal(10,2) DEFAULT NULL,
  `new_cost_price` decimal(10,2) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_price_history`
--

INSERT INTO `product_price_history` (`id`, `product_id`, `old_sell_price`, `new_sell_price`, `old_cost_price`, `new_cost_price`, `changed_by`, `change_reason`, `created_at`) VALUES
(1, 10, 40.00, 45.00, 25.00, 30.00, 1, 'زيادة التكلفة', '2026-06-15 08:00:00'),
(2, 11, 75.00, 85.00, 50.00, 55.00, 1, 'زيادة التكلفة', '2026-06-15 08:00:00'),
(3, 16, 55.00, 65.00, 35.00, 42.00, 1, 'زيادة التكلفة', '2026-06-15 08:00:00'),
(4, 17, 65.00, 75.00, 40.00, 48.00, 1, 'زيادة التكلفة', '2026-06-15 08:00:00'),
(5, 28, 20.00, 25.00, 14.00, 16.00, 1, 'زيادة التكلفة', '2026-06-15 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_types`
--

CREATE TABLE `product_types` (
  `id` int(11) NOT NULL,
  `type_code` varchar(20) NOT NULL,
  `type_name_ar` varchar(50) NOT NULL,
  `type_name_en` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_types`
--

INSERT INTO `product_types` (`id`, `type_code`, `type_name_ar`, `type_name_en`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'drug', 'دواء', 'Drug', 1, 1, '2026-06-17 18:39:29'),
(2, 'accessory', 'أكسسوار', 'Accessory', 2, 1, '2026-06-17 18:39:29'),
(3, 'paper', 'ورقيات', 'Paper', 3, 1, '2026-06-17 18:39:29'),
(4, 'medical_supply', 'مستلزمات طبية', 'Medical Supply', 4, 1, '2026-06-17 18:39:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_units`
--

CREATE TABLE `product_units` (
  `id` int(11) NOT NULL,
  `unit_code` varchar(50) DEFAULT NULL,
  `unit_name_ar` varchar(50) NOT NULL,
  `unit_name_en` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_units`
--

INSERT INTO `product_units` (`id`, `unit_code`, `unit_name_ar`, `unit_name_en`, `is_active`, `source`, `estock_id`, `created_at`) VALUES
(1, 'TAB', 'قرص', 'Tablet', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(2, 'CAP', 'كبسولة', 'Capsule', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(3, 'AMP', 'أمبول', 'Ampoule', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(4, 'VIAL', 'فيال', 'Vial', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(5, 'BOTTLE', 'زجاجة', 'Bottle', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(6, 'TUBE', 'أنبوبة', 'Tube', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(7, 'BOX', 'علبة', 'Box', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(8, 'STRIP', 'شريط', 'Strip', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(9, 'PACK', 'عبوة', 'Pack', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(10, 'SACHET', 'كيس', 'Sachet', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(11, 'SYRUP', 'شراب', 'Syrup', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(12, 'CREAM', 'كريم', 'Cream', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(13, 'OINT', 'مرهم', 'Ointment', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(14, 'DROP', 'قطرة', 'Drops', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(15, 'INHALER', 'بخاخ', 'Inhaler', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(16, 'PEN', 'قلم', 'Pen', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(17, 'PATCH', 'لاصقة', 'Patch', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(18, 'SUPPO', 'تحميلة', 'Suppository', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(19, 'SUSP', 'معالق', 'Suspension', 1, 'manual', NULL, '2026-06-20 08:00:00'),
(20, 'GEL', 'جل', 'Gel', 1, 'manual', NULL, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `purchased_items`
--

CREATE TABLE `purchased_items` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_price` decimal(10,2) NOT NULL,
  `sell_price` decimal(10,2) NOT NULL,
  `profit_margin` decimal(5,2) DEFAULT 0.00,
  `quantity` int(11) DEFAULT 1,
  `total_cost` decimal(10,2) NOT NULL,
  `total_sell` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `purchased_by` int(11) DEFAULT NULL,
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchased_items`
--

INSERT INTO `purchased_items` (`id`, `order_item_id`, `supplier_id`, `supplier_price`, `sell_price`, `profit_margin`, `quantity`, `total_cost`, `total_sell`, `profit`, `notes`, `purchased_by`, `purchased_at`) VALUES
(1, 35, 4, 30000.00, 36000.00, 20.00, 2, 60000.00, 72000.00, 12000.00, '', 1, '2026-06-17 00:36:14'),
(4, 41, 5, 120.00, 144.00, 20.00, 1, 120.00, 144.00, 24.00, '', 1, '2026-06-17 17:01:01'),
(5, 43, 1, 30.00, 45.00, 50.00, 2, 60.00, 90.00, 30.00, '', 1, '2026-06-20 08:00:00'),
(6, 44, 2, 16.00, 25.00, 56.25, 3, 48.00, 75.00, 27.00, '', 1, '2026-06-20 08:00:00'),
(7, 45, 2, 42.00, 65.00, 54.76, 2, 84.00, 130.00, 46.00, '', 1, '2026-06-21 08:00:00'),
(8, 48, 1, 30.00, 38.25, 27.50, 20, 600.00, 765.00, 165.00, 'خصم جملة', 1, '2026-06-22 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `shift_handovers`
--

CREATE TABLE `shift_handovers` (
  `id` int(11) NOT NULL,
  `shift_date` date DEFAULT curdate(),
  `from_user` int(11) NOT NULL,
  `to_user` int(11) NOT NULL,
  `open_orders_count` int(11) DEFAULT 0,
  `urgent_orders_count` int(11) DEFAULT 0,
  `critical_notes` text DEFAULT NULL,
  `general_notes` text DEFAULT NULL,
  `is_acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shift_handovers`
--

INSERT INTO `shift_handovers` (`id`, `shift_date`, `from_user`, `to_user`, `open_orders_count`, `urgent_orders_count`, `critical_notes`, `general_notes`, `is_acknowledged`, `acknowledged_at`, `created_at`) VALUES
(1, '2026-06-27', 1, 2, 5, 2, 'طلبات عاجلة تحتاج متابعة', 'التسليمات المعلقة', 1, '2026-06-27 08:00:00', '2026-06-27 05:00:00'),
(2, '2026-06-27', 2, 1, 3, 1, 'متابعة طلب مستشفى النور', 'الوردية المسائية', 0, NULL, '2026-06-27 13:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `adjustment_code` varchar(20) NOT NULL,
  `store_id` int(11) NOT NULL,
  `adjustment_type` enum('periodic') DEFAULT 'periodic',
  `status` enum('draft','counting','completed','cancelled') DEFAULT 'draft',
  `total_items` int(11) DEFAULT 0,
  `counted_items` int(11) DEFAULT 0,
  `total_variance_qty` decimal(12,3) DEFAULT 0.000,
  `total_variance_cost` decimal(12,2) DEFAULT 0.00,
  `counted_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `counted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `adjustment_code`, `store_id`, `adjustment_type`, `status`, `total_items`, `counted_items`, `total_variance_qty`, `total_variance_cost`, `counted_by`, `approved_by`, `counted_at`, `approved_at`, `notes`, `created_at`) VALUES
(1, 'ADJ-00001', 6, 'periodic', 'draft', 0, 0, 0.000, 0.00, 1, NULL, '2026-06-27 20:59:57', NULL, NULL, '2026-06-27 18:59:57'),
(2, 'ADJ-00002', 1, 'periodic', 'draft', 0, 0, 0.000, 0.00, 1, NULL, '2026-06-27 21:00:48', NULL, NULL, '2026-06-27 19:00:48'),
(3, 'ADJ-00003', 1, 'periodic', 'completed', 5, 5, 10.000, 350.00, 1, 1, '2026-06-26 10:00:00', '2026-06-26 12:00:00', 'جرد دوري', '2026-06-26 08:00:00'),
(4, 'ADJ-00004', 2, '', 'draft', 0, 0, 0.000, 0.00, 1, NULL, '2026-06-27 10:00:00', NULL, 'جرد مفاجئ', '2026-06-27 08:00:00'),
(5, 'ADJ-9.2233720368548E', 1, 'periodic', 'completed', 10, 0, 0.000, 0.00, 1, NULL, '2026-06-28 20:02:17', NULL, NULL, '2026-06-28 18:02:17');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_items`
--

CREATE TABLE `stock_adjustment_items` (
  `id` int(11) NOT NULL,
  `adjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `system_qty` decimal(12,3) DEFAULT 0.000,
  `actual_qty` decimal(12,3) DEFAULT 0.000,
  `variance_qty` decimal(12,3) DEFAULT 0.000,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `variance_cost` decimal(12,2) DEFAULT 0.00,
  `is_counted` tinyint(1) DEFAULT 0,
  `counted_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_adjustment_items`
--

INSERT INTO `stock_adjustment_items` (`id`, `adjustment_id`, `product_id`, `batch_id`, `system_qty`, `actual_qty`, `variance_qty`, `unit_cost`, `variance_cost`, `is_counted`, `counted_at`, `notes`) VALUES
(1, 3, 10, 1, 450.000, 455.000, 5.000, 30.00, 150.00, 1, '2026-06-26 10:00:00', 'زيادة في الجرد'),
(2, 3, 16, 4, 550.000, 545.000, -5.000, 42.00, -210.00, 1, '2026-06-26 10:00:00', 'نقص في الجرد'),
(3, 3, 28, 7, 950.000, 960.000, 10.000, 16.00, 160.00, 1, '2026-06-26 10:00:00', 'زيادة في الجرد'),
(4, 3, 29, 8, 1400.000, 1395.000, -5.000, 11.00, -55.00, 1, '2026-06-26 10:00:00', 'نقص في الجرد'),
(5, 3, 36, 9, 480.000, 485.000, 5.000, 62.00, 310.00, 1, '2026-06-26 10:00:00', 'زيادة في الجرد'),
(6, 5, 10, 1, 450.000, 450.000, 0.000, 30.00, 0.00, 0, NULL, NULL),
(7, 5, 10, 2, 280.000, 280.000, 0.000, 28.00, 0.00, 0, NULL, NULL),
(8, 5, 11, 3, 380.000, 380.000, 0.000, 55.00, 0.00, 0, NULL, NULL),
(9, 5, 16, 4, 550.000, 550.000, 0.000, 42.00, 0.00, 0, NULL, NULL),
(10, 5, 17, 5, 750.000, 750.000, 0.000, 48.00, 0.00, 0, NULL, NULL),
(11, 5, 24, 6, 480.000, 480.000, 0.000, 55.00, 0.00, 0, NULL, NULL),
(12, 5, 28, 7, 950.000, 950.000, 0.000, 16.00, 0.00, 0, NULL, NULL),
(13, 5, 29, 8, 1400.000, 1400.000, 0.000, 11.00, 0.00, 0, NULL, NULL),
(14, 5, 36, 9, 480.000, 480.000, 0.000, 62.00, 0.00, 0, NULL, NULL),
(15, 5, 40, 10, 380.000, 380.000, 0.000, 35.00, 0.00, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `store_code` varchar(20) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `store_type` enum('main','sub') NOT NULL DEFAULT 'sub',
  `store_category` enum('warehouse','pharmacy','expired','damaged','surplus','general') NOT NULL DEFAULT 'general',
  `branch_id` int(11) DEFAULT NULL,
  `parent_store_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `store_code`, `store_name`, `store_type`, `store_category`, `branch_id`, `parent_store_id`, `is_active`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'CENT-01', 'المخزن الرئيسي المركزي', 'sub', 'general', NULL, NULL, 1, 'المخزن الرئيسي للشركة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(2, 'BR01-MAIN', 'مخزن فرع الشركة الرئيسي', 'sub', 'general', 1, 1, 1, 'المخزن الرئيسي لفرع الشركة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(3, 'BR02-MAIN', 'مخزن فرع نوال الرئيسي', 'sub', 'general', 2, 1, 1, 'المخزن الرئيسي لفرع نوال', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(4, 'BR03-MAIN', 'مخزن فرع مصدق الرئيسي', 'sub', 'general', 3, 1, 1, 'المخزن الرئيسي لفرع مصدق', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(5, 'BR04-MAIN', 'مخزن فرع صيد الرئيسي', 'sub', 'general', 4, 1, 1, 'المخزن الرئيسي لفرع صيد', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(6, 'BR01-PHARM', 'صيدلية فرع الشركة', 'sub', 'pharmacy', 1, 2, 1, 'صيدلية فرع الشركة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(7, 'BR01-WH', 'مستودع فرع الشركة', 'sub', 'warehouse', 1, 2, 1, 'مستودع فرع الشركة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(8, 'BR01-DMG', 'مخزن التالف فرع الشركة', 'sub', 'damaged', 1, 2, 1, 'مخزن الأصناف التالفة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(9, 'BR01-EXP', 'مخزن الهالك فرع الشركة', 'sub', 'expired', 1, 2, 1, 'مخزن الأصناف منتهية الصلاحية', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(10, 'BR02-PHARM', 'صيدلية فرع نوال', 'sub', 'pharmacy', 2, 3, 1, 'صيدلية فرع نوال', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(11, 'BR02-WH', 'مستودع فرع نوال', 'sub', 'warehouse', 2, 3, 1, 'مستودع فرع نوال', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(12, 'BR02-DMG', 'مخزن التالف فرع نوال', 'sub', 'damaged', 2, 3, 1, 'مخزن الأصناف التالفة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(13, 'BR02-EXP', 'مخزن الهالك فرع نوال', 'sub', 'expired', 2, 3, 1, 'مخزن الأصناف منتهية الصلاحية', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(14, 'BR03-PHARM', 'صيدلية فرع مصدق', 'sub', 'pharmacy', 3, 4, 1, 'صيدلية فرع مصدق', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(15, 'BR03-WH', 'مستودع فرع مصدق', 'sub', 'warehouse', 3, 4, 1, 'مستودع فرع مصدق', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(16, 'BR03-DMG', 'مخزن التالف فرع مصدق', 'sub', 'damaged', 3, 4, 1, 'مخزن الأصناف التالفة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(17, 'BR03-EXP', 'مخزن الهالك فرع مصدق', 'sub', 'expired', 3, 4, 1, 'مخزن الأصناف منتهية الصلاحية', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(18, 'BR04-PHARM', 'صيدلية فرع صيد', 'sub', 'pharmacy', 4, 5, 1, 'صيدلية فرع صيد', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(19, 'BR04-WH', 'مستودع فرع صيد', 'sub', 'warehouse', 4, 5, 1, 'مستودع فرع صيد', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(20, 'BR04-DMG', 'مخزن التالف فرع صيد', 'sub', 'damaged', 4, 5, 1, 'مخزن الأصناف التالفة', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(21, 'BR04-EXP', 'مخزن الهالك فرع صيد', 'sub', 'expired', 4, 5, 1, 'مخزن الأصناف منتهية الصلاحية', 1, '2026-06-27 18:58:15', '2026-06-30 00:10:11'),
(22, 'BR02-EXPENSIVE', 'مخزن الغوالي', 'sub', 'general', 2, 3, 1, NULL, 1, '2026-06-29 21:17:00', '2026-06-30 00:10:11');

-- --------------------------------------------------------

--
-- Table structure for table `store_code_sequences`
--

CREATE TABLE `store_code_sequences` (
  `id` int(11) NOT NULL,
  `store_type` varchar(20) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `prefix` varchar(10) NOT NULL,
  `last_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_code_sequences`
--

INSERT INTO `store_code_sequences` (`id`, `store_type`, `branch_id`, `prefix`, `last_number`, `created_at`) VALUES
(1, 'central_main', NULL, 'CENT', 1, '2026-06-27 18:58:15'),
(2, 'branch_main', 1, 'BR01', 1, '2026-06-27 18:58:15'),
(3, 'branch_main', 2, 'BR02', 1, '2026-06-27 18:58:15'),
(4, 'branch_main', 3, 'BR03', 1, '2026-06-27 18:58:15'),
(5, 'branch_main', 4, 'BR04', 1, '2026-06-27 18:58:15');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(20) NOT NULL COMMENT 'كود المورد التسلسلي',
  `supplier_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_time` varchar(50) DEFAULT 'نفس اليوم',
  `delivery_time_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `manual_code` varchar(50) DEFAULT NULL,
  `supplier_name_en` varchar(100) DEFAULT NULL,
  `supplier_type` varchar(20) DEFAULT 'company',
  `payment_type` varchar(20) DEFAULT 'cash',
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `grace_period` int(11) DEFAULT 0,
  `return_policy` text DEFAULT NULL,
  `instapay_number` varchar(50) DEFAULT NULL,
  `wallet_number` varchar(50) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `phone`, `email`, `address`, `is_active`, `created_at`, `delivery_time`, `delivery_time_id`, `notes`, `source`, `estock_id`, `manual_code`, `supplier_name_en`, `supplier_type`, `payment_type`, `credit_limit`, `grace_period`, `return_policy`, `instapay_number`, `wallet_number`, `deleted_at`, `created_by`, `updated_at`) VALUES
(1, '1', 'مورد الأدوية العام', '01234567890', 'supplier1@email.com', NULL, 1, '2026-06-16 19:06:20', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, NULL, 'company', 'cash', 0.00, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-25 21:56:41'),
(2, '2', 'مورد المستلزمات الطبية', '01234567891', 'supplier2@email.com', NULL, 1, '2026-06-16 19:06:20', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, NULL, 'company', 'cash', 0.00, 0, NULL, NULL, NULL, NULL, NULL, '2026-06-25 21:56:41'),
(3, '3', 'مورد مستحضرات التجميل', '01234567892', 'supplier3@email.com', NULL, 0, '2026-06-16 19:06:20', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, NULL, 'company', 'cash', 0.00, 0, NULL, NULL, NULL, '2026-06-27 19:16:08', NULL, '2026-06-27 17:16:08'),
(4, '4', 'احمد عبد الحميد', '01111111', NULL, NULL, 0, '2026-06-17 00:35:55', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, NULL, 'company', 'cash', 0.00, 0, NULL, NULL, NULL, '2026-06-26 00:03:42', NULL, '2026-06-25 22:30:05'),
(5, '5', 'عبد الرحمن كانسيداس', '01067788553', NULL, NULL, 0, '2026-06-17 17:00:49', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, NULL, 'company', 'cash', 0.00, 0, NULL, NULL, NULL, '2026-06-26 00:21:21', NULL, '2026-06-25 22:30:09'),
(6, '6', 'عمرو حجازي', NULL, NULL, NULL, 1, '2026-06-27 17:14:12', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, 'Amr Hegazy', 'b2b', 'cash', 0.00, 0, NULL, NULL, NULL, NULL, 1, '2026-06-27 17:14:12'),
(7, '7', 'احمد يحيي', NULL, NULL, NULL, 1, '2026-06-27 17:14:34', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL, 'ahmed yahia', 'b2b', 'cash', 0.00, 0, NULL, NULL, NULL, NULL, 1, '2026-06-27 17:14:34'),
(8, '8', 'مورد الأدوية المستوردة', '01200000001', 'imported@supplier.com', 'القاهرة', 1, '2026-06-20 08:00:00', 'أسبوع', 6, 'مورد أدوية مستوردة', 'manual', NULL, NULL, 'Imported Drugs Supplier', 'company', 'credit', 500000.00, 30, 'إرجاع خلال 30 يوم', '01000000001', '01000000001', NULL, 1, '2026-06-27 08:00:00'),
(9, '9', 'مورد المستلزمات الطبية 2', '01200000002', 'medical2@supplier.com', 'الإسكندرية', 1, '2026-06-20 08:00:00', 'نفس اليوم', 4, 'مورد مستلزمات', 'manual', NULL, NULL, 'Medical Supplies 2', 'company', 'cash', 0.00, 0, 'لا يوجد إرجاع', '01000000002', '01000000002', NULL, 1, '2026-06-27 08:00:00'),
(10, '10', 'مورد المستحضرات التجميلية', '01200000003', 'cosmetics@supplier.com', 'الجيزة', 1, '2026-06-20 08:00:00', '3-4 ساعات', 3, 'مورد تجميل', 'manual', NULL, NULL, 'Cosmetics Supplier', 'company', 'credit', 100000.00, 15, 'إرجاع خلال 15 يوم', '01000000003', '01000000003', NULL, 1, '2026-06-27 08:00:00'),
(11, '11', 'مورد الفيتامينات', '01200000004', 'vitamins@supplier.com', 'القاهرة', 1, '2026-06-20 08:00:00', 'يوم عمل', 5, 'مورد فيتامينات ومكملات', 'manual', NULL, NULL, 'Vitamins Supplier', 'company', 'credit', 200000.00, 20, 'إرجاع خلال 20 يوم', '01000000004', '01000000004', NULL, 1, '2026-06-27 08:00:00'),
(12, '12', 'مورد الأجهزة الطبية', '01200000005', 'devices@supplier.com', 'القاهرة', 1, '2026-06-20 08:00:00', 'أسبوع', 6, 'مورد أجهزة قياس', 'manual', NULL, NULL, 'Medical Devices Supplier', 'company', 'credit', 300000.00, 30, 'إرجاع خلال 30 يوم', '01000000005', '01000000005', NULL, 1, '2026-06-27 08:00:00'),
(13, '13', 'مورد B2B 1', '01200000006', 'b2b1@supplier.com', 'القاهرة', 1, '2026-06-20 08:00:00', 'نفس اليوم', 4, 'مورد B2B', 'manual', NULL, NULL, 'B2B Supplier 1', 'b2b', 'cash', 0.00, 0, '', '01000000006', '01000000006', NULL, 1, '2026-06-27 08:00:00'),
(14, '14', 'مورد B2B 2', '01200000007', 'b2b2@supplier.com', 'الجيزة', 1, '2026-06-20 08:00:00', 'ساعتين', 2, 'مورد B2B', 'manual', NULL, NULL, 'B2B Supplier 2', 'b2b', 'cash', 0.00, 0, '', '01000000007', '01000000007', NULL, 1, '2026-06-27 08:00:00'),
(15, '15', 'مورد موقوف 1', '01200000008', 'inactive@supplier.com', 'القاهرة', 0, '2026-06-20 08:00:00', 'نفس اليوم', 4, 'مورد موقوف', 'manual', NULL, NULL, 'Inactive Supplier 1', 'company', 'cash', 0.00, 0, '', '01000000008', '01000000008', NULL, 1, '2026-06-27 08:00:00'),
(16, '16', 'مورد محلي 1', '01200000009', 'local1@supplier.com', 'القاهرة', 1, '2026-06-20 08:00:00', 'ساعة', 1, 'مورد محلي', 'manual', NULL, NULL, 'Local Supplier 1', 'company', 'cash', 0.00, 0, '', '01000000009', '01000000009', NULL, 1, '2026-06-27 08:00:00'),
(17, '17', 'مورد محلي 2', '01200000010', 'local2@supplier.com', 'الجيزة', 1, '2026-06-20 08:00:00', 'ساعة', 1, 'مورد محلي', 'manual', NULL, NULL, 'Local Supplier 2', 'company', 'cash', 0.00, 0, '', '01000000010', '01000000010', NULL, 1, '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_addresses`
--

CREATE TABLE `supplier_addresses` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `address_type` enum('main','warehouse','branch','other') DEFAULT 'main' COMMENT 'نوع العنوان',
  `building_number` varchar(20) DEFAULT NULL,
  `floor_number` varchar(10) DEFAULT NULL,
  `apartment_number` varchar(20) DEFAULT NULL,
  `street_name` varchar(200) DEFAULT NULL,
  `landmark` varchar(200) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `governorate_id` int(11) DEFAULT NULL,
  `delivery_zone_id` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عناوين الموردين';

--
-- Dumping data for table `supplier_addresses`
--

INSERT INTO `supplier_addresses` (`id`, `supplier_id`, `address_type`, `building_number`, `floor_number`, `apartment_number`, `street_name`, `landmark`, `area_id`, `governorate_id`, `delivery_zone_id`, `is_primary`, `is_active`, `created_at`) VALUES
(1, 8, 'main', '10', '2', '5', 'شارع رمسيس', 'برج رمسيس', 7, 1, 2, 1, 1, '2026-06-20 08:00:00'),
(2, 8, 'warehouse', '25', '1', '1', 'المنطقة الصناعية', 'مستودع رئيسي', 13, 2, 3, 0, 1, '2026-06-20 08:00:00'),
(3, 9, 'main', '5', '3', '8', 'شارع الجمهورية', 'مبنى التجارة', 1, 1, 1, 1, 1, '2026-06-20 08:00:00'),
(4, 10, 'main', '15', '1', '2', 'شارع فيصل', 'بجوار المستشفى', 12, 2, 3, 1, 1, '2026-06-20 08:00:00'),
(5, 11, 'main', '20', '4', '10', 'شارع النصر', 'برج النصر', 3, 1, 2, 1, 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_balances`
--

CREATE TABLE `supplier_balances` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `balance` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد الحالي (دائن إذا موجب = علينا فلوس، مدين إذا سالب = المورد عليه)',
  `total_purchases` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المشتريات',
  `total_payments` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المدفوعات',
  `total_returns` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المرتجعات',
  `last_transaction_date` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أرصدة الموردين';

--
-- Dumping data for table `supplier_balances`
--

INSERT INTO `supplier_balances` (`id`, `supplier_id`, `balance`, `total_purchases`, `total_payments`, `total_returns`, `last_transaction_date`, `updated_at`) VALUES
(1, 1, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 21:56:41'),
(2, 4, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 21:56:41'),
(3, 5, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 21:56:41'),
(4, 2, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 21:56:41'),
(5, 3, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-25 21:56:41'),
(6, 8, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(7, 9, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(8, 10, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(9, 6, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 17:14:12'),
(10, 7, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 17:14:34'),
(11, 13, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(12, 14, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(13, 15, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(14, 16, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00'),
(15, 17, 0.00, 0.00, 0.00, 0.00, NULL, '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_bank_accounts`
--

CREATE TABLE `supplier_bank_accounts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `account_number` varchar(50) NOT NULL COMMENT 'رقم الحساب',
  `bank_name` varchar(100) NOT NULL COMMENT 'اسم البنك',
  `iban` varchar(50) DEFAULT NULL COMMENT 'IBAN',
  `swift_code` varchar(20) DEFAULT NULL COMMENT 'Swift Code',
  `branch_name` varchar(100) DEFAULT NULL COMMENT 'فرع البنك',
  `is_primary` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حسابات بنكية للموردين';

--
-- Dumping data for table `supplier_bank_accounts`
--

INSERT INTO `supplier_bank_accounts` (`id`, `supplier_id`, `account_number`, `bank_name`, `iban`, `swift_code`, `branch_name`, `is_primary`, `is_active`, `created_at`) VALUES
(1, 8, '1234567890', 'البنك الأهلي المصري', 'EG123456789012345678901234567', 'NBEGEGCX', 'فرع رمسيس', 1, 1, '2026-06-20 08:00:00'),
(2, 8, '0987654321', 'بنك القاهرة', 'EG987654321098765432109876543', 'CAIEGCX', 'فرع الجمهورية', 0, 1, '2026-06-20 08:00:00'),
(3, 9, '1122334455', 'البنك التجاري الدولي', 'EG112233445566778899001122334', 'CIBEGCX', 'فرع النصر', 1, 1, '2026-06-20 08:00:00'),
(4, 10, '5566778899', 'بنك مصر', 'EG556677889900112233445566778', 'BMISGCX', 'فرع فيصل', 1, 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_contacts`
--

CREATE TABLE `supplier_contacts` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `contact_type` enum('manager','representative','distributor','other') NOT NULL COMMENT 'نوع التواصل',
  `contact_name` varchar(100) NOT NULL COMMENT 'الاسم',
  `job_title` varchar(100) DEFAULT NULL COMMENT 'المسمى الوظيفي',
  `phone` varchar(20) DEFAULT NULL COMMENT 'رقم الهاتف',
  `email` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0 COMMENT 'رئيسي',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='موظفين ومناديب الموردين';

--
-- Dumping data for table `supplier_contacts`
--

INSERT INTO `supplier_contacts` (`id`, `supplier_id`, `contact_type`, `contact_name`, `job_title`, `phone`, `email`, `is_primary`, `notes`, `is_active`, `created_at`) VALUES
(1, 8, 'manager', 'أحمد السيد', 'مدير المبيعات', '01210000001', 'ahmed@supplier8.com', 1, 'المدير الرئيسي', 1, '2026-06-20 08:00:00'),
(2, 8, 'representative', 'محمد علي', 'مندوب مبيعات', '01210000002', 'mohamed@supplier8.com', 0, 'مندوب القاهرة', 1, '2026-06-20 08:00:00'),
(3, 9, 'manager', 'خالد محمود', 'مدير عام', '01210000003', 'khaled@supplier9.com', 1, '', 1, '2026-06-20 08:00:00'),
(4, 10, 'manager', 'سامي فؤاد', 'مدير المشتريات', '01210000004', 'sami@supplier10.com', 1, '', 1, '2026-06-20 08:00:00'),
(5, 11, 'distributor', 'عمر حسن', 'موزع', '01210000005', 'omar@supplier11.com', 1, 'موزع رئيسي', 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_due_payments`
--

CREATE TABLE `supplier_due_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL COMMENT 'رقم الحركة المرتبطة',
  `reference_number` varchar(50) DEFAULT NULL COMMENT 'رقم الفاتورة/الشيك',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'المبلغ المستحق',
  `paid_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'المبلغ المدفوع',
  `remaining_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'المبلغ المتبقي',
  `due_type` enum('credit','cheque') DEFAULT 'credit' COMMENT 'نوع الاستحقاق',
  `due_date` date NOT NULL COMMENT 'تاريخ الاستحقاق',
  `cheque_number` varchar(50) DEFAULT NULL COMMENT 'رقم الشيك (لو شيك)',
  `bank_name` varchar(100) DEFAULT NULL COMMENT 'البنك (لو شيك)',
  `status` enum('pending','partial','paid','overdue') DEFAULT 'pending' COMMENT 'الحالة',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مستحقات الموردين';

--
-- Dumping data for table `supplier_due_payments`
--

INSERT INTO `supplier_due_payments` (`id`, `supplier_id`, `transaction_id`, `reference_number`, `amount`, `paid_amount`, `remaining_amount`, `due_type`, `due_date`, `cheque_number`, `bank_name`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 8, 1, 'PUR-20260001', 10000.00, 5000.00, 5000.00, 'credit', '2026-07-20', NULL, NULL, 'partial', 'دفعة جزئية', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(2, 9, 3, 'PUR-20260002', 5000.00, 0.00, 5000.00, 'credit', '2026-07-22', NULL, NULL, 'pending', 'مستحق الدفع', 1, '2026-06-22 08:00:00', '2026-06-27 08:00:00'),
(3, 10, 4, 'PUR-20260003', 8000.00, 0.00, 8000.00, 'cheque', '2026-07-23', 'CHQ-001', 'البنك الأهلي', 'pending', 'شيك مستحق', 1, '2026-06-23 08:00:00', '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_phones`
--

CREATE TABLE `supplier_phones` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `country_code` varchar(10) DEFAULT '+20',
  `phone_number` varchar(20) NOT NULL,
  `phone_type` enum('mobile','landline','fax','whatsapp') DEFAULT 'mobile',
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أرقام هاتف الموردين';

--
-- Dumping data for table `supplier_phones`
--

INSERT INTO `supplier_phones` (`id`, `supplier_id`, `country_code`, `phone_number`, `phone_type`, `is_primary`, `created_at`) VALUES
(2, 2, '+20', '01234567891', 'mobile', 1, '2026-06-25 21:59:37'),
(3, 3, '+20', '01234567892', 'mobile', 1, '2026-06-25 21:59:37'),
(4, 4, '+20', '01111111', 'mobile', 1, '2026-06-25 21:59:37'),
(5, 5, '+20', '01067788553', 'mobile', 1, '2026-06-25 21:59:37'),
(8, 1, '+20', '01234567890', 'mobile', 1, '2026-06-25 22:20:30'),
(10, 7, '+20', '0101010101010', 'mobile', 1, '2026-06-27 17:14:44'),
(11, 6, '+20', '01067788553', 'mobile', 1, '2026-06-27 17:15:40'),
(12, 8, '+20', '01200000001', 'mobile', 1, '2026-06-20 08:00:00'),
(13, 8, '+20', '01200000002', 'landline', 0, '2026-06-20 08:00:00'),
(14, 9, '+20', '01200000003', 'mobile', 1, '2026-06-20 08:00:00'),
(15, 10, '+20', '01200000004', 'mobile', 1, '2026-06-20 08:00:00'),
(16, 10, '+20', '01200000005', 'whatsapp', 0, '2026-06-20 08:00:00'),
(17, 11, '+20', '01200000006', 'mobile', 1, '2026-06-20 08:00:00'),
(18, 12, '+20', '01200000007', 'mobile', 1, '2026-06-20 08:00:00'),
(19, 13, '+20', '01200000008', 'mobile', 1, '2026-06-20 08:00:00'),
(20, 14, '+20', '01200000009', 'mobile', 1, '2026-06-20 08:00:00'),
(21, 16, '+20', '01200000010', 'mobile', 1, '2026-06-20 08:00:00'),
(22, 17, '+20', '01200000011', 'mobile', 1, '2026-06-20 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_prices`
--

CREATE TABLE `supplier_prices` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `profit_margin` decimal(5,2) DEFAULT 0.00,
  `delivery_time_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_prices`
--

INSERT INTO `supplier_prices` (`id`, `product_id`, `product_code`, `supplier_id`, `supplier_price`, `notes`, `created_by`, `is_active`, `created_at`, `updated_at`, `sell_price`, `profit_margin`, `delivery_time_id`) VALUES
(1, 10, '10', 1, 30.00, 'سعر المورد الرئيسي', 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 45.00, 50.00, 4),
(2, 10, '10', 8, 28.00, 'سعر المورد المستورد', 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 45.00, 60.71, 6),
(3, 16, '16', 2, 42.00, 'سعر المورد الرئيسي', 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 65.00, 54.76, 4),
(4, 17, '17', 2, 48.00, 'سعر المورد الرئيسي', 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 75.00, 56.25, 4),
(5, 28, '28', 2, 16.00, 'سعر المورد الرئيسي', 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 25.00, 56.25, 4),
(6, 36, '36', 1, 62.00, 'سعر المورد الرئيسي', 1, 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00', 95.00, 53.23, 4);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'رقم الصنف من جدول products',
  `product_code` varchar(50) DEFAULT NULL COMMENT 'كود الصنف عند المورد',
  `purchase_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'سعر الشراء',
  `discount_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'نسبة الخصم',
  `vat_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'نسبة الضريبة',
  `net_price` decimal(10,2) DEFAULT 0.00 COMMENT 'السعر بعد الخصم والضريبة',
  `last_purchase_date` date DEFAULT NULL COMMENT 'تاريخ آخر شراء',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'السعر الافتراضي الحالي',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أصناف الموردين وأسعار الشراء';

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`id`, `supplier_id`, `product_id`, `product_code`, `purchase_price`, `discount_percent`, `vat_percent`, `net_price`, `last_purchase_date`, `is_default`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 10, '10', 30.00, 0.00, 14.00, 34.20, '2026-06-20', 1, 'سعر افتراضي', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(2, 2, 16, '16', 42.00, 0.00, 14.00, 47.88, '2026-06-20', 1, 'سعر افتراضي', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(3, 2, 17, '17', 48.00, 0.00, 14.00, 54.72, '2026-06-20', 1, 'سعر افتراضي', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(4, 2, 28, '28', 16.00, 0.00, 14.00, 18.24, '2026-06-20', 1, 'سعر افتراضي', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00'),
(5, 1, 36, '36', 62.00, 0.00, 14.00, 70.68, '2026-06-20', 1, 'سعر افتراضي', 1, '2026-06-20 08:00:00', '2026-06-27 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_product_pricing`
--

CREATE TABLE `supplier_product_pricing` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sell_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 0.00,
  `last_supply_date` date DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_transactions`
--

CREATE TABLE `supplier_transactions` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','payment','return','refund','adjustment') NOT NULL COMMENT 'نوع الحركة',
  `reference_type` enum('purchase_invoice','payment','return_invoice','manual') DEFAULT 'manual' COMMENT 'نوع المرجع',
  `reference_id` int(11) DEFAULT NULL COMMENT 'رقم المرجع',
  `reference_number` varchar(50) DEFAULT NULL COMMENT 'رقم المرجع النصي (رقم الفاتورة/الشيك)',
  `debit` decimal(12,2) DEFAULT 0.00 COMMENT 'مدين (علينا للمورد)',
  `credit` decimal(12,2) DEFAULT 0.00 COMMENT 'دائن (المورد عليه / دفعنا له)',
  `balance_after` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد بعد الحركة',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='حركات حسابات الموردين';

--
-- Dumping data for table `supplier_transactions`
--

INSERT INTO `supplier_transactions` (`id`, `supplier_id`, `transaction_type`, `reference_type`, `reference_id`, `reference_number`, `debit`, `credit`, `balance_after`, `notes`, `created_by`, `created_at`) VALUES
(1, 8, 'purchase', 'purchase_invoice', 1, 'PUR-20260001', 10000.00, 0.00, 10000.00, 'فاتورة مشتريات', 1, '2026-06-20 08:00:00'),
(2, 8, 'payment', 'payment', 1, 'PAY-SUP-001', 0.00, 5000.00, 5000.00, 'دفعة للمورد', 1, '2026-06-21 08:00:00'),
(3, 9, 'purchase', 'purchase_invoice', 2, 'PUR-20260002', 5000.00, 0.00, 5000.00, 'فاتورة مشتريات', 1, '2026-06-22 08:00:00'),
(4, 10, 'purchase', 'purchase_invoice', 3, 'PUR-20260003', 8000.00, 0.00, 8000.00, 'فاتورة مشتريات', 1, '2026-06-23 08:00:00'),
(5, 11, 'purchase', 'purchase_invoice', 4, 'PUR-20260004', 12000.00, 0.00, 12000.00, 'فاتورة مشتريات', 1, '2026-06-24 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','pharmacist','purchaser','cashier') DEFAULT 'pharmacist',
  `branch_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `branch_code`, `phone`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2b$10$BWUBpgWGlNUigwPale.wlOfuBvh8Y4nPXu556/ECJ.hxp4ye5kZ46', 'System Administrator', 'admin', NULL, NULL, 1, '2026-06-29 23:44:48', '2026-06-15 16:53:16', '2026-06-29 21:44:48'),
(2, 'Zain', '$2y$10$334KBKCnb3ilFu1UH91sU.Rvva4LuD6os7celKfZFwdXZFVsvWVvG', 'Ahmed Zain', 'purchaser', '', '01003065048', 1, '2026-06-17 01:45:16', '2026-06-16 23:45:07', '2026-06-16 23:45:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_logs_user` (`user_id`),
  ADD KEY `idx_activity_logs_action` (`action`),
  ADD KEY `idx_activity_logs_date` (`created_at`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `area_code` (`area_code`),
  ADD KEY `fk_area_governorate` (`governorate_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`);

--
-- Indexes for table `company_employees`
--
ALTER TABLE `company_employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `country_codes`
--
ALTER TABLE `country_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `country_code` (`country_code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `fk_customer_class` (`customer_class_id`),
  ADD KEY `fk_customer_branch` (`branch_id`);

--
-- Indexes for table `customers_backup`
--
ALTER TABLE `customers_backup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `fk_address_area` (`area_id`),
  ADD KEY `fk_address_governorate` (`governorate_id`),
  ADD KEY `fk_address_zone` (`delivery_zone_id`);

--
-- Indexes for table `customer_areas`
--
ALTER TABLE `customer_areas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_balances`
--
ALTER TABLE `customer_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_customer` (`customer_id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `customer_classes`
--
ALTER TABLE `customer_classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_contracts`
--
ALTER TABLE `customer_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `customer_phones`
--
ALTER TABLE `customer_phones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `customer_transactions`
--
ALTER TABLE `customer_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `fk_transaction_user` (`created_by`);

--
-- Indexes for table `delivery_times`
--
ALTER TABLE `delivery_times`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `time_code` (`time_code`);

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `zone_code` (`zone_code`);

--
-- Indexes for table `governorates`
--
ALTER TABLE `governorates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `governorate_code` (`governorate_code`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_product` (`product_id`),
  ADD KEY `idx_batch_store` (`store_id`),
  ADD KEY `idx_batch_supplier` (`supplier_id`),
  ADD KEY `idx_batch_exp` (`exp_date`),
  ADD KEY `idx_batch_active` (`is_active`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_store_product_batch` (`store_id`,`product_id`,`batch_id`),
  ADD KEY `idx_inv_item_store` (`store_id`),
  ADD KEY `idx_inv_item_product` (`product_id`),
  ADD KEY `idx_inv_item_batch` (`batch_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_tx_store` (`store_id`),
  ADD KEY `idx_inv_tx_product` (`product_id`),
  ADD KEY `idx_inv_tx_batch` (`batch_id`),
  ADD KEY `idx_inv_tx_type` (`transaction_type`),
  ADD KEY `idx_inv_tx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_inv_tx_date` (`created_at`),
  ADD KEY `fk_inv_tx_unit` (`unit_id`),
  ADD KEY `fk_inv_tx_user` (`created_by`);

--
-- Indexes for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_code` (`transfer_code`),
  ADD KEY `idx_transfer_from` (`from_store_id`),
  ADD KEY `idx_transfer_to` (`to_store_id`),
  ADD KEY `idx_transfer_status` (`status`),
  ADD KEY `idx_transfer_date` (`created_at`),
  ADD KEY `fk_transfer_from_branch` (`from_branch_id`),
  ADD KEY `fk_transfer_to_branch` (`to_branch_id`);

--
-- Indexes for table `inventory_transfer_items`
--
ALTER TABLE `inventory_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transfer_item_transfer` (`transfer_id`),
  ADD KEY `idx_transfer_item_product` (`product_id`),
  ADD KEY `idx_transfer_item_batch` (`batch_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_orders_status` (`status_id`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_date` (`order_date`),
  ADD KEY `idx_orders_branch` (`branch_code`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `order_items_ibfk_2` (`product_id`);

--
-- Indexes for table `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `price_adjustments`
--
ALTER TABLE `price_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_product` (`store_id`,`product_id`),
  ADD KEY `idx_date` (`adjustment_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD UNIQUE KEY `uk_manual_code` (`manual_code`),
  ADD KEY `idx_product_type` (`product_type_id`),
  ADD KEY `idx_product_active` (`is_active`),
  ADD KEY `idx_product_company` (`company_id`),
  ADD KEY `idx_product_category` (`category_id`);

--
-- Indexes for table `product_alerts`
--
ALTER TABLE `product_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_barcodes`
--
ALTER TABLE `product_barcodes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_companies`
--
ALTER TABLE `product_companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_locations`
--
ALTER TABLE `product_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_product_location` (`product_id`,`location_code`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_movements`
--
ALTER TABLE `product_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_store` (`product_id`,`store_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `product_price_history`
--
ALTER TABLE `product_price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `product_types`
--
ALTER TABLE `product_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_code` (`type_code`);

--
-- Indexes for table `product_units`
--
ALTER TABLE `product_units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchased_items`
--
ALTER TABLE `purchased_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `shift_handovers`
--
ALTER TABLE `shift_handovers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `adjustment_code` (`adjustment_code`),
  ADD KEY `idx_adj_store` (`store_id`),
  ADD KEY `idx_adj_status` (`status`),
  ADD KEY `idx_adj_date` (`created_at`);

--
-- Indexes for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_adj_item_adj` (`adjustment_id`),
  ADD KEY `idx_adj_item_product` (`product_id`),
  ADD KEY `idx_adj_item_batch` (`batch_id`),
  ADD KEY `idx_adj_item_counted` (`is_counted`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_code` (`store_code`),
  ADD KEY `idx_store_branch` (`branch_id`),
  ADD KEY `idx_store_parent` (`parent_store_id`),
  ADD KEY `idx_store_type` (`store_type`),
  ADD KEY `idx_store_active` (`is_active`),
  ADD KEY `fk_store_user` (`created_by`);

--
-- Indexes for table `store_code_sequences`
--
ALTER TABLE `store_code_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_type_branch` (`store_type`,`branch_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD UNIQUE KEY `uk_supplier_code` (`supplier_code`);

--
-- Indexes for table `supplier_addresses`
--
ALTER TABLE `supplier_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `fk_supp_addr_area` (`area_id`),
  ADD KEY `fk_supp_addr_gov` (`governorate_id`),
  ADD KEY `fk_supp_addr_zone` (`delivery_zone_id`);

--
-- Indexes for table `supplier_balances`
--
ALTER TABLE `supplier_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_supplier` (`supplier_id`),
  ADD KEY `idx_supplier` (`supplier_id`);

--
-- Indexes for table `supplier_bank_accounts`
--
ALTER TABLE `supplier_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`);

--
-- Indexes for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_contact_type` (`contact_type`);

--
-- Indexes for table `supplier_due_payments`
--
ALTER TABLE `supplier_due_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_due_user` (`created_by`);

--
-- Indexes for table `supplier_phones`
--
ALTER TABLE `supplier_phones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`);

--
-- Indexes for table `supplier_prices`
--
ALTER TABLE `supplier_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_supplier_product` (`supplier_id`,`product_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_last_purchase` (`last_purchase_date`);

--
-- Indexes for table `supplier_product_pricing`
--
ALTER TABLE `supplier_product_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_product_supplier` (`product_id`,`supplier_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_default` (`is_default`);

--
-- Indexes for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `fk_transaction_user_sup` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `company_employees`
--
ALTER TABLE `company_employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `country_codes`
--
ALTER TABLE `country_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `customer_balances`
--
ALTER TABLE `customer_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `customer_phones`
--
ALTER TABLE `customer_phones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `customer_transactions`
--
ALTER TABLE `customer_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `delivery_times`
--
ALTER TABLE `delivery_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `governorates`
--
ALTER TABLE `governorates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_transfer_items`
--
ALTER TABLE `inventory_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `price_adjustments`
--
ALTER TABLE `price_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `product_alerts`
--
ALTER TABLE `product_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_barcodes`
--
ALTER TABLE `product_barcodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `product_locations`
--
ALTER TABLE `product_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_movements`
--
ALTER TABLE `product_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_price_history`
--
ALTER TABLE `product_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_types`
--
ALTER TABLE `product_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchased_items`
--
ALTER TABLE `purchased_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shift_handovers`
--
ALTER TABLE `shift_handovers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `store_code_sequences`
--
ALTER TABLE `store_code_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `supplier_addresses`
--
ALTER TABLE `supplier_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supplier_balances`
--
ALTER TABLE `supplier_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `supplier_bank_accounts`
--
ALTER TABLE `supplier_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supplier_due_payments`
--
ALTER TABLE `supplier_due_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `supplier_phones`
--
ALTER TABLE `supplier_phones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `supplier_prices`
--
ALTER TABLE `supplier_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supplier_product_pricing`
--
ALTER TABLE `supplier_product_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `fk_area_governorate` FOREIGN KEY (`governorate_id`) REFERENCES `governorates` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `company_employees`
--
ALTER TABLE `company_employees`
  ADD CONSTRAINT `fk_employee_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customer_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_customer_class` FOREIGN KEY (`customer_class_id`) REFERENCES `customer_classes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `fk_address_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_address_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_address_governorate` FOREIGN KEY (`governorate_id`) REFERENCES `governorates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_address_zone` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_balances`
--
ALTER TABLE `customer_balances`
  ADD CONSTRAINT `fk_balance_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_phones`
--
ALTER TABLE `customer_phones`
  ADD CONSTRAINT `fk_phone_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_transactions`
--
ALTER TABLE `customer_transactions`
  ADD CONSTRAINT `fk_transaction_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transaction_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inv_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_item_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inv_tx_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_tx_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_tx_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_tx_unit` FOREIGN KEY (`unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_tx_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_transfers`
--
ALTER TABLE `inventory_transfers`
  ADD CONSTRAINT `fk_transfer_from_branch` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_from_store` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_to_branch` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_to_store` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_transfer_items`
--
ALTER TABLE `inventory_transfer_items`
  ADD CONSTRAINT `fk_transfer_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_item_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `inventory_transfers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `purchased_items`
--
ALTER TABLE `purchased_items`
  ADD CONSTRAINT `purchased_items_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
  ADD CONSTRAINT `purchased_items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `shift_handovers`
--
ALTER TABLE `shift_handovers`
  ADD CONSTRAINT `shift_handovers_ibfk_1` FOREIGN KEY (`from_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shift_handovers_ibfk_2` FOREIGN KEY (`to_user`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `fk_adj_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_adjustment_items`
--
ALTER TABLE `stock_adjustment_items`
  ADD CONSTRAINT `fk_adj_item_adj` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_adj_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `fk_store_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_store_parent` FOREIGN KEY (`parent_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_store_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_addresses`
--
ALTER TABLE `supplier_addresses`
  ADD CONSTRAINT `fk_addr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supp_addr_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supp_addr_gov` FOREIGN KEY (`governorate_id`) REFERENCES `governorates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supp_addr_zone` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_balances`
--
ALTER TABLE `supplier_balances`
  ADD CONSTRAINT `fk_balance_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_bank_accounts`
--
ALTER TABLE `supplier_bank_accounts`
  ADD CONSTRAINT `fk_bank_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_contacts`
--
ALTER TABLE `supplier_contacts`
  ADD CONSTRAINT `fk_contact_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_due_payments`
--
ALTER TABLE `supplier_due_payments`
  ADD CONSTRAINT `fk_due_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_due_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `supplier_phones`
--
ALTER TABLE `supplier_phones`
  ADD CONSTRAINT `fk_phone_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_prices`
--
ALTER TABLE `supplier_prices`
  ADD CONSTRAINT `supplier_prices_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `fk_sp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_product_pricing`
--
ALTER TABLE `supplier_product_pricing`
  ADD CONSTRAINT `fk_supp_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supp_pricing_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  ADD CONSTRAINT `fk_transaction_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transaction_user_sup` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
