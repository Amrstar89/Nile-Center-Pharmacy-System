
-- ============================================
-- NILE CENTER SYSTEM - COMPLETE DATABASE SCHEMA
-- Migration Ready - Matches E-Stock Structure
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- 1. CORE TABLES (System Foundation)
-- ============================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','pharmacist','purchaser','cashier','manager') DEFAULT 'pharmacist',
  `branch_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_branch` (`branch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `address` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_code` (`branch_code`),
  KEY `idx_manager` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table` (`table_name`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. PRODUCT MANAGEMENT (Matches E-Stock)
-- ============================================

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int(11) NOT NULL,  -- E-Stock group_id
  `category_code` varchar(50) DEFAULT NULL,
  `category_name_ar` varchar(100) NOT NULL,
  `category_name_en` varchar(100) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_companies` (
  `id` int(11) NOT NULL,  -- E-Stock company_id
  `company_code` varchar(50) DEFAULT NULL,
  `company_name_ar` varchar(100) NOT NULL,
  `company_name_en` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_units` (
  `id` int(11) NOT NULL,  -- E-Stock unit_id
  `unit_code` varchar(50) DEFAULT NULL,
  `unit_name_ar` varchar(50) NOT NULL,
  `unit_name_en` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL,  -- E-Stock product_id (NOT AUTO_INCREMENT)
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(200) NOT NULL,  -- Arabic name (E-Stock product_name_ar)
  `product_name_en` varchar(200) DEFAULT NULL,
  `scientific_name` varchar(200) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,  -- E-Stock group_id

  -- Pricing
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `unit2_sell_price` decimal(10,2) DEFAULT 0.00,
  `unit3_sell_price` decimal(10,2) DEFAULT 0.00,

  -- Units
  `unit1_id` int(11) DEFAULT NULL,
  `unit2_id` int(11) DEFAULT NULL,
  `unit3_id` int(11) DEFAULT NULL,
  `unit1_to_unit2` decimal(18,0) DEFAULT NULL,  -- conversion ratio
  `unit1_to_unit3` decimal(18,0) DEFAULT NULL,
  `default_sale_unit` int(11) DEFAULT 1,  -- 1, 2, or 3

  -- Flags
  `has_expire` tinyint(1) DEFAULT 0,
  `is_drug` tinyint(1) DEFAULT 0,
  `is_imported` tinyint(1) DEFAULT 0,
  `allow_discount` tinyint(1) DEFAULT 1,
  `max_discount` float DEFAULT NULL,
  `can_be_negative` tinyint(1) DEFAULT 0,
  `is_made` tinyint(1) DEFAULT 0,  -- manufactured locally

  -- Barcode
  `print_barcode` int(11) DEFAULT 0,
  `barcode_type` varchar(20) DEFAULT NULL,

  -- Source tracking
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `manual_code` varchar(50) DEFAULT NULL,  -- M-12345 for manual products

  -- Status
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`),
  UNIQUE KEY `manual_code` (`manual_code`),
  KEY `idx_category` (`category_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_source` (`source`),
  KEY `idx_name` (`product_name`),
  FULLTEXT KEY `ft_name` (`product_name`, `product_name_en`, `scientific_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Barcodes (multiple barcodes per product)
CREATE TABLE IF NOT EXISTS `product_barcodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `unit_id` int(11) DEFAULT 1,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Stock (per branch)
CREATE TABLE IF NOT EXISTS `product_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT 1,
  `quantity` float DEFAULT 0,
  `buy_price` decimal(10,2) DEFAULT 0.00,
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `exp_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_branch_store` (`product_id`, `branch_id`, `store_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_exp` (`exp_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CUSTOMER MANAGEMENT (Matches E-Stock)
-- ============================================

CREATE TABLE IF NOT EXISTS `customer_classes` (
  `id` int(11) NOT NULL,  -- E-Stock customer_class_id
  `class_code` varchar(50) DEFAULT NULL,
  `class_name_ar` varchar(50) NOT NULL,
  `class_name_en` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_areas` (
  `id` int(11) NOT NULL,  -- E-Stock area_id
  `area_code` varchar(20) DEFAULT NULL,
  `area_name_ar` varchar(100) NOT NULL,
  `area_name_en` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL,  -- E-Stock customer_id (NOT AUTO_INCREMENT)
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,  -- Arabic
  `customer_name_en` varchar(100) DEFAULT NULL,
  `job_title` varchar(50) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone2` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,

  -- Classification
  `customer_class_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,

  -- Financial
  `max_credit` decimal(10,2) DEFAULT 0.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `start_balance` decimal(10,2) DEFAULT 0.00,
  `pay_rate` float DEFAULT 0,
  `pay_type` int(11) DEFAULT 1,  -- 1=cash, 2=credit, etc.

  -- Contract
  `contract_id` int(11) DEFAULT NULL,
  `insurance_code` varchar(100) DEFAULT NULL,
  `discount_local` float DEFAULT 0,
  `discount_import` float DEFAULT 0,

  -- Salesman
  `salesman_id` int(11) DEFAULT NULL,

  -- Source tracking
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `manual_code` varchar(50) DEFAULT NULL,  -- C-12345 for manual

  -- Status
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  UNIQUE KEY `manual_code` (`manual_code`),
  KEY `idx_class` (`customer_class_id`),
  KEY `idx_area` (`area_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_source` (`source`),
  KEY `idx_name` (`customer_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Contracts
CREATE TABLE IF NOT EXISTS `customer_contracts` (
  `id` int(11) NOT NULL,  -- E-Stock contract_id
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. SUPPLIER/VENDOR MANAGEMENT (Matches E-Stock)
-- ============================================

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL,  -- E-Stock vendor_id (NOT AUTO_INCREMENT)
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,  -- Arabic
  `supplier_name_en` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `company_code` varchar(50) DEFAULT NULL,  -- E-Stock company_code

  -- Employees
  `sales_rep_phone` varchar(100) DEFAULT NULL,
  `sales_rep_name` varchar(100) DEFAULT NULL,
  `area_manager_phone` varchar(100) DEFAULT NULL,
  `area_manager_name` varchar(100) DEFAULT NULL,
  `delivery_phone` varchar(100) DEFAULT NULL,
  `delivery_name` varchar(100) DEFAULT NULL,
  `collection_phone` varchar(100) DEFAULT NULL,
  `collection_name` varchar(100) DEFAULT NULL,

  -- Financial
  `max_credit` decimal(10,2) DEFAULT 0.00,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `start_balance` decimal(10,2) DEFAULT 0.00,

  -- Source tracking
  `source` enum('estock','manual') DEFAULT 'estock',
  `estock_id` decimal(18,0) DEFAULT NULL,
  `manual_code` varchar(50) DEFAULT NULL,  -- V-12345 for manual

  -- Status
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `return_policy` varchar(100) DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`),
  UNIQUE KEY `manual_code` (`manual_code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_source` (`source`),
  KEY `idx_name` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier Prices (Product Pricing per Supplier)
CREATE TABLE IF NOT EXISTS `supplier_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_price` decimal(10,2) NOT NULL,
  `sell_price` decimal(10,2) DEFAULT 0.00,
  `profit_margin` decimal(5,2) DEFAULT 0.00,
  `delivery_time_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_supplier` (`product_code`, `supplier_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery Times
CREATE TABLE IF NOT EXISTS `delivery_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `time_code` varchar(20) NOT NULL,
  `time_name` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `time_code` (`time_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. ORDER MANAGEMENT (Customer Requests)
-- ============================================

CREATE TABLE IF NOT EXISTS `order_statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(50) NOT NULL,
  `status_color` varchar(20) DEFAULT '#6c757d',
  `sort_order` int(11) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `is_final` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `final_total` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status_id`),
  KEY `idx_date` (`order_date`),
  KEY `idx_branch` (`branch_code`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,  -- Links to products.id (E-Stock)
  `product_code` varchar(50) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `discount_type` enum('percentage','fixed') DEFAULT NULL,
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_manual` tinyint(1) DEFAULT 0,  -- Manual price entry
  `needs_purchase` tinyint(1) DEFAULT 0,  -- Needs supplier purchase
  `is_manual_product` tinyint(1) DEFAULT 0,  -- Product not in E-Stock
  `manual_product_name` varchar(200) DEFAULT NULL,  -- Store original name if manual
  `supplier_name` varchar(100) DEFAULT NULL,
  `supplier_phone` varchar(20) DEFAULT NULL,
  `delivery_time` varchar(50) DEFAULT NULL,
  `purchased_at` datetime DEFAULT NULL,
  `purchased_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_needs_purchase` (`needs_purchase`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchased Items (When order item is purchased from supplier)
CREATE TABLE IF NOT EXISTS `purchased_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_item` (`order_item_id`),
  KEY `idx_supplier` (`supplier_id`),
  CONSTRAINT `fk_purchased_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
  CONSTRAINT `fk_purchased_items_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. SHIFT HANDOVER
-- ============================================

CREATE TABLE IF NOT EXISTS `shift_handovers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_date` date DEFAULT curdate(),
  `from_user` int(11) NOT NULL,
  `to_user` int(11) NOT NULL,
  `open_orders_count` int(11) DEFAULT 0,
  `urgent_orders_count` int(11) DEFAULT 0,
  `critical_notes` text DEFAULT NULL,
  `general_notes` text DEFAULT NULL,
  `is_acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_from_user` (`from_user`),
  KEY `idx_to_user` (`to_user`),
  KEY `idx_date` (`shift_date`),
  CONSTRAINT `fk_shift_from` FOREIGN KEY (`from_user`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_shift_to` FOREIGN KEY (`to_user`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. DEFAULT DATA
-- ============================================

INSERT INTO `order_statuses` (`status_name`, `status_color`, `sort_order`, `is_default`, `is_final`, `is_active`) VALUES
('تحت البحث', '#ffc107', 1, 1, 0, 1),
('جاهز', '#198754', 2, 0, 0, 1),
('تم التسليم', '#0d6efd', 3, 0, 1, 1),
('إلغي', '#dc3545', 4, 0, 1, 1);

INSERT INTO `delivery_times` (`time_code`, `time_name`, `sort_order`, `is_active`) VALUES
('1H', 'ساعة', 1, 1),
('2H', 'ساعتين', 2, 1),
('4H', '3-4 ساعات', 3, 1),
('TD', 'نفس اليوم', 4, 1),
('1D', 'يوم عمل', 5, 1),
('1W', 'أسبوع', 6, 1);

-- ============================================
-- 8. SEQUENCES FOR MANUAL CODES
-- ============================================

-- Manual product sequence (starts from 1, format: M-00001)
CREATE TABLE IF NOT EXISTS `sequence_manual_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual customer sequence (starts from 1, format: C-00001)
CREATE TABLE IF NOT EXISTS `sequence_manual_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual supplier sequence (starts from 1, format: V-00001)
CREATE TABLE IF NOT EXISTS `sequence_manual_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
