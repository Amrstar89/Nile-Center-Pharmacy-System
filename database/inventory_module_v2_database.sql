-- ============================================================
-- Nile Center Pharmacy System - Inventory Module v2
-- Complete Database Update
-- Generated: 2026-06-27
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- 1. DROP EXISTING INVENTORY TABLES (to rebuild properly)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `stock_adjustment_items`;
DROP TABLE IF EXISTS `stock_adjustments`;
DROP TABLE IF EXISTS `inventory_transfer_items`;
DROP TABLE IF EXISTS `inventory_transfers`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `inventory_items`;
DROP TABLE IF EXISTS `inventory_batches`;
DROP TABLE IF EXISTS `stores`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 2. CREATE STORES TABLE (with auto code generation support)
-- ============================================================

CREATE TABLE `stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_code` varchar(20) NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `store_type` enum('central_main','branch_main','sub_store','pharmacy','warehouse','damaged','expired','returned') DEFAULT 'sub_store',
  `branch_id` int(11) DEFAULT NULL,
  `parent_store_id` int(11) DEFAULT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_code` (`store_code`),
  KEY `idx_store_branch` (`branch_id`),
  KEY `idx_store_parent` (`parent_store_id`),
  KEY `idx_store_type` (`store_type`),
  KEY `idx_store_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. CREATE STORE CODE SEQUENCES (for auto generation)
-- ============================================================

