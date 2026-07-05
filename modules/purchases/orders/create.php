<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'أمر شراء جديد';

$suppliers = $db->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();
$stores = $db->query("SELECT s.id, s.store_name, s.branch_id, b.branch_name FROM stores s LEFT JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY s.store_name")->fetchAll();
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        if (empty($items)) { throw new Exception('يجب إضافة صنف واحد على الأقل'); }
        $db->beginTransaction();
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $po_number = 'PO-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $supplier_id = intval($_POST['supplier_id']);
        $store_id = intval($_POST['store_id'] ?? 0);
        $expected_date = $_POST['expected_date'] ?: null;
        $notes = $_POST['notes'] ?? '';
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
        $db->prepare("INSERT INTO purchase_orders (po_number, supplier_id, store_id, order_date, expected_date, status, subtotal, vat_percent, vat_amount, grand_total, notes, created_by) VALUES (?, ?, ?, NOW(), ?, 'draft', ?, 0, ?, ?, ?, ?)")
           ->execute([$po_number, $supplier_id, $store_id ?: null, $expected_date, $subtotal, $total_vat, $subtotal + $total_vat, $notes, $_SESSION['user_id']]);
        $po_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_order_items (po_id, product_id, product_name, product_code, barcode, unit_id, unit_name, quantity, bonus_qty, unit_cost, sell_price, discount_percent, vat_percent, vat_value, expiry_date, batch_number, line_total, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $pid = intval($item['product_id'] ?? 0); $qty = floatval($item['quantity'] ?? 0); $bonus = floatval($item['bonus'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0); $discPct = floatval($item['discount_percent'] ?? 0); $totalQty = $qty + $bonus; $line = $totalQty * $cost * (1 - $discPct/100);
            $itemVatPct = floatval($item['vat_percent'] ?? 0); $itemVatVal = floatval($item['vat_value'] ?? 0);
            $itemStmt->execute([$po_id, $pid ?: null, $item['product_name'] ?? '', $item['product_code'] ?? null, $item['barcode'] ?? null, intval($item['unit_id'] ?? 0) ?: null, $item['unit_name'] ?? 'علبة', $qty, $bonus, $cost, floatval($item['sell_price'] ?? 0), $discPct, $itemVatPct, $itemVatVal, ($item['expiry_date'] ?? null) ?: null, $item['batch_number'] ?? null, $line, $item['notes'] ?? '']);
        }
        logActivity('purchase_order_create', 'purchase_orders', $po_id); $db->commit();
        $_SESSION['success'] = 'تم إنشاء أمر الشراء ' . $po_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/orders/view.php?id=' . $po_id);
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
:root{--po-blue:#0277bd;--red:#dc3545;}
*{box-sizing:border-box}body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:var(--po-blue);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.2);color:#fff}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--po-blue);color:#fff;border-color:var(--po-blue)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}
.invoice-header{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,#29b6f6 0%,var(--po-blue) 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.25);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;color:var(--po-blue);transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}
.items-section{overflow-x:auto;padding:10px;background:#fff;margin:0 55px 0 0}
.items-section::-webkit-scrollbar{height:10px}
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:2400px}
.items-table th{background:var(--po-blue);color:#fff;padding:6px 4px;text-align:center;font-weight:600;font-size:11px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table td{padding:3px 4px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff;vertical-align:middle}
.items-table tr:hover td{background:#e1f5fe}
.items-table td input,.items-table td select{border:1px solid #ddd;border-radius:4px;padding:2px 4px;font-size:12px;text-align:center;height:28px;width:100%}
.items-table td input:focus{border-color:var(--po-blue);outline:none}
.items-table .product-name{text-align:right;font-weight:600;min-width:170px}
.items-table .num{text-align:center;min-width:50px}
.items-table .row-total{font-weight:700;color:var(--po-blue);background:#e1f5fe !important}
.items-table .row-calc{background:#e3f2fd}
.items-table .btn-del{color:var(--red);cursor:pointer;font-size:15px;padding:0 3px}
.items-table .barcode-w{position:relative;display:flex;align-items:center;min-width:100px}
.items-table .barcode-w input{flex:1;padding-left:28px}
.items-table .barcode-w .btn-f2{position:absolute;left:2px;top:2px;bottom:2px;width:24px;border:none;background:#f0f0f0;border-radius:3px;cursor:pointer;font-size:10px;color:#666;display:flex;align-items:center;justify-content:center}
.items-table .barcode-w .btn-f2:hover{background:var(--po-blue);color:#fff}
.items-table .print-icon{font-size:16px;cursor:pointer}
.items-table .print-on{color:#198754}
.items-table .print-off{color:#ddd}
.bottom-bar{background:#e1f5fe;border-top:2px solid var(--po-blue);padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .row1{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.bottom-bar .item{display:flex;align-items:center;gap:4px;background:#fff;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px}
.bottom-bar .item label{color:#666;font-size:11px;white-space:nowrap}
.bottom-bar .item strong{color:#333;font-size:13px;white-space:nowrap}
.bottom-bar .item.ro{background:#b3e5fc;border-color:#81d4fa}
.bottom-bar .item.ro strong{color:#01579b}
.bottom-bar .grand{background:linear-gradient(135deg,#29b6f6,var(--po-blue));color:#fff;padding:8px 20px;border-radius:10px;font-size:16px;font-weight:700}
.bottom-bar .item input{height:26px;padding:2px 5px;font-size:12px;width:80px;border:1px solid #ccc;border-radius:4px}
.supplier-section{background:#fff3cd;padding:10px 20px;border-top:1px solid #ffc107}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-capsule"></i> <?= APP_NAME ?></span>
    <a href="../" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="../invoices/" class="menu-item"><i class="bi bi-receipt"></i> فواتير الشراء</a>
    <a href="./" class="menu-item"><i class="bi bi-file-earmark-text"></i> أوامر الشراء</a>
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
    <div class="btn-icon" onclick="savePO()" title="حفظ Ctrl+S"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--red)" onclick="window.location='./'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px"><i class="bi bi-info-circle"></i> F2=بحث | F3=إضافة صف | Enter=التالي | Ctrl+S=حفظ</div>
</div>
<div class="invoice-header">
<form method="POST" id="poForm">
<div class="row g-2">
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">المورد <span class="text-danger">*</span></label>
        <select name="supplier_id" id="supplier_id" class="form-select form-select-sm" required onchange="onSupChange()">
            <option value="">-- اختر المورد --</option>
            <?php foreach($suppliers as $s){ ?><option value="<?= $s['id'] ?>" data-code="<?= $s['supplier_code'] ?>"><?= $s['supplier_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">كود المورد</label>
        <input type="text" id="sup_code_dsp" class="form-control form-control-sm" placeholder="كود" oninput="onSupCodeInput()" style="font-weight:700;text-align:center">
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">تاريخ التوقع</label>
        <input type="date" name="expected_date" class="form-control form-control-sm">
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">الفرع</label>
        <select id="branchSel" class="form-select form-select-sm" onchange="fltStores()">
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
    <div class="col-lg-2 col-md-3">
        <label class="form-label small text-muted">الحالة</label>
        <input type="text" class="form-control form-control-sm" value="مسودة" readonly style="background:#e3f2fd;font-weight:700;text-align:center;color:var(--po-blue)">
    </div>
</div>
</div>
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="addRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صنف</span></button>
    <button type="button" class="tool-btn" onclick="openF2Search()"><i class="bi bi-search"></i><span class="tooltip">بحث F2</span></button>
    <button type="button" class="tool-btn" onclick="ColOrder.openModal()"><i class="bi bi-layout-three-columns"></i><span class="tooltip">الأعمدة</span></button>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="clearAll()" style="color:#ffebee"><i class="bi bi-trash"></i><span class="tooltip">مسح</span></button>
    <button type="button" class="tool-btn" onclick="window.location='./'" style="color:#ffebee"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
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
    <div class="item ro"><label>تكلفة:</label><strong id="t_cost">0.00</strong></div>
    <div class="item ro"><label>الضريبة:</label><strong id="t_vat">0.00</strong></div>
    <div class="item ro"><label>البيع:</label><strong id="t_sell">0.00</strong></div>
    <div class="item ro"><label>ربح ص:</label><strong id="t_profit_val">0.00</strong></div>
    <div class="item ro"><label>ربح %:</label><strong id="t_profit_pct">0%</strong></div>
    <div class="ms-auto grand">الإجمالي: <span id="t_grand">0.00</span> ج</div>
</div>
</div>
<div class="supplier-section">
<div class="row w-100 align-items-center g-2">
    <div class="col-md-8"><input type="text" name="notes" class="form-control form-control-sm" placeholder="ملاحظات..."></div>
    <div class="col-md-2 text-start"><button type="submit" class="btn btn-primary btn-lg px-4" style="background:var(--po-blue);border-color:var(--po-blue)"><i class="bi bi-save"></i> حفظ أمر الشراء</button></div>
</div>
</div>
</form>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../js/product-search.js"></script>
<script src="../../../js/col-order.js"></script>
<script>
/* ===== Column Definitions ===== */
const colDefs=[
    {key:'rownum',label:'#',width:'30px',fixed:true},
    {key:'print',label:'ط',width:'24px'},
    {key:'barcode',label:'الباركود',width:'100px'},
    {key:'code',label:'كود الصنف',width:'80px'},
    {key:'name',label:'اسم الصنف',width:'170px'},
    {key:'unit',label:'الوحدة',width:'60px'},
    {key:'qty',label:'الكمية',width:'60px'},
    {key:'sell',label:'سعر البيع',width:'60px'},
    {key:'bonus',label:'بونص',width:'50px'},
    {key:'expiry',label:'الصلاحية',width:'110px'},
    {key:'disc_pct',label:'خصم %',width:'55px'},
    {key:'disc_val',label:'ق الخصم',width:'60px'},
    {key:'cost',label:'سعر الشراء',width:'60px'},
    {key:'vat_pct',label:'ضريبة %',width:'55px'},
    {key:'vat_val',label:'ق الضريبة',width:'60px'},
    {key:'total',label:'إجمالي تكلفة',width:'75px'},
    {key:'profit_v',label:'ربح ص',width:'55px'},
    {key:'profit_p',label:'ربح %',width:'55px'},
    {key:'company',label:'الشركة',width:'90px'},
    {key:'location',label:'الموقع',width:'70px'},
    {key:'batch',label:'الباتش',width:'55px'},
    {key:'delete',label:'',width:'24px',fixed:true}
];

function buildRowHTML(id,d){
    const data=d||{};
    let uo='<option value="">--</option>';
    units.forEach(u=>uo+='<option value="'+u.id+'">'+u.unit_name_ar+'</option>');
    let html='<tr id="r_'+id+'" data-rid="'+id+'">';
    const order=ColOrder.getOrder();
    order.filter(c=>c.visible).forEach(c=>{
        switch(c.key){
            case 'rownum':
                html+='<td>'+id+'<input type="hidden" name="items['+id+'][product_id]" value="'+(data.product_id||'')+'"></td>';
                break;
            case 'print':
                html+='<td><i class="bi bi-printer print-icon print-off" id="pr_'+id+'" onclick="tglPrint('+id+')"></i><input type="hidden" name="items['+id+'][has_barcode_print]" id="prv_'+id+'" value="0"></td>';
                break;
            case 'barcode':
                html+='<td><div class="barcode-w"><input type="text" name="items['+id+'][barcode]" id="bc_'+id+'" class="form-control form-control-sm" value="'+(data.barcode||'')+'" onkeydown="handleEnter(event,'+id+',1)"><button type="button" class="btn-f2" onclick="f2r('+id+')">F2</button></div></td>';
                break;
            case 'code':
                html+='<td><input type="text" name="items['+id+'][product_code]" id="co_'+id+'" class="form-control form-control-sm" value="'+(data.product_code||'')+'" onkeydown="handleEnter(event,'+id+',2)"></td>';
                break;
            case 'name':
                html+='<td><input type="text" name="items['+id+'][product_name]" id="nm_'+id+'" class="form-control form-control-sm product-name" value="'+(data.product_name||'')+'" required onkeydown="handleEnter(event,'+id+',3)"></td>';
                break;
            case 'unit':
                html+='<td><select name="items['+id+'][unit_id]" id="un_'+id+'" class="form-select form-select-sm" onkeydown="handleEnter(event,'+id+',4)">'+uo+'</select><input type="hidden" name="items['+id+'][unit_name]" id="unm_'+id+'" value=""></td>';
                break;
            case 'qty':
                html+='<td><input type="number" name="items['+id+'][quantity]" id="qt_'+id+'" class="form-control form-control-sm num" value="'+(data.quantity||'1')+'" step="0.001" min="0.001" required oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',5)"></td>';
                break;
            case 'sell':
                html+='<td><input type="number" name="items['+id+'][sell_price]" id="sp_'+id+'" class="form-control form-control-sm num" value="'+(data.sell_price||'')+'" step="0.01" min="0" oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',6)"></td>';
                break;
            case 'bonus':
                html+='<td><input type="number" name="items['+id+'][bonus]" id="bn_'+id+'" class="form-control form-control-sm num" value="0" step="0.001" min="0" oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',7)"></td>';
                break;
            case 'expiry':
                html+='<td><input type="month" name="items['+id+'][expiry_date]" id="ex_'+id+'" class="form-control form-control-sm" style="font-size:11px" onkeydown="handleEnter(event,'+id+',8)"></td>';
                break;
            case 'disc_pct':
                html+='<td><input type="number" name="items['+id+'][discount_percent]" id="dp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="onDiscPct('+id+')" onkeydown="handleEnter(event,'+id+',9)"></td>';
                break;
            case 'disc_val':
                html+='<td><input type="number" id="dv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" oninput="onDiscVal('+id+')" onkeydown="handleEnter(event,'+id+',10)"></td>';
                break;
            case 'cost':
                html+='<td><input type="number" name="items['+id+'][unit_cost]" id="cs_'+id+'" class="form-control form-control-sm num" value="'+(data.unit_cost||'')+'" step="0.01" min="0" required oninput="calc('+id+')" onkeydown="handleEnter(event,'+id+',11)"></td>';
                break;
            case 'vat_pct':
                html+='<td><input type="number" name="items['+id+'][vat_percent]" id="vp_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" min="0" oninput="onVatPct('+id+')" onkeydown="handleEnter(event,'+id+',12)"></td>';
                break;
            case 'vat_val':
                html+='<td><input type="number" name="items['+id+'][vat_value]" id="vv_'+id+'" class="form-control form-control-sm num" value="0" step="0.01" oninput="onVatVal('+id+')" onkeydown="handleEnter(event,'+id+',13)"></td>';
                break;
            case 'total':
                html+='<td><input type="number" id="tl_'+id+'" class="form-control form-control-sm num row-total" value="0" step="0.01" readonly></td>';
                break;
            case 'profit_v':
                html+='<td><input type="number" id="pv_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>';
                break;
            case 'profit_p':
                html+='<td><input type="number" id="pp_'+id+'" class="form-control form-control-sm num row-calc" value="0" step="0.01" readonly></td>';
                break;
            case 'company':
                html+='<td><input type="text" id="cy_'+id+'" class="form-control form-control-sm" value="'+(data.company_name||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>';
                break;
            case 'location':
                html+='<td><input type="text" id="lc_'+id+'" class="form-control form-control-sm" value="'+(data.location||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>';
                break;
            case 'batch':
                html+='<td><input type="text" name="items['+id+'][batch_number]" id="ba_'+id+'" class="form-control form-control-sm num" placeholder="باتش" onkeydown="handleEnter(event,'+id+',14)"></td>';
                break;
            case 'delete':
                html+='<td><span class="btn-del" onclick="delRow('+id+')" tabindex="-1"><i class="bi bi-trash-fill"></i></span></td>';
                break;
        }
    });
    html+='</tr>';return html;
}

let R=0; const units=<?= json_encode($units) ?>; const suppliers=<?= json_encode($suppliers) ?>;
function onSupChange(){
    const sel=document.getElementById('supplier_id');
    document.getElementById('sup_code_dsp').value=sel.options[sel.selectedIndex].dataset.code||'';
}
function onSupCodeInput(){
    const code=document.getElementById('sup_code_dsp').value.trim();
    const sel=document.getElementById('supplier_id');
    for(let i=0;i<sel.options.length;i++){
        if(sel.options[i].dataset.code===code){sel.selectedIndex=i;onSupChange();return;}
    }
}
function fltStores(){
    const bid=document.getElementById('branchSel').value;
    const sel=document.getElementById('store_id');
    for(let i=0;i<sel.options.length;i++){const o=sel.options[i];if(!o.value)continue;o.style.display=!bid||o.dataset.branch===bid?'':'none';}
}
function addRow(data){
    R++;const id=R;
    const html=buildRowHTML(id,data);
    const temp=document.createElement('tbody');
    temp.innerHTML=html;
    const tr=temp.firstElementChild;
    document.getElementById('itemsBody').appendChild(tr);
    document.getElementById('bc_'+id).addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2r(id);}});
    document.getElementById('nm_'+id).addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2r(id);}});
    if(data)calc(id);
    recalc();
    setTimeout(()=>document.getElementById('bc_'+id).focus(),50);
    return id;
}
function onDiscPct(id){
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
    const base=(qt+bn)*cs;
    document.getElementById('dv_'+id).value=(base*dp/100).toFixed(2);
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
    const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
    const dp=parseFloat(document.getElementById('dp_'+id).value)||0;
    const vv=parseFloat(document.getElementById('vv_'+id).value)||0;
    const tq=qt+bn;const base=tq*cs;const disc=base*(dp/100);const afterDisc=base-disc;
    const totalCost=afterDisc+vv;
    const profitVal=(sp-cs)*qt;const profitPct=cs>0?(((sp-cs)/cs)*100):0;
    document.getElementById('tl_'+id).value=totalCost.toFixed(2);
    document.getElementById('pv_'+id).value=profitVal.toFixed(2);
    document.getElementById('pp_'+id).value=profitPct.toFixed(1);
    recalc();
}
function recalc(){
    let n=0,tc=0,tv=0,ts=0,tp=0;
    document.querySelectorAll('#itemsBody tr').forEach(tr=>{
        const id=tr.dataset.rid;
        const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
        const bn=parseFloat(document.getElementById('bn_'+id).value)||0;
        const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
        const sp=parseFloat(document.getElementById('sp_'+id).value)||0;
        n++;tc+=cs*(qt+bn);tv+=(parseFloat(document.getElementById('vv_'+id).value)||0);
        ts+=sp*qt;tp+=(parseFloat(document.getElementById('pv_'+id).value)||0);
    });
    document.getElementById('t_items').textContent=n;
    document.getElementById('t_cost').textContent=tc.toFixed(2);
    document.getElementById('t_vat').textContent=tv.toFixed(2);
    document.getElementById('t_sell').textContent=ts.toFixed(2);
    document.getElementById('t_profit_val').textContent=tp.toFixed(2);
    document.getElementById('t_profit_pct').textContent=tc>0?((tp/tc)*100).toFixed(1)+'%':'0%';
    document.getElementById('t_grand').textContent=(tc+tv).toFixed(2);
}
const fieldMap=['bc','co','nm','un','qt','sp','bn','ex','dp','dv','cs','vp','vv','ba'];
function handleEnter(e,id,fieldIdx){
    if(e.key==='Enter'){
        e.preventDefault();
        if(fieldIdx===fieldMap.length-1){addRow();}
        else{const el=document.getElementById(fieldMap[fieldIdx+1]+'_'+id);if(el)el.focus();}
    }
}
function tglPrint(id){
    const ico=document.getElementById('pr_'+id);const inp=document.getElementById('prv_'+id);
    if(inp.value==='1'){inp.value='0';ico.classList.remove('print-on');ico.classList.add('print-off');}
    else{inp.value='1';ico.classList.remove('print-off');ico.classList.add('print-on');}
}
function delRow(id){const r=document.getElementById('r_'+id);if(r)r.remove();recalc();}
function clearAll(){if(!confirm('مسح كل الأصناف؟'))return;document.getElementById('itemsBody').innerHTML='';R=0;recalc();}
function f2r(id){
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
function savePO(){document.getElementById('poForm').submit();}
document.addEventListener('keydown',function(e){
    if(e.ctrlKey&&e.key==='s'){e.preventDefault();savePO();}
    if(e.key==='F3'){e.preventDefault();addRow();}
});

// Initialize column order
ColOrder.init(colDefs,'purchase_order_cols','headerRow',buildRowHTML);
addRow();
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
</script>
</body>
</html>