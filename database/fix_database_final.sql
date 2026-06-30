-- ============================================
-- SQL UPDATE FINAL - يتوافق مع قاعدة البيانات الحالية
-- تاريخ: 2026-07-01
-- ============================================
-- الخطوات:
-- 1. اطفي فحص Foreign Keys
-- 2. احذف Index (مش Foreign Key فعلياً)
-- 3. احذف عمود parent_store_id
-- 4. ضيف is_main_store
-- 5. ضيف total_cost في inventory_items
-- 6. احسب القيم
-- 7. رجّع الفحص
-- ============================================

-- 1) اطفي فحص Foreign Keys مؤقتاً
SET FOREIGN_KEY_CHECKS = 0;

-- 2) احذف الـ Index المرتبط بالعمود (لو موجود)
DROP INDEX IF EXISTS idx_store_parent ON stores;

-- 3) احذف عمود parent_store_id
ALTER TABLE stores DROP COLUMN IF EXISTS parent_store_id;

-- 4) ضيف عمود is_main_store
ALTER TABLE stores ADD COLUMN IF NOT EXISTS is_main_store TINYINT(1) DEFAULT 0 AFTER store_category;

-- 5) حدّث المخازن الرئيسية (store_type = 'main' → is_main_store = 1)
UPDATE stores SET is_main_store = 1 WHERE store_type = 'main';

-- 6) ضيف عمود total_cost في inventory_items
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS total_cost DECIMAL(12,2) DEFAULT 0.00 AFTER unit_cost;

-- 7) احسب total_cost = quantity * unit_cost
UPDATE inventory_items SET total_cost = quantity * unit_cost WHERE total_cost = 0 OR total_cost IS NULL;

-- 8) رجّع فحص Foreign Keys
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- تأكيد: شوف الأعمدة الجديدة
-- ============================================
SELECT '=== stores table ===' AS info;
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME IN ('is_main_store', 'store_category');

SELECT '=== inventory_items table ===' AS info;
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items' AND COLUMN_NAME = 'total_cost';
