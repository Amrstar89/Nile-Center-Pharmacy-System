<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فاتورة شراء جديدة';

$stores = $db->query("SELECT s.id, s.store_name, s.branch_id, b.branch_name FROM stores s LEFT JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY s.store_name")->fetchAll();
$suppliers = $db->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        if (empty($items)) { throw new Exception('يجب إضافة صنف واحد على الأقل'); }
        $db->beginTransaction();
        $supplier_id = intval($_POST['supplier_id']);
        $supplier_inv_no = trim($_POST['supplier_invoice_no'] ?? '');
        if ($supplier_inv_no !== '') {
            $check = $db->prepare("SELECT id FROM purchase_invoices WHERE supplier_id = ? AND supplier_invoice_no = ?");
            $check->execute([$supplier_id, $supplier_inv_no]);
            if ($check->fetch()) { throw new Exception('رقم فاتورة المورد ' . $supplier_inv_no . ' مسجل مسبقاً'); }
        }
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_invoices WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $inv_number = 'PINV-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $store_id = intval($_POST['store_id'] ?? 0);
        $invoice_date = $_POST['invoice_date'] ?: date('Y-m-d');
        $due_date = $_POST['due_date'] ?: null;
        $payment_method = $_POST['payment_method'] ?? 'credit';
        $discount_type = $_POST['discount_type'] ?: null;
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $vat_percent = floatval($_POST['vat_percent'] ?? 0);
        $extra_discount_pct = floatval($_POST['extra_discount_pct'] ?? 0);
        $extra_discount_val = floatval($_POST['extra_discount_val'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $subtotal = 0; $total_vat = 0;
        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 0); $bonus = floatval($item['bonus'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0); $discPct = floatval($item['discount_percent'] ?? 0);
            $itemVatPct = floatval($item['vat_percent'] ?? 0); $totalQty = $qty + $bonus;
            $afterDisc = $totalQty * $cost * (1 - $discPct/100); $subtotal += $afterDisc;
            $total_vat += $afterDisc * ($itemVatPct/100);
        }
        $discount = $discount_type === 'percentage' ? $subtotal * ($discount_value/100) : $discount_value;
        $afterDisc = $subtotal - $discount; $grand = $afterDisc + $total_vat - $extra_discount_val;
        if ($extra_discount_pct > 0) { $grand = $grand * (1 - $extra_discount_pct/100); }
        $status = $paid_amount >= $grand ? 'paid' : ($paid_amount > 0 ? 'partial' : 'open');
        if ($payment_method === 'credit') $status = 'open';
        $db->prepare("INSERT INTO purchase_invoices (invoice_number, supplier_id, store_id, invoice_date, due_date, status, subtotal, discount_type, discount_value, vat_percent, vat_amount, grand_total, paid_amount, extra_discount_pct, extra_discount_val, payment_method, supplier_invoice_no, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
           ->execute([$inv_number, $supplier_id, $store_id ?: null, $invoice_date, $due_date, $status, $subtotal, $discount_type, $discount_value, $vat_percent, $total_vat, $grand, $paid_amount, $extra_discount_pct, $extra_discount_val, $payment_method, $supplier_inv_no ?: null, $notes, $_SESSION['user_id']]);
        $inv_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_id, unit_name, quantity, bonus_qty, unit_cost, sell_price, discount_percent, vat_percent, expiry_date, batch_number, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $pid = intval($item['product_id'] ?? 0); $qty = floatval($item['quantity'] ?? 0); $bonus = floatval($item['bonus'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0); $discPct = floatval($item['discount_percent'] ?? 0); $totalQty = $qty + $bonus; $line = $totalQty * $cost * (1 - $discPct/100);
            $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'] ?? '', $item['product_code'] ?? null, $item['barcode'] ?? null, intval($item['unit_id'] ?? 0) ?: null, $item['unit_name'] ?? 'علبة', $qty, $bonus, $cost, floatval($item['sell_price'] ?? 0), $discPct, floatval($item['vat_percent'] ?? 0), ($item['expiry_date'] ?? null) ?: null, $item['batch_number'] ?? null, $line]);
            if ($store_id && $pid) {
                $existing = $db->prepare("SELECT id FROM inventory_items WHERE store_id = ? AND product_id = ?");
                $existing->execute([$store_id, $pid]); $existingData = $existing->fetch();
                if ($existingData) { $db->prepare("UPDATE inventory_items SET quantity = quantity + ?, unit_cost = ?, updated_at = NOW() WHERE id = ?")->execute([$qty + $bonus, $cost, $existingData['id']]); }
                else { $db->prepare("INSERT INTO inventory_items (store_id, product_id, quantity, unit_cost, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())")->execute([$store_id, $pid, $qty + $bonus, $cost]); }
            }
        }
        if ($paid_amount > 0) { $db->prepare("INSERT INTO supplier_payments (payment_number, supplier_id, invoice_id, amount, payment_date, payment_method, created_by) VALUES (?, ?, ?, ?, NOW(), 'cash', ?)")->execute(['PAY' . time(), $supplier_id, $inv_id, $paid_amount, $_SESSION['user_id']]); }
        logActivity('purchase_invoice_create', 'purchase_invoices', $inv_id); $db->commit();
        $_SESSION['success'] = 'تم إنشاء فاتورة الشراء ' . $inv_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/invoices/view.php?id=' . $inv_id);
    } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
}

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
:root{--primary:#667eea;--secondary:#764ba2;--sidebar-bg:#1a1a2e;--green:#198754;--red:#dc3545;--orange:#ff9800;}
*{box-sizing:border-box}body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:var(--sidebar-bg);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.15);color:#fff}
.top-header .menu-item i{margin-left:6px}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}
.invoice-header{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
.invoice-header .row{align-items:end}
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,#ffc107 0%,#ff9800 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.3);color:#333;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}
.items-section{overflow-x:auto;overflow-y:visible;padding:10px;background:#fff;margin:0 55px 0 0;position:relative}
.items-section::-webkit-scrollbar{height:10px}
.items-section::-webkit-scrollbar-track{background:#f0f0f0}
.items-section::-webkit-scrollbar-thumb{background:#ccc;border-radius:5px}
.items-section::-webkit-scrollbar-thumb:hover{background:#999}
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:2200px}
.items-table th{background:var(--green);color:#fff;padding:6px 4px;text-align:center;font-weight:600;font-size:11px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table th:first-child{width:30px}
.items-table td{padding:3px 4px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff;vertical-align:middle}
.items-table tr:hover td{background:#e3f2fd}
.items-table td input,.items-table td select{border:1px solid #ddd;border-radius:4px;padding:2px 4px;font-size:12px;text-align:center;height:28px;width:100%}
.items-table td input:focus{border-color:var(--primary);outline:none}
.items-table .product-name{text-align:right;font-weight:600;min-width:170px}
.items-table .num{text-align:center;min-width:50px}
.items-table .row-total{font-weight:700;color:var(--primary);background:#e8f5e9 !important}
.items-table .row-calc{background:#e3f2fd}
.items-table .btn-del{color:var(--red);cursor:pointer;font-size:15px;padding:0 3px}
.items-table .btn-del:hover{transform:scale(1.2)}
.items-table .barcode-w{position:relative;display:flex;align-items:center;min-width:100px}
.items-table .barcode-w input{flex:1;padding-left:28px}
.items-table .barcode-w .btn-f2{position:absolute;left:2px;top:2px;bottom:2px;width:24px;border:none;background:#f0f0f0;border-radius:3px;cursor:pointer;font-size:10px;color:#666;display:flex;align-items:center;justify-content:center}
.items-table .barcode-w .btn-f2:hover{background:var(--primary);color:#fff}
.items-table .print-icon{font-size:16px;cursor:pointer}
.items-table .print-on{color:var(--green)}
.items-table .print-off{color:#ddd}
.bottom-bar{background:#f8f9fa;border-top:2px solid #ddd;padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .row1{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.bottom-bar .item{display:flex;align-items:center;gap:4px;background:#fff;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px}
.bottom-bar .item label{color:#666;font-size:11px;white-space:nowrap}
.bottom-bar .item strong{color:#333;font-size:13px;white-space:nowrap}
.bottom-bar .item.ro{background:#e3f2fd;border-color:#90caf9}
.bottom-bar .item.ro strong{color:#1565c0}
.bottom-bar .grand{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:8px 20px;border-radius:10px;font-size:16px;font-weight:700}
.bottom-bar .item input,.bottom-bar .item select{height:26px;padding:2px 5px;font-size:12px;width:80px;border:1px solid #ccc;border-radius:4px}
.supplier-section{background:#fff3cd;padding:10px 20px;border-top:1px solid #ffc107}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}}
</style>
</head>
<body>
<!-- Top Header -->
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-capsule"></i> <?= APP_NAME ?></span>
    <a href="../" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="../invoices/" class="menu-item"><i class="bi bi-receipt"></i> فواتير الشراء</a>
    <a href="../orders/" class="menu-item"><i class="bi bi-file-earmark-text"></i> أوامر الشراء</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?> | <?= arabicDate(date('Y-m-d')) ?></div>
</div>
<!-- Sub Menu Toolbar -->
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="addRow()" title="إضافة صنف (F3)"><i class="bi bi-plus-lg"></i></div>
    <div class="btn-icon" onclick="openF2Search()" title="بحث F2"><i class="bi bi-search"></i></div>
    <div class="btn-icon" onclick="clearAll()" title="مسح الكل"><i class="bi bi-trash"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="saveInv()" title="حفظ Ctrl+S"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--red)" onclick="window.location='../'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px"><i class="bi bi-info-circle"></i> F2=بحث | F3=إضافة صف | Ctrl+S=حفظ</div>
</div>
<!-- Invoice Header -->
<div class="invoice-header">
<form method="POST" id="invForm">
<div class="row g-2">
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">المورد <span class="text-danger">*</span></label>
        <select name="supplier_id" id="supplier_id" class="form-select form-select-sm" required onchange="onSupplierChange()">
            <option value="">-- اختر المورد --</option>
            <?php foreach($suppliers as $s){ ?><option value="<?= $s['id'] ?>" data-code="<?= $s['supplier_code'] ?>"><?= $s['supplier_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">كود المورد</label>
        <input type="text" id="supplier_code_display" class="form-control form-control-sm" readonly style="background:#e9ecef;font-weight:700;text-align:center">
    </div>
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">رقم فاتورة المورد <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <input type="text" name="supplier_invoice_no" id="supInvNo" class="form-control" required placeholder="مطلوب - فريد للمورد">
            <button type="button" class="btn btn-outline-secondary" onclick="autoGenInvNo()" title="توليد تلقائي"><i class="bi bi-magic"></i></button>
        </div>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">تاريخ الفاتورة</label>
        <input type="date" name="invoice_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">طريقة الدفع</label>
        <select name="payment_method" id="payMethod" class="form-select form-select-sm" onchange="onPayMethodChange()">
            <option value="credit">آجل</option>
            <option value="cash">كاش</option>
            <option value="under_collection">تحت التصريف</option>
            <option value="bank_transfer">تحويل بنكي</option>
            <option value="wallet_transfer">تحويل محفظة</option>
        </select>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">الفرع</label>
        <select id="branchSelect" class="form-select form-select-sm" onchange="filterStores()">
            <option value="">الكل</option>
            <?php foreach($branches as $b){ ?><option value="<?= $b['id'] ?>"><?= $b['branch_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">المخزن <span class="text-danger">*</span></label>
        <select name="store_id" id="store_id" class="form-select form-select-sm" required>
            <option value="">-- --</option>
            <?php foreach($stores as $st){ ?><option value="<?= $st['id'] ?>" data-branch="<?= $st['branch_id'] ?? '' ?>"><?= $st['store_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-2" id="dueDateWrap">
        <label class="form-label small text-muted">تاريخ الاستحقاق</label>
        <input type="date" name="due_date" class="form-control form-control-sm">
    </div>
    <div class="col-lg-1 col-md-2 d-none" id="paidNowWrap">
        <label class="form-label small text-muted">مدفوع الآن</label>
        <input type="number" name="paid_amount" id="paidAmount" class="form-control form-control-sm" value="0" step="0.01" oninput="onPaidInput()">
    </div>
</div>
</div>
<!-- Toolbar Right -->
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="addRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صنف</span></button>
    <button type="button" class="tool-btn" onclick="openF2Search()"><i class="bi bi-search"></i><span class="tooltip">بحث F2</span></button>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="clearAll()" style="color:var(--red)"><i class="bi bi-trash"></i><span class="tooltip">مسح</span></button>
    <button type="button" class="tool-btn" onclick="window.location='../'" style="color:var(--red)"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
</div>
<!-- Items Table -->
<div class="items-section">
<table class="items-table" id="itemsTable">
<thead>
<tr>
    <th>#</th>
    <th style="min-width:24px" title="طباعة باركود">ط</th>
    <th style="min-width:100px">الباركود</th>
    <th style="min-width:80px">كود الصنف</th>
    <th style="min-width:170px">اسم الصنف</th>
    <th style="min-width:60px">الوحدة</th>
    <th style="min-width:60px">الكمية</th>
    <th style="min-width:50px">بونص</th>
    <th style="min-width:110px">الصلاحية</th>
    <th style="min-width:55px">خصم %</th>
    <th style="min-width:60px">سعر الشراء</th>
    <th style="min-width:55px">ضريبة %</th>
    <th style="min-width:60px">ق الضريبة</th>
    <th style="min-width:75px">إجمالي التكلفة</th>
    <th style="min-width:60px">خصم إضافي</th>
    <th style="min-width:60px">سعر البيع</th>
    <th style="min-width:55px">ربح ص</th>
    <th style="min-width:55px">ربح %</th>
    <th style="min-width:90px">الشركة</th>
    <th style="min-width:70px">الموقع</th>
    <th style="min-width:55px">الباتش</th>
    <th style="min-width:24px"></th>
</tr>
</thead>
<tbody id="itemsBody"></tbody>
</table>
</div>
<!-- Bottom Summary -->
<div class="bottom-bar">
<div class="row1">
    <div class="item ro"><label>الأصناف:</label><strong id="t_items">0</strong></div>
    <div class="item ro"><label>الباركود:</label><strong id="t_barcodes">0</strong></div>
    <div class="item ro"><label>تكلفة:</label><strong id="t_cost">0.00</strong></div>
    <div class="item ro"><label>الضريبة:</label><strong id="t_vat">0.00</strong></div>
    <div class="item ro"><label>البيع:</label><strong id="t_sell">0.00</strong></div>
    <div class="item ro"><label>ربح ص:</label><strong id="t_profit_val">0.00</strong></div>
    <div class="item ro"><label>ربح %:</label><strong id="t_profit_pct">0%</strong></div>
    <div class="item"><label>خصم إضافي %:</label><input type="number" id="xdisc_pct" value="0" step="0.01" oninput="recalc()"></div>
    <div class="item"><label>خصم إضافي ق:</label><input type="number" name="extra_discount_val" id="xdisc_val" value="0" step="0.01" oninput="recalc()"></div>
    <div class="item"><label>مدفوع الآن:</label><input type="number" name="paid_amount" id="paid_amount" value="0" step="0.01" oninput="onPaidInput()"></div>
    <div class="ms-auto grand">الصافي: <span id="t_grand">0.00</span> ج</div>
</div>
</div>
<!-- Supplier & Save Section -->
<div class="supplier-section">
<div class="row w-100 align-items-center g-2">
    <div class="col-md-8"><input type="text" name="notes" class="form-control form-control-sm" placeholder="ملاحظات..."></div>
    <div class="col-md-2 text-start"><button type="submit" class="btn btn-success btn-lg px-4"><i class="bi bi-save"></i> حفظ الفاتورة</button></div>
</div>
</div>
</form>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../js/product-search.js"></script>
<script>
let R=0; const units=<?= json_encode($units) ?>; const suppliers=<?= json_encode($suppliers) ?>;
function onSupplierChange(){
    const sel=document.getElementById('supplier_id');
    const opt=sel.options[sel.selectedIndex];
    document.getElementById('supplier_code_display').value=opt.dataset.code||'';
    autoGenInvNo();
}
function autoGenInvNo(){
    const sid=document.getElementById('supplier_id').value;
    if(!sid)return;
    const s=suppliers.find(x=>x.id==sid);
    if(s) document.getElementById('supInvNo').value=s.supplier_code+'-'+Date.now().toString().slice(-4);
}
function onPayMethodChange(){
    const m=document.getElementById('payMethod').value;
    document.getElementById('dueDateWrap').classList.toggle('d-none',m!=='credit');
    document.getElementById('paidNowWrap').classList.toggle('d-none',m==='credit');
}
function filterStores(){
    const bid=document.getElementById('branchSelect').value;
    const sel=document.getElementById('store_id');
    for(let i=0;i<sel.options.length;i++){
        const o=sel.options[i]; if(!o.value)continue;
        o.style.display=!bid||o.dataset.branch===bid?'':'none';
    }
}
function onPaidInput(){
    const v=parseFloat(document.getElementById('paid_amount').value)||0;
    const pm=document.getElementById('payMethod');
    if(v>0&&pm.value==='cash') pm.value='credit';
}
function addRow(data){
    R++;
    const id=R;
    let uo='<option value="">--</option>';
    units.forEach(u=>uo+='<option value="'+u.id+'">'+u.unit_name_ar+'</option>');
    const d=data||{};
    const tr=document.createElement('tr');
    tr.id='r_'+id; tr.dataset.rid=id;
    tr.innerHTML=
        '<td>'+id+'<input type="hidden" name="items['+id+'][product_id]" value="'+(d.product_id||'')+'"></td>'+
        '<td><i class="bi bi-printer print-icon print-off" id="pr_'+id+'" onclick="togglePrint('+id+')"></i><input type="hidden" name="items['+id+'][has_barcode_print]" id="prv_'+id+'" value="0"></td>'+
        '<td><div class="barcode-w"><input type="text" name="items['+id+'][barcode]" id="bc_'+id+'" class="form-control form-control-sm" value="'+(d.barcode||'')+'" placeholder="باركود"><button type="button" class="btn-f2" onclick="f2row('+id+')">F2</button></div></td>'+
        '<td><input type="text" name="items['+id+'][product_code]" id="co_'+id+'" class="form-control form-control-sm" value="'+(d.product_code||'')+'"></td>'+
        '<td><input type="text" name="items['+id+'][product_name]" id="nm_'+id+'" class="form-control form-control-sm product-name" value="'+(d.product_name||'')+'" required></td>'+
        '<td><select name="items['+id+'][unit_id]" id="un_'+id+'" class="form-select form-select-sm">'+uo+'</select><input type="hidden" name="items['+id+'][unit_name]" id="unm_'+id+'" value=""></td>'+
        '<td><input type="number" name="items['+id+'][quantity]" id="qt_'+id+'" class="form-control form-control-sm num" value="'+(d.quantity||'1')+'" step="0.001" min="0.001" required oninput="calc('+id+')"></td>'+
        '<td><input type="number" name="items['+id+'][bonus]" id="bn_'+id+'" class="form-control form-control-sm num" value="0" step="0.001" min="0" oninput="calc('+id+')"></td>'+
        '<td><input type="month" name="items['+id+'][expiry_date]" id="ex_'+id+'" class="form-control form-control-sm" style="font-size:11px"></td>'+
        '<td><input type="number" name="items['+id+'][discount_percent]" id="dp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="calc('+id+')"></td>'+
        '<td><input type="number" name="items['+id+'][unit_cost]" id="cs_'+id+'" class="form-control form-control-sm num" value="'+(d.unit_cost||'')+'" step="0.01" min="0" required oninput="calc('+id+')"></td>'+
        '<td><input type="number" name="items['+id+'][vat_percent]" id="vp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="calc('+id+')"></td>'+
        '<td><input type="number" id="vv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>'+
        '<td><input type="number" id="tl_'+id+'" class="form-control form-control-sm num row-total" value="0" step="0.01" readonly></td>'+
        '<td><input type="number" name="items['+id+'][extra_discount]" id="xd_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="calc('+id+')"></td>'+
        '<td><input type="number" name="items['+id+'][sell_price]" id="sp_'+id+'" class="form-control form-control-sm num" value="'+(d.sell_price||'')+'" step="0.01" min="0" oninput="calc('+id+')"></td>'+
        '<td><input type="number" id="pv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>'+
        '<td><input type="number" id="pp_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>'+
        '<td><input type="text" id="cy_'+id+'" class="form-control form-control-sm" value="'+(d.company_name||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>'+
        '<td><input type="text" id="lc_'+id+'" class="form-control form-control-sm" value="'+(d.location||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>'+
        '<td><input type="text" name="items['+id+'][batch_number]" class="form-control form-control-sm num" placeholder="باتش"></td>'+
        '<td><span class="btn-del" onclick="delRow('+id+')"><i class="bi bi-trash-fill"></i></span></td>';
    document.getElementById('itemsBody').appendChild(tr);
    document.getElementById('bc_'+id).addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2row(id);}});
    document.getElementById('nm_'+id).addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2row(id);}});
    if(data) calc(id);
    recalc();
    return id;
}
function togglePrint(id){
    const ico=document.getElementById('pr_'+id);
    const inp=document.getElementById('prv_'+id);
    if(inp.value==='1'){inp.value='0';ico.classList.remove('print-on');ico.classList.add('print-off');}
    else{inp.value='1';ico.classList.remove('print-off');ico.classList.add('print-on');}
}
function delRow(id){const r=document.getElementById('r_'+id);if(r)r.remove();recalc();}
function clearAll(){if(!confirm('مسح كل الأصناف؟'))return;document.getElementById('itemsBody').innerHTML='';R=0;recalc();}
function calc(id){
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const vp=parseFloat(document.getElementById('vp_'+id).value)||0;
    const xd=parseFloat(document.getElementById('xd_'+id).value)||0;
    const tq=qt+bn;
    const base=tq*cs;
    const disc=base*(dp/100);
    const afterDisc=base-disc;
    const vatVal=afterDisc*(vp/100);
    const totalCost=afterDisc+vatVal-xd;
    const profitVal=(sp-cs)*qt;
    const profitPct=cs>0?(((sp-cs)/cs)*100):0;
    document.getElementById('vv_'+id).value=vatVal.toFixed(2);
    document.getElementById('tl_'+id).value=totalCost.toFixed(2);
    document.getElementById('pv_'+id).value=profitVal.toFixed(2);
    document.getElementById('pp_'+id).value=profitPct.toFixed(1);
    recalc();
}
function recalc(){
    let n=0,bc=0,tc=0,tv=0,ts=0,tp=0;
    document.querySelectorAll('#itemsBody tr').forEach(tr=>{
        const id=tr.dataset.rid;
        const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
        const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
        const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
        const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
        n++;
        if(document.getElementById('bc_'+id).value)bc++;
        tc+=cs*(qt+bn);
        tv+=(parseFloat(document.getElementById('vv_'+id).value)||0);
        ts+=sp*qt;
        tp+=(parseFloat(document.getElementById('pv_'+id).value)||0);
    });
    const xdp=parseFloat(document.getElementById('xdisc_pct').value)||0;
    const xdv=parseFloat(document.getElementById('xdisc_val').value)||0;
    let grand=tc+tv;
    if(xdp>0) grand-=grand*(xdp/100);
    grand-=xdv;
    if(grand<0)grand=0;
    document.getElementById('t_items').textContent=n;
    document.getElementById('t_barcodes').textContent=bc;
    document.getElementById('t_cost').textContent=tc.toFixed(2);
    document.getElementById('t_vat').textContent=tv.toFixed(2);
    document.getElementById('t_sell').textContent=ts.toFixed(2);
    document.getElementById('t_profit_val').textContent=tp.toFixed(2);
    document.getElementById('t_profit_pct').textContent=tc>0?((tp/tc)*100).toFixed(1)+'%':'0%';
    document.getElementById('t_grand').textContent=grand.toFixed(2);
}
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
    document.getElementById('nm_'+id).value=p.product_name||'';
    document.getElementById('co_'+id).value=p.product_code||'';
    document.getElementById('bc_'+id).value=p.barcode||'';
    document.getElementById('cs_'+id).value=p.unit_cost||0;
    document.getElementById('sp_'+id).value=p.sell_price||0;
    document.getElementById('cy_'+id).value=p.company_name||'';
    document.getElementById('lc_'+id).value=p.location||'';
    const pi=document.querySelector('#r_'+id+' input[name*="[product_id]"]');
    if(pi)pi.value=p.product_id||p.id||'';
    calc(id);
}
function saveInv(){document.getElementById('invForm').submit();}
document.addEventListener('keydown',function(e){
    if(e.ctrlKey&&e.key==='s'){e.preventDefault();saveInv();}
    if(e.key==='F3'){e.preventDefault();addRow();}
    if(e.key==='F2'){const a=document.activeElement;const r=a.closest('tr');if(r&&r.dataset.rid){e.preventDefault();f2row(r.dataset.rid);}}
});
addRow();
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
</script>
</body>
</html>