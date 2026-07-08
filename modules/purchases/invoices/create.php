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
        $items = [];
        if (!empty($_POST['items_data'])) {
            $decoded = json_decode($_POST['items_data'], true);
            if (is_array($decoded)) $items = $decoded;
        }
        if (empty($items)) {
            $items = $_POST['items'] ?? [];
        }
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
        $extra_discount_pct = floatval($_POST['extra_discount_pct'] ?? 0);
        $extra_discount_val = floatval($_POST['extra_discount_val'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        $deferred_amount = floatval($_POST['deferred_amount'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $subtotal = 0; $total_vat = 0;
        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 0); $bonus = floatval($item['bonus'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0); $discPct = floatval($item['discount_percent'] ?? 0);
            $itemVatPct = floatval($item['vat_percent'] ?? 0); $itemVatVal = floatval($item['vat_value'] ?? 0);
            $totalQty = $qty + $bonus;
            $afterDisc = $totalQty * $cost * (1 - $discPct/100); $subtotal += $afterDisc;
            if ($itemVatVal > 0) { $total_vat += $itemVatVal; }
            else { $total_vat += $afterDisc * ($itemVatPct/100); }
        }
        $grand = $subtotal + $total_vat - $extra_discount_val;
        if ($extra_discount_pct > 0) { $grand = $grand * (1 - $extra_discount_pct/100); }
        
        $status = 'open';
        if ($payment_method === 'cash' || $payment_method === 'bank_transfer' || $payment_method === 'wallet_transfer') {
            $paid_amount = $grand - $deferred_amount;
            if ($deferred_amount <= 0) { $status = 'paid'; }
            else { $status = 'partial'; }
        } elseif ($payment_method === 'credit' || $payment_method === 'under_collection') {
            if ($paid_amount >= $grand) { $status = 'paid'; }
            elseif ($paid_amount > 0) { $status = 'partial'; }
            else { $status = 'open'; }
        }
        $db->prepare("INSERT INTO purchase_invoices (invoice_number, supplier_id, store_id, invoice_date, due_date, status, subtotal, vat_percent, vat_amount, grand_total, paid_amount, extra_discount_pct, extra_discount_val, payment_method, supplier_invoice_no, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
           ->execute([$inv_number, $supplier_id, $store_id ?: null, $invoice_date, $due_date, $status, $subtotal, 0, $total_vat, $grand, $paid_amount, $extra_discount_pct, $extra_discount_val, $payment_method, $supplier_inv_no ?: null, $notes, $_SESSION['user_id']]);
        $inv_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_id, unit_name, quantity, bonus_qty, unit_cost, sell_price, discount_percent, vat_percent, vat_value, expiry_date, batch_number, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $pid = intval($item['product_id'] ?? 0); $qty = floatval($item['quantity'] ?? 0); $bonus = floatval($item['bonus'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0); $discPct = floatval($item['discount_percent'] ?? 0); $totalQty = $qty + $bonus; $line = $totalQty * $cost * (1 - $discPct/100);
            $itemVatPct = floatval($item['vat_percent'] ?? 0); $itemVatVal = floatval($item['vat_value'] ?? 0);
            $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'] ?? '', $item['product_code'] ?? null, $item['barcode'] ?? null, intval($item['unit_id'] ?? 0) ?: null, $item['unit_name'] ?? 'علبة', $qty, $bonus, $cost, floatval($item['sell_price'] ?? 0), $discPct, $itemVatPct, $itemVatVal, ($item['expiry_date'] ?? null) ?: null, $item['batch_number'] ?? null, $line]);
            if ($store_id && $pid) {
                $existing = $db->prepare("SELECT id FROM inventory_items WHERE store_id = ? AND product_id = ?");
                $existing->execute([$store_id, $pid]); $existingData = $existing->fetch();
                if ($existingData) { $db->prepare("UPDATE inventory_items SET quantity = quantity + ?, unit_cost = ?, updated_at = NOW() WHERE id = ?")->execute([$qty + $bonus, $cost, $existingData['id']]); }
                else { $db->prepare("INSERT INTO inventory_items (store_id, product_id, quantity, unit_cost, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())")->execute([$store_id, $pid, $qty + $bonus, $cost]); }
                $movementQty = $qty + $bonus;
                $db->prepare("INSERT INTO inventory_movements (store_id, product_id, movement_type, reference_type, reference_id, reference_number, quantity, unit_cost, total_cost, notes, created_by) VALUES (?, ?, 'purchase', 'purchase_invoice', ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$store_id, $pid, $inv_id, $inv_number, $movementQty, $cost, $movementQty * $cost, 'فاتورة شراء ' . $inv_number, $_SESSION['user_id']]);
            }
        }
        if ($paid_amount > 0) {
            $db->prepare("INSERT INTO supplier_payments (payment_number, supplier_id, invoice_id, amount, payment_date, payment_method, notes, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)")
               ->execute(['PAY' . time(), $supplier_id, $inv_id, $paid_amount, $payment_method, 'دفعة من فاتورة ' . $inv_number, $_SESSION['user_id']]);
        }
        if ($status !== 'paid' && ($grand - $paid_amount) > 0) {
            $deferred = $grand - $paid_amount;
            $lastBalance = $db->query("SELECT balance_after FROM supplier_transactions WHERE supplier_id = $supplier_id ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
            $newBalance = floatval($lastBalance) + $deferred;
            $db->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, reference_type, reference_id, reference_number, debit, credit, balance_after, notes, created_by, created_at) VALUES (?, 'purchase', 'purchase_invoice', ?, ?, ?, 0, ?, ?, ?, NOW())")
               ->execute([$supplier_id, $inv_id, $inv_number, $deferred, $newBalance, 'مبلغ مؤجل من فاتورة ' . $inv_number, $_SESSION['user_id']]);
        }
        logActivity('purchase_invoice_create', 'purchase_invoices', $inv_id); $db->commit();
        $_SESSION['success'] = 'تم إنشاء فاتورة الشراء ' . $inv_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/invoices/view.php?id=' . $inv_id);
    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $error = $e->getMessage();
    }
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
*{box-sizing:border-box}
body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow:auto;min-width:1400px}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:var(--sidebar-bg);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.15);color:#fff}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}
.invoice-header{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,#ffc107 0%,#ff9800 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.3);color:#333;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}
.items-section{overflow-x:auto;padding:10px;background:#fff;margin:0 55px 0 0;position:relative}
.items-section::-webkit-scrollbar{height:10px}
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:2400px}
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
.d-none{display:none !important}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}body{min-width:auto}}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-capsule"></i> <?= APP_NAME ?></span>
    <a href="../" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="../invoices/" class="menu-item"><i class="bi bi-receipt"></i> فواتير الشراء</a>
    <a href="../orders/" class="menu-item"><i class="bi bi-file-earmark-text"></i> أوامر الشراء</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?> | <?= arabicDate(date('Y-m-d')) ?></div>
</div>
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="addRow()" title="إضافة صنف (F3)"><i class="bi bi-plus-lg"></i></div>
    <div class="btn-icon" onclick="openF2Search()" title="بحث F2"><i class="bi bi-search"></i></div>
    <div class="btn-icon" onclick="clearAll()" title="مسح الكل"><i class="bi bi-trash"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="ColOrder.openModal()" title="تخصيص الأعمدة"><i class="bi bi-layout-three-columns"></i></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="saveInv()" title="حفظ Ctrl+S"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--red)" onclick="window.location='../'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px"><i class="bi bi-info-circle"></i> F2=بحث | F3=إضافة صف | Enter=التالي | Ctrl+S=حفظ</div>
