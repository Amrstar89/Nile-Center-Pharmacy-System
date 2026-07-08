/**
 * Column Order & Visibility Manager
 * Handles customizable table columns with localStorage persistence
 */
const ColOrder = (function() {
    let defs = [];
    let storageKey = '';
    let headerRowId = '';
    let visibleMap = {};

    function init(columnDefs, sKey, hrId) {
        defs = columnDefs;
        storageKey = sKey;
        headerRowId = hrId;

        // Load saved visibility from localStorage
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            try {
                visibleMap = JSON.parse(saved);
            } catch(e) {
                visibleMap = {};
            }
        }

        // Default: all visible except those explicitly hidden
        defs.forEach(function(d) {
            if (!(d.key in visibleMap)) {
                visibleMap[d.key] = true;
            }
        });

        renderHeaders();
    }

    function isVisible(key) {
        return visibleMap[key] !== false;
    }

    function renderHeaders() {
        const row = document.getElementById(headerRowId);
        if (!row) return;
        let html = '';
        defs.forEach(function(d) {
            if (d.fixed || visibleMap[d.key] !== false) {
                const style = d.width ? ' style="width:' + d.width + '"' : '';
                html += '<th' + style + '>' + d.label + '</th>';
            }
        });
        row.innerHTML = html;
    }

    function openModal() {
        const existing = document.getElementById('colOrderModal');
        if (existing) existing.remove();

        let html = '<div class="modal fade show" id="colOrderModal" tabindex="-1" style="display:block;z-index:9999;background:rgba(0,0,0,0.5)">'
            + '<div class="modal-dialog modal-sm" style="margin-top:100px">'
            + '<div class="modal-content">'
            + '<div class="modal-header"><h5 class="modal-title"><i class="bi bi-layout-three-columns"></i> تخصيص الأعمدة</h5>'
            + '<button type="button" class="btn-close" onclick="ColOrder.closeModal()"></button></div>'
            + '<div class="modal-body">';

        defs.forEach(function(d, i) {
            if (d.fixed) return;
            const checked = visibleMap[d.key] !== false ? 'checked' : '';
            html += '<div class="form-check mb-2">'
                + '<input class="form-check-input" type="checkbox" id="col_chk_' + i + '" ' + checked
                + ' onchange="ColOrder.toggleCol(\'' + d.key + '\', this.checked)">'
                + '<label class="form-check-label" for="col_chk_' + i + '">' + d.label + '</label>'
                + '</div>';
        });

        html += '</div><div class="modal-footer">'
            + '<button type="button" class="btn btn-sm btn-secondary" onclick="ColOrder.resetDefaults()">الافتراضي</button>'
            + '<button type="button" class="btn btn-sm btn-primary" onclick="ColOrder.closeModal()">إغلاق</button>'
            + '</div></div></div></div>';

        const div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div);

        document.getElementById('colOrderModal').addEventListener('click', function(e) {
            if (e.target === this) ColOrder.closeModal();
        });
    }

    function closeModal() {
        const m = document.getElementById('colOrderModal');
        if (m) m.remove();
    }

    function toggleCol(key, visible) {
        visibleMap[key] = visible;
        localStorage.setItem(storageKey, JSON.stringify(visibleMap));
        renderHeaders();
        location.reload();
    }

    function resetDefaults() {
        localStorage.removeItem(storageKey);
        visibleMap = {};
        defs.forEach(function(d) { visibleMap[d.key] = true; });
        renderHeaders();
        closeModal();
        location.reload();
    }

    return {
        init: init,
        isVisible: isVisible,
        openModal: openModal,
        closeModal: closeModal,
        toggleCol: toggleCol,
        resetDefaults: resetDefaults
    };
})();
