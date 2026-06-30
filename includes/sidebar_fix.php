<?php
// ===========================================
// تعديل على ملف includes/sidebar.php
// استبدل قسم "إدارة المخازن" بالكامل بالكود التالي
// ===========================================
?>

<!-- إدارة المخازن -->
<div class="sidebar-heading" onclick="toggleSection(this)">
    <span><i class="bi bi-box-seam"></i> إدارة المخازن</span>
    <i class="bi bi-chevron-down toggle-icon"></i>
</div>
<div class="sidebar-section">
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/stores/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/stores/') !== false && in_array($current_page, ['index.php', 'view.php', 'edit.php']) ? 'active' : '' ?>">
            <i class="bi bi-building"></i> المخازن
        </a>
    </div>
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/stores/create.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/stores/') !== false && $current_page === 'create.php' ? 'active' : '' ?>">
            <i class="bi bi-plus-lg"></i> إضافة مخزن
        </a>
    </div>
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/transfers/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/transfers/') !== false ? 'active' : '' ?>">
            <i class="bi bi-arrow-left-right"></i> التحويلات
        </a>
    </div>
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/adjustments/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/adjustments/') !== false ? 'active' : '' ?>">
            <i class="bi bi-clipboard-check"></i> الجرد والتسويات
        </a>
    </div>
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/opening_balance/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/opening_balance/') !== false ? 'active' : '' ?>">
            <i class="bi bi-journal-plus"></i> الرصيد الافتتاحي
        </a>
    </div>
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/price_adjustment/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/price_adjustment/') !== false ? 'active' : '' ?>">
            <i class="bi bi-currency-exchange"></i> تعديل الأرصدة والأسعار
        </a>
    </div>
    <div class="nav-item">
        <a href="<?= $base_url ?>/modules/inventory/product_movement/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/product_movement/') !== false ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i> حركة الأصناف
        </a>
    </div>
</div>
