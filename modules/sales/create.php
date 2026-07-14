<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فاتورة بيع جديدة';

function addColIfMissing($db, $table, $column, $def) {
    $cols = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchAll();
    if (empty($cols)) {
        try { $db->exec("ALTER TABLE `$table` ADD COLUMN $column $def"); } catch (PDOException $e) {}
    }
}
addColIfMissing($db, 'sale_invoices', 'invoice_time', 'TIME NULL AFTER invoice_date');
addColIfMissing($db, 'sale_invoice_items', 'batch_id', 'INT NULL AFTER batch_number');

$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();
$stores = $db->query("SELECT s.id, s.store_name, s.branch_id FROM stores s WHERE s.is_active = 1 ORDER BY s.store_name")->fetchAll();
$customers = $db->query("SELECT id, customer_name, customer_code, phone FROM customers WHERE is_active = 1 ORDER BY customer_name LIMIT 500")->fetchAll();
$users = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();

$pre_customer_id = intval($_GET['customer_id'] ?? 0);
$pre_customer = null;
if ($pre_customer_id) { foreach ($customers as $c) { if ($c['id'] == $pre_customer_id) { $pre_customer = $c; break; } } }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $items = [];
        if (!empty($_POST['items_data'])) { $decoded = json_decode($_POST['items_data'], true); if (is_array($decoded)) $items = $decoded; }
        if (empty($items)) { throw new Exception('يجب إضافة صنف واحد على الأقل'); }
        $db->beginTransaction();
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $store_id = intval($_POST['store_id']);
        $user_id = intval($_POST['user_id'] ?? ($_SESSION['user_id'] ?? 0));
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $invoice_date = $_POST['invoice_date'] ?: date('Y-m-d');
        $invoice_time = $_POST['invoice_time'] ?: date('H:i');
        $discount_pct = floatval($_POST['discount_pct'] ?? 0);
        $discount_val = floatval($_POST['discount_val'] ?? 0);
        $extra_discount_pct = floatval($_POST['extra_discount_pct'] ?? 0);
        $extra_discount_val = floatval($_POST['extra_discount_val'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        $subtotal = 0; $total_vat = 0; $total_cost = 0; $total_profit = 0;
        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 0); $price = floatval($item['sell_price'] ?? 0); $cost = floatval($item['unit_cost'] ?? 0);
            $discPct = floatval($item['discount_percent'] ?? 0); $vatPct = floatval($item['vat_percent'] ?? 0);
            $afterDisc = $qty * $price * (1 - $discPct/100);
            $subtotal += $afterDisc; $total_vat += $afterDisc * ($vatPct/100); $total_cost += $qty * $cost; $total_profit += ($price - $cost) * $qty;
        }
        $grand = $subtotal + $total_vat - $discount_val;
        if ($extra_discount_pct > 0) $grand -= $grand * ($extra_discount_pct/100);
        $grand -= $extra_discount_val; if ($grand < 0) $grand = 0;
        $status = 'open';
        if ($payment_method === 'cash' || $payment_method === 'visa') { $paid_amount = $grand; $status = 'paid'; }
        elseif ($payment_method === 'credit') { if ($paid_amount >= $grand) $status = 'paid'; elseif ($paid_amount > 0) $status = 'partial'; }
        elseif ($payment_method === 'pending') { $paid_amount = 0; $status = 'open'; }
        $remaining = $grand - $paid_amount;
        $year = date('Y');
        $count = $db->query("SELECT COUNT(*) FROM sale_invoices WHERE YEAR(created_at) = $year")->fetchColumn() + 1;
        $inv_number = 'SINV-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
        $hasInvTime = !empty($db->query("SHOW COLUMNS FROM sale_invoices LIKE 'invoice_time'")->fetchAll());
        $hasBatchId = !empty($db->query("SHOW COLUMNS FROM sale_invoice_items LIKE 'batch_id'")->fetchAll());
        if ($hasInvTime) {
            $db->prepare("INSERT INTO sale_invoices (invoice_number, customer_id, store_id, user_id, invoice_date, invoice_time, payment_method, subtotal, discount_pct, discount_val, extra_discount_pct, extra_discount_val, vat_amount, grand_total, paid_amount, remaining_amount, profit_amount, cost_amount, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$inv_number, $customer_id ?: null, $store_id, $user_id, $invoice_date, $invoice_time, $payment_method, $subtotal, $discount_pct, $discount_val, $extra_discount_pct, $extra_discount_val, $total_vat, $grand, $paid_amount, $remaining, $total_profit, $total_cost, $status, $notes, $_SESSION['user_id']]);
        } else {
            $db->prepare("INSERT INTO sale_invoices (invoice_number, customer_id, store_id, user_id, invoice_date, payment_method, subtotal, discount_pct, discount_val, extra_discount_pct, extra_discount_val, vat_amount, grand_total, paid_amount, remaining_amount, profit_amount, cost_amount, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$inv_number, $customer_id ?: null, $store_id, $user_id, $invoice_date, $payment_method, $subtotal, $discount_pct, $discount_val, $extra_discount_pct, $extra_discount_val, $total_vat, $grand, $paid_amount, $remaining, $total_profit, $total_cost, $status, $notes, $_SESSION['user_id']]);
        }
        $inv_id = $db->lastInsertId();
        if ($hasBatchId) {
            $itemStmt = $db->prepare("INSERT INTO sale_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_name, quantity, unit_cost, sell_price, discount_pct, discount_val, vat_pct, vat_val, line_total, profit_val, expiry_date, batch_number, batch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $itemStmt = $db->prepare("INSERT INTO sale_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_name, quantity, unit_cost, sell_price, discount_pct, discount_val, vat_pct, vat_val, line_total, profit_val, expiry_date, batch_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        }
        foreach ($items as $item) {
            $pid = intval($item['product_id'] ?? 0); $qty = floatval($item['quantity'] ?? 0); $price = floatval($item['sell_price'] ?? 0); $cost = floatval($item['unit_cost'] ?? 0);
            $discPct = floatval($item['discount_percent'] ?? 0); $vatPct = floatval($item['vat_percent'] ?? 0);
            $afterDisc = $qty * $price * (1 - $discPct/100); $vatVal = $afterDisc * ($vatPct/100); $discVal = $qty * $price * ($discPct/100);
            $line = $afterDisc + $vatVal; $profit = ($price - $cost) * $qty;
            if ($hasBatchId) {
                $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'] ?? '', $item['product_code'] ?? '', $item['barcode'] ?? '', $item['unit_name'] ?? 'علبة', $qty, $cost, $price, $discPct, $discVal, $vatPct, $vatVal, $line, $profit, ($item['expiry_date'] ?? null) ?: null, $item['batch_number'] ?? null, $item['batch_id'] ?? null]);
            } else {
                $itemStmt->execute([$inv_id, $pid ?: null, $item['product_name'] ?? '', $item['product_code'] ?? '', $item['barcode'] ?? '', $item['unit_name'] ?? 'علبة', $qty, $cost, $price, $discPct, $discVal, $vatPct, $vatVal, $line, $profit, ($item['expiry_date'] ?? null) ?: null, $item['batch_number'] ?? null]);
            }
            if ($store_id && $pid) {
                $db->prepare("UPDATE inventory_items SET quantity = GREATEST(quantity - ?, 0), updated_at = NOW() WHERE store_id = ? AND product_id = ?")->execute([$qty, $store_id, $pid]);
                $db->prepare("INSERT INTO inventory_movements (store_id, product_id, movement_type, reference_type, reference_id, reference_number, quantity, unit_cost, total_cost, notes, created_by) VALUES (?, ?, 'sale', 'sale_invoice', ?, ?, ?, ?, ?, ?, ?)")->execute([$store_id, $pid, $inv_id, $inv_number, $qty, $cost, $qty * $cost, 'فاتورة بيع ' . $inv_number, $_SESSION['user_id']]);
            }
        }
        if ($paid_amount > 0 && $customer_id) {
            $payNum = 'CPAY-' . time();
            $db->prepare("INSERT INTO customer_payments (payment_number, customer_id, invoice_id, amount, payment_date, payment_method, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$payNum, $customer_id, $inv_id, $paid_amount, $invoice_date, $payment_method, 'دفعة من فاتورة ' . $inv_number, $_SESSION['user_id']]);
        }
        if ($customer_id && $grand > 0) {
            $lastBal = $db->query("SELECT balance_after FROM customer_transactions WHERE customer_id = $customer_id ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0;
            $newBal = floatval($lastBal) + ($grand - $paid_amount);
            $db->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, reference_type, reference_id, reference_number, debit, credit, balance_after, notes, created_by, created_at) VALUES (?, 'sale', 'sale_invoice', ?, ?, ?, ?, ?, ?, ?, NOW())")
               ->execute([$customer_id, $inv_id, $inv_number, ($grand - $paid_amount), $paid_amount, $newBal, 'فاتورة بيع ' . $inv_number, $_SESSION['user_id']]);
        }
        logActivity('sale_invoice_create', 'sale_invoices', $inv_id); $db->commit();
        $_SESSION['success'] = 'تم إنشاء فاتورة البيع ' . $inv_number . ' بنجاح';
        redirect(APP_URL . '/modules/sales/view.php?id=' . $inv_id);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2;--green:#198754;--red:#dc3545;--orange:#ff9800;--blue:#0d6efd;--purple:#6f42c1;}
*{box-sizing:border-box}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow:auto;min-width:1400px}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:8px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;white-space:nowrap}
.top-header .menu-item:hover,.top-header .menu-item.active{background:rgba(255,255,255,0.2);color:#fff}
.sub-menu-bar{background:#f8f9fa;border-bottom:1px solid #ddd;padding:5px 20px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sub-menu-bar .btn-icon{width:36px;height:36px;border-radius:8px;border:1px solid #ccc;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:16px}
.sub-menu-bar .btn-icon:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.sub-menu-bar .divider{width:1px;height:28px;background:#ccc;margin:0 5px}
.invoice-header{background:#fff;padding:12px 20px;border-bottom:2px solid #e9ecef}
.pay-types{display:flex;flex-direction:column;gap:6px;padding:8px;background:#fff;border-radius:8px}
.pay-types .pay-btn{padding:8px 12px;border-radius:6px;border:2px solid #e9ecef;background:#fff;cursor:pointer;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;transition:all .2s;text-align:right}
.pay-types .pay-btn:hover{border-color:var(--primary);background:#f8f9ff}
.pay-types .pay-btn.active{border-color:var(--primary);background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff}
.pay-types .pay-btn i{font-size:16px}
.info-section{background:#fff;padding:12px 20px;border-bottom:1px solid #e9ecef}
.grand-box{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border:2px solid var(--green);border-radius:12px;padding:15px;text-align:center;margin-bottom:10px}
.grand-box .lbl{color:#555;font-size:13px}
.grand-box .val{color:var(--green);font-size:36px;font-weight:700}
.stats-box{background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:10px;display:flex;gap:15px;flex-wrap:wrap;align-items:center}
.stats-box .st{display:flex;flex-direction:column;align-items:center;min-width:60px}
.stats-box .st label{color:#888;font-size:11px;white-space:nowrap}
.stats-box .st strong{color:#333;font-size:13px}
.toolbar-right{position:fixed;right:0;top:110px;width:55px;background:linear-gradient(180deg,var(--green) 0%,#2e7d32 100%);border-radius:10px 0 0 10px;padding:8px 4px;display:flex;flex-direction:column;gap:6px;z-index:50;box-shadow:-2px 2px 10px rgba(0,0,0,0.2)}
.toolbar-right .tool-btn{width:46px;height:46px;border-radius:10px;border:none;background:rgba(255,255,255,0.25);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:18px;position:relative}
.toolbar-right .tool-btn:hover{background:#fff;color:var(--green);transform:scale(1.1)}
.toolbar-right .tool-btn .tooltip{position:absolute;left:55px;top:50%;transform:translateY(-50%);background:#333;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.toolbar-right .tool-btn:hover .tooltip{opacity:1}
.items-section{overflow-x:auto;padding:10px;background:#fff;margin:0 55px 0 0;position:relative}
.items-section::-webkit-scrollbar{height:10px}
.items-table{width:100%;border-collapse:collapse;font-size:12px;min-width:2200px}
.items-table th{background:var(--green);color:#fff;padding:6px 4px;text-align:center;font-weight:600;font-size:11px;white-space:nowrap;position:sticky;top:0;z-index:10}
.items-table td{padding:3px 4px;border-bottom:1px solid #e9ecef;text-align:center;background:#fff;vertical-align:middle}
.items-table tr:hover td{background:#e8f5e9}
.items-table tr.selected td{background:#fff3cd}
.items-table td input,.items-table td select{border:1px solid #ddd;border-radius:4px;padding:2px 4px;font-size:12px;text-align:center;height:28px;width:100%}
.items-table td input:focus{border-color:var(--green);outline:none}
.items-table .product-name{text-align:right;font-weight:600;min-width:170px}
.items-table .num{text-align:center;min-width:50px}
.items-table .row-total{font-weight:700;color:var(--primary);background:#e8f5e9 !important}
.items-table .row-calc{background:#e3f2fd}
.items-table .btn-del{color:var(--red);cursor:pointer;font-size:15px;padding:0 3px}
.items-table .btn-del:hover{transform:scale(1.2)}
.items-table .barcode-w{position:relative;display:flex;align-items:center;min-width:100px}
.items-table .barcode-w input{flex:1;padding-left:28px}
.items-table .barcode-w .btn-f2{position:absolute;left:2px;top:2px;bottom:2px;width:24px;border:none;background:#f0f0f0;border-radius:3px;cursor:pointer;font-size:10px;color:#666;display:flex;align-items:center;justify-content:center}
.items-table .barcode-w .btn-f2:hover{background:var(--green);color:#fff}
.bottom-bar{background:#f8f9fa;border-top:2px solid #ddd;padding:10px 20px;position:sticky;bottom:0;z-index:50}
.bottom-bar .row1{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.bottom-bar .item{display:flex;align-items:center;gap:4px;background:#fff;padding:4px 10px;border-radius:6px;border:1px solid #ddd;font-size:12px}
.bottom-bar .item label{color:#666;font-size:11px;white-space:nowrap}
.bottom-bar .item strong{color:#333;font-size:13px;white-space:nowrap}
.bottom-bar .grand{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:8px 20px;border-radius:10px;font-size:16px;font-weight:700}
.bottom-bar .item input,.bottom-bar .item select{height:26px;padding:2px 5px;font-size:12px;width:80px;border:1px solid #ccc;border-radius:4px}
.customer-display{background:#e3f2fd;border:1px solid #90caf9;border-radius:6px;padding:6px 12px;display:none;align-items:center;gap:10px;flex-wrap:wrap}
.customer-display.show{display:flex}
.customer-display .cust-name{font-weight:700;color:#1565c0}
.customer-display .cust-balance{font-size:12px;color:#666}
.cust-actions{display:flex;gap:6px;margin-right:auto}
.cust-actions .btn-action{padding:4px 12px;border-radius:6px;border:none;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:5px}
.cust-actions .btn-profile{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff}
.cust-actions .btn-profile:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(102,126,234,0.3)}
.cust-actions .btn-ledger{background:linear-gradient(135deg,var(--green),#2e7d32);color:#fff}
.cust-actions .btn-ledger:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(25,135,84,0.3)}
.save-section{background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-top:3px solid var(--green);padding:20px;text-align:center;display:flex;justify-content:center;align-items:center;gap:20px}
.save-section .btn-save{background:linear-gradient(135deg,var(--green),#2e7d32);color:#fff;border:none;padding:16px 60px;border-radius:14px;font-size:20px;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 6px 20px rgba(25,135,84,0.35);display:inline-flex;align-items:center;gap:10px}
.save-section .btn-save:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(25,135,84,0.5)}
.save-section .btn-save i{font-size:24px}
.save-section .btn-cancel{background:#fff;color:#666;border:2px solid #ddd;padding:12px 30px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s}
.save-section .btn-cancel:hover{border-color:var(--red);color:var(--red)}
/* Inline customer search dropdown */
.cust-dropdown{position:absolute;top:100%;right:0;left:0;z-index:1000;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,0.15);max-height:220px;overflow-y:auto;display:none;margin-top:2px}
.cust-dropdown.show{display:block}
.cust-dropdown .dd-item{padding:8px 12px;border-bottom:1px solid #f0f0f0;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:8px}
.cust-dropdown .dd-item:hover{background:#e8f0fe}
.cust-dropdown .dd-item b{color:#333}
.cust-dropdown .dd-item small{color:#888}
.cust-dropdown .dd-item .dd-code{background:#f0f0f0;padding:1px 6px;border-radius:4px;font-size:11px;font-family:monospace}
.cust-dropdown .dd-empty{padding:12px;text-align:center;color:#999;font-size:13px}
.d-none{display:none !important}
@media print{.toolbar-right,.sub-menu-bar,.top-header .menu-item,.btn-icon,.save-section{display:none!important}}
@media(max-width:768px){.toolbar-right{position:relative;width:100%;flex-direction:row;border-radius:0;top:0}.items-section{margin-right:0}body{min-width:auto}}
</style>
</head>
<body>
<form id="saleForm" method="POST" action="" onsubmit="return prepareSubmit();">
<input type="hidden" name="items_data" id="itemsData" value="">

<!-- Top Header -->
<div class="top-header">
  <strong><i class="bi bi-shop"></i> <?= APP_NAME ?></strong>
  <a href="<?= APP_URL ?>/modules/sales/" class="menu-item"><i class="bi bi-receipt"></i> فواتير البيع</a>
  <a href="<?= APP_URL ?>/modules/sales/create.php" class="menu-item active"><i class="bi bi-plus-circle"></i> فاتورة بيع جديدة</a>
  <a href="<?= APP_URL ?>/modules/sales/returns/create.php" class="menu-item"><i class="bi bi-arrow-return-left"></i> مرتجع بيع</a>
  <div style="margin-right:auto;display:flex;align-items:center;gap:10px">
    <span><i class="bi bi-person"></i> <?= $_SESSION['full_name'] ?? '' ?></span>
    <a href="<?= APP_URL ?>/logout.php" class="menu-item"><i class="bi bi-box-arrow-left"></i> خروج</a>
  </div>
</div>

<!-- Sub Menu Bar -->
<div class="sub-menu-bar">
  <button type="button" class="btn-icon" onclick="addNewRow()" title="إضافة صف جديد (F7)"><i class="bi bi-plus-lg" style="color:var(--green)"></i></button>
  <button type="button" class="btn-icon" onclick="deleteSelectedRow()" title="حذف الصف المحدد (F9)"><i class="bi bi-trash" style="color:var(--red)"></i></button>
  <button type="button" class="btn-icon" onclick="openColumnOrder()" title="ترتيب الأعمدة"><i class="bi bi-arrows-move" style="color:var(--blue)"></i></button>
  <div class="divider"></div>
  <button type="button" class="btn-icon" onclick="location.href='<?= APP_URL ?>/modules/sales/'" title="فواتير البيع"><i class="bi bi-list" style="color:var(--purple)"></i></button>
</div>

<!-- Invoice Header -->
<div class="invoice-header">
  <div class="row g-2 align-items-center">
    <div class="col-auto"><h5 class="m-0" style="color:var(--primary)"><i class="bi bi-cart-plus"></i> فاتورة بيع جديدة</h5></div>
    <div class="col-auto">
      <label class="form-label m-0" style="font-size:12px">الفرع:</label>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" id="branch_id" name="branch_id" onchange="filterStoresByBranch()" required>
        <option value="">-- اختر الفرع --</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label m-0" style="font-size:12px">المخزن:</label>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" id="store_id" name="store_id" required disabled>
        <option value="">-- اختر الفرع أولاً --</option>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label m-0" style="font-size:12px">التاريخ:</label>
    </div>
    <div class="col-auto">
      <input type="date" class="form-control form-control-sm" name="invoice_date" value="<?= date('Y-m-d') ?>" required style="width:140px">
    </div>
    <div class="col-auto">
      <label class="form-label m-0" style="font-size:12px">الوقت:</label>
    </div>
    <div class="col-auto">
      <input type="time" class="form-control form-control-sm" name="invoice_time" value="<?= date('H:i') ?>" required style="width:100px">
    </div>
    <div class="col-auto">
      <label class="form-label m-0" style="font-size:12px">المستخدم:</label>
    </div>
    <div class="col-auto">
      <select class="form-select form-select-sm" name="user_id" style="width:140px">
        <?php foreach ($users as $u): ?>
        <option value="<?= $u['id'] ?>" <?= ($u['id'] == ($_SESSION['user_id'] ?? 0)) ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<!-- Customer Section -->
<div class="info-section">
  <div class="row g-2 align-items-center">
    <div class="col-md-4" style="position:relative">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" class="form-control form-control-sm" id="customer_code_input" placeholder="كود/اسم/تليفون العميل - F4 للبحث" oninput="searchCustomerInline(this.value)" onkeydown="handleCustomerKey(event)" onblur="setTimeout(()=>hideCustDropdown(),200)" autocomplete="off">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openCustomerSearch()"><i class="bi bi-search"></i></button>
      </div>
      <!-- Inline customer dropdown -->
      <div class="cust-dropdown" id="custDropdown"></div>
    </div>
    <div class="col-md-7">
      <div id="customer_display" class="customer-display">
        <i class="bi bi-person-check" style="color:var(--green);font-size:20px"></i>
        <div>
          <div class="cust-name" id="cust_name"></div>
          <div class="cust-balance" id="cust_info"></div>
        </div>
        <div class="cust-actions">
          <button type="button" class="btn-action btn-profile" onclick="openCustomerProfile()"><i class="bi bi-person-vcard"></i> معلومات العميل</button>
          <button type="button" class="btn-action btn-ledger" onclick="openCustomerLedger()"><i class="bi bi-journal-text"></i> كشف حساب</button>
        </div>
        <button type="button" class="btn btn-sm btn-link text-danger" onclick="clearCustomer()" style="margin-right:auto"><i class="bi bi-x-circle"></i></button>
      </div>
    </div>
    <input type="hidden" name="customer_id" id="customer_id" value="0">
    <div class="col-auto" style="margin-right:auto">
      <div class="pay-types">
        <button type="button" class="pay-btn active" data-pay="cash" onclick="setPayType('cash',this)"><i class="bi bi-cash-stack"></i> نقدي</button>
        <button type="button" class="pay-btn" data-pay="credit" onclick="setPayType('credit',this)"><i class="bi bi-credit-card"></i> آجل</button>
        <button type="button" class="pay-btn" data-pay="visa" onclick="setPayType('visa',this)"><i class="bi bi-bank"></i> فيزا</button>
        <button type="button" class="pay-btn" data-pay="pending" onclick="setPayType('pending',this)"><i class="bi bi-clock"></i> مؤجل</button>
      </div>
      <input type="hidden" name="payment_method" id="payment_method" value="cash">
    </div>
  </div>
</div>

<!-- Toolbar Right -->
<div class="toolbar-right">
  <button type="button" class="tool-btn" onclick="addNewRow()"><i class="bi bi-plus-lg"></i><span class="tooltip">إضافة صف (F7)</span></button>
  <button type="button" class="tool-btn" onclick="deleteSelectedRow()"><i class="bi bi-trash"></i><span class="tooltip">حذف صف (F9)</span></button>
  <button type="button" class="tool-btn" onclick="openColumnOrder()"><i class="bi bi-arrows-move"></i><span class="tooltip">ترتيب الأعمدة</span></button>
  <button type="button" class="tool-btn" onclick="document.getElementById('saleForm').submit()"><i class="bi bi-save" style="color:#fff"></i><span class="tooltip">حفظ الفاتورة</span></button>
  <button type="button" class="tool-btn" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i><span class="tooltip">جديد</span></button>
</div>

<!-- Items Section -->
<div class="items-section">
  <table class="items-table" id="itemsTable">
    <thead>
      <tr>
        <th>#</th>
        <th data-col="barcode">الباركود</th>
        <th data-col="product_code">كود الصنف</th>
        <th data-col="product_name">اسم الصنف</th>
        <th data-col="unit">الوحدة</th>
        <th data-col="quantity">الكمية</th>
        <th data-col="bonus">بونص</th>
        <th data-col="unit_cost">ت.الوحدة</th>
        <th data-col="sell_price">سعر البيع</th>
        <th data-col="disc_pct">خصم%</th>
        <th data-col="disc_val">خصم قيمة</th>
        <th data-col="vat_pct">ض.ق%</th>
        <th data-col="vat_val">ض.قيمة</th>
        <th data-col="total">الإجمالي</th>
        <th data-col="profit">الربح</th>
        <th data-col="batch">الباتش</th>
        <th data-col="expiry">الصلاحية</th>
        <th><i class="bi bi-gear"></i></th>
      </tr>
    </thead>
    <tbody id="itemsBody">
    </tbody>
  </table>
  <div id="emptyMsg" style="text-align:center;padding:40px;color:#999">
    <i class="bi bi-cart" style="font-size:48px"></i><br>
    <span style="font-size:16px">اضغط F7 أو زر <i class="bi bi-plus-lg" style="color:var(--green)"></i> لإضافة صنف</span><br>
    <span style="font-size:13px">أو اضغط F2 في خانة الباركود للبحث عن منتج</span>
  </div>
</div>

<!-- Bottom Bar -->
<div class="bottom-bar">
  <div class="row1">
    <div class="item"><label>الأصناف:</label><strong id="totalItems">0</strong></div>
    <div class="item"><label>الكميات:</label><strong id="totalQty">0</strong></div>
    <div class="item"><label>الإجمالي:</label><strong id="subtotalDisplay">0.00</strong></div>
    <div class="item"><label>ضريبة:</label><strong id="vatDisplay">0.00</strong></div>
    <div class="item"><label>خصم%:</label><input type="number" name="discount_pct" id="discount_pct" value="0" step="0.01" onchange="recalcAll()"></div>
    <div class="item"><label>خصم ق:</label><input type="number" name="discount_val" id="discount_val" value="0" step="0.01" onchange="recalcAll()"></div>
    <div class="item"><label>خصم إض%:</label><input type="number" name="extra_discount_pct" id="extra_discount_pct" value="0" step="0.01" onchange="recalcAll()"></div>
    <div class="item"><label>خصم إض ق:</label><input type="number" name="extra_discount_val" id="extra_discount_val" value="0" step="0.01" onchange="recalcAll()"></div>
    <div class="item"><label>المدفوع:</label><input type="number" name="paid_amount" id="paid_amount" value="0" step="0.01"></div>
    <div class="item"><label>المتبقي:</label><strong id="remainingDisplay" style="color:var(--red)">0.00</strong></div>
    <div class="grand" style="margin-right:auto">الصافي: <span id="grandDisplay">0.00</span> ج.م</div>
  </div>
  <div class="row1">
    <div class="item" style="flex:1"><label>ملاحظات:</label><input type="text" name="notes" style="flex:1;min-width:300px" placeholder="أي ملاحظات على الفاتورة..."></div>
  </div>
</div>

<!-- Save Section -->
<div class="save-section">
  <button type="submit" class="btn-save" id="saveBtn">
    <i class="bi bi-check-circle-fill"></i> حفظ الفاتورة
  </button>
  <button type="button" class="btn-cancel" onclick="if(confirm('هل تريد إلغاء الفاتورة الحالية؟')) location.reload()">
    <i class="bi bi-x-circle"></i> إلغاء
  </button>
</div>

</form>

<!-- Column Order Modal -->
<div class="modal fade" id="colOrderModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">ترتيب الأعمدة (اسحب للتغيير)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><ul class="list-group" id="colOrderList"></ul></div>
      <div class="modal-footer"><button type="button" class="btn btn-sm btn-primary" onclick="applyColOrder()">تطبيق</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ============ DATA ============
const allStores = <?= json_encode($stores) ?>;
const allCustomers = <?= json_encode($customers) ?>;
const allUnits = <?= json_encode($units) ?>;
const CURRENCY = 'ج.م';
let _currentRowIndex = 0;
let _currentCustomerId = 0;

// ============ BRANCH → STORE CASCADING ============
function filterStoresByBranch() {
  const branchId = document.getElementById('branch_id').value;
  const storeSel = document.getElementById('store_id');
  storeSel.innerHTML = '';
  if (!branchId) {
    storeSel.disabled = true;
    storeSel.innerHTML = '<option value="">-- اختر الفرع أولاً --</option>';
    return;
  }
  const filtered = allStores.filter(s => String(s.branch_id) === String(branchId));
  if (filtered.length === 0) {
    storeSel.innerHTML = '<option value="">لا يوجد مخازن لهذا الفرع</option>';
    storeSel.disabled = true;
    return;
  }
  storeSel.disabled = false;
  const defOpt = document.createElement('option');
  defOpt.value = ''; defOpt.textContent = '-- اختر المخزن --';
  storeSel.appendChild(defOpt);
  filtered.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id; opt.textContent = s.store_name;
    storeSel.appendChild(opt);
  });
}

// ============ COLUMN ORDER MODULE ============
const ColOrder = {
  modal: null,
  STORAGE_KEY: 'sale_col_order',
  DEFAULT_ORDER: ['barcode','product_code','product_name','unit','quantity','bonus','unit_cost','sell_price','disc_pct','disc_val','vat_pct','vat_val','total','profit','batch','expiry'],
  getOrder() {
    try { const saved = localStorage.getItem(this.STORAGE_KEY); if (saved) return JSON.parse(saved); } catch(e){}
    return this.DEFAULT_ORDER.slice();
  },
  saveOrder(order) { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(order)); },
  reset() { localStorage.removeItem(this.STORAGE_KEY); this.apply(this.DEFAULT_ORDER); },
  open() {
    const list = document.getElementById('colOrderList');
    list.innerHTML = '';
    const order = this.getOrder();
    const labels = {barcode:'الباركود',product_code:'كود الصنف',product_name:'اسم الصنف',unit:'الوحدة',quantity:'الكمية',bonus:'بونص',unit_cost:'ت.الوحدة',sell_price:'سعر البيع',disc_pct:'خصم%',disc_val:'خصم قيمة',vat_pct:'ض.ق%',vat_val:'ض.قيمة',total:'الإجمالي',profit:'الربح',batch:'الباتش',expiry:'الصلاحية'};
    order.forEach((col, idx) => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center';
      li.draggable = true; li.dataset.col = col; li.dataset.idx = idx;
      li.innerHTML = `<span><i class="bi bi-grip-vertical" style="color:#999;margin-left:5px"></i> ${labels[col] || col}</span>`;
      li.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', idx); li.style.opacity = '0.5'; });
      li.addEventListener('dragend', () => { li.style.opacity = '1'; });
      li.addEventListener('dragover', e => { e.preventDefault(); });
      li.addEventListener('drop', e => {
        e.preventDefault();
        const fromIdx = parseInt(e.dataTransfer.getData('text/plain'));
        const toIdx = parseInt(li.dataset.idx);
        if (fromIdx === toIdx) return;
        const cur = Array.from(list.children).map(c => c.dataset.col);
        const [moved] = cur.splice(fromIdx, 1);
        cur.splice(toIdx, 0, moved);
        list.innerHTML = '';
        cur.forEach((c, i) => {
          const nli = document.createElement('li');
          nli.className = 'list-group-item d-flex justify-content-between align-items-center';
          nli.draggable = true; nli.dataset.col = c; nli.dataset.idx = i;
          nli.innerHTML = `<span><i class="bi bi-grip-vertical" style="color:#999;margin-left:5px"></i> ${labels[c] || c}</span>`;
          nli.addEventListener('dragstart', ev => { ev.dataTransfer.setData('text/plain', i); nli.style.opacity = '0.5'; });
          nli.addEventListener('dragend', () => { nli.style.opacity = '1'; });
          nli.addEventListener('dragover', ev => { ev.preventDefault(); });
          nli.addEventListener('drop', ev => {
            ev.preventDefault();
            const fI = parseInt(ev.dataTransfer.getData('text/plain'));
            const tI = parseInt(nli.dataset.idx);
            if (fI === tI) return;
            const cu = Array.from(list.children).map(x => x.dataset.col);
            const [mv] = cu.splice(fI, 1); cu.splice(tI, 0, mv);
            list.innerHTML = '';
            cu.forEach((cc, ii) => {
              const nn = document.createElement('li'); nn.className='list-group-item d-flex justify-content-between align-items-center';
              nn.draggable=true; nn.dataset.col=cc; nn.dataset.idx=ii;
              nn.innerHTML=`<span><i class="bi bi-grip-vertical" style="color:#999;margin-left:5px"></i> ${labels[cc]||cc}</span>`;
              nn.addEventListener('dragstart', e9 => {e9.dataTransfer.setData('text/plain',ii);nn.style.opacity='0.5';});
              nn.addEventListener('dragend',()=>{nn.style.opacity='1';});
              nn.addEventListener('dragover',e9=>{e9.preventDefault();});
              nn.addEventListener('drop', e9 => {
                e9.preventDefault(); const fi=parseInt(e9.dataTransfer.getData('text/plain')); const ti=parseInt(nn.dataset.idx);
                if(fi===ti)return; const cu2=Array.from(list.children).map(x=>x.dataset.col); const[mv2]=cu2.splice(fi,1); cu2.splice(ti,0,mv2);
                list.innerHTML=''; cu2.forEach((ccc,iii)=>{const n2=document.createElement('li');n2.className='list-group-item d-flex justify-content-between align-items-center';n2.draggable=true;n2.dataset.col=ccc;n2.dataset.idx=iii;n2.innerHTML=`<span><i class="bi bi-grip-vertical" style="color:#999;margin-left:5px"></i> ${labels[ccc]||ccc}</span>`;list.appendChild(n2);});
              });
              list.appendChild(nn);
            });
          });
          list.appendChild(nli);
        });
      });
      list.appendChild(li);
    });
    this.modal = new bootstrap.Modal(document.getElementById('colOrderModal'));
    this.modal.show();
  },
  apply(order) {
    const ths = document.querySelectorAll('#itemsTable thead th[data-col]');
    const tbody = document.getElementById('itemsBody');
    const colMap = {};
    ths.forEach(th => { colMap[th.dataset.col] = th; });
    const headerRow = ths[0]?.parentNode; if (!headerRow) return;
    const firstTh = headerRow.querySelector('th:first-child');
    const lastTh = headerRow.querySelector('th:last-child');
    headerRow.innerHTML = '';
    if (firstTh) headerRow.appendChild(firstTh);
    order.forEach(col => { if (colMap[col]) headerRow.appendChild(colMap[col]); });
    if (lastTh) headerRow.appendChild(lastTh);
    tbody.querySelectorAll('tr').forEach(tr => {
      const cells = Array.from(tr.querySelectorAll('td'));
      const numCell = cells[0]; const actionCell = cells[cells.length - 1];
      const cellMap = {};
      ths.forEach((th, i) => { if (th.dataset.col) cellMap[th.dataset.col] = cells[i]; });
      tr.innerHTML = '';
      if (numCell) tr.appendChild(numCell);
      order.forEach(col => {
        const th = colMap[col]; if (!th) return;
        const idx = Array.from(headerRow.querySelectorAll('th')).indexOf(th);
        if (cells[idx]) tr.appendChild(cells[idx]);
      });
      if (actionCell) tr.appendChild(actionCell);
    });
  }
};
function openColumnOrder() { ColOrder.open(); }
function applyColOrder() {
  const list = document.getElementById('colOrderList');
  const order = Array.from(list.children).map(c => c.dataset.col);
  ColOrder.saveOrder(order); ColOrder.apply(order);
  if (ColOrder.modal) ColOrder.modal.hide();
}

// ============ PRODUCT SEARCH (Popup Window) ============
function openProductSearch(rowIndex) {
  const storeId = document.getElementById('store_id').value;
  if (!storeId) { alert('اختر المخزن أولاً'); document.getElementById('store_id').focus(); return; }
  _currentRowIndex = rowIndex;
  const url = '<?= APP_URL ?>/includes/product-search-popup-new.php?mode=sales&store_id=' + storeId + '&row=' + rowIndex;
  window.productSearchWin = window.open(url, 'productSearch', 'width=1100,height=700,scrollbars=yes,resizable=yes');
}
// Callback from popup - receives product object only (row index stored in global var)
function onProductSelected(product) {
  const rows = document.querySelectorAll('#itemsBody tr');
  const row = rows[_currentRowIndex];
  if (!row) return;
  const setVal = (col, val) => { const el = row.querySelector('[data-col="'+col+'"]'); if (el) el.value = val || ''; };
  const setText = (col, val) => { const el = row.querySelector('[data-col="'+col+'"]'); if (el) el.textContent = val || ''; };
  setVal('product_id', product.id);
  setText('product_code', product.product_code || '');
  setText('product_name', product.product_name || '');
  setText('barcode', product.barcode || '');
  const unitSel = row.querySelector('[data-col="unit"]');
  if (unitSel && product.unit_name) {
    let found = false;
    for (let o of unitSel.options) { if (o.text === product.unit_name) { o.selected = true; found = true; break; } }
    if (!found) { const opt = document.createElement('option'); opt.value = product.unit_name; opt.textContent = product.unit_name; opt.selected = true; unitSel.appendChild(opt); }
  }
  setVal('unit_cost', product.unit_cost || product.avg_cost || product.last_cost || 0);
  setVal('sell_price', product.sell_price || 0);
  const vatPct = parseFloat(product.vat_percent || product.vat_pct || 0);
  setVal('vat_pct', vatPct);
  setVal('quantity', product.quantity || 1);
  const batchInp = row.querySelector('[data-col="batch"]');
  const expSel = row.querySelector('[data-col="expiry"]');
  if (product.batches && product.batches.length > 0) {
    if (expSel) {
      expSel.innerHTML = '<option value="">-- اختر --</option>';
      let nearestBatchId = '', nearestDate = null;
      product.batches.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id || b.batch_number;
        opt.textContent = (b.exp_date || '') + ' (' + (b.quantity || 0) + ')';
        if (b.batch_number) opt.dataset.batch = b.batch_number;
        if (b.exp_date) opt.dataset.exp = b.exp_date;
        expSel.appendChild(opt);
        if (b.exp_date) {
          const bd = new Date(b.exp_date);
          if (!nearestDate || bd < nearestDate) { nearestDate = bd; nearestBatchId = b.id || b.batch_number; }
        }
      });
      if (nearestBatchId) { expSel.value = nearestBatchId; }
    }
    if (batchInp && product.batches[0]) batchInp.value = product.batches[0].batch_number || '';
  } else if (product.exp_date || product.batch_id) {
    if (expSel) {
      expSel.innerHTML = '<option value="">-- اختر --</option>';
      const opt = document.createElement('option');
      opt.value = product.batch_id || '';
      opt.textContent = (product.exp_date || '') + ' (' + (product.stock_qty || 0) + ')';
      if (product.exp_date) opt.dataset.exp = product.exp_date;
      expSel.appendChild(opt);
      if (product.batch_id) expSel.value = product.batch_id;
    }
    if (batchInp) batchInp.value = product.batch_id || '';
  }
  calc(row);
  recalcAll();
  row.classList.add('selected');
}

// ============ INLINE CUSTOMER SEARCH ============
function searchCustomerInline(q) {
  const dd = document.getElementById('custDropdown');
  if (!q || q.length < 1) { dd.classList.remove('show'); return; }
  const qlower = q.toLowerCase().trim();
  const matches = allCustomers.filter(c => {
    const name = (c.customer_name || '').toLowerCase();
    const code = (c.customer_code || '').toLowerCase();
    const phone = (c.phone || '').toLowerCase();
    return name.includes(qlower) || code.includes(qlower) || phone.includes(qlower);
  }).slice(0, 10);
  if (matches.length === 0) { dd.classList.remove('show'); return; }
  let h = '';
  matches.forEach(c => {
    h += `<div class="dd-item" onclick="selectInlineCustomer(${c.id})">
      <i class="bi bi-person" style="color:var(--primary)"></i>
      <div style="flex:1"><b>${escapeHtml(c.customer_name)}</b>
      <small>${c.phone || ''}</small></div>
      <span class="dd-code">${escapeHtml(c.customer_code || '')}</span>
    </div>`;
  });
  dd.innerHTML = h;
  dd.classList.add('show');
}
function hideCustDropdown() {
  setTimeout(() => document.getElementById('custDropdown').classList.remove('show'), 200);
}
function selectInlineCustomer(id) {
  const c = allCustomers.find(x => x.id == id);
  if (!c) return;
  onCustomerSelected(c);
  document.getElementById('customer_code_input').value = c.customer_code || c.customer_name || '';
  document.getElementById('custDropdown').classList.remove('show');
}
function escapeHtml(t) {
  const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
}

// ============ ROW MANAGEMENT ============
let selectedRowIndex = -1;
function addNewRow() {
  document.getElementById('emptyMsg').style.display = 'none';
  const tbody = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  const rowIdx = tbody.children.length;
  const unitOptions = allUnits.map(u => `<option value="${u.unit_name_ar}">${u.unit_name_ar}</option>`).join('');
  tr.innerHTML = `
    <td class="num">${rowIdx + 1}</td>
    <td><div class="barcode-w"><input type="text" data-col="barcode" onkeydown="handleBarcodeKey(event, this)" placeholder="F2 بحث"><button type="button" class="btn-f2" onclick="openProductSearch(${rowIdx})">F2</button></div></td>
    <td><span data-col="product_code"></span><input type="hidden" data-col="product_id" value="0"></td>
    <td class="product-name"><span data-col="product_name"></span></td>
    <td><select data-col="unit">${unitOptions}</select></td>
    <td><input type="number" data-col="quantity" value="1" min="0" step="0.01" onchange="calc(this.closest('tr'))"></td>
    <td><input type="number" data-col="bonus" value="0" min="0" step="0.01" onchange="calc(this.closest('tr'))"></td>
    <td><input type="number" data-col="unit_cost" value="0" min="0" step="0.01" onchange="calc(this.closest('tr'))"></td>
    <td><input type="number" data-col="sell_price" value="0" min="0" step="0.01" onchange="calc(this.closest('tr'))"></td>
    <td><input type="number" data-col="disc_pct" value="0" min="0" max="100" step="0.01" onchange="onDiscPct(this)"></td>
    <td><input type="number" data-col="disc_val" value="0" min="0" step="0.01" onchange="calc(this.closest('tr'))"></td>
    <td><input type="number" data-col="vat_pct" value="0" min="0" step="0.01" onchange="calc(this.closest('tr'))"></td>
    <td class="row-calc"><span data-col="vat_val">0.00</span></td>
    <td class="row-total"><span data-col="total">0.00</span></td>
    <td class="row-calc"><span data-col="profit" style="color:var(--green)">0.00</span></td>
    <td><input type="text" data-col="batch" value="" style="min-width:70px"></td>
    <td><select data-col="expiry"><option value="">-- اختر --</option></select></td>
    <td><i class="bi bi-trash btn-del" onclick="deleteRow(this)"></i></td>
  `;
  tr.addEventListener('click', () => selectRow(rowIdx));
  tbody.appendChild(tr);
  selectedRowIndex = rowIdx;
  updateRowNumbers();
  setTimeout(() => {
    const inp = tr.querySelector('[data-col="barcode"]');
    if (inp) inp.focus();
  }, 10);
}
function selectRow(idx) {
  document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
    tr.classList.toggle('selected', i === idx);
  });
  selectedRowIndex = idx;
}
function deleteRow(el) {
  const tr = el.closest('tr');
  tr.remove();
  updateRowNumbers();
  recalcAll();
  const rows = document.querySelectorAll('#itemsBody tr');
  if (rows.length === 0) {
    document.getElementById('emptyMsg').style.display = 'block';
  }
}
function deleteSelectedRow() {
  const rows = document.querySelectorAll('#itemsBody tr');
  if (selectedRowIndex >= 0 && selectedRowIndex < rows.length) {
    rows[selectedRowIndex].remove();
    updateRowNumbers();
    recalcAll();
    selectedRowIndex = -1;
    if (document.querySelectorAll('#itemsBody tr').length === 0) {
      document.getElementById('emptyMsg').style.display = 'block';
    }
  }
}
function updateRowNumbers() {
  document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
    tr.querySelector('td:first-child').textContent = i + 1;
    tr.dataset.index = i;
    const btn = tr.querySelector('.btn-f2');
    if (btn) btn.onclick = () => openProductSearch(i);
    const bc = tr.querySelector('[data-col="barcode"]');
    if (bc) bc.onkeydown = (e) => handleBarcodeKey(e, bc);
    tr.onclick = () => selectRow(i);
  });
}

// ============ CALCULATIONS ============
function calc(tr) {
  const getVal = (col) => {
    const el = tr.querySelector('[data-col="'+col+'"]');
    if (!el) return 0;
    return parseFloat(el.value) || 0;
  };
  const setText = (col, val) => {
    const el = tr.querySelector('[data-col="'+col+'"]');
    if (el) el.textContent = formatNum(val);
  };
  const qty = getVal('quantity');
  const cost = getVal('unit_cost');
  const price = getVal('sell_price');
  const discPct = getVal('disc_pct');
  const discVal = getVal('disc_val');
  const vatPct = getVal('vat_pct');
  const base = qty * price;
  const afterDisc = base * (1 - discPct / 100) - discVal;
  const vatVal = Math.max(0, afterDisc) * (vatPct / 100);
  const total = Math.max(0, afterDisc) + vatVal;
  const profit = (price - cost) * qty;
  setText('vat_val', vatVal);
  setText('total', total);
  setText('profit', profit);
}
function recalcAll() {
  let subtotal = 0, totalVat = 0, totalQty = 0, totalProfit = 0, itemCount = 0;
  document.querySelectorAll('#itemsBody tr').forEach(tr => {
    calc(tr);
    const qty = parseFloat(tr.querySelector('[data-col="quantity"]')?.value) || 0;
    const price = parseFloat(tr.querySelector('[data-col="sell_price"]')?.value) || 0;
    const cost = parseFloat(tr.querySelector('[data-col="unit_cost"]')?.value) || 0;
    const discPct = parseFloat(tr.querySelector('[data-col="disc_pct"]')?.value) || 0;
    const discVal = parseFloat(tr.querySelector('[data-col="disc_val"]')?.value) || 0;
    const vatPct = parseFloat(tr.querySelector('[data-col="vat_pct"]')?.value) || 0;
    const base = qty * price;
    const afterDisc = base * (1 - discPct / 100) - discVal;
    const vatVal = Math.max(0, afterDisc) * (vatPct / 100);
    subtotal += Math.max(0, afterDisc);
    totalVat += vatVal;
    totalQty += qty;
    totalProfit += (price - cost) * qty;
    itemCount++;
  });
  const discPct = parseFloat(document.getElementById('discount_pct')?.value) || 0;
  const discVal = parseFloat(document.getElementById('discount_val')?.value) || 0;
  const extraDiscPct = parseFloat(document.getElementById('extra_discount_pct')?.value) || 0;
  const extraDiscVal = parseFloat(document.getElementById('extra_discount_val')?.value) || 0;
  const paid = parseFloat(document.getElementById('paid_amount')?.value) || 0;
  let grand = subtotal + totalVat - discVal;
  if (discPct > 0) grand -= (subtotal + totalVat) * (discPct / 100);
  if (extraDiscPct > 0) grand -= grand * (extraDiscPct / 100);
  grand -= extraDiscVal;
  if (grand < 0) grand = 0;
  const remaining = Math.max(0, grand - paid);
  document.getElementById('subtotalDisplay').textContent = formatNum(subtotal);
  document.getElementById('vatDisplay').textContent = formatNum(totalVat);
  document.getElementById('totalItems').textContent = itemCount;
  document.getElementById('totalQty').textContent = formatNum(totalQty);
  document.getElementById('grandDisplay').textContent = formatNum(grand);
  document.getElementById('remainingDisplay').textContent = formatNum(remaining);
}
function onDiscPct(el) {
  const tr = el.closest('tr');
  const qty = parseFloat(tr.querySelector('[data-col="quantity"]')?.value) || 0;
  const price = parseFloat(tr.querySelector('[data-col="sell_price"]')?.value) || 0;
  const pct = parseFloat(el.value) || 0;
  const discVal = qty * price * (pct / 100);
  const valEl = tr.querySelector('[data-col="disc_val"]');
  if (valEl) valEl.value = formatNum(discVal);
  calc(tr);
  recalcAll();
}
function formatNum(n) {
  if (n === null || n === undefined || isNaN(n)) return '0.00';
  return parseFloat(n).toFixed(2);
}

// ============ BARCODE KEY HANDLER ============
function handleBarcodeKey(e, inp) {
  if (e.key === 'F2') {
    e.preventDefault();
    const tr = inp.closest('tr');
    const rows = document.querySelectorAll('#itemsBody tr');
    let idx = 0;
    rows.forEach((r, i) => { if (r === tr) idx = i; });
    openProductSearch(idx);
  }
}

// ============ PAYMENT TYPE ============
function setPayType(type, btn) {
  document.getElementById('payment_method').value = type;
  document.querySelectorAll('.pay-types .pay-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (type === 'cash' || type === 'visa') {
    const grandText = document.getElementById('grandDisplay').textContent;
    document.getElementById('paid_amount').value = parseFloat(grandText) || 0;
  }
}

// ============ CUSTOMER SEARCH & PROFILE ============
function openCustomerSearch() {
  const q = document.getElementById('customer_code_input').value;
  const url = '<?= APP_URL ?>/includes/customer-search-popup.php?q=' + encodeURIComponent(q);
  window.customerSearchWin = window.open(url, 'customerSearch', 'width=900,height=600,scrollbars=yes,resizable=yes');
}
function onCustomerSelected(customer) {
  document.getElementById('customer_id').value = customer.id;
  _currentCustomerId = customer.id;
  document.getElementById('customer_code_input').value = customer.customer_code || customer.customer_name || '';
  document.getElementById('cust_name').textContent = customer.customer_name || '';
  document.getElementById('cust_info').textContent = (customer.phone || '') + ' | الرصيد: ' + (customer.balance || 0);
  document.getElementById('customer_display').classList.add('show');
}
function clearCustomer() {
  document.getElementById('customer_id').value = '0';
  _currentCustomerId = 0;
  document.getElementById('customer_code_input').value = '';
  document.getElementById('customer_display').classList.remove('show');
}
function handleCustomerKey(e) {
  if (e.key === 'F4') { e.preventDefault(); openCustomerSearch(); return; }
  if (e.key === 'Enter') {
    e.preventDefault();
    const code = e.target.value.trim();
    if (!code) return;
    const dd = document.getElementById('custDropdown');
    if (dd.classList.contains('show') && dd.children.length > 0) {
      const first = dd.querySelector('.dd-item');
      if (first) first.click();
      return;
    }
    const found = allCustomers.find(c => (c.customer_code || '') === code || String(c.id) === code);
    if (found) { onCustomerSelected(found); }
    else { openCustomerSearch(); }
  }
  if (e.key === 'Escape') {
    document.getElementById('custDropdown').classList.remove('show');
  }
}
function openCustomerProfile() {
  if (!_currentCustomerId) { alert('اختر عميل أولاً'); return; }
  const url = '<?= APP_URL ?>/includes/customer-profile-popup.php?customer_id=' + _currentCustomerId;
  window.open(url, 'customerProfile', 'width=1000,height=750,scrollbars=yes,resizable=yes');
}
function openCustomerLedger() {
  if (!_currentCustomerId) { alert('اختر عميل أولاً'); return; }
  const url = '<?= APP_URL ?>/includes/customer-profile-popup.php?customer_id=' + _currentCustomerId + '&tab=ledger';
  window.open(url, 'customerLedger', 'width=1000,height=750,scrollbars=yes,resizable=yes');
}

// ============ SUBMIT ============
function prepareSubmit() {
  const branchId = document.getElementById('branch_id').value;
  const storeId = document.getElementById('store_id').value;
  if (!branchId) { alert('يجب اختيار الفرع أولاً'); document.getElementById('branch_id').focus(); return false; }
  if (!storeId) { alert('يجب اختيار المخزن'); document.getElementById('store_id').focus(); return false; }
  const rows = document.querySelectorAll('#itemsBody tr');
  if (rows.length === 0) { alert('يجب إضافة صنف واحد على الأقل'); return false; }
  const items = [];
  let valid = true;
  rows.forEach((tr, i) => {
    const get = (col) => {
      const el = tr.querySelector('[data-col="'+col+'"]');
      return el ? (el.value !== undefined ? el.value : el.textContent) : '';
    };
    const qty = parseFloat(get('quantity')) || 0;
    const price = parseFloat(get('sell_price')) || 0;
    if (qty <= 0) { alert('الكمية يجب أن تكون أكبر من صفر في الصف ' + (i + 1)); valid = false; }
    if (price <= 0) { alert('سعر البيع يجب أن يكون أكبر من صفر في الصف ' + (i + 1)); valid = false; }
    const expSel = tr.querySelector('[data-col="expiry"]');
    let batchId = null, expDate = null, batchNum = get('batch');
    if (expSel && expSel.value) {
      const opt = expSel.options[expSel.selectedIndex];
      batchId = expSel.value;
      expDate = opt.dataset.exp || null;
      if (opt.dataset.batch) batchNum = opt.dataset.batch;
    }
    const discPct = parseFloat(get('disc_pct')) || 0;
    const vatPct = parseFloat(get('vat_pct')) || 0;
    const cost = parseFloat(get('unit_cost')) || 0;
    const base = qty * price;
    const afterDisc = base * (1 - discPct/100);
    const discVal = base * (discPct/100);
    const vatVal = afterDisc * (vatPct/100);
    items.push({
      product_id: parseInt(get('product_id')) || 0,
      product_code: get('product_code'),
      product_name: get('product_name'),
      barcode: get('barcode'),
      unit_name: get('unit'),
      quantity: qty,
      unit_cost: cost,
      sell_price: price,
      discount_percent: discPct,
      discount_val: discVal,
      vat_percent: vatPct,
      vat_val: vatVal,
      line_total: afterDisc + vatVal,
      profit_val: (price - cost) * qty,
      expiry_date: expDate,
      batch_number: batchNum,
      batch_id: batchId
    });
  });
  if (!valid) return false;
  document.getElementById('itemsData').value = JSON.stringify(items);
  document.getElementById('saveBtn').disabled = true;
  document.getElementById('saveBtn').innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الحفظ...';
  return true;
}

// ============ KEYBOARD SHORTCUTS ============
document.addEventListener('keydown', function(e) {
  if (e.ctrlKey && e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('saleForm').submit();
  }
  if (e.key === 'F7') {
    e.preventDefault();
    addNewRow();
  }
  if (e.key === 'F9') {
    e.preventDefault();
    deleteSelectedRow();
  }
  if (e.key === 'ArrowDown' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT') {
    e.preventDefault();
    const rows = document.querySelectorAll('#itemsBody tr');
    if (selectedRowIndex < rows.length - 1) selectRow(selectedRowIndex + 1);
  }
  if (e.key === 'ArrowUp' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT') {
    e.preventDefault();
    if (selectedRowIndex > 0) selectRow(selectedRowIndex - 1);
  }
});

// ============ INIT ============
document.addEventListener('DOMContentLoaded', function() {
  const savedOrder = ColOrder.getOrder();
  if (savedOrder && savedOrder.length > 0) ColOrder.apply(savedOrder);
  ['discount_pct','discount_val','extra_discount_pct','extra_discount_val','paid_amount'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalcAll);
  });
});
</script>
</body>
</html>
