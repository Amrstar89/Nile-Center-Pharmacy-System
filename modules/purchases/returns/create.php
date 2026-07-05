<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();
$db = getDB();
$page_title = 'مرتجع بفاتورة';

$suppliers = $db->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();
$stores = $db->query("SELECT s.id, s.store_name, s.branch_id, b.branch_name FROM stores s LEFT JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY s.store_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        $hasReturn = false;
        foreach ($items as $item) { if (floatval($item['return_qty'] ?? 0) > 0) { $hasReturn = true; break; } }
        if (!$hasReturn) { throw new Exception('يجب إرجاع صنف واحد على الأقل'); }
        $db->beginTransaction();
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_returns WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $ret_number = 'PRET-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $invoice_id = intval($_POST['invoice_id']);
        $store_id = intval($_POST['store_id'] ?? 0);
        $supplier_id = intval($_POST['supplier_id']);
        $return_date = $_POST['return_date'] ?: date('Y-m-d');
        $notes = $_POST['notes'] ?? '';
        $subtotal = 0;
        foreach ($items as $item) {
            $rqty = floatval($item['return_qty'] ?? 0);
            if ($rqty <= 0) continue;
            $cost = floatval($item['unit_cost'] ?? 0);
            $subtotal += $rqty * $cost;
        }
        $db->prepare("INSERT INTO purchase_returns (return_number, invoice_id, supplier_id, store_id, return_date, subtotal, grand_total, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$ret_number, $invoice_id, $supplier_id, $store_id ?: null, $return_date, $subtotal, $subtotal, $notes, $_SESSION['user_id']]);
        $ret_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_return_items (return_id, invoice_item_id, product_id, product_name, product_code, barcode, quantity, unit_cost, line_total, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $rqty = floatval($item['return_qty'] ?? 0);
            if ($rqty <= 0) continue;
            $cost = floatval($item['unit_cost'] ?? 0);
            $line = $rqty * $cost;
            $pid = intval($item['product_id'] ?? 0);
            $itemStmt->execute([$ret_id, $item['invoice_item_id'], $pid ?: null, $item['product_name'], $item['product_code'] ?? '', $item['barcode'] ?? '', $rqty, $cost, $line, $item['reason'] ?? '']);
            if ($store_id && $pid) {
                $db->prepare("UPDATE inventory_items SET quantity = GREATEST(quantity - ?, 0), updated_at = NOW() WHERE store_id = ? AND product_id = ?")
                   ->execute([$rqty, $store_id, $pid]);
            }
        }
        logActivity('purchase_return_create', 'purchase_returns', $ret_id);
        $db->commit();
        $_SESSION['success'] = 'تم إنشاء المرتجع ' . $ret_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/returns/');
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
:root{--ret-red:#c0392b;--primary:#667eea;--secondary:#764ba2;--sidebar-bg:#1a1a2e;}
*{box-sizing:border-box}body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto}
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
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:1600px}
.items-table th{background:var(--ret-red);color:#fff;padding:6px 4px;text-align:center;font-weight:600;font-size:11px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table td{padding:4px 6px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff;vertical-align:middle}
.items-table tr:hover td{background:#fdeaea}
.items-table tr.selected td{background:#fff3cd}
.items-table td input,.items-table td select{border:1px solid #ddd;border-radius:4px;padding:3px 5px;font-size:12px;text-align:center;height:28px}
.items-table .product-name{text-align:right;font-weight:600}
.items-table .num{text-align:center}
.items-table .row-check{width:18px;height:18px;cursor:pointer;accent-color:var(--ret-red)}
.bottom-bar{background:#fdeaea;border-top:2px solid var(--ret-red);padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .row1{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.bottom-bar .item{display:flex;align-items:center;gap:4px;background:#fff;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px}
.bottom-bar .item label{color:#666;font-size:11px}
.bottom-bar .item strong{color:#333;font-size:13px}
.bottom-bar .grand{background:linear-gradient(135deg,#e74c3c,var(--ret-red));color:#fff;padding:8px 20px;border-radius:10px;font-size:16px;font-weight:700}
.supplier-section{background:#fff3cd;padding:10px 20px;border-top:1px solid #ffc107}
.select-section{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}}
</style>
</head>
<body>
<!-- Top Header -->
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="./" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="create_direct.php" class="menu-item"><i class="bi bi-plus-circle"></i> مرتجع بدون فاتورة</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?> | <?= arabicDate(date('Y-m-d')) ?></div>
</div>
<!-- Sub Menu -->
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="selectAll()" title="تحديد الكل"><i class="bi bi-check-all"></i></div>
    <div class="btn-icon" onclick="unselectAll()" title="إلغاء التحديد"><i class="bi bi-check-square"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="saveRet()" title="حفظ Ctrl+S"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--ret-red)" onclick="window.location='./'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px"><i class="bi bi-info-circle"></i> اختر المورد ثم الفاتورة | Ctrl+S=حفظ</div>
</div>
<!-- Select Invoice Section -->
<div class="select-section">
<div class="row g-2 align-items-end">
    <div class="col-md-3">
        <label class="form-label small fw-bold text-danger">المورد <span class="text-danger">*</span></label>
        <select id="supplierSelect" class="form-select form-select-sm" onchange="loadSupplierInvoices()">
            <option value="">-- اختر المورد --</option>
            <?php foreach($suppliers as $s){ ?><option value="<?= $s['id'] ?>"><?= $s['supplier_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-bold text-danger">فاتورة الشراء <span class="text-danger">*</span></label>
        <select id="invoiceSelect" class="form-select form-select-sm" onchange="loadInvoiceItems()" disabled>
            <option value="">-- اختر الفاتورة --</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small text-muted">المخزن</label>
        <select name="store_id" id="store_id" class="form-select form-select-sm">
            <option value="">-- اختر --</option>
            <?php foreach($stores as $st){ ?><option value="<?= $st['id'] ?>"><?= $st['store_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small text-muted">تاريخ المرتجع</label>
        <input type="date" name="return_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-2 text-start">
        <span class="badge bg-danger fs-6"><i class="bi bi-arrow-return-left"></i> مرتجع بفاتورة</span>
    </div>
</div>
</div>
<form method="POST" id="retForm">
<input type="hidden" name="supplier_id" id="formSupplierId" value="">
<input type="hidden" name="invoice_id" id="formInvoiceId" value="">
<!-- Toolbar Right -->
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="selectAll()"><i class="bi bi-check-all"></i><span class="tooltip">تحديد الكل</span></button>
    <button type="button" class="tool-btn" onclick="unselectAll()"><i class="bi bi-check-square"></i><span class="tooltip">إلغاء التحديد</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="window.location='./'" style="color:#ffebee"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
</div>
<!-- Items -->
<div class="items-section">
<table class="items-table" id="itemsTable">
<thead>
<tr>
    <th><input type="checkbox" id="checkAll" class="row-check" onchange="toggleAll()"></th>
    <th>#</th>
    <th style="min-width:200px">اسم الصنف</th>
    <th style="min-width:80px">كود</th>
    <th style="min-width:90px">باركود</th>
    <th style="min-width:70px">الوحدة</th>
    <th style="min-width:80px">كمية الفاتورة</th>
    <th style="min-width:80px">الرصيد الحالي</th>
    <th style="min-width:80px">سعر الشراء</th>
    <th style="min-width:110px">الصلاحية</th>
    <th style="min-width:80px">الباتش</th>
    <th style="min-width:100px">الكمية المرجعة</th>
    <th style="min-width:90px">الإجمالي</th>
    <th style="min-width:150px">السبب</th>
</tr>
</thead>
<tbody id="itemsBody">
<tr><td colspan="14" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>اختر المورد ثم الفاتورة لعرض الأصناف</td></tr>
</tbody>
</table>
</div>
<!-- Bottom Summary -->
<div class="bottom-bar">
<div class="row1">
    <div class="item"><label>الأصناف:</label><strong id="sumItems">0</strong></div>
    <div class="item"><label>المرجع:</label><strong id="sumReturn">0</strong></div>
    <div class="item"><label>الفاتورة الأصلية:</label><strong id="sumOrig">0.00</strong></div>
    <div class="item"><label>إجمالي المرتجع:</label><strong id="sumRetTotal" style="color:var(--ret-red)">0.00</strong></div>
    <div class="ms-auto grand">صافي المرتجع: <span id="sumGrand">0.00</span> ج</div>
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
let invData={};
function loadSupplierInvoices(){
    const sid=document.getElementById('supplierSelect').value;
    const sel=document.getElementById('invoiceSelect');
    document.getElementById('formSupplierId').value=sid;
    sel.innerHTML='<option value="">-- جاري التحميل --</option>';
    sel.disabled=true;
    if(!sid){sel.innerHTML='<option value="">-- اختر الفاتورة --</option>';document.getElementById('itemsBody').innerHTML='<tr><td colspan="14" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>اختر المورد ثم الفاتورة</td></tr>';return;}
    fetch('ajax_get_invoices.php?supplier_id='+sid).then(r=>r.json()).then(data=>{
        let h='<option value="">-- اختر الفاتورة --</option>';
        data.forEach(i=>{h+='<option value="'+i.id+'">'+i.invoice_number+' - '+i.invoice_date+' ('+parseFloat(i.grand_total).toFixed(2)+' ج)</option>';});
        sel.innerHTML=h;sel.disabled=false;
    });
}
function loadInvoiceItems(){
    const invId=document.getElementById('invoiceSelect').value;
    document.getElementById('formInvoiceId').value=invId;
    const tbody=document.getElementById('itemsBody');
    if(!invId){tbody.innerHTML='<tr><td colspan="14" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>اختر الفاتورة</td></tr>';recalc();return;}
    fetch('ajax_get_invoice_items.php?invoice_id='+invId).then(r=>r.json()).then(data=>{
        invData=data;
        let h='';
        data.forEach((it,idx)=>{
            h+='<tr id="row_'+idx+'" data-idx="'+idx+'">'+
                '<td><input type="checkbox" class="row-check" id="chk_'+idx+'" onchange="toggleRow('+idx+')"></td>'+
                '<td>'+(idx+1)+'<input type="hidden" name="items['+idx+'][invoice_item_id]" value="'+it.invoice_item_id+'"><input type="hidden" name="items['+idx+'][product_id]" value="'+(it.product_id||'')+'"></td>'+
                '<td class="product-name"><strong>'+it.product_name+'</strong><input type="hidden" name="items['+idx+'][product_name]" value="'+it.product_name+'"></td>'+
                '<td>'+(it.product_code||'-')+'<input type="hidden" name="items['+idx+'][product_code]" value="'+(it.product_code||'')+'"></td>'+
                '<td>'+(it.barcode||'-')+'<input type="hidden" name="items['+idx+'][barcode]" value="'+(it.barcode||'')+'"></td>'+
                '<td>'+(it.unit_name||'علبة')+'</td>'+
                '<td><input type="number" class="form-control form-control-sm num" value="'+it.quantity+'" readonly style="background:#e9ecef"></td>'+
                '<td><input type="number" class="form-control form-control-sm num" value="'+(it.current_stock||0)+'" readonly style="background:#e9ecef;color:'+(it.current_stock>0?'green':'red')+'"></td>'+
                '<td><input type="number" name="items['+idx+'][unit_cost]" id="cost_'+idx+'" class="form-control form-control-sm num" value="'+it.unit_cost+'" step="0.01" readonly style="background:#e9ecef"></td>'+
                '<td><input type="month" class="form-control form-control-sm" value="'+(it.expiry_date?it.expiry_date.substring(0,7):'')+'" readonly style="background:#e9ecef;font-size:11px"></td>'+
                '<td><input type="text" class="form-control form-control-sm" value="'+(it.batch_number||'')+'" readonly style="background:#e9ecef;font-size:11px"></td>'+
                '<td><input type="number" name="items['+idx+'][return_qty]" id="rqty_'+idx+'" class="form-control form-control-sm num" value="0" step="0.001" min="0" max="'+it.quantity+'" oninput="calcRow('+idx+')" style="font-weight:700;color:var(--ret-red)"></td>'+
                '<td><input type="number" id="rtotal_'+idx+'" class="form-control form-control-sm num" value="0" step="0.01" readonly style="background:#fdeaea;font-weight:700"></td>'+
                '<td><input type="text" name="items['+idx+'][reason]" class="form-control form-control-sm" placeholder="سبب الإرجاع"></td>'+
            '</tr>';
        });
        tbody.innerHTML=h;recalc();
    });
}
function toggleAll(){
    const ca=document.getElementById('checkAll').checked;
    document.querySelectorAll('.row-check').forEach((chk,idx)=>{
        if(chk.id==='checkAll')return;
        const rowIdx=parseInt(chk.id.replace('chk_',''));
        chk.checked=ca;
        const row=document.getElementById('row_'+rowIdx);
        if(row){
            if(ca){row.classList.add('selected');const q=document.getElementById('rqty_'+rowIdx);if(parseFloat(q.value)===0)q.value=invData[rowIdx]?.quantity||0;}
            else{row.classList.remove('selected');document.getElementById('rqty_'+rowIdx).value=0;}
            calcRow(rowIdx);
        }
    });
}
function selectAll(){document.getElementById('checkAll').checked=true;toggleAll();}
function unselectAll(){document.getElementById('checkAll').checked=false;toggleAll();}
function toggleRow(idx){
    const chk=document.getElementById('chk_'+idx);const row=document.getElementById('row_'+idx);
    if(chk.checked){row.classList.add('selected');const q=document.getElementById('rqty_'+idx);if(parseFloat(q.value)===0)q.value=invData[idx]?.quantity||0;}
    else{row.classList.remove('selected');document.getElementById('rqty_'+idx).value=0;}
    calcRow(idx);
}
function calcRow(idx){
    const rq=parseFloat(document.getElementById('rqty_'+idx).value)||0;
    const c=parseFloat(document.getElementById('cost_'+idx).value)||0;
    document.getElementById('rtotal_'+idx).value=(rq*c).toFixed(2);
    const chk=document.getElementById('chk_'+idx);const row=document.getElementById('row_'+idx);
    if(rq>0){row.classList.add('selected');if(chk)chk.checked=true;}
    else{row.classList.remove('selected');if(chk)chk.checked=false;}
    recalc();
}
function recalc(){
    let itemCount=0,retCount=0,origTotal=0,retTotal=0;
    document.querySelectorAll('#itemsBody tr[data-idx]').forEach(tr=>{
        const idx=parseInt(tr.dataset.idx);
        const rq=parseFloat(document.getElementById('rqty_'+idx)?.value)||0;
        const c=parseFloat(document.getElementById('cost_'+idx)?.value)||0;
        const oq=invData[idx]?.quantity||0;
        itemCount++;origTotal+=oq*c;
        if(rq>0){retCount++;retTotal+=rq*c;}
    });
    document.getElementById('sumItems').textContent=itemCount;
    document.getElementById('sumReturn').textContent=retCount;
    document.getElementById('sumOrig').textContent=origTotal.toFixed(2);
    document.getElementById('sumRetTotal').textContent=retTotal.toFixed(2);
    document.getElementById('sumGrand').textContent=retTotal.toFixed(2);
}
function saveRet(){document.getElementById('retForm').submit();}
document.addEventListener('keydown',function(e){if(e.ctrlKey&&e.key==='s'){e.preventDefault();saveRet();}});
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
</script>
</body>
</html>