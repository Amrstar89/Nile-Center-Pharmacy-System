<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إدارة العملاء';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search
$search = trim($_GET['search'] ?? '');
$where = "WHERE c.is_active = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (c.customer_name LIKE ? OR c.customer_code LIKE ? OR c.manual_code LIKE ? OR cp.phone_number LIKE ?)";
    $search_term = "%{$search}%";
    $params = [$search_term, $search_term, $search_term, $search_term];
}

// Get total count
$count_stmt = $db->prepare("SELECT COUNT(DISTINCT c.id) as total FROM customers c LEFT JOIN customer_phones cp ON c.id = cp.customer_id {$where}");
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get customers with primary phone and address
$sql = "SELECT 
    c.*,
    b.branch_name,
    cc.class_name_ar,
    cc.class_type,
    (SELECT CONCAT(cp.country_code, cp.phone_number) FROM customer_phones cp WHERE cp.customer_id = c.id AND cp.is_primary = 1 LIMIT 1) as primary_phone,
    (SELECT ca.address_type FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.is_primary = 1 LIMIT 1) as primary_address_type
FROM customers c
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN customer_classes cc ON c.customer_class_id = cc.id
{$where}
GROUP BY c.id
ORDER BY c.id DESC
LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; border-radius: 8px; margin: 2px 10px; text-decoration: none; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { color: #fff; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .sidebar .nav-link i { margin-left: 10px; font-size: 18px; color: rgba(255,255,255,0.7); }
        .sidebar .nav-link:hover i { color: #fff; }
        .sidebar .nav-link.active i { color: #fff; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; }
        .sidebar-brand h4 { margin: 0; font-size: 20px; }
        .sidebar-brand small { color: rgba(255,255,255,0.6); font-size: 12px; }
        .sidebar-heading { color: rgba(255,255,255,0.5); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px 5px; font-weight: 600; }
        .nav-menu { padding: 10px 0; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .customer-card { transition: all 0.3s; border: 1px solid #eee; }
        .customer-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .customer-code { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .customer-type-badge { font-size: 11px; padding: 3px 8px; border-radius: 10px; }
        .type-individual { background: #e3f2fd; color: #1976d2; }
        .type-company { background: #f3e5f5; color: #7b1fa2; }
        .status-active { color: var(--success); }
        .status-inactive { color: var(--danger); }
        .search-box { position: relative; }
        .search-box i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        .search-box input { padding-right: 40px; border-radius: 25px; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-people-fill"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> عميل جديد
                </a>
            </div>

            <!-- Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="search-box">
                                    <i class="bi bi-search"></i>
                                    <input type="text" name="search" class="form-control" placeholder="ابحث باسم العميل، الكود، أو رقم الهاتف..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> بحث
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg"></i> مسح
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Customers Grid -->
            <div class="row">
                <?php foreach ($customers as $customer): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card customer-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="customer-code">#<?= htmlspecialchars($customer['customer_code'] ?? $customer['id']) ?></span>
                                    <span class="customer-type-badge <?= $customer['customer_type'] == 'company' ? 'type-company' : 'type-individual' ?> ms-2">
                                        <?= $customer['customer_type'] == 'company' ? 'شركة' : 'فرد' ?>
                                    </span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="view.php?id=<?= $customer['id'] ?>"><i class="bi bi-eye"></i> عرض</a></li>
                                        <li><a class="dropdown-item" href="edit.php?id=<?= $customer['id'] ?>"><i class="bi bi-pencil"></i> تعديل</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="delete.php?id=<?= $customer['id'] ?>" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟')"><i class="bi bi-trash"></i> حذف</a></li>
                                    </ul>
                                </div>
                            </div>

                            <h5 class="card-title mb-1"><?= htmlspecialchars($customer['customer_name']) ?></h5>
                            <?php if (!empty($customer['customer_name_en'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($customer['customer_name_en']) ?></small>
                            <?php endif; ?>

                            <div class="mt-3">
                                <?php if (!empty($customer['primary_phone'])): ?>
                                    <div class="mb-1">
                                        <i class="bi bi-telephone-fill text-primary"></i>
                                        <span class="me-2"><?= htmlspecialchars($customer['primary_phone']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($customer['branch_name'])): ?>
                                    <div class="mb-1">
                                        <i class="bi bi-building text-info"></i>
                                        <span class="me-2"><?= htmlspecialchars($customer['branch_name']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($customer['class_name_ar'])): ?>
                                    <div class="mb-1">
                                        <i class="bi bi-tag-fill text-warning"></i>
                                        <span class="me-2"><?= htmlspecialchars($customer['class_name_ar']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-1">
                                    <i class="bi bi-cash-stack text-success"></i>
                                    <span class="me-2"><?= $customer['payment_type'] == 'credit' ? 'آجل' : 'نقدي' ?></span>
                                    <?php if ($customer['payment_type'] == 'credit' && $customer['credit_limit'] > 0): ?>
                                        <span class="badge bg-info">حد: <?= number_format($customer['credit_limit'], 2) ?> ج</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-top d-flex justify-content-between">
                                <small class="text-muted">
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('Y-m-d', strtotime($customer['created_at'])) ?>
                                </small>
                                <span class="<?= $customer['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                                    <?= $customer['is_active'] ? 'نشط' : 'موقوف' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($customers)): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-people" style="font-size: 64px; color: #ddd;"></i>
                    <h5 class="mt-3 text-muted">لا يوجد عملاء</h5>
                    <a href="create.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg"></i> إضافة عميل جديد
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">السابق</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">التالي</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>