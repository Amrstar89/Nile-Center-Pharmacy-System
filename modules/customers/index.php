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
    $search_term = "%{$search}%";
    // Search by name, code, or phone number (using EXISTS for phone)
    $where .= " AND (c.customer_name LIKE ? OR c.customer_code LIKE ? OR EXISTS (SELECT 1 FROM customer_phones cp2 WHERE cp2.customer_id = c.id AND cp2.phone_number LIKE ?))";
    $params = [$search_term, $search_term, $search_term];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM customers c {$where}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get customers with balance, primary phone and address
$sql = "SELECT 
    c.*,
    b.branch_name,
    cc.class_name_ar,
    cc.class_type,
    cb.balance,
    cb.total_invoices,
    cb.total_payments,
    cb.total_returns,
    (SELECT CONCAT(cp.country_code, cp.phone_number) FROM customer_phones cp WHERE cp.customer_id = c.id AND cp.is_primary = 1 LIMIT 1) as primary_phone,
    (SELECT ca.address_type FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.is_primary = 1 LIMIT 1) as primary_address_type
FROM customers c
LEFT JOIN branches b ON c.branch_id = b.id
LEFT JOIN customer_classes cc ON c.customer_class_id = cc.id
LEFT JOIN customer_balances cb ON c.id = cb.customer_id
{$where}
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
        .customer-code { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 3px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .customer-type-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
        .type-individual { background: #e3f2fd; color: #1976d2; }
        .type-company { background: #f3e5f5; color: #7b1fa2; }
        .balance-positive { color: var(--danger); font-weight: bold; }
        .balance-zero { color: var(--success); font-weight: bold; }
        .balance-negative { color: var(--warning); font-weight: bold; }
        .status-active { color: var(--success); }
        .status-inactive { color: var(--danger); }
        .search-box { position: relative; }
        .search-box i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        .search-box input { padding-right: 40px; border-radius: 25px; }
        .table-customers th { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; font-size: 13px; }
        .table-customers td { font-size: 13px; vertical-align: middle; }
        .table-customers tr:hover { background: #f0f4ff; }
        .action-btns .btn { padding: 3px 8px; font-size: 12px; }
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

            <!-- Customers Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-customers table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>الكود</th>
                                    <th>اسم العميل</th>
                                    <th>النوع</th>
                                    <th>التصنيف</th>
                                    <th>الفرع</th>
                                    <th>الهاتف</th>
                                    <th>الدفع</th>
                                    <th>الرصيد</th>
                                    <th>الحالة</th>
                                    <th style="width: 180px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <?php
                                    $balance = floatval($customer['balance'] ?? 0);
                                    $balance_class = $balance > 0 ? 'balance-positive' : ($balance < 0 ? 'balance-negative' : 'balance-zero');
                                    $balance_text = $balance > 0 ? 'مدين: ' . number_format($balance, 2) : ($balance < 0 ? 'دائن: ' . number_format(abs($balance), 2) : '0.00');
                                ?>
                                <tr>
                                    <td><?= $customer['id'] ?></td>
                                    <td><span class="customer-code"><?= htmlspecialchars($customer['customer_code'] ?? $customer['id']) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['customer_name']) ?></strong>
                                        <?php if (!empty($customer['customer_name_en'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($customer['customer_name_en']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="customer-type-badge <?= $customer['customer_type'] == 'company' ? 'type-company' : 'type-individual' ?>">
                                            <?= $customer['customer_type'] == 'company' ? 'شركة' : 'فرد' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($customer['class_name_ar'])): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($customer['class_name_ar']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($customer['branch_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($customer['primary_phone'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($customer['payment_type'] == 'credit'): ?>
                                            <span class="badge bg-info">آجل</span>
                                            <?php if ($customer['credit_limit'] > 0): ?>
                                                <br><small class="text-muted">حد: <?= number_format($customer['credit_limit'], 2) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-success">نقدي</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $balance_class ?>"><?= $balance_text ?> ج</td>
                                    <td>
                                        <span class="<?= $customer['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                            <i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                                            <?= $customer['is_active'] ? 'نشط' : 'موقوف' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-info" title="عرض">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-warning" title="تعديل">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="statement.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary" title="كشف حساب">
                                                <i class="bi bi-file-text"></i>
                                            </a>
                                            <a href="delete.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-danger" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <i class="bi bi-people" style="font-size: 48px; color: #ddd;"></i>
                                        <h5 class="mt-3 text-muted">لا يوجد عملاء</h5>
                                        <a href="create.php" class="btn btn-primary mt-3">
                                            <i class="bi bi-plus-lg"></i> إضافة عميل جديد
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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