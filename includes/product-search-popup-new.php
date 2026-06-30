<?php
require_once __DIR__ . '/../core/config.php';

if (basename($_SERVER['PHP_SELF']) === 'product-search-popup-new.php') {
    $store_id = intval($_GET['store_id'] ?? 0);
    $callback = htmlspecialchars($_GET['callback'] ?? 'onProductSelected');
    
    if ($store_id <= 0) die('معرف المخزن مطلوب');
    
    $db = getDB();
    $store = $db->prepare("SELECT store_name FROM stores WHERE id = ?");
    $store->execute([$store_id]);
    $store_name = $store->fetch()['store_name'] ?? 'المخزن';
    
    $categories = $db->query("SELECT id, category_name FROM product_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll();
    $companies = $db->query("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بحث عن صنف - <?= htmlspecialchars($store_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; min-height: 100vh; }
        .search-header { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 15px 25px; text-align: center; }
        .search-header h3 { margin: 0; font-size: 20px; }
        .store-badge { background: rgba(255,255,255,0.2); padding: 3px 12px; border-radius: 15px; font-size: 12px; margin-top: 5px; display: inline-block; }
        .filter-panel { background: white; border-radius: 0 0 15px 15px; padding: 15px 20px; margin: 0 15px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .search-type-wrap { display: flex; gap: 5px; }
        .search-type-btn { flex: 1; padding: 8px 5px; border: 2px solid #e9ecef; background: white; border-radius: 8px; cursor: pointer; text-align: center; font-size: 12px; transition: all 0.2s; font-weight: 600; }
        .search-type-btn:hover { border-color: var(--primary); }
        .search-type-btn.active { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary); }
        .search-type-btn i { display: block; font-size: 16px; margin-bottom: 3px; }
        .search-main-input { font-size: 16px; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; transition: all 0.3s; }
        .search-main-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
        .advanced-filters { background: #f8f9fa; border-radius: 10px; padding: 12px; margin-top: 10px; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { font-size: 11px; font-weight: 600; color: #666; margin-bottom: 3px; }
        .results-container { background: white; border-radius: 12px; margin: 0 15px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; }
        .results-header { background: linear-gradient(90deg, #f8f9fa 0%, #e9ecef 100%); padding: 8px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary); }
        .results-table { width: 100%; margin: 0; }
        .results-table thead th { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-size: 12px; font-weight: 600; padding: 10px 12px; border: none; position: sticky; top: 0; z-index: 10; }
        .results-table tbody td { font-size: 12px; padding: 8px 12px; vertical-align: middle; }
        .results-table tbody tr { cursor: pointer; transition: all 0.15s; }
        .results-table tbody tr:hover { background: #e8f0fe !important; }
        .results-table tbody tr.selected { background: linear-gradient(90deg, #d4edda 0%, #c3e6cb 100%) !important; border-right: 3px solid var(--success); }
        .results-table tbody tr.no-stock { opacity: 0.5; }
        .product-code { font-family: monospace; font-size: 11px; color: #888; }
        .product-name { font-weight: 600; color: #333; }
        .sell-price { font-weight: 700; color: var(--primary); }
        .stock-qty { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .stock-qty.has-stock { background: #d4edda; color: #155724; }
        .stock-qty.low-stock { background: #fff3cd; color: #856404; }
        .stock-qty.no-stock { background: #f8d7da; color: #721c24; }
        .pagination-bar { padding: 8px 15px; background: #f8f9fa; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .detail-panel { background: white; border-radius: 12px; margin: 0 15px 10px; padding: 15px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-top: 3px solid var(--success); }
        .detail-panel.hidden { display: none; }
        .detail-panel .product-title { font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 10px; }
        .product-info { display: flex; gap: 20px; margin-bottom: 12px; flex-wrap: wrap; }
        .info-item { background: #f8f9fa; padding: 8px 15px; border-radius: 8px; text-align: center; }
        .info-item .label { font-size: 10px; color: #888; }
        .info-item .value { font-size: 14px; font-weight: 700; color: #333; }
        .batch-section { margin-top: 10px; }
        .batch-table { width: 100%; font-size: 11px; }
        .batch-table th { background: #e9ecef; padding: 6px 10px; font-size: 11px; }
        .batch-table td { padding: 6px 10px; }
        .exp-badge { padding: 2px 8px; border-radius: 6px; font-size: 10px; }
        .exp-badge.safe { background: #d4edda; color: #155724; }
        .exp-badge.warning { background: #fff3cd; color: #856404; }
        .exp-badge.danger { background: #f8d7da; color: #721c24; }
        .qty-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 12px 15px; margin-top: 10px; display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .qty-section .form-group { margin: 0; }
        .qty-section .form-group label { font-size: 11px; font-weight: 600; color: #555; }
        .qty-section input, .qty-section select { font-size: 14px; font-weight: 600; text-align: center; }
        .total-display { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 8px 20px; border-radius: 8px; font-size: 16px; font-weight: 700; min-width: 100px; text-align: center; }
        .popup-footer { background: white; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e9ecef; position: sticky; bottom: 0; }
        .item-count-badge { background: #f8f9fa; padding: 8px 15px; border-radius: 8px; font-size: 13px; }
        .scrollable-table { max-height: 280px; overflow-y: auto; position: relative; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .detail-panel { animation: fadeIn 0.3s ease; }
    </style>
</head>
<body>
    <div class="search-header">
        <h3><i class="bi bi-search"></i> بحث عن صنف</h3>
        <span class="store-badge"><i class="bi bi-building"></i> <?= htmlspecialchars($store_name) ?></span>
    </div>
    
    <div class="filter-panel">
        <div class="search-type-wrap mb-3">
            <div class="search-type-btn active" data-type="name" onclick="setSearchType('name')"><i class="bi bi-fonts"></i> اسم الصنف</div>
            <div class="search-type-btn" data-type="barcode" onclick="setSearchType('barcode')"><i class="bi bi-upc-scan"></i> الباركود</div>
            <div class="search-type-btn" data-type="code" onclick="setSearchType('code')"><i class="bi bi-hash"></i> الكود</div>
            <div class="search-type-btn" data-type="scientific" onclick="setSearchType('scientific')"><i class="bi bi-flask"></i> المادة الفعالة</div>
            <div class="search-type-btn" data-type="company" onclick="setSearchType('company')"><i class="bi bi-building"></i> الشركة</div>
        </div>
        <div class="input-group mb-2">
            <input type="text" id="searchInput" class="form-control search-main-input" placeholder="اكتب هنا للبحث..." onkeyup="handleSearchKey(event)" autocomplete="off">
            <button class="btn btn-primary" type="button" onclick="doSearch()"><i class="bi bi-search"></i> بحث</button>
        </div>
        <div class="text-center">
            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleAdvanced()"><i class="bi bi-sliders"></i> فلاتر متقدمة</button>
        </div>
        <div class="advanced-filters d-none" id="advancedFilters">
            <div class="filter-row">
                <div class="filter-group">
                    <label>التصنيف</label>
                    <select id="filterCategory" class="form-select" onchange="doSearch()">
                        <option value="">كل التصنيفات</option>
                        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>الشركة</label>
                    <select id="filterCompany" class="form-select" onchange="doSearch()">
                        <option value="">كل الشركات</option>
                        <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group" style="flex: 0.5;"><label>السعر من</label><input type="number" id="priceFrom" class="form-control" placeholder="0" onchange="doSearch()"></div>
                <div class="filter-group" style="flex: 0.5;"><label>السعر إلى</label><input type="number" id="priceTo" class="form-control" placeholder="∞" onchange="doSearch()"></div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="showNoStock" onchange="doSearch()"><label class="form-check-label" for="showNoStock" style="font-size: 11px;">بدون رصيد</label></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="results-container">
        <div class="results-header"><h6><i class="bi bi-list-ul"></i> نتائج البحث</h6><span class="badge bg-primary" id="resultCount">0 صنف</span></div>
        <div class="scrollable-table">
            <table class="table table-hover results-table mb-0">
                <thead><tr><th style="width:60px;">كود</th><th>اسم الصنف</th><th style="width:80px;">سعر البيع</th><th style="width:100px;">المادة الفعالة</th><th style="width:80px;">الرصيد</th></tr></thead>
                <tbody id="resultsBody"><tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-search" style="font-size:24px;"></i><br>ابدأ البحث بكتابة اسم الصنف أو الباركود</td></tr></tbody>
            </table>
        </div>
        <div class="pagination-bar">
            <button class="btn btn-sm btn-outline-secondary" onclick="prevPage()" disabled id="btnPrev"><i class="bi bi-chevron-right"></i></button>
            <span class="text-muted" style="font-size:12px;" id="pageInfo">صفحة 1 من 1</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="nextPage()" disabled id="btnNext"><i class="bi bi-chevron-left"></i></button>
        </div>
    </div>
    
    <div class="detail-panel hidden" id="detailPanel">
        <div class="product-title" id="detailProductName"><i class="bi bi-check-circle-fill text-success"></i> <span></span></div>
        <div class="product-info">
            <div class="info-item"><div class="label">كود الصنف</div><div class="value" id="detailProductCode">-</div></div>
            <div class="info-item"><div class="label">الباركود</div><div class="value" id="detailBarcode">-</div></div>
            <div class="info-item"><div class="label">الشركة</div><div class="value" id="detailCompany">-</div></div>
            <div class="info-item"><div class="label">التصنيف</div><div class="value" id="detailCategory">-</div></div>
            <div class="info-item"><div class="label">الرصيد الكلي</div><div class="value" id="detailTotalStock" style="color:var(--success);">-</div></div>
        </div>
        <div class="batch-section" id="batchSection">
            <h6 class="mb-2"><i class="bi bi-calendar-check"></i> الدفعات المتاحة</h6>
            <div class="table-responsive"><table class="table table-sm batch-table"><thead><tr><th>تاريخ الصلاحية</th><th>الكمية المتاحة</th><th>تكلفة الوحدة</th></tr></thead><tbody id="batchBody"></tbody></table></div>
        </div>
        <div class="qty-section">
            <div class="form-group"><label>الوحدة</label><input type="text" id="detailUnit" class="form-control" value="علبة" readonly style="width:80px;"></div>
            <div class="form-group"><label>الكمية</label><input type="number" id="detailQty" class="form-control" value="1" min="0.001" step="0.001" style="width:100px;" onchange="calcDetailTotal()"></div>
            <div class="form-group"><label>تاريخ الصلاحية</label><select id="detailExpDate" class="form-select" style="width:150px;" onchange="onExpDateChange()"><option value="">أقرب تاريخ</option></select></div>
            <div class="form-group"><label>سعر البيع</label><input type="number" id="detailSellPrice" class="form-control" step="0.01" min="0" style="width:100px;" onchange="calcDetailTotal()"></div>
            <div class="form-group"><label>تكلفة الوحدة</label><input type="number" id="detailCost" class="form-control" step="0.01" min="0" style="width:100px;" readonly></div>
            <div class="total-display" id="detailTotal">0.00</div>
        </div>
    </div>
    
    <div class="popup-footer">
        <div class="item-count-badge"><i class="bi bi-boxes"></i> عدد الأصناف: <span class="count" id="itemCount">0</span></div>
        <div>
            <button class="btn btn-secondary" onclick="window.close()"><i class="bi bi-x-lg"></i> إلغاء</button>
            <button class="btn btn-success btn-lg" onclick="confirmSelection()" id="btnConfirm" disabled><i class="bi bi-check-lg"></i> موافق</button>
        </div>
    </div>

    <script>
        const STORE_ID = <?= $store_id ?>;
        const CALLBACK = '<?= $callback ?>';
        let searchType = 'name', currentPage = 1, totalPages = 1, selectedProduct = null, allResults = [];
        
        window.addEventListener('load', function() {
            const prefill = localStorage.getItem('product_search_prefill');
            if (prefill) {
                document.getElementById('searchInput').value = prefill;
                localStorage.removeItem('product_search_prefill');
                setSearchType(/^\d+$/.test(prefill) ? 'barcode' : 'name');
                doSearch();
            }
            document.getElementById('searchInput').focus();
        });
        
        function setSearchType(type) {
            searchType = type;
            document.querySelectorAll('.search-type-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`.search-type-btn[data-type="${type}"]`).classList.add('active');
            const ph = { name: 'اكتب اسم الصنف...', barcode: 'ادخل الباركود...', code: 'ادخل كود الصنف...', scientific: 'اكتب المادة الفعالة...', company: 'ابحث باسم الشركة...' };
            document.getElementById('searchInput').placeholder = ph[type] || 'اكتب هنا...';
            document.getElementById('searchInput').focus();
        }
        function toggleAdvanced() { document.getElementById('advancedFilters').classList.toggle('d-none'); }
        function handleSearchKey(e) { if (e.key === 'Enter') doSearch(); }
        
        async function doSearch() {
            const query = document.getElementById('searchInput').value.trim();
            const category = document.getElementById('filterCategory').value;
            const company = document.getElementById('filterCompany').value;
            const priceFrom = document.getElementById('priceFrom').value;
            const priceTo = document.getElementById('priceTo').value;
            const showNoStock = document.getElementById('showNoStock').checked;
            
            if (!query && !category && !company && !priceFrom && !priceTo) {
                document.getElementById('resultsBody').innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-search" style="font-size:24px;"></i><br>ابدأ البحث بكتابة اسم الصنف أو الباركود</td></tr>`;
                document.getElementById('resultCount').textContent = '0 صنف';
                return;
            }
            
            let url = `../api/product-search-advanced.php?store_id=${STORE_ID}&type=${searchType}`;
            if (query) url += `&q=${encodeURIComponent(query)}`;
            if (category) url += `&category=${category}`;
            if (company) url += `&company=${company}`;
            if (priceFrom) url += `&price_from=${priceFrom}`;
            if (priceTo) url += `&price_to=${priceTo}`;
            if (showNoStock) url += `&show_no_stock=1`;
            url += `&page=${currentPage}&limit=50`;
            
            try {
                const res = await fetch(url);
                const data = await res.json();
                if (data.error) { showError(data.error); return; }
                allResults = data.products || [];
                totalPages = data.total_pages || 1;
                currentPage = data.page || 1;
                renderResults(allResults);
                updatePagination();
                document.getElementById('resultCount').textContent = `${data.total || 0} صنف`;
            } catch (err) { showError('حدث خطأ في البحث'); }
        }
        
        function renderResults(products) {
            const tbody = document.getElementById('resultsBody');
            if (products.length === 0) { tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-inbox" style="font-size:24px;"></i><br>لا توجد نتائج</td></tr>`; return; }
            tbody.innerHTML = products.map((p, i) => {
                const stockClass = p.stock_qty > 10 ? 'has-stock' : (p.stock_qty > 0 ? 'low-stock' : 'no-stock');
                return `<tr class="${p.stock_qty <= 0 ? 'no-stock' : ''}" onclick="selectProduct(${i})" id="row_${i}"><td><span class="product-code">${p.manual_code || p.product_code || '-'}</span></td><td><span class="product-name">${p.product_name}</span>${p.barcode ? `<br><small class="text-muted">${p.barcode}</small>` : ''}</td><td class="sell-price">${parseFloat(p.sell_price).toFixed(2)}</td><td><small class="text-muted">${p.scientific_name || '-'}</small></td><td><span class="stock-qty ${stockClass}">${parseFloat(p.stock_qty).toFixed(0)}</span></td></tr>`;
            }).join('');
        }
        
        function selectProduct(index) {
            document.querySelectorAll('.results-table tbody tr').forEach(r => r.classList.remove('selected'));
            document.getElementById(`row_${index}`)?.classList.add('selected');
            selectedProduct = allResults[index];
            const panel = document.getElementById('detailPanel');
            panel.classList.remove('hidden');
            document.querySelector('#detailProductName span').textContent = selectedProduct.product_name;
            document.getElementById('detailProductCode').textContent = selectedProduct.manual_code || selectedProduct.product_code || '-';
            document.getElementById('detailBarcode').textContent = selectedProduct.barcode || '-';
            document.getElementById('detailCompany').textContent = selectedProduct.company_name || '-';
            document.getElementById('detailCategory').textContent = selectedProduct.category_name || '-';
            document.getElementById('detailTotalStock').textContent = parseFloat(selectedProduct.stock_qty).toFixed(2);
            document.getElementById('detailSellPrice').value = parseFloat(selectedProduct.sell_price).toFixed(2);
            document.getElementById('detailCost').value = parseFloat(selectedProduct.unit_cost || selectedProduct.cost_price).toFixed(2);
            document.getElementById('detailUnit').value = selectedProduct.unit_name_ar || 'علبة';
            
            const expSelect = document.getElementById('detailExpDate');
            expSelect.innerHTML = '<option value="">أقرب تاريخ</option>';
            if (selectedProduct.batches && selectedProduct.batches.length > 0) {
                document.getElementById('batchSection').style.display = 'block';
                document.getElementById('batchBody').innerHTML = selectedProduct.batches.map(b => {
                    const daysLeft = Math.ceil((new Date(b.exp_date) - new Date()) / (1000 * 60 * 60 * 24));
                    const expClass = daysLeft < 90 ? 'danger' : (daysLeft < 180 ? 'warning' : 'safe');
                    return `<tr><td><span class="exp-badge ${expClass}">${b.exp_date}</span></td><td>${parseFloat(b.remaining_qty).toFixed(2)}</td><td>${parseFloat(b.unit_cost).toFixed(2)}</td></tr>`;
                }).join('');
                selectedProduct.batches.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = `${b.exp_date} (${parseFloat(b.remaining_qty).toFixed(0)})`;
                    opt.dataset.qty = b.remaining_qty;
                    expSelect.appendChild(opt);
                });
            } else { document.getElementById('batchSection').style.display = 'none'; }
            
            calcDetailTotal();
            document.getElementById('btnConfirm').disabled = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function onExpDateChange() {}
        function calcDetailTotal() {
            const qty = parseFloat(document.getElementById('detailQty').value) || 0;
            const price = parseFloat(document.getElementById('detailSellPrice').value) || 0;
            document.getElementById('detailTotal').textContent = (qty * price).toFixed(2);
        }
        function updatePagination() {
            document.getElementById('pageInfo').textContent = `صفحة ${currentPage} من ${totalPages}`;
            document.getElementById('btnPrev').disabled = currentPage <= 1;
            document.getElementById('btnNext').disabled = currentPage >= totalPages;
        }
        function prevPage() { if (currentPage > 1) { currentPage--; doSearch(); } }
        function nextPage() { if (currentPage < totalPages) { currentPage++; doSearch(); } }
        function showError(msg) { document.getElementById('resultsBody').innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle" style="font-size:24px;"></i><br>${msg}</td></tr>`; }
        
        function confirmSelection() {
            if (!selectedProduct) return;
            const result = {
                id: selectedProduct.id, product_id: selectedProduct.id, product_name: selectedProduct.product_name,
                product_code: selectedProduct.manual_code || selectedProduct.product_code, barcode: selectedProduct.barcode,
                batch_id: document.getElementById('detailExpDate').value || null,
                exp_date: document.getElementById('detailExpDate').selectedOptions[0]?.text?.split(' ')[0] || null,
                quantity: parseFloat(document.getElementById('detailQty').value) || 1,
                unit_cost: parseFloat(document.getElementById('detailCost').value) || 0,
                sell_price: parseFloat(document.getElementById('detailSellPrice').value) || 0,
                total: parseFloat(document.getElementById('detailTotal').textContent) || 0,
                has_expire: selectedProduct.has_expire || false, unit_name_ar: selectedProduct.unit_name_ar || 'علبة',
                stock_qty: selectedProduct.stock_qty || 0, cost_price: selectedProduct.cost_price || selectedProduct.unit_cost || 0
            };
            if (window.opener && window.opener[CALLBACK]) { window.opener[CALLBACK](result); window.close(); }
            else { window.parent.postMessage({ type: 'product-selected', callback: CALLBACK, data: result }, '*'); }
        }
    </script>
</body>
</html>
<?php } ?>
