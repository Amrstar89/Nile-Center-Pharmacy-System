<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فاتورة شراء جديدة';

// Get PO data if linked
$po_id = intval($_GET['po_id'] ?? 0);
$po_items = [];
$po_data = null;
if ($po_id) {
    $po_data = $db->prepare("SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?")->execute([$po_id]) ? $db->prepare("SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?")->fetch() : null;
    if ($po_data) {
        $po_items = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?")->execute([$po_id]) ? $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?")->fetchAll() : [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_invoices WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $inv_number = 'PINV-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $supplier_id = intval($_POST['supplier_id']);
        $po_id_post = intval($_POST['po_id'] ?? 0);
        $store_id = intval($_POST['store_id'] ?? 0);
        $invoice_date = $_POST['invoice_date'] ?: date('Y-m-d');
        $due_date = $_POST['due_date'] ?: null;
        $discount_type = $_POST['discount_type'] ?: null;
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $vat_percent = floatval($_POST['vat_percent'] ?? 0);
        $shipping = floatval($_POST['shipping_cost'] ?? 0);
        $supplier_inv_no = $_POST['supplier_invoice_no'] ?? null;
        $notes = $_POST['notes'] ?? '';
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);

        $subtotal = 0;
        foreach ($_POST['items'] as $item) {
            $qty = floatval($item['quantity']);
            $cost = floatval($item['unit_cost']);
            $disc = floatval($item['discount_percent'] ?? 0);
            $subtotal += $qty * $cost * (1 - $disc/100);
        }

        $discount = $discount_type === 'percentage' ? $subtotal * ($discount_value/100) : $discount_value;
        $afterDisc = $subtotal - $discount;
        $vat = $afterDisc * ($vat_percent/100);
        $grand = $afterDisc + $vat + $shipping;
        $status = $paid_amount >= $grand ? 'paid' : ($paid_amount > 0 ? 'partial' : 'open');

        $db->prepare("
            INSERT INTO purchase_invoices (invoice_number, po_id, supplier_id, store_id, invoice_date, due_date, status,
                subtotal, discount_type, discount_value, vat_percent, vat_amount, shipping_cost, grand_total, paid_amount,
                supplier_invoice_no, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$inv_number, $po_id_post ?: null, $supplier_id, $store_id ?: null, $invoice_date, $due_date, $status,
            $subtotal, $discount_type, $discount_value, $vat_percent, $vat, $shipping, $grand, $paid_amount,
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
            $disc = floatval($item['discount_percent'] ?? 0);
            $vat_p = floatval($item['vat_percent'] ?? 0);
            $line = $qty * $cost * (1 - $disc/100);
            $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'], $item['product_code'] ?? null,
                $item['barcode'] ?? null, intval($item['unit_id'] ?? 0) ?: null, $item['unit_name'] ?? 'علبة',
                $qty, $cost, floatval($item['sell_price'] ?? 0), $disc, $vat_p,
                $item['expiry_date'] ?: null, $item['batch_number'] ?? null, $line]);

            // Update inventory if store selected
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

        // Record payment if any
        if ($paid_amount > 0) {
            $db->prepare("INSERT INTO supplier_payments (payment_number, supplier_id, invoice_id, amount, payment_date, payment_method, created_by)
                VALUES (?, ?, ?, ?, NOW(), 'cash', ?)
            ")->execute(['PAY' . time(), $supplier_id, $inv_id, $paid_amount, $_SESSION['user_id']]);
        }

        // Update PO status to received if linked
        if ($po_id_post) {
            $db->prepare("UPDATE purchase_orders SET status = 'received' WHERE id = ?")->execute([$po_id_post]);
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

$suppliers = $db->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();
$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();

require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
.main-content{margin-right:260px;padding:20px}
.topbar{background:white;border-radius:15px;padding:15px 25px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center}
.sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px}
.item-row{background:#f8f9fa;border-radius:10px;padding:12px;margin-bottom:8px;border:1px solid #e9ecef}
.sumbar{background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;padding:15px 20px;border-radius:12px;position:sticky;bottom:20px;z-index:100}
@media(max-width:768px){.main-content{margin-right:0}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div><h5 class="mb-0"><i class="bi bi-receipt"></i> <?= $page_title ?></h5></div>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> عودة</a>
    </div>
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <form method="POST" id="invForm">
        <input type="hidden" name="po_id" value="<?= $po_id ?>">

        <div class="sec-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">المورد <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- اختر --</option>
                        <?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= ($po_data && $po_data['supplier_id']==$s['id'])?'selected':'' ?>><?= $s['supplier_name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">المخزن</label><select name="store_id" class="form-select"><option value="">-- اختر --</option><?php foreach($stores as $st): ?><option value="<?= $st['id'] ?>"><?= $st['store_name'] ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">تاريخ الفاتورة</label><input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-md-2"><label class="form-label">تاريخ الاستحقاق</label><input type="date" name="due_date" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">رقم فاتورة المورد</label><input type="text" name="supplier_invoice_no" class="form-control" placeholder="اختياري"></div>
            </div>
            <?php if($po_data): ?>
            <div class="alert alert-info mt-3"><i class="bi bi-link"></i> مرتبط بأمر الشراء: <strong><?= $po_data['po_number'] ?></strong></div>
            <?php endif; ?>
        </div>

        <div class="sec-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-boxes"></i> الأصناف</h6>
                <button type="button" class="btn btn-success btn-sm" onclick="addItem()"><i class="bi bi-plus-lg"></i> إضافة صنف</button>
            </div>
            <div id="itemsContainer">
                <?php if($po_items): foreach($po_items as $idx => $pi):
                    $itemId = $idx + 1;
                ?>
                <div class="item-row" id="item_<?= $itemId ?>">
                    <div class="row g-2">
                        <div class="col-md-2"><input type="text" name="items[<?= $itemId ?>][product_name]" class="form-control" value="<?= $pi['product_name'] ?>" placeholder="اسم الصنف" required></div>
                        <div class="col-md-1"><input type="text" name="items[<?= $itemId ?>][product_code]" class="form-control" value="<?= $pi['product_code'] ?>" placeholder="كود"></div>
                        <div class="col-md-1"><input type="text" name="items[<?= $itemId ?>][barcode]" class="form-control" value="<?= $pi['barcode'] ?>" placeholder="باركود"></div>
                        <div class="col-md-1"><input type="text" name="items[<?= $itemId ?>][unit_name]" class="form-control" value="<?= $pi['unit_name'] ?>" placeholder="وحدة"></div>
                        <div class="col-md-1"><input type="number" name="items[<?= $itemId ?>][quantity]" id="qty_<?= $itemId ?>" class="form-control" value="<?= $pi['quantity'] ?>" step="0.001" min="0.001" required oninput="calcTotal()"></div>
                        <div class="col-md-1"><input type="number" name="items[<?= $itemId ?>][unit_cost]" id="cost_<?= $itemId ?>" class="form-control" value="<?= $pi['unit_cost'] ?>" step="0.01" min="0" required oninput="calcTotal()"></div>
                        <div class="col-md-1"><input type="number" name="items[<?= $itemId ?>][sell_price]" class="form-control" value="<?= $pi['sell_price'] ?>" step="0.01" placeholder="بيع"></div>
                        <div class="col-md-1"><input type="date" name="items[<?= $itemId ?>][expiry_date]" class="form-control" placeholder="صلاحية"></div>
                        <div class="col-md-1"><input type="text" name="items[<?= $itemId ?>][batch_number]" class="form-control" placeholder="باتش"></div>
                        <div class="col-md-1"><input type="hidden" name="items[<?= $itemId ?>][product_id]" value="<?= $pi['product_id'] ?>"><button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('item_<?= $itemId ?>').remove();calcTotal()"><i class="bi bi-trash"></i></button></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="sec-card">
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">نوع الخصم</label><select name="discount_type" id="discountType" class="form-select" onchange="calcTotal()"><option value="">بدون</option><option value="percentage">نسبة %</option><option value="fixed">مبلغ</option></select></div>
                <div class="col-md-2"><label class="form-label">قيمة الخصم</label><input type="number" name="discount_value" id="discountValue" class="form-control" step="0.01" value="0" oninput="calcTotal()"></div>
                <div class="col-md-2"><label class="form-label">ضريبة %</label><input type="number" name="vat_percent" id="vatPercent" class="form-control" step="0.01" value="0" oninput="calcTotal()"></div>
                <div class="col-md-2"><label class="form-label">شحن</label><input type="number" name="shipping_cost" id="shippingCost" class="form-control" step="0.01" value="0" oninput="calcTotal()"></div>
                <div class="col-md-2"><label class="form-label">مدفوع الآن</label><input type="number" name="paid_amount" id="paidAmount" class="form-control" step="0.01" value="0" oninput="calcTotal()"></div>
                <div class="col-md-2"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" rows="1"></textarea></div>
            </div>
        </div>

        <div class="sumbar">
            <div class="row align-items-center">
                <div class="col-md-2">الإجمالي: <strong id="sumSubtotal">0.00</strong> ج</div>
                <div class="col-md-2">الخصم: <strong id="sumDiscount">0.00</strong> ج</div>
                <div class="col-md-2">الضريبة: <strong id="sumVat">0.00</strong> ج</div>
                <div class="col-md-2">الشحن: <strong id="sumShipping">0.00</strong> ج</div>
                <div class="col-md-2">النهائي: <strong id="sumGrand">0.00</strong> ج</div>
                <div class="col-md-2 text-start"><button type="submit" class="btn btn-light fw-bold"><i class="bi bi-save"></i> حفظ الفاتورة</button></div>
            </div>
        </div>
    </form>
</div>
<script>
let itemCount = <?= count($po_items) ?>;
function addItem() {
    itemCount++;
    const id = itemCount;
    const html = `
    <div class="item-row" id="item_${id}">
        <div class="row g-2">
            <div class="col-md-2"><input type="text" name="items[${id}][product_name]" class="form-control" placeholder="اسم الصنف *" required></div>
            <div class="col-md-1"><input type="text" name="items[${id}][product_code]" class="form-control" placeholder="كود"></div>
            <div class="col-md-1"><input type="text" name="items[${id}][barcode]" class="form-control" placeholder="باركود"></div>
            <div class="col-md-1"><input type="text" name="items[${id}][unit_name]" class="form-control" placeholder="وحدة" value="علبة"></div>
            <div class="col-md-1"><input type="number" name="items[${id}][quantity]" id="qty_${id}" class="form-control" step="0.001" min="0.001" value="1" required oninput="calcTotal()"></div>
            <div class="col-md-1"><input type="number" name="items[${id}][unit_cost]" id="cost_${id}" class="form-control" step="0.01" min="0" required oninput="calcTotal()"></div>
            <div class="col-md-1"><input type="number" name="items[${id}][sell_price]" class="form-control" step="0.01" placeholder="بيع"></div>
            <div class="col-md-1"><input type="date" name="items[${id}][expiry_date]" class="form-control"></div>
            <div class="col-md-1"><input type="text" name="items[${id}][batch_number]" class="form-control" placeholder="باتش"></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('item_${id}').remove();calcTotal()"><i class="bi bi-trash"></i></button></div>
        </div>
    </div>`;
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
}
function calcTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const id = row.id.replace('item_','');
        const qty = parseFloat(document.getElementById('qty_'+id)?.value) || 0;
        const cost = parseFloat(document.getElementById('cost_'+id)?.value) || 0;
        subtotal += qty * cost;
    });
    const dType = document.getElementById('discountType').value;
    const dVal = parseFloat(document.getElementById('discountValue').value) || 0;
    const vatPct = parseFloat(document.getElementById('vatPercent').value) || 0;
    const ship = parseFloat(document.getElementById('shippingCost').value) || 0;
    const discount = dType === 'percentage' ? subtotal * (dVal/100) : dVal;
    const afterDisc = subtotal - discount;
    const vat = afterDisc * (vatPct/100);
    const grand = afterDisc + vat + ship;
    document.getElementById('sumSubtotal').textContent = subtotal.toFixed(2);
    document.getElementById('sumDiscount').textContent = discount.toFixed(2);
    document.getElementById('sumVat').textContent = vat.toFixed(2);
    document.getElementById('sumShipping').textContent = ship.toFixed(2);
    document.getElementById('sumGrand').textContent = grand.toFixed(2);
}
<?php if(!$po_items): ?>addItem();<?php endif; ?>
calcTotal();
</script>
</body>
</html>