CREATE TABLE `store_code_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_type` varchar(20) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `prefix` varchar(10) NOT NULL,
  `last_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_branch` (`store_type`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CREATE INVENTORY ITEMS (current stock per store)
-- ============================================================

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_store_product_batch` (`store_id`,`product_id`,`batch_id`),
  KEY `idx_inv_item_store` (`store_id`),
  KEY `idx_inv_item_product` (`product_id`),
  KEY `idx_inv_item_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. CREATE INVENTORY BATCHES (FIFO tracking)
-- ============================================================

CREATE TABLE `inventory_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batch_product` (`product_id`),
  KEY `idx_batch_store` (`store_id`),
  KEY `idx_batch_supplier` (`supplier_id`),
  KEY `idx_batch_exp` (`exp_date`),
  KEY `idx_batch_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. CREATE INVENTORY TRANSACTIONS (all movements)
-- ============================================================

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inv_tx_store` (`store_id`),
  KEY `idx_inv_tx_product` (`product_id`),
  KEY `idx_inv_tx_batch` (`batch_id`),
  KEY `idx_inv_tx_type` (`transaction_type`),
  KEY `idx_inv_tx_reference` (`reference_type`,`reference_id`),
  KEY `idx_inv_tx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. CREATE INVENTORY TRANSFERS
-- ============================================================

CREATE TABLE `inventory_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_code` (`transfer_code`),
  KEY `idx_transfer_from` (`from_store_id`),
  KEY `idx_transfer_to` (`to_store_id`),
  KEY `idx_transfer_status` (`status`),
  KEY `idx_transfer_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. CREATE INVENTORY TRANSFER ITEMS
-- ============================================================

CREATE TABLE `inventory_transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `status` enum('pending','shipped','received','rejected') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_transfer_item_transfer` (`transfer_id`),
  KEY `idx_transfer_item_product` (`product_id`),
  KEY `idx_transfer_item_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. CREATE STOCK ADJUSTMENTS
-- ============================================================

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `adjustment_code` varchar(20) NOT NULL,
  `store_id` int(11) NOT NULL,
  `adjustment_type` enum('periodic','spot','year_end','damage','expired') DEFAULT 'periodic',
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `adjustment_code` (`adjustment_code`),
  KEY `idx_adj_store` (`store_id`),
  KEY `idx_adj_status` (`status`),
  KEY `idx_adj_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. CREATE STOCK ADJUSTMENT ITEMS
-- ============================================================

CREATE TABLE `stock_adjustment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adj_item_adj` (`adjustment_id`),
  KEY `idx_adj_item_product` (`product_id`),
  KEY `idx_adj_item_batch` (`batch_id`),
  KEY `idx_adj_item_counted` (`is_counted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. ADD FOREIGN KEYS
-- ============================================================

ALTER TABLE `stores`
  ADD CONSTRAINT `fk_store_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_store_parent` FOREIGN KEY (`parent_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_store_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inv_item_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inv_tx_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_tx_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_tx_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_tx_unit` FOREIGN KEY (`unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_tx_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_transfers`
  ADD CONSTRAINT `fk_transfer_from_store` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_to_store` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_from_branch` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_to_branch` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_transfer_items`
  ADD CONSTRAINT `fk_transfer_item_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `inventory_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL;

ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `fk_adj_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

ALTER TABLE `stock_adjustment_items`
  ADD CONSTRAINT `fk_adj_item_adj` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL;

-- ============================================================
-- 12. INSERT DEFAULT STORE CODE SEQUENCES
-- ============================================================

INSERT INTO `store_code_sequences` (`store_type`, `branch_id`, `prefix`, `last_number`) VALUES
('central_main', NULL, 'CENT', 0),
('branch_main', 1, 'BR01', 0),
('branch_main', 2, 'BR02', 0),
('branch_main', 3, 'BR03', 0),
('branch_main', 4, 'BR04', 0);

-- ============================================================
-- 13. INSERT DEFAULT STORES
-- ============================================================

-- Central Main Store
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('CENT-01', 'المخزن الرئيسي المركزي', 'central_main', NULL, NULL, 1, 1, 'المخزن الرئيسي للشركة', 1);

-- Branch Main Stores
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR01-MAIN', 'مخزن فرع الشركة الرئيسي', 'branch_main', 1, 1, 1, 1, 'المخزن الرئيسي لفرع الشركة', 1),
('BR02-MAIN', 'مخزن فرع نوال الرئيسي', 'branch_main', 2, 1, 1, 1, 'المخزن الرئيسي لفرع نوال', 1),
('BR03-MAIN', 'مخزن فرع مصدق الرئيسي', 'branch_main', 3, 1, 1, 1, 'المخزن الرئيسي لفرع مصدق', 1),
('BR04-MAIN', 'مخزن فرع صيد الرئيسي', 'branch_main', 4, 1, 1, 1, 'المخزن الرئيسي لفرع صيد', 1);

-- Sub-stores for Branch 1
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR01-PHARM', 'صيدلية فرع الشركة', 'pharmacy', 1, 2, 0, 1, 'صيدلية فرع الشركة', 1),
('BR01-WH', 'مستودع فرع الشركة', 'warehouse', 1, 2, 0, 1, 'مستودع فرع الشركة', 1),
('BR01-DMG', 'مخزن التالف فرع الشركة', 'damaged', 1, 2, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR01-EXP', 'مخزن الهالك فرع الشركة', 'expired', 1, 2, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Sub-stores for Branch 2
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR02-PHARM', 'صيدلية فرع نوال', 'pharmacy', 2, 3, 0, 1, 'صيدلية فرع نوال', 1),
('BR02-WH', 'مستودع فرع نوال', 'warehouse', 2, 3, 0, 1, 'مستودع فرع نوال', 1),
('BR02-DMG', 'مخزن التالف فرع نوال', 'damaged', 2, 3, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR02-EXP', 'مخزن الهالك فرع نوال', 'expired', 2, 3, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Sub-stores for Branch 3
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR03-PHARM', 'صيدلية فرع مصدق', 'pharmacy', 3, 4, 0, 1, 'صيدلية فرع مصدق', 1),
('BR03-WH', 'مستودع فرع مصدق', 'warehouse', 3, 4, 0, 1, 'مستودع فرع مصدق', 1),
('BR03-DMG', 'مخزن التالف فرع مصدق', 'damaged', 3, 4, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR03-EXP', 'مخزن الهالك فرع مصدق', 'expired', 3, 4, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Sub-stores for Branch 4
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR04-PHARM', 'صيدلية فرع صيد', 'pharmacy', 4, 5, 0, 1, 'صيدلية فرع صيد', 1),
('BR04-WH', 'مستودع فرع صيد', 'warehouse', 4, 5, 0, 1, 'مستودع فرع صيد', 1),
('BR04-DMG', 'مخزن التالف فرع صيد', 'damaged', 4, 5, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR04-EXP', 'مخزن الهالك فرع صيد', 'expired', 4, 5, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Update sequences
UPDATE `store_code_sequences` SET `last_number` = 1 WHERE `store_type` = 'central_main' AND `branch_id` IS NULL;
UPDATE `store_code_sequences` SET `last_number` = 1 WHERE `store_type` = 'branch_main' AND `branch_id` = 1;
UPDATE `store_code_sequences` SET `last_number` = 1 WHERE `store_type` = 'branch_main' AND `branch_id` = 2;
UPDATE `store_code_sequences` SET `last_number` = 1 WHERE `store_type` = 'branch_main' AND `branch_id` = 3;
UPDATE `store_code_sequences` SET `last_number` = 1 WHERE `store_type` = 'branch_main' AND `branch_id` = 4;

COMMIT;
