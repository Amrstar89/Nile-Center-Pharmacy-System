/**
 * ProductSearch - وحدة البحث المتقدم عن الأصناف
 * 
 * الاستخدام:
 * ProductSearch.open({
 *     storeId: 1,
 *     mode: 'sales',        // 'sales' | 'purchase'
 *     onSelect: function(product) { console.log(product); }
 * });
 * 
 * Modes:
 *   - 'sales':    Shows only stocked products by default, can't add zero-stock items
 *   - 'purchase': Shows all products regardless of stock
 */
const ProductSearch = (function() {
    'use strict';
    
    let popupWindow = null;
    let onSelectCallback = null;
    
    // Global callback - receives selected product from popup
    window.onProductSelected = function(product) {
        if (onSelectCallback) {
            onSelectCallback(product);
        }
        if (popupWindow && !popupWindow.closed) {
            popupWindow.close();
        }
        popupWindow = null;
    };
    
    // postMessage fallback for iframe mode
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'product-selected') {
            if (onSelectCallback) {
                onSelectCallback(e.data.data);
            }
            popupWindow = null;
        }
    });
    
    function getBaseUrl() {
        const path = window.location.pathname;
        const modulesIdx = path.indexOf('/modules/');
        if (modulesIdx > 0) {
            return path.substring(0, modulesIdx);
        }
        return '';
    }
    
    return {
        /**
         * فتح شاشة البحث
         * @param {Object} options
         * @param {number} options.storeId - معرف المخزن (مطلوب)
         * @param {string} options.mode - 'sales' أو 'purchase' (افتراضي: 'sales')
         * @param {function} options.onSelect - callback عند اختيار صنف (مطلوب)
         */
        open: function(options) {
            if (!options.storeId) { console.error('ProductSearch: storeId required'); return; }
            if (!options.onSelect) { console.error('ProductSearch: onSelect required'); return; }
            
            onSelectCallback = options.onSelect;
            const baseUrl = getBaseUrl();
            const mode = options.mode || 'sales';
            
            if (popupWindow && !popupWindow.closed) {
                popupWindow.close();
            }
            
            const width = 950;
            const height = 750;
            const left = (window.screen.width - width) / 2;
            const top = (window.screen.height - height) / 2;
            
            const url = baseUrl + '/includes/product-search-popup-new.php?store_id=' + options.storeId + '&mode=' + mode + '&callback=onProductSelected';
            
            console.log('Opening popup:', url);
            
            popupWindow = window.open(
                url, 'productSearchPopup',
                'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left + ',resizable=yes,scrollbars=yes'
            );
            
            if (popupWindow) {
                popupWindow.focus();
            } else {
                this._openIframe(url);
            }
        },
        
        /**
         * ربط input field (F2 key + click)
         * @param {number} storeId - معرف المخزن
         * @param {HTMLInputElement} inputElement - عنصر الإدخال
         * @param {string} mode - 'sales' أو 'purchase'
         */
        pick: function(storeId, inputElement, mode) {
            var triggerSearch = function(e) {
                if (e) e.preventDefault();
                ProductSearch.open({
                    storeId: storeId,
                    mode: mode || 'sales',
                    onSelect: function(product) {
                        inputElement.value = product.product_name;
                        inputElement.dataset.productId = product.product_id || product.id;
                        inputElement.dataset.productCode = product.product_code;
                        inputElement.dataset.unitCost = product.unit_cost;
                        inputElement.dataset.sellPrice = product.sell_price;
                        inputElement.dispatchEvent(new CustomEvent('productSelected', { detail: product }));
                    }
                });
            };
            
            inputElement.addEventListener('keydown', function(e) {
                if (e.key === 'F2') triggerSearch(e);
            });
            
            var wrap = inputElement.closest('.barcode-wrap');
            if (wrap) {
                var btn = wrap.querySelector('.btn-f2');
                if (btn) btn.addEventListener('click', triggerSearch);
            }
        },
        
        /**
         * بحث سريع بدون popup
         */
        quickSearch: async function(storeId, query, type, mode) {
            var baseUrl = getBaseUrl();
            var url = baseUrl + '/includes/product-search-api.php?store_id=' + storeId + '&type=' + (type || 'name') + '&q=' + encodeURIComponent(query) + '&limit=20';
            if (mode === 'purchase') {
                url += '&show_no_stock=1';
            }
            try {
                var res = await fetch(url);
                var data = await res.json();
                return data.products || [];
            } catch (e) {
                console.error('quickSearch error:', e);
                return [];
            }
        },
        
        // Internal: iframe fallback when popup is blocked
        _openIframe: function(url) {
            var existing = document.getElementById('productSearchModal');
            if (existing) existing.remove();
            
            var modal = document.createElement('div');
            modal.id = 'productSearchModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
            modal.innerHTML = '<div style="background:white;border-radius:12px;width:95%;height:95%;overflow:hidden;position:relative;">' +
                '<button onclick="document.getElementById(\'productSearchModal\').remove()" style="position:absolute;top:10px;left:10px;z-index:10;background:#dc3545;color:white;border:none;padding:5px 15px;border-radius:6px;cursor:pointer;"><i class="bi bi-x-lg"></i> إغلاق</button>' +
                '<iframe src="' + url + '" style="width:100%;height:100%;border:none;"></iframe>' +
            '</div>';
            document.body.appendChild(modal);
        }
    };
})();
