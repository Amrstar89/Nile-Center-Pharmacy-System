<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$current_module = basename(dirname(dirname($_SERVER['PHP_SELF'])));

// Determine paths based on current directory
if ($current_dir === 'admin') {
    // We are in admin folder
    $modules_path = '../modules/customer-requests/';
    $products_path = '../modules/products/';
    $customers_path = '../modules/customers/';
    $admin_path = '';
    $root_path = '../';
} elseif ($current_module === 'products') {
    // We are in modules/products/
    $modules_path = '../customer-requests/';
    $products_path = '';
    $customers_path = '../customers/';
    $admin_path = '../../admin/';
    $root_path = '../../';
} elseif ($current_module === 'customers') {
    // We are in modules/customers/
    $modules_path = '../customer-requests/';
    $products_path = '../products/';
    $customers_path = '';
    $admin_path = '../../admin/';
    $root_path = '../../';
} else {
    // We are in modules/customer-requests/
    $modules_path = '';
    $products_path = '../products/';
    $customers_path = '../customers/';
    $admin_path = '../../admin/';
    $root_path = '../../';
}
?>

<div class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-capsule"></i> نايل سنتر</h4>
        <small>نظام طلبات العملاء</small>
    </div>
    <div class="nav-menu">
        <!-- Main Pages -->
        <div class="nav-item">
            <a href="<?= $modules_path ?>index.php" class="nav-link <?= $current_page === 'index.php' && $current_module === 'customer-requests' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> الرئيسية
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>new-order.php" class="nav-link <?= $current_page === 'new-order.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i> طلب جديد
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>orders.php" class="nav-link <?= $current_page === 'orders.php' ? 'active' : '' ?>">
                <i class="bi bi-list-task"></i> متابعة الطلبات
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>purchases.php" class="nav-link <?= $current_page === 'purchases.php' ? 'active' : '' ?>">
                <i class="bi bi-cart3"></i> شاشة المشتريات
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>shift-handover.php" class="nav-link <?= $current_page === 'shift-handover.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> تسليم الشيفت
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>shift-receive.php" class="nav-link <?= $current_page === 'shift-receive.php' ? 'active' : '' ?>">
                <i class="bi bi-box-arrow-in-down"></i> استلام الشيفت
            </a>
        </div>

        <!-- Customers Module -->
        <div class="sidebar-heading">كارت العملاء</div>
        <div class="nav-item">
            <a href="<?= $customers_path ?>index.php" class="nav-link <?= $current_module === 'customers' && $current_page === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> قائمة العملاء
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $customers_path ?>create.php" class="nav-link <?= $current_module === 'customers' && $current_page === 'create.php' ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i> إضافة عميل جديد
            </a>
        </div>

        <!-- Products Module -->
        <div class="sidebar-heading">كارت الأصناف</div>
        <div class="nav-item">
            <a href="<?= $products_path ?>index.php" class="nav-link <?= $current_module === 'products' && $current_page === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-boxes"></i> قائمة الأصناف
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $products_path ?>create.php" class="nav-link <?= $current_module === 'products' && $current_page === 'create.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-square"></i> إضافة صنف جديد
            </a>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Admin Pages -->
        <div class="sidebar-heading">الإدارة</div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>status-manager.php" class="nav-link <?= $current_page === 'status-manager.php' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i> إدارة الحالات
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $modules_path ?>activity-log.php" class="nav-link <?= $current_page === 'activity-log.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> سجل الحركة
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $admin_path ?>branches.php" class="nav-link <?= $current_page === 'branches.php' ? 'active' : '' ?>">
                <i class="bi bi-building"></i> إدارة الفروع
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $admin_path ?>users.php" class="nav-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> إدارة المستخدمين
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $admin_path ?>suppliers.php" class="nav-link <?= $current_page === 'suppliers.php' ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> إدارة الموردين
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $admin_path ?>delivery-times.php" class="nav-link <?= $current_page === 'delivery-times.php' ? 'active' : '' ?>">
                <i class="bi bi-clock"></i> أوقات التوفير
            </a>
        </div>
        <div class="nav-item">
            <a href="<?= $admin_path ?>products.php" class="nav-link <?= $current_page === 'products.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> إدارة الأصناف
            </a>
        </div>
        <?php endif; ?>

        <!-- Logout -->
        <div class="nav-item mt-4">
            <a href="<?= $root_path ?>index.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
            </a>
        </div>
    </div>
</div>