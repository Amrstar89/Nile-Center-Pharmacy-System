<?php
/**
 * كارت العميل + كشف حساب - Popup Window
 * Tabs: بيانات العميل | كشف حساب
 * AUTO-CREATE: any missing tables/columns are created automatically
 */
require_once __DIR__ . '/../core/config.php';
$db = getDB();

// ============================================================
// AUTO-CREATE HELPER FUNCTIONS
// ============================================================
function ensureColumn($db, $table, $column, $def) {
    $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchAll();
    if (empty($exists)) {
        try { $db->exec("ALTER TABLE `$table` ADD COLUMN $column $def"); } catch (PDOException $e) {}
    }
}

function tableExists($db, $table) {
    $tables = $db->query("SHOW TABLES LIKE '$table'")->fetchAll();
    return !empty($tables);
}

function ensureTable($db, $table, $sql) {
    if (!tableExists($db, $table)) {
        try { $db->exec($sql); } catch (PDOException $e) {}
    }
}

// ============================================================
// STEP 1-9: Auto-create all missing tables and columns
// ============================================================
ensureColumn($db, 'customers', 'balance', "DECIMAL(15,2) DEFAULT 0 AFTER phone");
ensureColumn($db, 'customers', 'address', "VARCHAR(500) NULL AFTER balance");
ensureColumn($db, 'customers', 'branch_id', "INT NULL AFTER address");
ensureColumn($db, 'customers', 'email', "VARCHAR(200) NULL AFTER customer_name");
ensureColumn($db, 'customers', 'tax_number', "VARCHAR(50) NULL AFTER email");