</div>
<div class="invoice-header">
<form method="POST" id="invForm" autocomplete="off" onsubmit="return beforeSubmit()">
<input type="hidden" name="items_data" id="itemsData" value="">
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
        <input type="text" id="supplier_code_display" class="form-control form-control-sm" placeholder="كود" oninput="onSupCodeInput()" style="font-weight:700;text-align:center">
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
            <option value="cash">كاش</option>
            <option value="credit" selected>آجل</option>
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
    <div class="col-lg-1 col-md-2" id="paidFromWrap">
        <label class="form-label small text-muted">المدفوع منه</label>
        <input type="number" name="paid_amount" id="paid_amount" class="form-control form-control-sm" value="0" step="0.01" oninput="recalc()">
    </div>
    <div class="col-lg-1 col-md-2 d-none" id="deferredWrap">
        <label class="form-label small text-muted">المؤجل منه</label>
        <input type="number" name="deferred_amount" id="deferred_amount" class="form-control form-control-sm" value="0" step="0.01" oninput="recalc()">
    </div>
</div>
</div>
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="addRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صنف</span></button>
    <button type="button" class="tool-btn" onclick="openF2Search()"><i class="bi bi-search"></i><span class="tooltip">بحث F2</span></button>
    <button type="button" class="tool-btn" onclick="ColOrder.openModal()"><i class="bi bi-layout-three-columns"></i><span class="tooltip">الأعمدة</span></button>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="clearAll()" style="color:var(--red)"><i class="bi bi-trash"></i><span class="tooltip">مسح</span></button>
    <button type="button" class="tool-btn" onclick="window.location='../'" style="color:var(--red)"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
