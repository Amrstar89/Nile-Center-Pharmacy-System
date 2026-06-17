<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
requireAuth();

$db = getDB();

// ============================================
// AJAX HANDLERS
// ============================================

// 1. Import from Excel (E-Stock Export)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    header('Content-Type: application/json');

    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'يرجى اختيار ملف Excel']);
        exit;
    }

    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        $file = $_FILES['excel_file']['tmp_name'];
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $imported = 0;
        $updated = 0;
        $errors = [];

        $db->beginTransaction();

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // Skip header

            $id = isset($row[0]) && is_numeric($row[0]) ? (int)$row[0] : null;
            $product_code = isset($row[1]) ? trim($row[1]) : null;  // E-Stock ID as code
            $product_name_ar = isset($row[2]) ? trim($row[2]) : null;
            $product_name_en = isset($row[3]) ? trim($row[3]) : null;
            $sell_price = isset($row[4]) && is_numeric($row[4]) ? (float)$row[4] : 0;
            $buy_price = isset($row[5]) && is_numeric($row[5]) ? (float)$row[5] : 0;
            $company_id = isset($row[6]) && is_numeric($row[6]) ? (int)$row[6] : null;
            $company_name = isset($row[7]) ? trim($row[7]) : null;
            $group_id = isset($row[8]) && is_numeric($row[8]) ? (int)$row[8] : null;
            $group_name = isset($row[9]) ? trim($row[9]) : null;
            $scientific_name = isset($row[10]) ? trim($row[10]) : null;
            $has_expire = isset($row[11]) ? (int)$row[11] : 0;
            $is_drug = isset($row[12]) ? (int)$row[12] : 0;
            $product_type = isset($row[13]) ? trim($row[13]) : 'drug';
            $is_local = isset($row[14]) ? (int)$row[14] : 1;
            $fast_code = isset($row[15]) ? trim($row[15]) : null;
            $international_barcode = isset($row[16]) ? trim($row[16]) : null;
            $qr_code = isset($row[17]) ? trim($row[17]) : null;

            if (!$id || !$product_code || !$product_name_ar) {
                $errors[] = "صف $i: بيانات ناقصة (id=$id, code=$product_code, name=$product_name_ar)";
                continue;
            }

            // Insert/Update Company
            if ($company_id && $company_name) {
                $stmt = $db->prepare("
                    INSERT INTO product_companies (id, company_name_ar, is_active, source, estock_id)
                    VALUES (?, ?, 1, 'estock', ?)
                    ON DUPLICATE KEY UPDATE 
                        company_name_ar = VALUES(company_name_ar),
                        is_active = 1,
                        updated_at = NOW()
                ");
                $stmt->execute([$company_id, $company_name, $company_id]);
            }

            // Insert/Update Category
            if ($group_id && $group_name) {
                $stmt = $db->prepare("
                    INSERT INTO product_categories (id, category_name_ar, is_active, source, estock_id)
                    VALUES (?, ?, 1, 'estock', ?)
                    ON DUPLICATE KEY UPDATE 
                        category_name_ar = VALUES(category_name_ar),
                        is_active = 1,
                        updated_at = NOW()
                ");
                $stmt->execute([$group_id, $group_name, $group_id]);
            }

            // Insert/Update Product (ID = E-Stock product_id, product_code = E-Stock ID)
            $stmt = $db->prepare("
                INSERT INTO products 
                (id, product_code, product_name, product_name_en, scientific_name, product_type, is_local,
                 sell_price, cost_price, category_id, company_id, group_id,
                 has_expire, is_drug, source, estock_id, is_active, fast_code, international_barcode, qr_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'estock', ?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    product_code = VALUES(product_code),
                    product_name = VALUES(product_name),
                    product_name_en = VALUES(product_name_en),
                    scientific_name = VALUES(scientific_name),
                    product_type = VALUES(product_type),
                    is_local = VALUES(is_local),
                    sell_price = VALUES(sell_price),
                    cost_price = VALUES(cost_price),
                    category_id = VALUES(category_id),
                    company_id = VALUES(company_id),
                    group_id = VALUES(group_id),
                    has_expire = VALUES(has_expire),
                    is_drug = VALUES(is_drug),
                    is_active = 1,
                    fast_code = VALUES(fast_code),
                    international_barcode = VALUES(international_barcode),
                    qr_code = VALUES(qr_code),
                    updated_at = NOW()
            ");

            $stmt->execute([
                $id, $product_code, $product_name_ar, $product_name_en, $scientific_name, 
                $product_type, $is_local, $sell_price, $buy_price, $group_id, $company_id, $group_id,
                $has_expire, $is_drug, $id, $fast_code, $international_barcode, $qr_code
            ]);

            if ($stmt->rowCount() === 1) $imported++;
            else $updated++;
        }

        $db->commit();

        echo json_encode([
            'success' => true, 
            'message' => "تم استيراد $imported وتحديث $updated صنف بنجاح",
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. Add Manual Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_manual') {
    header('Content-Type: application/json');

    $product_name = $_POST['product_name'] ?? '';
    $product_name_en = $_POST['product_name_en'] ?? '';
    $scientific_name = $_POST['scientific_name'] ?? '';
    $product_type = $_POST['product_type'] ?? 'drug';
    $is_local = $_POST['is_local'] ?? 1;
    $category_id = $_POST['category_id'] ?? null;
    $company_id = $_POST['company_id'] ?? null;
    $sell_price = $_POST['sell_price'] ?? 0;
    $cost_price = $_POST['cost_price'] ?? 0;
    $has_expire = $_POST['has_expire'] ?? 0;
    $is_drug = $_POST['is_drug'] ?? 0;
    $print_barcode = $_POST['print_barcode'] ?? 1;
    $fast_code = $_POST['fast_code'] ?? null;
    $international_barcode = $_POST['international_barcode'] ?? null;
    $qr_code = $_POST['qr_code'] ?? null;
    $notes = $_POST['notes'] ?? '';

    if (!$product_name) {
        echo json_encode(['success' => false, 'message' => 'اسم الصنف مطلوب']);
        exit;
    }

    try {
        // Get next manual ID (negative)
        $stmt = $db->query("SELECT COALESCE(MIN(id), 0) - 1 as next_id FROM products WHERE id < 0 OR source = 'manual'");
        $next_id = $stmt->fetch()['next_id'];
        if ($next_id >= 0 || $next_id === null) $next_id = -1;

        // Generate manual code: M1, M2, M3...
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE source = 'manual'");
        $manual_count = $stmt->fetch()['count'] + 1;
        $manual_code = 'M' . $manual_count;

        $stmt = $db->prepare("
            INSERT INTO products (id, product_code, product_name, product_name_en, scientific_name,
                                product_type, is_local, category_id, company_id, sell_price, cost_price,
                                has_expire, is_drug, print_barcode, fast_code, international_barcode, qr_code,
                                notes, source, manual_code, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', ?, 1)
        ");
        $stmt->execute([
            $next_id, $manual_code, $product_name, $product_name_en, $scientific_name,
            $product_type, $is_local, $category_id, $company_id, $sell_price, $cost_price,
            $has_expire, $is_drug, $print_barcode, $fast_code, $international_barcode, $qr_code,
            $notes, $manual_code
        ]);

        echo json_encode(['success' => true, 'message' => 'تم إضافة الصنف بنجاح', 'id' => $next_id, 'manual_code' => $manual_code]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    header('Content-Type: application/json');

    $id = $_POST['id'] ?? 0;
    $product_name = $_POST['product_name'] ?? '';
    $product_name_en = $_POST['product_name_en'] ?? '';
    $scientific_name = $_POST['scientific_name'] ?? '';
    $product_type = $_POST['product_type'] ?? 'drug';
    $is_local = $_POST['is_local'] ?? 1;
    $category_id = $_POST['category_id'] ?? null;
    $company_id = $_POST['company_id'] ?? null;
    $sell_price = $_POST['sell_price'] ?? 0;
    $cost_price = $_POST['cost_price'] ?? 0;
    $has_expire = $_POST['has_expire'] ?? 0;
    $is_drug = $_POST['is_drug'] ?? 0;
    $print_barcode = $_POST['print_barcode'] ?? 1;
    $fast_code = $_POST['fast_code'] ?? null;
    $international_barcode = $_POST['international_barcode'] ?? null;
    $qr_code = $_POST['qr_code'] ?? null;
    $is_active = $_POST['is_active'] ?? 1;
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $db->prepare("
            UPDATE products SET 
                product_name = ?, product_name_en = ?, scientific_name = ?, product_type = ?, is_local = ?,
                category_id = ?, company_id = ?, sell_price = ?, cost_price = ?,
                has_expire = ?, is_drug = ?, print_barcode = ?, fast_code = ?, international_barcode = ?, qr_code = ?,
                is_active = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $product_name, $product_name_en, $scientific_name, $product_type, $is_local,
            $category_id, $company_id, $sell_price, $cost_price,
            $has_expire, $is_drug, $print_barcode, $fast_code, $international_barcode, $qr_code,
            $is_active, $notes, $id
        ]);

        echo json_encode(['success' => true, 'message' => 'تم تحديث الصنف بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 4. Delete Product (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');

    $id = $_POST['id'] ?? 0;

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'تم تعطيل الصنف (مستخدم في طلبات)']);
        } else {
            $stmt = $db->prepare("SELECT source FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $source = $stmt->fetchColumn();

            if ($source === 'manual') {
                $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'تم حذف الصنف بنجاح']);
            } else {
                $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'تم تعطيل الصنف']);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 5. Get Product Card Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_card') {
    header('Content-Type: application/json');

    $id = $_GET['id'] ?? 0;

    try {
        $stmt = $db->prepare("
            SELECT p.*, c.category_name_ar, co.company_name_ar
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.id
            LEFT JOIN product_companies co ON p.company_id = co.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'الصنف غير موجود']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT sp.*, s.supplier_name, s.supplier_code, s.phone, dt.time_name as delivery_time
            FROM supplier_prices sp
            JOIN suppliers s ON sp.supplier_id = s.id
            LEFT JOIN delivery_times dt ON sp.delivery_time_id = dt.id
            WHERE sp.product_code = ? AND sp.is_active = 1
            ORDER BY sp.supplier_price ASC
        ");
        $stmt->execute([$product['product_code']]);
        $supplier_prices = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT oi.*, o.order_number, o.customer_name, o.order_date
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.product_id = ? OR oi.product_code = ?
            ORDER BY o.order_date DESC
            LIMIT 50
        ");
        $stmt->execute([$id, $product['product_code']]);
        $sales_history = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT ps.*, b.branch_name
            FROM product_stock ps
            JOIN branches b ON ps.branch_id = b.id
            WHERE ps.product_id = ?
        ");
        $stmt->execute([$id]);
        $stock = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'product' => $product,
            'supplier_prices' => $supplier_prices,
            'sales_history' => $sales_history,
            'stock' => $stock
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// PAGE DATA
// ============================================

$source = $_GET['source'] ?? 'all';
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$product_type = $_GET['product_type'] ?? '';
$is_active = $_GET['is_active'] ?? '1';

$sql = "SELECT p.*, c.category_name_ar, co.company_name_ar 
        FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.id 
        LEFT JOIN product_companies co ON p.company_id = co.id 
        WHERE 1=1";
$params = [];

if ($source !== 'all') {
    $sql .= " AND p.source = ?";
    $params[] = $source;
}

if ($search) {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.product_name_en LIKE ? OR p.scientific_name LIKE ? OR p.fast_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($company_id) {
    $sql .= " AND p.company_id = ?";
    $params[] = $company_id;
}

if ($product_type) {
    $sql .= " AND p.product_type = ?";
    $params[] = $product_type;
}

if ($is_active !== 'all') {
    $sql .= " AND p.is_active = ?";
    $params[] = $is_active;
}

$sql .= " ORDER BY p.id DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Filters data
$categories = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll();
$companies = $db->query("SELECT * FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar")->fetchAll();
$product_types = $db->query("SELECT * FROM product_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Stats
$total_estock = $db->query("SELECT COUNT(*) FROM products WHERE source = 'estock'")->fetchColumn();
$total_manual = $db->query("SELECT COUNT(*) FROM products WHERE source = 'manual'")->fetchColumn();
$total_active = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
$total_inactive = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 0")->fetchColumn();

$page_title = 'إدارة الأصناف';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .content-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 15px; padding: 20px; text-align: center; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-number { font-size: 32px; font-weight: 700; }
        .product-card { border: 2px solid #e0e0e0; border-radius: 15px; padding: 20px; transition: all 0.3s; cursor: pointer; position: relative; overflow: hidden; }
        .product-card:hover { border-color: var(--primary); box-shadow: 0 8px 25px rgba(0,0,0,0.15); transform: translateY(-3px); }
        .product-card .card-actions { position: absolute; top: 10px; left: 10px; opacity: 0; transition: opacity 0.3s; }
        .product-card:hover .card-actions { opacity: 1; }
        .estock-badge { background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .manual-badge { background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .inactive-badge { background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 20px; font-size: 11px; }
        .type-badge { background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 15px; font-size: 11px; }
        .imported-badge { background: #fce4ec; color: #c2185b; padding: 4px 10px; border-radius: 15px; font-size: 11px; }
        .local-badge { background: #e0f2f1; color: #00695c; padding: 4px 10px; border-radius: 15px; font-size: 11px; }
        .import-area { border: 3px dashed #adb5bd; border-radius: 15px; padding: 40px; text-align: center; background: #f8f9fa; transition: all 0.3s; }
        .import-area:hover { border-color: var(--primary); background: #e8eaf6; }
        .product-image { width: 80px; height: 80px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; }
        .price-tag { background: #e8f5e9; color: #2e7d32; padding: 6px 12px; border-radius: 10px; font-weight: 600; }
        .search-box { position: relative; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .search-box input { padding-left: 40px; }
        .barcode-icon { color: #1976d2; }
        @media (max-width: 768px) { .sidebar { width: 0; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إدارة أصناف E-Stock والأصناف اليدوية</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($total_estock + $total_manual) ?></div>
                    <div><i class="bi bi-box-seam me-2"></i>إجمالي الأصناف</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #42a5f5, #1e88e5);">
                    <div class="stat-number"><?= number_format($total_estock) ?></div>
                    <div><i class="bi bi-cloud-download me-2"></i>من E-Stock</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffa726, #fb8c00);">
                    <div class="stat-number"><?= number_format($total_manual) ?></div>
                    <div><i class="bi bi-hand-index me-2"></i>أصناف يدوية</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #66bb6a, #43a047);">
                    <div class="stat-number"><?= number_format($total_active) ?></div>
                    <div><i class="bi bi-check-circle me-2"></i>نشطة</div>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="content-card">
            <h5 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>استيراد من E-Stock</h5>
            <div class="import-area" id="dropZone">
                <i class="bi bi-file-earmark-excel" style="font-size: 48px; color: var(--primary);"></i>
                <h5 class="mt-3">ارفع ملف Excel من E-Stock</h5>
                <p class="text-muted">
                    الأعمدة: product_id | product_code | product_name_ar | product_name_en | sell_price | buy_price | company_id | company_name | group_id | group_name | has_expire | is_drug | scientific_name | product_type | is_local | fast_code | international_barcode | qr_code
                </p>
                <input type="file" id="excelFile" accept=".xlsx,.xls,.csv" class="d-none">
                <button class="btn btn-primary btn-lg" onclick="document.getElementById('excelFile').click()">
                    <i class="bi bi-upload me-2"></i>اختر الملف
                </button>
                <div id="importProgress" class="mt-3 d-none">
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%">جاري الاستيراد...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Manual Product -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>إضافة صنف يدوي</h5>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#manualForm">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="manualForm">
                <form id="addManualForm" class="row g-3">
                    <!-- Product Name -->
                    <div class="col-md-4">
                        <label class="form-label">اسم الصنف (عربي) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="product_name" required placeholder="اسم الصنف بالعربي">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">اسم الصنف (إنجليزي)</label>
                        <input type="text" class="form-control" name="product_name_en" placeholder="Product Name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الاسم العلمي</label>
                        <input type="text" class="form-control" name="scientific_name" placeholder="Scientific Name">
                    </div>

                    <!-- Product Type & Origin -->
                    <div class="col-md-3">
                        <label class="form-label">نوع الصنف <span class="text-danger">*</span></label>
                        <select class="form-select" name="product_type" required>
                            <option value="">-- اختر --</option>
                            <?php foreach ($product_types as $pt): ?>
                            <option value="<?= $pt['type_code'] ?>"><?= htmlspecialchars($pt['type_name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">المصدر</label>
                        <select class="form-select" name="is_local">
                            <option value="1">محلي</option>
                            <option value="0">مستورد</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">التصنيف</label>
                        <select class="form-select select2" name="category_id">
                            <option value="">-- اختر --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الشركة</label>
                        <select class="form-select select2" name="company_id">
                            <option value="">-- اختر --</option>
                            <?php foreach ($companies as $co): ?>
                            <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['company_name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pricing -->
                    <div class="col-md-3">
                        <label class="form-label">سعر البيع</label>
                        <input type="number" class="form-control" name="sell_price" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">سعر التكلفة</label>
                        <input type="number" class="form-control" name="cost_price" step="0.01" placeholder="0.00">
                    </div>

                    <!-- Barcode Options -->
                    <div class="col-md-3">
                        <label class="form-label d-block">طباعة باركود</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="print_barcode" value="1" checked>
                            <label class="form-check-label">نعم</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="print_barcode" value="0">
                            <label class="form-check-label">لا</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">خيارات</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="has_expire" value="1">
                            <label class="form-check-label">له تاريخ صلاحية</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="is_drug" value="1">
                            <label class="form-check-label">دواء</label>
                        </div>
                    </div>

                    <!-- Codes -->
                    <div class="col-md-4">
                        <label class="form-label">كود مختصر (Fast Code)</label>
                        <input type="text" class="form-control" name="fast_code" placeholder="كود مختصر للبحث السريع">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">باركود دولي</label>
                        <input type="text" class="form-control" name="international_barcode" placeholder="International Barcode">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">QR Code</label>
                        <input type="text" class="form-control" name="qr_code" placeholder="QR Code">
                    </div>

                    <!-- Notes -->
                    <div class="col-md-12">
                        <label class="form-label">ملاحظات</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="ملاحظات إضافية..."></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-plus-lg me-2"></i>إضافة صنف يدوي
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <div class="content-card">
            <div class="row g-3">
                <div class="col-md-3 search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" id="searchInput" placeholder="بحث في الأصناف..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="sourceFilter">
                        <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>كل الأصناف</option>
                        <option value="estock" <?= $source === 'estock' ? 'selected' : '' ?>>E-Stock فقط</option>
                        <option value="manual" <?= $source === 'manual' ? 'selected' : '' ?>>يدوي فقط</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="typeFilter">
                        <option value="">كل الأنواع</option>
                        <?php foreach ($product_types as $pt): ?>
                        <option value="<?= $pt['type_code'] ?>" <?= $product_type === $pt['type_code'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['type_name_ar']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="categoryFilter">
                        <option value="">كل التصنيفات</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name_ar']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="1" <?= $is_active === '1' ? 'selected' : '' ?>>نشطة</option>
                        <option value="0" <?= $is_active === '0' ? 'selected' : '' ?>>معطلة</option>
                        <option value="all" <?= $is_active === 'all' ? 'selected' : '' ?>>الكل</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>قائمة الأصناف (<?= count($products) ?>)</h5>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary active" onclick="setView('grid')">
                        <i class="bi bi-grid"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="setView('list')">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </div>

            <div class="row" id="productsGrid">
                <?php foreach ($products as $product): 
                    $isManual = $product['source'] === 'manual';
                    $badgeClass = $isManual ? 'manual-badge' : 'estock-badge';
                    $badgeText = $isManual ? 'يدوي' : 'E-Stock';
                    $statusClass = $product['is_active'] ? '' : 'inactive-badge';

                    // Type badge
                    $typeNames = ['drug' => 'دواء', 'accessory' => 'أكسسوار', 'paper' => 'ورقيات', 'medical_supply' => 'مستلزمات طبية'];
                    $typeName = $typeNames[$product['product_type']] ?? 'غير محدد';

                    // Origin badge
                    $originClass = $product['is_local'] ? 'local-badge' : 'imported-badge';
                    $originText = $product['is_local'] ? 'محلي' : 'مستورد';
                ?>
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="product-card" onclick="openProductCard(<?= $product['id'] ?>)">
                        <div class="card-actions">
                            <button class="btn btn-sm btn-light" onclick="event.stopPropagation(); editProduct(<?= $product['id'] ?>)" title="تعديل">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-light text-danger" onclick="event.stopPropagation(); deleteProduct(<?= $product['id'] ?>)" title="حذف">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                        <div class="d-flex align-items-start mb-3">
                            <div class="product-image me-3">
                                <?= mb_substr($product['product_name'], 0, 2) ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <span class="<?= $badgeClass ?>"><?= $badgeText ?></span>
                                    <?php if (!$product['is_active']): ?>
                                    <span class="inactive-badge">معطل</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1"><?= htmlspecialchars($product['product_code']) ?></div>
                            </div>
                        </div>

                        <h6 class="mb-2" style="min-height: 48px;"><?= htmlspecialchars($product['product_name']) ?></h6>
                        <?php if ($product['product_name_en']): ?>
                        <div class="small text-muted mb-2"><?= htmlspecialchars($product['product_name_en']) ?></div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="price-tag"><?= number_format($product['sell_price'], 2) ?> ج</span>
                            <span class="small text-muted"><?= $product['id'] ?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="type-badge"><?= $typeName ?></span>
                            <span class="<?= $originClass ?>"><?= $originText ?></span>
                        </div>

                        <div class="d-flex justify-content-between text-muted small">
                            <span><i class="bi bi-building me-1"></i><?= htmlspecialchars($product['company_name_ar'] ?? 'غير محدد') ?></span>
                            <span><i class="bi bi-tag me-1"></i><?= htmlspecialchars($product['category_name_ar'] ?? 'غير محدد') ?></span>
                        </div>

                        <?php if ($product['scientific_name']): ?>
                        <div class="small text-primary mt-2">
                            <i class="bi bi-capsule me-1"></i><?= htmlspecialchars($product['scientific_name']) ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($product['print_barcode']): ?>
                        <div class="small text-info mt-1">
                            <i class="bi bi-upc barcode-icon me-1"></i>باركود: نعم
                        </div>
                        <?php endif; ?>

                        <?php if ($product['fast_code']): ?>
                        <div class="small text-warning mt-1">
                            <i class="bi bi-lightning me-1"></i>كود مختصر: <?= htmlspecialchars($product['fast_code']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($products) === 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 64px; color: #dee2e6;"></i>
                <h5 class="mt-3 text-muted">لا يوجد أصناف</h5>
                <p class="text-muted">استورد من E-Stock أو أضف صنف يدوي</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Card Modal -->
    <div class="modal fade" id="productCardModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">كارت الصنف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="productCardBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">جاري تحميل البيانات...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" onclick="editCurrentProduct()">
                        <i class="bi bi-pencil me-2"></i>تعديل
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل صنف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" class="row g-3">
                        <input type="hidden" name="id" id="editId">

                        <div class="col-md-4">
                            <label>كود الصنف</label>
                            <input type="text" class="form-control" id="editCode" readonly>
                        </div>
                        <div class="col-md-4">
                            <label>الاسم (عربي)</label>
                            <input type="text" class="form-control" name="product_name" id="editName" required>
                        </div>
                        <div class="col-md-4">
                            <label>الاسم (إنجليزي)</label>
                            <input type="text" class="form-control" name="product_name_en" id="editNameEn">
                        </div>

                        <div class="col-md-4">
                            <label>الاسم العلمي</label>
                            <input type="text" class="form-control" name="scientific_name" id="editScientific">
                        </div>
                        <div class="col-md-4">
                            <label>نوع الصنف</label>
                            <select class="form-select" name="product_type" id="editType">
                                <?php foreach ($product_types as $pt): ?>
                                <option value="<?= $pt['type_code'] ?>"><?= htmlspecialchars($pt['type_name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>المصدر</label>
                            <select class="form-select" name="is_local" id="editLocal">
                                <option value="1">محلي</option>
                                <option value="0">مستورد</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>سعر البيع</label>
                            <input type="number" class="form-control" name="sell_price" id="editSellPrice" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label>سعر التكلفة</label>
                            <input type="number" class="form-control" name="cost_price" id="editCostPrice" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label>التصنيف</label>
                            <select class="form-select" name="category_id" id="editCategory">
                                <option value="">-- اختر --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>الشركة</label>
                            <select class="form-select" name="company_id" id="editCompany">
                                <option value="">-- اختر --</option>
                                <?php foreach ($companies as $co): ?>
                                <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['company_name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>كود مختصر</label>
                            <input type="text" class="form-control" name="fast_code" id="editFastCode">
                        </div>
                        <div class="col-md-3">
                            <label>باركود دولي</label>
                            <input type="text" class="form-control" name="international_barcode" id="editIntBarcode">
                        </div>
                        <div class="col-md-3">
                            <label>QR Code</label>
                            <input type="text" class="form-control" name="qr_code" id="editQR">
                        </div>
                        <div class="col-md-3">
                            <label>الحالة</label>
                            <select class="form-select" name="is_active" id="editStatus">
                                <option value="1">نشط</option>
                                <option value="0">معطل</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="has_expire" id="editHasExpire" value="1">
                                <label class="form-check-label">له تاريخ صلاحية</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_drug" id="editIsDrug" value="1">
                                <label class="form-check-label">دواء</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="print_barcode" id="editPrintBarcode" value="1">
                                <label class="form-check-label">طباعة باركود</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>ملاحظات</label>
                            <textarea class="form-control" name="notes" id="editNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-success" onclick="saveEdit()">
                        <i class="bi bi-check-lg me-2"></i>حفظ التعديلات
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2
        $('.select2').select2({ width: '100%', placeholder: 'اختر...', allowClear: true });

        let currentProductId = null;
        const productCardModal = new bootstrap.Modal(document.getElementById('productCardModal'));
        const editProductModal = new bootstrap.Modal(document.getElementById('editProductModal'));

        // File Import
        document.getElementById('excelFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('excel_file', file);
            formData.append('action', 'import');

            document.getElementById('importProgress').classList.remove('d-none');

            $.ajax({
                url: 'products.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    document.getElementById('importProgress').classList.add('d-none');
                    if (response.success) {
                        alert('✅ ' + response.message);
                        if (response.errors && response.errors.length > 0) {
                            console.log('Errors:', response.errors);
                        }
                        location.reload();
                    } else {
                        alert('❌ ' + response.message);
                    }
                },
                error: function() {
                    document.getElementById('importProgress').classList.add('d-none');
                    alert('❌ حدث خطأ في الاستيراد');
                }
            });
        });

        // Add Manual Product
        document.getElementById('addManualForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_manual');

            $.ajax({
                url: 'products.php',
                method: 'POST',
                data: Object.fromEntries(formData),
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.message + '\nالكود: ' + response.manual_code);
                        location.reload();
                    } else {
                        alert('❌ ' + response.message);
                    }
                }
            });
        });

        // Filters
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const source = document.getElementById('sourceFilter').value;
            const type = document.getElementById('typeFilter').value;
            const category = document.getElementById('categoryFilter').value;
            const status = document.getElementById('statusFilter').value;

            window.location.href = `products.php?search=${encodeURIComponent(search)}&source=${source}&product_type=${type}&category_id=${category}&is_active=${status}`;
        }

        // Open Product Card
        function openProductCard(id) {
            currentProductId = id;
            productCardModal.show();

            $.ajax({
                url: 'products.php',
                method: 'GET',
                data: { action: 'get_card', id: id },
                success: function(response) {
                    if (response.success) {
                        renderProductCard(response);
                    } else {
                        document.getElementById('productCardBody').innerHTML = `
                            <div class="alert alert-danger">${response.message}</div>
                        `;
                    }
                }
            });
        }

        // Render Product Card
        function renderProductCard(data) {
            const p = data.product;
            const sp = data.supplier_prices;
            const sh = data.sales_history;
            const st = data.stock;

            const typeNames = {drug: 'دواء', accessory: 'أكسسوار', paper: 'ورقيات', medical_supply: 'مستلزمات طبية'};
            const typeName = typeNames[p.product_type] || 'غير محدد';

            let html = `
                <div class="row">
                    <!-- Product Info -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="product-image mx-auto mb-3" style="width:100px;height:100px;font-size:36px;">
                                    ${p.product_name.substring(0, 2)}
                                </div>
                                <h5>${p.product_name}</h5>
                                <p class="text-muted">${p.product_name_en || ''}</p>
                                <span class="${p.source === 'estock' ? 'estock-badge' : 'manual-badge'}">
                                    ${p.source === 'estock' ? 'E-Stock' : 'يدوي'}
                                </span>
                                ${p.is_active ? '' : '<span class="inactive-badge">معطل</span>'}

                                <hr>

                                <div class="text-start">
                                    <p><strong>الكود:</strong> ${p.product_code}</p>
                                    <p><strong>الكود اليدوي:</strong> ${p.manual_code || 'N/A'}</p>
                                    <p><strong>E-Stock ID:</strong> ${p.estock_id || 'N/A'}</p>
                                    <p><strong>النوع:</strong> <span class="type-badge">${typeName}</span></p>
                                    <p><strong>المصدر:</strong> <span class="${p.is_local ? 'local-badge' : 'imported-badge'}">${p.is_local ? 'محلي' : 'مستورد'}</span></p>
                                    <p><strong>السعر:</strong> <span class="price-tag">${parseFloat(p.sell_price).toFixed(2)} ج</span></p>
                                    <p><strong>سعر التكلفة:</strong> ${parseFloat(p.cost_price).toFixed(2)} ج</p>
                                    <p><strong>التصنيف:</strong> ${p.category_name_ar || 'غير محدد'}</p>
                                    <p><strong>الشركة:</strong> ${p.company_name_ar || 'غير محدد'}</p>
                                    <p><strong>الاسم العلمي:</strong> ${p.scientific_name || 'غير محدد'}</p>
                                    <p><strong>كود مختصر:</strong> ${p.fast_code || 'غير محدد'}</p>
                                    <p><strong>باركود دولي:</strong> ${p.international_barcode || 'غير محدد'}</p>
                                    <p><strong>QR Code:</strong> ${p.qr_code || 'غير محدد'}</p>
                                    <p><strong>طباعة باركود:</strong> ${p.print_barcode ? 'نعم' : 'لا'}</p>
                                    <p><strong>له تاريخ صلاحية:</strong> ${p.has_expire ? 'نعم' : 'لا'}</p>
                                    <p><strong>دواء:</strong> ${p.is_drug ? 'نعم' : 'لا'}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Prices -->
                    <div class="col-md-8">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-shop me-2"></i>أسعار الموردين (${sp.length})
                            </div>
                            <div class="card-body">
                                ${sp.length > 0 ? `
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>المورد</th>
                                                <th>سعر الشراء</th>
                                                <th>سعر البيع</th>
                                                <th>الربح</th>
                                                <th>وقت التوفير</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${sp.map(price => `
                                                <tr>
                                                    <td>${price.supplier_name} <small class="text-muted">(${price.supplier_code})</small></td>
                                                    <td>${parseFloat(price.supplier_price).toFixed(2)} ج</td>
                                                    <td>${parseFloat(price.sell_price).toFixed(2)} ج</td>
                                                    <td><span class="badge bg-success">${price.profit_margin}%</span></td>
                                                    <td>${price.delivery_time || 'غير محدد'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                ` : '<div class="text-muted text-center py-3">لا يوجد أسعار موردين</div>'}
                            </div>
                        </div>

                        <!-- Sales History -->
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-cart me-2"></i>تاريخ المبيعات (آخر 50)
                            </div>
                            <div class="card-body">
                                ${sh.length > 0 ? `
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>الطلب</th>
                                                <th>العميل</th>
                                                <th>التاريخ</th>
                                                <th>الكمية</th>
                                                <th>السعر</th>
                                                <th>الإجمالي</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${sh.map(sale => `
                                                <tr>
                                                    <td>${sale.order_number}</td>
                                                    <td>${sale.customer_name}</td>
                                                    <td>${new Date(sale.order_date).toLocaleDateString('ar-EG')}</td>
                                                    <td>${sale.quantity}</td>
                                                    <td>${parseFloat(sale.unit_price).toFixed(2)} ج</td>
                                                    <td>${parseFloat(sale.final_price).toFixed(2)} ج</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                ` : '<div class="text-muted text-center py-3">لا يوجد مبيعات</div>'}
                            </div>
                        </div>

                        <!-- Stock -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-box-seam me-2"></i>المخزون بالفروع
                            </div>
                            <div class="card-body">
                                ${st.length > 0 ? `
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>الفرع</th>
                                                <th>المخزن</th>
                                                <th>الكمية</th>
                                                <th>سعر الشراء</th>
                                                <th>تاريخ الصلاحية</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${st.map(s => `
                                                <tr>
                                                    <td>${s.branch_name}</td>
                                                    <td>${s.store_id}</td>
                                                    <td class="${s.quantity <= 0 ? 'text-danger' : 'text-success'}">${s.quantity}</td>
                                                    <td>${parseFloat(s.buy_price).toFixed(2)} ج</td>
                                                    <td>${s.exp_date || 'N/A'}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                ` : '<div class="text-muted text-center py-3">لا يوجد مخزون مسجل</div>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('productCardBody').innerHTML = html;
        }

        // Edit Product
        function editProduct(id) {
            currentProductId = id;

            $.ajax({
                url: 'products.php',
                method: 'GET',
                data: { action: 'get_card', id: id },
                success: function(response) {
                    if (response.success) {
                        const p = response.product;
                        document.getElementById('editId').value = p.id;
                        document.getElementById('editCode').value = p.product_code;
                        document.getElementById('editName').value = p.product_name;
                        document.getElementById('editNameEn').value = p.product_name_en || '';
                        document.getElementById('editScientific').value = p.scientific_name || '';
                        document.getElementById('editType').value = p.product_type || 'drug';
                        document.getElementById('editLocal').value = p.is_local ? '1' : '0';
                        document.getElementById('editSellPrice').value = p.sell_price;
                        document.getElementById('editCostPrice').value = p.cost_price;
                        document.getElementById('editCategory').value = p.category_id || '';
                        document.getElementById('editCompany').value = p.company_id || '';
                        document.getElementById('editFastCode').value = p.fast_code || '';
                        document.getElementById('editIntBarcode').value = p.international_barcode || '';
                        document.getElementById('editQR').value = p.qr_code || '';
                        document.getElementById('editStatus').value = p.is_active ? '1' : '0';
                        document.getElementById('editHasExpire').checked = p.has_expire == 1;
                        document.getElementById('editIsDrug').checked = p.is_drug == 1;
                        document.getElementById('editPrintBarcode').checked = p.print_barcode == 1;
                        document.getElementById('editNotes').value = p.notes || '';

                        editProductModal.show();
                    }
                }
            });
        }

        function editCurrentProduct() {
            if (currentProductId) {
                productCardModal.hide();
                editProduct(currentProductId);
            }
        }

        function saveEdit() {
            const formData = new FormData(document.getElementById('editForm'));
            formData.append('action', 'update');

            $.ajax({
                url: 'products.php',
                method: 'POST',
                data: Object.fromEntries(formData),
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.message);
                        editProductModal.hide();
                        location.reload();
                    } else {
                        alert('❌ ' + response.message);
                    }
                }
            });
        }

        // Delete Product
        function deleteProduct(id) {
            if (!confirm('هل أنت متأكد من حذف/تعطيل هذا الصنف؟')) return;

            $.ajax({
                url: 'products.php',
                method: 'POST',
                data: { action: 'delete', id: id },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.message);
                        location.reload();
                    } else {
                        alert('❌ ' + response.message);
                    }
                }
            });
        }

        // View Toggle
        function setView(view) {
            console.log('View:', view);
        }
    </script>
</body>
</html>