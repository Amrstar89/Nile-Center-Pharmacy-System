<?php
/**
 * إضافة صنف جديد إلى المخزن (من شاشة تعديل الأسعار)
 */
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$store_id = intval($_GET['store_id'] ?? 0);
$product_id = intval($_GET['product_id'] ?? 0);

if ($store_id <= 0 || $product_id <= 0) {
    die('معرف المخزن والصنف مطلوب');
}

// Get product info
$product = $db->prepare("
    SELECT p.*, u.unit_name_ar 
    FROM products p 
    LEFT JOIN product_units u ON p.unit_id = u.id 
    WHERE p.id = ?
");
$product->execute([$product_id]);
$prod = $product->fetch();

if (!$prod) {
    die('الصنف غير موجود');
}

// Get store info
$store = $db->prepare("SELECT store_name FROM stores WHERE id = ?");
$store->execute([$store_id]);
$store_name = $store->fetch()['store_name'] ?? '';

// Check if product already exists in this store
$existing = $db->prepare("SELECT id, quantity FROM inventory_items WHERE store_id = ? AND product_id = ? AND is_active = 1");
$existing->execute([$store_id, $product_id]);
$ex = $existing->fetch();

$page_title = 'إضافة صنف إلى المخزن';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $quantity = floatval($_POST['quantity'] ?? 0);
        $unit_cost = floatval($_POST['unit_cost'] ?? 0);
        $sell_price = floatval($_POST['sell_price'] ?? 0);
        $discount = floatval($_POST['discount_percent'] ?? 0);
        $vat = floatval($_POST['vat_percent'] ?? 0);
        $exp_date = !empty($_POST['exp_date']) ? $_POST['exp_date'] : null;
        $batch_number = !empty($_POST['batch_number']) ? $_POST['batch_number'] : 'BP-' . time();

        if ($quantity <= 0) throw new Exception('الكمية يجب أن تكون أكبر من صفر');

        $db->beginTransaction();

        if ($ex) {
            // Update existing
            $db->prepare("
                UPDATE inventory_items 
                SET quantity = quantity + ?, unit_cost = ?, sell_price = ?, 
                    discount_percent = ?, vat_percent = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$quantity, $unit_cost, $sell_price, $discount, $vat, $ex['id']]);
        } else {
            // Insert new
            $db->prepare("
                INSERT INTO inventory_items 
                (store_id, product_id, quantity, unit_cost, sell_price, 
                 discount_percent, vat_percent, reorder_point, max_stock, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
            ")->execute([$store_id, $product_id, $quantity, $unit_cost, $sell_price, $discount, $vat]);
        }

        // Add batch if expiry date provided
        if ($exp_date && $prod['has_expire']) {
            $db->prepare("
                INSERT INTO inventory_batches 
                (product_id, store_id, batch_number, exp_date, initial_qty, remaining_qty, unit_cost, sell_price, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$product_id, $store_id, $batch_number, $exp_date, $quantity, $quantity, $unit_cost, $sell_price]);
        }

        $db->commit();

        header("Location: index.php?branch=" . intval($_GET['branch'] ?? 0) . "&store={$store_id}&success=" . urlencode('تم إضافة الصنف بنجاح'));
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../../includes/sidebar.php';
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
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .product-card { background: linear-gradient(135deg, #f0f4ff 0%, #e8e8f0 100%); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-plus-lg"></i> <?= $page_title ?></h2>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
            <?php endif; ?>

            <?php if ($ex): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    هذا الصنف موجود بالفعل في المخزن برصيد <?= number_format(floatval($ex['quantity']), 3) ?>. سيتم إضافة الكمية الجديدة إلى الرصيد الحالي.
                </div>
            <?php endif; ?>

            <div class="product-card">
                <h5><i class="bi bi-box"></i> <?= htmlspecialchars($prod['product_name']) ?></h5>
                <div class="row text-muted small mt-2">
                    <div class="col-md-3">كود: <?= htmlspecialchars($prod['product_code'] ?? $prod['manual_code'] ?? '-') ?></div>
                    <div class="col-md-3">باركود: <?= htmlspecialchars($prod['barcode'] ?? '-') ?></div>
                    <div class="col-md-3">الوحدة: <?= htmlspecialchars($prod['unit_name_ar'] ?? 'علبة') ?></div>
                    <div class="col-md-3">المخزن: <?= htmlspecialchars($store_name) ?></div>
                </div>
                <?php if ($prod['has_expire']): ?>
                    <span class="badge bg-warning text-dark mt-2"><i class="bi bi-calendar"></i> له تاريخ صلاحية</span>
                <?php endif; ?>
            </div>

            <form method="POST">
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">الكمية *</label>
                                <input type="number" name="quantity" class="form-control" step="0.001" min="0.001" value="1" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">تكلفة الوحدة</label>
                                <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" 
                                       value="<?= $prod['cost_price'] ?? '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">سعر البيع</label>
                                <input type="number" name="sell_price" class="form-control" step="0.01" min="0" 
                                       value="<?= $prod['sell_price'] ?? '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">رقم الدفعة</label>
                                <input type="text" name="batch_number" class="form-control" value="BP-<?= time() ?>">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-3">
                                <label class="form-label">الخصم %</label>
                                <input type="number" name="discount_percent" class="form-control" step="0.01" min="0" max="100" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الضريبة %</label>
                                <input type="number" name="vat_percent" class="form-control" step="0.01" min="0" max="100" value="0">
                            </div>
                            <?php if ($prod['has_expire']): ?>
                            <div class="col-md-3">
                                <label class="form-label">تاريخ الصلاحية *</label>
                                <input type="date" name="exp_date" class="form-control" required>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-plus-lg"></i> إضافة إلى المخزن</button>
                        <a href="index.php?branch=<?= intval($_GET['branch'] ?? 0) ?>&store=<?= $store_id ?>" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
