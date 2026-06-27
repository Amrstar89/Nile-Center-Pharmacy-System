<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إنشاء جرد جديد';

// Get active stores
$stores = $db->query("
    SELECT s.*, b.branch_name 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY s.store_name
")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];

        $store_id = intval($_POST['store_id'] ?? 0);
        $adjustment_type = $_POST['adjustment_type'] ?? 'periodic';
        $notes = trim($_POST['notes'] ?? '');

        if ($store_id <= 0) {
            $errors[] = 'يجب اختيار المخزن';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // Generate adjustment code
        $last_code = $db->query("SELECT MAX(CAST(SUBSTRING(adjustment_code, 4) AS UNSIGNED)) as max_code FROM stock_adjustments WHERE adjustment_code LIKE 'ADJ-%'")->fetch();
        $max_code = intval($last_code['max_code'] ?? 0);
        $adjustment_code = 'ADJ-' . str_pad($max_code + 1, 5, '0', STR_PAD_LEFT);

        // Insert adjustment
        $stmt = $db->prepare("
            INSERT INTO stock_adjustments 
            (adjustment_code, store_id, adjustment_type, status, notes, counted_by, counted_at, created_at)
            VALUES (?, ?, ?, 'draft', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $adjustment_code,
            $store_id,
            $adjustment_type,
            $notes ?: null,
            $_SESSION['user_id'] ?? 1
        ]);

        $adjustment_id = $db->lastInsertId();

        // Get current stock items and insert them
        $stock_items = $db->prepare("
            SELECT ii.*, p.product_name, p.product_code, p.manual_code, p.cost_price
            FROM inventory_items ii
            JOIN products p ON ii.product_id = p.id
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
            $item_stmt->execute([
                $adjustment_id,
                $item['product_id'],
                $item['batch_id'],
                $item['quantity'],
                0, // actual_qty starts at 0
                -$item['quantity'], // variance = actual - system = 0 - quantity
                $item['unit_cost'] ?? $item['cost_price'] ?? 0,
                -($item['quantity'] * ($item['unit_cost'] ?? $item['cost_price'] ?? 0))
            ]);
            $total_items++;
        }

        // Update total items
        $db->prepare("UPDATE stock_adjustments SET total_items = ? WHERE id = ?")
            ->execute([$total_items, $adjustment_id]);

        header("Location: count.php?id={$adjustment_id}");
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin-bottom: 20px; }
        .form-label { font-weight: 600; color: #555; }
        .type-card { cursor: pointer; transition: all 0.3s; border: 2px solid #e9ecef; border-radius: 10px; padding: 15px; text-align: center; }
        .type-card:hover { border-color: var(--primary); }
        .type-card.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%); }
        .type-card i { font-size: 24px; margin-bottom: 8px; color: var(--primary); }
        .type-card h6 { font-size: 14px; margin: 0; }
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
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="adjustmentForm">
                <div class="card">
                    <div class="card-body">
                        <h5 class="section-title">معلومات الجرد</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">المخزن <span class="text-danger">*</span></label>
                                    <select name="store_id" class="form-select" required>
                                        <option value="">-- اختر المخزن --</option>
                                        <?php foreach ($stores as $s): ?>
                                            <option value="<?= $s['id'] ?>">
                                                <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <h5 class="section-title mt-4">نوع الجرد</h5>
                        <div class="row">
                            <?php 
                            $types = [
                                'periodic' => ['جرد دوري', 'bi-calendar-check'],
                                'spot' => ['جرد مفاجئ', 'bi-lightning'],
                                'year_end' => ['جرد نهاية سنة', 'bi-calendar-event'],
                                'damage' => ['جرد تالف', 'bi-trash'],
                                'expired' => ['جرد هالك', 'bi-exclamation-triangle']
                            ];
                            foreach ($types as $key => $label): 
                            ?>
                            <div class="col-md-3 mb-3">
                                <div class="type-card <?= (isset($_POST['adjustment_type']) && $_POST['adjustment_type'] === $key) || (!isset($_POST['adjustment_type']) && $key === 'periodic') ? 'selected' : '' ?>" onclick="selectType('<?= $key ?>')">
                                    <i class="bi <?= $label[1] ?>"></i>
                                    <h6><?= $label[0] ?></h6>
                                    <input type="radio" name="adjustment_type" value="<?= $key ?>" class="d-none" <?= (isset($_POST['adjustment_type']) && $_POST['adjustment_type'] === $key) || (!isset($_POST['adjustment_type']) && $key === 'periodic') ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">ملاحظات</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عن الجرد..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-play-circle"></i> بدء الجرد
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectType(type) {
            document.querySelectorAll('.type-card').forEach(card => card.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>
