<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Get adjustment info
$stmt = $db->prepare("
    SELECT a.*, s.store_name, s.store_code, b.branch_name,
           u1.full_name as counted_by_name, u2.full_name as approved_by_name
    FROM stock_adjustments a
    LEFT JOIN stores s ON a.store_id = s.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN users u1 ON a.counted_by = u1.id
    LEFT JOIN users u2 ON a.approved_by = u2.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$adjustment = $stmt->fetch();

if (!$adjustment) {
    header('Location: index.php?error=' . urlencode('الجرد غير موجود'));
    exit;
}

$page_title = 'عرض الجرد: ' . $adjustment['adjustment_code'];

// Get adjustment items
$items = $db->prepare("
    SELECT ai.*, p.product_name, p.product_code, p.manual_code
    FROM stock_adjustment_items ai
    JOIN products p ON ai.product_id = p.id
    WHERE ai.adjustment_id = ?
    ORDER BY p.product_name
");
$items->execute([$id]);
$adjustment_items = $items->fetchAll();

$total_variance = array_sum(array_column($adjustment_items, 'variance_cost'));
$total_var_items = count(array_filter($adjustment_items, fn($i) => floatval($i['variance_qty']) != 0));

$status_labels = [
    'draft' => ['مسودة', 'bg-secondary', 'bi-pencil'],
    'counting' => ['جاري العد', 'bg-warning text-dark', 'bi-clipboard-data'],
    'completed' => ['مكتمل', 'bg-success', 'bi-check-circle'],
    'cancelled' => ['ملغي', 'bg-dark', 'bi-x-octagon']
];
$status_info = $status_labels[$adjustment['status']] ?? ['غير معروف', 'bg-secondary', 'bi-question'];

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
        .adj-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; }
        .adj-code { background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 8px; font-family: monospace; }
        .variance-positive { color: #198754; }
        .variance-negative { color: #dc3545; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right"></i> العودة</a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> تمت العملية بنجاح</div>
            <?php endif; ?>

            <!-- Header -->
            <div class="adj-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-2"><i class="bi bi-clipboard-check"></i> <?= htmlspecialchars($adjustment['adjustment_code']) ?></h3>
                        <span class="adj-code"><?= htmlspecialchars($adjustment['store_code'] ?? '') ?></span>
                        <span class="badge bg-light text-dark ms-2"><?= htmlspecialchars($adjustment['store_name']) ?></span>
                        <span class="badge <?= $status_info[1] ?> ms-2"><i class="bi <?= $status_info[2] ?>"></i> <?= $status_info[0] ?></span>
                    </div>
                    <div class="btn-group">
                        <?php if ($adjustment['status'] === 'draft'): ?>
                            <a href="count.php?id=<?= $id ?>" class="btn btn-light"><i class="bi bi-play-fill"></i> بدء العد</a>
                        <?php elseif ($adjustment['status'] === 'counting'): ?>
                            <a href="count.php?id=<?= $id ?>" class="btn btn-light"><i class="bi bi-pencil"></i> متابعة العد</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3 row">
                    <div class="col-md-3"><small><i class="bi bi-building"></i> <?= htmlspecialchars($adjustment['branch_name'] ?? 'مركزي') ?></small></div>
                    <div class="col-md-3"><small><i class="bi bi-person"></i> <?= htmlspecialchars($adjustment['counted_by_name'] ?? 'غير معروف') ?></small></div>
                    <div class="col-md-3"><small><i class="bi bi-calendar"></i> <?= date('Y-m-d', strtotime($adjustment['created_at'])) ?></small></div>
                    <div class="col-md-3"><small><i class="bi bi-boxes"></i> <?= $adjustment['total_items'] ?> صنف</small></div>
                </div>
                <?php if ($adjustment['notes']): ?>
                    <div class="mt-2"><small><i class="bi bi-info-circle"></i> <?= htmlspecialchars($adjustment['notes']) ?></small></div>
                <?php endif; ?>
            </div>

            <!-- Variance Summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h4><?= number_format($adjustment['total_items']) ?></h4>
                        <small class="text-muted">إجمالي الأصناف</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h4 class="<?= $total_variance >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                            <?= number_format(abs($total_variance), 2) ?> ج
                            <?= $total_variance >= 0 ? '<i class="bi bi-arrow-up-circle"></i>' : '<i class="bi bi-arrow-down-circle"></i>' ?>
                        </h4>
                        <small class="text-muted">صافي الفرق</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3">
                        <h4 class="<?= $total_var_items > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($total_var_items) ?></h4>
                        <small class="text-muted">أصناف بها فروقات</small>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> تفاصيل الجرد</h5>
                    <span class="badge bg-primary"><?= count($adjustment_items) ?> صنف</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>الصنف</th>
                                    <th class="text-center">رصيد النظام</th>
                                    <th class="text-center">الرصيد الفعلي</th>
                                    <th class="text-center">الفرق</th>
                                    <th class="text-center">التكلفة</th>
                                    <th class="text-center">قيمة الفرق</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adjustment_items as $i => $item): 
                                    $variance = floatval($item['variance_qty']);
                                    $variance_cost = floatval($item['variance_cost']);
                                    $row_class = $variance > 0 ? 'table-success' : ($variance < 0 ? 'table-danger' : '');
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? '') ?></small>
                                    </td>
                                    <td class="text-center"><?= number_format(floatval($item['system_qty']), 3) ?></td>
                                    <td class="text-center"><?= number_format(floatval($item['actual_qty']), 3) ?></td>
                                    <td class="text-center <?= $variance >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                                        <?= ($variance > 0 ? '+' : '') . number_format($variance, 3) ?>
                                    </td>
                                    <td class="text-center"><?= number_format(floatval($item['unit_cost']), 2) ?> ج</td>
                                    <td class="text-center <?= $variance_cost >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                                        <?= ($variance_cost > 0 ? '+' : '') . number_format($variance_cost, 2) ?> ج
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adjustment_items)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">لا توجد أصناف</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mt-4">
                <div class="card-body text-center">
                    <?php if ($adjustment['status'] === 'draft'): ?>
                        <a href="count.php?id=<?= $id ?>" class="btn btn-primary btn-lg"><i class="bi bi-play-fill"></i> بدء عملية العد</a>
                    <?php elseif ($adjustment['status'] === 'counting'): ?>
                        <a href="count.php?id=<?= $id ?>" class="btn btn-warning btn-lg"><i class="bi bi-pencil"></i> متابعة العد</a>
                        <a href="complete.php?id=<?= $id ?>" class="btn btn-success btn-lg" onclick="return confirm('تأكيد إتمام الجرد؟')"><i class="bi bi-check-lg"></i> إتمام الجرد</a>
                    <?php elseif ($adjustment['status'] === 'completed'): ?>
                        <span class="badge bg-success p-3"><i class="bi bi-check-circle"></i> تم إتمام الجرد بنجاح</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
