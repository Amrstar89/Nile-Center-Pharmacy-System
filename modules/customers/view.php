<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$customer_id = intval($_GET['id'] ?? 0);
if ($customer_id === 0) {
    redirect('index.php');
}

// Get customer data with joins
$stmt = $db->prepare("
    SELECT c.*, b.branch_name, cc.class_name_ar, cc.class_type, cc.local_margin, cc.imported_margin, cc.local_discount, cc.imported_discount
    FROM customers c
    LEFT JOIN branches b ON c.branch_id = b.id
    LEFT JOIN customer_classes cc ON c.customer_class_id = cc.id
    WHERE c.id = ?
");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    redirect('index.php');
}

$page_title = 'بيانات العميل: ' . $customer['customer_name'];

// Get related data
$phones = $db->prepare("SELECT * FROM customer_phones WHERE customer_id = ? ORDER BY is_primary DESC");
$phones->execute([$customer_id]);
$phones = $phones->fetchAll();

$addresses = $db->prepare("
    SELECT ca.*, g.governorate_name_ar, a.area_name_ar, dz.zone_name_ar, dz.delivery_fee
    FROM customer_addresses ca
    LEFT JOIN governorates g ON ca.governorate_id = g.id
    LEFT JOIN areas a ON ca.area_id = a.id
    LEFT JOIN delivery_zones dz ON ca.delivery_zone_id = dz.id
    WHERE ca.customer_id = ? ORDER BY ca.is_primary DESC
");
$addresses->execute([$customer_id]);
$addresses = $addresses->fetchAll();

$employees = $db->prepare("SELECT * FROM company_employees WHERE customer_id = ? AND is_active = 1");
$employees->execute([$customer_id]);
$employees = $employees->fetchAll();

// Get order history
$orders = $db->prepare("
    SELECT o.*, os.status_name, os.status_color
    FROM orders o
    LEFT JOIN order_statuses os ON o.status_id = os.id
    WHERE o.customer_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
");
$orders->execute([$customer_id]);
$orders = $orders->fetchAll();

// Get total orders count and amounts
$stats = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(final_total) as total_amount
    FROM orders
    WHERE customer_id = ?
");
$stats->execute([$customer_id]);
$stats = $stats->fetch();

require_once __DIR__ . '/../../includes/sidebar.php';
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
        .profile-header { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 20px; }
        .profile-avatar { width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; }
        .info-item { padding: 12px 0; border-bottom: 1px solid #eee; }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: #666; font-size: 13px; }
        .info-value { font-weight: 600; color: #333; }
        .badge-custom { padding: 6px 12px; border-radius: 20px; font-size: 12px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: 700; color: var(--primary); }
        .stat-label { color: #666; font-size: 13px; }
        .timeline-item { padding: 15px 0; border-left: 3px solid var(--primary); padding-left: 20px; margin-left: 10px; position: relative; }
        .timeline-item::before { content: ''; width: 12px; height: 12px; background: var(--primary); border-radius: 50%; position: absolute; left: -7px; top: 20px; }
        .phone-badge { background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 15px; font-size: 13px; margin-left: 5px; display: inline-block; }
        .address-card { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-right: 4px solid var(--primary); }
        .employee-card { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header Actions -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-person"></i> كارت العميل</h2>
                <div>
                    <a href="edit.php?id=<?= $customer_id ?>" class="btn btn-warning me-2">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> العودة
                    </a>
                </div>
            </div>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="d-flex align-items-center">
                    <div class="profile-avatar me-4">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <h3 class="mb-1"><?= htmlspecialchars($customer['customer_name']) ?></h3>
                        <?php if (!empty($customer['customer_name_en'])): ?>
                            <p class="mb-2 opacity-75"><?= htmlspecialchars($customer['customer_name_en']) ?></p>
                        <?php endif; ?>
                        <div>
                            <span class="badge-custom bg-white text-dark me-2">
                                <i class="bi bi-hash"></i> <?= htmlspecialchars($customer['customer_code'] ?? $customer['id']) ?>
                            </span>
                            <span class="badge-custom bg-white <?= $customer['customer_type'] == 'company' ? 'text-purple' : 'text-primary' ?> me-2">
                                <i class="bi bi-<?= $customer['customer_type'] == 'company' ? 'building' : 'person' ?>"></i>
                                <?= $customer['customer_type'] == 'company' ? 'شركة' : 'فرد' ?>
                            </span>
                            <span class="badge-custom bg-white <?= $customer['is_active'] ? 'text-success' : 'text-danger' ?> me-2">
                                <i class="bi bi-circle-fill" style="font-size: 8px;"></i>
                                <?= $customer['is_active'] ? 'نشط' : 'موقوف' ?>
                            </span>
                            <?php if (!empty($customer['class_name_ar'])): ?>
                                <span class="badge-custom bg-white text-warning">
                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($customer['class_name_ar']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="stat-card card">
                        <div class="stat-number"><?= number_format($stats['total_orders'] ?? 0) ?></div>
                        <div class="stat-label"><i class="bi bi-cart"></i> إجمالي الطلبات</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card card">
                        <div class="stat-number"><?= number_format($stats['total_amount'] ?? 0, 2) ?> ج</div>
                        <div class="stat-label"><i class="bi bi-cash-stack"></i> إجمالي المبيعات</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-card card">
                        <div class="stat-number"><?= count($addresses) ?></div>
                        <div class="stat-label"><i class="bi bi-geo-alt"></i> عدد العناوين</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Basic Info -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle text-primary"></i> البيانات الأساسية</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">الكود التسلسلي</div>
                                        <div class="info-value"><?= htmlspecialchars($customer['customer_code'] ?? $customer['id']) ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">نوع العميل</div>
                                        <div class="info-value"><?= $customer['customer_type'] == 'company' ? 'شركة' : 'فرد' ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">طريقة الدفع</div>
                                        <div class="info-value">
                                            <?php if ($customer['payment_type'] == 'credit'): ?>
                                                <span class="badge bg-info">آجل</span>
                                                <?php if ($customer['credit_limit'] > 0): ?>
                                                    <small class="text-muted">(حد: <?= number_format($customer['credit_limit'], 2) ?> ج)</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-success">نقدي</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="info-label">البريد الإلكتروني</div>
                                        <div class="info-value"><?= htmlspecialchars($customer['email'] ?? '—') ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">الفرع</div>
                                        <div class="info-value"><?= htmlspecialchars($customer['branch_name'] ?? '—') ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">تاريخ التسجيل</div>
                                        <div class="info-value"><?= arabicDate($customer['created_at']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($customer['notes'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <div class="info-label mb-1"><i class="bi bi-sticky"></i> ملاحظات</div>
                                    <div><?= nl2br(htmlspecialchars($customer['notes'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Phones -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-telephone text-primary"></i> أرقام الهاتف</h5>
                            <span class="badge bg-primary"><?= count($phones) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($phones)): ?>
                                <?php foreach ($phones as $phone): ?>
                                    <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                                        <div class="me-3">
                                            <i class="bi bi-telephone-fill text-primary" style="font-size: 24px;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center">
                                                <span class="phone-badge">
                                                    <i class="bi bi-<?= $phone['phone_type'] == 'mobile' ? 'phone' : ($phone['phone_type'] == 'home' ? 'house' : 'briefcase') ?>"></i>
                                                    <?= $phone['phone_type'] == 'mobile' ? 'موبايل' : ($phone['phone_type'] == 'home' ? 'منزل' : 'عمل') ?>
                                                </span>
                                                <?php if ($phone['is_primary']): ?>
                                                    <span class="phone-badge bg-success text-white">رئيسي</span>
                                                <?php endif; ?>
                                                <?php if ($phone['is_whatsapp']): ?>
                                                    <span class="phone-badge bg-success text-white"><i class="bi bi-whatsapp"></i> واتساب</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2">
                                                <span class="fs-5 fw-bold"><?= htmlspecialchars($phone['country_code'] . ' ' . $phone['phone_number']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-telephone" style="font-size: 32px;"></i>
                                    <p class="mt-2">لا توجد أرقام هاتف مسجلة</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-geo-alt text-primary"></i> العناوين</h5>
                            <span class="badge bg-primary"><?= count($addresses) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($addresses)): ?>
                                <?php foreach ($addresses as $addr): ?>
                                    <div class="address-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="badge bg-primary me-2"><?= $addr['address_type'] == 'home' ? 'منزل' : ($addr['address_type'] == 'work' ? 'عمل' : 'آخر') ?></span>
                                                <?php if ($addr['is_primary']): ?>
                                                    <span class="badge bg-success">العنوان الرئيسي</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-8">
                                                <p class="mb-1">
                                                    <i class="bi bi-house-door text-muted"></i>
                                                    <?= htmlspecialchars($addr['building_number'] ?? '') ?>
                                                    <?= $addr['floor_number'] ? '، الدور ' . htmlspecialchars($addr['floor_number']) : '' ?>
                                                    <?= $addr['apartment_number'] ? '، شقة ' . htmlspecialchars($addr['apartment_number']) : '' ?>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="bi bi-signpost text-muted"></i>
                                                    <?= htmlspecialchars($addr['street_name'] ?? '—') ?>
                                                </p>
                                                <?php if ($addr['landmark']): ?>
                                                    <p class="mb-1 text-muted">
                                                        <i class="bi bi-geo"></i> <?= htmlspecialchars($addr['landmark']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="mb-0">
                                                    <i class="bi bi-map text-muted"></i>
                                                    <?= htmlspecialchars($addr['governorate_name_ar'] ?? '—') ?>
                                                    <?= $addr['area_name_ar'] ? ' - ' . htmlspecialchars($addr['area_name_ar']) : '' ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-md-end">
                                                <?php if ($addr['zone_name_ar']): ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-truck"></i> <?= htmlspecialchars($addr['zone_name_ar']) ?>
                                                    </span>
                                                    <div class="mt-1 text-muted">
                                                        رسوم التوصيل: <?= number_format($addr['delivery_fee'] ?? 0, 2) ?> ج
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-geo-alt" style="font-size: 32px;"></i>
                                    <p class="mt-2">لا توجد عناوين مسجلة</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Company Employees -->
                    <?php if ($customer['customer_type'] == 'company'): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-people text-primary"></i> موظفين الشركة</h5>
                            <span class="badge bg-primary"><?= count($employees) ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($employees)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>الاسم</th>
                                                <th>الرقم القومي</th>
                                                <th>الهاتف</th>
                                                <th>المسمى الوظيفي</th>
                                                <th>القسم</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($employees as $emp): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($emp['employee_name']) ?></strong>
                                                        <?php if ($emp['employee_name_en']): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($emp['employee_name_en']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($emp['national_id'] ?? '—') ?></td>
                                                    <td><?= htmlspecialchars($emp['phone'] ?? '—') ?></td>
                                                    <td><?= htmlspecialchars($emp['job_title'] ?? '—') ?></td>
                                                    <td><?= htmlspecialchars($emp['department'] ?? '—') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-people" style="font-size: 32px;"></i>
                                    <p class="mt-2">لا يوجد موظفين مسجلين</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Customer Class Info -->
                    <?php if (!empty($customer['class_name_ar'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-tag text-warning"></i> معلومات التصنيف</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-label">التصنيف</div>
                                <div class="info-value"><?= htmlspecialchars($customer['class_name_ar']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">نوع التصنيف</div>
                                <div class="info-value">
                                    <?php if ($customer['class_type'] == 'wholesale'): ?>جملة
                                    <?php elseif ($customer['class_type'] == 'retail'): ?>تجزئة
                                    <?php else: ?>تكلفة<?php endif; ?>
                                </div>
                            </div>
                            <?php if ($customer['class_type'] == 'wholesale'): ?>
                                <div class="info-item">
                                    <div class="info-label">هامش الربح (محلي)</div>
                                    <div class="info-value"><?= number_format($customer['local_margin'] ?? 0, 2) ?>%</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">هامش الربح (مستورد)</div>
                                    <div class="info-value"><?= number_format($customer['imported_margin'] ?? 0, 2) ?>%</div>
                                </div>
                            <?php elseif ($customer['class_type'] == 'retail'): ?>
                                <div class="info-item">
                                    <div class="info-label">خصم (محلي)</div>
                                    <div class="info-value"><?= number_format($customer['local_discount'] ?? 0, 2) ?>%</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">خصم (مستورد)</div>
                                    <div class="info-value"><?= number_format($customer['imported_discount'] ?? 0, 2) ?>%</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Orders -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clock-history text-info"></i> آخر الطلبات</h5>
                            <a href="../orders/index.php?customer_id=<?= $customer_id ?>" class="btn btn-sm btn-outline-primary">الكل</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $order): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between">
                                            <strong>طلب #<?= htmlspecialchars($order['order_number']) ?></strong>
                                            <span class="badge" style="background-color: <?= $order['status_color'] ?>;"><?= htmlspecialchars($order['status_name']) ?></span>
                                        </div>
                                        <div class="text-muted mt-1">
                                            <small><?= arabicDate($order['order_date']) ?> | <?= number_format($order['final_total'] ?? 0, 2) ?> ج</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-cart-x" style="font-size: 32px;"></i>
                                    <p class="mt-2">لا توجد طلبات سابقة</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <a href="edit.php?id=<?= $customer_id ?>" class="btn btn-warning w-100 mb-2">
                                <i class="bi bi-pencil"></i> تعديل بيانات العميل
                            </a>
                            <a href="delete.php?id=<?= $customer_id ?>" class="btn btn-outline-danger w-100" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟ سيتم حذف جميع بياناته!')">
                                <i class="bi bi-trash"></i> حذف العميل
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>