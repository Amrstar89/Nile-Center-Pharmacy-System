<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'مرتجعات المشتريات';

$invoices = $db->query("SELECT pi.id, pi.invoice_number, s.supplier_name FROM purchase_invoices pi JOIN suppliers s ON pi.supplier_id = s.id WHERE pi.status != 'cancelled' ORDER BY pi.created_at DESC LIMIT 50")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) FROM purchase_returns WHERE YEAR(created_at) = $year");
        $count = $stmt->fetchColumn() + 1;
        $ret_number = 'PRET-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        $invoice_id = intval($_POST['invoice_id']);
        $inv = $db->prepare("SELECT supplier_id, store_id FROM purchase_invoices WHERE id = ?")->execute([$invoice_id]) ? $db->prepare("SELECT supplier_id, store_id FROM purchase_invoices WHERE id = ?")->fetch() : null;
        
        $subtotal = 0;
        foreach ($_POST['items'] as $item) {
            $subtotal += floatval($item['quantity']) * floatval($item['unit_cost']);
        }
        
        $db->prepare("INSERT INTO purchase_returns (return_number, invoice_id, supplier_id, store_id, return_date, subtotal, grand_total, notes, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)")
           ->execute([$ret_number, $invoice_id, $inv['supplier_id'], $inv['store_id'], $subtotal, $subtotal, $_POST['notes'] ?? '', $_SESSION['user_id']]);
        
        $ret_id = $db->lastInsertId();
        $itemStmt = $db->prepare("INSERT INTO purchase_return_items (return_id, invoice_item_id, product_id, product_name, quantity, unit_cost, line_total, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($_POST['items'] as $item) {
            $qty = floatval($item['quantity']);
            if ($qty <= 0) continue;
            $cost = floatval($item['unit_cost']);
            $line = $qty * $cost;
            $itemStmt->execute([$ret_id, $item['invoice_item_id'], $item['product_id'] ?: null, $item['product_name'], $qty, $cost, $line, $item['reason'] ?? '']);
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
@media(max-width:768px){.main-content{margin-right:0}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div><h5 class="mb-0"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></h5></div>
        <a href="../" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> عودة</a>
    </div>
    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <form method="POST" id="returnForm">
        <div class="sec-card">
            <label class="form-label">فاتورة الشراء الأصلية <span class="text-danger">*</span></label>
            <select name="invoice_id" id="invoiceSelect" class="form-select" required onchange="loadInvoiceItems()">
                <option value="">-- اختر الفاتورة --</option>
                <?php foreach($invoices as $inv): ?><option value="<?= $inv['id'] ?>"><?= $inv['invoice_number'] ?> - <?= $inv['supplier_name'] ?></option><?php endforeach; ?>
            </select>
        </div>

        <div class="sec-card">
            <h6 class="mb-3"><i class="bi bi-boxes"></i> الأصناف المراد إرجاعها</h6>
            <div id="itemsContainer">
                <p class="text-muted text-center">اختر فاتورة أولاً لعرض الأصناف</p>
            </div>
        </div>

        <div class="sec-card">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-danger btn-lg"><i class="bi bi-arrow-return-left"></i> إنشاء مرتجع</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const invoiceItems = {};
<?php
// Pre-load items for each invoice
foreach ($invoices as $inv) {
    $items = $db->prepare("SELECT id, product_id, product_name, product_code, quantity, unit_cost FROM purchase_invoice_items WHERE invoice_id = ?")->execute([$inv['id']]) ? $db->prepare("SELECT id, product_name, product_code, quantity, unit_cost, product_id, id as invoice_item_id FROM purchase_invoice_items WHERE invoice_id = ?")->fetchAll() : [];
    echo "invoiceItems[" . $inv['id'] . "] = " . json_encode($items) . ";\n";
}
?>

function loadInvoiceItems() {
    const invId = document.getElementById('invoiceSelect').value;
    const container = document.getElementById('itemsContainer');
    if (!invId || !invoiceItems[invId]) {
        container.innerHTML = '<p class="text-muted text-center">اختر فاتورة أولاً</p>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>الصنف</th><th>الكود</th><th>الكمية الأصلية</th><th>سعر الوحدة</th><th>الكمية المراد إرجاعها</th><th>السبب</th></tr></thead><tbody>';
    
    invoiceItems[invId].forEach((item, idx) => {
        html += `<tr>
            <td><strong>${item.product_name}</strong><input type="hidden" name="items[${idx}][invoice_item_id]" value="${item.invoice_item_id}"><input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}"><input type="hidden" name="items[${idx}][product_name]" value="${item.product_name}"></td>
            <td>${item.product_code || '-'}</td>
            <td>${item.quantity}</td>
            <td><input type="number" name="items[${idx}][unit_cost]" class="form-control" value="${item.unit_cost}" step="0.01" readonly style="background:#e9ecef"></td>
            <td><input type="number" name="items[${idx}][quantity]" class="form-control" step="0.001" min="0" max="${item.quantity}" value="0"></td>
            <td><input type="text" name="items[${idx}][reason]" class="form-control" placeholder="سبب الإرجاع"></td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}
</script>
</body>
</html>
