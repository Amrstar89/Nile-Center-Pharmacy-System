-- ============================================================
-- إصلاح الأعمدة المفقودة - SQL آمن (يشتغل خطوة بخطوة)
-- افتح phpMyAdmin → اختار داتابيز nile_center → تبويب SQL
-- انسخ كل سطر وشغله لوحده عشان تشوف كل خطوة
-- ============================================================

-- ============================================================
-- الجزء 1: جدول stores
-- ============================================================

-- 1A. إضافة عمود store_category لو مش موجود
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'store_category');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE stores ADD COLUMN store_category VARCHAR(50) NULL AFTER store_type', 'SELECT "store_category already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1B. إضافة عمود is_main_store لو مش موجود
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' AND COLUMN_NAME = 'is_main_store');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE stores ADD COLUMN is_main_store TINYINT(1) NOT NULL DEFAULT 0', 'SELECT "is_main_store already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1C. تحويل store_type لـ ENUM (لو لسه نوعه القديم)
ALTER TABLE stores MODIFY COLUMN store_type ENUM('main','sub') NOT NULL DEFAULT 'sub';

-- 1D. تحويل البيانات القديمة
UPDATE stores SET store_type = 'main' WHERE store_type IN ('central_main', 'branch_main');
UPDATE stores SET store_type = 'sub' WHERE store_type NOT IN ('main', 'sub');

-- 1E. تعبئة store_category
UPDATE stores SET store_category = 'warehouse' WHERE store_type = 'sub' AND (store_category IS NULL OR store_category = '');
UPDATE stores SET store_category = 'main_store' WHERE store_type = 'main' AND (store_category IS NULL OR store_category = '');

-- 1F. تعبئة is_main_store (أول مخزن في كل فرع)
UPDATE stores SET is_main_store = 0;
SET @rank = 0;
SET @branch = 0;
UPDATE stores s1
JOIN (
    SELECT id, branch_id,
           @rank := IF(@branch = branch_id, @rank + 1, 1) AS rn,
           @branch := branch_id
    FROM stores
    ORDER BY branch_id, id
) s2 ON s1.id = s2.id
SET s1.is_main_store = IF(s2.rn = 1, 1, 0);

-- ============================================================
-- الجزء 2: جدول inventory_items - إضافة total_cost لو مش موجود
-- ============================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items' AND COLUMN_NAME = 'total_cost');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE inventory_items ADD COLUMN total_cost DECIMAL(12,2) NULL DEFAULT 0', 'SELECT "total_cost already exists" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- الجزء 3: إنشاء الجداول الجديدة
-- ============================================================

CREATE TABLE IF NOT EXISTS price_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_code VARCHAR(50) NOT NULL UNIQUE,
    store_id INT NOT NULL,
    adjustment_type ENUM('cost','sell','profit_margin','all') NOT NULL DEFAULT 'all',
    notes TEXT,
    status ENUM('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
    total_items INT NOT NULL DEFAULT 0,
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    INDEX idx_store (store_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS price_adjustment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adjustment_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NULL,
    old_cost DECIMAL(12,2),
    new_cost DECIMAL(12,2),
    old_sell DECIMAL(12,2),
    new_sell DECIMAL(12,2),
    old_profit_margin DECIMAL(5,2),
    new_profit_margin DECIMAL(5,2),
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (adjustment_id) REFERENCES price_adjustments(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_movement_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_id INT NULL,
    store_id INT NOT NULL,
    movement_type ENUM('purchase','sale','transfer_in','transfer_out','adjustment','opening_balance','return_in','return_out','price_change') NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2),
    unit_price DECIMAL(12,2),
    notes TEXT,
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_store (store_id),
    INDEX idx_movement (movement_type),
    INDEX idx_date (created_at),
    INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT '=== All fixes applied successfully! ===' AS result;
