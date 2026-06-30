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

$items = $db->prepare("
    SELECT sai.*, p.product_name, p.product_code, p.manual_code
    FROM stock_adjustment_items sai
    JOIN products p ON sai.product_id = p.id
    WHERE sai.adjustment_id = ?
    ORDER BY p.product_name
");
$items->execute([$adjustment_id]);
$items = $items->fetchAll();

$page_title = 'عرض الجرد - ' . $adjustment['adjustment_code'];
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .variance-positive { color: var(--success); }
        .variance-negative { color: var(--danger); }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-eye"></i> <?= $page_title ?></h2>
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

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3"><strong>المخزن:</strong> <?= htmlspecialchars($adjustment['store_name']) ?></div>
                        <div class="col-md-3"><strong>النوع:</strong> جرد دوري</div>
                        <div class="col-md-3">
                            <strong>الحالة:</strong>
                            <?php if ($adjustment['status'] === 'completed'): ?>
                                <span class="badge bg-success">مكتمل</span>
                            <?php elseif ($adjustment['status'] === 'counting'): ?>
                                <span class="badge bg-warning">جاري العد</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">مسودة</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3"><strong>التاريخ:</strong> <?= date('Y-m-d', strtotime($adjustment['created_at'])) ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th><th>الصنف</th><th>كود</th>
                                    <th>كمية النظام</th><th>الكمية الفعلية</th>
                                    <th>الفرق</th><th>التكلفة</th><th>قيمة الفرق</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $i => $item): 
                                    $variance = ($item['actual_qty'] ?? 0) - $item['system_qty'];
                                    $vc = $variance * $item['unit_cost'];
                                    $vc_class = $variance > 0 ? 'variance-positive' : ($variance < 0 ? 'variance-negative' : '');
                                ?>
                                <tr>
                                    <td><?= $i+1 ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td><code><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? 'N/A') ?></code></td>
                                    <td><?= number_format($item['system_qty'], 3) ?></td>
                                    <td><?= number_format($item['actual_qty'] ?? 0, 3) ?></td>
                                    <td class="<?= $vc_class ?> fw-bold"><?= ($variance > 0 ? '+' : '') . number_format($variance, 3) ?></td>
                                    <td><?= number_format($item['unit_cost'], 2) ?> ج</td>
                                    <td class="<?= $vc_class ?>"><?= number_format($vc, 2) ?> ج</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="text-start fw-bold">الإجمالي</td>
                                    <td class="fw-bold"><?= number_format(array_sum(array_map(function($i){ return ($i['actual_qty']??0)-$i['system_qty']; }, $items)), 3) ?></td>
                                    <td></td>
                                    <td class="fw-bold"><?= number_format(array_sum(array_map(function($i){ return (($i['actual_qty']??0)-$i['system_qty'])*$i['unit_cost']; }, $items)), 2) ?> ج</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>