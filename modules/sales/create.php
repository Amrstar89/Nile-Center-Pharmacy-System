<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فاتورة بيع جديدة';

$stores = $db->query("SELECT s.id, s.store_name FROM stores s WHERE s.is_active = 1 ORDER BY s.store_name")->fetchAll();
$customers = $db->query("SELECT id, customer_name, customer_code, phone FROM customers WHERE is_active = 1 ORDER BY customer_name LIMIT 200")->fetchAll();
$users = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();

// Pre-selected customer from URL
$pre_customer_id = intval($_GET['customer_id'] ?? 0);
$pre_customer = null;
if ($pre_customer_id) {
    foreach ($customers as $c) {
        if ($c['id'] == $pre_customer_id) { $pre_customer = $c; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = [];
        if (!empty($_POST['items_data'])) {
            $decoded = json_decode($_POST['items_data'], true);
            if (is_array($decoded)) $items = $decoded;
        }
        if (empty($items)) { throw new Exception('يجب إضافة صنف واحد على الأقل'); }
        
        $db->beginTransaction();
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $store_id = intval($_POST['store_id']);
        $user_id = intval($_POST['user_id'] ?? ($_SESSION['user_id'] ?? 0));
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $invoice_date = $_POST['invoice_date'] ?: date('Y-m-d');
        $discount_pct = floatval($_POST['discount_pct'] ?? 0);
        $discount_val = floatval($_POST['discount_val'] ?? 0);
        $extra_discount_pct = floatval($_POST['extra_discount_pct'] ?? 0);
        $extra_discount_val = floatval($_POST['extra_discount_val'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        $subtotal = 0; $total_vat = 0; $total_cost = 0; $total_profit = 0;
        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 0);
            $price = floatval($item['sell_price'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0);
            $discPct = floatval($item['discount_percent'] ?? 0);
            $vatPct = floatval($item['vat_percent'] ?? 0);
            $afterDisc = $qty * $price * (1 - $discPct/100);
            $subtotal += $afterDisc;
            $total_vat += $afterDisc * ($vatPct/100);
            $total_cost += $qty * $cost;
            $total_profit += ($price - $cost) * $qty;
        }
        
        $grand = $subtotal + $total_vat - $discount_val;
        if ($extra_discount_pct > 0) $grand -= $grand * ($extra_discount_pct/100);
        $grand -= $extra_discount_val;
        if ($grand < 0) $grand = 0;
        
        $status = 'open';
        if ($payment_method === 'cash' || $payment_method === 'visa') {
            $paid_amount = $grand; $status = 'paid';
        } elseif ($payment_method === 'credit') {
            if ($paid_amount >= $grand) $status = 'paid';
            elseif ($paid_amount > 0) $status = 'partial';
        } elseif ($payment_method === 'pending') {
            $paid_amount = 0; $status = 'open';
        }
        $remaining = $grand - $paid_amount;
        
        $year = date('Y');
        $count = $db->query("SELECT COUNT(*) FROM sale_invoices WHERE YEAR(created_at) = $year")->fetchColumn() + 1;
        $inv_number = 'SINV-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
        
        $db->prepare("INSERT INTO sale_invoices (invoice_number, customer_id, store_id, user_id, invoice_date, payment_method, subtotal, discount_pct, discount_val, extra_discount_pct, extra_discount_val, vat_amount, grand_total, paid_amount, remaining_amount, profit_amount, cost_amount, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
           ->execute([$inv_number, $customer_id ?: null, $store_id, $user_id, $invoice_date, $payment_method, $subtotal, $discount_pct, $discount_val, $extra_discount_pct, $extra_discount_val, $total_vat, $grand, $paid_amount, $remaining, $total_profit, $total_cost, $status, $notes, $_SESSION['user_id']]);
        $inv_id = $db->lastInsertId();
        
        $itemStmt = $db->prepare("INSERT INTO sale_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_name, quantity, unit_cost, sell_price, discount_pct, discount_val, vat_pct, vat_val, line_total, profit_val, expiry_date, batch_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $pid = intval($item['product_id'] ?? 0);
            $qty = floatval($item['quantity'] ?? 0);
            $price = floatval($item['sell_price'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0);
            $discPct = floatval($item['discount_percent'] ?? 0);
            $vatPct = floatval($item['vat_percent'] ?? 0);
            $afterDisc = $qty * $price * (1 - $discPct/100);
            $vatVal = $afterDisc * ($vatPct/100);
            $discVal = $qty * $price * ($discPct/100);
            $line = $afterDisc + $vatVal;
            $profit = ($price - $cost) * $qty;
            
            $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'] ?? '', $item['product_code'] ?? '', $item['barcode'] ?? '', $item['unit_name'] ?? 'علبة', $qty, $cost, $price, $discPct, $discVal, $vatPct, $vatVal, $line, $profit, ($item['expiry_date'] ?? null) ?: null, $item['batch_number'] ?? null]);
            
            if ($store_id && $pid) {
                $db->prepare("UPDATE inventory_items SET quantity = GREATEST(quantity - ?, 0), updated_at = NOW() WHERE store_id = ? AND product_id = ?")
                   ->execute([$qty, $store_id, $pid]);
                $db->prepare("INSERT INTO inventory_movements (store_id, product_id, movement_type, reference_type, reference_id, reference_number, quantity, unit_cost, total_cost, notes, created_by) VALUES (?, ?, 'sale', 'sale_invoice', ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$store_id, $pid, $inv_id, $inv_number, $qty, $cost, $qty * $cost, 'فاتورة بيع ' . $inv_number, $_SESSION['user_id']]);
            }
        }
        
        if ($paid_amount > 0 && $customer_id) {
            $payNum = 'CPAY-' . time();
            $db->prepare("INSERT INTO customer_payments (payment_number, customer_id, invoice_id, amount, payment_date, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$payNum, $customer_id, $inv_id, $paid_amount, $invoice_date, $payment_method, 'دفعة من فاتورة ' . $inv_number, $_SESSION['user_id']]);
        }
        
        if ($customer_id && $grand > 0) {
            $lastBal = $db->query("SELECT balance_after FROM customer_transactions WHERE customer_id = $customer_id ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
            $newBal = floatval($lastBal) + ($grand - $paid_amount);
            $db->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, reference_type, reference_id, reference_number, debit, credit, balance_after, notes, created_by, created_at) VALUES (?, 'sale', 'sale_invoice', ?, ?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$customer_id, $inv_id, $inv_number, ($grand - $paid_amount), $paid_amount, $newBal, 'فاتورة بيع ' . $inv_number, $_SESSION['user_id']]);
        }
        
        logActivity('sale_invoice_create', 'sale_invoices', $inv_id);
        $db->commit();
        $_SESSION['success'] = 'تم إنشاء فاتورة البيع ' . $inv_number . ' بنجاح';
        redirect(APP_URL . '/modules/sales/view.php?id=' . $inv_id);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2;--green:#198754;--red:#dc3545;--orange:#ff9800;--blue:#0d6efd;--purple:#6f42c1;}
*{box-sizing:border-box}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow:auto;min-width:1400px}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.2);color:#fff}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}
.invoice-header{background:#fff;padding:12px 20px;border-bottom:2px solid #e9ecef}
.pay-types{display:flex;flex-direction:column;gap:6px;padding:8px;background:#fff;border-radius:8px}
.pay-types .pay-btn{padding:8px 12px;border-radius:6px;border:2px solid #e9ecef;background:#fff;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;transition:all .2s;text-align:right}
.pay-types .pay-btn:hover{border-color:var(--primary);background:#f8f9ff}
.pay-types .pay-btn.active{border-color:var(--primary);background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff}
.pay-types .pay-btn i{font-size:16px}
.info-section{background:#fff;padding:12px 20px;border-bottom:1px solid #e9ecef}
.grand-box{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border:2px solid var(--green);border-radius:12px;padding:15px;text-align:center;margin-bottom:10px}
.grand-box .lbl{color:#555;font-size:13px}
.grand-box .val{color:var(--green);font-size:36px;font-weight:700}
.stats-box{background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:10px;display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.stats-box .st{display:flex;flex-direction:column;align-items:center;min-width:60px}
.stats-box .st label{color:#888;font-size:11px;white-space:nowrap}
.stats-box .st strong{color:#333;font-size:13px}
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,var(--green) 0%,#2e7d32 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.25);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;color:var(--green);transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}
.items-section{overflow-x:auto;padding:10px;background:#fff;margin:0 55px 0 0;position:relative}
.items-section::-webkit-scrollbar{height:10px}
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:2200px}
.items-table th{background:var(--green);color:#fff;padding:6px 4px;text-align:center;font-weight:600;font-size:11px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table td{padding:3px 4px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff;vertical-align:middle}
.items-table tr:hover td{background:#e8f5e9}
.items-table tr.selected td{background:#fff3cd}
.items-table td input,.items-table td select{border:1px solid #ddd;border-radius:4px;padding:2px 4px;font-size:12px;text-align:center;height:28px;width:100%}
.items-table td input:focus{border-color:var(--green);outline:none}
.items-table .product-name{text-align:right;font-weight:600;min-width:170px}
.items-table .num{text-align:center;min-width:50px}
.items-table .row-total{font-weight:700;color:var(--primary);background:#e8f5e9 !important}
.items-table .row-calc{background:#e3f2fd}
.items-table .btn-del{color:var(--red);cursor:pointer;font-size:15px;padding:0 3px}
.items-table .btn-del:hover{transform:scale(1.2)}
.items-table .barcode-w{position:relative;display:flex;align-items:center;min-width:100px}
.items-table .barcode-w input{flex:1;padding-left:28px}
.items-table .barcode-w .btn-f2{position:absolute;left:2px;top:2px;bottom:2px;width:24px;border:none;background:#f0f0f0;border-radius:3px;cursor:pointer;font-size:10px;color:#666;display:flex;align-items:center;justify-content:center}
.items-table .barcode-w .btn-f2:hover{background:var(--green);color:#fff}
.bottom-bar{background:#f8f9fa;border-top:2px solid #ddd;padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .row1{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.bottom-bar .item{display:flex;align-items:center;gap:4px;background:#fff;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px}
.bottom-bar .item label{color:#666;font-size:11px;white-space:nowrap}
.bottom-bar .item strong{color:#333;font-size:13px;white-space:nowrap}
.bottom-bar .grand{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:8px 20px;border-radius:10px;font-size:16px;font-weight:700}
.bottom-bar .item input,.bottom-bar .item select{height:26px;padding:2px 5px;font-size:12px;width:80px;border:1px solid #ccc;border-radius:4px}
.customer-display{background:#e3f2fd;border:1px solid #90caf9;border-radius:6px;padding:6px 12px;display:none}
.customer-display.show{display:flex;align-items:center;gap:10px}
.customer-display .cust-name{font-weight:700;color:#1565c0}
.customer-display .cust-balance{font-size:12px;color:#666}
.d-none{display:none !important}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}body{min-width:auto}}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-cart3"></i> <?= APP_NAME ?> - فاتورة بيع</span>
    <a href="./index.php" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="./returns/" class="menu-item"><i class="bi bi-arrow-return-left"></i> مرتجعات</a>
    <a href="../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?> | <?= date('Y-m-d') ?></div>
</div>
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="newInvoice()" title="جديد (Ctrl+N)"><i class="bi bi-file-earmark-plus"></i></div>
    <div class="btn-icon" onclick="saveInv()" title="حفظ (Ctrl+S)"><i class="bi bi-save"></i></div>
    <div class="btn-icon" onclick="suspendInv()" title="تعليق الفاتورة"><i class="bi bi-pause-circle"></i></div>
    <div class="btn-icon" onclick="openPendingInvoices()" title="فواتير معلقة"><i class="bi bi-clock-history"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="addRow()" title="إضافة صنف (F3)"><i class="bi bi-plus-lg"></i></div>
    <div class="btn-icon" onclick="openF2Search()" title="بحث (F2)"><i class="bi bi-search"></i></div>
    <div class="btn-icon" onclick="deleteRow()" title="حذف سطر"><i class="bi bi-trash"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="ColOrder.openModal()" title="تخصيص الأعمدة"><i class="bi bi-layout-three-columns"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--red)" onclick="window.location='../dashboard/'" title="إغلاق"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px"><i class="bi bi-info-circle"></i> F2=بحث | F3=إضافة | Delete=حذف | Ctrl+S=حفظ</div>
</div>

<form method="POST" id="invForm" autocomplete="off" onsubmit="return beforeSubmit()">
<input type="hidden" name="items_data" id="itemsData" value="">

<div class="invoice-header">
<div class="row g-2">
    <div class="col-lg-3 col-md-4">
        <label class="form-label small text-muted">العميل</label>
        <div class="input-group input-group-sm">
            <input type="text" id="customerSearch" class="form-control" placeholder="اسم العميل أو الكود" oninput="searchCustomers()" style="font-weight:600">
            <button type="button" class="btn btn-outline-secondary" onclick="openCustomerModal()" title="بحث عن عميل"><i class="bi bi-people"></i></button>
        </div>
        <div id="customerResults" class="list-group" style="position:absolute;z-index:100;max-height:200px;overflow-y:auto;display:none;width:100%"></div>
        <div id="customerDisplay" class="customer-display mt-1">
            <span class="cust-name" id="custName"></span>
            <span class="cust-balance" id="custBalance"></span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="clearCustomer()" style="font-size:11px">إلغاء</button>
        </div>
        <input type="hidden" name="customer_id" id="customer_id" value="">
    </div>
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">البائع <span class="text-danger">*</span></label>
        <select name="user_id" id="user_id" class="form-select form-select-sm" required>
            <?php foreach($users as $u){ ?><option value="<?= $u['id'] ?>" <?= $u['id']==($_SESSION['user_id']??0)?'selected':'' ?>><?= $u['full_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">المخزن <span class="text-danger">*</span></label>
        <select name="store_id" id="store_id" class="form-select form-select-sm" required>
            <option value="">-- اختر المخزن --</option>
            <?php foreach($stores as $st){ ?><option value="<?= $st['id'] ?>"><?= $st['store_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">التاريخ</label>
        <input type="date" name="invoice_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">نوع الفاتورة</label>
        <input type="hidden" name="payment_method" id="paymentMethod" value="cash">
        <div class="pay-types">
            <div class="pay-btn active" data-type="cash" onclick="setPaymentType('cash')"><i class="bi bi-cash-coin"></i> كاش</div>
            <div class="pay-btn" data-type="visa" onclick="setPaymentType('visa')"><i class="bi bi-credit-card"></i> فيزا</div>
            <div class="pay-btn" data-type="credit" onclick="setPaymentType('credit')"><i class="bi bi-calendar-check"></i> آجل</div>
            <div class="pay-btn" data-type="pending" onclick="setPaymentType('pending')"><i class="bi bi-pause-circle"></i> معلقة</div>
            <div class="pay-btn" data-type="delivery" onclick="setPaymentType('delivery')"><i class="bi bi-truck"></i> توصيل منزلي</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-3">
        <div class="grand-box">
            <div class="lbl">الصافي المطلوب</div>
            <div class="val" id="grandDisplay">0.00</div>
        </div>
        <div class="d-flex gap-2 justify-content-between" style="font-size:11px">
            <span class="text-success"><i class="bi bi-graph-up-arrow"></i> ربح: <strong id="profitDisplay">0.00</strong></span>
            <span class="text-muted">تكلفة: <strong id="costDisplay">0.00</strong></span>
        </div>
    </div>
</div>
</div>

<div class="info-section">
<div class="stats-box">
    <div class="st"><label>الأصناف</label><strong id="t_items">0</strong></div>
    <div class="st"><label>الإجمالي</label><strong id="t_subtotal">0.00</strong></div>
    <div class="st"><label>خصم %</label><input type="number" id="disc_pct" name="discount_pct" value="0" step="0.01" oninput="recalc()"></div>
    <div class="st"><label>خصم قيمة</label><input type="number" id="disc_val" name="discount_val" value="0" step="0.01" oninput="recalc()"></div>
    <div class="st"><label>خ إضافي %</label><input type="number" id="xdisc_pct" name="extra_discount_pct" value="0" step="0.01" oninput="recalc()"></div>
    <div class="st"><label>م إضافية</label><input type="number" id="xdisc_val" name="extra_discount_val" value="0" step="0.01" oninput="recalc()"></div>
    <div class="st"><label>الضريبة</label><strong id="t_vat">0.00</strong></div>
    <div class="st"><label>المدفوع</label><input type="number" id="paid_amount" name="paid_amount" value="0" step="0.01" oninput="recalc()"></div>
    <div class="st"><label>المتبقي</label><strong id="t_remaining" style="color:var(--red)">0.00</strong></div>
    <div class="ms-auto"><input type="text" name="notes" class="form-control form-control-sm" placeholder="ملاحظات..." style="width:250px"></div>
</div>
</div>

<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="addRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صنف</span></button>
    <button type="button" class="tool-btn" onclick="openF2Search()"><i class="bi bi-search"></i><span class="tooltip">بحث F2</span></button>
    <button type="button" class="tool-btn" onclick="deleteRow()"><i class="bi bi-trash"></i><span class="tooltip">حذف سطر</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <button type="button" class="tool-btn" onclick="saveInv()" style="background:rgba(255,255,255,0.4)"><i class="bi bi-save"></i><span class="tooltip">حفظ Ctrl+S</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="newInvoice()" style="color:#fff3cd"><i class="bi bi-file-earmark-plus"></i><span class="tooltip">جديد</span></button>
    <button type="button" class="tool-btn" onclick="window.location='../dashboard/'" style="color:#ffcdd2"><i class="bi bi-x-lg"></i><span class="tooltip">إغلاق</span></button>
</div>

<div class="items-section">
<table class="items-table" id="itemsTable">
<thead><tr id="headerRow"></tr></thead>
<tbody id="itemsBody"></tbody>
</table>
</div>

<div class="bottom-bar">
<div class="row1">
    <div class="item"><label>تكلفة الفاتورة:</label><strong id="t_cost">0.00</strong></div>
    <div class="item"><label>قيمة ربح الفاتورة:</label><strong id="t_profit_val" style="color:var(--green)">0.00</strong></div>
    <div class="item"><label>نسبة الربح:</label><strong id="t_profit_pct">0%</strong></div>
    <div class="ms-auto grand">الصافي: <span id="t_grand">0.00</span> ج</div>
</div>
</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/product-search.js"></script>
<script src="../js/col-order.js"></script>
<script>
const colDefs=[
    {key:'rownum',label:'#',width:'30px',fixed:true},{key:'print',label:'ط',width:'24px'},
    {key:'barcode',label:'الباركود',width:'100px'},{key:'code',label:'كود الصنف',width:'80px'},
    {key:'name',label:'اسم الصنف',width:'180px'},{key:'unit',label:'الوحدة',width:'60px'},
    {key:'qty',label:'الكمية',width:'60px'},{key:'sell',label:'سعر البيع',width:'65px'},
    {key:'disc_pct',label:'خصم %',width:'55px'},{key:'disc_val',label:'ق الخصم',width:'60px'},
    {key:'after_disc',label:'بعد الخصم',width:'65px'},{key:'vat_pct',label:'ضريبة %',width:'55px'},
    {key:'vat_val',label:'ق الضريبة',width:'60px'},{key:'total',label:'إجمالي',width:'70px'},
    {key:'profit',label:'ن الربح',width:'55px'},{key:'cost',label:'التكلفة',width:'55px'},
    {key:'expiry',label:'الصلاحية',width:'100px'},{key:'stock',label:'الرصيد',width:'55px'},
    {key:'batch',label:'الباتش',width:'55px'},{key:'notes',label:'ملاحظات',width:'80px'},
    {key:'delete',label:'',width:'24px',fixed:true}
];

function setPaymentType(type){
    document.getElementById('paymentMethod').value=type;
    document.querySelectorAll('.pay-btn').forEach(btn=>{
        if(btn.dataset.type===type)btn.classList.add('active');
        else btn.classList.remove('active');
    });
    recalc();
}

let R=0;const allCustomers=<?= json_encode($customers) ?>;
function getUnitOptions(selUnitId){
    let h='<option value="">--</option>';
    allUnits.forEach(u=>{h+='<option value="'+u.id+'"'+(u.id==selUnitId?' selected':'')+'>'+u.unit_name_ar+'</option>';});
    return h;
}

function buildRowCells(id,d){
    const V=function(k){return ColOrder.isVisible(k);};
    const vis=function(k,html){return V(k)?html:'';};
    let h='';
    h+=vis('rownum','<td>'+id+'<input type="hidden" name="items['+id+'][product_id]" id="hip_'+id+'" value="'+(d.product_id||'')+'"></td>');
    h+=vis('print','<td><i class="bi bi-printer print-icon print-off" id="pr_'+id+'" onclick="togglePrint('+id+')"></i></td>');
    h+=vis('barcode','<td><div class="barcode-w"><input type="text" id="bc_'+id+'" class="form-control form-control-sm" value="'+(d.barcode||'')+'" placeholder="باركود" onkeydown="handleEnter(event,'+id+',1)"><button type="button" class="btn-f2" onclick="f2row('+id+')">F2</button></div></td>');
    h+=vis('code','<td><input type="text" id="co_'+id+'" class="form-control form-control-sm" value="'+(d.product_code||'')+'" onkeydown="handleEnter(event,'+id+',2)"></td>');
    h+=vis('name','<td><input type="text" id="nm_'+id+'" class="form-control form-control-sm product-name" value="'+(d.product_name||'')+'" required onkeydown="handleEnter(event,'+id+',3)"></td>');
    h+=vis('unit','<td><select id="un_'+id+'" class="form-select form-select-sm" onkeydown="handleEnter(event,'+id+',4)">'+getUnitOptions(d.unit_id)+'</select></td>');
    h+=vis('qty','<td><input type="number" id="qt_'+id+'" class="form-control form-control-sm num" value="'+(d.quantity||'1')+'" step="0.001" min="0.001" required oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',5)"></td>');
    h+=vis('sell','<td><input type="number" id="sp_'+id+'" class="form-control form-control-sm num" value="'+(d.sell_price||'')+'" step="0.01" min="0" required oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',6)"></td>');
    h+=vis('disc_pct','<td><input type="number" id="dp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="onDiscPct('+id+')" onkeydown="handleEnter(event,'+id+',7)"></td>');
    h+=vis('disc_val','<td><input type="number" id="dv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" oninput="onDiscVal('+id+')" onkeydown="handleEnter(event,'+id+',8)"></td>');
    h+=vis('after_disc','<td><input type="number" id="ad_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>');
    h+=vis('vat_pct','<td><input type="number" id="vp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="onVatPct('+id+')" onkeydown="handleEnter(event,'+id+',9)"></td>');
    h+=vis('vat_val','<td><input type="number" id="vv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" oninput="onVatVal('+id+')" onkeydown="handleEnter(event,'+id+',10)"></td>');
    h+=vis('total','<td><input type="number" id="tl_'+id+'" class="form-control form-control-sm num row-total" value="0" step="0.01" readonly></td>');
    h+=vis('profit','<td><input type="number" id="pf_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>');
    h+=vis('cost','<td><input type="number" id="cs_'+id+'" class="form-control form-control-sm num" value="'+(d.unit_cost||0)+'" step="0.01" readonly style="background:#e9ecef"></td>');
    h+=vis('expiry','<td><input type="month" id="ex_'+id+'" class="form-control form-control-sm" style="font-size:11px" onkeydown="handleEnter(event,'+id+',11)"></td>');
    h+=vis('stock','<td><input type="number" id="st_'+id+'" class="form-control form-control-sm num" value="0" readonly style="background:#e9ecef"></td>');
    h+=vis('batch','<td><input type="text" id="ba_'+id+'" class="form-control form-control-sm num" placeholder="باتش" onkeydown="handleEnter(event,'+id+',12)"></td>');
    h+=vis('notes','<td><input type="text" id="no_'+id+'" class="form-control form-control-sm" placeholder="..." onkeydown="handleEnter(event,'+id+',13)"></td>');
    h+=vis('delete','<td><span class="btn-del" onclick="delRow('+id+')" tabindex="-1"><i class="bi bi-trash-fill"></i></span></td>');
    return h;
}

function addRow(data){
    R++;const id=R;const d=data||{};
    const cells=buildRowCells(id,d);
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend','<tr id="r_'+id+'" data-rid="'+id+'">'+cells+'</tr>');
    const bc=document.getElementById('bc_'+id);const nm=document.getElementById('nm_'+id);
    if(bc)bc.addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2row(id);}});
    if(nm)nm.addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2row(id);}});
    if(d)calc(id);recalc();
    setTimeout(()=>{const el=document.getElementById('bc_'+id);if(el)el.focus();},50);
    return id;
}

function beforeSubmit(){
    const rows=[];
    document.querySelectorAll('#itemsBody tr').forEach(tr=>{
        const id=tr.dataset.rid;if(!id)return;
        const getVal=function(fid,def){const el=document.getElementById(fid+'_'+id);return el?el.value:(def||'');};
        const getNum=function(fid){const el=document.getElementById(fid+'_'+id);return el?parseFloat(el.value)||0:0;};
        const row={
            product_id:document.getElementById('hip_'+id)?.value||'',
            barcode:getVal('bc'),product_code:getVal('co'),product_name:getVal('nm'),
            unit_id:getVal('un'),unit_name:'',quantity:getNum('qt')||1,
            unit_cost:getNum('cs'),sell_price:getNum('sp'),discount_percent:getNum('dp'),
            vat_percent:getNum('vp'),vat_value:getNum('vv'),expiry_date:getVal('ex'),batch_number:getVal('ba')
        };
        if(row.product_name)rows.push(row);
    });
    if(rows.length===0){alert('يجب إضافة صنف واحد على الأقل');return false;}
    document.getElementById('itemsData').value=JSON.stringify(rows);
    return true;
}

function onDiscPct(id){
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const base=qt*sp;
    document.getElementById('dv_'+id).value=(base*dp/100).toFixed(2);
    calc(id);
}
function onDiscVal(id){
    const dv=parseFloat(document.getElementById('dv_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const base=qt*sp;
    document.getElementById('dp_'+id).value=base>0?((dv/base)*100).toFixed(2):0;
    calc(id);
}
function onVatPct(id){
    const vp=parseFloat(document.getElementById('vp_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const afterDisc=qt*sp*(1-dp/100);
    document.getElementById('vv_'+id).value=(afterDisc*vp/100).toFixed(2);
    calc(id);
}
function onVatVal(id){
    const vv=parseFloat(document.getElementById('vv_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const afterDisc=qt*sp*(1-dp/100);
    document.getElementById('vp_'+id).value=afterDisc>0?((vv/afterDisc)*100).toFixed(2):0;
    calc(id);
}
function calc(id){
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const vv=parseFloat(document.getElementById('vv_'+id).value)||0;
    const base=qt*sp;
    const disc=base*(dp/100);
    const afterDisc=base-disc;
    const total=afterDisc+vv;
    const profit=(sp-cs)*qt;
    document.getElementById('dv_'+id).value=disc.toFixed(2);
    document.getElementById('ad_'+id).value=afterDisc.toFixed(2);
    document.getElementById('tl_'+id).value=total.toFixed(2);
    document.getElementById('pf_'+id).value=profit.toFixed(2);
    recalc();
}
function recalc(){
    let n=0,sub=0,tv=0,tc=0,tp=0;
    document.querySelectorAll('#itemsBody tr').forEach(tr=>{
        const id=tr.dataset.rid;
        const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
        const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
        const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
        const vv=parseFloat(document.getElementById('vv_'+id).value)||0;
        const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
        n++;
        const afterDisc=qt*sp*(1-dp/100);
        sub+=afterDisc;tv+=vv;tc+=cs*qt;tp+=(sp-cs)*qt;
    });
    const discPct=parseFloat(document.getElementById('disc_pct').value)||0;
    const discVal=parseFloat(document.getElementById('disc_val').value)||0;
    const xdp=parseFloat(document.getElementById('xdisc_pct').value)||0;
    const xdv=parseFloat(document.getElementById('xdisc_val').value)||0;
    let grand=sub+tv;
    if(discPct>0)grand-=grand*(discPct/100);
    grand-=discVal;
    if(xdp>0)grand-=grand*(xdp/100);
    grand-=xdv;if(grand<0)grand=0;
    
    const payType=document.getElementById('paymentMethod').value;
    let paid=parseFloat(document.getElementById('paid_amount').value)||0;
    if(payType==='cash'||payType==='visa')paid=grand;
    else if(payType==='pending')paid=0;
    const remaining=grand-paid;
    
    document.getElementById('t_items').textContent=n;
    document.getElementById('t_subtotal').textContent=sub.toFixed(2);
    document.getElementById('t_vat').textContent=tv.toFixed(2);
    document.getElementById('t_cost').textContent=tc.toFixed(2);
    document.getElementById('t_profit_val').textContent=tp.toFixed(2);
    document.getElementById('t_profit_pct').textContent=tc>0?((tp/tc)*100).toFixed(1)+'%':'0%';
    document.getElementById('t_grand').textContent=grand.toFixed(2);
    document.getElementById('grandDisplay').textContent=grand.toFixed(2);
    document.getElementById('profitDisplay').textContent=tp.toFixed(2);
    document.getElementById('costDisplay').textContent=tc.toFixed(2);
    document.getElementById('t_remaining').textContent=remaining.toFixed(2);
    if(payType==='cash'||payType==='visa')document.getElementById('paid_amount').value=grand.toFixed(2);
}
const fieldMap=['bc','co','nm','un','qt','sp','dp','dv','ad','vp','vv','ba','no'];
function handleEnter(e,id,fieldIdx){
    if(e.key==='Enter'){
        e.preventDefault();
        if(fieldIdx===fieldMap.length-1){addRow();}
        else{const el=document.getElementById(fieldMap[fieldIdx+1]+'_'+id);if(el)el.focus();}
    }
    if(e.key==='Delete'&&e.ctrlKey){e.preventDefault();delRow(id);}
}
function togglePrint(id){
    const ico=document.getElementById('pr_'+id);
    if(ico.classList.contains('print-off')){ico.classList.remove('print-off');ico.classList.add('print-on');}
    else{ico.classList.remove('print-on');ico.classList.add('print-off');}
}
function delRow(id){const r=document.getElementById('r_'+id);if(r)r.remove();recalc();}
function deleteRow(){const sel=document.querySelector('#itemsBody tr.selected');if(sel)delRow(sel.dataset.rid);}
function newInvoice(){if(confirm('فاتورة جديدة؟ سيتم مسح البيانات الحالية')){document.getElementById('invForm').reset();document.getElementById('itemsBody').innerHTML='';R=0;document.getElementById('customerDisplay').classList.remove('show');setPaymentType('cash');recalc();}}
function suspendInv(){alert('سيتم حفظ الفاتورة كمعلقة');}
function openPendingInvoices(){alert('فواتير معلقة - قريباً');}

function searchCustomers(){
    const q=document.getElementById('customerSearch').value.trim().toLowerCase();
    const res=document.getElementById('customerResults');
    if(q.length<1){res.style.display='none';return;}
    const matches=allCustomers.filter(c=>(c.customer_name&&c.customer_name.toLowerCase().includes(q))||(c.customer_code&&c.customer_code.toLowerCase().includes(q))||(c.phone&&c.phone.includes(q)));
    if(matches.length===0){res.style.display='none';return;}
    let h='';
    matches.forEach(c=>{h+='<a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer('+c.id+',\''+c.customer_name+'\');return false;"><strong>'+c.customer_name+'</strong> <span class="text-muted">('+c.customer_code+')</span></a>';});
    res.innerHTML=h;res.style.display='block';
}
function selectCustomer(id,name){
    document.getElementById('customer_id').value=id;
    document.getElementById('custName').textContent=name;
    document.getElementById('customerDisplay').classList.add('show');
    document.getElementById('customerResults').style.display='none';
    document.getElementById('customerSearch').value='';
}
function clearCustomer(){document.getElementById('customer_id').value='';document.getElementById('customerDisplay').classList.remove('show');}
function openCustomerModal(){const q=document.getElementById('customerSearch').value;window.open('../customers/index.php?search='+q,'_blank');}

function f2row(id){
    const sid=document.getElementById('store_id').value;
    if(!sid){alert('اختر المخزن أولاً');return;}
    ProductSearch.open({storeId:parseInt(sid),onSelect:function(p){fill(id,p);}});
}
function openF2Search(){
    const sid=document.getElementById('store_id').value;
    if(!sid){alert('اختر المخزن أولاً');document.getElementById('store_id').focus();return;}
    ProductSearch.open({storeId:parseInt(sid),onSelect:function(p){addRow(p);}});
}
function fill(id,p){
    document.getElementById('hip_'+id).value=p.product_id||p.id||'';
    document.getElementById('nm_'+id).value=p.product_name||'';
    document.getElementById('co_'+id).value=p.product_code||'';
    document.getElementById('bc_'+id).value=p.barcode||'';
    document.getElementById('cs_'+id).value=p.unit_cost||0;
    document.getElementById('sp_'+id).value=p.sell_price||0;
    document.getElementById('st_'+id).value=p.current_stock||0;
    if(p.unit_id)document.getElementById('un_'+id).value=p.unit_id;
    if(p.units&&p.units.length>0){
        const sel=document.getElementById('un_'+id);sel.innerHTML='';
        p.units.forEach(u=>{const opt=document.createElement('option');opt.value=u.id;opt.textContent=u.name;if(u.is_default)opt.selected=true;sel.appendChild(opt);});
    }
    calc(id);
}
function saveInv(){if(beforeSubmit())document.getElementById('invForm').submit();}
document.addEventListener('keydown',function(e){
    if(e.ctrlKey&&e.key==='s'){e.preventDefault();saveInv();}
    if(e.ctrlKey&&e.key==='n'){e.preventDefault();newInvoice();}
    if(e.key==='F3'){e.preventDefault();addRow();}
});
ColOrder.init(colDefs,'sale_invoice_cols','headerRow');
setPaymentType('cash');addRow();
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
<?php if($pre_customer): ?>selectCustomer(<?= $pre_customer['id'] ?>,'<?= addslashes($pre_customer['customer_name']) ?>');<?php endif; ?>
</script>
</body>
</html>