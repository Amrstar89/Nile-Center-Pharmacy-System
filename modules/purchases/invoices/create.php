<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فاتورة شراء جديدة';

// Get store list
$stores = $db->query("SELECT id, store_name, branch_id FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();
// Get suppliers
$suppliers = $db->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();
// Get units
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_invoices WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $inv_number = 'PINV-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        $supplier_id = intval($_POST['supplier_id']);
        $store_id = intval($_POST['store_id'] ?? 0);
        $invoice_date = $_POST['invoice_date'] ?: date('Y-m-d');
        $due_date = $_POST['due_date'] ?: null;
        $payment_method = $_POST['payment_method'] ?? 'credit';
        $discount_type = $_POST['discount_type'] ?: null;
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $vat_percent = floatval($_POST['vat_percent'] ?? 14);
        $shipping = floatval($_POST['shipping_cost'] ?? 0);
        $extra_discount_pct = floatval($_POST['extra_discount_pct'] ?? 0);
        $extra_discount_val = floatval($_POST['extra_discount_val'] ?? 0);
        $extra_charge = floatval($_POST['extra_charge'] ?? 0);
        $supplier_inv_no = $_POST['supplier_invoice_no'] ?? null;
        $notes = $_POST['notes'] ?? '';
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        
        // Calculate totals
        $subtotal = 0;
        foreach ($_POST['items'] as $item) {
            $qty = floatval($item['quantity']);
            $cost = floatval($item['unit_cost']);
            $discPct = floatval($item['discount_percent'] ?? 0);
            $line = $qty * $cost * (1 - $discPct/100);
            $subtotal += $line;
        }
        
        $discount = $discount_type === 'percentage' ? $subtotal * ($discount_value/100) : $discount_value;
        $afterDisc = $subtotal - $discount;
        $vat = $afterDisc * ($vat_percent/100);
        $grand = $afterDisc + $vat + $shipping + $extra_charge - $extra_discount_val;
        if ($extra_discount_pct > 0) {
            $grand = $grand * (1 - $extra_discount_pct/100);
        }
        
        $status = $paid_amount >= $grand ? 'paid' : ($paid_amount > 0 ? 'partial' : 'open');
        if ($payment_method === 'credit') $status = 'open';
        
        $db->prepare("
            INSERT INTO purchase_invoices (invoice_number, supplier_id, store_id, invoice_date, due_date, status,
                subtotal, discount_type, discount_value, vat_percent, vat_amount, shipping_cost, grand_total, paid_amount,
                extra_discount_pct, extra_discount_val, extra_charge, payment_method,
                supplier_invoice_no, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$inv_number, $supplier_id, $store_id ?: null, $invoice_date, $due_date, $status,
            $subtotal, $discount_type, $discount_value, $vat_percent, $vat, $shipping, $grand, $paid_amount,
            $extra_discount_pct, $extra_discount_val, $extra_charge, $payment_method,
            $supplier_inv_no, $notes, $_SESSION['user_id']]);
        
        $inv_id = $db->lastInsertId();
        
        $itemStmt = $db->prepare("
            INSERT INTO purchase_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_id, unit_name,
                quantity, unit_cost, sell_price, discount_percent, vat_percent, expiry_date, batch_number, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_POST['items'] as $item) {
            $pid = intval($item['product_id'] ?? 0);
            $qty = floatval($item['quantity']);
            $cost = floatval($item['unit_cost']);
            $discPct = floatval($item['discount_percent'] ?? 0);
            $line = $qty * $cost * (1 - $discPct/100);
            $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'], $item['product_code'] ?? null,
                $item['barcode'] ?? null, intval($item['unit_id'] ?? 0) ?: null, $item['unit_name'] ?? 'علبة',
                $qty, $cost, floatval($item['sell_price'] ?? 0), $discPct, $vat_percent,
                $item['expiry_date'] ?: null, $item['batch_number'] ?? null, $line]);
            
            // Update inventory
            if ($store_id && $pid) {
                $existing = $db->prepare("SELECT id, quantity FROM inventory_items WHERE store_id = ? AND product_id = ?")->execute([$store_id, $pid]) ? $db->prepare("SELECT id, quantity FROM inventory_items WHERE store_id = ? AND product_id = ?")->fetch() : null;
                if ($existing) {
                    $db->prepare("UPDATE inventory_items SET quantity = quantity + ?, unit_cost = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$qty, $cost, $existing['id']]);
                } else {
                    $db->prepare("INSERT INTO inventory_items (store_id, product_id, quantity, unit_cost, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
                       ->execute([$store_id, $pid, $qty, $cost]);
                }
            }
        }
        
        if ($paid_amount > 0) {
            $db->prepare("INSERT INTO supplier_payments (payment_number, supplier_id, invoice_id, amount, payment_date, payment_method, created_by)
                VALUES (?, ?, ?, ?, NOW(), 'cash', ?)
            ")->execute(['PAY' . time(), $supplier_id, $inv_id, $paid_amount, $_SESSION['user_id']]);
        }
        
        logActivity('purchase_invoice_create', 'purchase_invoices', $inv_id);
        $db->commit();
        
        $_SESSION['success'] = 'تم إنشاء فاتورة الشراء ' . $inv_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/invoices/view.php?id=' . $inv_id);
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../../includes/sidebar.php';
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
:root{--primary:#667eea;--secondary:#764ba2;--sidebar-bg:#1a1a2e;--green:#198754;--red:#dc3545;--orange:#ff9800;}
*{box-sizing:border-box}
body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0}
.main-content{padding:0;margin-right:0}

/* Top Header Bar */
.top-header{background:var(--sidebar-bg);color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.15);color:#fff}
.top-header .menu-item i{margin-left:6px}

/* Sub Menu */
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:10px;align-items:center}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.sub-menu-bar .btn-icon.active{background:var(--primary);color:#fff}
.sub-menu-bar .btn-icon.danger:hover{background:var(--red);border-color:var(--red)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}

/* Invoice Header */
.invoice-header{background:#fff;padding:15px 20px;border-bottom:1px solid #ddd}
.invoice-header .row{align-items:end}

/* Toolbar right side */
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,#ffc107 0%,#ff9800 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.3);color:#333;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}

/* Items Table */
.items-section{flex:1;overflow:auto;padding:0 20px;background:#fff;margin:0 55px 0 0}
.items-table{width:100%;border-collapse:collapse;font-size:13px;min-width:1400px}
.items-table th{background:var(--green);color:#fff;padding:8px 6px;text-align:center;font-weight:600;font-size:12px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table th:first-child{width:30px}
.items-table td{padding:4px 6px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff}
.items-table tr:hover td{background:#e3f2fd}
.items-table td input,.items-table td select{width:100%;border:1px solid #ddd;border-radius:4px;padding:3px 5px;font-size:12px;text-align:center}
.items-table td input:focus{border-color:var(--primary);outline:none}
.items-table .product-name-input{text-align:right;font-weight:600}
.items-table .num-input{text-align:center}
.items-table .row-total{font-weight:700;color:var(--primary)}
.items-table .btn-delete{color:var(--red);cursor:pointer;font-size:16px;padding:0 4px}
.items-table .btn-delete:hover{transform:scale(1.2)}
.items-table .barcode-wrap{position:relative;display:flex;align-items:center}
.items-table .barcode-wrap input{flex:1;padding-left:28px}
.items-table .barcode-wrap .btn-f2{position:absolute;left:2px;top:2px;bottom:2px;width:24px;border:none;background:#f0f0f0;border-radius:3px;cursor:pointer;font-size:10px;color:#666;display:flex;align-items:center;justify-content:center}
.items-table .barcode-wrap .btn-f2:hover{background:var(--primary);color:#fff}

/* Bottom Summary Bar */
.bottom-bar{background:#f8f9fa;border-top:2px solid #ddd;padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .summary-row{display:flex;gap:15px;align-items:center;flex-wrap:wrap;margin-bottom:8px}
.bottom-bar .summary-item{display:flex;align-items:center;gap:5px;background:#fff;padding:5px 12px;border-radius:8px;border:1px solid #ddd;font-size:13px}
.bottom-bar .summary-item label{color:#666;font-size:11px}
.bottom-bar .summary-item strong{color:#333;font-size:14px}
.bottom-bar .summary-item .form-control-sm{width:80px;padding:3px 6px;font-size:12px}
.bottom-bar .grand-total{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:8px 20px;border-radius:10px;font-size:18px;font-weight:700}

/* Payment method */
.payment-method{display:flex;align-items:center;gap:8px}
.payment-method select{padding:5px 10px;border-radius:6px;border:1px solid #ddd}

/* Supplier section */
.supplier-section{background:#fff3cd;padding:10px 20px;border-top:1px solid #ffc107;display:flex;gap:20px;align-items:center}

/* Print */
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon{display:none!important}}

/* Responsive */
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
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?> | <?= arabicDate(date('Y-m-d')) ?></div>
</div>

<!-- Sub Menu Toolbar -->
<div class="sub-menu-bar">
    <div class="btn-icon" onclick="addRow()" title="إضافة صنف (F3)"><i class="bi bi-plus-lg"></i></div>
    <div class="btn-icon" onclick="openF2Search()" title="بحث عن صنف (F2)"><i class="bi bi-search"></i></div>
    <div class="btn-icon" onclick="clearAll()" title="مسح الكل"><i class="bi bi-trash"></i></div>
    <div class="divider"></div>
    <div class="btn-icon" onclick="window.print()" title="طباعة (Ctrl+P)"><i class="bi bi-printer"></i></div>
    <div class="btn-icon" onclick="saveInvoice()" title="حفظ (Ctrl+S)"><i class="bi bi-save"></i></div>
    <div class="divider"></div>
    <div class="btn-icon danger" onclick="window.location='../'" title="خروج"><i class="bi bi-x-lg"></i></div>
    <div class="ms-auto text-muted" style="font-size:12px">
        <i class="bi bi-info-circle"></i> F2=بحث | F3=إضافة | Ctrl+S=حفظ | Delete=حذف صنف
    </div>
</div>

<!-- Invoice Header Info -->
<div class="invoice-header">
    <form method="POST" id="invoiceForm">
    <div class="row g-3">
        <div class="col-md-2">
            <label class="form-label small text-muted">رقم الفاتورة</label>
            <input type="text" class="form-control form-control-sm" value="تلقائي" readonly style="background:#e9ecef;font-weight:700">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted">تاريخ الفاتورة</label>
            <input type="date" name="invoice_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted">تاريخ الاستحقاق</label>
            <input type="date" name="due_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted">المخزن <span class="text-danger">*</span></label>
            <select name="store_id" id="store_id" class="form-select form-select-sm" required>
                <option value="">-- اختر --</option>
                <?php foreach($stores as $st){ ?><option value="<?= $st['id'] ?>"><?= $st['store_name'] ?></option><?php } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted">رقم فاتورة المورد</label>
            <input type="text" name="supplier_invoice_no" class="form-control form-control-sm" placeholder="اختياري">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted">الضريبة %</label>
            <input type="number" name="vat_percent" id="vatPercent" class="form-control form-control-sm" value="14" step="0.01" oninput="recalcAll()">
        </div>
    </div>
</div>

<!-- Toolbar Right Side -->
<div class="toolbar-right">
    <button type="button" class="tool-btn" onclick="addRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صنف</span></button>
    <button type="button" class="tool-btn" onclick="openF2Search()"><i class="bi bi-search"></i><span class="tooltip">بحث F2</span></button>
    <button type="button" class="tool-btn" onclick="window.print()"><i class="bi bi-printer"></i><span class="tooltip">طباعة</span></button>
    <div style="height:1px;background:rgba(255,255,255,0.3);margin:4px 0"></div>
    <button type="button" class="tool-btn" onclick="clearAll()" style="color:var(--red)"><i class="bi bi-trash"></i><span class="tooltip">مسح</span></button>
    <button type="button" class="tool-btn" onclick="window.location='../'" style="color:var(--red)"><i class="bi bi-x-lg"></i><span class="tooltip">خروج</span></button>
</div>

<!-- Items Section -->
<div class="items-section">
    <table class="items-table" id="itemsTable">
        <thead>
            <tr>
                <th>#</th>
                <th style="min-width:90px">الباركود</th>
                <th style="min-width:80px">كود الصنف</th>
                <th style="min-width:200px">اسم الصنف</th>
                <th style="min-width:70px">الوحدة</th>
                <th style="min-width:70px">الكمية</th>
                <th style="min-width:60px">الرصيد</th>
                <th style="min-width:80px">سعر البيع</th>
                <th style="min-width:55px">ن الخصم</th>
                <th style="min-width:80px">ق الخصم</th>
                <th style="min-width:55px">ن الضريبة</th>
                <th style="min-width:80px">ق الضريبة</th>
                <th style="min-width:80px">سعر الشراء</th>
                <th style="min-width:90px">الإجمالي</th>
                <th style="min-width:55px">ف الربح</th>
                <th style="min-width:55px">ن الربح</th>
                <th style="min-width:100px">الشركة</th>
                <th style="min-width:80px">الموقع</th>
                <th style="min-width:50px">الصلاحية</th>
                <th style="min-width:50px">الباتش</th>
                <th style="width:30px"></th>
            </tr>
        </thead>
        <tbody id="itemsBody">
        </tbody>
    </table>
</div>

<!-- Bottom Summary Bar -->
<div class="bottom-bar">
    <div class="summary-row">
        <div class="summary-item"><label>عدد الأصناف:</label><strong id="sumItemCount">0</strong></div>
        <div class="summary-item"><label>عدد الباركود:</label><strong id="sumBarcodeCount">0</strong></div>
        <div class="summary-item"><label>سعر بيع:</label><strong id="sumSellPrice">0.00</strong></div>
        <div class="summary-item"><label>الضريبة:</label><strong id="sumVat">0.00</strong></div>
        <div class="summary-item"><label>سعر الشراء:</label><strong id="sumCost">0.00</strong></div>
        <div class="summary-item"><label>ف الربح:</label><strong id="sumProfitVal">0.00</strong></div>
        <div class="summary-item"><label>ن الربح:</label><strong id="sumProfitPct">0.00%</strong></div>
        <div class="ms-auto grand-total">الصافي: <span id="sumGrand">0.00</span> ج</div>
    </div>
    <div class="summary-row">
        <div class="summary-item"><label>خصم إضافي %:</label><input type="number" name="extra_discount_pct" id="extraDiscPct" class="form-control-sm" value="0" step="0.01" oninput="recalcAll()"></div>
        <div class="summary-item"><label>خصم إضافي قيمة:</label><input type="number" name="extra_discount_val" id="extraDiscVal" class="form-control-sm" value="0" step="0.01" oninput="recalcAll()"></div>
        <div class="summary-item"><label>م إضافية قيمة:</label><input type="number" name="extra_charge" id="extraCharge" class="form-control-sm" value="0" step="0.01" oninput="recalcAll()"></div>
        <div class="summary-item"><label>الشحن:</label><input type="number" name="shipping_cost" id="shippingCost" class="form-control-sm" value="0" step="0.01" oninput="recalcAll()"></div>
        <div class="summary-item"><label>الخصم على الفاتورة:</label>
            <select name="discount_type" id="discType" class="form-select-sm" style="width:70px" onchange="recalcAll()">
                <option value="">لا</option><option value="percentage">%</option><option value="fixed">مبلغ</option>
            </select>
            <input type="number" name="discount_value" id="discValue" class="form-control-sm" value="0" step="0.01" style="width:70px" oninput="recalcAll()">
        </div>
        <div class="summary-item"><label>مدفوع الآن:</label><input type="number" name="paid_amount" id="paidAmount" class="form-control-sm" value="0" step="0.01" style="width:90px"></div>
        <div class="ms-auto payment-method">
            <label>طريقة السداد:</label>
            <select name="payment_method" class="form-select-sm">
                <option value="credit">آجل</option><option value="cash">كاش</option><option value="bank_transfer">تحويل بنكي</option>
            </select>
        </div>
    </div>
</div>

<!-- Supplier Section -->
<div class="supplier-section">
    <div class="row w-100 align-items-center g-3">
        <div class="col-md-3">
            <label class="small fw-bold">المورد <span class="text-danger">*</span></label>
            <select name="supplier_id" class="form-select" required>
                <option value="">-- اختر المورد --</option>
                <?php foreach($suppliers as $s){ ?><option value="<?= $s['id'] ?>"><?= $s['supplier_name'] ?> (<?= $s['supplier_code'] ?>)</option><?php } ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="small fw-bold">ملاحظات</label>
            <input type="text" name="notes" class="form-control" placeholder="أي ملاحظات...">
        </div>
        <div class="col-md-3 text-start">
            <button type="submit" class="btn btn-success btn-lg px-5"><i class="bi bi-save"></i> حفظ الفاتورة</button>
        </div>
    </div>
</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../js/product-search.js"></script>
<script>
let rowCount = 0;
const units = <?= json_encode($units) ?>;

function addRow(data) {
    rowCount++;
    const id = rowCount;
    let unitOpts = '<option value="">--</option>';
    units.forEach(u => unitOpts += '<option value="'+u.id+'">'+u.unit_name_ar+'</option>');
    
    const barcodeVal = data && data.barcode ? data.barcode : '';
    const codeVal = data && data.product_code ? data.product_code : '';
    const nameVal = data && data.product_name ? data.product_name : '';
    const costVal = data && data.unit_cost ? data.unit_cost : '';
    const sellVal = data && data.sell_price ? data.sell_price : '';
    const pidVal = data && data.product_id ? data.product_id : '';
    const stockVal = data && data.stock_qty ? data.stock_qty : '';
    const companyVal = data && data.company_name ? data.company_name : '';
    const locationVal = data && data.location ? data.location : '';
    const qtyVal = data && data.quantity ? data.quantity : '1';
    
    const tr = document.createElement('tr');
    tr.id = 'row_'+id;
    tr.dataset.rowId = id;
    tr.innerHTML = `
        <td>${id}<input type="hidden" name="items[${id}][product_id]" value="${pidVal}"></td>
        <td><div class="barcode-wrap"><input type="text" name="items[${id}][barcode]" id="bc_${id}" class="form-control form-control-sm" value="${barcodeVal}" placeholder="باركود"><button type="button" class="btn-f2" onclick="openF2ForRow(${id})">F2</button></div></td>
        <td><input type="text" name="items[${id}][product_code]" id="code_${id}" class="form-control form-control-sm" value="${codeVal}" placeholder="الكود"></td>
        <td><input type="text" name="items[${id}][product_name]" id="name_${id}" class="form-control form-control-sm product-name-input" value="${nameVal}" placeholder="اسم الصنف" required></td>
        <td><select name="items[${id}][unit_id]" id="unit_${id}" class="form-select form-select-sm">${unitOpts}</select><input type="hidden" name="items[${id}][unit_name]" id="unitName_${id}" value=""></td>
        <td><input type="number" name="items[${id}][quantity]" id="qty_${id}" class="form-control form-control-sm num-input" value="${qtyVal}" step="0.001" min="0.001" required oninput="calcRow(${id})"></td>
        <td><input type="text" id="stock_${id}" class="form-control form-control-sm num-input" value="${stockVal}" readonly style="background:#e9ecef;color:#666" title="الرصيد الحالي"></td>
        <td><input type="number" name="items[${id}][sell_price]" id="sell_${id}" class="form-control form-control-sm num-input" value="${sellVal}" step="0.01" oninput="calcRow(${id})"></td>
        <td><input type="number" id="discPct_${id}" class="form-control form-control-sm num-input" value="0" step="0.01" oninput="calcRow(${id})"></td>
        <td><input type="number" id="discVal_${id}" class="form-control form-control-sm num-input" value="0" step="0.01" readonly style="background:#e9ecef"></td>
        <td><input type="number" name="items[${id}][vat_percent]" id="vat_${id}" class="form-control form-control-sm num-input" value="${document.getElementById('vatPercent').value}" step="0.01" readonly style="background:#e9ecef"></td>
        <td><input type="number" id="vatVal_${id}" class="form-control form-control-sm num-input" value="0" step="0.01" readonly style="background:#e9ecef"></td>
        <td><input type="number" name="items[${id}][unit_cost]" id="cost_${id}" class="form-control form-control-sm num-input" value="${costVal}" step="0.01" min="0" required oninput="calcRow(${id})"></td>
        <td><input type="number" id="total_${id}" class="form-control form-control-sm num-input row-total" value="0" step="0.01" readonly style="background:#e8f5e9"></td>
        <td><input type="number" id="profitVal_${id}" class="form-control form-control-sm num-input" value="0" step="0.01" readonly style="background:#e9ecef"></td>
        <td><input type="number" id="profitPct_${id}" class="form-control form-control-sm num-input" value="0" step="0.01" readonly style="background:#e9ecef"></td>
        <td><input type="text" id="company_${id}" class="form-control form-control-sm" value="${companyVal}" readonly style="background:#e9ecef;color:#666;font-size:11px" title="الشركة المنتجة"></td>
        <td><input type="text" id="location_${id}" class="form-control form-control-sm" value="${locationVal}" readonly style="background:#e9ecef;color:#666;font-size:11px" title="موقع الصنف"></td>
        <td><input type="date" name="items[${id}][expiry_date]" class="form-control form-control-sm" style="font-size:11px"></td>
        <td><input type="text" name="items[${id}][batch_number]" class="form-control form-control-sm" placeholder="باتش" style="font-size:11px"></td>
        <td><span class="btn-delete" onclick="deleteRow(${id})"><i class="bi bi-trash-fill"></i></span></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    
    // Bind F2 on barcode input
    document.getElementById('bc_'+id).addEventListener('keydown', function(e){
        if (e.key === 'F2') { e.preventDefault(); openF2ForRow(id); }
    });
    document.getElementById('name_'+id).addEventListener('keydown', function(e){
        if (e.key === 'F2') { e.preventDefault(); openF2ForRow(id); }
    });
    
    if (data) calcRow(id);
    recalcAll();
    return id;
}

function deleteRow(id) {
    const row = document.getElementById('row_'+id);
    if (row) row.remove();
    recalcAll();
}

function clearAll() {
    if (!confirm('مسح كل الأصناف؟')) return;
    document.getElementById('itemsBody').innerHTML = '';
    rowCount = 0;
    recalcAll();
}

function calcRow(id) {
    const qty = parseFloat(document.getElementById('qty_'+id).value) || 0;
    const cost = parseFloat(document.getElementById('cost_'+id).value) || 0;
    const sell = parseFloat(document.getElementById('sell_'+id).value) || 0;
    const discPct = parseFloat(document.getElementById('discPct_'+id).value) || 0;
    const vatPct = parseFloat(document.getElementById('vat_'+id).value) || 0;
    
    // Line total after item discount
    const discVal = qty * cost * (discPct / 100);
    const afterDisc = qty * cost - discVal;
    
    // VAT on cost
    const vatVal = afterDisc * (vatPct / 100);
    
    // Profit
    const profitVal = (sell - cost) * qty;
    const profitPct = cost > 0 ? ((sell - cost) / cost) * 100 : 0;
    
    document.getElementById('discVal_'+id).value = discVal.toFixed(2);
    document.getElementById('vatVal_'+id).value = vatVal.toFixed(2);
    document.getElementById('total_'+id).value = afterDisc.toFixed(2);
    document.getElementById('profitVal_'+id).value = profitVal.toFixed(2);
    document.getElementById('profitPct_'+id).value = profitPct.toFixed(1);
    
    recalcAll();
}

function recalcAll() {
    let itemCount = 0, barcodeCount = 0, totalSell = 0, totalVat = 0, totalCost = 0;
    let totalProfitVal = 0;
    
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const id = tr.dataset.rowId;
        const qty = parseFloat(document.getElementById('qty_'+id).value) || 0;
        const cost = parseFloat(document.getElementById('cost_'+id).value) || 0;
        const sell = parseFloat(document.getElementById('sell_'+id).value) || 0;
        const vatVal = parseFloat(document.getElementById('vatVal_'+id).value) || 0;
        const bc = document.getElementById('bc_'+id).value;
        
        itemCount++;
        if (bc) barcodeCount++;
        totalSell += sell * qty;
        totalVat += vatVal;
        totalCost += cost * qty;
        totalProfitVal += (sell - cost) * qty;
    });
    
    const vatPctGlobal = parseFloat(document.getElementById('vatPercent').value) || 0;
    const discType = document.getElementById('discType').value;
    const discValGlobal = parseFloat(document.getElementById('discValue').value) || 0;
    const extraDiscPct = parseFloat(document.getElementById('extraDiscPct').value) || 0;
    const extraDiscVal = parseFloat(document.getElementById('extraDiscVal').value) || 0;
    const extraCharge = parseFloat(document.getElementById('extraCharge').value) || 0;
    const shipping = parseFloat(document.getElementById('shippingCost').value) || 0;
    
    let discount = discType === 'percentage' ? totalCost * (discValGlobal/100) : discValGlobal;
    let afterDisc = totalCost - discount;
    let vatTotal = afterDisc * (vatPctGlobal/100);
    let grand = afterDisc + vatTotal + shipping + extraCharge - extraDiscVal;
    if (extraDiscPct > 0) grand = grand * (1 - extraDiscPct/100);
    
    const profitPct = totalCost > 0 ? (totalProfitVal / totalCost) * 100 : 0;
    
    document.getElementById('sumItemCount').textContent = itemCount;
    document.getElementById('sumBarcodeCount').textContent = barcodeCount;
    document.getElementById('sumSellPrice').textContent = totalSell.toFixed(2);
    document.getElementById('sumVat').textContent = vatTotal.toFixed(2);
    document.getElementById('sumCost').textContent = totalCost.toFixed(2);
    document.getElementById('sumProfitVal').textContent = totalProfitVal.toFixed(2);
    document.getElementById('sumProfitPct').textContent = profitPct.toFixed(1) + '%';
    document.getElementById('sumGrand').textContent = grand.toFixed(3);
}

// F2 Search
function openF2Search() {
    const storeId = document.getElementById('store_id').value;
    if (!storeId) { alert('اختر المخزن أولاً'); document.getElementById('store_id').focus(); return; }
    ProductSearch.open({
        storeId: parseInt(storeId),
        onSelect: function(product) { addRow(product); }
    });
}

function openF2ForRow(id) {
    const storeId = document.getElementById('store_id').value;
    if (!storeId) { alert('اختر المخزن أولاً'); return; }
    ProductSearch.open({
        storeId: parseInt(storeId),
        onSelect: function(product) { fillRow(id, product); }
    });
}

function fillRow(id, product) {
    document.getElementById('name_'+id).value = product.product_name || '';
    document.getElementById('code_'+id).value = product.product_code || '';
    document.getElementById('bc_'+id).value = product.barcode || '';
    document.getElementById('cost_'+id).value = product.unit_cost || 0;
    document.getElementById('sell_'+id).value = product.sell_price || 0;
    document.getElementById('stock_'+id).value = product.stock_qty || 0;
    document.getElementById('company_'+id).value = product.company_name || '';
    document.getElementById('location_'+id).value = product.location || '';
    const pidInput = document.querySelector('#row_'+id+' input[name*="[product_id]"]');
    if (pidInput) pidInput.value = product.product_id || product.id || '';
    calcRow(id);
}

function onProductSelect(product) {
    addRow(product);
}

function saveInvoice() {
    document.getElementById('invoiceForm').submit();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); saveInvoice(); }
    if (e.key === 'F3') { e.preventDefault(); addRow(); }
    if (e.key === 'F2') {
        const active = document.activeElement;
        const row = active.closest('tr');
        if (row && row.dataset.rowId) {
            e.preventDefault();
            openF2ForRow(row.dataset.rowId);
        }
    }
});

// Add first row
addRow();
</script>
</body>
</html>
