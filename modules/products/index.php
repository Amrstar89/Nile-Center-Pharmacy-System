<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'كارت الأصناف';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$company = isset($_GET['company']) ? (int)$_GET['company'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(p.product_name LIKE ? OR p.product_code LIKE ? OR p.scientific_name LIKE ? OR pb.barcode LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($category) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($company) {
    $where[] = "p.company_id = ?";
    $params[] = $company;
}

if ($status != 'all') {
    $where[] = "p.is_active = ?";
    $params[] = ($status == 'active' ? 1 : 0);
}

$where_clause = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(DISTINCT p.id) as total 
              FROM products p 
              LEFT JOIN product_barcodes pb ON p.id = pb.product_id 
              WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get products
$sql = "SELECT p.*, 
        c.category_name_ar as category_name,
        co.company_name_ar as company_name,
        u.unit_name_ar as unit_name,
        (SELECT SUM(quantity) FROM inventory_batches WHERE product_id = p.id AND is_active = 1) as stock_quantity
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        LEFT JOIN product_companies co ON p.company_id = co.id
        LEFT JOIN product_units u ON p.unit1_id = u.id
        LEFT JOIN product_barcodes pb ON p.id = pb.product_id
        WHERE $where_clause
        GROUP BY p.id
        ORDER BY p.product_name
        LIMIT $offset, $per_page";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get filters data
$categories = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll();
$companies = $db->query("SELECT * FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar")->fetchAll();

// Stats
$total_products = $db->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
$active_products = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1")->fetch()['count'];
$low_stock = $db->query("SELECT COUNT(*) as count FROM products p 
    JOIN inventory_batches ib ON p.id = ib.product_id 
    WHERE ib.quantity <= p.min_stock AND p.min_stock > 0")->fetch()['count'];

// Include sidebar
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
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #0dcaf0;
        }
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h4 {
            color: white;
            margin: 0;
            font-weight: 700;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.6);
        }
        .nav-menu {
            padding: 15px 0;
        }
        .nav-item {
            margin: 2px 0;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-right: 3px solid var(--primary);
        }
        .nav-link i {
            width: 25px;
            margin-left: 10px;
            font-size: 18px;
        }
        .main-content {
            margin-right: 260px;
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
        }
        .badge-low { background: #dc3545; }
        .badge-out { background: #6c757d; }
        .sidebar-heading {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 20px 5px;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; }
            .main-content { margin-right: 0; }
        }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-boxes"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> إضافة صنف جديد
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <h3 class="mb-1"><?= number_format($total_products) ?></h3>
                        <p class="text-muted mb-0">إجمالي الأصناف</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h3 class="mb-1"><?= number_format($active_products) ?></h3>
                        <p class="text-muted mb-0">أصناف نشطة</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="mb-1"><?= number_format($low_stock) ?></h3>
                        <p class="text-muted mb-0">نواقص المخزون</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-plus-circle"></i>
                        </div>
                        <h3 class="mb-1"><i class="bi bi-plus-lg"></i></h3>
                        <p class="text-muted mb-0">صنف جديد</p>
                        <a href="create.php" class="btn btn-sm btn-primary mt-2">إضافة الآن</a>
                    </div>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="table-container mb-4">
                <h5 class="mb-3"><i class="bi bi-search"></i> بحث وفلترة</h5>
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" 
                               placeholder="بحث بالاسم، الكود، المادة الفعالة، الباركود..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="category" class="form-select">
                            <option value="0">كل الأقسام</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="company" class="form-select">
                            <option value="0">كل الشركات</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['id'] ?>" <?= $company == $comp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comp['company_name_ar']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>كل الحالات</option>
                            <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>نشط</option>
                            <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>موقوف</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>

            <!-- Products Table -->
            <div class="table-container">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-list"></i> قائمة الأصناف</h5>
                    <a href="create.php" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-lg"></i> صنف جديد
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>الكود</th>
                                <th>اسم الصنف</th>
                                <th>المادة الفعالة</th>
                                <th>الشركة</th>
                                <th>القسم</th>
                                <th>السعر</th>
                                <th>المخزون</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $index => $product): 
                                $stock = $product['stock_quantity'] ?? 0;
                                $is_low = $product['min_stock'] > 0 && $stock <= $product['min_stock'];
                                $is_out = $stock <= 0;
                            ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($product['product_code']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                                    <?php if ($product['product_name_en']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($product['product_name_en']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['scientific_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($product['company_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                <td>
                                    <strong><?= number_format($product['sell_price'], 2) ?></strong>
                                    <?php if ($product['cost_price'] > 0): ?>
                                        <br><small class="text-muted">تكلفة: <?= number_format($product['cost_price'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_out): ?>
                                        <span class="badge badge-out">نفذ</span>
                                    <?php elseif ($is_low): ?>
                                        <span class="badge badge-low">ناقص</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= number_format($stock) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">موقوف</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?= $product['id'] ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteProduct(<?= $product['id'] ?>)" title="حذف">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&company=<?= $company ?>&status=<?= $status ?>">السابق</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&company=<?= $company ?>&status=<?= $status ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&company=<?= $company ?>&status=<?= $status ?>">التالي</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
    function deleteProduct(id) {
        if (confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
            window.location.href = 'delete.php?id=' + id;
        }
    }
    </script>
</body>
</html>