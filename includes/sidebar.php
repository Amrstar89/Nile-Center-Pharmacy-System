<?php
// Get current URI to determine correct paths
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';

// Detect project name (folder name)
$script_parts = explode('/', trim($script_name, '/'));
$project_name = '';
if (count($script_parts) > 0) {
    $project_name = $script_parts[0];
}

// Build base URL with project name
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . '://' . $host;
if (!empty($project_name)) {
    $base_url .= '/' . $project_name;
}

// Current page detection
$current_page = basename($script_name);
$current_uri = $request_uri;
$is_dashboard = strpos($current_uri, '/dashboard/') !== false;
$is_customers = strpos($current_uri, '/customers/') !== false;
$is_products = strpos($current_uri, '/products/') !== false;
$is_suppliers = strpos($current_uri, '/suppliers/') !== false;
$is_customer_requests = strpos($current_uri, '/customer-requests/') !== false;
$is_sales = strpos($current_uri, '/sales/') !== false;
$is_purchases = strpos($current_uri, '/purchases/') !== false;
$is_shifts = strpos($current_uri, '/shifts/') !== false;
$is_admin = strpos($current_uri, '/admin/') !== false;
$is_inventory = strpos($current_uri, '/inventory/') !== false;

// Purchase sub-sections
$is_purchase_dashboard = $is_purchases && (basename(dirname($script_name)) === 'purchases' && $current_page === 'index.php');
$is_purchase_orders = strpos($current_uri, '/purchases/orders/') !== false;
$is_purchase_invoices = strpos($current_uri, '/purchases/invoices/') !== false;
$is_purchase_returns = strpos($current_uri, '/purchases/returns/') !== false;
$is_purchase_reports = strpos($current_uri, '/purchases/reports/') !== false;
?>

