-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 18, 2026 at 01:23 AM
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

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddColumnIfNotExists` (IN `p_table` VARCHAR(64), IN `p_column` VARCHAR(64), IN `p_definition` VARCHAR(255))   BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = @dbname 
        AND TABLE_NAME = p_table 
        AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN ', p_column, ' ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

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
(72, 1, 'System Administrator', 'update', 'orders', 24, NULL, '{\"order_number\":\"20260005\"}', '26.201.9.238', '2026-06-17 17:01:21');

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
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
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
  `estock_id` decimal(18,0) DEFAULT NULL,
  `manual_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `customer_name`, `phone`, `phone2`, `email`, `address`, `branch_code`, `notes`, `is_active`, `created_at`, `updated_at`, `source`, `estock_id`, `manual_code`) VALUES
(1, 'CUST001', 'أحمد محمد', '01001234567', NULL, NULL, NULL, 'BR001', 'عميل دائم', 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL, NULL),
(2, 'CUST002', 'محمد علي', '01002345678', NULL, NULL, NULL, 'BR001', NULL, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL, NULL),
(3, 'CUST003', 'فاطمة أحمد', '01003456789', NULL, NULL, NULL, 'BR001', NULL, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL, NULL),
(5, 'CUST1781640433', 'الة', '01067785', '012645564984', NULL, '21لاالي اللاايس', '3', NULL, 1, '2026-06-16 20:07:13', '2026-06-16 20:07:13', 'estock', NULL, NULL),
(6, 'CUST1781640799', 'محمد ', '0123456789', '012345678998', NULL, '29 نوال الدقي', '2', NULL, 1, '2026-06-16 20:13:19', '2026-06-16 20:13:19', 'estock', NULL, NULL);

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
-- Table structure for table `customer_classes`
--

CREATE TABLE `customer_classes` (
  `id` int(11) NOT NULL,
  `class_code` varchar(50) DEFAULT NULL,
  `class_name_ar` varchar(50) NOT NULL,
  `class_name_en` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_contracts`
--

CREATE TABLE `customer_contracts` (
  `id` int(11) NOT NULL,
  `contract_code` varchar(50) DEFAULT NULL,
  `contract_name` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
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
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT 1,
  `quantity` float DEFAULT 0,
  `buy_price` decimal(10,2) DEFAULT 0.00,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `exp_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `purchase_invoice_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(25, '20260006', 5, 'CUST1781640433', 'الة', '01067785', '012645564984', '21لاالي اللاايس', '3', '2026-06-17 02:49:22', 'normal', 1, 1, '', 1, '2026-06-17 00:49:22', 1, '2026-06-17 16:59:50', '', 0.00, 144.00);

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
(42, 24, NULL, NULL, 'galvus me 50/1000', 1, '', '2026-06-17 17:01:21', 144.00, NULL, 0.00, 144.00, 0, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-17 23:08:28');

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
(1, 'PRD001', 'Ozempic', NULL, NULL, 1, 0, 0, 0, 0, 0, 0, 'أدوية السكري', 'Novo Nordisk', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, 'طلب متكرر', 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL, NULL),
(2, 'PRD002', 'Humira', NULL, NULL, 1, 0, 0, 0, 0, 0, 0, 'أدوية المناعة', 'AbbVie', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, NULL, 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL, NULL),
(3, 'PRD003', 'Eliquis', NULL, NULL, 1, 0, 0, 0, 0, 0, 0, 'مميعات الدم', 'Bristol Myers Squibb', NULL, NULL, 0.00, 0.00, 0.00, 0, 0, 0, 0, 0, NULL, NULL, 0.00, 0, NULL, 0, 1, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-06-15 16:53:16', '2026-06-15 16:53:16', 'estock', NULL, NULL);

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
(4, 41, 5, 120.00, 144.00, 20.00, 1, 120.00, 144.00, 24.00, '', 1, '2026-06-17 17:01:01');

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

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(20) NOT NULL,
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
  `manual_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `phone`, `email`, `address`, `is_active`, `created_at`, `delivery_time`, `delivery_time_id`, `notes`, `source`, `estock_id`, `manual_code`) VALUES
(1, 'SUP001', 'مورد الأدوية العام', '01234567890', 'supplier1@email.com', NULL, 1, '2026-06-16 19:06:20', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL),
(2, 'SUP002', 'مورد المستلزمات الطبية', '01234567891', 'supplier2@email.com', NULL, 1, '2026-06-16 19:06:20', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL),
(3, 'SUP003', 'مورد مستحضرات التجميل', '01234567892', 'supplier3@email.com', NULL, 1, '2026-06-16 19:06:20', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL),
(4, 'SUP1781656555', 'احمد عبد الحميد', '01111111', NULL, NULL, 1, '2026-06-17 00:35:55', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL),
(5, 'SUP1781715649', 'عبد الرحمن كانسيداس', '01067788553', NULL, NULL, 1, '2026-06-17 17:00:49', 'نفس اليوم', NULL, NULL, 'estock', NULL, NULL);

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
(1, 'admin', '$2b$10$BWUBpgWGlNUigwPale.wlOfuBvh8Y4nPXu556/ECJ.hxp4ye5kZ46', 'System Administrator', 'admin', NULL, NULL, 1, '2026-06-17 18:52:02', '2026-06-15 16:53:16', '2026-06-17 16:52:02'),
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
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `customer_areas`
--
ALTER TABLE `customer_areas`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `delivery_times`
--
ALTER TABLE `delivery_times`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `time_code` (`time_code`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_product_branch_store` (`product_id`,`branch_id`,`store_id`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_exp` (`exp_date`),
  ADD KEY `idx_batch_product` (`product_id`),
  ADD KEY `idx_batch_expiry` (`exp_date`),
  ADD KEY `idx_batch_active` (`is_active`),
  ADD KEY `idx_batch_supplier` (`supplier_id`);

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
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
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
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `supplier_prices`
--
ALTER TABLE `supplier_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `delivery_times`
--
ALTER TABLE `delivery_times`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_alerts`
--
ALTER TABLE `product_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_barcodes`
--
ALTER TABLE `product_barcodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_locations`
--
ALTER TABLE `product_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_price_history`
--
ALTER TABLE `product_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_types`
--
ALTER TABLE `product_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchased_items`
--
ALTER TABLE `purchased_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shift_handovers`
--
ALTER TABLE `shift_handovers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_prices`
--
ALTER TABLE `supplier_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supplier_product_pricing`
--
ALTER TABLE `supplier_product_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `supplier_prices`
--
ALTER TABLE `supplier_prices`
  ADD CONSTRAINT `supplier_prices_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `supplier_product_pricing`
--
ALTER TABLE `supplier_product_pricing`
  ADD CONSTRAINT `fk_supp_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supp_pricing_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
