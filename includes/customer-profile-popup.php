<?php
/**
 * كارت العميل + كشف حساب - Popup Window
 * Tabs: بيانات العميل | كشف حساب
 * Parameters: customer_id (required)
 */
require_once __DIR__ . '/../core/config.php';
$db = getDB();

$customer_id = intval($_GET['customer_id'] ?? 0);
if ($customer_id <= 0) die('معرف العميل مطلوب');

// Check which columns exist in customers table
$cols = $db->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
$hasBalance = in_array('balance', $cols);
$hasAddress = in_array('address', $cols);
$hasBranch = in_array('branch_id', $cols);
$hasEmail = in_array('email', $cols);
$hasTaxNumber = in_array('tax_number', $cols);

// Check which tables exist
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$hasSaleInvoices = in_array('sale_invoices', $tables);
$hasCustomerPayments = in_array('customer_payments', $tables);
$hasSaleReturns = in_array('sale_return_invoices', $tables);
$hasCustTransactions = in_array('customer_transactions', $tables);

// Build select
$sel = "c.id, c.customer_name, c.customer_code, c.phone, c.is_active, c.created_at";
$join = "";
if ($hasBalance) $sel .= ", c.balance";
if ($hasAddress) $sel .= ", c.address";
if ($hasBranch) { $sel .= ", c.branch_id, b.branch_name"; $join = " LEFT JOIN branches b ON c.branch_id = b.id"; }

