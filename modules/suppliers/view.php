<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$supplier_id = intval($_GET['id'] ?? 0);
if ($supplier_id <= 0) {
    header("Location: index.php");
    exit;
}

// Get supplier with balance
$stmt = $db->prepare("
    SELECT s.*, sb.balance, sb.total_purchases, sb.total_payments, sb.total_returns
    FROM suppliers s
    LEFT JOIN supplier_balances sb ON s.id = sb.supplier_id
    WHERE s.id = ? AND (s.deleted_at IS NULL OR s.deleted_at = '')
");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();
if (!$supplier) {
    header("Location: index.php");
    exit;
}

// Get related data
$phones = $db->query("SELECT * FROM supplier_phones WHERE supplier_id = {$supplier_id} ORDER BY is_primary DESC, id ASC")->fetchAll();
$addresses = $db->query("SELECT sa.*, a.area_name_ar, g.governorate_name_ar, z.zone_name_ar 
    FROM supplier_addresses sa 
    LEFT JOIN areas a ON sa.area_id = a.id 
    LEFT JOIN governorates g ON sa.governorate_id = g.id 
    LEFT JOIN delivery_zones z ON sa.delivery_zone_id = z.id 
    WHERE sa.supplier_id = {$supplier_id} ORDER BY sa.is_primary DESC, sa.id ASC")->fetchAll();
$contacts = $db->query("SELECT * FROM supplier_contacts WHERE supplier_id = {$supplier_id} AND is_active = 1 ORDER BY is_primary DESC, id ASC")->fetchAll();
$bank_accounts = $db->query("SELECT * FROM supplier_bank_accounts WHERE supplier_id = {$supplier_id} AND is_active = 1 ORDER BY is_primary DESC, id ASC")->fetchAll();

// NEW: Purchase integration data
$invStats = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total FROM purchase_invoices WHERE supplier_id = ?");
$invStats->execute([$supplier_id]); $invStat = $invStats->fetch();

$retStats = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total FROM purchase_returns WHERE supplier_id = ?");
$retStats->execute([$supplier_id]); $retStat = $retStats->fetch();

$payStats = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM supplier_payments WHERE supplier_id = ?");
$payStats->execute([$supplier_id]); $payStat = $payStats->fetch();

$transCount = $db->prepare("SELECT COUNT(*) FROM supplier_transactions WHERE supplier_id = ?");
$transCount->execute([$supplier_id]); $transCnt = $transCount->fetchColumn();

// Get lists
$invoices = $db->prepare("SELECT * FROM purchase_invoices WHERE supplier_id = ? ORDER BY created_at DESC LIMIT 50");
$invoices->execute([$supplier_id]); $invList = $invoices->fetchAll();

$returns = $db->prepare("SELECT * FROM purchase_returns WHERE supplier_id = ? ORDER BY created_at DESC LIMIT 50");
$returns->execute([$supplier_id]); $retList = $returns->fetchAll();

$payments = $db->prepare("SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY created_at DESC LIMIT 50");
$payments->execute([$supplier_id]); $payList = $payments->fetchAll();

$transactions = $db->prepare("SELECT * FROM supplier_transactions WHERE supplier_id = ? ORDER BY id DESC LIMIT 100");
$transactions->execute([$supplier_id]); $transList = $transactions->fetchAll();

$type_labels = [
    'b2b' => ['صيدلية', 'bi-shop', 'info'],
    'private_office' => ['مكتب خاص', 'bi-briefcase', 'warning'],
    'warehouse' => ['مخزن', 'bi-building', 'secondary'],
    'distributor' => ['موزع', 'bi-truck', 'success'],
    'company' => ['شركة', 'bi-building-fill', 'primary']
];
$type_info = $type_labels[$supplier['supplier_type']] ?? ['شركة', 'bi-building-fill', 'primary'];

$page_title = 'عرض المورد - ' . $supplier['supplier_name'];
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
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .nav-tabs .nav-link { border: none; color: #666; font-weight: 500; padding: 10px 15px; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 2px solid var(--primary); background: transparent; }
        .nav-tabs .nav-link i { margin-left: 5px; }
        .info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 15px; margin-bottom: 10px; }
        .info-label { color: #666; font-size: 12px; margin-bottom: 3px; }
        .info-value { font-weight: 600; font-size: 14px; }
        .balance-card { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 15px; }
        .balance-amount { font-size: 28px; font-weight: bold; }
        .contact-card { border-left: 3px solid var(--primary); padding: 10px 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; }
        .stat-card { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all .2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-card i { font-size: 24px; margin-bottom: 5px; }
        .stat-card .val { font-size: 18px; font-weight: 700; }
        .stat-card .lbl { font-size: 11px; color: #888; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { background: #f8f9fa; padding: 10px; text-align: center; font-weight: 600; border-bottom: 2px solid #dee2e6; }
        .data-table td { padding: 8px 10px; text-align: center; border-bottom: 1px solid #e9ecef; }
        .data-table tr:hover td { background: #f8f9fa; }
        .badge-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-partial { background: #fff3cd; color: #856404; }
        .badge-open { background: #e2e3e5; color: #383d41; }
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
                            <li class="breadcrumb-item"><a href="index.php">الموردين</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($supplier['supplier_name']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="../purchases/invoices/create.php?supplier_id=<?= $supplier_id ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> فاتورة شراء</a>
                    <a href="statement.php?id=<?= $supplier_id ?>" class="btn btn-primary"><i class="bi bi-file-text"></i> كشف حساب</a>
                    <a href="edit.php?id=<?= $supplier_id ?>" class="btn btn-warning"><i class="bi bi-pencil"></i> تعديل</a>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> رجوع</a>
                </div>
            </div>

            <!-- Supplier Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h3><i class="bi bi-truck"></i> <?= htmlspecialchars($supplier['supplier_name']) ?></h3>
                            <?php if (!empty($supplier['supplier_name_en'])): ?>
                                <p class="text-muted mb-2"><?= htmlspecialchars($supplier['supplier_name_en']) ?></p>
                            <?php endif; ?>
                            <div>
                                <span class="badge bg-<?= $type_info[2] ?> me-2"><i class="bi <?= $type_info[1] ?>"></i> <?= $type_info[0] ?></span>
                                <span class="badge bg-light text-dark border">كود: <?= htmlspecialchars($supplier['supplier_code'] ?? $supplier['id']) ?></span>
                                <span class="badge <?= $supplier['is_active'] ? 'bg-success' : 'bg-danger' ?>"><?= $supplier['is_active'] ? 'نشط' : 'موقوف' ?></span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="balance-card p-3 text-center">
                                <div class="mb-1" style="font-size:12px">الرصيد الحالي</div>
                                <div class="balance-amount"><?= number_format(floatval($supplier['balance'] ?? 0), 2) ?> ج</div>
                                <small style="font-size:10px"><?= floatval($supplier['balance'] ?? 0) > 0 ? '(علينا)' : (floatval($supplier['balance'] ?? 0) < 0 ? '(لنا)' : 'متعادل') ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="row g-2">
                                <div class="col-6"><div class="stat-card"><i class="bi bi-receipt text-primary"></i><div class="val text-primary"><?= number_format($invStat['cnt']) ?></div><div class="lbl">فواتير</div></div></div>
                                <div class="col-6"><div class="stat-card"><i class="bi bi-arrow-return-left text-danger"></i><div class="val text-danger"><?= number_format($retStat['cnt']) ?></div><div class="lbl">مرتجعات</div></div></div>
                                <div class="col-6"><div class="stat-card"><i class="bi bi-cash-stack text-success"></i><div class="val text-success"><?= number_format($payStat['cnt']) ?></div><div class="lbl">دفعات</div></div></div>
                                <div class="col-6"><div class="stat-card"><i class="bi bi-calculator text-info"></i><div class="val text-info"><?= number_format($invStat['total'] - $retStat['total'] - $payStat['total'], 2) ?></div><div class="lbl">صافي</div></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" id="viewTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button"><i class="bi bi-info-circle"></i> المعلومات</button></li>
                <li class="nav-item"><button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button"><i class="bi bi-receipt"></i> فواتير الشراء (<?= $invStat['cnt'] ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="returns-tab" data-bs-toggle="tab" data-bs-target="#returns" type="button"><i class="bi bi-arrow-return-left"></i> المرتجعات (<?= $retStat['cnt'] ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button"><i class="bi bi-cash-stack"></i> الدفعات (<?= $payStat['cnt'] ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button"><i class="bi bi-journal-text"></i> حركات الحساب (<?= $transCnt ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="phones-tab" data-bs-toggle="tab" data-bs-target="#phones" type="button"><i class="bi bi-telephone"></i> الهواتف</button></li>
                <li class="nav-item"><button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button"><i class="bi bi-geo-alt"></i> العناوين</button></li>
                <li class="nav-item"><button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button"><i class="bi bi-people"></i> الموظفين</button></li>
                <li class="nav-item"><button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button"><i class="bi bi-bank"></i> البنوك</button></li>
            </ul>

            <div class="tab-content" id="viewTabsContent">
                <!-- Info Tab -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3"><div class="info-card"><div class="info-label">نوع التعامل</div><div class="info-value"><?php if ($supplier['payment_type'] == 'credit'): ?><span class="badge bg-info">آجل</span><?php elseif ($supplier['payment_type'] == 'cheque'): ?><span class="badge bg-warning text-dark">شيك</span><?php else: ?><span class="badge bg-success">نقدي</span><?php endif; ?></div></div></div>
                            <div class="col-md-4 mb-3"><div class="info-card"><div class="info-label">حد التعامل</div><div class="info-value"><?= $supplier['payment_type'] != 'cash' ? number_format($supplier['credit_limit'], 2) . ' ج' : '-' ?></div></div></div>
                            <div class="col-md-4 mb-3"><div class="info-card"><div class="info-label">فترة السماح</div><div class="info-value"><?= $supplier['grace_period'] > 0 ? $supplier['grace_period'] . ' يوم' : '-' ?></div></div></div>
                            <div class="col-md-4 mb-3"><div class="info-card"><div class="info-label">رقم الإنستاباي</div><div class="info-value"><?= htmlspecialchars($supplier['instapay_number'] ?? '-') ?></div></div></div>
                            <div class="col-md-4 mb-3"><div class="info-card"><div class="info-label">رقم المحفظة</div><div class="info-value"><?= htmlspecialchars($supplier['wallet_number'] ?? '-') ?></div></div></div>
                            <div class="col-md-4 mb-3"><div class="info-card"><div class="info-label">تاريخ الإضافة</div><div class="info-value"><?= date('Y-m-d', strtotime($supplier['created_at'])) ?></div></div></div>
                            <div class="col-md-12 mb-3"><div class="info-card"><div class="info-label">سياسة المرتجعات</div><div class="info-value"><?= nl2br(htmlspecialchars($supplier['return_policy'] ?? 'لا توجد')) ?></div></div></div>
                            <div class="col-md-12"><div class="info-card"><div class="info-label">ملاحظات</div><div class="info-value"><?= nl2br(htmlspecialchars($supplier['notes'] ?? 'لا توجد')) ?></div></div></div>
                        </div>
                    </div></div>
                </div>

                <!-- Invoices Tab -->
                <div class="tab-pane fade" id="invoices" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-receipt text-primary"></i> فواتير الشراء</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>#</th><th>رقم الفاتورة</th><th>التاريخ</th><th>المخزن</th><th>الحالة</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
                                <tbody>
                                    <?php foreach ($invList as $i => $inv): 
                                        $remaining = $inv['grand_total'] - $inv['paid_amount'];
                                        $statusClass = $inv['status'] === 'paid' ? 'badge-paid' : ($inv['status'] === 'partial' ? 'badge-partial' : 'badge-open');
                                        $statusText = $inv['status'] === 'paid' ? 'مدفوع' : ($inv['status'] === 'partial' ? 'جزئي' : 'مفتوح');
                                    ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><a href="../purchases/invoices/view.php?id=<?= $inv['id'] ?>" class="fw-bold"><?= $inv['invoice_number'] ?></a></td>
                                        <td><?= $inv['invoice_date'] ?></td>
                                        <td><?= $inv['store_id'] ? 'مخزن #' . $inv['store_id'] : '-' ?></td>
                                        <td><span class="badge-status <?= $statusClass ?>"><?= $statusText ?></span></td>
                                        <td class="fw-bold"><?= number_format($inv['grand_total'], 2) ?></td>
                                        <td class="text-success"><?= number_format($inv['paid_amount'], 2) ?></td>
                                        <td class="text-danger"><?= number_format($remaining, 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($invList)): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد فواتير</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div></div>
                </div>

                <!-- Returns Tab -->
                <div class="tab-pane fade" id="returns" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-arrow-return-left text-danger"></i> مرتجعات المشتريات</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>#</th><th>رقم المرتجع</th><th>التاريخ</th><th>المخزن</th><th>الإجمالي</th><th>ملاحظات</th></tr></thead>
                                <tbody>
                                    <?php foreach ($retList as $i => $ret): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><a href="../purchases/returns/view.php?id=<?= $ret['id'] ?>" class="text-danger fw-bold"><?= $ret['return_number'] ?></a></td>
                                        <td><?= $ret['return_date'] ?></td>
                                        <td><?= $ret['store_id'] ? 'مخزن #' . $ret['store_id'] : '-' ?></td>
                                        <td class="fw-bold text-danger"><?= number_format($ret['grand_total'], 2) ?></td>
                                        <td><?= $ret['notes'] ?: '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($retList)): ?><tr><td colspan="6" class="text-center text-muted py-4">لا توجد مرتجعات</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div></div>
                </div>

                <!-- Payments Tab -->
                <div class="tab-pane fade" id="payments" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-cash-stack text-success"></i> دفعات المورد</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>#</th><th>رقم الدفعة</th><th>التاريخ</th><th>المبلغ</th><th>الطريقة</th><th>ملاحظات</th></tr></thead>
                                <tbody>
                                    <?php foreach ($payList as $i => $pay): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td class="fw-bold"><?= $pay['payment_number'] ?></td>
                                        <td><?= $pay['payment_date'] ?></td>
                                        <td class="fw-bold text-success"><?= number_format($pay['amount'], 2) ?></td>
                                        <td><?= $pay['payment_method'] ?></td>
                                        <td><?= $pay['notes'] ?: '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($payList)): ?><tr><td colspan="6" class="text-center text-muted py-4">لا توجد دفعات</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div></div>
                </div>

                <!-- Transactions Tab -->
                <div class="tab-pane fade" id="transactions" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-journal-text text-info"></i> حركات حساب المورد</h5>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>#</th><th>التاريخ</th><th>النوع</th><th>المرجع</th><th>مدين</th><th>دائن</th><th>الرصيد</th><th>ملاحظات</th></tr></thead>
                                <tbody>
                                    <?php foreach ($transList as $i => $t): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= $t['created_at'] ?></td>
                                        <td><?= $t['transaction_type'] ?></td>
                                        <td><?= $t['reference_number'] ?: '-' ?></td>
                                        <td class="text-danger"><?= $t['debit'] > 0 ? number_format($t['debit'], 2) : '-' ?></td>
                                        <td class="text-success"><?= $t['credit'] > 0 ? number_format($t['credit'], 2) : '-' ?></td>
                                        <td class="fw-bold"><?= number_format($t['balance_after'], 2) ?></td>
                                        <td><?= $t['notes'] ?: '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($transList)): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد حركات</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div></div>
                </div>

                <!-- Phones Tab -->
                <div class="tab-pane fade" id="phones" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-telephone"></i> أرقام الهاتف</h5>
                        <?php if (empty($phones)): ?><div class="text-center text-muted py-4">لا توجد أرقام</div>
                        <?php else: ?><div class="row"><?php foreach ($phones as $p): ?><div class="col-md-4 mb-3"><div class="info-card"><div class="d-flex justify-content-between"><span class="badge bg-light text-dark border"><?= $p['phone_type'] ?></span><?php if ($p['is_primary']): ?><span class="badge bg-primary">رئيسي</span><?php endif; ?></div><div class="info-value mt-2"><?= htmlspecialchars($p['country_code'].' '.$p['phone_number']) ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
                    </div></div>
                </div>

                <!-- Addresses Tab -->
                <div class="tab-pane fade" id="addresses" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-geo-alt"></i> العناوين</h5>
                        <?php if (empty($addresses)): ?><div class="text-center text-muted py-4">لا توجد عناوين</div>
                        <?php else: ?><div class="row"><?php foreach ($addresses as $a): ?><div class="col-md-6 mb-3"><div class="info-card"><div class="d-flex justify-content-between mb-2"><span class="badge bg-light text-dark border"><?= $a['address_type'] ?></span><?php if ($a['is_primary']): ?><span class="badge bg-primary">رئيسي</span><?php endif; ?></div><div class="info-value"><?= htmlspecialchars($a['street_name']??'') ?></div><div class="info-label"><?= htmlspecialchars($a['governorate_name_ar']??'') ?> - <?= htmlspecialchars($a['area_name_ar']??'') ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
                    </div></div>
                </div>

                <!-- Contacts Tab -->
                <div class="tab-pane fade" id="contacts" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-people"></i> الموظفين</h5>
                        <?php if (empty($contacts)): ?><div class="text-center text-muted py-4">لا يوجد موظفين</div>
                        <?php else: ?><?php foreach ($contacts as $c): ?><div class="contact-card"><div class="d-flex justify-content-between"><div><span class="badge bg-secondary me-2"><?= $c['contact_type'] ?></span><?php if ($c['is_primary']): ?><span class="badge bg-primary">رئيسي</span><?php endif; ?><h6 class="mt-2 mb-1"><?= htmlspecialchars($c['contact_name']) ?></h6></div><div class="text-start"><?php if ($c['phone']): ?><div><i class="bi bi-telephone"></i> <?= htmlspecialchars($c['phone']) ?></div><?php endif; ?></div></div></div><?php endforeach; ?><?php endif; ?>
                    </div></div>
                </div>

                <!-- Bank Tab -->
                <div class="tab-pane fade" id="bank" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-bank"></i> الحسابات البنكية</h5>
                        <?php if (empty($bank_accounts)): ?><div class="text-center text-muted py-4">لا توجد حسابات</div>
                        <?php else: ?><div class="table-responsive"><table class="table table-hover"><thead><tr><th>البنك</th><th>رقم الحساب</th><th>IBAN</th><th>رئيسي</th></tr></thead><tbody><?php foreach ($bank_accounts as $b): ?><tr><td><strong><?= htmlspecialchars($b['bank_name']) ?></strong></td><td><?= htmlspecialchars($b['account_number']) ?></td><td><?= htmlspecialchars($b['iban']??'-') ?></td><td><?= $b['is_primary']?'<span class="badge bg-primary">✓</span>':'-' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>