</div>
<div class="items-section">
<table class="items-table" id="itemsTable">
<thead><tr id="headerRow"></tr></thead>
<tbody id="itemsBody"></tbody>
</table>
</div>
<div class="bottom-bar">
<div class="row1">
    <div class="item ro"><label>الأصناف:</label><strong id="t_items">0</strong></div>
    <div class="item ro"><label>الباركود:</label><strong id="t_barcodes">0</strong></div>
    <div class="item ro"><label>تكلفة:</label><strong id="t_cost">0.00</strong></div>
    <div class="item ro"><label>الضريبة:</label><strong id="t_vat">0.00</strong></div>
    <div class="item ro"><label>البيع:</label><strong id="t_sell">0.00</strong></div>
    <div class="item ro"><label>ربح ص:</label><strong id="t_profit_val">0.00</strong></div>
    <div class="item ro"><label>ربح %:</label><strong id="t_profit_pct">0%</strong></div>
    <div class="item"><label>خصم إضافي %:</label><input type="number" name="extra_discount_pct" id="xdisc_pct" value="0" step="0.01" oninput="recalc()"></div>
    <div class="item"><label>خصم إضافي ق:</label><input type="number" name="extra_discount_val" id="xdisc_val" value="0" step="0.01" oninput="recalc()"></div>
    <div class="ms-auto grand">الصافي: <span id="t_grand">0.00</span> ج</div>
</div>
</div>
<div class="supplier-section">
<div class="row w-100 align-items-center g-2">
    <div class="col-md-8"><input type="text" name="notes" class="form-control form-control-sm" placeholder="ملاحظات..."></div>
    <div class="col-md-2 text-start"><button type="submit" class="btn btn-success btn-lg px-4"><i class="bi bi-save"></i> حفظ الفاتورة</button></div>
