<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

// Handle status change via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    header('Content-Type: application/json');
    $order_id = $_POST['order_id'] ?? 0;
    $new_status_id = $_POST['status_id'] ?? 0;
    
    try {
        $stmt = $db->prepare("SELECT status_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $old_status = $stmt->fetch()['status_id'] ?? null;
        
        $stmt = $db->prepare("UPDATE orders SET status_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status_id, $_SESSION['user_id'], $order_id]);
        
        logActivity('update_status', 'orders', $order_id, ['status_id' => $old_status], ['status_id' => $new_status_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle delete
if (isset($_GET['delete']) && isAdmin()) {
    $id = (int)$_GET['delete'];
    try {
        $db->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
        logActivity('delete', 'orders', $id);
        $_SESSION['success'] = 'تم حذف الطلب بنجاح';
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ في الحذف: ' . $e->getMessage();
    }
    redirect(APP_URL . '/modules/customer-requests/orders.php');
}

// Filters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT o.*, os.status_name, os.status_color, os.is_final, u.full_name as creator_name, updater.full_name as updater_name 
        FROM orders o 
        JOIN order_statuses os ON o.status_id = os.id 
        JOIN users u ON o.created_by = u.id 
        LEFT JOIN users updater ON o.updated_by = updater.id 
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND o.status_id = ?";
    $params[] = $status_filter;
}
if ($priority_filter) {
    $sql .= " AND o.priority = ?";
    $params[] = $priority_filter;
}
if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($date_from) {
    $sql .= " AND o.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $sql .= " AND o.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get all statuses for filter and dropdown
$stmt = $db->query("SELECT * FROM order_statuses WHERE is_active = 1 ORDER BY sort_order");
$all_statuses = $stmt->fetchAll();

// Get order items for ALL orders (for inline display)
$orderItemsMap = [];
$stmt = $db->query("SELECT oi.*, o.order_number FROM order_items oi JOIN orders o ON oi.order_id = o.id");
$allItems = $stmt->fetchAll();
foreach ($allItems as $item) {
    $orderItemsMap[$item['order_id']][] = $item;
}

// Get order details for modal
$order_view = null;
$order_items = [];
if (isset($_GET['view'])) {
    $order_id = (int)$_GET['view'];
    $stmt = $db->prepare("SELECT o.*, os.status_name, os.status_color, u.full_name as creator_name, b.branch_name 
                        FROM orders o 
                        JOIN order_statuses os ON o.status_id = os.id 
                        JOIN users u ON o.created_by = u.id 
                        LEFT JOIN branches b ON o.branch_code = b.branch_code
                        WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order_view = $stmt->fetch();
    
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
}

$page_title = 'متابعة الطلبات';
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
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h4 { color: white; margin: 0; font-weight: 700; }
        .nav-menu { padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .nav-link i { width: 25px; margin-left: 10px; font-size: 18px; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; }
        .priority-badge { padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .priority-normal { background: #e9ecef; color: #495057; }
        .priority-urgent { background: #fff3cd; color: #856404; }
        .priority-critical { background: #f8d7da; color: #721c24; }
        .btn-action { padding: 5px 10px; border-radius: 8px; font-size: 12px; }
        .filter-card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table th { background: #f8f9fa; font-weight: 600; }
        .order-row:hover { background: #f8f9fa; }
        .status-select { border-radius: 20px; padding: 4px 12px; font-size: 12px; border: 2px solid; color: white; cursor: pointer; }
        .item-inline { background: #f8f9fa; border-radius: 6px; padding: 6px 10px; margin: 2px 0; font-size: 12px; display: flex; justify-content: space-between; }
        .item-inline.manual { border-right: 3px solid #ffc107; }
        .item-inline.purchase-needed { border-right: 3px solid #dc3545; }
        .item-detail { background: #f8f9fa; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
        .item-detail.manual { border-right: 3px solid #ffc107; }
        .item-detail.purchase-needed { border-right: 3px solid #dc3545; }
        .modal-lg { max-width: 900px; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">متابعة وإدارة جميع طلبات العملاء</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" value="<?= $search ?>" placeholder="بحث (رقم/عميل/كود)">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">كل الحالات</option>
                        <?php foreach ($all_statuses as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $status_filter == $s['id'] ? 'selected' : '' ?>><?= $s['status_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="priority">
                        <option value="">كل الأولويات</option>
                        <option value="normal" <?= $priority_filter == 'normal' ? 'selected' : '' ?>>عادي</option>
                        <option value="urgent" <?= $priority_filter == 'urgent' ? 'selected' : '' ?>>عاجل</option>
                        <option value="critical" <?= $priority_filter == 'critical' ? 'selected' : '' ?>>حرج</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>" placeholder="من">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>" placeholder="إلى">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="bi bi-list-task me-2"></i>قائمة الطلبات (<?= count($orders) ?>)</h5>
                <a href="new-order.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>طلب جديد</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>الأصناف</th>
                            <th>الإجمالي</th>
                            <th>الحالة</th>
                            <th>الأولوية</th>
                            <th>التاريخ</th>
                            <th>بواسطة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $items = $orderItemsMap[$order['id']] ?? [];
                        ?>
                        <tr class="order-row">
                            <td class="fw-bold"><?= $order['order_number'] ?></td>
                            <td>
                                <?= $order['customer_name'] ?>
                                <?php if ($order['customer_phone']): ?>
                                    <br><small class="text-muted"><i class="bi bi-telephone"></i> <?= $order['customer_phone'] ?></small>
                                <?php endif; ?>
                                <?php if ($order['customer_phone2']): ?>
                                    <br><small class="text-muted"><i class="bi bi-telephone-plus"></i> <?= $order['customer_phone2'] ?></small>
                                <?php endif; ?>
                                <?php if ($order['customer_address']): ?>
                                    <br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= $order['customer_address'] ?></small>
                                <?php endif; ?>
                                <?php if ($order['customer_code']): ?>
                                    <br><small class="text-muted">كود: <?= $order['customer_code'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (count($items) > 0): ?>
                                    <?php foreach ($items as $item): ?>
                                    <div class="item-inline <?= $item['is_manual'] ? 'manual' : '' ?> <?= $item['needs_purchase'] ? 'purchase-needed' : '' ?>">
                                        <span><?= $item['product_name'] ?></span>
                                        <span>
                                            <?= $item['quantity'] ?> × <?= number_format($item['unit_price'], 2) ?> ج
                                            <?php if ($item['discount_value'] > 0): ?>
                                                <span class="text-success">(خصم <?= $item['discount_value'] ?><?= $item['discount_type'] === 'percentage' ? '%' : ' ج' ?>)</span>
                                            <?php endif; ?>
                                            = <strong><?= number_format($item['final_price'], 2) ?> ج</strong>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?= $order['total_items'] ?> صنف
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['final_total'] > 0): ?>
                                    <strong><?= number_format($order['final_total'], 2) ?> ج</strong>
                                    <?php if ($order['total_discount_value'] > 0): ?>
                                        <br><small class="text-success">خصم إجمالي: <?= $order['total_discount_value'] ?><?= $order['total_discount_type'] === 'percentage' ? '%' : ' ج' ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($order['is_final']): ?>
                                    <span class="status-badge" style="background: <?= $order['status_color'] ?>">
                                        <?= $order['status_name'] ?>
                                    </span>
                                <?php else: ?>
                                    <select class="status-select" style="background: <?= $order['status_color'] ?>; border-color: <?= $order['status_color'] ?>" 
                                            onchange="changeStatus(<?= $order['id'] ?>, this.value)">
                                        <?php foreach ($all_statuses as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $order['status_id'] == $s['id'] ? 'selected' : '' ?> 
                                                style="background: <?= $s['status_color'] ?>; color: white">
                                            <?= $s['status_name'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="priority-badge priority-<?= $order['priority'] ?>">
                                    <?= $order['priority'] === 'normal' ? 'عادي' : ($order['priority'] === 'urgent' ? 'عاجل' : 'حرج') ?>
                                </span>
                            </td>
                           <td><?= arabicDate($order['order_date']) ?><br><small class="text-muted"><?= date('h:i A', strtotime($order['order_date'])) ?></small></td>
                            <td>
                                <?= $order['creator_name'] ?>
                                <?php if ($order['updater_name']): ?>
                                    <br><small class="text-muted">آخر تعديل: <?= $order['updater_name'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?view=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit-order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-warning btn-action">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (isAdmin()): ?>
                                <a href="?delete=<?= $order['id'] ?>" class="btn btn-sm btn-outline-danger btn-action" onclick="return confirm('هل أنت متأكد من الحذف؟')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <?php if ($order_view): ?>
    <div class="modal fade show" id="orderModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل الطلب <?= $order_view['order_number'] ?></h5>
                    <a href="orders.php" class="btn-close"></a>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>العميل:</strong> <?= $order_view['customer_name'] ?>
                            <?php if ($order_view['customer_phone']): ?>
                                <br><small class="text-muted"><i class="bi bi-telephone"></i> <?= $order_view['customer_phone'] ?></small>
                            <?php endif; ?>
                            <?php if ($order_view['customer_phone2']): ?>
                                <br><small class="text-muted"><i class="bi bi-telephone-plus"></i> <?= $order_view['customer_phone2'] ?></small>
                            <?php endif; ?>
                            <?php if ($order_view['customer_address']): ?>
                                <br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= $order_view['customer_address'] ?></small>
                            <?php endif; ?>
                            <?php if ($order_view['customer_code']): ?>
                                <br><small class="text-muted">كود: <?= $order_view['customer_code'] ?></small>
                            <?php endif; ?>
                            <?php if ($order_view['branch_name']): ?>
                                <br><small class="text-muted"><i class="bi bi-building"></i> <?= $order_view['branch_name'] ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>الحالة:</strong> 
                            <span class="status-badge" style="background: <?= $order_view['status_color'] ?>">
                                <?= $order_view['status_name'] ?>
                            </span>
                            <br>
                            <strong>الأولوية:</strong> 
                            <span class="priority-badge priority-<?= $order_view['priority'] ?>">
                                <?= $order_view['priority'] === 'normal' ? 'عادي' : ($order_view['priority'] === 'urgent' ? 'عاجل' : 'حرج') ?>
                            </span>
                            <br>
                            <strong>تاريخ الطلب:</strong> <?= arabicDate($order_view['order_date']) ?> <small class="text-muted"><?= date('h:i A', strtotime($order_view['order_date'])) ?></small>
                        </div>
                    </div>
                    <?php if ($order_view['final_total'] > 0): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>الإجمالي قبل الخصم:</strong> 
                            <?php 
                                $subTotal = 0;
                                foreach ($order_items as $item) {
                                    $subTotal += ($item['quantity'] * $item['unit_price']);
                                }
                                echo number_format($subTotal, 2);
                            ?> ج
                        </div>
                        <?php if ($order_view['total_discount_value'] > 0): ?>
                        <div class="col-md-6">
                            <strong>الخصم على الإجمالي:</strong> <?= $order_view['total_discount_value'] ?><?= $order_view['total_discount_type'] === 'percentage' ? '%' : ' ج' ?>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <strong>الإجمالي النهائي:</strong> <span class="text-success fw-bold"><?= number_format($order_view['final_total'], 2) ?> ج</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order_view['notes']): ?>
                    <div class="mb-3">
                        <strong>ملاحظات:</strong>
                        <p class="text-muted"><?= nl2br($order_view['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                    <h6 class="mt-4 mb-3">الأصناف المطلوبة (<?= count($order_items) ?>):</h6>
                    <?php foreach ($order_items as $item): ?>
                    <div class="item-detail <?= $item['is_manual'] ? 'manual' : '' ?> <?= $item['needs_purchase'] ? 'purchase-needed' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= $item['product_name'] ?></strong>
                                <?php if ($item['is_manual']): ?>
                                    <span class="badge bg-warning">يدوي</span>
                                <?php endif; ?>
                                <?php if ($item['needs_purchase']): ?>
                                    <span class="badge bg-danger">يحتاج شراء</span>
                                <?php endif; ?>
                                <?php if ($item['product_code']): ?>
                                    <br><small class="text-muted">كود: <?= $item['product_code'] ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <div><?= $item['quantity'] ?> × <?= number_format($item['unit_price'], 2) ?> ج</div>
                                <?php if ($item['discount_value'] > 0): ?>
                                    <small class="text-success">خصم: <?= $item['discount_value'] ?><?= $item['discount_type'] === 'percentage' ? '%' : ' ج' ?></small>
                                <?php endif; ?>
                                <?php if ($item['final_price'] > 0): ?>
                                    <div class="fw-bold text-primary"><?= number_format($item['final_price'], 2) ?> ج</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($item['notes']): ?>
                        <small class="text-muted d-block mt-1"><?= $item['notes'] ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <a href="edit-order.php?id=<?= $order_view['id'] ?>" class="btn btn-warning">
                        <i class="bi bi-pencil me-1"></i> تعديل الطلب
                    </a>
                    <a href="orders.php" class="btn btn-secondary">إغلاق</a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function changeStatus(orderId, statusId) {
            const select = event.target;
            const originalValue = select.value;
            
            $.ajax({
                url: 'orders.php',
                method: 'POST',
                data: { action: 'change_status', order_id: orderId, status_id: statusId },
                success: function(response) {
                    if (response.success) {
                        const selectedOption = select.options[select.selectedIndex];
                        select.style.background = selectedOption.style.background;
                        select.style.borderColor = selectedOption.style.background;
                        alert('تم تحديث الحالة بنجاح');
                    } else {
                        alert('خطأ: ' + response.message);
                        select.value = originalValue;
                    }
                },
                error: function() {
                    alert('حدث خطأ في الاتصال');
                    select.value = originalValue;
                }
            });
        }
    </script>
</body>
</html>