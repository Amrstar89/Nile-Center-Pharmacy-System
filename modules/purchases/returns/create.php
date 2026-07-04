<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'مرتجع شراء جديد';

$invoices = $db->query("SELECT pi.id, pi.invoice_number, s.supplier_name, s.id as supplier_id, pi.store_id FROM purchase_invoices pi JOIN suppliers s ON pi.supplier_id = s.id WHERE pi.status != 'cancelled' ORDER BY pi.created_at DESC LIMIT 50")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_returns WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $ret_number = 'PRET-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        $invoice_id = intval($_POST['invoice_id']);
        $inv = $db->prepare("SELECT supplier_id, store_id FROM purchase_invoices WHERE id = ?");
        $inv->execute([$invoice_id]);
        $invData = $inv->fetch();
        
        $subtotal = 0;
        $itemCount = 0;
        foreach ($_POST['items'] as $item) {
            $qty = floatval($item['quantity']);
            if ($qty <= 0) continue;
            $subtotal += $qty * floatval($item['unit_cost']);
            $itemCount++;
        }
        
        if ($itemCount === 0) {
            throw new Exception('يجب إرجاع صنف واحد على الأقل');
        }
        
        $db->prepare("INSERT INTO purchase_returns (return_number, invoice_id, supplier_id, store_id, return_date, subtotal, grand_total, notes, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)")
           ->execute([$ret_number, $invoice_id, $invData['supplier_id'], $invData['store_id'], $subtotal, $subtotal, $_POST['notes'] ?? '', $_SESSION['user_id']]);
        
        $ret_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_return_items (return_id, invoice_item_id, product_id, product_name, product_code, quantity, unit_cost, line_total, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($_POST['items'] as $item) {
            $qty = floatval($item['quantity']);
            if ($qty <= 0) continue;
            $cost = floatval($item['unit_cost']);
            $line = $qty * $cost;
            $itemStmt->execute([$ret_id, $item['invoice_item_id'], $item['product_id'] ?: null, $item['product_name'], $item['product_code'] ?? '', $qty, $cost, $line, $item['reason'] ?? '']);
            
            // Update inventory - reduce quantity
            if ($invData['store_id'] && $item['product_id']) {
                $db->prepare("UPDATE inventory_items SET quantity = GREATEST(quantity - ?, 0), updated_at = NOW() WHERE store_id = ? AND product_id = ?")
                   ->execute([$qty, $invData['store_id'], $item['product_id']]);
            }
        }
        
        logActivity('purchase_return_create', 'purchase_returns', $ret_id);
        $db->commit();
        $_SESSION['success'] = 'تم إنشاء المرتجع ' . $ret_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/returns/');
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
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
:root{--primary:#667eea;--secondary:#764ba2;--sidebar-bg:#1a1a2e;--green:#198754;--red:#dc3545;--orange:#ff9800;--return-red:#c0392b}
*{box-sizing:border-box}
body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0}

/* Top Header Bar */
.top-header{background:var(--return-red);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.2);color:#fff}
.top-header .menu-item i{margin-left:6px}

/* Sub Menu */
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--return-red);color:#fff;border-color:var(--return-red)}
.sub-menu-bar .btn-icon.active{background:var(--return-red);color:#fff}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}

/* Invoice Header */
.invoice-header{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}

/* Toolbar right side - RED for returns */
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,var(--return-red) 0%,#e74c3c 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.25);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;color:var(--return-red);transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}

/* Items Table */
.items-section{flex:1;overflow:auto;padding:0 20px;background:#fff;margin:0 55px 0 0}
.items-table{width:100%;border-collapse:collapse;font-size:13px}
.items-table th{background:var(--return-red);color:#fff;padding:8px 6px;text-align:center;font-weight:600;font-size:12px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table th:first-child{width:30px}
.items-table td{padding:6px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff}
.items-table tr:hover td{background:#fdeaea}
.items-table tr.selected td{background:#fff3cd}
.items-table td input,.items-table td select{width:100%;border:1px solid #ddd;border-radius:4px;padding:4px 5px;font-size:12px;text-align:center}
.items-table td input:focus{border-color:var(--return-red);outline:none}
.items-table .product-name{text-align:right;font-weight:600}
.items-table .num-input{text-align:center}
.items-table .btn-delete{color:var(--red);cursor:pointer;font-size:16px;padding:0 4px}
.items-table .btn-delete:hover{transform:scale(1.2)}

/* Checkbox styling */
.items-table .row-check{width:18px;height:18px;cursor:pointer;accent-color:var(--return-red)}

/* Bottom Summary Bar */
.bottom-bar{background:#fdeaea;border-top:2px solid var(--return-red);padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .summary-row{display:flex;gap:15px;align-items:center;flex-wrap:wrap;margin-bottom:8px}
.bottom-bar .summary-item{display:flex;align-items:center;gap:5px;background:#fff;padding:5px 12px;border-radius:8px;border:1px solid #ddd;font-size:13px}
.bottom-bar .summary-item label{color:#666;font-size:11px}
.bottom-bar .summary-item strong{color:#333;font-size:14px}
.bottom-bar .grand-total{background:linear-gradient(135deg,var(--return-red),#e74c3c);color:#fff;padding:8px 20px;border-radius:10px;font-size:18px;font-weight:700}

/* Supplier section */
.supplier-section{background:#fff3cd;padding:10px 20px;border-top:1px solid #ffc107;display:flex;gap:20px;align-items:center}

/* Select invoice section */
.select-section{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}

/* Print */
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}

/* Responsive */
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}}
</style>
</head>
<body>

<!-- Top Header -->
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="../" class="menu-item"><i class="bi bi-arrow-right"></i> عودة</a>
    <a href="../invoices/" class="menu-item"><i class="bi bi-receipt"></i> فواتير الشراء</a>
    <a href="../orders/" class="menu-item"><i class="bi bi-file-earmark-text"></i> أوامر الشراء</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?> | <?= arabicDate(date('Y-m-d')) ?></div>
</div>

<!-- Sub Menu Toolbar -->
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="selectAll()" title="تحديد الكل"><i class="bi bi-check-all"></i></div>
    <div class="btn-icon" onclick="unselectAll()" title="إلغاء التحديد"><i class="bi bi-check-square"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة (Ctrl+P)"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="saveReturn()" title="حفظ المرتجع (Ctrl+S)"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" style="color:var(--red)" onclick="window.location='../'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px">
        <i class="bi bi-info-circle"></i> حدد الأصناف المراد إرجاعها واكتب الكمية | Ctrl+S=حفظ
    </div>
</div>

<!-- Select Invoice Section -->
<div class="select-section">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-bold text-danger">فاتورة الشراء الأصلية <span class="text-danger">*</span></label>
            <select id="invoiceSelect" class="form-select" onchange="loadInvoiceItems()">
                <option value="">-- اختر فاتورة الشراء --</option>
                <?php foreach($invoices as $inv): ?>
                <option value="<?= $inv['id'] ?>" data-supplier="<?= $inv['supplier_name'] ?>" data-supplier-id="<?= $inv['supplier_id'] ?>" data-store="<?= $inv['store_id'] ?>"><?= $inv['invoice_number'] ?> - <?= $inv['supplier_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted">المورد</label>
            <input type="text" id="supplierDisplay" class="form-control" readonly style="background:#e9ecef;font-weight:700" value="---">
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted">تاريخ المرتجع</label>
            <input type="date" class="form-control" value="<?= date('Y-m-d') ?>" readonly style="background:#e9ecef">
        </div>
        <div class="col-md-2 text-start">
            <span class="badge bg-danger fs-6"><i class="bi bi-arrow-return-left"></i> مرتجع شراء</span>
        </div>
    </div>
</div>

<form method="POST" id="returnForm">
<input type="hidden" name="invoice_id" id="formInvoiceId" value="">

<!-- Toolbar Right Side -->
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="selectAll()"><i class="bi bi-check-all"></i><span class="tooltip">تحديد الكل</span></button>
    <button type="button" class="tool-btn" onclick="unselectAll()"><i class="bi bi-check-square"></i><span class="tooltip">إلغاء التحديد</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="window.location='../'" style="color:#ffebee"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
</div>

<!-- Items Section -->
<div class="items-section">
    <table class="items-table" id="itemsTable">
        <thead>
            <tr>
                <th><input type="checkbox" id="checkAll" class="row-check" onchange="toggleAll()"></th>
                <th style="min-width:30px">#</th>
                <th style="min-width:200px">اسم الصنف</th>
                <th style="min-width:80px">كود الصنف</th>
                <th style="min-width:90px">الباركود</th>
                <th style="min-width:70px">الوحدة</th>
                <th style="min-width:80px">الكمية الأصلية</th>
                <th style="min-width:80px">سعر الشراء</th>
                <th style="min-width:100px">الكمية المرجعة</th>
                <th style="min-width:90px">الإجمالي</th>
                <th style="min-width:150px">السبب</th>
            </tr>
        </thead>
        <tbody id="itemsBody">
            <tr>
                <td colspan="11" class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size:48px"></i><br>
                    اختر فاتورة شراء لعرض الأصناف
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Bottom Summary Bar -->
<div class="bottom-bar">
    <div class="summary-row">
        <div class="summary-item"><label>عدد الأصناف:</label><strong id="sumItemCount">0</strong></div>
        <div class="summary-item"><label>عدد المرجع:</label><strong id="sumReturnCount">0</strong></div>
        <div class="summary-item"><label>إجمالي الشراء:</label><strong id="sumOriginal">0.00</strong></div>
        <div class="summary-item"><label>إجمالي المرتجع:</label><strong id="sumReturn" style="color:var(--return-red)">0.00</strong></div>
        <div class="ms-auto grand-total">صافي المرتجع: <span id="sumGrand">0.00</span> ج</div>
    </div>
</div>

<!-- Notes Section -->
<div class="supplier-section">
    <div class="row w-100 align-items-center g-3">
        <div class="col-md-8">
            <label class="small fw-bold">ملاحظات المرتجع</label>
            <input type="text" name="notes" class="form-control" placeholder="سبب الإرجاع أو أي ملاحظات...">
        </div>
        <div class="col-md-4 text-start">
            <button type="submit" class="btn btn-danger btn-lg px-5"><i class="bi bi-arrow-return-left"></i> حفظ المرتجع</button>
        </div>
    </div>
</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const invoiceItems = {};
let currentItems = [];

<?php
// Pre-load items for each invoice
foreach ($invoices as $inv) {
    $items = $db->prepare("SELECT pii.id as invoice_item_id, pii.product_id, pii.product_name, pii.product_code, pii.barcode, pii.unit_name, pii.quantity, pii.unit_cost, (pii.quantity * pii.unit_cost) as line_total FROM purchase_invoice_items pii WHERE pii.invoice_id = ? ORDER BY pii.id");
    $items->execute([$inv['id']]);
    $itemList = $items->fetchAll(PDO::FETCH_ASSOC);
    echo "invoiceItems[" . $inv['id'] . "] = " . json_encode($itemList) . ";\n";
}
?>

function loadInvoiceItems() {
    const select = document.getElementById('invoiceSelect');
    const invId = select.value;
    const container = document.getElementById('itemsBody');
    const supplierDisplay = document.getElementById('supplierDisplay');
    const formInvoiceId = document.getElementById('formInvoiceId');
    
    if (!invId || !invoiceItems[invId]) {
        container.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>اختر فاتورة شراء لعرض الأصناف</td></tr>';
        supplierDisplay.value = '---';
        formInvoiceId.value = '';
        currentItems = [];
        recalcAll();
        return;
    }
    
    const option = select.options[select.selectedIndex];
    supplierDisplay.value = option.dataset.supplier || '---';
    formInvoiceId.value = invId;
    currentItems = invoiceItems[invId];
    
    let html = '';
    currentItems.forEach((item, idx) => {
        html += `<tr id="row_${idx}" data-idx="${idx}">
            <td><input type="checkbox" class="row-check" id="chk_${idx}" onchange="toggleRow(${idx})"></td>
            <td>${idx + 1}</td>
            <td class="product-name"><strong>${item.product_name}</strong>
                <input type="hidden" name="items[${idx}][invoice_item_id]" value="${item.invoice_item_id}">
                <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id || ''}">
                <input type="hidden" name="items[${idx}][product_name]" value="${item.product_name}">
                <input type="hidden" name="items[${idx}][product_code]" value="${item.product_code || ''}">
            </td>
            <td>${item.product_code || '-'}</td>
            <td>${item.barcode || '-'}</td>
            <td>${item.unit_name || 'علبة'}</td>
            <td><input type="number" class="form-control form-control-sm num-input" value="${item.quantity}" readonly style="background:#e9ecef;font-weight:700"></td>
            <td><input type="number" name="items[${idx}][unit_cost]" id="cost_${idx}" class="form-control form-control-sm num-input" value="${item.unit_cost}" step="0.01" readonly style="background:#e9ecef"></td>
            <td><input type="number" name="items[${idx}][quantity]" id="qty_${idx}" class="form-control form-control-sm num-input" value="0" step="0.001" min="0" max="${item.quantity}" oninput="calcRow(${idx})" style="font-weight:700;color:var(--return-red)"></td>
            <td><input type="number" id="total_${idx}" class="form-control form-control-sm num-input" value="0" step="0.01" readonly style="background:#fdeaea;font-weight:700"></td>
            <td><input type="text" name="items[${idx}][reason]" class="form-control form-control-sm" placeholder="سبب الإرجاع"></td>
        </tr>`;
    });
    
    container.innerHTML = html;
    recalcAll();
}

function toggleAll() {
    const checkAll = document.getElementById('checkAll').checked;
    document.querySelectorAll('.row-check').forEach((chk, idx) => {
        chk.checked = checkAll;
        if (chk.id !== 'checkAll') {
            const rowIdx = parseInt(chk.id.replace('chk_', ''));
            const row = document.getElementById('row_' + rowIdx);
            if (row) {
                if (checkAll) {
                    row.classList.add('selected');
                    document.getElementById('qty_' + rowIdx).value = currentItems[rowIdx]?.quantity || 0;
                } else {
                    row.classList.remove('selected');
                    document.getElementById('qty_' + rowIdx).value = 0;
                }
                calcRow(rowIdx);
            }
        }
    });
}

function selectAll() {
    document.getElementById('checkAll').checked = true;
    toggleAll();
}

function unselectAll() {
    document.getElementById('checkAll').checked = false;
    toggleAll();
}

function toggleRow(idx) {
    const chk = document.getElementById('chk_' + idx);
    const row = document.getElementById('row_' + idx);
    if (chk.checked) {
        row.classList.add('selected');
        const qtyInput = document.getElementById('qty_' + idx);
        if (parseFloat(qtyInput.value) === 0) {
            qtyInput.value = currentItems[idx]?.quantity || 0;
        }
    } else {
        row.classList.remove('selected');
        document.getElementById('qty_' + idx).value = 0;
    }
    calcRow(idx);
}

function calcRow(idx) {
    const qty = parseFloat(document.getElementById('qty_' + idx).value) || 0;
    const cost = parseFloat(document.getElementById('cost_' + idx).value) || 0;
    const total = qty * cost;
    document.getElementById('total_' + idx).value = total.toFixed(2);
    
    const chk = document.getElementById('chk_' + idx);
    const row = document.getElementById('row_' + idx);
    if (qty > 0) {
        row.classList.add('selected');
        if (chk) chk.checked = true;
    } else {
        row.classList.remove('selected');
        if (chk) chk.checked = false;
    }
    
    recalcAll();
}

function recalcAll() {
    let itemCount = 0, returnCount = 0, totalOriginal = 0, totalReturn = 0;
    
    document.querySelectorAll('#itemsBody tr[data-idx]').forEach(tr => {
        const idx = parseInt(tr.dataset.idx);
        const qtyInput = document.getElementById('qty_' + idx);
        if (!qtyInput) return;
        
        const qty = parseFloat(qtyInput.value) || 0;
        const cost = parseFloat(document.getElementById('cost_' + idx).value) || 0;
        const origQty = currentItems[idx]?.quantity || 0;
        
        itemCount++;
        totalOriginal += origQty * cost;
        if (qty > 0) {
            returnCount++;
            totalReturn += qty * cost;
        }
    });
    
    document.getElementById('sumItemCount').textContent = itemCount;
    document.getElementById('sumReturnCount').textContent = returnCount;
    document.getElementById('sumOriginal').textContent = totalOriginal.toFixed(2);
    document.getElementById('sumReturn').textContent = totalReturn.toFixed(2);
    document.getElementById('sumGrand').textContent = totalReturn.toFixed(2);
}

function saveReturn() {
    const invId = document.getElementById('formInvoiceId').value;
    if (!invId) {
        alert('اختر فاتورة الشراء أولاً');
        return;
    }
    
    // Check at least one item has return qty > 0
    let hasReturn = false;
    document.querySelectorAll('#itemsBody tr[data-idx]').forEach(tr => {
        const idx = parseInt(tr.dataset.idx);
        const qty = parseFloat(document.getElementById('qty_' + idx).value) || 0;
        if (qty > 0) hasReturn = true;
    });
    
    if (!hasReturn) {
        alert('يجب إرجاع صنف واحد على الأقل');
        return;
    }
    
    document.getElementById('returnForm').submit();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); saveReturn(); }
});

<?php if(isset($error)): ?>
alert('خطأ: <?= addslashes($error) ?>');
<?php endif; ?>
</script>
</body>
</html>