</div>
</div>
</form>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../js/product-search.js"></script>
<script src="../../../js/col-order.js"></script>
<script>
const colDefs=[
    {key:'rownum',label:'#',width:'30px',fixed:true},{key:'print',label:'ط',width:'24px'},
    {key:'barcode',label:'الباركود',width:'100px'},{key:'code',label:'كود الصنف',width:'80px'},
    {key:'name',label:'اسم الصنف',width:'170px'},{key:'unit',label:'الوحدة',width:'60px'},
    {key:'qty',label:'الكمية',width:'60px'},{key:'sell',label:'سعر البيع',width:'60px'},
    {key:'bonus',label:'بونص',width:'50px'},{key:'expiry',label:'الصلاحية',width:'110px'},
    {key:'disc_pct',label:'خصم %',width:'55px'},{key:'disc_val',label:'ق الخصم',width:'60px'},
    {key:'cost',label:'سعر الشراء',width:'60px'},{key:'vat_pct',label:'ضريبة %',width:'55px'},
    {key:'vat_val',label:'ق الضريبة',width:'60px'},{key:'total',label:'إجمالي تكلفة',width:'75px'},
    {key:'profit_v',label:'ربح ص',width:'55px'},{key:'profit_p',label:'ربح %',width:'55px'},
    {key:'company',label:'الشركة',width:'90px'},{key:'location',label:'الموقع',width:'70px'},
    {key:'batch',label:'الباتش',width:'55px'},{key:'delete',label:'',width:'24px',fixed:true}
];
function onPayMethodChange(){
    const m=document.getElementById('payMethod').value;
    const dueWrap=document.getElementById('dueDateWrap');
    const paidWrap=document.getElementById('paidFromWrap');
    const defWrap=document.getElementById('deferredWrap');
    if(m==='cash'||m==='bank_transfer'||m==='wallet_transfer'){
        dueWrap.classList.add('d-none');paidWrap.classList.add('d-none');defWrap.classList.remove('d-none');
    } else if(m==='credit'||m==='under_collection'){
        dueWrap.classList.remove('d-none');paidWrap.classList.remove('d-none');defWrap.classList.add('d-none');
    }
    recalc();
}
let R=0;const allUnits=<?= json_encode($units) ?>;const suppliers=<?= json_encode($suppliers) ?>;
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
    h+=vis('sell','<td><input type="number" id="sp_'+id+'" class="form-control form-control-sm num" value="'+(d.sell_price||'')+'" step="0.01" min="0" oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',6)"></td>');
    h+=vis('bonus','<td><input type="number" id="bn_'+id+'" class="form-control form-control-sm num" value="0" step="0.001" min="0" oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',7)"></td>');
    h+=vis('expiry','<td><input type="month" id="ex_'+id+'" class="form-control form-control-sm" style="font-size:11px" onkeydown="handleEnter(event,'+id+',8)"></td>');
    h+=vis('disc_pct','<td><input type="number" id="dp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="onDiscPct('+id+')" onkeydown="handleEnter(event,'+id+',9)"></td>');
    h+=vis('disc_val','<td><input type="number" id="dv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" oninput="onDiscVal('+id+')" onkeydown="handleEnter(event,'+id+',10)"></td>');
    h+=vis('cost','<td><input type="number" id="cs_'+id+'" class="form-control form-control-sm num" value="'+(d.unit_cost||'')+'" step="0.01" min="0" required oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',11)"></td>');
    h+=vis('vat_pct','<td><input type="number" id="vp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="onVatPct('+id+')" onkeydown="handleEnter(event,'+id+',12)"></td>');
    h+=vis('vat_val','<td><input type="number" id="vv_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" oninput="onVatVal('+id+')" onkeydown="handleEnter(event,'+id+',13)"></td>');
    h+=vis('total','<td><input type="number" id="tl_'+id+'" class="form-control form-control-sm num row-total" value="0" step="0.01" readonly></td>');
    h+=vis('profit_v','<td><input type="number" id="pv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>');
    h+=vis('profit_p','<td><input type="number" id="pp_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>');
    h+=vis('company','<td><input type="text" id="cy_'+id+'" class="form-control form-control-sm" value="'+(d.company_name||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>');
    h+=vis('location','<td><input type="text" id="lc_'+id+'" class="form-control form-control-sm" value="'+(d.location||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>');
    h+=vis('batch','<td><input type="text" id="ba_'+id+'" class="form-control form-control-sm num" placeholder="باتش" onkeydown="handleEnter(event,'+id+',14)"></td>');
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
            unit_id:getVal('un'),unit_name:'',quantity:getNum('qt')||1,bonus:getNum('bn'),
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
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const base=(qt+bn)*cs;
    const dvEl=document.getElementById('dv_'+id);
    if(dvEl)dvEl.value=(base*dp/100).toFixed(2);
    calc(id);
}
function onDiscVal(id){
    const dv=parseFloat(document.getElementById('dv_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const base=(qt+bn)*cs;
    document.getElementById('dp_'+id).value=base>0?((dv/base)*100).toFixed(2):0;
    calc(id);
}
function onVatPct(id){
    const vp=parseFloat(document.getElementById('vp_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const base=(qt+bn)*cs;
    const afterDisc=base*(1-dp/100);
    document.getElementById('vv_'+id).value=(afterDisc*vp/100).toFixed(2);
    calc(id);
}
function onVatVal(id){
    const vv=parseFloat(document.getElementById('vv_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const base=(qt+bn)*cs;
    const afterDisc=base*(1-dp/100);
    document.getElementById('vp_'+id).value=afterDisc>0?((vv/afterDisc)*100).toFixed(2):0;
    calc(id);
}
function calc(id){
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+id)?.value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const vv=parseFloat(document.getElementById('vv_'+id).value)||0;
    const tq=qt+bn;const base=tq*cs;const disc=base*(dp/100);const afterDisc=base-disc;
    const totalCost=afterDisc+vv;
    const profitVal=(sp-cs)*qt;const profitPct=cs>0?(((sp-cs)/cs)*100):0;
    document.getElementById('tl_'+id).value=totalCost.toFixed(2);
    const pvEl=document.getElementById('pv_'+id);const ppEl=document.getElementById('pp_'+id);
    if(pvEl)pvEl.value=profitVal.toFixed(2);
    if(ppEl)ppEl.value=profitPct.toFixed(1);
    recalc();
}
function recalc(){
    let n=0,bc=0,tc=0,tv=0,ts=0,tp=0;
    document.querySelectorAll('#itemsBody tr').forEach(tr=>{
        const id=tr.dataset.rid;
        const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
        const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
        const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
        const sp=parseFloat(document.getElementById('sp_'+id)?.value)||0;
        n++;if(document.getElementById('bc_'+id).value)bc++;
        tc+=cs*(qt+bn);tv+=(parseFloat(document.getElementById('vv_'+id).value)||0);
        ts+=sp*qt;tp+=(parseFloat(document.getElementById('pv_'+id)?.value)||0);
    });
    const xdp=parseFloat(document.getElementById('xdisc_pct').value)||0;
    const xdv=parseFloat(document.getElementById('xdisc_val').value)||0;
    let grand=tc+tv;
    if(xdp>0) grand-=grand*(xdp/100);
    grand-=xdv;if(grand<0)grand=0;
    document.getElementById('t_items').textContent=n;
    document.getElementById('t_barcodes').textContent=bc;
    document.getElementById('t_cost').textContent=tc.toFixed(2);
    document.getElementById('t_vat').textContent=tv.toFixed(2);
    document.getElementById('t_sell').textContent=ts.toFixed(2);
    document.getElementById('t_profit_val').textContent=tp.toFixed(2);
    document.getElementById('t_profit_pct').textContent=tc>0?((tp/tc)*100).toFixed(1)+'%':'0%';
    document.getElementById('t_grand').textContent=grand.toFixed(2);
}
const fieldMap=['bc','co','nm','un','qt','sp','bn','ex','dp','dv','cs','vp','vv','ba'];
function handleEnter(e,id,fieldIdx){
    if(e.key==='Enter'){
        e.preventDefault();
        if(fieldIdx===fieldMap.length-1){addRow();}
        else{const el=document.getElementById(fieldMap[fieldIdx+1]+'_'+id);if(el)el.focus();}
    }
}
function togglePrint(id){
    const ico=document.getElementById('pr_'+id);const inp=document.getElementById('prv_'+id);
    if(inp&&inp.value==='1'){inp.value='0';ico.classList.remove('print-on');ico.classList.add('print-off');}
    else if(inp){inp.value='1';ico.classList.remove('print-off');ico.classList.add('print-on');}
}
function delRow(id){const r=document.getElementById('r_'+id);if(r)r.remove();recalc();}
function clearAll(){if(!confirm('مسح كل الأصناف؟'))return;document.getElementById('itemsBody').innerHTML='';R=0;recalc();}
function onSupplierChange(){
    const sel=document.getElementById('supplier_id');
    const opt=sel.options[sel.selectedIndex];
    document.getElementById('supplier_code_display').value=opt.dataset.code||'';
    autoGenInvNo();
}
function onSupCodeInput(){
    const code=document.getElementById('supplier_code_display').value.trim();
    const sel=document.getElementById('supplier_id');
    for(let i=0;i<sel.options.length;i++){if(sel.options[i].dataset.code===code){sel.selectedIndex=i;onSupplierChange();return;}}
}
function autoGenInvNo(){
    const sid=document.getElementById('supplier_id').value;
    if(!sid)return;
    const s=suppliers.find(x=>x.id==sid);
    if(s)document.getElementById('supInvNo').value=s.supplier_code+'-'+Date.now().toString().slice(-4);
}
function filterStores(){
    const bid=document.getElementById('branchSelect').value;
    const sel=document.getElementById('store_id');
    for(let i=0;i<sel.options.length;i++){const o=sel.options[i];if(!o.value)continue;o.style.display=!bid||o.dataset.branch===bid?'':'none';}
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
    const hip=document.getElementById('hip_'+id);
    if(hip)hip.value=p.product_id||p.id||'';
    document.getElementById('nm_'+id).value=p.product_name||'';
    document.getElementById('co_'+id).value=p.product_code||'';
    document.getElementById('bc_'+id).value=p.barcode||'';
    document.getElementById('cs_'+id).value=p.unit_cost||0;
    document.getElementById('sp_'+id).value=p.sell_price||0;
    document.getElementById('cy_'+id).value=p.company_name||'';
    document.getElementById('lc_'+id).value=p.location||'';
    if(p.unit_id){document.getElementById('un_'+id).value=p.unit_id;}
    if(p.units&&p.units.length>0){
        const sel=document.getElementById('un_'+id);sel.innerHTML='';
        p.units.forEach(u=>{const opt=document.createElement('option');opt.value=u.id;opt.textContent=u.name;if(u.is_default)opt.selected=true;sel.appendChild(opt);});
    }
    calc(id);
}
function saveInv(){if(beforeSubmit())document.getElementById('invForm').submit();}
document.addEventListener('keydown',function(e){
    if(e.ctrlKey&&e.key==='s'){e.preventDefault();saveInv();}
    if(e.key==='F3'){e.preventDefault();addRow();}
});
ColOrder.init(colDefs,'purchase_invoice_cols','headerRow');
onPayMethodChange();addRow();
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
</script>
</body>
</html>