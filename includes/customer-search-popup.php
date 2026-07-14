<?php
/**
 * شاشة بحث عن العملاء - Popup Window
 * ينادي window.opener.onCustomerSelected(customer) لما تختار عميل
 */
require_once __DIR__ . '/../core/config.php';
$db = getDB();

// Check which columns exist in customers table
$cols = $db->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
$hasBalance = in_array('balance', $cols);
$hasAddress = in_array('address', $cols);
$hasBranch = in_array('branch_id', $cols);

$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

$q = $_GET['q'] ?? '';
$branch_filter = $_GET['branch'] ?? '';
$customers = [];

// Build query dynamically
$select = "c.id, c.customer_name, c.customer_code, c.phone, c.is_active";
$join = "";
if ($hasBalance) $select .= ", c.balance";
if ($hasAddress) $select .= ", c.address";
if ($hasBranch) { $select .= ", c.branch_id, b.branch_name"; $join = " LEFT JOIN branches b ON c.branch_id = b.id"; }

$where = "WHERE c.is_active = 1";
$params = [];

if ($q) {
    $where .= " AND (c.customer_name LIKE ? OR c.customer_code LIKE ? OR c.phone LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
if ($branch_filter && $hasBranch) {
    $where .= " AND c.branch_id = ?";
    $params[] = $branch_filter;
}

$sql = "SELECT $select FROM customers c $join $where ORDER BY c.customer_name LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>بحث عن عميل</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2;--green:#198754;--red:#dc3545;}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;}
.search-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:15px 20px;text-align:center;}
.search-header h4{margin:0;font-size:18px;}
.search-box{padding:15px 20px;background:#fff;border-bottom:1px solid #ddd;}
.search-input{font-size:15px;padding:10px 15px;border:2px solid #e9ecef;border-radius:10px;width:100%;transition:all .2s;}
.search-input:focus{border-color:var(--primary);outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.15);}
.filter-row{padding:0 20px 10px;background:#fff;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.results-table{width:100%;border-collapse:collapse;font-size:13px;}
.results-table thead th{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:10px;font-size:12px;font-weight:600;position:sticky;top:0;z-index:10;}
.results-table tbody td{padding:8px 10px;border-bottom:1px solid #e9ecef;vertical-align:middle;}
.results-table tbody tr{cursor:pointer;transition:all .15s;}
.results-table tbody tr:hover{background:#e8f0fe!important;}
.results-table tbody tr.selected{background:linear-gradient(90deg,#d4edda,#c3e6cb)!important;border-right:3px solid var(--green);}
.customer-name{font-weight:600;color:#333;}
.customer-code{font-family:monospace;font-size:12px;color:#666;background:#f0f0f0;padding:2px 6px;border-radius:4px;}
.customer-address{font-size:11px;color:#888;display:block;margin-top:3px;}
.customer-branch{font-size:11px;color:var(--primary);background:#e3f2fd;padding:1px 8px;border-radius:10px;display:inline-block;margin-top:3px;}
.no-results{text-align:center;padding:40px;color:#999;}
.detail-panel{background:#fff;border-radius:12px;margin:15px;padding:15px;box-shadow:0 2px 10px rgba(0,0,0,0.08);border-top:3px solid var(--green);display:none;}
.detail-panel.show{display:block;animation:fadeIn .3s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
</style>
</head>
<body>

<div class="search-header">
    <h4><i class="bi bi-people"></i> بحث عن عميل</h4>
    <small style="opacity:.8">اختر عميل من القائمة أو اكتب للبحث</small>
</div>

<div class="search-box">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="searchInput" class="form-control search-input" placeholder="اكتب اسم العميل أو الكود أو التليفون..." value="<?= htmlspecialchars($q) ?>" onkeyup="if(event.key==='Enter')doSearch()" autofocus>
        <button class="btn btn-primary" type="button" onclick="doSearch()"><i class="bi bi-search"></i> بحث</button>
    </div>
</div>

<?php if (count($branches) > 0): ?>
<div class="filter-row">
    <label style="font-size:13px;color:#666"><i class="bi bi-funnel"></i> فلتر بالفرع:</label>
    <select id="branchFilter" class="form-select form-select-sm" style="width:180px" onchange="doSearch()">
        <option value="">كل الفروع</option>
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $branch_filter == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['branch_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <span class="text-muted" style="font-size:12px"><?= count($customers) ?> عميل</span>
</div>
<?php endif; ?>

<div class="px-3">
    <div class="card">
        <div class="table-responsive" style="max-height:350px;overflow-y:auto;">
            <table class="table table-hover results-table mb-0">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>اسم العميل</th>
                        <th style="width:80px">الكود</th>
                        <th style="width:100px">التليفون</th>
                        <?php if ($hasBalance): ?><th style="width:80px">الرصيد</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="resultsBody">
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="<?= $hasBalance ? 5 : 4 ?>" class="no-results"><i class="bi bi-inbox" style="font-size:28px"></i><br>لا يوجد عملاء - اكتب للبحث</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $i => $c): ?>
                        <tr onclick="selectCustomer(<?= $i ?>)" id="row_<?= $i ?>" 
                            data-id="<?= $c['id'] ?>" 
                            data-name="<?= htmlspecialchars($c['customer_name']) ?>" 
                            data-code="<?= htmlspecialchars($c['customer_code'] ?? '') ?>" 
                            data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>" 
                            data-balance="<?= $c['balance'] ?? 0 ?>" 
                            data-address="<?= htmlspecialchars($c['address'] ?? '') ?>"
                            data-branch="<?= htmlspecialchars($c['branch_name'] ?? '') ?>">
                            <td><?= $i + 1 ?></td>
                            <td>
                                <span class="customer-name"><?= htmlspecialchars($c['customer_name']) ?></span>
                                <?php if ($hasAddress && $c['address']): ?>
                                <span class="customer-address"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($c['address']) ?></span>
                                <?php endif; ?>
                                <?php if ($hasBranch && $c['branch_name']): ?>
                                <span class="customer-branch"><i class="bi bi-building"></i> <?= htmlspecialchars($c['branch_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="customer-code"><?= htmlspecialchars($c['customer_code'] ?? '-') ?></span></td>
                            <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                            <?php if ($hasBalance): ?><td><?= number_format($c['balance'] ?? 0, 2) ?></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail Panel -->
<div class="detail-panel" id="detailPanel">
    <h5 class="text-success mb-3"><i class="bi bi-check-circle-fill"></i> <span id="selName"></span></h5>
    <div class="row g-2 mb-3">
        <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">الكود</small><strong id="selCode">-</strong></div></div>
        <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">التليفون</small><strong id="selPhone">-</strong></div></div>
        <?php if ($hasAddress): ?>
        <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">العنوان</small><strong id="selAddress">-</strong></div></div>
        <?php endif; ?>
        <?php if ($hasBalance): ?>
        <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">الرصيد</small><strong id="selBalance" class="text-success">-</strong></div></div>
        <?php endif; ?>
    </div>
    <div class="text-end">
        <button class="btn btn-success btn-lg" onclick="confirmSelect()">
            <i class="bi bi-check-lg"></i> اختيار هذا العميل
        </button>
    </div>
</div>

<script>
let selectedCustomer = null;

function doSearch() {
    const q = document.getElementById('searchInput').value.trim();
    const branch = document.getElementById('branchFilter')?.value || '';
    let url = '?q=' + encodeURIComponent(q);
    if (branch) url += '&branch=' + encodeURIComponent(branch);
    location.href = url;
}

function selectCustomer(idx) {
    document.querySelectorAll('tbody tr').forEach(r => r.classList.remove('selected'));
    const row = document.getElementById('row_' + idx);
    if (!row) return;
    row.classList.add('selected');
    
    selectedCustomer = {
        id: row.dataset.id,
        customer_name: row.dataset.name,
        customer_code: row.dataset.code,
        phone: row.dataset.phone,
        balance: row.dataset.balance || 0,
        address: row.dataset.address || '',
        branch_name: row.dataset.branch || ''
    };
    
    document.getElementById('detailPanel').classList.add('show');
    document.getElementById('selName').textContent = selectedCustomer.customer_name;
    document.getElementById('selCode').textContent = selectedCustomer.customer_code || '-';
    document.getElementById('selPhone').textContent = selectedCustomer.phone || '-';
    <?php if ($hasAddress): ?>document.getElementById('selAddress').textContent = selectedCustomer.address || '-';<?php endif; ?>
    <?php if ($hasBalance): ?>document.getElementById('selBalance').textContent = parseFloat(selectedCustomer.balance || 0).toFixed(2);<?php endif; ?>
}

function confirmSelect() {
    if (!selectedCustomer) { alert('اختر عميل أولاً'); return; }
    if (window.opener && window.opener.onCustomerSelected) {
        window.opener.onCustomerSelected(selectedCustomer);
        window.close();
    } else { alert('لا يمكن إرسال البيانات للصفحة الأم'); }
}

// Double click to select
document.querySelectorAll('tbody tr').forEach((row, idx) => {
    row.addEventListener('dblclick', () => { selectCustomer(idx); confirmSelect(); });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') window.close(); });
document.getElementById('searchInput').focus();
</script>

</body>
</html>
