/**
 * Product Search Modal
 * F2 search functionality for inventory items
 */
const ProductSearch = (function() {
    let callback = null;
    let currentStoreId = 0;
    let allProducts = [];

    function open(options) {
        callback = options.onSelect || null;
        currentStoreId = options.storeId || 0;

        if (!currentStoreId) {
            alert('اختر المخزن أولاً');
            return;
        }

        removeExisting();
        createModal();
        loadProducts();
    }

    function removeExisting() {
        const existing = document.getElementById('productSearchModal');
        if (existing) existing.remove();
    }

    function createModal() {
        const div = document.createElement('div');
        div.id = 'productSearchModal';
        div.className = 'modal fade show';
        div.style.cssText = 'display:block;z-index:9999;background:rgba(0,0,0,0.5);position:fixed;top:0;left:0;right:0;bottom:0;overflow-y:auto;';
        div.innerHTML =
            '<div class="modal-dialog modal-lg" style="margin:50px auto;">' +
            '<div class="modal-content">' +
            '<div class="modal-header bg-primary text-white">' +
            '<h5 class="modal-title"><i class="bi bi-search"></i> بحث الأصناف</h5>' +
            '<button type="button" class="btn-close btn-close-white" onclick="ProductSearch.close()"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="row g-2 mb-3">' +
            '<div class="col-md-4"><input type="text" id="ps_searchName" class="form-control form-control-sm" placeholder="اسم الصنف..." oninput="ProductSearch.filter()"></div>' +
            '<div class="col-md-3"><input type="text" id="ps_searchCode" class="form-control form-control-sm" placeholder="كود الصنف..." oninput="ProductSearch.filter()"></div>' +
            '<div class="col-md-3"><input type="text" id="ps_searchBarcode" class="form-control form-control-sm" placeholder="باركود..." oninput="ProductSearch.filter()"></div>' +
            '<div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100" onclick="ProductSearch.clearFilter()">مسح</button></div>' +
            '</div>' +
            '<div style="max-height:400px;overflow-y:auto;">' +
            '<table class="table table-hover table-sm">' +
            '<thead class="table-dark"><tr>' +
            '<th>كود</th><th>باركود</th><th style="min-width:200px">اسم الصنف</th>' +
            '<th>الوحدة</th><th>التكلفة</th><th>سعر البيع</th><th>الرصيد</th><th></th>' +
            '</tr></thead>' +
            '<tbody id="ps_results"><tr><td colspan="8" class="text-center text-muted py-4">جاري التحميل...</td></tr></tbody>' +
            '</table></div>' +
            '</div>' +
            '<div class="modal-footer"><small class="text-muted" id="ps_count">0 صنف</small></div>' +
            '</div></div>';

        document.body.appendChild(div);
        div.addEventListener('click', function(e) {
            if (e.target === this) close();
        });
        document.addEventListener('keydown', handleEsc);

        // Focus on search
        setTimeout(function() {
            const el = document.getElementById('ps_searchName');
            if (el) el.focus();
        }, 100);
    }

    function handleEsc(e) {
        if (e.key === 'Escape') close();
    }

    function close() {
        document.removeEventListener('keydown', handleEsc);
        removeExisting();
    }

    function loadProducts() {
        // Try multiple possible paths for the AJAX endpoint
        const paths = [
            '../../inventory/ajax_get_products.php?store_id=' + currentStoreId,
            '../inventory/ajax_get_products.php?store_id=' + currentStoreId,
            'ajax_get_products.php?store_id=' + currentStoreId,
            '../../sales/ajax_get_products.php?store_id=' + currentStoreId
        ];

        tryPath(0);

        function tryPath(index) {
            if (index >= paths.length) {
                document.getElementById('ps_results').innerHTML =
                    '<tr><td colspan="8" class="text-center text-muted py-4">' +
                    '<i class="bi bi-exclamation-triangle" style="font-size:32px"></i><br>' +
                    'لا توجد أصناف متاحة في هذا المخزن<br>' +
                    '<small>تأكد من وجود أصناف في المخزن المختار</small>' +
                    '</td></tr>';
                return;
            }
            fetch(paths[index])
                .then(function(r) { if (!r.ok) throw new Error(); return r.json(); })
                .then(function(data) {
                    allProducts = data || [];
                    renderResults(allProducts);
                })
                .catch(function() { tryPath(index + 1); });
        }
    }

    function renderResults(products) {
        const tbody = document.getElementById('ps_results');
        const countEl = document.getElementById('ps_count');

        if (!products || products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">لا توجد أصناف</td></tr>';
            if (countEl) countEl.textContent = '0 صنف';
            return;
        }

        let html = '';
        products.forEach(function(p) {
            const stock = parseFloat(p.current_stock || p.quantity || 0);
            const stockClass = stock <= 0 ? 'text-danger' : (stock < 10 ? 'text-warning' : 'text-success');
            html += '<tr style="cursor:pointer" onclick="ProductSearch.select(' + p.id + ')">' +
                '<td>' + (p.product_code || '-') + '</td>' +
                '<td>' + (p.barcode || '-') + '</td>' +
                '<td><strong>' + (p.product_name || p.name || '') + '</strong></td>' +
                '<td>' + (p.unit_name || p.unit || 'علبة') + '</td>' +
                '<td>' + parseFloat(p.unit_cost || p.cost || 0).toFixed(2) + '</td>' +
                '<td>' + parseFloat(p.sell_price || p.price || 0).toFixed(2) + '</td>' +
                '<td class="' + stockClass + ' fw-bold">' + stock.toFixed(2) + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-primary" onclick="event.stopPropagation();ProductSearch.select(' + p.id + ')"><i class="bi bi-plus-lg"></i></button></td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
        if (countEl) countEl.textContent = products.length + ' صنف';
    }

    function filter() {
        const name = document.getElementById('ps_searchName').value.trim().toLowerCase();
        const code = document.getElementById('ps_searchCode').value.trim().toLowerCase();
        const barcode = document.getElementById('ps_searchBarcode').value.trim().toLowerCase();

        const filtered = allProducts.filter(function(p) {
            const pName = (p.product_name || p.name || '').toLowerCase();
            const pCode = (p.product_code || '').toLowerCase();
            const pBarcode = (p.barcode || '').toLowerCase();
            return (!name || pName.includes(name)) &&
                   (!code || pCode.includes(code)) &&
                   (!barcode || pBarcode.includes(barcode));
        });
        renderResults(filtered);
    }

    function clearFilter() {
        document.getElementById('ps_searchName').value = '';
        document.getElementById('ps_searchCode').value = '';
        document.getElementById('ps_searchBarcode').value = '';
        renderResults(allProducts);
    }

    function select(productId) {
        const product = allProducts.find(function(p) { return p.id == productId; });
        if (!product) return;

        var normalized = {
            id: product.id,
            product_id: product.product_id || product.id,
            product_name: product.product_name || product.name || '',
            product_code: product.product_code || '',
            barcode: product.barcode || '',
            unit_id: product.unit_id || product.unit || '',
            unit_name: product.unit_name || product.unit || 'علبة',
            unit_cost: parseFloat(product.unit_cost || product.cost || 0),
            sell_price: parseFloat(product.sell_price || product.price || 0),
            current_stock: parseFloat(product.current_stock || product.quantity || 0),
            company_name: product.company_name || '',
            location: product.location || '',
            units: product.units || []
        };

        if (callback) {
            callback(normalized);
        }
        close();
    }

    return {
        open: open,
        close: close,
        filter: filter,
        clearFilter: clearFilter,
        select: select
    };
})();
