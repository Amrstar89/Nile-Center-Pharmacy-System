-- ============================================================
-- Nile Center Pharmacy System - Inventory Module
-- Fixes + Default Data
-- Generated: 2026-06-27
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- 1. FIX: Update inventory_batches store_id to allow NULL
-- ============================================================
ALTER TABLE `inventory_batches` 
  MODIFY `store_id` int(11) DEFAULT NULL;

-- ============================================================
-- 2. ADD FOREIGN KEYS for Inventory Module
-- ============================================================

-- stores -> branches
ALTER TABLE `stores`
  ADD CONSTRAINT `fk_store_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_store_parent` FOREIGN KEY (`parent_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_store_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- inventory_items -> stores, products, inventory_batches
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_inv_item_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL;

-- inventory_batches -> products, stores, suppliers
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_batch_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_batch_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

-- inventory_transactions -> stores, products, inventory_batches, users
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inv_tx_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_tx_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inv_tx_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_tx_unit` FOREIGN KEY (`unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_tx_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- inventory_transfers -> stores, branches, users
ALTER TABLE `inventory_transfers`
  ADD CONSTRAINT `fk_transfer_from_store` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_to_store` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_from_branch` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_to_branch` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_req_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_app_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_ship_user` FOREIGN KEY (`shipped_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_rec_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- inventory_transfer_items -> inventory_transfers, products, inventory_batches
ALTER TABLE `inventory_transfer_items`
  ADD CONSTRAINT `fk_transfer_item_transfer` FOREIGN KEY (`transfer_id`) REFERENCES `inventory_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transfer_item_unit` FOREIGN KEY (`unit_id`) REFERENCES `product_units` (`id`) ON DELETE SET NULL;

-- stock_adjustments -> stores, users
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `fk_adj_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_count_user` FOREIGN KEY (`counted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_adj_app_user` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- stock_adjustment_items -> stock_adjustments, products, inventory_batches
ALTER TABLE `stock_adjustment_items`
  ADD CONSTRAINT `fk_adj_item_adj` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_adj_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE SET NULL;

-- ============================================================
-- 3. DEFAULT DATA: Stores
-- ============================================================

-- Central Main Store (Company HQ)
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('CENT-01', 'المخزن الرئيسي المركزي', 'central_main', NULL, NULL, 1, 1, 'المخزن الرئيسي للشركة - يحتوي على جميع الأصناف', 1);

-- Branch Main Stores (one per branch)
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR01-MAIN', 'مخزن فرع الشركة الرئيسي', 'branch_main', 1, 1, 1, 1, 'المخزن الرئيسي لفرع الشركة', 1),
('BR02-MAIN', 'مخزن فرع نوال الرئيسي', 'branch_main', 2, 1, 1, 1, 'المخزن الرئيسي لفرع نوال', 1),
('BR03-MAIN', 'مخزن فرع مصدق الرئيسي', 'branch_main', 3, 1, 1, 1, 'المخزن الرئيسي لفرع مصدق', 1),
('BR04-MAIN', 'مخزن فرع صيد الرئيسي', 'branch_main', 4, 1, 1, 1, 'المخزن الرئيسي لفرع صيد', 1);

-- Sub-stores for Branch 1 (Company)
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR01-PHARM', 'صيدلية فرع الشركة', 'pharmacy', 1, 2, 0, 1, 'صيدلية فرع الشركة', 1),
('BR01-WH', 'مستودع فرع الشركة', 'warehouse', 1, 2, 0, 1, 'مستودع فرع الشركة', 1),
('BR01-DMG', 'مخزن التالف فرع الشركة', 'damaged', 1, 2, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR01-EXP', 'مخزن الهالك فرع الشركة', 'expired', 1, 2, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Sub-stores for Branch 2 (Nawal)
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR02-PHARM', 'صيدلية فرع نوال', 'pharmacy', 2, 3, 0, 1, 'صيدلية فرع نوال', 1),
('BR02-WH', 'مستودع فرع نوال', 'warehouse', 2, 3, 0, 1, 'مستودع فرع نوال', 1),
('BR02-DMG', 'مخزن التالف فرع نوال', 'damaged', 2, 3, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR02-EXP', 'مخزن الهالك فرع نوال', 'expired', 2, 3, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Sub-stores for Branch 3 (Masdaq)
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR03-PHARM', 'صيدلية فرع مصدق', 'pharmacy', 3, 4, 0, 1, 'صيدلية فرع مصدق', 1),
('BR03-WH', 'مستودع فرع مصدق', 'warehouse', 3, 4, 0, 1, 'مستودع فرع مصدق', 1),
('BR03-DMG', 'مخزن التالف فرع مصدق', 'damaged', 3, 4, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR03-EXP', 'مخزن الهالك فرع مصدق', 'expired', 3, 4, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

-- Sub-stores for Branch 4 (Sayd)
INSERT INTO `stores` (`store_code`, `store_name`, `store_type`, `branch_id`, `parent_store_id`, `is_main`, `is_active`, `notes`, `created_by`) VALUES
('BR04-PHARM', 'صيدلية فرع صيد', 'pharmacy', 4, 5, 0, 1, 'صيدلية فرع صيد', 1),
('BR04-WH', 'مستودع فرع صيد', 'warehouse', 4, 5, 0, 1, 'مستودع فرع صيد', 1),
('BR04-DMG', 'مخزن التالف فرع صيد', 'damaged', 4, 5, 0, 1, 'مخزن الأصناف التالفة', 1),
('BR04-EXP', 'مخزن الهالك فرع صيد', 'expired', 4, 5, 0, 1, 'مخزن الأصناف منتهية الصلاحية', 1);

COMMIT;
