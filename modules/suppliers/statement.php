<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$supplier_id = intval($_GET['id'] ?? 0);
if ($supplier_id <= 0) {
    redirect('index.php');
}

// Get supplier details
$stmt = $db->prepare("
    SELECT s.*, sb.balance, sb.total_purchases, sb.total_payments, sb.total_returns
    FROM suppliers s
    LEFT JOIN supplier_balances sb ON s.id = sb.supplier_id
    WHERE s.id = ?
");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    redirect('index.php');
}

// Get transactions
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM supplier_transactions WHERE supplier_id = ?");
$count_stmt->execute([$supplier_id]);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

$stmt = $db->prepare("
    SELECT st.*, u.full_name as created_by_name
    FROM supplier_transactions st
    LEFT JOIN users u ON st.created_by = u.id
    WHERE st.supplier_id = ?
    ORDER BY st.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute([$supplier_id]);
$transactions = $stmt->fetchAll();

// Transaction type labels
$tx_labels = [
    'purchase' => ['فاتورة شراء', 'tx-purchase', 'bi-file-earmark-text'],
    'payment' => ['دفعة', 'tx-payment', 'bi-cash-coin'],
    'return' => ['مرتجع', 'tx-return', 'bi-arrow-counterclockwise'],
    'refund' => ['استرداد', 'tx-refund', 'bi-arrow-return-left'],
    'adjustment' => ['تسوية', 'tx-adjustment', 'bi-sliders']
];

$page_title = 'كشف حساب - ' . $supplier['supplier_name'];
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
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; border-radius: 8px; margin: 2px 10px; text-decoration: none; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { color: #fff; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .sidebar .nav-link i { margin-left: 10px; font-size: 18px; color: rgba(255,255,255,0.7); }
        .sidebar .nav-link:hover i { color: #fff; }
        .sidebar .nav-link.active i { color: #fff; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; }
        .sidebar-brand h4 { margin: 0; font-size: 20px; }
        .sidebar-brand small { color: rgba(255,255,255,0.6); font-size: 12px; }
        .sidebar-heading { color: rgba(255,255,255,0.5); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px 5px; font-weight: 600; }
        .nav-menu { padding: 10px 0; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .balance-card { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; }
        .balance-card .card-body { padding: 25px; }
        .balance-amount { font-size: 32px; font-weight: bold; }
        .summary-card { text-align: center; padding: 15px; }
        .summary-card .icon { font-size: 28px; margin-bottom: 10px; }
        .summary-card .amount { font-size: 20px; font-weight: bold; }
        .summary-card .label { font-size: 12px; color: #666; }
        .table-statement th { background: #f8f9fa; font-weight: 600; font-size: 13px; }
        .table-statement td { font-size: 13px; vertical-align: middle; }
        .tx-purchase { color: var(--danger); }
        .tx-payment { color: var(--success); }
        .tx-return { color: var(--warning); }
        .tx-refund { color: var(--info); }
        .tx-adjustment { color: #6c757d; }
        .print-btn { float: left; }
        @media print { .sidebar, .no-print { display: none !important; } .main-content { margin-right: 0; } }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <div>
                    <h2><i class="bi bi-file-text"></i> كشف حساب مورد</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">الموردين</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($supplier['supplier_name']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> رجوع
                    </a>
                    <button onclick="window.print()" class="btn btn-primary print-btn">
                        <i class="bi bi-printer"></i> طباعة
                    </button>
                </div>
            </div>

            <!-- Supplier Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5><i class="bi bi-truck"></i> <?= htmlspecialchars($supplier['supplier_name']) ?></h5>
                            <?php if (!empty($supplier['supplier_name_en'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($supplier['supplier_name_en']) ?></small>
                            <?php endif; ?>
                            <div class="mt-2">
                                <span class="badge bg-light text-dark border">كود: <?= htmlspecialchars($supplier['supplier_code'] ?? $supplier['id']) ?></span>
                                <span class="badge bg-light text-dark border">
                                    <?= ['b2b'=>'صيدلية','private_office'=>'مكتب خاص','warehouse'=>'مخزن','distributor'=>'موزع','company'=>'شركة'][$supplier['supplier_type']] ?? 'شركة' ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div><i class="bi bi-cash-stack"></i> طريقة الدفع: 
                                <?= $supplier['payment_type'] == 'credit' ? 'آجل' : ($supplier['payment_type'] == 'cheque' ? 'شيك' : 'نقدي') ?>
                            </div>
                            <?php if ($supplier['payment_type'] != 'cash' && $supplier['credit_limit'] > 0): ?>
                                <div><i class="bi bi-credit-card"></i> حد التعامل: <?= number_format($supplier['credit_limit'], 2) ?> ج</div>
                            <?php endif; ?>
                            <?php if ($supplier['grace_period'] > 0): ?>
                                <div><i class="bi bi-clock"></i> فترة السماح: <?= $supplier['grace_period'] ?> يوم</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div><i class="bi bi-telephone"></i> 
                                <?= !empty($supplier['instapay_number']) ? 'إنستاباي: ' . htmlspecialchars($supplier['instapay_number']) : '-' ?>
                            </div>
                            <div><i class="bi bi-wallet"></i> 
                                <?= !empty($supplier['wallet_number']) ? 'محفظة: ' . htmlspecialchars($supplier['wallet_number']) : '-' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card balance-card">
                        <div class="card-body text-center">
                            <div class="mb-2"><i class="bi bi-wallet2" style="font-size: 24px;"></i></div>
                            <div class="balance-amount"><?= number_format(floatval($supplier['balance'] ?? 0), 2) ?> ج</div>
                            <div>الرصيد الحالي</div>
                            <small><?= floatval($supplier['balance'] ?? 0) > 0 ? '(علينا للمورد)' : (floatval($supplier['balance'] ?? 0) < 0 ? '(المورد علينا)' : 'متعادل') ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card">
                        <div class="icon text-danger"><i class="bi bi-cart-plus"></i></div>
                        <div class="amount text-danger"><?= number_format(floatval($supplier['total_purchases'] ?? 0), 2) ?> ج</div>
                        <div class="label">إجمالي المشتريات</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card">
                        <div class="icon text-success"><i class="bi bi-cash-coin"></i></div>
                        <div class="amount text-success"><?= number_format(floatval($supplier['total_payments'] ?? 0), 2) ?> ج</div>
                        <div class="label">إجمالي المدفوعات</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card">
                        <div class="icon text-warning"><i class="bi bi-arrow-counterclockwise"></i></div>
                        <div class="amount text-warning"><?= number_format(floatval($supplier['total_returns'] ?? 0), 2) ?> ج</div>
                        <div class="label">إجمالي المرتجعات</div>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> حركات الحساب</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-statement table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>التاريخ</th>
                                    <th>نوع الحركة</th>
                                    <th>رقم المرجع</th>
                                    <th>علينا (مدين)</th>
                                    <th>له (دائن)</th>
                                    <th>الرصيد بعد</th>
                                    <th>ملاحظات</th>
                                    <th>بواسطة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                <?php $tx_info = $tx_labels[$tx['transaction_type']] ?? ['غير معروف', 'text-muted', 'bi-question-circle']; ?>
                                <tr>
                                    <td><?= $tx['id'] ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($tx['created_at'])) ?></td>
                                    <td>
                                        <span class="<?= $tx_info[1] ?>">
                                            <i class="bi <?= $tx_info[2] ?>"></i> <?= $tx_info[0] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($tx['reference_number'])): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($tx['reference_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-danger"><?= $tx['debit'] > 0 ? number_format($tx['debit'], 2) : '-' ?></td>
                                    <td class="text-success"><?= $tx['credit'] > 0 ? number_format($tx['credit'], 2) : '-' ?></td>
                                    <td class="fw-bold"><?= number_format($tx['balance_after'], 2) ?> ج</td>
                                    <td><?= htmlspecialchars($tx['notes'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($tx['created_by_name'] ?? 'النظام') ?></td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 48px; color: #ddd;"></i>
                                        <h5 class="mt-3 text-muted">لا توجد حركات مسجلة</h5>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4 no-print">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?= $supplier_id ?>&page=<?= $page - 1 ?>">السابق</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?id=<?= $supplier_id ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?= $supplier_id ?>&page=<?= $page + 1 ?>">التالي</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>