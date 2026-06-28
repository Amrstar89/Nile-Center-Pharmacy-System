<!-- Product Search Popup - Shared Component -->
<!-- Usage: Set $api_base_path before including this file -->
<!-- Example: $api_base_path = '../../api'; (from modules/inventory/) -->

<?php
$api_base = isset($api_base_path) ? $api_base_path : '../../api';
?>

<!-- Button to open popup -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productSearchModal" onclick="openProductSearch('<?= $target_field ?? '' ?>')">
    <i class="bi bi-search"></i> بحث عن صنف (F2)
</button>

<!-- Product Search Modal -->
<div class="modal fade" id="productSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-search"></i> بحث عن صنف</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                <th>تواريخ الصلاحية</th>
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

<!-- Batch Selection Modal -->
<div class="modal fade" id="batchSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-check"></i> اختيار تاريخ الصلاحية</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <strong id="batchProductName"></strong>
                    <br>اختر تاريخ الصلاحية المطلوب
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>تاريخ الصلاحية</th>
                                <th>رصيد</th>
                                <th>التكلفة</th>
                                <th>سعر البيع</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="batchResultsBody">
                            <!-- Batches will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTargetField = '';
let selectedStoreId = 0;
let currentProduct = null;
let currentRowIndex = 0;

function openProductSearch(targetField, storeId = 0) {
    currentTargetField = targetField;
    selectedStoreId = storeId || document.getElementById('fromStore')?.value || document.getElementById('store_id')?.value || 0;
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

    const apiBase = '<?= $api_base ?>';

    fetch(`${apiBase}/product-search.php?code=${encodeURIComponent(code)}&name=${encodeURIComponent(name)}&barcode=${encodeURIComponent(barcode)}&scientific=${encodeURIComponent(scientific)}&company=${encodeURIComponent(company)}&category=${encodeURIComponent(category)}&type=${encodeURIComponent(type)}&store_id=${selectedStoreId}`)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('searchResultsBody');
            tbody.innerHTML = '';

            data.forEach(product => {
                // Show first batch date or "-"
                let batchInfo = '-';
                if (product.batches && product.batches.length > 0) {
                    batchInfo = product.batches.map(b => 
                        `<span class="badge bg-light text-dark border">${b.exp_date} (${b.remaining_qty})</span>`
                    ).join(' ');
                }

                const row = document.createElement('tr');
                row.style.cursor = 'pointer';
                row.innerHTML = `
                    <td><code>${product.product_code || product.manual_code || 'N/A'}</code></td>
                    <td><strong>${escapeHtml(product.product_name)}</strong></td>
                    <td>${product.company_name || '-'}</td>
                    <td>${product.scientific_name || '-'}</td>
                    <td>${product.sell_price || 0} ج</td>
                    <td>${product.cost_price || 0} ج</td>
                    <td>${product.stock_qty || 0}</td>
                    <td>${batchInfo}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="selectProductWithBatch(event, ${product.id}, '${escapeHtml(product.product_name)}', ${product.cost_price || 0}, ${product.sell_price || 0}, ${product.unit1_id || 'null'})">
                            <i class="bi bi-check"></i> اختيار
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-3 text-muted">لا توجد نتائج</td></tr>';
            }
        })
        .catch(err => console.error('Search error:', err));
}

function selectProductWithBatch(event, productId, productName, costPrice, sellPrice, unitId) {
    event.stopPropagation();

    currentProduct = {
        id: productId,
        name: productName,
        cost_price: costPrice,
        sell_price: sellPrice,
        unit_id: unitId
    };

    // If no store selected, just select product without batch
    if (!selectedStoreId || selectedStoreId == 0) {
        confirmProductSelection(null);
        return;
    }

    // Fetch batches for this product
    const apiBase = '<?= $api_base ?>';
    fetch(`${apiBase}/get-product-batches.php?product_id=${productId}&store_id=${selectedStoreId}`)
        .then(r => r.json())
        .then(batches => {
            if (batches.length === 0) {
                // No batches, select product directly
                confirmProductSelection(null);
                return;
            }

            if (batches.length === 1) {
                // Only one batch, select it automatically
                confirmProductSelection(batches[0]);
                return;
            }

            // Show batch selection modal
            document.getElementById('batchProductName').textContent = productName;
            const tbody = document.getElementById('batchResultsBody');
            tbody.innerHTML = '';

            batches.forEach(batch => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.onclick = () => confirmProductSelection(batch);
                tr.innerHTML = `
                    <td><span class="badge bg-primary">${batch.exp_date}</span></td>
                    <td>${batch.remaining_qty}</td>
                    <td>${batch.unit_cost} ج</td>
                    <td>${batch.sell_price} ج</td>
                    <td><button class="btn btn-sm btn-success"><i class="bi bi-check"></i></button></td>
                `;
                tbody.appendChild(tr);
            });

            // Close search modal and open batch modal
            const searchModal = bootstrap.Modal.getInstance(document.getElementById('productSearchModal'));
            searchModal.hide();

            setTimeout(() => {
                const batchModal = new bootstrap.Modal(document.getElementById('batchSelectModal'));
                batchModal.show();
            }, 300);
        })
        .catch(err => console.error('Batch fetch error:', err));
}

function confirmProductSelection(batch) {
    // Close batch modal if open
    const batchModalEl = document.getElementById('batchSelectModal');
    const batchModal = bootstrap.Modal.getInstance(batchModalEl);
    if (batchModal) batchModal.hide();

    // Dispatch event with selected product and batch data
    const detail = {
        ...currentProduct,
        batch: batch
    };

    const event = new CustomEvent('productSelected', { detail: detail });
    document.dispatchEvent(event);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/'/g, "\'");
}

// Load filter options on page load
document.addEventListener('DOMContentLoaded', function() {
    const apiBase = '<?= $api_base ?>';

    // Load companies
    fetch(`${apiBase}/get-companies.php`)
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('search_company');
            data.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.company_name_ar}</option>`;
            });
        });

    // Load categories
    fetch(`${apiBase}/get-categories.php`)
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('search_category');
            data.forEach(c => {
                select.innerHTML += `<option value="${c.id}">${c.category_name_ar}</option>`;
            });
        });

    // Load product types
    fetch(`${apiBase}/get-product-types.php`)
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('search_type');
            data.forEach(t => {
                select.innerHTML += `<option value="${t.id}">${t.type_name_ar}</option>`;
            });
        });
});
</script>
