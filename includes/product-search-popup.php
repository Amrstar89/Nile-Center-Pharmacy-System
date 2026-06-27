<!-- Product Search Popup - Shared Component -->
<!-- Include this in any page that needs product search -->

<!-- Button to open popup -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productSearchModal" onclick="openProductSearch('<?= $target_field ?? '' ?>')">
    <i class="bi bi-search"></i> بحث عن صنف
</button>

<!-- Product Search Modal -->
<div class="modal fade" id="productSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-search"></i> بحث عن صنف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Search Filters -->
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" id="search_code" class="form-control" placeholder="الكود..." onkeyup="searchProducts()">
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="search_name" class="form-control" placeholder="الاسم..." onkeyup="searchProducts()">
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="search_barcode" class="form-control" placeholder="الباركود..." onkeyup="searchProducts()">
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="search_scientific" class="form-control" placeholder="المادة الفعالة..." onkeyup="searchProducts()">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select id="search_company" class="form-select" onchange="searchProducts()">
                            <option value="">-- كل الشركات --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="search_category" class="form-select" onchange="searchProducts()">
                            <option value="">-- كل المجموعات --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="search_type" class="form-select" onchange="searchProducts()">
                            <option value="">-- كل الأنواع --</option>
                        </select>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-sm" id="searchResultsTable">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>كود</th>
                                <th>اسم الصنف</th>
                                <th>الشركة</th>
                                <th>المادة الفعالة</th>
                                <th>سعر البيع</th>
                                <th>التكلفة</th>
                                <th>المخزون</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="searchResultsBody">
                            <!-- Results will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTargetField = '';
let selectedStoreId = 0;

function openProductSearch(targetField, storeId = 0) {
    currentTargetField = targetField;
    selectedStoreId = storeId;
    searchProducts();
}

function searchProducts() {
    const code = document.getElementById('search_code').value;
    const name = document.getElementById('search_name').value;
    const barcode = document.getElementById('search_barcode').value;
    const scientific = document.getElementById('search_scientific').value;
    const company = document.getElementById('search_company').value;
    const category = document.getElementById('search_category').value;
    const type = document.getElementById('search_type').value;

    fetch(`../../api/product-search.php?code=${encodeURIComponent(code)}&name=${encodeURIComponent(name)}&barcode=${encodeURIComponent(barcode)}&scientific=${encodeURIComponent(scientific)}&company=${encodeURIComponent(company)}&category=${encodeURIComponent(category)}&type=${encodeURIComponent(type)}&store_id=${selectedStoreId}`)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('searchResultsBody');
            tbody.innerHTML = '';

            data.forEach(product => {
                const row = document.createElement('tr');
                row.style.cursor = 'pointer';
                row.onclick = () => selectProduct(product);
                row.innerHTML = `
                    <td><code>${product.product_code || product.manual_code || 'N/A'}</code></td>
                    <td>${product.product_name}</td>
                    <td>${product.company_name || '-'}</td>
                    <td>${product.scientific_name || '-'}</td>
                    <td>${product.sell_price || 0} ج</td>
                    <td>${product.cost_price || 0} ج</td>
                    <td>${product.stock_qty || 0}</td>
                    <td><button class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button></td>
                `;
                tbody.appendChild(row);
            });

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3">لا توجد نتائج</td></tr>';
            }
        })
        .catch(err => console.error('Search error:', err));
}

function selectProduct(product) {
    // Dispatch event with selected product data
    const event = new CustomEvent('productSelected', { detail: product });
    document.dispatchEvent(event);

    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('productSearchModal'));
    modal.hide();
}

// Load filter options on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load companies
    fetch('../../api/get-companies.php')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('search_company');
            data.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.company_name_ar}</option>`;
            });
        });

    // Load categories
    fetch('../../api/get-categories.php')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('search_category');
            data.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.category_name_ar}</option>`;
            });
        });

    // Load product types
    fetch('../../api/get-product-types.php')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('search_type');
            data.forEach(t => {
                select.innerHTML += `<option value="${t.id}">${t.type_name_ar}</option>`;
            });
        });
});
</script>
