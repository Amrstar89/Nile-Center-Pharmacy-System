/**
 * ============================================
 * Product Search Popup - JavaScript Module
 * ============================================
 * 
 * Usage in any page:
 * 
 * 1. Include this file:
 *    <script src="../../js/product-search.js"></script>
 * 
 * 2. Open search popup:
 *    ProductSearch.open({
 *        storeId: 1,
 *        onSelect: function(product) {
 *            // product = { product_id, product_name, barcode, batch_id, 
 *            //             exp_date, quantity, unit_cost, sell_price, total }
 *            console.log(product);
 *        }
 *    });
 * 
 * 3. Or use the inline helper:
 *    <input type="text" onclick="ProductSearch.pick(1, this)" readonly placeholder="اضغط F2 أو اضغط هنا">
 */

const ProductSearch = (function() {
    'use strict';
    
    let popupWindow = null;
    let onSelectCallback = null;
    let storeId = null;
    
    // Callback function called by popup window
    window.onProductSelected = function(product) {
        if (onSelectCallback && typeof onSelectCallback === 'function') {
            onSelectCallback(product);
        }
        popupWindow = null;
    };
    
    // Listen for postMessage (iframe mode)
    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'product-selected') {
            if (onSelectCallback && typeof onSelectCallback === 'function') {
                onSelectCallback(e.data.data);
            }
            popupWindow = null;
        }
    });
    
    function getBaseUrl() {
        // Auto-detect base URL
        const scripts = document.querySelectorAll('script[src*="product-search"]');
        if (scripts.length > 0) {
            const src = scripts[0].src;
            return src.substring(0, src.indexOf('/js/'));
        }
        // Fallback - assumes standard structure
        return window.location.pathname.split('/modules/')[0];
    }
    
    return {
        /**
         * Open search popup
         * @param {Object} options
         *   - storeId: required, the store ID to search in
         *   - onSelect: required, callback function when product is selected
         *   - callbackName: optional, custom callback name (default: onProductSelected)
         */
        open: function(options) {
            if (!options.storeId) {
                console.error('ProductSearch: storeId is required');
                return;
            }
            if (!options.onSelect || typeof options.onSelect !== 'function') {
                console.error('ProductSearch: onSelect callback is required');
                return;
            }
            
            storeId = options.storeId;
            onSelectCallback = options.onSelect;
            const callbackName = options.callbackName || 'onProductSelected';
            const baseUrl = getBaseUrl();
            
            // Close previous popup if exists
            if (popupWindow && !popupWindow.closed) {
                popupWindow.close();
            }
            
            // Calculate center position
            const width = 900;
            const height = 700;
            const left = (window.screen.width - width) / 2;
            const top = (window.screen.height - height) / 2;
            
            // Open popup
            const url = `${baseUrl}/includes/product-search-popup-new.php?store_id=${storeId}&callback=${callbackName}`;
            popupWindow = window.open(
                url,
                'productSearchPopup',
                `width=${width},height=${height},top=${top},left=${left},` +
                `resizable=yes,scrollbars=yes,status=no,menubar=no,toolbar=no`
            );
            
            // Focus popup
            if (popupWindow) {
                popupWindow.focus();
            }
            
            // Check if popup was blocked
            setTimeout(function() {
                if (!popupWindow || popupWindow.closed || popupWindow.innerHeight === 0) {
                    // Fallback: open in iframe modal
                    ProductSearch._openIframe(url);
                }
            }, 500);
        },
        
        /**
         * Inline helper - attach to input field
         * When user presses F2 or clicks, opens search
         * @param {number} storeId
         * @param {HTMLElement} inputElement - the input to fill after selection
         * @param {Object} extraOptions - optional extra data to include
         */
        pick: function(storeId, inputElement, extraOptions) {
            extraOptions = extraOptions || {};
            
            // Attach F2 key handler to input
            inputElement.addEventListener('keydown', function(e) {
                if (e.key === 'F2') {
                    e.preventDefault();
                    ProductSearch._doPick(storeId, inputElement, extraOptions);
                }
            });
            
            // Attach click handler
            inputElement.addEventListener('click', function(e) {
                if (inputElement.readOnly || inputElement.dataset.searchTrigger) {
                    ProductSearch._doPick(storeId, inputElement, extraOptions);
                }
            });
            
            // Add search icon if not exists
            if (!inputElement.dataset.searchIconAdded) {
                inputElement.dataset.searchIconAdded = 'true';
                inputElement.style.paddingLeft = '35px';
                
                const wrap = document.createElement('div');
                wrap.style.position = 'relative';
                wrap.style.display = 'inline-block';
                inputElement.parentNode.insertBefore(wrap, inputElement);
                wrap.appendChild(inputElement);
                
                const icon = document.createElement('button');
                icon.type = 'button';
                icon.innerHTML = '<i class="bi bi-search"></i>';
                icon.title = 'بحث F2';
                icon.style.cssText = 'position:absolute;left:0;top:0;bottom:0;width:30px;' +
                    'border:1px solid #dee2e6;border-radius:4px 0 0 4px;background:#f8f9fa;' +
                    'cursor:pointer;display:flex;align-items:center;justify-content:center;';
                icon.onclick = function() {
                    ProductSearch._doPick(storeId, inputElement, extraOptions);
                };
                wrap.appendChild(icon);
            }
        },
        
        // Internal: do the actual pick
        _doPick: function(sid, inputEl, extraOptions) {
            ProductSearch.open({
                storeId: sid,
                onSelect: function(product) {
                    // Fill input with product name
                    inputEl.value = product.product_name;
                    inputEl.dataset.productId = product.product_id;
                    inputEl.dataset.productCode = product.product_code;
                    inputEl.dataset.barcode = product.barcode;
                    inputEl.dataset.batchId = product.batch_id || '';
                    inputEl.dataset.expDate = product.exp_date || '';
                    inputEl.dataset.qty = product.quantity;
                    inputEl.dataset.unitCost = product.unit_cost;
                    inputEl.dataset.sellPrice = product.sell_price;
                    inputEl.dataset.total = product.total;
                    
                    // Trigger change event
                    inputEl.dispatchEvent(new Event('change'));
                    inputEl.dispatchEvent(new CustomEvent('productSelected', { detail: product }));
                    
                    // Call extra callback if provided
                    if (extraOptions.onSelect) {
                        extraOptions.onSelect(product, inputEl);
                    }
                }
            });
        },
        
        // Internal: open in iframe modal (fallback when popup blocked)
        _openIframe: function(url) {
            // Remove existing modal
            const existing = document.getElementById('productSearchModal');
            if (existing) existing.remove();
            
            const modal = document.createElement('div');
            modal.id = 'productSearchModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;' +
                'background:rgba(0,0,0,0.5);z-index:9999;display:flex;' +
                'align-items:center;justify-content:center;';
            
            modal.innerHTML = `
                <div style="background:white;border-radius:12px;width:90%;height:90%;overflow:hidden;position:relative;">
                    <button onclick="document.getElementById('productSearchModal').remove()" 
                        style="position:absolute;top:10px;left:10px;z-index:10;background:#dc3545;color:white;border:none;padding:5px 15px;border-radius:6px;cursor:pointer;">
                        <i class="bi bi-x-lg"></i> إغلاق
                    </button>
                    <iframe src="${url}" style="width:100%;height:100%;border:none;"></iframe>
                </div>
            `;
            
            document.body.appendChild(modal);
        },
        
        /**
         * Quick search - returns results without popup
         * @param {number} storeId
         * @param {string} query
         * @param {string} type - search type
         * @returns {Promise<Array>}
         */
        quickSearch: async function(storeId, query, type) {
            const baseUrl = getBaseUrl();
            const url = `${baseUrl}/api/product-search-advanced.php?store_id=${storeId}&type=${type || 'name'}&q=${encodeURIComponent(query)}&limit=20`;
            const res = await fetch(url);
            const data = await res.json();
            return data.products || [];
        }
    };
})();
