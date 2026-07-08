<?php
/**
 * شاشة بحث متقدمة عن الأصناف - Popup Window
 * 
 * Parameters:
 *   - store_id (required): معرف المخزن
 *   - mode: 'sales' | 'purchase' - يتحكم في عرض الأصناف بدون رصيد
 *   - callback: اسم دالة الاستدعاء في النافذة الأم
 */
$db_host = 'localhost';
$db_name = 'nile_center';
$db_user = 'root';
$db_pass = '';

try {
    $db = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
}

$store_id = intval($_GET['store_id'] ?? 0);
$mode = $_GET['mode'] ?? 'sales';
$is_sales_mode = ($mode === 'sales');
$callback = htmlspecialchars($_GET['callback'] ?? 'onProductSelected');

if ($store_id <= 0) die('معرف المخزن مطلوب');

$store = $db->prepare("SELECT store_name FROM stores WHERE id = ?");
$store->execute([$store_id]);
$store_name = $store->fetch()['store_name'] ?? 'المخزن';

$categories = $db->query("SELECT id, category_name_ar as category_name FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll();
$companies = $db->query("SELECT id, company_name_ar as company_name FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar LIMIT 100")->fetchAll();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_url = $protocol . '://' . $host . $script_dir;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بحث عن صنف - <?= htmlspecialchars($store_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --danger: #dc3545; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; min-height: 100vh; }
        .search-header { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 15px 25px; text-align: center; }
        .search-header h3 { margin: 0; font-size: 20px; }
        .store-badge { background: rgba(255,255,255,0.2); padding: 3px 12px; border-radius: 15px; font-size: 12px; margin-top: 5px; display: inline-block; }
        .mode-badge { background: rgba(255,255,255,0.3); padding: 3px 12px; border-radius: 15px; font-size: 11px; margin-top: 5px; display: inline-block; margin-right: 8px; }
        .mode-badge.sales { background: #d4edda; color: #155724; }
        .mode-badge.purchase { background: #cce5ff; color: #004085; }
        .search-type-wrap { display: flex; gap: 5px; padding: 15px 20px 0; }
        .search-type-btn { flex: 1; padding: 8px 5px; border: 2px solid #e9ecef; background: white; border-radius: 8px; cursor: pointer; text-align: center; font-size: 12px; transition: all 0.2s; font-weight: 600; }
        .search-type-btn:hover { border-color: var(--primary); }
        .search-type-btn.active { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary); }
        .search-main-input { font-size: 16px; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; transition: all 0.3s; }
        .search-main-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
        .results-table thead th { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600; padding: 10px 12px; border: none; position: sticky; top: 0; z-index: 10; }
        .results-table tbody td { font-size: 12px; padding: 8px 12px; vertical-align: middle; }
        .results-table tbody tr { cursor: pointer; transition: all 0.15s; }
        .results-table tbody tr:hover { background: #e8f0fe !important; }
        .results-table tbody tr.selected { background: linear-gradient(90deg, #d4edda 0%, #c3e6cb 100%) !important; border-right: 3px solid var(--success); }
        .results-table tbody tr.no-stock { opacity: 0.6; }
        .results-table tbody tr.no-stock:hover { background: #f8d7da !important; }
        .product-name { font-weight: 600; color: #333; }
        .sell-price { font-weight: 700; color: var(--primary); }
        .stock-qty { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .stock-qty.has-stock { background: #d4edda; color: #155724; }
        .stock-qty.low-stock { background: #fff3cd; color: #856404; }
        .stock-qty.no-stock { background: #f8d7da; color: #721c24; }
        .detail-panel { background: white; border-radius: 12px; margin: 15px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-top: 3px solid var(--success); display: none; }
        .detail-panel.show { display: block; animation: fadeIn 0.3s ease; }
        .qty-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 12px 15px; margin-top: 10px; display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .total-display { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 8px 20px; border-radius: 8px; font-size: 16px; font-weight: 700; min-width: 100px; text-align: center; }
        .error-detail { background: #f8d7da; color: #721c24; padding: 10px 15px; border-radius: 8px; font-size: 12px; margin: 10px 20px; display: none; }
        .error-detail.show { display: block; }
        .stock-summary { background: #e8f5e9; border-radius: 8px; padding: 8px 12px; margin-top: 8px; font-size: 12px; }
        .stock-summary .stock-total { font-weight: 700; color: #155724; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="search-header">
        <h3><i class="bi bi-search"></i> بحث عن صنف</h3>
        <span class="store-badge"><i class="bi bi-building"></i> <?= htmlspecialchars($store_name) ?></span>
        <?php if ($is_sales_mode): ?>
        <span class="mode-badge sales"><i class="bi bi-cart-check"></i> بيع - رصيد فقط</span>
        <?php else: ?>
        <span class="mode-badge purchase"><i class="bi bi-cart-plus"></i> شراء - كل الأصناف</span>
        <?php endif; ?>
    </div>
    
    <div class="search-type-wrap mb-2">
        <div class="search-type-btn active" data-type="name" onclick="setSearchType('name')"><i class="bi bi-fonts"></i> اسم</div>
        <div class="search-type-btn" data-type="barcode" onclick="setSearchType('barcode')"><i class="bi bi-upc-scan"></i> باركود</div>
        <div class="search-type-btn" data-type="code" onclick="setSearchType('code')"><i class="bi bi-hash"></i> كود</div>
        <div class="search-type-btn" data-type="scientific" onclick="setSearchType('scientific')"><i class="bi bi-flask"></i> مادة فعالة</div>
        <div class="search-type-btn" data-type="company" onclick="setSearchType('company')"><i class="bi bi-building"></i> شركة</div>
    </div>
    
    <div class="px-4 mb-3">
        <div class="input-group">
            <input type="text" id="searchInput" class="form-control search-main-input" placeholder="اكتب هنا للبحث واضغط Enter..." onkeyup="handleSearchKey(event)" autocomplete="off" autofocus>
            <button class="btn btn-primary" type="button" onclick="doSearch()"><i class="bi bi-search"></i></button>
        </div>
    </div>
    
    <div class="px-4 mb-3">
        <div class="row g-2">
            <div class="col-md-3">
                <select id="filterCategory" class="form-select form-select-sm" onchange="doSearch()">
                    <option value="">كل التصنيفات</option>
                    <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="filterCompany" class="form-select form-select-sm" onchange="doSearch()">
                    <option value="">كل الشركات</option>
                    <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="number" id="priceFrom" class="form-control form-control-sm" placeholder="سعر من" onchange="doSearch()"></div>
            <div class="col-md-2"><input type="number" id="priceTo" class="form-control form-control-sm" placeholder="سعر إلى" onchange="doSearch()"></div>
            <div class="col-md-2">
                <?php if (!$is_sales_mode): ?>
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" id="showNoStock" onchange="doSearch()">
                    <label class="form-check-label" for="showNoStock" style="font-size: 12px;">بدون رصيد</label>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="error-detail" id="errorDetail"></div>
    
    <div class="px-4">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                <small class="text-muted"><i class="bi bi-list-ul"></i> النتائج</small>
                <span class="badge bg-primary" id="resultCount">0</span>
            </div>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-hover results-table mb-0">
                    <thead><tr><th style="width:60px;">كود</th><th>اسم الصنف</th><th style="width:80px;">سعر البيع</th><th style="width:100px;">المادة الفعالة</th><th style="width:80px;">الرصيد</th></tr></thead>
                    <tbody id="resultsBody">
                        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-search" style="font-size: 24px;"></i><br>اكتب اسم الصنف واضغط Enter أو زر البحث</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-center gap-2 py-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="prevPage()" disabled id="btnPrev"><i class="bi bi-chevron-right"></i></button>
                <small class="text-muted pt-1" id="pageInfo">صفحة 1 من 1</small>
                <button class="btn btn-sm btn-outline-secondary" onclick="nextPage()" disabled id="btnNext"><i class="bi bi-chevron-left"></i></button>
            </div>
        </div>
    </div>
    
    <div class="detail-panel" id="detailPanel">
        <h5 class="text-primary mb-3"><i class="bi bi-check-circle-fill text-success"></i> <span id="detailName"></span></h5>
        <div class="row g-2 mb-3">
            <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">كود</small><strong id="detailCode">-</strong></div></div>
            <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">الشركة</small><strong id="detailCompany">-</strong></div></div>
            <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">التصنيف</small><strong id="detailCategory">-</strong></div></div>
            <div class="col"><div class="bg-light rounded p-2 text-center"><small class="text-muted d-block">الرصيد</small><strong id="detailStock" class="text-success">-</strong></div></div>
        </div>
        
        <div class="stock-summary" id="stockSummary" style="display:none;">
            <span class="stock-total">الرصيد الكلي: <span id="stockTotal">0</span></span>
            <span class="text-muted" style="margin-right:15px; font-size: 11px;">تاريخ الصلاحية يُختار تلقائياً حسب أقرب تاريخ</span>
        </div>
        
        <div class="qty-section">
            <div><label class="small text-muted">الكمية</label><input type="number" id="detailQty" class="form-control" value="1" min="0.001" step="0.001" style="width: 100px;" onchange="calcTotal()"></div>
            <div>
                <label class="small text-muted">تاريخ الصلاحية</label>
                <select id="detailExpDate" class="form-select" style="width: 180px;">
                    <option value="">-- اختر تاريخ --</option>
                </select>
            </div>
            <div><label class="small text-muted">تكلفة الوحدة</label><input type="number" id="detailCost" class="form-control" step="0.01" style="width: 100px;" readonly></div>
            <div><label class="small text-muted">سعر البيع</label><input type="number" id="detailSell" class="form-control" step="0.01" style="width: 100px;" onchange="calcTotal()"></div>
            <div class="total-display" id="detailTotal">0.00</div>
        </div>
        <div class="text-end mt-3">
            <button class="btn btn-success" onclick="confirmSelect()" id="btnConfirm"><i class="bi bi-check-lg"></i> اختيار</button>
            <span id="zeroStockWarning" class="text-danger ms-2" style="display:none; font-size: 13px;"><i class="bi bi-exclamation-triangle"></i> لا يمكن إضافة صنف بدون رصيد في فاتورة البيع</span>
        </div>
    </div>
    <div style="height: 30px;"></div>

    <script>
        const STORE_ID = <?= $store_id ?>;
        const IS_SALES = <?= $is_sales_mode ? 'true' : 'false' ?>;
        const API_BASE = '<?= $base_url ?>';
        let searchType = 'name', currentPage = 1, totalPages = 1, selectedProduct = null, allResults = [];
        
        function setSearchType(type) {
            searchType = type;
            document.querySelectorAll('.search-type-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector('.search-type-btn[data-type="' + type + '"]').classList.add('active');
            document.getElementById('searchInput').focus();
        }
        
        function handleSearchKey(e) { if (e.key === 'Enter') doSearch(); }
        
        async function doSearch() {
            const q = document.getElementById('searchInput').value.trim();
            const cat = document.getElementById('filterCategory').value;
            const comp = document.getElementById('filterCompany').value;
            const pFrom = document.getElementById('priceFrom').value;
            const pTo = document.getElementById('priceTo').value;
            const noStockEl = document.getElementById('showNoStock');
            const noStock = noStockEl ? noStockEl.checked : IS_SALES ? false : true;
            
            let url = API_BASE + '/product-search-api.php?store_id=' + STORE_ID + '&type=' + searchType;
            if (q) url += '&q=' + encodeURIComponent(q);
            if (cat) url += '&category=' + cat;
            if (comp) url += '&company=' + comp;
            if (pFrom) url += '&price_from=' + pFrom;
            if (pTo) url += '&price_to=' + pTo;
            if (noStock) url += '&show_no_stock=1';
            url += '&page=' + currentPage + '&limit=50';
            
            console.log('API URL:', url);
            
            document.getElementById('errorDetail').classList.remove('show');
            
            try {
                const res = await fetch(url);
                console.log('Response status:', res.status);
                
                if (!res.ok) {
                    const text = await res.text();
                    console.error('HTTP Error:', res.status, text);
                    showError('HTTP ' + res.status + ': ' + text.substring(0, 200));
                    return;
                }
                
                const data = await res.json();
                console.log('Response data:', data);
                
                if (data.error) { 
                    showError(data.error); 
                    return; 
                }
                
                allResults = data.products || [];
                totalPages = data.total_pages || 1;
                renderResults(allResults);
                document.getElementById('resultCount').textContent = (data.total || 0) + ' صنف';
                document.getElementById('btnPrev').disabled = currentPage <= 1;
                document.getElementById('btnNext').disabled = currentPage >= totalPages;
                document.getElementById('pageInfo').textContent = 'صفحة ' + currentPage + ' من ' + totalPages;
                
            } catch (err) { 
                console.error('Fetch error:', err);
                showError('خطأ في الاتصال: ' + err.message); 
            }
        }
        
        function renderResults(products) {
            const tbody = document.getElementById('resultsBody');
            if (!products.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox" style="font-size:24px;"></i><br>لا توجد نتائج</td></tr>';
                return;
            }
            tbody.innerHTML = products.map((p, i) => {
                const sc = p.stock_qty > 10 ? 'has-stock' : (p.stock_qty > 0 ? 'low-stock' : 'no-stock');
                const noStockClass = (p.stock_qty <= 0) ? 'no-stock' : '';
                return '<tr onclick="selectProduct(' + i + ')" id="row_' + i + '" class="' + noStockClass + '"><td><small class="text-muted font-monospace">' + (p.manual_code || p.product_code || '-') + '</small></td><td><span class="product-name">' + p.product_name + '</span></td><td class="sell-price">' + parseFloat(p.sell_price).toFixed(2) + '</td><td><small class="text-muted">' + (p.scientific_name || '-') + '</small></td><td><span class="stock-qty ' + sc + '">' + parseFloat(p.stock_qty).toFixed(0) + '</span></td></tr>';
            }).join('');
        }
        
        function selectProduct(idx) {
            document.querySelectorAll('tr').forEach(r => r.classList.remove('selected'));
            document.getElementById('row_' + idx)?.classList.add('selected');
            selectedProduct = allResults[idx];
            
            const isZeroStock = (selectedProduct.stock_qty || 0) <= 0;
            
            document.getElementById('detailPanel').classList.add('show');
            document.getElementById('detailName').textContent = selectedProduct.product_name;
            document.getElementById('detailCode').textContent = selectedProduct.manual_code || selectedProduct.product_code || '-';
            document.getElementById('detailCompany').textContent = selectedProduct.company_name || '-';
            document.getElementById('detailCategory').textContent = selectedProduct.category_name || '-';
            document.getElementById('detailStock').textContent = parseFloat(selectedProduct.stock_qty).toFixed(2);
            document.getElementById('detailCost').value = parseFloat(selectedProduct.unit_cost || selectedProduct.cost_price).toFixed(2);
            document.getElementById('detailSell').value = parseFloat(selectedProduct.sell_price).toFixed(2);
            
            const expSel = document.getElementById('detailExpDate');
            expSel.innerHTML = '';
            
            let totalStock = 0;
            let nearestBatchId = '';
            let nearestDate = null;
            
            if (selectedProduct.batches && selectedProduct.batches.length) {
                selectedProduct.batches.forEach((b, bIdx) => {
                    const qty = parseFloat(b.remaining_qty) || 0;
                    totalStock += qty;
                    
                    const o = document.createElement('option');
                    o.value = b.id;
                    o.textContent = b.exp_date + ' (رصيد: ' + qty.toFixed(0) + ')';
                    o.dataset.qty = qty;
                    expSel.appendChild(o);
                    
                    const batchDate = new Date(b.exp_date);
                    if (!nearestDate || batchDate < nearestDate) {
                        nearestDate = batchDate;
                        nearestBatchId = b.id;
                    }
                });
            } else {
                const o = document.createElement('option');
                o.value = '';
                o.textContent = 'لا يوجد تاريخ صلاحية';
                expSel.appendChild(o);
            }
            
            if (nearestBatchId) {
                expSel.value = nearestBatchId;
            }
            
            const stockSummary = document.getElementById('stockSummary');
            const stockTotal = document.getElementById('stockTotal');
            if (selectedProduct.has_expire && selectedProduct.batches && selectedProduct.batches.length) {
                stockSummary.style.display = 'block';
                stockTotal.textContent = parseFloat(selectedProduct.stock_qty).toFixed(0);
            } else {
                stockSummary.style.display = 'none';
            }
            
            const btnConfirm = document.getElementById('btnConfirm');
            const zeroStockWarning = document.getElementById('zeroStockWarning');
            
            if (IS_SALES && isZeroStock) {
                btnConfirm.disabled = true;
                btnConfirm.classList.add('btn-secondary');
                btnConfirm.classList.remove('btn-success');
                zeroStockWarning.style.display = 'inline';
            } else {
                btnConfirm.disabled = false;
                btnConfirm.classList.remove('btn-secondary');
                btnConfirm.classList.add('btn-success');
                zeroStockWarning.style.display = 'none';
            }
            
            calcTotal();
        }
        
        function calcTotal() {
            const q = parseFloat(document.getElementById('detailQty').value) || 0;
            const p = parseFloat(document.getElementById('detailSell').value) || 0;
            document.getElementById('detailTotal').textContent = (q * p).toFixed(2);
        }
        
        function confirmSelect() {
            if (!selectedProduct) return;
            
            if (IS_SALES && (selectedProduct.stock_qty || 0) <= 0) {
                alert('لا يمكن إضافة صنف بدون رصيد في فاتورة البيع');
                return;
            }
            
            const expSelect = document.getElementById('detailExpDate');
            const selectedOption = expSelect.selectedOptions[0];
            const result = {
                id: selectedProduct.id,
                product_id: selectedProduct.id,
                product_name: selectedProduct.product_name,
                product_code: selectedProduct.manual_code || selectedProduct.product_code,
                batch_id: expSelect.value || null,
                exp_date: selectedOption ? selectedOption.text.split(' (')[0] : null,
                quantity: parseFloat(document.getElementById('detailQty').value) || 1,
                unit_cost: parseFloat(document.getElementById('detailCost').value) || 0,
                sell_price: parseFloat(document.getElementById('detailSell').value) || 0,
                total: parseFloat(document.getElementById('detailTotal').textContent) || 0,
                has_expire: selectedProduct.has_expire,
                stock_qty: selectedProduct.stock_qty,
                current_stock: selectedProduct.stock_qty
            };
            
            if (window.opener && window.opener.onProductSelected) {
                window.opener.onProductSelected(result);
                window.close();
            } else {
                window.parent.postMessage({ type: 'product-selected', data: result }, '*');
            }
        }
        
        function prevPage() { if (currentPage > 1) { currentPage--; doSearch(); } }
        function nextPage() { if (currentPage < totalPages) { currentPage++; doSearch(); } }
        
        function showError(msg) { 
            console.error('Search error:', msg);
            document.getElementById('errorDetail').textContent = msg;
            document.getElementById('errorDetail').classList.add('show');
            document.getElementById('resultsBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle" style="font-size:24px;"></i><br>خطأ في البحث - شوف التفاصيل فوق</td></tr>'; 
        }
        
        document.getElementById('searchInput').focus();
    </script>
</body>
</html>
