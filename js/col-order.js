/**
 * Column Order Manager - ترتيب الأعمدة بالسحب والإفلات
 * 
 * ALL COLUMNS ARE MANDATORY - no hiding, only reordering
 * Saves column order to localStorage per page
 * 
 * Usage:
 *   const colDefs = [
 *     {key:'barcode', label:'الباركود', width:'100px'},
 *     {key:'name', label:'اسم الصنف', width:'170px'},
 *     // ...
 *   ];
 *   ColOrder.init(colDefs, 'my_page_cols', 'headerRowId');
 *   // In buildRowCells: use ColOrder.buildCells(builders) or ColOrder.isVisible(key) [always true]
 *   // Add a button: onclick="ColOrder.openModal()"
 */
const ColOrder = (function(){
    'use strict';
    
    let _defs=[], _order=[], _storageKey='', _headerId='';
    let _dragSrc=null;

    /**
     * Initialize the column order manager
     * @param {Array} colDefs - Column definitions [{key, label, width, fixed}]
     * @param {String} storageKey - localStorage key
     * @param {String} headerRowId - ID of the <tr> element for headers
     */
    function init(colDefs, storageKey, headerRowId){
        _defs=colDefs;
        _storageKey=storageKey;
        _headerId=headerRowId;
        
        // Load saved order or use default
        const saved=localStorage.getItem(_storageKey);
        if(saved){
            try{
                const parsed=JSON.parse(saved);
                // Validate: all definition keys must be present
                const defKeys=_defs.map(c=>c.key);
                const savedKeys=parsed.map(o=>o.key);
                const allPresent=defKeys.every(k=>savedKeys.includes(k));
                const noExtras=savedKeys.every(k=>defKeys.includes(k));
                if(allPresent && noExtras){
                    _order=parsed;
                }else{
                    _order=_defs.map(c=>({key:c.key}));
                }
            }catch(e){
                _order=_defs.map(c=>({key:c.key}));
            }
        }else{
            _order=_defs.map(c=>({key:c.key}));
        }
        renderHeader();
    }

    function getDef(key){return _defs.find(c=>c.key===key)||{label:'',width:''};}

    /**
     * Render table headers in current order
     */
    function renderHeader(){
        const hr=document.getElementById(_headerId);
        if(!hr)return;
        hr.innerHTML=_order.map(c=>{
            const d=getDef(c.key);
            const style=d.width?' style="min-width:'+d.width+'"':'';
            return '<th'+style+'>'+(d.label||'')+'</th>';
        }).join('');
    }

    /**
     * Get current column order
     */
    function getOrder(){return _order;}

    /**
     * Get ordered column keys
     */
    function getKeys(){return _order.map(c=>c.key);}

    /**
     * Check if column is visible - ALWAYS RETURNS TRUE (all columns mandatory)
     * Kept for backward compatibility with existing buildRowCells functions
     */
    function isVisible(key){return true;}

    /**
     * Build row cells HTML in the current column order
     * @param {Object} builders - Object mapping column keys to functions that return HTML strings
     * @returns {String} Combined HTML for all cells in order
     * 
     * Usage:
     *   const html = ColOrder.buildCells({
     *     'rownum': () => '<td>1</td>',
     *     'barcode': () => '<td><input...></td>',
     *     // etc
     *   });
     */
    function buildCells(builders){
        let h='';
        _order.forEach(c=>{
            if(builders[c.key]){
                h+=builders[c.key]();
            }
        });
        return h;
    }

    /* ===== Modal ===== */
    function openModal(){
        let modal=document.getElementById('colOrderModal');
        if(!modal){modal=createModalElement();}
        renderModalList();
        modal.style.display='flex';
    }
    
    function closeModal(){
        const m=document.getElementById('colOrderModal');
        if(m)m.style.display='none';
    }

    function createModalElement(){
        const div=document.createElement('div');
        div.id='colOrderModal';
        div.style.cssText='display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;';
        div.innerHTML=
            '<div id="colOrderBox" style="background:#fff;border-radius:12px;padding:20px;width:380px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);direction:rtl;">'
            +'  <h5 style="margin:0 0 12px;font-size:16px;"><i class="bi bi-layout-three-columns"></i> ترتيب الأعمدة</h5>'
            +'  <p style="font-size:12px;color:#666;margin:0 0 12px;">اسحب الأعمدة للأعلى أو الأسفل لترتيبها</p>'
            +'  <ul id="colOrderList" style="list-style:none;padding:0;margin:0;"></ul>'
            +'  <div style="display:flex;gap:8px;margin-top:15px;justify-content:flex-end;">'
            +'    <button type="button" class="btn btn-sm btn-secondary" onclick="ColOrder.resetOrder()">إعادة ضبط</button>'
            +'    <button type="button" class="btn btn-sm btn-primary" onclick="ColOrder.saveOrder()">حفظ</button>'
            +'    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="ColOrder.closeModal()">إغلاق</button>'
            +'  </div>'
            +'</div>';
        document.body.appendChild(div);
        div.addEventListener('click',function(e){if(e.target===div)closeModal();});
        
        // Escape key to close
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape') closeModal();
        });
        
        return div;
    }

    function renderModalList(){
        const list=document.getElementById('colOrderList');
        if(!list)return;
        list.innerHTML='';
        _order.forEach((c,idx)=>{
            const def=getDef(c.key);
            // Skip fixed columns (rownum, delete) from reordering
            if(def.fixed)return;
            
            const li=document.createElement('li');
            li.draggable=true;
            li.dataset.index=idx;
            li.style.cssText='display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e9ecef;border-radius:6px;margin-bottom:6px;background:#fff;cursor:move;transition:all .2s;user-select:none;';
            li.innerHTML='<span style="color:#999;font-size:14px;cursor:move;"><i class="bi bi-grip-vertical"></i></span>'
                +'<span style="flex:1;margin:0;font-size:13px;font-weight:500;">'+(def.label||c.key)+'</span>'
                +'<span style="color:#bbb;font-size:11px;">#'+(idx+1)+'</span>';
            
            li.addEventListener('dragstart',dragStart);
            li.addEventListener('dragover',dragOver);
            li.addEventListener('drop',drop);
            li.addEventListener('dragend',dragEnd);
            
            // Touch support for mobile
            li.addEventListener('touchstart',touchStart,{passive:false});
            li.addEventListener('touchmove',touchMove,{passive:false});
            li.addEventListener('touchend',touchEnd);
            
            list.appendChild(li);
        });
    }

    function saveOrder(){
        localStorage.setItem(_storageKey,JSON.stringify(_order));
        renderHeader();
        closeModal();
        // Refresh page to apply new order to existing rows
        location.reload();
    }

    function resetOrder(){
        _order=_defs.map(c=>({key:c.key}));
        localStorage.setItem(_storageKey,JSON.stringify(_order));
        renderHeader();
        closeModal();
        location.reload();
    }

    /* ===== Drag & Drop (Mouse) ===== */
    function dragStart(e){
        _dragSrc=this;
        this.style.opacity='0.5';
        e.dataTransfer.effectAllowed='move';
        e.dataTransfer.setData('text/html',this.innerHTML);
    }
    
    function dragOver(e){
        e.preventDefault();
        e.dataTransfer.dropEffect='move';
        return false;
    }
    
    function drop(e){
        e.stopPropagation();
        if(_dragSrc===this)return false;
        const srcIdx=parseInt(_dragSrc.dataset.index);
        const tgtIdx=parseInt(this.dataset.index);
        if(srcIdx!==tgtIdx){
            const item=_order.splice(srcIdx,1)[0];
            _order.splice(tgtIdx,0,item);
        }
        renderModalList();
        return false;
    }
    
    function dragEnd(){
        this.style.opacity='1';
        _dragSrc=null;
    }

    /* ===== Touch Support (Mobile) ===== */
    let _touchSrc=null,_touchY=0;
    
    function touchStart(e){
        _touchSrc=this;
        _touchY=e.touches[0].clientY;
        this.style.opacity='0.5';
    }
    
    function touchMove(e){
        e.preventDefault();
        if(!_touchSrc)return;
        const y=e.touches[0].clientY;
        const list=document.getElementById('colOrderList');
        if(!list)return;
        const items=Array.from(list.children);
        const srcIdx=parseInt(_touchSrc.dataset.index);
        items.forEach(item=>{
            const rect=item.getBoundingClientRect();
            if(y>rect.top&&y<rect.bottom){
                const tgtIdx=parseInt(item.dataset.index);
                if(tgtIdx!==srcIdx){
                    const itemData=_order.splice(srcIdx,1)[0];
                    _order.splice(tgtIdx,0,itemData);
                    renderModalList();
                    _touchSrc=list.children[Math.min(tgtIdx,list.children.length-1)];
                    if(_touchSrc)_touchSrc.style.opacity='0.5';
                }
            }
        });
    }
    
    function touchEnd(){
        if(_touchSrc)_touchSrc.style.opacity='1';
        _touchSrc=null;
    }

    // Public API
    return{
        init:init,
        openModal:openModal,
        closeModal:closeModal,
        saveOrder:saveOrder,
        resetOrder:resetOrder,
        getOrder:getOrder,
        getKeys:getKeys,
        isVisible:isVisible,        // Backward-compatible: always returns true
        buildCells:buildCells,      // New: builds cells in order
        renderHeader:renderHeader
    };
})();
