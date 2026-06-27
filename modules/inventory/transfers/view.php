<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$transfer_id = intval($_GET['id'] ?? 0);
if ($transfer_id <= 0) {
    header("Location: index.php");
    exit;
}

// Get transfer with store and branch info
$stmt = $db->prepare("
    SELECT t.*,
        fs.store_name as from_store_name, fs.store_code as from_store_code,
        ts.store_name as to_store_name, ts.store_code as to_store_code,
        fb.branch_name as from_branch_name,
        tb.branch_name as to_branch_name,
        u1.full_name as requested_by_name,
        u2.full_name as approved_by_name,
        u3.full_name as shipped_by_name,
        u4.full_name as received_by_name
    FROM inventory_transfers t
    LEFT JOIN stores fs ON t.from_store_id = fs.id
    LEFT JOIN stores ts ON t.to_store_id = ts.id
    LEFT JOIN branches fb ON t.from_branch_id = fb.id
    LEFT JOIN branches tb ON t.to_branch_id = tb.id
    LEFT JOIN users u1 ON t.requested_by = u1.id
    LEFT JOIN users u2 ON t.approved_by = u2.id
    LEFT JOIN users u3 ON t.shipped_by = u3.id
    LEFT JOIN users u4 ON t.received_by = u4.id
    WHERE t.id = ?
");
$stmt->execute([$transfer_id]);
$transfer = $stmt->fetch();

if (!$transfer) {
    header("Location: index.php");
    exit;
}

// Get transfer items
$items = $db->prepare("
    SELECT ti.*, p.product_name, p.product_code, p.manual_code, u.unit_name_ar
    FROM inventory_transfer_items ti
    JOIN products p ON ti.product_id = p.id
    LEFT JOIN product_units u ON ti.unit_id = u.id
    WHERE ti.transfer_id = ?
    ORDER BY ti.id
");
$items->execute([$transfer_id]);
$items = $items->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'] ?? 1;

    try {
        switch ($action) {
            case 'approve':
                // المرسل يعتمد التحويل (يؤكد إنه هيشحن)
                if ($transfer['status'] === 'draft') {
                    $db->prepare("UPDATE inventory_transfers SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                        ->execute([$user_id, $transfer_id]);
                }
                break;

            case 'ship':
                // المرسل يشحن البضاعة فعلياً
                if ($transfer['status'] === 'approved') {
                    $db->beginTransaction();

                    // Update transfer status
                    $db->prepare("UPDATE inventory_transfers SET status = 'shipped', shipped_by = ?, shipped_at = NOW() WHERE id = ?")
                        ->execute([$user_id, $transfer_id]);

                    // Deduct from source store
                    foreach ($items as $item) {
                        $db->prepare("
                            INSERT INTO inventory_transactions 
                            (transaction_type, reference_type, reference_id, store_id, product_id, batch_id, quantity, quantity_base, unit_cost, total_cost, notes, created_by, created_at)
                            VALUES ('transfer_out', 'transfer_order', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ") ->execute([
                            $transfer_id,
                            $transfer['from_store_id'],
                            $item['product_id'],
                            $item['batch_id'],
                            -$item['requested_qty'],
                            -$item['requested_qty'] * ($item['unit_conversion'] ?? 1),
                            $item['unit_cost'],
                            $item['requested_qty'] * $item['unit_cost'],
                            'تحويل صادر: ' . $transfer['transfer_code'],
                            $user_id
                        ]);

                        // Update inventory_items quantity (deduct)
                        $db->prepare("
                            UPDATE inventory_items 
                            SET quantity = quantity - ?, updated_at = NOW()
                            WHERE store_id = ? AND product_id = ?
                        ")->execute([
                            $item['requested_qty'],
                            $transfer['from_store_id'],
                            $item['product_id']
                        ]);
                    }

                    $db->commit();
                }
                break;

            case 'receive':
                // المستلم يستلم البضاعة ويراجع الكميات
                if ($transfer['status'] === 'shipped' || $transfer['status'] === 'partial_received') {
                    $db->beginTransaction();

                    // Update received quantities for each item
                    $all_received = true;
                    foreach ($items as $item) {
                        $received_qty = isset($_POST['received'][$item['id']]) ? floatval($_POST['received'][$item['id']]) : 0;

                        if ($received_qty > 0) {
                            // Update item received quantity
                            $db->prepare("UPDATE inventory_transfer_items SET received_qty = ?, status = 'received' WHERE id = ? AND transfer_id = ?")
                                ->execute([$received_qty, $item['id'], $transfer_id]);

                            // Add to destination store inventory
                            $db->prepare("
                                INSERT INTO inventory_transactions 
                                (transaction_type, reference_type, reference_id, store_id, product_id, batch_id, quantity, quantity_base, unit_cost, total_cost, notes, created_by, created_at)
                                VALUES ('transfer_in', 'transfer_order', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ") ->execute([
                                $transfer_id,
                                $transfer['to_store_id'],
                                $item['product_id'],
                                $item['batch_id'],
                                $received_qty,
                                $received_qty * ($item['unit_conversion'] ?? 1),
                                $item['unit_cost'],
                                $received_qty * $item['unit_cost'],
                                'تحويل وارد: ' . $transfer['transfer_code'],
                                $user_id
                            ]);

                            // Update inventory_items (add to destination)
                            $existing = $db->prepare("SELECT id FROM inventory_items WHERE store_id = ? AND product_id = ? AND batch_id IS NULL")
                                ->execute([$transfer['to_store_id'], $item['product_id']]);

                            if ($existing->fetch()) {
                                $db->prepare("
                                    UPDATE inventory_items 
                                    SET quantity = quantity + ?, unit_cost = ?, updated_at = NOW()
                                    WHERE store_id = ? AND product_id = ?
                                ")->execute([$received_qty, $item['unit_cost'], $transfer['to_store_id'], $item['product_id']]);
                            } else {
                                $db->prepare("
                                    INSERT INTO inventory_items 
                                    (store_id, product_id, quantity, unit_cost, is_active, created_at)
                                    VALUES (?, ?, ?, ?, 1, NOW())
                                ")->execute([$transfer['to_store_id'], $item['product_id'], $received_qty, $item['unit_cost']]);
                            }
                        }

                        // Check if all items received
                        if ($received_qty < $item['requested_qty']) {
                            $all_received = false;
                        }
                    }

                    // Update transfer status
                    $new_status = $all_received ? 'received' : 'partial_received';
                    $db->prepare("UPDATE inventory_transfers SET status = ?, received_by = ?, received_at = NOW() WHERE id = ?")
                        ->execute([$new_status, $user_id, $transfer_id]);

                    $db->commit();
                }
                break;

            case 'reject':
                // المستلم يرفض التحويل (يرجع للمرسل)
                if ($transfer['status'] === 'shipped') {
                    $db->prepare("UPDATE inventory_transfers SET status = 'rejected' WHERE id = ?")
                        ->execute([$transfer_id]);
                }
                break;

            case 'cancel':
                // المرسل يلغي التحويل قبل الشحن
                if (in_array($transfer['status'], ['draft', 'approved'])) {
                    $db->prepare("UPDATE inventory_transfers SET status = 'cancelled' WHERE id = ?")
                        ->execute([$transfer_id]);
                }
                break;
        }

        header("Location: view.php?id={$transfer_id}&updated=1");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Status labels
$status_labels = [
    'draft' => ['مسودة', 'bg-secondary', 'bi-pencil'],
    'pending' => ['معلق', 'bg-warning text-dark', 'bi-clock'],
    'approved' => ['معتمد', 'bg-info', 'bi-check-circle'],
    'shipped' => ['مرسل', 'bg-primary', 'bi-truck'],
    'partial_received' => ['مستلم جزئي', 'bg-warning', 'bi-box-seam'],
    'received' => ['مستلم', 'bg-success', 'bi-check-all'],
    'rejected' => ['مرفوض', 'bg-danger', 'bi-x-circle'],
    'cancelled' => ['ملغي', 'bg-dark', 'bi-x-octagon']
];
$status_info = $status_labels[$transfer['status']] ?? ['غير معروف', 'bg-secondary', 'bi-question'];

$page_title = 'عرض التحويل - ' . $transfer['transfer_code'];
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; --info: #0dcaf0; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .timeline { position: relative; padding-right: 30px; }
        .timeline::before { content: ''; position: absolute; right: 10px; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item::before { content: ''; position: absolute; right: -24px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); }
        .timeline-item.completed::before { background: var(--success); }
        .timeline-item.pending::before { background: var(--warning); }
        .store-box { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; }
        .store-box i { font-size: 28px; color: var(--primary); margin-bottom: 8px; }
        .arrow-box { display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--primary); }
        .workflow-step { text-align: center; padding: 10px; border-radius: 8px; margin: 5px; }
        .workflow-step.active { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; }
        .workflow-step.completed { background: #d4edda; color: #155724; }
        .workflow-step.pending { background: #f8f9fa; color: #6c757d; }
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
                    <h2><i class="bi bi-eye"></i> <?= $page_title ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">التحويلات</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($transfer['transfer_code']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> رجوع</a>
                </div>
            </div>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">تم إنشاء التحويل بنجاح!</div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">تم تحديث حالة التحويل بنجاح!</div>
            <?php endif; ?>
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- Transfer Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h4><span class="badge bg-light text-dark border"><?= htmlspecialchars($transfer['transfer_code']) ?></span></h4>
                            <span class="badge <?= $status_info[1] ?> fs-6">
                                <i class="bi <?= $status_info[2] ?>"></i> <?= $status_info[0] ?>
                            </span>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-5">
                                    <div class="store-box">
                                        <i class="bi bi-box-seam"></i>
                                        <div class="fw-bold"><?= htmlspecialchars($transfer['from_store_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($transfer['from_store_code']) ?></small>
                                        <?php if ($transfer['from_branch_name']): ?>
                                            <div><span class="badge bg-light text-dark"><?= htmlspecialchars($transfer['from_branch_name']) ?></span></div>
                                        <?php endif; ?>
                                        <div class="mt-2"><span class="badge bg-primary">المرسل</span></div>
                                    </div>
                                </div>
                                <div class="col-2 arrow-box">
                                    <i class="bi bi-arrow-left"></i>
                                </div>
                                <div class="col-5">
                                    <div class="store-box">
                                        <i class="bi bi-box"></i>
                                        <div class="fw-bold"><?= htmlspecialchars($transfer['to_store_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($transfer['to_store_code']) ?></small>
                                        <?php if ($transfer['to_branch_name']): ?>
                                            <div><span class="badge bg-light text-dark"><?= htmlspecialchars($transfer['to_branch_name']) ?></span></div>
                                        <?php endif; ?>
                                        <div class="mt-2"><span class="badge bg-success">المستلم</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workflow Steps -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3"><i class="bi bi-diagram-3"></i> سير العمل</h5>
                    <div class="row">
                        <div class="col">
                            <div class="workflow-step <?= in_array($transfer['status'], ['draft', 'approved', 'shipped', 'partial_received', 'received']) ? 'completed' : 'pending' ?>">
                                <i class="bi bi-pencil fs-4"></i>
                                <div class="fw-bold">إنشاء</div>
                                <small><?= htmlspecialchars($transfer['requested_by_name'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="workflow-step <?= in_array($transfer['status'], ['approved', 'shipped', 'partial_received', 'received']) ? 'completed' : 'pending' ?>">
                                <i class="bi bi-check-circle fs-4"></i>
                                <div class="fw-bold">اعتماد</div>
                                <small><?= htmlspecialchars($transfer['approved_by_name'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="workflow-step <?= in_array($transfer['status'], ['shipped', 'partial_received', 'received']) ? 'completed' : 'pending' ?>">
                                <i class="bi bi-truck fs-4"></i>
                                <div class="fw-bold">شحن</div>
                                <small><?= htmlspecialchars($transfer['shipped_by_name'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="workflow-step <?= in_array($transfer['status'], ['received']) ? 'completed' : ($transfer['status'] === 'partial_received' ? 'active' : 'pending') ?>">
                                <i class="bi bi-check-all fs-4"></i>
                                <div class="fw-bold">استلام</div>
                                <small><?= htmlspecialchars($transfer['received_by_name'] ?? '') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3"><i class="bi bi-gear"></i> الإجراءات المتاحة</h5>
                    <form method="POST" action="" class="d-flex gap-2 flex-wrap">
                        <?php if ($transfer['status'] === 'draft'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-info btn-lg">
                                <i class="bi bi-check-circle"></i> اعتماد التحويل
                            </button>
                            <button type="submit" name="action" value="cancel" class="btn btn-dark btn-lg" onclick="return confirm('هل أنت متأكد من إلغاء التحويل؟')">
                                <i class="bi bi-x-octagon"></i> إلغاء
                            </button>
                        <?php endif; ?>

                        <?php if ($transfer['status'] === 'approved'): ?>
                            <button type="submit" name="action" value="ship" class="btn btn-primary btn-lg">
                                <i class="bi bi-truck"></i> تأكيد الشحن
                            </button>
                        <?php endif; ?>

                        <?php if ($transfer['status'] === 'shipped' || $transfer['status'] === 'partial_received'): ?>
                            <button type="submit" name="action" value="receive" class="btn btn-success btn-lg">
                                <i class="bi bi-check-all"></i> تأكيد الاستلام
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg" onclick="return confirm('هل أنت متأكد من رفض التحويل؟ سيتم إرجاع البضاعة للمرسل.')">
                                <i class="bi bi-x-circle"></i> رفض الاستلام
                            </button>
                        <?php endif; ?>

                        <?php if ($transfer['status'] === 'received'): ?>
                            <div class="alert alert-success w-100 mb-0">
                                <i class="bi bi-check-circle-fill"></i> تم استلام التحويل بنجاح!
                            </div>
                        <?php endif; ?>

                        <?php if ($transfer['status'] === 'rejected'): ?>
                            <div class="alert alert-danger w-100 mb-0">
                                <i class="bi bi-x-circle-fill"></i> تم رفض التحويل - البضاعة تم إرجاعها للمرسل
                            </div>
                        <?php endif; ?>

                        <?php if ($transfer['status'] === 'cancelled'): ?>
                            <div class="alert alert-secondary w-100 mb-0">
                                <i class="bi bi-x-octagon-fill"></i> تم إلغاء التحويل
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> الأصناف</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>الصنف</th>
                                    <th>الكمية المطلوبة</th>
                                    <th>الكمية المرسلة</th>
                                    <th>الكمية المستلمة</th>
                                    <th>الوحدة</th>
                                    <th>التكلفة</th>
                                    <th>الحالة</th>
                                    <?php if ($transfer['status'] === 'shipped' || $transfer['status'] === 'partial_received'): ?>
                                    <th>استلام</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($item['product_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? 'N/A') ?></small>
                                    </td>
                                    <td><?= number_format($item['requested_qty'], 3) ?></td>
                                    <td><?= number_format($item['shipped_qty'], 3) ?></td>
                                    <td><?= number_format($item['received_qty'], 3) ?></td>
                                    <td><?= htmlspecialchars($item['unit_name_ar'] ?? 'وحدة') ?></td>
                                    <td><?= number_format($item['unit_cost'], 2) ?> ج</td>
                                    <td>
                                        <?php if ($item['status'] === 'received'): ?>
                                            <span class="badge bg-success">مستلم</span>
                                        <?php elseif ($item['status'] === 'shipped'): ?>
                                            <span class="badge bg-primary">مرسل</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">معلق</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($transfer['status'] === 'shipped' || $transfer['status'] === 'partial_received'): ?>
                                    <td>
                                        <input type="number" name="received[<?= $item['id'] ?>]" class="form-control form-control-sm" 
                                               step="0.001" min="0" max="<?= $item['requested_qty'] ?>" 
                                               value="<?= $item['requested_qty'] ?>"
                                               placeholder="<?= $item['requested_qty'] ?>" style="width: 100px;">
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> تفاصيل التحويل</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="timeline">
                                <div class="timeline-item <?= $transfer['requested_at'] ? 'completed' : 'pending' ?>">
                                    <div class="fw-bold">إنشاء التحويل</div>
                                    <div class="text-muted">بواسطة: <?= htmlspecialchars($transfer['requested_by_name'] ?? 'غير معروف') ?></div>
                                    <div class="text-muted small"><?= $transfer['requested_at'] ? date('Y-m-d H:i', strtotime($transfer['requested_at'])) : '-' ?></div>
                                </div>
                                <div class="timeline-item <?= $transfer['approved_at'] ? 'completed' : 'pending' ?>">
                                    <div class="fw-bold">اعتماد التحويل</div>
                                    <div class="text-muted">بواسطة: <?= htmlspecialchars($transfer['approved_by_name'] ?? 'غير معروف') ?></div>
                                    <div class="text-muted small"><?= $transfer['approved_at'] ? date('Y-m-d H:i', strtotime($transfer['approved_at'])) : '-' ?></div>
                                </div>
                                <div class="timeline-item <?= $transfer['shipped_at'] ? 'completed' : 'pending' ?>">
                                    <div class="fw-bold">الشحن</div>
                                    <div class="text-muted">بواسطة: <?= htmlspecialchars($transfer['shipped_by_name'] ?? 'غير معروف') ?></div>
                                    <div class="text-muted small"><?= $transfer['shipped_at'] ? date('Y-m-d H:i', strtotime($transfer['shipped_at'])) : '-' ?></div>
                                </div>
                                <div class="timeline-item <?= $transfer['received_at'] ? 'completed' : 'pending' ?>">
                                    <div class="fw-bold">الاستلام</div>
                                    <div class="text-muted">بواسطة: <?= htmlspecialchars($transfer['received_by_name'] ?? 'غير معروف') ?></div>
                                    <div class="text-muted small"><?= $transfer['received_at'] ? date('Y-m-d H:i', strtotime($transfer['received_at'])) : '-' ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> تعليمات سير العمل:</h6>
                                <ol class="mb-0">
                                    <li><strong>المرسل</strong> ينشئ التحويل ويختار الأصناف</li>
                                    <li><strong>المرسل</strong> يعتمد التحويل (تأكيد إنه هيشحن)</li>
                                    <li><strong>المرسل</strong> يشحن البضاعة فعلياً</li>
                                    <li><strong>المستلم</strong> يستلم البضاعة ويدخل الكميات المستلمة</li>
                                    <li>لو فيه نقصان، يكتب الكمية الفعلية المستلمة</li>
                                    <li>لو رفض، البضاعة ترجع للمرسل</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
