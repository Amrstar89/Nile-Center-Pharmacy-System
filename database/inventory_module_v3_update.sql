-- ============================================================
-- تحديث هيكل جدول المخازن - إعادة تنظيم شامل V3
-- ============================================================

-- 1. إضافة عمود "تصنيف المخزن" لتحديد الغرض من المخزن
ALTER TABLE stores ADD COLUMN IF NOT EXISTS store_category VARCHAR(50) NULL AFTER store_type;

-- 2. تحديث البيانات الموجودة: تحويل الأنواع القديمة للنظام الجديد
-- المخازن الرئيسية المركزية والفرعية تبقى رئيسية
UPDATE stores SET store_type = 'main' WHERE store_type IN ('central_main', 'branch_main');
-- باقي المخازن تبقى فرعية
UPDATE stores SET store_type = 'sub' WHERE store_type NOT IN ('main', 'sub');

-- 3. تعبئة تصنيف المخازن من الأنواع القديمة
UPDATE stores SET store_category = 'warehouse' WHERE store_type = 'sub' AND (store_name LIKE '%مستودع%' OR store_name LIKE '%warehouse%');
UPDATE stores SET store_category = 'expired' WHERE store_type = 'sub' AND (store_name LIKE '%هالك%' OR store_name LIKE '%منتهي%' OR store_name LIKE '%expired%');
UPDATE stores SET store_category = 'damaged' WHERE store_type = 'sub' AND (store_name LIKE '%تالف%' OR store_name LIKE '%damaged%');
UPDATE stores SET store_category = 'surplus' WHERE store_type = 'sub' AND (store_name LIKE '%فائض%' OR store_name LIKE '%surplus%');
-- المخازن اللي لسه مالهاش تصنيف نحطها "مستودع" افتراضي
UPDATE stores SET store_category = 'warehouse' WHERE store_type = 'sub' AND store_category IS NULL;
-- المخازن الرئيسية تصنيفها "main_store"
UPDATE stores SET store_category = 'main_store' WHERE store_type = 'main';

-- 4. حذف الأعمدة القديمة اللي مش محتاجينها
ALTER TABLE stores DROP COLUMN IF EXISTS parent_store_id;
ALTER TABLE stores DROP COLUMN IF EXISTS is_main;

-- 5. إضافة عمود is_main_store كعلم فقط (بدون تدخل من المستخدم)
ALTER TABLE stores ADD COLUMN IF NOT EXISTS is_main_store TINYINT(1) NOT NULL DEFAULT 0;

-- 6. أول مخزن في كل فرع يبقى الرئيسي
-- أولاً نحدد المخزن الرئيسي لكل فرع
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

-- 7. تحديث عمود is_active لو مش موجود
ALTER TABLE stores MODIFY COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;

-- 8. تعديل نوع store_type ليكون ENUM بسيط
ALTER TABLE stores MODIFY COLUMN store_type ENUM('main','sub') NOT NULL DEFAULT 'sub';

-- 9. إضافة قيد UNIQUE على كود المخزن لو مش موجود
ALTER TABLE stores ADD UNIQUE KEY IF NOT EXISTS uk_store_code (store_code);

-- ============================================================
-- إنشاء جدول تعديلات الأرصدة والأسعار (جديد)
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

-- ============================================================
-- إنشاء جدول حركة الأصناف
-- ============================================================
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
