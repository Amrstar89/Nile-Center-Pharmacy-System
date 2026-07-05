<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();
$db = getDB();
$page_title = 'مرتجع بدون فاتورة';

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
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_returns WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $ret_number = 'PRET-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $supplier_id = intval($_POST['supplier_id']);
        $store_id = intval($_POST['store_id'] ?? 0);
        $return_date = $_POST['return_date'] ?: date('Y-m-d');
        $notes = $_POST['notes'] ?? '';
        $subtotal = 0;
        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $subtotal += $qty * floatval($item['unit_cost'] ?? 0);
        }
        if ($subtotal <= 0) { throw new Exception('يجب إرجاع صنف واحد على الأقل'); }
        $db->prepare("INSERT INTO purchase_returns (return_number, invoice_id, supplier_id, store_id, return_date, subtotal, grand_total, notes, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$ret_number, $supplier_id, $store_id ?: null, $return_date, $subtotal, $subtotal, $notes, $_SESSION['user_id']]);
        $ret_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_return_items (return_id, invoice_item_id, product_id, product_name, product_code, barcode, quantity, unit_cost, line_total, reason) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $cost = floatval($item['unit_cost'] ?? 0);
            $line = $qty * $cost;
            $pid = intval($item['product_id'] ?? 0);
            $itemStmt->execute([$ret_id, $pid ?: null, $item['product_name'], $item['product_code'] ?? '', $item['barcode'] ?? '', $qty, $cost, $line, $item['reason'] ?? '']);
            if ($store_id && $pid) {
                $db->prepare("UPDATE inventory_items SET quantity = GREATEST(quantity - ?, 0), updated_at = NOW() WHERE store_id = ? AND product_id = ?")
                   ->execute([$qty, $store_id, $pid]);
            }
        }
        logActivity('purchase_return_direct', 'purchase_returns', $ret_id);
        $db->commit();
        $_SESSION['success'] = 'تم إنشاء المرتجع المباشر ' . $ret_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/returns/');
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
:root{--ret-red:#c0392b;--primary:#667eea;--sidebar-bg:#1a1a2e;--green:#198754;--red:#dc3545;}
*{box-sizing:border-box}body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:var(--ret-red);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.2);color:#fff}
.top-header .menu-item i{margin-left:6px}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--ret-red);color:#fff;border-color:var(--ret-red)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}
.invoice-header{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,#e74c3c 0%,var(--ret-red) 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.25);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;color:var(--ret-red);transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}
.items-section{overflow-x:auto;padding:10px;background:#fff;margin:0 55px 0 0}
.items-section::-webkit-scrollbar{height:10px}
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:2000px}
.items-table th{background:var(--ret-red);color:#fff;padding:6px 4px;text-align:center;font-weight:600;font-size:11px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table td{padding:3px 4px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff;vertical-align:middle}
.items-table tr:hover td{background:#fdeaea}
.items-table td input,.items-table td select{border:1px solid #ddd;border-radius:4px;padding:2px 4px;font-size:12px;text-align:center;height:28px;width:100%}
.items-table td input:focus{border-color:var(--ret-red);outline:none}
.items-table .product-name{text-align:right;font-weight:600;min-width:170px}
.items-table .num{text-align:center;min-width:50px}
.items-table .row-calc{background:#e3f2fd}
.items-table .row-total{font-weight:700;color:var(--ret-red);background:#fdeaea !important}
.items-table .btn-del{color:var(--red);cursor:pointer;font-size:15px;padding:0 3px}
.items-table .btn-del:hover{transform:scale(1.2)}
.items-table .barcode-w{position:relative;display:flex;align-items:center;min-width:100px}
.items-table .barcode-w input{flex:1;padding-left:28px}
.items-table .barcode-w .btn-f2{position:absolute;left:2px;top:2px;bottom:2px;width:24px;border:none;background:#f0f0f0;border-radius:3px;cursor:pointer;font-size:10px;color:#666;display:flex;align-items:center;justify-content:center}
.items-table .barcode-w .btn-f2:hover{background:var(--ret-red);color:#fff}
.bottom-bar{background:#fdeaea;border-top:2px solid var(--ret-red);padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .row1{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.bottom-bar .item{display:flex;align-items:center;gap:4px;background:#fff;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px}
.bottom-bar .item label{color:#666;font-size:11px;white-space:nowrap}
.bottom-bar .item strong{color:#333;font-size:13px;white-space:nowrap}
.bottom-bar .item.ro{background:#ffcdd2;border-color:#ef9a9a}
.bottom-bar .item.ro strong{color:#b71c1c}
.bottom-bar .grand{background:linear-gradient(135deg,#e74c3c,var(--ret-red));color:#fff;padding:8px 20px;border-radius:10px;font-size:16px;font-weight:700}
.bottom-bar .item input{height:26px;padding:2px 5px;font-size:12px;width:80px;border:1px solid #ccc;border-radius:4px}
.supplier-section{background:#fff3cd;padding:10px 20px;border-top:1px solid #ffc107}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}}
</style>
</head>
<body>
<!-- Top Header -->
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="./" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="create.php" class="menu-item"><i class="bi bi-receipt"></i> مرتجع بفاتورة</a>
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
    <div class="btn-icon" onclick="saveRet()" title="حفظ Ctrl+S"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--ret-red)" onclick="window.location='./'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px"><i class="bi bi-info-circle"></i> F2=بحث | F3=إضافة صف | Ctrl+S=حفظ</div>
</div>
<!-- Header -->
<div class="invoice-header">
<form method="POST" id="retForm">
<div class="row g-2">
    <div class="col-lg-2 col-md-3">
        <label class="form-label small fw-bold text-danger">المورد <span class="text-danger">*</span></label>
        <select name="supplier_id" id="supplier_id" class="form-select form-select-sm" required>
            <option value="">-- اختر المورد --</option>
            <?php foreach($suppliers as $s){ ?><option value="<?= $s['id'] ?>" data-code="<?= $s['supplier_code'] ?>"><?= $s['supplier_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">كود المورد</label>
        <input type="text" id="sup_code_dsp" class="form-control form-control-sm" readonly style="background:#e9ecef;font-weight:700;text-align:center">
    </div>
    <div class="col-lg-1 col-md-2">
        <label class="form-label small text-muted">تاريخ المرتجع</label>
        <input type="date" name="return_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
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
    <div class="col-lg-1 col-md-2 text-start">
        <span class="badge bg-danger fs-6"><i class="bi bi-arrow-return-left"></i> بدون فاتورة</span>
    </div>
</div>
</div>
<!-- Toolbar Right -->
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="addRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صنف</span></button>
    <button type="button" class="tool-btn" onclick="openF2Search()"><i class="bi bi-search"></i><span class="tooltip">بحث F2</span></button>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="clearAll()" style="color:#ffebee"><i class="bi bi-trash"></i><span class="tooltip">مسح</span></button>
    <button type="button" class="tool-btn" onclick="window.location='./'" style="color:#ffebee"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
</div>
<!-- Items Table -->
<div class="items-section">
<table class="items-table" id="itemsTable">
<thead>
<tr>
    <th>#</th>
    <th style="min-width:100px">الباركود</th>
    <th style="min-width:80px">كود الصنف</th>
    <th style="min-width:170px">اسم الصنف</th>
    <th style="min-width:60px">الوحدة</th>
    <th style="min-width:60px">الكمية</th>
    <th style="min-width:80px">الرصيد الحالي</th>
    <th style="min-width:60px">سعر الشراء</th>
    <th style="min-width:110px">الصلاحية</th>
    <th style="min-width:55px">الباتش</th>
    <th style="min-width:60px">إجمالي</th>
    <th style="min-width:150px">السبب / ملاحظات</th>
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
    <div class="item ro"><label>إجمالي المرتجع:</label><strong id="t_total">0.00</strong></div>
    <div class="ms-auto grand">الإجمالي: <span id="t_grand">0.00</span> ج</div>
</div>
</div>
<!-- Notes & Save -->
<div class="supplier-section">
<div class="row w-100 align-items-center g-2">
    <div class="col-md-8"><input type="text" name="notes" class="form-control" placeholder="سبب الإرجاع أو ملاحظات..."></div>
    <div class="col-md-2 text-start"><button type="submit" class="btn btn-danger btn-lg px-4"><i class="bi bi-arrow-return-left"></i> حفظ المرتجع</button></div>
</div>
</div>
</form>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../js/product-search.js"></script>
<script>
let R=0; const units=<?= json_encode($units) ?>;
document.getElementById('supplier_id').addEventListener('change',function(){
    document.getElementById('sup_code_dsp').value=this.options[this.selectedIndex].dataset.code||'';
});
function fltStores(){
    const bid=document.getElementById('branchSel').value;
    const sel=document.getElementById('store_id');
    for(let i=0;i<sel.options.length;i++){const o=sel.options[i];if(!o.value)continue;o.style.display=!bid||o.dataset.branch===bid?'':'none';}
}
function addRow(data){
    R++;const id=R;
    let uo='<option value="">--</option>';
    units.forEach(u=>uo+='<option value="'+u.id+'">'+u.unit_name_ar+'</option>');
    const d=data||{};
    const tr=document.createElement('tr');
    tr.id='r_'+id;tr.dataset.rid=id;
    tr.innerHTML=
        '<td>'+id+'<input type="hidden" name="items['+id+'][product_id]" value="'+(d.product_id||'')+'"></td>'+
        '<td><div class="barcode-w"><input type="text" name="items['+id+'][barcode]" id="bc_'+id+'" class="form-control form-control-sm" value="'+(d.barcode||'')+'"><button type="button" class="btn-f2" onclick="f2r('+id+')">F2</button></div></td>'+
        '<td><input type="text" name="items['+id+'][product_code]" id="co_'+id+'" class="form-control form-control-sm" value="'+(d.product_code||'')+'"></td>'+
        '<td><input type="text" name="items['+id+'][product_name]" id="nm_'+id+'" class="form-control form-control-sm product-name" value="'+(d.product_name||'')+'" required></td>'+
        '<td><select name="items['+id+'][unit_id]" id="un_'+id+'" class="form-select form-select-sm">'+uo+'</select><input type="hidden" name="items['+id+'][unit_name]" id="unm_'+id+'" value=""></td>'+
        '<td><input type="number" name="items['+id+'][quantity]" id="qt_'+id+'" class="form-control form-control-sm num" value="1" step="0.001" min="0.001" required oninput="calc('+id+')"></td>'+
        '<td><input type="number" id="st_'+id+'" class="form-control form-control-sm num row-calc" value="'+(d.stock_qty||'')+'" readonly></td>'+
        '<td><input type="number" name="items['+id+'][unit_cost]" id="cs_'+id+'" class="form-control form-control-sm num" value="'+(d.unit_cost||'')+'" step="0.01" min="0" required oninput="calc('+id+')"></td>'+
        '<td><input type="month" name="items['+id+'][expiry_date]" class="form-control form-control-sm" style="font-size:11px"></td>'+
        '<td><input type="text" name="items['+id+'][batch_number]" class="form-control form-control-sm num" placeholder="باتش"></td>'+
        '<td><input type="number" id="tl_'+id+'" class="form-control form-control-sm num row-total" value="0" step="0.01" readonly></td>'+
        '<td><input type="text" name="items['+id+'][reason]" class="form-control form-control-sm" placeholder="سبب الإرجاع..."></td>'+
        '<td><span class="btn-del" onclick="delRow('+id+')"><i class="bi bi-trash-fill"></i></span></td>';
    document.getElementById('itemsBody').appendChild(tr);
    document.getElementById('bc_'+id).addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2r(id);}});
    document.getElementById('nm_'+id).addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();f2r(id);}});
    if(data)calc(id);
    recalc();
    return id;
}
function delRow(id){const r=document.getElementById('r_'+id);if(r)r.remove();recalc();}
function clearAll(){if(!confirm('مسح كل الأصناف؟'))return;document.getElementById('itemsBody').innerHTML='';R=0;recalc();}
function calc(id){
    const qt=parseFloat(document.getElementById('qt_'+id).value)||0;
    const cs=parseFloat(document.getElementById('cs_'+id).value)||0;
    document.getElementById('tl_'+id).value=(qt*cs).toFixed(2);
    recalc();
}
function recalc(){
    let n=0,total=0;
    document.querySelectorAll('#itemsBody tr').forEach(tr=>{
        const id=tr.dataset.rid;
        n++;
        total+=(parseFloat(document.getElementById('tl_'+id)?.value)||0);
    });
    document.getElementById('t_items').textContent=n;
    document.getElementById('t_total').textContent=total.toFixed(2);
    document.getElementById('t_grand').textContent=total.toFixed(2);
}
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
    document.getElementById('st_'+id).value=p.stock_qty||0;
    const pi=document.querySelector('#r_'+id+' input[name*="[product_id]"]');
    if(pi)pi.value=p.product_id||p.id||'';
    calc(id);
}
function saveRet(){document.getElementById('retForm').submit();}
document.addEventListener('keydown',function(e){
    if(e.ctrlKey&&e.key==='s'){e.preventDefault();saveRet();}
    if(e.key==='F3'){e.preventDefault();addRow();}
    if(e.key==='F2'){const a=document.activeElement;const r=a.closest('tr');if(r&&r.dataset.rid){e.preventDefault();f2r(r.dataset.rid);}}
});
addRow();
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
</script>
</body>
</html>