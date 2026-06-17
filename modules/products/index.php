<?php
require_once '../../core/init.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Check permissions
if (!hasPermission('products.view')) {
    redirect('../../index.php');
}

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
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-boxes"></i> <?= $page_title ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../index.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">كارت الأصناف</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($total_products) ?></h3>
                            <p>إجمالي الأصناف</p>
                        </div>
                        <div class="icon"><i class="fas fa-boxes"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($active_products) ?></h3>
                            <p>أصناف نشطة</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($low_stock) ?></h3>
                            <p>نواقص المخزون</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><i class="fas fa-plus"></i></h3>
                            <p>صنف جديد</p>
                        </div>
                        <div class="icon"><i class="fas fa-plus-circle"></i></div>
                        <a href="create.php" class="small-box-footer">إضافة صنف <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-search"></i> بحث وفلترة</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="بحث بالاسم، الكود، المادة الفعالة، الباركود..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="category" class="form-control">
                                <option value="0">كل الأقسام</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="company" class="form-control">
                                <option value="0">كل الشركات</option>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= $comp['id'] ?>" <?= $company == $comp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($comp['company_name_ar']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>كل الحالات</option>
                                <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>نشط</option>
                                <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>موقوف</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> قائمة الأصناف</h3>
                    <div class="card-tools">
                        <a href="create.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> صنف جديد
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-striped">
                        <thead>
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
                                <td><code><?= htmlspecialchars($product['product_code']) ?></code></td>
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
                                        <span class="badge badge-danger">نفذ</span>
                                    <?php elseif ($is_low): ?>
                                        <span class="badge badge-warning">ناقص</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?= number_format($stock) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">موقوف</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?= $product['id'] ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="view.php?id=<?= $product['id'] ?>#pricing">
                                                <i class="fas fa-tag"></i> التسعير
                                            </a>
                                            <a class="dropdown-item" href="view.php?id=<?= $product['id'] ?>#stock">
                                                <i class="fas fa-warehouse"></i> المخزون
                                            </a>
                                            <a class="dropdown-item" href="view.php?id=<?= $product['id'] ?>#suppliers">
                                                <i class="fas fa-truck"></i> الموردين
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="#" onclick="deleteProduct(<?= $product['id'] ?>)">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
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
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
function deleteProduct(id) {
    if (confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
