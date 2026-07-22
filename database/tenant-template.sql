-- ╔══════════════════════════════════════════════════════════════════════════╗
-- ║          NILE CENTER ERP v3.0 - TENANT DATABASE TEMPLATE                ║
-- ║          (Auto-generated when creating a new tenant)                    ║
-- ╚══════════════════════════════════════════════════════════════════════════╝

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Core authentication and user management
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('admin','manager','cashier','pharmacist','user') NOT NULL DEFAULT 'user',
  `branch_id` INT(11) UNSIGNED DEFAULT NULL,
  `store_id` INT(11) UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `login_count` INT(11) NOT NULL DEFAULT 0,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user (password: admin123 - CHANGE IMMEDIATELY)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@nilecenter.com', 'admin');

-- Branches
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_name` VARCHAR(100) NOT NULL,
  `branch_code` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `manager_name` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_main` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores/Warehouses
CREATE TABLE IF NOT EXISTS `stores` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_name` VARCHAR(100) NOT NULL,
  `store_code` VARCHAR(20) DEFAULT NULL,
  `branch_id` INT(11) UNSIGNED DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Categories
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `parent_id` INT(11) UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Companies/Manufacturers
CREATE TABLE IF NOT EXISTS `product_companies` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(100) NOT NULL,
  `country` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Types
CREATE TABLE IF NOT EXISTS `product_types` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Units of Measurement
CREATE TABLE IF NOT EXISTS `product_units` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_name` VARCHAR(50) NOT NULL,
  `unit_code` VARCHAR(10) DEFAULT NULL,
  `conversion_factor` DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_code` VARCHAR(50) DEFAULT NULL,
  `barcode` VARCHAR(50) DEFAULT NULL,
  `barcode2` VARCHAR(50) DEFAULT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `scientific_name` VARCHAR(200) DEFAULT NULL,
  `category_id` INT(11) UNSIGNED DEFAULT NULL,
  `company_id` INT(11) UNSIGNED DEFAULT NULL,
  `type_id` INT(11) UNSIGNED DEFAULT NULL,
  `unit_id` INT(11) UNSIGNED DEFAULT NULL,
  `purchase_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `sale_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `wholesale_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `avg_purchase_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `min_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `reorder_point` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `reorder_quantity` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
  `expiry_alert_days` INT(11) NOT NULL DEFAULT 30,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_suspended` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_code` (`product_code`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_category` (`category_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Items (stock tracking)
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `branch_id` INT(11) UNSIGNED DEFAULT NULL,
  `store_id` INT(11) UNSIGNED DEFAULT NULL,
  `batch_number` VARCHAR(50) DEFAULT NULL,
  `quantity` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `expiry_date` DATE DEFAULT NULL,
  `manufacturing_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory Transactions
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `store_id` INT(11) UNSIGNED DEFAULT NULL,
  `branch_id` INT(11) UNSIGNED DEFAULT NULL,
  `type` ENUM('purchase','sale','return','adjustment','transfer_in','transfer_out','opening_balance','damage') NOT NULL,
  `quantity` DECIMAL(12,4) NOT NULL,
  `unit_cost` DECIMAL(12,4) DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT(11) UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_type` (`type`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) DEFAULT NULL,
  `customer_name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `phone2` VARCHAR(20) DEFAULT NULL,
  `whatsapp` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `address2` TEXT DEFAULT NULL,
  `area_id` INT(11) UNSIGNED DEFAULT NULL,
  `governorate_id` INT(11) UNSIGNED DEFAULT NULL,
  `class_id` INT(11) UNSIGNED DEFAULT NULL,
  `max_credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `max_credit_days` INT(11) NOT NULL DEFAULT 0,
  `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `birth_date` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Classes
CREATE TABLE IF NOT EXISTS `customer_classes` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_name` VARCHAR(50) NOT NULL,
  `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Balances
CREATE TABLE IF NOT EXISTS `customer_balances` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) UNSIGNED NOT NULL,
  `balance` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `total_debit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `total_credit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Transactions
CREATE TABLE IF NOT EXISTS `customer_transactions` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) UNSIGNED NOT NULL,
  `type` VARCHAR(20) NOT NULL COMMENT 'debit|credit|payment|return',
  `amount` DECIMAL(15,4) NOT NULL,
  `balance_after` DECIMAL(15,4) NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT(11) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_name` VARCHAR(200) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `phone2` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `tax_number` VARCHAR(50) DEFAULT NULL,
  `commercial_registration` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sale Invoices
CREATE TABLE IF NOT EXISTS `sale_invoices` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL,
  `customer_id` INT(11) UNSIGNED DEFAULT NULL,
  `branch_id` INT(11) UNSIGNED DEFAULT NULL,
  `store_id` INT(11) UNSIGNED DEFAULT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'cash',
  `subtotal` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `discount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `tax` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `final_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `paid_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `remaining_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `total_profit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'confirmed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_number` (`invoice_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sale Invoice Items
CREATE TABLE IF NOT EXISTS `sale_invoice_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `product_code` VARCHAR(50) DEFAULT NULL,
  `quantity` DECIMAL(12,4) NOT NULL,
  `unit_price` DECIMAL(12,4) NOT NULL,
  `discount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total` DECIMAL(15,4) NOT NULL,
  `cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `profit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `batch_id` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Invoices
CREATE TABLE IF NOT EXISTS `purchase_invoices` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL,
  `supplier_id` INT(11) UNSIGNED NOT NULL,
  `branch_id` INT(11) UNSIGNED DEFAULT NULL,
  `store_id` INT(11) UNSIGNED DEFAULT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `discount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `tax` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `final_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `paid_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `remaining_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
  `notes` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'confirmed',
  `invoice_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Invoice Items
CREATE TABLE IF NOT EXISTS `purchase_invoice_items` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT(11) UNSIGNED NOT NULL,
  `product_id` INT(11) UNSIGNED NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `quantity` DECIMAL(12,4) NOT NULL,
  `unit_price` DECIMAL(12,4) NOT NULL,
  `discount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total` DECIMAL(15,4) NOT NULL,
  `batch_number` VARCHAR(50) DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `user_name` VARCHAR(100) DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `table_name` VARCHAR(50) DEFAULT NULL,
  `record_id` INT(11) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schema Migrations tracking
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch` INT(11) NOT NULL DEFAULT 1,
  `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default data
INSERT INTO `branches` (`branch_name`, `branch_code`, `is_main`) VALUES
('الفرع الرئيسي', 'MAIN', 1);

INSERT INTO `stores` (`store_name`, `store_code`, `branch_id`, `is_default`) VALUES
('المخزن الرئيسي', 'MAIN', 1, 1);

INSERT INTO `product_categories` (`category_name`) VALUES
('أدوية'), ('مستحضرات تجميل'), ('مكملات غذائية'), ('مستلزمات طبية'), ('عناية شخصية'), ('أخرى');

INSERT INTO `product_types` (`type_name`) VALUES
('أصلي'), ('بديل'), ('مستورد'), ('محلي');

INSERT INTO `product_units` (`unit_name`, `unit_code`, `conversion_factor`) VALUES
('قطعة', 'PC', 1.0000), ('علبة', 'BOX', 1.0000), ('شريط', 'STRIP', 1.0000), ('كرتونة', 'CTN', 1.0000);

INSERT INTO `customer_classes` (`class_name`, `discount_percent`) VALUES
('عادي', 0.00), ('VIP', 5.00), ('موظف', 10.00), ('تاجر', 15.00);

-- Areas and Governorates
CREATE TABLE IF NOT EXISTS `governorates` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `governorate_name_ar` VARCHAR(100) NOT NULL,
  `governorate_name_en` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `areas` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `area_name_ar` VARCHAR(100) NOT NULL,
  `area_name_en` VARCHAR(100) DEFAULT NULL,
  `governorate_id` INT(11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `areas` (`area_name_ar`) VALUES
('غير محدد');

SET FOREIGN_KEY_CHECKS = 1;
