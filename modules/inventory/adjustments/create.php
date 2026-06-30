<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إنشاء جرد دوري جديد';

// Get branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Get active stores with branch info
$stores = $db->query("
    SELECT s.*, b.branch_name 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY b.branch_name, s.store_name
")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];

        $store_id = intval($_POST['store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($store_id <= 0) {
            $errors[] = 'يجب اختيار المخزن';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // Generate adjustment code - fixed to avoid floating point issues
        $year_month = date('Ym');
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM stock_adjustments WHERE adjustment_code LIKE 'ADJ-{$year_month}-%'");
        $count = intval($stmt->fetch()['cnt']) + 1;
        $adjustment_code = 'ADJ-' . $year_month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Insert adjustment
        $stmt = $db->prepare("
            INSERT INTO stock_adjustments 
            (adjustment_code, store_id, adjustment_type, status, notes, counted_by, counted_at, created_at)
            VALUES (?, ?, 'periodic', 'draft', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $adjustment_code,
            $store_id,
            $notes ?: null,
            $_SESSION['user_id'] ?? 1
        ]);

        $adjustment_id = $db->lastInsertId();

        // Get current stock items and insert them
        $stock_items = $db->prepare("
            SELECT ii.*, p.product_name, p.product_code, p.manual_code, p.cost_price, b.exp_date
            FROM inventory_items ii
            JOIN products p ON ii.product_id = p.id
            LEFT JOIN inventory_batches b ON ii.batch_id = b.id
            WHERE ii.store_id = ? AND ii.is_active = 1
        ");
        $stock_items->execute([$store_id]);
        $items = $stock_items->fetchAll();

        $item_stmt = $db->prepare("
            INSERT INTO stock_adjustment_items 
            (adjustment_id, product_id, batch_id, system_qty, actual_qty, variance_qty, unit_cost, variance_cost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $total_items = 0;
        foreach ($items as $item) {
            $system_qty = floatval($item['quantity']);
            $unit_cost = floatval($item['unit_cost'] ?? $item['cost_price'] ?? 0);
            $item_stmt->execute([
                $adjustment_id,
                $item['product_id'],
                $item['batch_id'],
                $system_qty,
                0, // actual_qty starts at 0
                -$system_qty, // variance = actual - system
                $unit_cost,
                -($system_qty * $unit_cost)
            ]);
            $total_items++;
        }

        // Update total items
        $db->prepare("UPDATE stock_adjustments SET total_items = ? WHERE id = ?")
            ->execute([$total_items, $adjustment_id]);

        header("Location: view.php?id={$adjustment_id}&success=1");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../../includes/sidebar.php';
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
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin: 25px 0 20px; }
        .form-label { font-weight: 600; color: #555; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-plus-lg"></i> <?= $page_title ?></h2>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="card">
                    <div class="card-body">
                        <h5 class="section-title"><i class="bi bi-clipboard-check"></i> بيانات الجرد الدوري</h5>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            سيتم إنشاء عملية جرد دوري وجلب جميع الأصناف الموجودة في المخزن. يمكنك بعد ذلك إدخال الكميات الفعلية.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">الفرع</label>
                                <select id="branch_filter" class="form-select" onchange="filterStores()">
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">المخزن <span class="text-danger">*</span></label>
                                    <select name="store_id" id="store_select" class="form-select" required>
                                        <option value="">-- اختر المخزن --</option>
                                        <?php foreach ($stores as $s): ?>
                                            <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? '0' ?>" style="display:none;">
                                                <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">ملاحظات</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات عن الجرد..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-play-circle"></i> بدء الجرد وجلب الأصناف
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg">إلغاء</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterStores() {
            const branchId = document.getElementById('branch_filter').value;
            const storeSelect = document.getElementById('store_select');
            const options = storeSelect.querySelectorAll('option[data-branch]');
            options.forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (!branchId || opt.getAttribute('data-branch') === branchId) ? '' : 'none';
            });
            storeSelect.value = '';
        }
    </script>
</body>
</html>
