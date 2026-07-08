<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'مرتجع بيع بفاتورة';

$customers = $db->query("SELECT id, customer_name, customer_code, phone FROM customers WHERE is_active = 1 ORDER BY customer_name LIMIT 200")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = $_POST['items'] ?? [];
        $hasReturn = false;
        foreach ($items as $item) { if (floatval($item['return_qty'] ?? 0) > 0) { $hasReturn = true; break; } }
        if (!$hasReturn) { throw new Exception('يجب إرجاع صنف واحد على الأقل'); }
        
        $db->beginTransaction();
        $year = date('Y');
        $count = $db->query("SELECT COUNT(*) FROM sale_returns WHERE YEAR(created_at) = $year")->fetchColumn() + 1;
        $ret_number = 'SRET-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
        
        $invoice_id = intval($_POST['invoice_id']);
        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) { throw new Exception('يجب اختيار المخزن'); }
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $return_date = $_POST['return_date'] ?: date('Y-m-d');
        $notes = $_POST['notes'] ?? '';
        
        $subtotal = 0;
        foreach ($items as $item) {
            $rqty = floatval($item['return_qty'] ?? 0);
            if ($rqty <= 0) continue;
            $price = floatval($item['sell_price'] ?? 0);
            $subtotal += $rqty * $price;
        }
        
        $db->prepare("INSERT INTO sale_returns (return_number, invoice_id, customer_id, store_id, user_id, return_date, subtotal, grand_total, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$ret_number, $invoice_id, $customer_id ?: null, $store_id, $_SESSION['user_id'], $return_date, $subtotal, $subtotal, $notes, $_SESSION['user_id']]);
        $ret_id = $db->lastInsertId();
        
        $itemStmt = $db->prepare("INSERT INTO sale_return_items (return_id, invoice_item_id, product_id, product_name, product_code, barcode, quantity, unit_cost, sell_price, line_total, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $rqty = floatval($item['return_qty'] ?? 0);
            if ($rqty <= 0) continue;
            $price = floatval($item['sell_price'] ?? 0);
            $cost = floatval($item['unit_cost'] ?? 0);
            $line = $rqty * $price;
            $pid = intval($item['product_id'] ?? 0);
            $itemStmt->execute([$ret_id, $item['invoice_item_id'], $pid ?: null, $item['product_name'], $item['product_code'] ?? '', $item['barcode'] ?? '', $rqty, $cost, $price, $line, $item['reason'] ?? '']);
            
            if ($pid) {
                // Add back to inventory
                $existing = $db->prepare("SELECT id FROM inventory_items WHERE store_id = ? AND product_id = ?");
                $existing->execute([$store_id, $pid]);
                $existingData = $existing->fetch();
                if ($existingData) {
                    $db->prepare("UPDATE inventory_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$rqty, $existingData['id']]);
                } else {
                    $db->prepare("INSERT INTO inventory_items (store_id, product_id, quantity, unit_cost, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
                       ->execute([$store_id, $pid, $rqty, $cost]);
                }
                // Record movement
                $db->prepare("INSERT INTO inventory_movements (store_id, product_id, movement_type, reference_type, reference_id, reference_number, quantity, unit_cost, total_cost, notes, created_by) VALUES (?, ?, 'sale_return', 'sale_return', ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$store_id, $pid, $ret_id, $ret_number, $rqty, $cost, $rqty * $cost, 'مرتجع بيع ' . $ret_number, $_SESSION['user_id']]);
            }
        }
        
        // Credit customer balance
        if ($customer_id && $subtotal > 0) {
            $lastBal = $db->query("SELECT balance_after FROM customer_transactions WHERE customer_id = $customer_id ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
            $newBal = floatval($lastBal) - $subtotal;
            $db->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, reference_type, reference_id, reference_number, debit, credit, balance_after, notes, created_by, created_at) VALUES (?, 'sale_return', 'sale_return', ?, ?, 0, ?, ?, ?, ?, NOW())")
               ->execute([$customer_id, $ret_id, $ret_number, $subtotal, $newBal, 'مرتجع بيع ' . $ret_number, $_SESSION['user_id']]);
        }
        
        logActivity('sale_return_create', 'sale_returns', $ret_id);
        $db->commit();
        $_SESSION['success'] = 'تم إنشاء المرتجع ' . $ret_number . ' بنجاح';
        redirect(APP_URL . '/modules/sales/returns/');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
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
:root{--ret-red:#c0392b;--primary:#667eea;}
*{box-sizing:border-box}body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto;min-width:1400px}
.top-header{background:var(--ret-red);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--ret-red);color:#fff;border-color:var(--ret-red)}
.items-table{width:100%;border-collapse:collapse;font-size:12px}
.items-table th{background:var(--ret-red);color:#fff;padding:6px;text-align:center}
.items-table td{padding:4px 6px;text-align:center;border-bottom:1px solid #e9ecef}
.items-table tr:hover td{background:#fdeaea}
.items-table tr.selected td{background:#fff3cd}
.bottom-bar{background:#fdeaea;border-top:2px solid var(--ret-red);padding:10px 20px}
.select-section{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
.customer-display{background:#e3f2fd;border:1px solid #90caf9;border-radius:6px;padding:6px 12px;display:none}
.customer-display.show{display:flex;align-items:center;gap:10px}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="./" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
</div>
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="selectAll()" title="تحديد الكل"><i class="bi bi-check-all"></i></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="saveRet()" title="حفظ"><i class="bi bi-save"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px">اختر العميل ثم الفاتورة</div>
</div>

<div class="select-section">
<div class="row g-2 align-items-end">
    <div class="col-md-3">
        <label class="form-label small fw-bold text-danger">العميل <span class="text-danger">*</span></label>
        <select id="customerSelect" class="form-select form-select-sm" onchange="loadCustomerInvoices()">
            <option value="">-- اختر العميل --</option>
            <?php foreach($customers as $c){ ?><option value="<?= $c['id'] ?>"><?= $c['customer_name'] ?> (<?= $c['customer_code'] ?>)</option><?php } ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-bold text-danger">فاتورة البيع <span class="text-danger">*</span></label>
        <select id="invoiceSelect" class="form-select form-select-sm" onchange="loadInvoiceItems()" disabled>
            <option value="">-- اختر الفاتورة --</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small text-muted">المخزن <span class="text-danger">*</span></label>
        <select name="store_id" id="store_id" class="form-select form-select-sm" required>
            <option value="">-- اختر المخزن --</option>
            <?php 
            $stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();
            foreach($stores as $st){ ?><option value="<?= $st['id'] ?>"><?= $st['store_name'] ?></option><?php } ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small text-muted">تاريخ المرتجع</label>
        <input type="date" name="return_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-2 text-start">
        <span class="badge bg-danger fs-6"><i class="bi bi-arrow-return-left"></i> مرتجع بيع</span>
    </div>
</div>
</div>

<form method="POST" id="retForm">
<input type="hidden" name="customer_id" id="formCustomerId" value="">
<input type="hidden" name="invoice_id" id="formInvoiceId" value="">

<div class="items-section p-3" style="overflow-x:auto">
<table class="items-table" id="itemsTable">
<thead>
<tr>
    <th><input type="checkbox" id="checkAll" onchange="toggleAll()"></th>
    <th>#</th><th style="min-width:200px">الصنف</th><th>كود</th><th>باركود</th>
    <th>الكمية</th><th>الرصيد</th><th>سعر البيع</th><th>الصلاحية</th><th>الكمية المرجعة</th><th>الإجمالي</th><th>السبب</th>
</tr>
</thead>
<tbody id="itemsBody">
<tr><td colspan="12" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>اختر العميل ثم الفاتورة</td></tr>
</tbody>
</table>
</div>

<div class="bottom-bar">
<div class="row w-100 align-items-center g-2">
    <div class="col-md-8"><input type="text" name="notes" class="form-control" placeholder="سبب الإرجاع..."></div>
    <div class="col-md-2 text-start"><button type="submit" class="btn btn-danger btn-lg px-4"><i class="bi bi-arrow-return-left"></i> حفظ المرتجع</button></div>
</div>
</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let invData=[];
function loadCustomerInvoices(){
    const cid=document.getElementById('customerSelect').value;
    const sel=document.getElementById('invoiceSelect');
    document.getElementById('formCustomerId').value=cid;
    sel.innerHTML='<option value="">-- جاري التحميل --</option>';
    sel.disabled=true;
    if(!cid){sel.innerHTML='<option value="">-- اختر الفاتورة --</option>';return;}
    fetch('ajax_get_invoices.php?customer_id='+cid).then(r=>r.json()).then(data=>{
        let h='<option value="">-- اختر الفاتورة --</option>';
        data.forEach(i=>{h+='<option value="'+i.id+'">'+i.invoice_number+' - '+i.invoice_date+' ('+parseFloat(i.grand_total).toFixed(2)+' ج)</option>';});
        sel.innerHTML=h;sel.disabled=false;
    });
}
function loadInvoiceItems(){
    const invId=document.getElementById('invoiceSelect').value;
    document.getElementById('formInvoiceId').value=invId;
    if(!invId){document.getElementById('itemsBody').innerHTML='<tr><td colspan="12" class="text-center text-muted py-5">اختر الفاتورة</td></tr>';return;}
    fetch('ajax_get_invoice_items.php?invoice_id='+invId).then(r=>r.json()).then(data=>{
        if(data.store_id)document.getElementById('store_id').value=data.store_id;
        invData=data.items||[];
        let h='';
        invData.forEach((it,idx)=>{
            h+='<tr id="row_'+idx+'" data-idx="'+idx+'">'+
                '<td><input type="checkbox" id="chk_'+idx+'" onchange="toggleRow('+idx+')"></td>'+
                '<td>'+(idx+1)+'<input type="hidden" name="items['+idx+'][invoice_item_id]" value="'+it.invoice_item_id+'"><input type="hidden" name="items['+idx+'][product_id]" value="'+(it.product_id||'')+'"></td>'+
                '<td><strong>'+it.product_name+'</strong><input type="hidden" name="items['+idx+'][product_name]" value="'+it.product_name+'"></td>'+
                '<td>'+(it.product_code||'-')+'<input type="hidden" name="items['+idx+'][product_code]" value="'+(it.product_code||'')+'"></td>'+
                '<td>'+(it.barcode||'-')+'<input type="hidden" name="items['+idx+'][barcode]" value="'+(it.barcode||'')+'"></td>'+
                '<td><input type="number" class="form-control form-control-sm" value="'+it.quantity+'" readonly style="background:#e9ecef"></td>'+
                '<td><input type="number" class="form-control form-control-sm" value="'+(it.current_stock||0)+'" readonly style="background:#e9ecef"></td>'+
                '<td><input type="number" name="items['+idx+'][sell_price]" id="sp_'+idx+'" class="form-control form-control-sm" value="'+it.sell_price+'" step="0.01" readonly style="background:#e9ecef"></td>'+
                '<td><input type="month" class="form-control form-control-sm" value="'+(it.expiry_date?it.expiry_date.substring(0,7):'')+'" readonly style="background:#e9ecef;font-size:11px"></td>'+
                '<td><input type="number" name="items['+idx+'][return_qty]" id="rqty_'+idx+'" class="form-control form-control-sm" value="0" step="0.001" min="0" oninput="calcRow('+idx+')" style="font-weight:700;color:var(--ret-red)"></td>'+
                '<td><input type="number" id="rtotal_'+idx+'" class="form-control form-control-sm" value="0" step="0.01" readonly style="background:#fdeaea;font-weight:700"></td>'+
                '<td><input type="text" name="items['+idx+'][reason]" class="form-control form-control-sm" placeholder="سبب"></td>'+
            '</tr>';
        });
        document.getElementById('itemsBody').innerHTML=h;
    });
}
function toggleAll(){
    const ca=document.getElementById('checkAll').checked;
    document.querySelectorAll('input[type=checkbox]').forEach(chk=>{
        if(chk.id==='checkAll')return;
        chk.checked=ca;
        const idx=parseInt(chk.id.replace('chk_',''));
        if(!isNaN(idx)){const q=document.getElementById('rqty_'+idx);if(ca&&parseFloat(q.value)===0)q.value=invData[idx]?.quantity||0;calcRow(idx);}
    });
}
function toggleRow(idx){
    const chk=document.getElementById('chk_'+idx);
    if(chk.checked){const q=document.getElementById('rqty_'+idx);if(parseFloat(q.value)===0)q.value=invData[idx]?.quantity||0;}
    else{document.getElementById('rqty_'+idx).value=0;}
    calcRow(idx);
}
function calcRow(idx){
    const rq=parseFloat(document.getElementById('rqty_'+idx).value)||0;
    const sp=parseFloat(document.getElementById('sp_'+idx).value)||0;
    document.getElementById('rtotal_'+idx).value=(rq*sp).toFixed(2);
}
function saveRet(){document.getElementById('retForm').submit();}
<?php if(isset($error)): ?>alert('خطأ: <?= addslashes($error) ?>');<?php endif; ?>
</script>
</body>
</html>