$stmt = $db->prepare("SELECT $sel FROM customers c $join WHERE c.id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();
if (!$customer) die('العميل غير موجود');

// Stats
$stats = ['inv_count' => 0, 'total_sales' => 0, 'total_paid' => 0];
if ($hasSaleInvoices) {
    try {
        $s = $db->prepare("SELECT COUNT(*) as inv_count, COALESCE(SUM(grand_total),0) as total_sales, COALESCE(SUM(paid_amount),0) as total_paid FROM sale_invoices WHERE customer_id = ?");
        $s->execute([$customer_id]);
        $stats = $s->fetch();
    } catch (PDOException $e) {}
}

// Get ledger transactions - build UNION dynamically based on existing tables
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-3 months'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$transactions = [];

$unions = [];
$params = [];

if ($hasSaleInvoices) {
    $unions[] = "SELECT 'invoice' as type, si.id as ref_id, si.invoice_number as ref_number, si.invoice_date as trans_date, si.grand_total as debit, si.paid_amount as credit, si.status, 'فاتورة بيع' as trans_type, si.notes FROM sale_invoices si WHERE si.customer_id = ? AND si.invoice_date BETWEEN ? AND ?";
    $params = array_merge($params, [$customer_id, $from_date, $to_date]);
}

if ($hasCustomerPayments) {
    $unions[] = "SELECT 'payment' as type, cp.id as ref_id, cp.payment_number as ref_number, cp.payment_date as trans_date, 0 as debit, cp.amount as credit, 'completed' as status, 'دفعة' as trans_type, cp.notes FROM customer_payments cp WHERE cp.customer_id = ? AND cp.payment_date BETWEEN ? AND ?";
    $params = array_merge($params, [$customer_id, $from_date, $to_date]);
}

if ($hasSaleReturns) {
    $unions[] = "SELECT 'return' as type, sri.id as ref_id, sri.return_number as ref_number, sri.return_date as trans_date, 0 as debit, sri.grand_total as credit, sri.status, 'مرتجع بيع' as trans_type, sri.notes FROM sale_return_invoices sri WHERE sri.customer_id = ? AND sri.return_date BETWEEN ? AND ?";
    $params = array_merge($params, [$customer_id, $from_date, $to_date]);
}

if (!empty($unions)) {
    try {
        $sql = implode(" UNION ALL ", $unions) . " ORDER BY trans_date DESC, ref_id DESC";
        $ledger = $db->prepare($sql);
        $ledger->execute($params);
        $transactions = $ledger->fetchAll();
    } catch (PDOException $e) {
        $transactions = [];
    }
}

// Calculate running balance
$runningBalance = 0;
foreach (array_reverse($transactions) as $t) {
    $runningBalance += floatval($t['debit']) - floatval($t['credit']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>كارت العميل - <?= htmlspecialchars($customer['customer_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2;--green:#198754;--red:#dc3545;--gold:#ffc107;}
*{box-sizing:border-box}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;}
.popup-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:15px 20px;display:flex;align-items:center;gap:15px;}
.popup-header h4{margin:0;font-size:18px;}
.popup-header .cust-code{background:rgba(255,255,255,0.2);padding:3px 12px;border-radius:15px;font-size:12px;}
.nav-tabs{padding:0 20px;background:#fff;border-bottom:2px solid #e9ecef;margin:0;}
.nav-tabs .nav-link{border:none;padding:12px 20px;font-weight:600;color:#666;font-size:14px;cursor:pointer;}
.nav-tabs .nav-link:hover{color:var(--primary);}
.nav-tabs .nav-link.active{color:var(--primary);border-bottom:3px solid var(--primary);background:transparent;}
.tab-content{padding:20px;}
.info-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:15px;}
.info-card h6{color:var(--primary);font-weight:700;margin-bottom:15px;font-size:14px;border-bottom:2px solid #e9ecef;padding-bottom:8px;}
.info-row{display:flex;margin-bottom:10px;font-size:13px;}
.info-row label{color:#888;min-width:120px;}
.info-row span{color:#333;font-weight:600;}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;}
.stat-box{background:#fff;border-radius:12px;padding:15px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.06);border-top:3px solid var(--primary);}
.stat-box.sales{border-top-color:var(--primary);}
.stat-box.paid{border-top-color:var(--green);}
.stat-box.due{border-top-color:var(--red);}
.stat-box.count{border-top-color:var(--gold);}
.stat-box .num{font-size:24px;font-weight:700;color:#333;}
.stat-box .lbl{font-size:12px;color:#888;margin-top:5px;}
.filter-bar{background:#fff;border-radius:8px;padding:12px 15px;display:flex;gap:10px;align-items:center;margin-bottom:15px;box-shadow:0 1px 4px rgba(0,0,0,0.05);}
.filter-bar input{height:35px;font-size:13px;}
.filter-bar button{height:35px;font-size:13px;}
.ledger-table{width:100%;border-collapse:collapse;font-size:12px;}
.ledger-table thead th{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:10px;font-size:12px;font-weight:600;position:sticky;top:0;z-index:10;}
.ledger-table tbody td{padding:8px 10px;border-bottom:1px solid #e9ecef;vertical-align:middle;}
.ledger-table tbody tr:hover{background:#f8f9ff;}
.ledger-table .type-badge{padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;}
.ledger-table .type-invoice{background:#e3f2fd;color:#1565c0;}
.ledger-table .type-payment{background:#e8f5e9;color:#2e7d32;}
.ledger-table .type-return{background:#fce4ec;color:#c62828;}
.ledger-table .amount{font-weight:700;}
.ledger-table .status-paid{color:var(--green);}
.ledger-table .status-partial{color:var(--gold);}
.ledger-table .status-open{color:var(--red);}
.total-bar{background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:8px;padding:15px;margin-top:15px;display:flex;justify-content:space-around;font-size:14px;}
.total-bar .t-item{text-align:center;}
.total-bar .t-item strong{display:block;font-size:18px;margin-top:5px;}
.no-data{text-align:center;padding:40px;color:#999;}
@media print{.filter-bar,.no-print{display:none!important}.tab-content{display:block!important}.tab-pane{display:block!important;opacity:1!important;}}
</style>
</head>
<body>

<div class="popup-header">
    <i class="bi bi-person-circle" style="font-size:28px"></i>
    <div>
        <h4><?= htmlspecialchars($customer['customer_name']) ?></h4>
        <span class="cust-code"><?= htmlspecialchars($customer['customer_code'] ?? 'بدون كود') ?></span>
        <?php if ($customer['is_active']): ?><span class="cust-code" style="background:rgba(25,135,84,0.3)"><i class="bi bi-check-circle"></i> نشط</span><?php else: ?><span class="cust-code" style="background:rgba(220,53,69,0.3)"><i class="bi bi-x-circle"></i> غير نشط</span><?php endif; ?>
    </div>
    <div class="ms-auto no-print">
        <button class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
        <button class="btn btn-danger btn-sm" onclick="window.close()" style="margin-right:5px"><i class="bi bi-x-lg"></i> إغلاق</button>
    </div>
</div>

<ul class="nav nav-tabs" id="profileTabs">
    <li class="nav-item">
        <button class="nav-link active" onclick="showTab('tab-profile',this)"><i class="bi bi-person-vcard"></i> كارت العميل</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" onclick="showTab('tab-ledger',this)"><i class="bi bi-journal-text"></i> كشف حساب</button>
    </li>
</ul>

<div class="tab-content">

<!-- TAB 1: PROFILE -->
<div id="tab-profile" class="tab-pane" style="display:block">
    <div class="stats-row">
        <div class="stat-box count">
            <div class="num"><?= number_format($stats['inv_count'] ?? 0) ?></div>
            <div class="lbl">عدد الفواتير</div>
        </div>
        <div class="stat-box sales">
            <div class="num" style="color:var(--primary)"><?= number_format($stats['total_sales'] ?? 0, 2) ?></div>
            <div class="lbl">إجمالي المبيعات</div>
        </div>
        <div class="stat-box paid">
            <div class="num" style="color:var(--green)"><?= number_format($stats['total_paid'] ?? 0, 2) ?></div>
            <div class="lbl">إجمالي المدفوعات</div>
        </div>
        <div class="stat-box due">
            <div class="num" style="color:var(--red)"><?= number_format(($stats['total_sales'] - $stats['total_paid']) ?? 0, 2) ?></div>
            <div class="lbl">المبلغ المستحق</div>
        </div>
    </div>
    
    <div class="row g-3">
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="bi bi-info-circle"></i> البيانات الأساسية</h6>
                <div class="info-row"><label><i class="bi bi-person"></i> الاسم:</label><span><?= htmlspecialchars($customer['customer_name']) ?></span></div>
                <div class="info-row"><label><i class="bi bi-hash"></i> الكود:</label><span><?= htmlspecialchars($customer['customer_code'] ?? '-') ?></span></div>
                <?php if ($hasTaxNumber && $customer['tax_number']): ?>
                <div class="info-row"><label><i class="bi bi-receipt"></i> الرقم الضريبي:</label><span><?= htmlspecialchars($customer['tax_number']) ?></span></div>
                <?php endif; ?>
                <div class="info-row"><label><i class="bi bi-telephone"></i> التليفون:</label><span dir="ltr"><?= htmlspecialchars($customer['phone'] ?? '-') ?></span></div>
                <?php if ($hasEmail && $customer['email']): ?>
                <div class="info-row"><label><i class="bi bi-envelope"></i> البريد:</label><span><?= htmlspecialchars($customer['email']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="bi bi-geo-alt"></i> العنوان والفرع</h6>
                <?php if ($hasAddress && $customer['address']): ?>
                <div class="info-row"><label><i class="bi bi-geo-alt-fill"></i> العنوان:</label><span><?= htmlspecialchars($customer['address']) ?></span></div>
                <?php else: ?>
                <div class="info-row"><label><i class="bi bi-geo-alt-fill"></i> العنوان:</label><span class="text-muted">غير مسجل</span></div>
                <?php endif; ?>
                <?php if ($hasBranch && $customer['branch_name']): ?>
                <div class="info-row"><label><i class="bi bi-building"></i> الفرع:</label><span><?= htmlspecialchars($customer['branch_name']) ?></span></div>
                <?php else: ?>
                <div class="info-row"><label><i class="bi bi-building"></i> الفرع:</label><span class="text-muted">غير محدد</span></div>
                <?php endif; ?>
                <div class="info-row"><label><i class="bi bi-calendar"></i> تسجيل:</label><span><?= $customer['created_at'] ? date('Y-m-d', strtotime($customer['created_at'])) : '-' ?></span></div>
                <div class="info-row"><label><i class="bi bi-shield-check"></i> الحالة:</label><span><?= $customer['is_active'] ? '<span style="color:var(--green)">نشط</span>' : '<span style="color:var(--red)">غير نشط</span>' ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: LEDGER -->
<div id="tab-ledger" class="tab-pane" style="display:none">
    <form method="GET" class="filter-bar no-print">
        <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
        <label style="font-size:13px;font-weight:600"><i class="bi bi-funnel"></i> فلتر:</label>
        <input type="date" name="from_date" class="form-control" value="<?= $from_date ?>" style="width:140px">
        <span>إلى</span>
        <input type="date" name="to_date" class="form-control" value="<?= $to_date ?>" style="width:140px">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> عرض</button>
        <button type="button" class="btn btn-outline-secondary" onclick="location.href='?customer_id=<?= $customer_id ?>'"><i class="bi bi-arrow-counterclockwise"></i></button>
        <div class="ms-auto" style="font-size:13px">
            <span class="badge bg-primary"><?= count($transactions) ?> حركة</span>
        </div>
    </form>
    
    <div class="card">
        <div class="table-responsive" style="max-height:450px;overflow-y:auto;">
            <table class="table ledger-table mb-0">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:100px">التاريخ</th>
                        <th style="width:100px">النوع</th>
                        <th>رقم المرجع</th>
                        <th style="width:80px">مدين</th>
                        <th style="width:80px">دائن</th>
                        <th style="width:80px">الرصيد</th>
                        <th style="width:80px">الحالة</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr><td colspan="9" class="no-data"><i class="bi bi-inbox" style="font-size:32px"></i><br>لا توجد حركات في الفترة المحددة</td></tr>
                    <?php else: 
                        $runningBal = 0;
                        foreach (array_reverse($transactions) as $t) {
                            $runningBal += floatval($t['debit']) - floatval($t['credit']);
                        }
                        $runningBalForward = 0;
                        foreach ($transactions as $i => $t):
                            $runningBalForward += floatval($t['debit']) - floatval($t['credit']);
                            $typeClass = match($t['type']) {
                                'invoice' => 'type-invoice',
                                'payment' => 'type-payment',
                                'return' => 'type-return',
                                default => 'type-invoice'
                            };
                            $typeLabel = match($t['type']) {
                                'invoice' => 'فاتورة',
                                'payment' => 'دفعة',
                                'return' => 'مرتجع',
                                default => $t['type']
                            };
                            $statusClass = match($t['status']) {
                                'paid' => 'status-paid',
                                'partial' => 'status-partial',
                                'open' => 'status-open',
                                default => ''
                            };
                            $statusLabel = match($t['status']) {
                                'paid' => 'مسدد',
                                'partial' => 'جزئي',
                                'open' => 'مفتوح',
                                'completed' => 'تم',
                                default => $t['status']
                            };
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= $t['trans_date'] ?></td>
                        <td><span class="type-badge <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                        <td><small class="font-monospace"><?= htmlspecialchars($t['ref_number']) ?></small></td>
                        <td class="amount text-danger"><?= $t['debit'] > 0 ? number_format($t['debit'], 2) : '-' ?></td>
                        <td class="amount text-success"><?= $t['credit'] > 0 ? number_format($t['credit'], 2) : '-' ?></td>
                        <td class="amount fw-bold"><?= number_format($runningBalForward, 2) ?></td>
                        <td class="<?= $statusClass ?>"><?= $statusLabel ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($t['notes'] ?? '') ?></small></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="total-bar">
        <div class="t-item">إجمالي مدين<strong style="color:var(--red)"><?= number_format(array_sum(array_column($transactions, 'debit')), 2) ?></strong></div>
        <div class="t-item">إجمالي دائن<strong style="color:var(--green)"><?= number_format(array_sum(array_column($transactions, 'credit')), 2) ?></strong></div>
        <div class="t-item">الرصيد النهائي<strong style="color:var(--primary)"><?= number_format($runningBalForward ?? 0, 2) ?></strong></div>
    </div>
</div>

</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    btn.classList.add('active');
}

// Auto-switch to ledger tab if requested
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('tab') === 'ledger') {
    const ledgerTab = document.querySelector('button[onclick*="tab-ledger"]');
    if (ledgerTab) ledgerTab.click();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') window.close();
});
</script>

</body>
</html>
