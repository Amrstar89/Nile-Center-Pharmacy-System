<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'أمر شراء جديد';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Generate PO number
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $po_number = 'PO-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $supplier_id = intval($_POST['supplier_id']);
        $store_id = intval($_POST['store_id'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);
        $expected_date = $_POST['expected_date'] ?: null;
        $discount_type = $_POST['discount_type'] ?: null;
        $discount_value = floatval($_POST['discount_value'] ?? 0);
        $vat_percent = floatval($_POST['vat_percent'] ?? 0);
        $shipping = floatval($_POST['shipping_cost'] ?? 0);
        $notes = $_POST['notes'] ?? '';

        // Calculate totals
        $subtotal = 0;
        foreach ($_POST['items'] as $item) {
            $qty = floatval($item['quantity']);
            $cost = floatval($item['unit_cost']);
            $disc = floatval($item['discount_percent'] ?? 0);
            $line = $qty * $cost * (1 - $disc/100);
            $subtotal += $line;
        }

        $discount = $discount_type === 'percentage' ? $subtotal * ($discount_value/100) : $discount_value;
        $afterDiscount = $subtotal - $discount;
        $vat = $afterDiscount * ($vat_percent/100);
        $grand = $afterDiscount + $vat + $shipping;

        // Insert PO
        $db->prepare("
            INSERT INTO purchase_orders (po_number, supplier_id, store_id, branch_id, order_date, expected_date, status,
                subtotal, discount_type, discount_value, vat_percent, vat_amount, shipping_cost, grand_total, notes, created_by)
            VALUES (?, ?, ?, ?, NOW(), ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$po_number, $supplier_id, $store_id ?: null, $branch_id ?: null, $expected_date,
            $subtotal, $discount_type, $discount_value, $vat_percent, $vat, $shipping, $grand, $notes, $_SESSION['user_id']]);

        $po_id = $db->lastInsertId();

        // Insert items
        $itemStmt = $db->prepare("
            INSERT INTO purchase_order_items (po_id, product_id, product_name, product_code, barcode, unit_id, unit_name,
                quantity, unit_cost, sell_price, discount_percent, vat_percent, line_total, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($_POST['items'] as $item) {
            $pid = intval($item['product_id'] ?? 0);
            $pname = $item['product_name'];
            $pcode = $item['product_code'] ?? null;
            $barcode = $item['barcode'] ?? null;
            $uid = intval($item['unit_id'] ?? 0);
            $uname = $item['unit_name'] ?? 'علبة';
            $qty = floatval($item['quantity']);
            $cost = floatval($item['unit_cost']);
            $sell = floatval($item['sell_price'] ?? 0);
            $disc = floatval($item['discount_percent'] ?? 0);
            $vat_p = floatval($item['vat_percent'] ?? 0);
            $line = $qty * $cost * (1 - $disc/100);
            $inotes = $item['notes'] ?? '';

            $itemStmt->execute([$po_id, $pid ?: null, $pname, $pcode, $barcode, $uid ?: null, $uname,
                $qty, $cost, $sell, $disc, $vat_p, $line, $inotes]);
        }

        logActivity('purchase_order_create', 'purchase_orders', $po_id);
        $db->commit();

        $_SESSION['success'] = 'تم إنشاء أمر الشراء ' . $po_number . ' بنجاح';
        redirect(APP_URL . '/modules/purchases/orders/view.php?id=' . $po_id);
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

$suppliers = $db->query("SELECT id, supplier_name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();
$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();
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
.item-row:hover{border-color:var(--primary)}
.sumbar{background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;padding:15px 20px;border-radius:12px;position:sticky;bottom:20px;z-index:100}
@media(max-width:768px){.main-content{margin-right:0}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div><h5 class="mb-0"><i class="bi bi-file-earmark-plus"></i> <?= $page_title ?></h5></div>
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> عودة</a>
    </div>

    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <form method="POST" id="poForm">
        <!-- Header -->
        <div class="sec-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">المورد <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">-- اختر المورد --</option>
                        <?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= $s['supplier_name'] ?> (<?= $s['supplier_code'] ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">المخزن</label>
                    <select name="store_id" class="form-select">
                        <option value="">-- اختر --</option>
                        <?php foreach($stores as $st): ?><option value="<?= $st['id'] ?>"><?= $st['store_name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الفرع</label>
                    <select name="branch_id" class="form-select">
                        <option value="">-- اختر --</option>
                        <?php foreach($branches as $b): ?><option value="<?= $b['id'] ?>"><?= $b['branch_name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">تاريخ التوقع</label>
                    <input type="date" name="expected_date" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <input type="text" class="form-control" value="مسودة" readonly style="background:#e9ecef">
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="sec-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-boxes"></i> الأصناف</h6>
                <button type="button" class="btn btn-success btn-sm" onclick="addItem()"><i class="bi bi-plus-lg"></i> إضافة صنف</button>
            </div>
            <div id="itemsContainer"></div>
        </div>

        <!-- Summary -->
        <div class="sec-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">نوع الخصم</label>
                    <select name="discount_type" id="discountType" class="form-select" onchange="calcTotal()">
                        <option value="">بدون خصم</option>
                        <option value="percentage">نسبة %</option>
                        <option value="fixed">مبلغ ثابت</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">قيمة الخصم</label>
                    <input type="number" name="discount_value" id="discountValue" class="form-control" step="0.01" value="0" oninput="calcTotal()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">ضريبة %</label>
                    <input type="number" name="vat_percent" id="vatPercent" class="form-control" step="0.01" value="0" oninput="calcTotal()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">شحن</label>
                    <input type="number" name="shipping_cost" id="shippingCost" class="form-control" step="0.01" value="0" oninput="calcTotal()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="1"></textarea>
                </div>
            </div>
        </div>

        <!-- Summary Bar -->
        <div class="sumbar">
            <div class="row align-items-center">
                <div class="col-md-2">الإجمالي: <strong id="sumSubtotal">0.00</strong> ج</div>
                <div class="col-md-2">الخصم: <strong id="sumDiscount">0.00</strong> ج</div>
                <div class="col-md-2">الضريبة: <strong id="sumVat">0.00</strong> ج</div>
                <div class="col-md-2">الشحن: <strong id="sumShipping">0.00</strong> ج</div>
                <div class="col-md-2">النهائي: <strong id="sumGrand">0.00</strong> ج</div>
                <div class="col-md-2 text-start"><button type="submit" class="btn btn-light fw-bold"><i class="bi bi-save"></i> حفظ أمر الشراء</button></div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let itemCount = 0;
const units = <?= json_encode($units) ?>;

function addItem() {
    itemCount++;
    const id = itemCount;
    let unitOpts = '<option value="">-- الوحدة --</option>';
    units.forEach(u => unitOpts += `<option value="${u.id}">${u.unit_name_ar}</option>`);

    const html = `
    <div class="item-row" id="item_${id}">
        <div class="row g-2">
            <div class="col-md-3"><input type="text" name="items[${id}][product_name]" class="form-control" placeholder="اسم الصنف *" required></div>
            <div class="col-md-2"><input type="text" name="items[${id}][product_code]" class="form-control" placeholder="الكود"></div>
            <div class="col-md-2"><input type="text" name="items[${id}][barcode]" class="form-control" placeholder="الباركود"></div>
            <div class="col-md-1"><select name="items[${id}][unit_id]" class="form-select">${unitOpts}</select></div>
            <div class="col-md-1"><input type="number" name="items[${id}][quantity]" id="qty_${id}" class="form-control" placeholder="الكمية *" step="0.001" min="0.001" value="1" required oninput="calcTotal()"></div>
            <div class="col-md-1"><input type="number" name="items[${id}][unit_cost]" id="cost_${id}" class="form-control" placeholder="سعر *" step="0.01" min="0" required oninput="calcTotal()"></div>
            <div class="col-md-1"><input type="number" name="items[${id}][sell_price]" class="form-control" placeholder="سعر البيع" step="0.01"></div>
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

// Add first item
addItem();
</script>
</body>
</html>
