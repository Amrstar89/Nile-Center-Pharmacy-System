<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/estock-bridge.php';
requireAdmin();

$synced_employees = 0;
$synced_products = 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sync_employees'])) {
        $synced_employees = syncESTOCKEmployeesToMySQL();
        $message = "تم مزامنة $synced_employees موظف جديد";
    }
    if (isset($_POST['sync_products'])) {
        $synced_products = syncESTOCKProductsToMySQL();
        $message .= ($message ? " و " : "") . "$synced_products صنف جديد";
    }

    if ($message) {
        $_SESSION['success'] = $message;
    }
    redirect(APP_URL . '/admin/sync-estock.php');
}

// Get counts
$estock_emp_count = count(getESTOCKEmployees());
$estock_prod_count = count(getESTOCKProducts());

$mysql_emp_count = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$mysql_prod_count = $db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];

$page_title = 'مزامنة ESTOCK';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h4 { color: white; margin: 0; font-weight: 700; }
        .nav-menu { padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .nav-link i { width: 25px; margin-left: 10px; font-size: 18px; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .sync-card { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 15px; padding: 25px; text-align: center; }
        .sync-card h3 { font-size: 36px; font-weight: 700; margin-bottom: 10px; }
        .sync-card p { margin: 0; opacity: 0.9; }
        .btn-sync { background: white; color: #667eea; border: none; border-radius: 10px; padding: 12px 30px; font-weight: 600; }
        .btn-sync:hover { transform: translateY(-2px); }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <h4><i class="bi bi-capsule"></i> نايل سنتر</h4>
            <small>نظام طلبات العملاء</small>
        </div>
        <div class="nav-menu">
            <div class="nav-item"><a href="../modules/customer-requests/index.php" class="nav-link"><i class="bi bi-speedometer2"></i> الرئيسية</a></div>
            <div class="nav-item"><a href="../modules/customer-requests/new-order.php" class="nav-link"><i class="bi bi-plus-circle"></i> طلب جديد</a></div>
            <div class="nav-item"><a href="../modules/customer-requests/orders.php" class="nav-link"><i class="bi bi-list-task"></i> متابعة الطلبات</a></div>
            <div class="nav-item"><a href="users.php" class="nav-link"><i class="bi bi-people"></i> إدارة المستخدمين</a></div>
            <div class="nav-item"><a href="sync-estock.php" class="nav-link active"><i class="bi bi-arrow-repeat"></i> مزامنة ESTOCK</a></div>
            <div class="nav-item mt-4"><a href="../index.php?logout=1" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> تسجيل الخروج</a></div>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">مزامنة البيانات من ESTOCK</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted">مدير النظام</small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="sync-card">
                    <h3><?= $estock_emp_count ?></h3>
                    <p>موظف في ESTOCK</p>
                    <hr style="border-color: rgba(255,255,255,0.3);">
                    <h3><?= $mysql_emp_count ?></h3>
                    <p>موظف في Nile Center</p>
                    <form method="POST" class="mt-3">
                        <button type="submit" name="sync_employees" class="btn btn-sync">
                            <i class="bi bi-arrow-repeat me-2"></i>مزامنة الموظفين
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-md-6">
                <div class="sync-card">
                    <h3><?= $estock_prod_count ?></h3>
                    <p>صنف في ESTOCK</p>
                    <hr style="border-color: rgba(255,255,255,0.3);">
                    <h3><?= $mysql_prod_count ?></h3>
                    <p>صنف في Nile Center</p>
                    <form method="POST" class="mt-3">
                        <button type="submit" name="sync_products" class="btn btn-sync">
                            <i class="bi bi-arrow-repeat me-2"></i>مزامنة الأصناف
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5><i class="bi bi-info-circle me-2"></i>حالة الربط</h5>
            <?php if (isESTOCKAvailable()): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>متصل بـ ESTOCK بنجاح
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-2"></i>غير متصل بـ ESTOCK — تأكد من تشغيل SQL Server وXAMPP
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