if (!tableExists($db, 'branches')) {
    ensureTable($db, 'branches', "
        CREATE TABLE IF NOT EXISTS branches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_name VARCHAR(200) NOT NULL,
            branch_code VARCHAR(50) NULL,
            address VARCHAR(500) NULL,
            phone VARCHAR(50) NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

ensureTable($db, 'customer_payments', "
    CREATE TABLE IF NOT EXISTS customer_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_number VARCHAR(50) NOT NULL,
        customer_id INT NOT NULL,
        invoice_id INT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        payment_date DATE NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'cash',
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

ensureTable($db, 'sale_return_invoices', "
    CREATE TABLE IF NOT EXISTS sale_return_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_number VARCHAR(50) NOT NULL,
        customer_id INT NOT NULL,
        store_id INT NULL,
        user_id INT NULL,
        return_date DATE NOT NULL,
        subtotal DECIMAL(15,2) DEFAULT 0,
        discount_pct DECIMAL(5,2) DEFAULT 0,
        discount_val DECIMAL(15,2) DEFAULT 0,
        vat_amount DECIMAL(15,2) DEFAULT 0,
        grand_total DECIMAL(15,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'open',
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

ensureTable($db, 'sale_return_items', "
    CREATE TABLE IF NOT EXISTS sale_return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(300) NULL,
        product_code VARCHAR(100) NULL,
        barcode VARCHAR(100) NULL,
        unit_name VARCHAR(100) DEFAULT 'علبة',
        quantity DECIMAL(10,2) DEFAULT 0,
        sell_price DECIMAL(15,2) DEFAULT 0,
        line_total DECIMAL(15,2) DEFAULT 0,
        expiry_date DATE NULL,
        batch_number VARCHAR(100) NULL,
        notes TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

ensureTable($db, 'customer_transactions', "
    CREATE TABLE IF NOT EXISTS customer_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        reference_type VARCHAR(50) NULL,
        reference_id INT NULL,
        reference_number VARCHAR(50) NULL,
        debit DECIMAL(15,2) DEFAULT 0,
        credit DECIMAL(15,2) DEFAULT 0,
        balance_after DECIMAL(15,2) DEFAULT 0,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

ensureTable($db, 'inventory_movements', "
    CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NULL,
        product_id INT NULL,
        movement_type VARCHAR(50) NOT NULL,
        reference_type VARCHAR(50) NULL,
        reference_id INT NULL,
        reference_number VARCHAR(50) NULL,
        quantity DECIMAL(10,2) DEFAULT 0,
        unit_cost DECIMAL(15,2) DEFAULT 0,
        total_cost DECIMAL(15,2) DEFAULT 0,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

ensureColumn($db, 'sale_invoices', 'invoice_time', 'TIME NULL AFTER invoice_date');
ensureColumn($db, 'sale_invoices', 'profit_amount', 'DECIMAL(15,2) DEFAULT 0 AFTER remaining_amount');
ensureColumn($db, 'sale_invoices', 'cost_amount', 'DECIMAL(15,2) DEFAULT 0 AFTER profit_amount');
ensureColumn($db, 'sale_invoices', 'extra_discount_pct', 'DECIMAL(5,2) DEFAULT 0 AFTER discount_val');
ensureColumn($db, 'sale_invoices', 'extra_discount_val', 'DECIMAL(15,2) DEFAULT 0 AFTER extra_discount_pct');
ensureColumn($db, 'sale_invoice_items', 'batch_id', 'INT NULL AFTER batch_number');

// ============================================================
// NOW FETCH DATA
// ============================================================
$customer_id = intval($_GET['customer_id'] ?? 0);
if ($customer_id <= 0) die('معرف العميل مطلوب');

$stmt = $db->prepare("
    SELECT c.*, b.branch_name 
    FROM customers c 
    LEFT JOIN branches b ON c.branch_id = b.id 
    WHERE c.id = ?
");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();
if (!$customer) die('العميل غير موجود');

// Stats
$stats = ['inv_count' => 0, 'total_sales' => 0, 'total_paid' => 0];
try {
    $s = $db->prepare("
        SELECT COUNT(*) as inv_count, COALESCE(SUM(grand_total),0) as total_sales, COALESCE(SUM(paid_amount),0) as total_paid 
        FROM sale_invoices WHERE customer_id = ?");
    $s->execute([$customer_id]);
    $stats = $s->fetch();
} catch (PDOException $e) {}

// Ledger
$from_date = $_GET['from_date'] ?? date('Y-m-d', strtotime('-3 months'));
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$active_tab = $_GET['tab'] ?? 'profile'; // 'profile' or 'ledger'

$transactions = [];
$unions = [];
$params = [];

if (tableExists($db, 'sale_invoices')) {
    $unions[] = "SELECT 'invoice' as type, id as ref_id, invoice_number as ref_number, invoice_date as trans_date, grand_total as debit, paid_amount as credit, status, 'فاتورة بيع' as trans_type, notes FROM sale_invoices WHERE customer_id = ? AND invoice_date BETWEEN ? AND ?";
    $params = array_merge($params, [$customer_id, $from_date, $to_date]);
}
if (tableExists($db, 'customer_payments')) {
    $unions[] = "SELECT 'payment' as type, id as ref_id, payment_number as ref_number, payment_date as trans_date, 0 as debit, amount as credit, 'completed' as status, 'دفعة' as trans_type, notes FROM customer_payments WHERE customer_id = ? AND payment_date BETWEEN ? AND ?";
    $params = array_merge($params, [$customer_id, $from_date, $to_date]);
}
if (tableExists($db, 'sale_return_invoices')) {
    $unions[] = "SELECT 'return' as type, id as ref_id, return_number as ref_number, return_date as trans_date, 0 as debit, grand_total as credit, status, 'مرتجع بيع' as trans_type, notes FROM sale_return_invoices WHERE customer_id = ? AND return_date BETWEEN ? AND ?";
    $params = array_merge($params, [$customer_id, $from_date, $to_date]);
}

if (!empty($unions)) {
    try {
        $sql = implode(" UNION ALL ", $unions) . " ORDER BY trans_date DESC, ref_id DESC";
        $ledger = $db->prepare($sql);
        $ledger->execute($params);
        $transactions = $ledger->fetchAll();
    } catch (PDOException $e) { $transactions = []; }
}

// Calculate running balance (forward)
$runningBalForward = 0;
foreach ($transactions as $t) {
    $runningBalForward += floatval($t['debit']) - floatval($t['credit']);
}

// Totals
$totalDebit = array_sum(array_map(fn($t)=>floatval($t['debit']??0), $transactions));
$totalCredit = array_sum(array_map(fn($t)=>floatval($t['credit']??0), $transactions));
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
.nav-tabs{padding:0 20px;background:#fff;border-bottom:2px solid #e9ecef;margin:0;list-style:none;display:flex;}
.nav-tabs .nav-link{border:none;padding:12px 20px;font-weight:600;color:#666;font-size:14px;cursor:pointer;background:transparent;}
.nav-tabs .nav-link:hover{color:var(--primary);}
.nav-tabs .nav-link.active{color:var(--primary);border-bottom:3px solid var(--primary);}
.tab-content{padding:20px;}
.info-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:15px;}
.info-card h6{color:var(--primary);font-weight:700;margin-bottom:15px;font-size:14px;border-bottom:2px solid #e9ecef;padding-bottom:8px;}
.info-row{display:flex;margin-bottom:10px;font-size:13px;}
.info-row label{color:#888;min-width:120px;}
.info-row span{color:#333;font-weight:600;}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;}
.stat-box{background:#fff;border-radius:12px;padding:15px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.06);border-top:3px solid var(--primary);}
.stat-box.paid{border-top-color:var(--green);}
.stat-box.due{border-top-color:var(--red);}
.stat-box.count{border-top-color:var(--gold);}
.stat-box .num{font-size:24px;font-weight:700;color:#333;}
.stat-box .lbl{font-size:12px;color:#888;margin-top:5px;}
.filter-bar{background:#fff;border-radius:8px;padding:12px 15px;display:flex;gap:10px;align-items:center;margin-bottom:15px;box-shadow:0 1px 4px rgba(0,0,0,0.05);flex-wrap:wrap;}
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
.total-bar{background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:8px;padding:15px;margin-top:15px;display:flex;justify-content:space-around;font-size:14px;flex-wrap:wrap;gap:15px;}
.total-bar .t-item{text-align:center;}
.total-bar .t-item strong{display:block;font-size:18px;margin-top:5px;}
.no-data{text-align:center;padding:40px;color:#999;}
@media print{.filter-bar,.no-print{display:none!important}.tab-content{display:block!important}.tab-pane{display:block!important;}}
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
        <button class="nav-link <?= $active_tab === 'profile' ? 'active' : '' ?>" onclick="showTab('tab-profile',this)"><i class="bi bi-person-vcard"></i> كارت العميل</button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $active_tab === 'ledger' ? 'active' : '' ?>" onclick="showTab('tab-ledger',this)"><i class="bi bi-journal-text"></i> كشف حساب</button>
    </li>
</ul>

<div class="tab-content">

<!-- TAB 1: PROFILE -->
<div id="tab-profile" class="tab-pane" style="display:<?= $active_tab === 'profile' ? 'block' : 'none' ?>">
    <div class="stats-row">
        <div class="stat-box count">
            <div class="num"><?= number_format(intval($stats['inv_count'] ?? 0)) ?></div>
            <div class="lbl">عدد الفواتير</div>
        </div>
        <div class="stat-box" style="border-top-color:var(--primary)">
            <div class="num" style="color:var(--primary)"><?= number_format(floatval($stats['total_sales'] ?? 0), 2) ?></div>
            <div class="lbl">إجمالي المبيعات</div>
        </div>
        <div class="stat-box paid">
            <div class="num" style="color:var(--green)"><?= number_format(floatval($stats['total_paid'] ?? 0), 2) ?></div>
            <div class="lbl">إجمالي المدفوعات</div>
        </div>
        <div class="stat-box due">
            <div class="num" style="color:var(--red)"><?= number_format(floatval($stats['total_sales'] ?? 0) - floatval($stats['total_paid'] ?? 0), 2) ?></div>
            <div class="lbl">المبلغ المستحق</div>
        </div>
    </div>
    
    <div class="row g-3">
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="bi bi-info-circle"></i> البيانات الأساسية</h6>
                <div class="info-row"><label><i class="bi bi-person"></i> الاسم:</label><span><?= htmlspecialchars($customer['customer_name']) ?></span></div>
                <div class="info-row"><label><i class="bi bi-hash"></i> الكود:</label><span><?= htmlspecialchars($customer['customer_code'] ?? '-') ?></span></div>
                <?php if (!empty($customer['tax_number'])): ?>
                <div class="info-row"><label><i class="bi bi-receipt"></i> الرقم الضريبي:</label><span><?= htmlspecialchars($customer['tax_number']) ?></span></div>
                <?php endif; ?>
                <div class="info-row"><label><i class="bi bi-telephone"></i> التليفون:</label><span dir="ltr"><?= htmlspecialchars($customer['phone'] ?? '-') ?></span></div>
                <?php if (!empty($customer['email'])): ?>
                <div class="info-row"><label><i class="bi bi-envelope"></i> البريد:</label><span><?= htmlspecialchars($customer['email']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <h6><i class="bi bi-geo-alt"></i> العنوان والفرع</h6>
                <?php if (!empty($customer['address'])): ?>
                <div class="info-row"><label><i class="bi bi-geo-alt-fill"></i> العنوان:</label><span><?= htmlspecialchars($customer['address']) ?></span></div>
                <?php else: ?>
                <div class="info-row"><label><i class="bi bi-geo-alt-fill"></i> العنوان:</label><span class="text-muted">غير مسجل</span></div>
                <?php endif; ?>
                <?php if (!empty($customer['branch_name'])): ?>
                <div class="info-row"><label><i class="bi bi-building"></i> الفرع:</label><span><?= htmlspecialchars($customer['branch_name']) ?></span></div>
                <?php else: ?>
                <div class="info-row"><label><i class="bi bi-building"></i> الفرع:</label><span class="text-muted">غير محدد</span></div>
                <?php endif; ?>
                <div class="info-row"><label><i class="bi bi-calendar"></i> تسجيل:</label><span><?= !empty($customer['created_at']) ? date('Y-m-d', strtotime($customer['created_at'])) : '-' ?></span></div>
                <div class="info-row"><label><i class="bi bi-shield-check"></i> الحالة:</label><span><?= !empty($customer['is_active']) ? '<span style="color:var(--green)">نشط</span>' : '<span style="color:var(--red)">غير نشط</span>' ?></span></div>
                <?php if (isset($customer['balance'])): ?>
                <div class="info-row"><label><i class="bi bi-wallet2"></i> الرصيد:</label><span style="color:var(--primary);font-weight:700"><?= number_format(floatval($customer['balance']), 2) ?> ج.م</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- TAB 2: LEDGER -->
<div id="tab-ledger" class="tab-pane" style="display:<?= $active_tab === 'ledger' ? 'block' : 'none' ?>">
    <form method="GET" class="filter-bar no-print">
        <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
        <!-- KEY FIX: tab=ledger persists when filtering -->
        <input type="hidden" name="tab" value="ledger">
        <label style="font-size:13px;font-weight:600"><i class="bi bi-funnel"></i> فترة:</label>
        <input type="date" name="from_date" class="form-control" value="<?= $from_date ?>" style="width:140px">
        <span>إلى</span>
        <input type="date" name="to_date" class="form-control" value="<?= $to_date ?>" style="width:140px">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> عرض</button>
        <button type="button" class="btn btn-outline-secondary" onclick="location.href='?customer_id=<?= $customer_id ?>&amp;tab=ledger'"><i class="bi bi-arrow-counterclockwise"></i> إعادة</button>
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
                        <th style="width:90px">النوع</th>
                        <th>رقم المرجع</th>
                        <th style="width:90px">مدين</th>
                        <th style="width:90px">دائن</th>
                        <th style="width:90px">الرصيد</th>
                        <th style="width:70px">الحالة</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr><td colspan="9" class="no-data"><i class="bi bi-inbox" style="font-size:32px"></i><br>لا توجد حركات في الفترة المحددة</td></tr>
                    <?php else: 
                        $bal = 0;
                        foreach (array_reverse($transactions) as $t) {
                            $bal += floatval($t['debit']) - floatval($t['credit']);
                        }
                        // Show running balance forward (chronological)
                        $fwd = 0;
                        foreach (array_reverse($transactions) as $i => $t):
                            $fwd += floatval($t['debit']) - floatval($t['credit']);
                            $tc = $t['type'] ?? 'invoice';
                            $typeClass = 'type-invoice';
                            $typeLabel = 'فاتورة';
                            if ($tc === 'payment') { $typeClass = 'type-payment'; $typeLabel = 'دفعة'; }
                            elseif ($tc === 'return') { $typeClass = 'type-return'; $typeLabel = 'مرتجع'; }
                            
                            $st = $t['status'] ?? 'open';
                            $statusClass = '';
                            $statusLabel = $st;
                            if ($st === 'paid' || $st === 'completed') { $statusClass = 'text-success'; $statusLabel = 'مسدد'; }
                            elseif ($st === 'partial') { $statusClass = 'text-warning'; $statusLabel = 'جزئي'; }
                            elseif ($st === 'open') { $statusClass = 'text-danger'; $statusLabel = 'مفتوح'; }
                    ?>
                    <tr>
                        <td><?= count($transactions) - $i ?></td>
                        <td><?= htmlspecialchars($t['trans_date'] ?? '-') ?></td>
                        <td><span class="type-badge <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                        <td><small class="font-monospace"><?= htmlspecialchars($t['ref_number'] ?? '-') ?></small></td>
                        <td class="amount text-danger"><?= floatval($t['debit'] ?? 0) > 0 ? number_format(floatval($t['debit']), 2) : '-' ?></td>
                        <td class="amount text-success"><?= floatval($t['credit'] ?? 0) > 0 ? number_format(floatval($t['credit']), 2) : '-' ?></td>
                        <td class="amount fw-bold"><?= number_format($fwd, 2) ?></td>
                        <td class="<?= $statusClass ?>"><?= $statusLabel ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($t['notes'] ?? '') ?></small></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="total-bar">
        <div class="t-item">إجمالي مدين<strong style="color:var(--red)"><?= number_format($totalDebit, 2) ?></strong></div>
        <div class="t-item">إجمالي دائن<strong style="color:var(--green)"><?= number_format($totalCredit, 2) ?></strong></div>
        <div class="t-item">الرصيد النهائي<strong style="color:var(--primary)"><?= number_format($runningBalForward, 2) ?></strong></div>
    </div>
</div>

</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    btn.classList.add('active');
    // Update URL without reloading
    const url = new URL(window.location);
    if (tabId === 'tab-ledger') { url.searchParams.set('tab', 'ledger'); }
    else { url.searchParams.delete('tab'); }
    window.history.replaceState({}, '', url);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') window.close();
});
</script>

</body>
</html>