<style>
    /* ========== SIDEBAR ========== */
    .sidebar-wrap { position: fixed; right: 0; top: 0; height: 100vh; z-index: 1000; }
    .sidebar {
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        height: 100vh;
        width: 60px;
        color: #fff;
        overflow-x: hidden;
        overflow-y: auto;
        transition: width 0.35s ease;
        position: relative;
        padding-bottom: 20px;
    }
    .sidebar-wrap:hover .sidebar { width: 260px; }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }

    .sidebar-brand {
        padding: 16px 10px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        color: #fff;
        position: sticky;
        top: 0;
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        z-index: 10;
        white-space: nowrap;
        overflow: hidden;
    }
    .sidebar-brand h4 { margin: 0; font-size: 0; transition: font-size 0.35s ease; }
    .sidebar-brand h4 .logo-icon { font-size: 26px; display: inline-block; }
    .sidebar-wrap:hover .sidebar-brand h4 { font-size: 18px; }
    .sidebar-brand small { opacity: 0; transition: opacity 0.35s ease; font-size: 11px; color: rgba(255,255,255,0.6); display: block; margin-top: 4px; }
    .sidebar-wrap:hover .sidebar-brand small { opacity: 1; }

    .nav-menu { padding: 8px 0; }

    .sidebar-heading {
        color: rgba(255,255,255,0.5);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 12px 14px 5px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: all 0.35s ease;
        white-space: nowrap;
        overflow: hidden;
    }
    .sidebar-wrap:hover .sidebar-heading { justify-content: space-between; padding: 12px 18px 5px; }
    .sidebar-heading .head-text { display: none; align-items: center; gap: 6px; }
    .sidebar-wrap:hover .sidebar-heading .head-text { display: flex; }
    .sidebar-heading .head-icon { font-size: 16px; display: flex; }
    .sidebar-wrap:hover .sidebar-heading .head-icon { display: none; }
    .sidebar-heading i.toggle-icon { font-size: 10px; transition: transform 0.3s; display: none; }
    .sidebar-wrap:hover .sidebar-heading i.toggle-icon { display: inline; }
    .sidebar-heading.collapsed i.toggle-icon { transform: rotate(-90deg); }
    .sidebar-heading:hover { color: rgba(255,255,255,0.8); }

    .sidebar-section { overflow: hidden; transition: max-height 0.3s ease; }
    .sidebar-section.collapsed { max-height: 0; }

    .nav-item { margin: 1px 0; }
    .nav-link {
        color: rgba(255,255,255,0.8);
        padding: 9px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        border-radius: 8px;
        margin: 2px 6px;
        text-decoration: none;
        font-size: 13px;
        position: relative;
        white-space: nowrap;
        overflow: hidden;
    }
    .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
    .nav-link.active { color: #fff; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .nav-link i {
        font-size: 18px;
        min-width: 28px;
        text-align: center;
        flex-shrink: 0;
    }
    .nav-link .link-text {
        display: none;
        margin-right: 8px;
        transition: opacity 0.2s;
    }
    .sidebar-wrap:hover .nav-link {
        justify-content: flex-start;
        padding: 9px 14px;
        margin: 2px 10px;
    }
    .sidebar-wrap:hover .nav-link .link-text { display: inline; }

    .nav-link.text-danger { color: #ff6b6b !important; }
    .nav-link.text-danger:hover { color: #ff4757 !important; }

    /* Tooltip on collapsed */
    .nav-link .tip {
        position: absolute;
        right: 55px;
        top: 50%;
        transform: translateY(-50%);
        background: #333;
        color: #fff;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
        z-index: 2000;
    }
    .nav-link:hover .tip { opacity: 1; }
    .sidebar-wrap:hover .nav-link .tip { display: none; }

    /* Main content shift */
    .main-content {
        margin-right: 60px !important;
        transition: margin-right 0.35s ease;
    }
    /* On screens with hover (desktop) expand margin on sidebar hover */
    @media (hover: hover) and (pointer: fine) {
        .sidebar-wrap:hover ~ .main-content,
        .sidebar-wrap:hover ~ * .main-content,
        .sidebar-wrap:hover ~ * * .main-content {
            margin-right: 260px !important;
        }
    }
    /* When sidebar is expanded, main-content gets 260px */
    body:has(.sidebar-wrap:hover) .main-content {
        margin-right: 260px !important;
    }

    /* Top header adjustment */
    .top-header { margin-right: 0 !important; }

    /* Print hide sidebar */
    @media print { .sidebar-wrap { display: none !important; } .main-content { margin-right: 0 !important; } }

    /* Mobile */
    @media (max-width: 768px) {
        .sidebar-wrap { width: 100%; position: relative; height: auto; }
        .sidebar { width: 100%; height: auto; }
        .main-content { margin-right: 0 !important; }
    }
</style>

<div class="sidebar-wrap">
<div class="sidebar">
    <div class="sidebar-brand">
        <h4><span class="logo-icon"><i class="bi bi-capsule"></i></span> <span class="brand-text">نايل سنتر</span></h4>
        <small>نظام إدارة الصيدلية</small>
    </div>
    <div class="nav-menu">

        <!-- الرئيسية -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="الرئيسية"><i class="bi bi-speedometer2"></i></span>
            <span class="head-text"><i class="bi bi-speedometer2"></i> الرئيسية</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/dashboard/" class="nav-link <?= $is_dashboard ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i><span class="link-text">لوحة التحكم</span><span class="tip">لوحة التحكم</span>
                </a>
            </div>
        </div>

        <!-- المبيعات -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="المبيعات"><i class="bi bi-cash-register"></i></span>
            <span class="head-text"><i class="bi bi-cash-register"></i> المبيعات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/" class="nav-link <?= $is_sales && $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i><span class="link-text">قائمة المبيعات</span><span class="tip">قائمة المبيعات</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/create.php" class="nav-link <?= $is_sales && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i><span class="link-text">فاتورة بيع جديدة</span><span class="tip">فاتورة بيع</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/pos.php" class="nav-link <?= $is_sales && $current_page === 'pos.php' ? 'active' : '' ?>">
                    <i class="bi bi-cart-check"></i><span class="link-text">نقطة البيع (POS)</span><span class="tip">POS</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/invoices.php" class="nav-link <?= $is_sales && $current_page === 'invoices.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i><span class="link-text">فواتير البيع</span><span class="tip">فواتير البيع</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/returns/" class="nav-link <?= $is_sales && strpos($current_uri, '/sales/returns/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-arrow-return-left"></i><span class="link-text">مرتجعات المبيعات</span><span class="tip">مرتجعات البيع</span>
                </a>
            </div>
        </div>

        <!-- المشتريات -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="المشتريات"><i class="bi bi-cart-plus"></i></span>
            <span class="head-text"><i class="bi bi-cart-plus"></i> المشتريات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/" class="nav-link <?= $is_purchases && basename(dirname($script_name)) === 'purchases' && $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i><span class="link-text">Dashboard</span><span class="tip">Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/orders/" class="nav-link <?= $is_purchase_orders && $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text"></i><span class="link-text">أوامر الشراء</span><span class="tip">أوامر الشراء</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/orders/create.php" class="nav-link <?= $is_purchase_orders && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i><span class="link-text">أمر شراء جديد</span><span class="tip">أمر شراء</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/invoices/" class="nav-link <?= $is_purchase_invoices && $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i><span class="link-text">فواتير الشراء</span><span class="tip">فواتير الشراء</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/invoices/create.php" class="nav-link <?= $is_purchase_invoices && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i><span class="link-text">فاتورة شراء جديدة</span><span class="tip">فاتورة شراء</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/returns/" class="nav-link <?= $is_purchase_returns && $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-return-left"></i><span class="link-text">مرتجعات المشتريات</span><span class="tip">مرتجعات</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/returns/create.php" class="nav-link <?= $is_purchase_returns && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i><span class="link-text">مرتجع جديد</span><span class="tip">مرتجع جديد</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/reports/" class="nav-link <?= $is_purchase_reports ? 'active' : '' ?>">
                    <i class="bi bi-graph-up"></i><span class="link-text">تقارير المشتريات</span><span class="tip">تقارير</span>
                </a>
            </div>
        </div>

        <!-- طلبات العملاء -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="طلبات العملاء"><i class="bi bi-cart"></i></span>
            <span class="head-text"><i class="bi bi-cart"></i> طلبات العملاء</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/orders.php" class="nav-link <?= $is_customer_requests && $current_page === 'orders.php' ? 'active' : '' ?>">
                    <i class="bi bi-list-task"></i><span class="link-text">متابعة الطلبات</span><span class="tip">متابعة الطلبات</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/new-order.php" class="nav-link <?= $is_customer_requests && $current_page === 'new-order.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i><span class="link-text">طلب جديد</span><span class="tip">طلب جديد</span>
                </a>
            </div>
        </div>

        <!-- الشيفتات -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="الشيفتات"><i class="bi bi-arrow-left-right"></i></span>
            <span class="head-text"><i class="bi bi-arrow-left-right"></i> الشيفتات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/shifts/" class="nav-link <?= $is_shifts ? 'active' : '' ?>">
                    <i class="bi bi-box-arrow-in-down"></i><span class="link-text">استلام / تسليم الشيفت</span><span class="tip">الشيفتات</span>
                </a>
            </div>
        </div>

        <!-- كارت العملاء -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="كارت العملاء"><i class="bi bi-people"></i></span>
            <span class="head-text"><i class="bi bi-people"></i> كارت العملاء</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customers/index.php" class="nav-link <?= $is_customers && in_array($current_page, ['index.php', 'view.php', 'edit.php', 'statement.php']) ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i><span class="link-text">قائمة العملاء</span><span class="tip">قائمة العملاء</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customers/create.php" class="nav-link <?= $is_customers && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-plus"></i><span class="link-text">إضافة عميل</span><span class="tip">إضافة عميل</span>
                </a>
            </div>
        </div>

        <!-- كارت الأصناف -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="كارت الأصناف"><i class="bi bi-boxes"></i></span>
            <span class="head-text"><i class="bi bi-boxes"></i> كارت الأصناف</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/products/index.php" class="nav-link <?= $is_products && in_array($current_page, ['index.php', 'view.php', 'edit.php']) ? 'active' : '' ?>">
                    <i class="bi bi-boxes"></i><span class="link-text">قائمة الأصناف</span><span class="tip">قائمة الأصناف</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/products/create.php" class="nav-link <?= $is_products && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-square"></i><span class="link-text">إضافة صنف</span><span class="tip">إضافة صنف</span>
                </a>
            </div>
        </div>

        <!-- كارت الموردين -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="كارت الموردين"><i class="bi bi-truck"></i></span>
            <span class="head-text"><i class="bi bi-truck"></i> كارت الموردين</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/suppliers/index.php" class="nav-link <?= $is_suppliers && in_array($current_page, ['index.php', 'view.php', 'edit.php', 'statement.php']) ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i><span class="link-text">قائمة الموردين</span><span class="tip">قائمة الموردين</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/suppliers/create.php" class="nav-link <?= $is_suppliers && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-lg"></i><span class="link-text">إضافة مورد</span><span class="tip">إضافة مورد</span>
                </a>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- إدارة المخازن -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="المخازن"><i class="bi bi-box-seam"></i></span>
            <span class="head-text"><i class="bi bi-box-seam"></i> إدارة المخازن</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/stores/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/stores/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-building"></i><span class="link-text">المخازن</span><span class="tip">المخازن</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/transfers/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/transfers/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-arrow-left-right"></i><span class="link-text">التحويلات</span><span class="tip">التحويلات</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/adjustments/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/adjustments/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-clipboard-check"></i><span class="link-text">الجرد والتسويات</span><span class="tip">الجرد</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/opening_balance/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/opening_balance/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-journal-plus"></i><span class="link-text">الرصيد الافتتاحي</span><span class="tip">رصيد افتتاحي</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/price_adjustment/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/price_adjustment/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-currency-exchange"></i><span class="link-text">تعديل الأرصدة والأسعار</span><span class="tip">تعديل أسعار</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/product_movement/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/product_movement/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-graph-up-arrow"></i><span class="link-text">حركة الأصناف</span><span class="tip">حركة الأصناف</span>
                </a>
            </div>
        </div>

        <!-- الإدارة -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span class="head-icon" title="الإدارة"><i class="bi bi-gear"></i></span>
            <span class="head-text"><i class="bi bi-gear"></i> الإدارة</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/status-manager.php" class="nav-link <?= $current_page === 'status-manager.php' ? 'active' : '' ?>">
                    <i class="bi bi-sliders"></i><span class="link-text">إدارة الحالات</span><span class="tip">إدارة الحالات</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/activity-log.php" class="nav-link <?= $current_page === 'activity-log.php' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i><span class="link-text">سجل الحركة</span><span class="tip">سجل الحركة</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/branches.php" class="nav-link <?= $is_admin && $current_page === 'branches.php' ? 'active' : '' ?>">
                    <i class="bi bi-building"></i><span class="link-text">إدارة الفروع</span><span class="tip">الفروع</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/users.php" class="nav-link <?= $is_admin && $current_page === 'users.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i><span class="link-text">إدارة المستخدمين</span><span class="tip">المستخدمين</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/delivery-times.php" class="nav-link <?= $is_admin && $current_page === 'delivery-times.php' ? 'active' : '' ?>">
                    <i class="bi bi-clock"></i><span class="link-text">أوقات التوفير</span><span class="tip">أوقات التوفير</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/sync-estock.php" class="nav-link <?= $current_page === 'sync-estock.php' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-repeat"></i><span class="link-text">مزامنة e-Stock</span><span class="tip">مزامنة e-Stock</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- تسجيل الخروج -->
        <div class="nav-item mt-4">
            <a href="<?= $base_url ?>/index.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i><span class="link-text">تسجيل الخروج</span><span class="tip">خروج</span>
            </a>
        </div>
    </div>
</div>
</div>

<script>
    function toggleSection(heading) {
        const section = heading.nextElementSibling;
        const isCollapsed = section.classList.contains('collapsed');
        if (isCollapsed) {
            section.classList.remove('collapsed');
            heading.classList.remove('collapsed');
            section.style.maxHeight = section.scrollHeight + 'px';
        } else {
            section.classList.add('collapsed');
            heading.classList.add('collapsed');
            section.style.maxHeight = '0';
        }
        saveAccordionState();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const sections = document.querySelectorAll('.sidebar-section');
        const headings = document.querySelectorAll('.sidebar-heading');
        const savedState = localStorage.getItem('sidebarAccordionState');

        if (savedState) {
            // User has a saved preference - use it
            const states = JSON.parse(savedState);
            headings.forEach((heading, index) => {
                if (states[index]) {
                    heading.classList.remove('collapsed');
                    sections[index].classList.remove('collapsed');
                    sections[index].style.maxHeight = sections[index].scrollHeight + 'px';
                } else {
                    heading.classList.add('collapsed');
                    sections[index].classList.add('collapsed');
                    sections[index].style.maxHeight = '0';
                }
            });
        } else {
            // No saved state: collapse ALL sections by default
            sections.forEach((section, index) => {
                headings[index].classList.add('collapsed');
                section.classList.add('collapsed');
                section.style.maxHeight = '0';
            });
        }

        // Always expand the section containing the active link
        const activeLink = document.querySelector('.nav-link.active');
        if (activeLink) {
            const activeSection = activeLink.closest('.sidebar-section');
            if (activeSection) {
                activeSection.classList.remove('collapsed');
                activeSection.style.maxHeight = activeSection.scrollHeight + 'px';
                activeSection.previousElementSibling.classList.remove('collapsed');
            }
        }
    });

    function saveAccordionState() {
        const headings = document.querySelectorAll('.sidebar-heading');
        const states = Array.from(headings).map(h => !h.classList.contains('collapsed'));
        localStorage.setItem('sidebarAccordionState', JSON.stringify(states));
    }
</script>