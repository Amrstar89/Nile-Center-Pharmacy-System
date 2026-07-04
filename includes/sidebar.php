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
?>

<style>
    .sidebar { 
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); 
        min-height: 100vh; 
        position: fixed; 
        right: 0; 
        top: 0; 
        width: 260px; 
        z-index: 1000; 
        color: #fff; 
        overflow-y: auto;
        overflow-x: hidden;
        max-height: 100vh;
        padding-bottom: 20px;
    }
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 3px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }
    .sidebar-brand { 
        padding: 20px; 
        text-align: center; 
        border-bottom: 1px solid rgba(255,255,255,0.1); 
        color: #fff; 
        position: sticky;
        top: 0;
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        z-index: 10;
    }
    .sidebar-brand h4 { margin: 0; font-size: 20px; }
    .sidebar-brand small { color: rgba(255,255,255,0.6); font-size: 12px; }
    .nav-menu { padding: 10px 0; }
    .sidebar-heading { 
        color: rgba(255,255,255,0.5); 
        font-size: 11px; 
        text-transform: uppercase; 
        letter-spacing: 1px; 
        padding: 15px 20px 5px; 
        font-weight: 600; 
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: color 0.3s;
    }
    .sidebar-heading:hover { color: rgba(255,255,255,0.8); }
    .sidebar-heading i.toggle-icon { font-size: 10px; transition: transform 0.3s; }
    .sidebar-heading.collapsed i.toggle-icon { transform: rotate(-90deg); }
    .sidebar-section { overflow: hidden; transition: max-height 0.3s ease; }
    .sidebar-section.collapsed { max-height: 0; }
    .nav-item { margin: 2px 0; }
    .nav-link { 
        color: rgba(255,255,255,0.8); 
        padding: 10px 20px; 
        display: flex; 
        align-items: center; 
        transition: all 0.3s; 
        border-radius: 8px; 
        margin: 2px 10px; 
        text-decoration: none; 
        font-size: 14px;
    }
    .nav-link:hover { 
        color: #fff; 
        background: rgba(255,255,255,0.1); 
    }
    .nav-link.active { 
        color: #fff; 
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
    }
    .nav-link i { 
        margin-left: 10px; 
        font-size: 16px; 
        color: rgba(255,255,255,0.7); 
        width: 20px;
        text-align: center;
    }
    .nav-link:hover i { color: #fff; }
    .nav-link.active i { color: #fff; }
    .nav-link.text-danger { color: #ff6b6b !important; }
    .nav-link.text-danger:hover { color: #ff4757 !important; }
    .nav-link.text-danger i { color: #ff6b6b !important; }
    @media (max-width: 768px) { 
        .sidebar { width: 100%; position: relative; max-height: none; } 
    }
</style>

<div class="sidebar">
    <div class="sidebar-brand">
        <h4><i class="bi bi-capsule"></i> نايل سنتر</h4>
        <small>نظام إدارة الصيدلية</small>
    </div>
    <div class="nav-menu">

        <!-- الرئيسية -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-speedometer2"></i> الرئيسية</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/dashboard/" class="nav-link <?= $is_dashboard ? 'active' : '' ?>">
                    <i class="bi bi-house-door"></i> لوحة التحكم
                </a>
            </div>
        </div>

        <!-- المبيعات -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-cash-register"></i> المبيعات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/" class="nav-link <?= $is_sales && $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i> قائمة المبيعات
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/create.php" class="nav-link <?= $is_sales && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i> فاتورة بيع جديدة
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/sales/pos.php" class="nav-link <?= $is_sales && $current_page === 'pos.php' ? 'active' : '' ?>">
                    <i class="bi bi-cart-check"></i> نقطة البيع (POS)
                </a>
            </div>
        </div>

        <!-- المشتريات -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-cart-plus"></i> المشتريات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/purchases/" class="nav-link <?= $is_purchases ? 'active' : '' ?>">
                    <i class="bi bi-shop"></i> شاشة المشتريات
                </a>
            </div>
        </div>

        <!-- طلبات العملاء -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-cart"></i> طلبات العملاء</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/orders.php" class="nav-link <?= $is_customer_requests && $current_page === 'orders.php' ? 'active' : '' ?>">
                    <i class="bi bi-list-task"></i> متابعة الطلبات
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/new-order.php" class="nav-link <?= $is_customer_requests && $current_page === 'new-order.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle"></i> طلب جديد
                </a>
            </div>
        </div>

        <!-- الشيفتات -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-arrow-left-right"></i> الشيفتات</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/shifts/" class="nav-link <?= $is_shifts ? 'active' : '' ?>">
                    <i class="bi bi-box-arrow-in-down"></i> استلام / تسليم الشيفت
                </a>
            </div>
        </div>

        <!-- كارت العملاء -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-people"></i> كارت العملاء</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customers/index.php" class="nav-link <?= $is_customers && in_array($current_page, ['index.php', 'view.php', 'edit.php', 'statement.php']) ? 'active' : '' ?>">
                    <i class="bi bi-people-fill"></i> قائمة العملاء
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customers/create.php" class="nav-link <?= $is_customers && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-plus"></i> إضافة عميل
                </a>
            </div>
        </div>

        <!-- كارت الأصناف -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-boxes"></i> كارت الأصناف</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/products/index.php" class="nav-link <?= $is_products && in_array($current_page, ['index.php', 'view.php', 'edit.php']) ? 'active' : '' ?>">
                    <i class="bi bi-boxes"></i> قائمة الأصناف
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/products/create.php" class="nav-link <?= $is_products && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-square"></i> إضافة صنف
                </a>
            </div>
        </div>

        <!-- كارت الموردين -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-truck"></i> كارت الموردين</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/suppliers/index.php" class="nav-link <?= $is_suppliers && in_array($current_page, ['index.php', 'view.php', 'edit.php', 'statement.php']) ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i> قائمة الموردين
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/suppliers/create.php" class="nav-link <?= $is_suppliers && $current_page === 'create.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-lg"></i> إضافة مورد
                </a>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- إدارة المخازن -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-box-seam"></i> إدارة المخازن</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/inventory/stores/index.php" class="nav-link <?= $is_inventory && strpos($current_uri, '/inventory/stores/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-building"></i> المخازن
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

        <!-- الإدارة -->
        <div class="sidebar-heading" onclick="toggleSection(this)">
            <span><i class="bi bi-gear"></i> الإدارة</span>
            <i class="bi bi-chevron-down toggle-icon"></i>
        </div>
        <div class="sidebar-section">
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/status-manager.php" class="nav-link <?= $current_page === 'status-manager.php' ? 'active' : '' ?>">
                    <i class="bi bi-sliders"></i> إدارة الحالات
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/modules/customer-requests/activity-log.php" class="nav-link <?= $current_page === 'activity-log.php' ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i> سجل الحركة
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/branches.php" class="nav-link <?= $is_admin && $current_page === 'branches.php' ? 'active' : '' ?>">
                    <i class="bi bi-building"></i> إدارة الفروع
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/users.php" class="nav-link <?= $is_admin && $current_page === 'users.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> إدارة المستخدمين
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/delivery-times.php" class="nav-link <?= $is_admin && $current_page === 'delivery-times.php' ? 'active' : '' ?>">
                    <i class="bi bi-clock"></i> أوقات التوفير
                </a>
            </div>
            <div class="nav-item">
                <a href="<?= $base_url ?>/admin/sync-estock.php" class="nav-link <?= $current_page === 'sync-estock.php' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-repeat"></i> مزامنة e-Stock
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- تسجيل الخروج -->
        <div class="nav-item mt-4">
            <a href="<?= $base_url ?>/index.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
            </a>
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
            sections.forEach(section => {
                section.style.maxHeight = section.scrollHeight + 'px';
            });
        }

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
