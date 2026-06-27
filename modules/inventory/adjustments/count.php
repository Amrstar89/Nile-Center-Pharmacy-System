<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$adjustment_id = intval($_GET['id'] ?? 0);
if ($adjustment_id <= 0) {
    header("Location: index.php");
    exit;
}

// Get adjustment with store info
$stmt = $db->prepare("
    SELECT a.*, s.store_name, s.store_code, b.branch_name
    FROM stock_adjustments a
    LEFT JOIN stores s ON a.store_id = s.id
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE a.id = ?
");
$stmt->execute([$adjustment_id]);
$adjustment = $stmt->fetch();

if (!$adjustment) {
    header("Location: index.php");
    exit;
}

// Handle counting submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actual_qty'])) {
    try {
        $db->beginTransaction();

        $total_variance_qty = 0;
        $total_variance_cost = 0;

        foreach ($_POST['actual_qty'] as $item_id => $actual_qty) {
            $actual_qty = floatval($actual_qty);
            $system_qty = floatval($_POST['system_qty'][$item_id] ?? 0);
            $unit_cost = floatval($_POST['unit_cost'][$item_id] ?? 0);

            $variance_qty = $actual_qty - $system_qty;
            $variance_cost = $variance_qty * $unit_cost;

            $total_variance_qty += $variance_qty;
            $total_variance_cost += $variance_cost;

            $db->prepare("
                UPDATE stock_adjustment_items 
                SET actual_qty = ?, variance_qty = ?, variance_cost = ?
                WHERE id = ? AND adjustment_id = ?
            ")->execute([$actual_qty, $variance_qty, $variance_cost, $item_id, $adjustment_id]);
        }

        // Update adjustment status
        $db->prepare("
            UPDATE stock_adjustments 
            SET status = 'counting', total_variance_qty = ?, total_variance_cost = ?
            WHERE id = ?
        ")->execute([$total_variance_qty, $total_variance_cost, $adjustment_id]);

        $db->commit();

        if (isset($_POST['complete'])) {
            // Complete the adjustment
            $db->prepare("UPDATE stock_adjustments SET status = 'completed' WHERE id = ?")
                ->execute([$adjustment_id]);

            // Create inventory transactions for adjustments
            $items = $db->prepare("
                SELECT sai.*, ii.id as inv_item_id
                FROM stock_adjustment_items sai
                LEFT JOIN inventory_items ii ON sai.product_id = ii.product_id AND ii.store_id = ?
                WHERE sai.adjustment_id = ? AND sai.variance_qty != 0
            ");
            $items->execute([$adjustment['store_id'], $adjustment_id]);

            foreach ($items->fetchAll() as $item) {
                $tx_type = $item['variance_qty'] > 0 ? 'adjustment' : 'damage';
                $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_type, reference_type, reference_id, store_id, product_id, quantity, quantity_base, unit_cost, total_cost, notes, created_by, created_at)
                    VALUES (?, 'adjustment', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $tx_type,
                    $adjustment_id,
                    $adjustment['store_id'],
                    $item['product_id'],
                    $item['variance_qty'],
                    $item['variance_qty'],
                    $item['unit_cost'],
                    $item['variance_cost'],
                    'جرد: ' . $adjustment['adjustment_code'],
                    $_SESSION['user_id'] ?? 1
                ]);

                // Update inventory_items quantity
                if ($item['inv_item_id']) {
                    $db->prepare("UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?")
                        ->execute([$item['variance_qty'], $item['inv_item_id']]);
                }
            }

            header("Location: index.php?completed=1");
            exit;
        }

        header("Location: count.php?id={$adjustment_id}&saved=1");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get adjustment items
$items = $db->prepare("
    SELECT sai.*, p.product_name, p.product_code, p.manual_code
    FROM stock_adjustment_items sai
    JOIN products p ON sai.product_id = p.id
    WHERE sai.adjustment_id = ?
    ORDER BY p.product_name
");
$items->execute([$adjustment_id]);
$items = $items->fetchAll();

$page_title = 'جرد المخزن - ' . $adjustment['store_name'];
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
        .count-input { font-size: 16px; font-weight: bold; text-align: center; }
        .variance-positive { color: var(--success); }
        .variance-negative { color: var(--danger); }
        .variance-zero { color: #6c757d; }
        .sticky-header { position: sticky; top: 0; background: white; z-index: 10; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-clipboard-data"></i> <?= $page_title ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">الجرد</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($adjustment['adjustment_code']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> رجوع</a>
                </div>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success">تم حفظ العد بنجاح!</div>
            <?php endif; ?>
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- Adjustment Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>المخزن:</strong> <?= htmlspecialchars($adjustment['store_name']) ?> (<?= htmlspecialchars($adjustment['store_code']) ?>)
                        </div>
                        <div class="col-md-4">
                            <strong>نوع الجرد:</strong> 
                            <?php 
                            $type_labels = ['periodic' => 'جرد دوري', 'spot' => 'جرد مفاجئ', 'year_end' => 'جرد نهاية سنة', 'damage' => 'جرد تالف', 'expired' => 'جرد هالك'];
                            echo $type_labels[$adjustment['adjustment_type']] ?? 'غير معروف';
                            ?>
                        </div>
                        <div class="col-md-4">
                            <strong>الحالة:</strong> 
                            <?php if ($adjustment['status'] === 'draft'): ?>
                                <span class="badge bg-secondary">مسودة</span>
                            <?php elseif ($adjustment['status'] === 'counting'): ?>
                                <span class="badge bg-warning">جاري العد</span>
                            <?php elseif ($adjustment['status'] === 'completed'): ?>
                                <span class="badge bg-success">مكتمل</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Counting Form -->
            <form method="POST" action="" id="countForm">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light sticky-header">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>الصنف</th>
                                        <th>كود الصنف</th>
                                        <th style="width: 120px;">كمية النظام</th>
                                        <th style="width: 150px;">الكمية الفعلية</th>
                                        <th style="width: 120px;">الفرق</th>
                                        <th style="width: 120px;">التكلفة</th>
                                        <th style="width: 150px;">قيمة الفرق</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item): ?>
                                    <?php
                                        $variance = $item['actual_qty'] - $item['system_qty'];
                                        $variance_class = $variance > 0 ? 'variance-positive' : ($variance < 0 ? 'variance-negative' : 'variance-zero');
                                        $variance_icon = $variance > 0 ? 'bi-arrow-up' : ($variance < 0 ? 'bi-arrow-down' : 'bi-dash');
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><code><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? 'N/A') ?></code></td>
                                        <td class="text-center">
                                            <?= number_format($item['system_qty'], 3) ?>
                                            <input type="hidden" name="system_qty[<?= $item['id'] ?>]" value="<?= $item['system_qty'] ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="actual_qty[<?= $item['id'] ?>]" 
                                                   class="form-control count-input" 
                                                   step="0.001" min="0" 
                                                   value="<?= number_format($item['actual_qty'], 3) ?>"
                                                   onchange="calculateVariance(this, <?= $item['id'] ?>)"
                                                   <?= $adjustment['status'] === 'completed' ? 'readonly' : '' ?>>
                                        </td>
                                        <td class="text-center <?= $variance_class ?>" id="variance_<?= $item['id'] ?>">
                                            <i class="bi <?= $variance_icon ?>"></i> <?= number_format(abs($variance), 3) ?>
                                        </td>
                                        <td>
                                            <?= number_format($item['unit_cost'], 2) ?> ج
                                            <input type="hidden" name="unit_cost[<?= $item['id'] ?>]" value="<?= $item['unit_cost'] ?>">
                                        </td>
                                        <td class="text-center <?= $variance_class ?>" id="variance_cost_<?= $item['id'] ?>">
                                            <?= number_format($item['variance_cost'], 2) ?> ج
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($adjustment['status'] !== 'completed'): ?>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ العد
                        </button>
                        <button type="submit" name="complete" value="1" class="btn btn-success btn-lg" onclick="return confirm('هل أنت متأكد من إتمام الجرد؟ سيتم تسوية الفروق في المخزن.')">
                            <i class="bi bi-check-circle"></i> إتمام الجرد
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function calculateVariance(input, itemId) {
            const actualQty = parseFloat(input.value) || 0;
            const systemQty = parseFloat(document.querySelector('input[name="system_qty[' + itemId + ']"]').value) || 0;
            const unitCost = parseFloat(document.querySelector('input[name="unit_cost[' + itemId + ']"]').value) || 0;

            const variance = actualQty - systemQty;
            const varianceCost = variance * unitCost;

            const varianceCell = document.getElementById('variance_' + itemId);
            const varianceCostCell = document.getElementById('variance_cost_' + itemId);

            let icon = 'bi-dash';
            let colorClass = 'variance-zero';

            if (variance > 0) {
                icon = 'bi-arrow-up';
                colorClass = 'variance-positive';
            } else if (variance < 0) {
                icon = 'bi-arrow-down';
                colorClass = 'variance-negative';
            }

            varianceCell.className = 'text-center ' + colorClass;
            varianceCell.innerHTML = '<i class="bi ' + icon + '"></i> ' + Math.abs(variance).toFixed(3);

            varianceCostCell.className = 'text-center ' + colorClass;
            varianceCostCell.innerHTML = varianceCost.toFixed(2) + ' ج';
        }
    </script>
</body>
</html>
