/**
 * Column Order Manager - Shared Library
 * Allows users to reorder and show/hide table columns
 * Saves preferences to localStorage per page
 * 
 * Usage:
 *   const colDefs = [
 *     {key:'barcode', label:'الباركود', width:'100px'},
 *     {key:'name', label:'اسم الصنف', width:'170px'},
 *     // ...
 *   ];
 *   ColOrder.init(colDefs, 'my_page_cols', 'headerRowId', buildRowHTML);
 *   // Add a button to open: ColOrder.openModal();
 */
const ColOrder = (function(){
    let _defs=[], _order=[], _storageKey='', _headerId='', _buildFn=null;
    let _dragSrc=null;

    function init(colDefs, storageKey, headerRowId, buildRowHTMLFn){
        _defs=colDefs;
        _storageKey=storageKey;
        _headerId=headerRowId;
        _buildFn=buildRowHTMLFn;
        const saved=localStorage.getItem(_storageKey);
        _order=saved?JSON.parse(saved):_defs.map(c=>({key:c.key,visible:c.hidden!==true}));
        renderHeader();
    }

    function getDef(key){return _defs.find(c=>c.key===key)||{label:'',width:''};}

    function renderHeader(){
        const hr=document.getElementById(_headerId);
        if(!hr)return;
        hr.innerHTML=_order.filter(c=>c.visible).map(c=>{
            const d=getDef(c.key);
            const style=d.width?' style="min-width:'+d.width+'"':'';
            return '<th'+style+'>'+(d.label||'')+'</th>';
        }).join('');
    }

    function getOrder(){return _order;}

    function isVisible(key){const o=_order.find(x=>x.key===key);return o?o.visible:true;}

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
            '<div id="colOrderBox" style="background:#fff;border-radius:12px;padding:20px;width:380px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">'
            +'  <h5 style="margin:0 0 12px;font-size:16px;"><i class="bi bi-layout-three-columns"></i> تخصيص الأعمدة</h5>'
            +'  <p style="font-size:12px;color:#666;margin:0 0 12px;">اسحب للترتيب - أزل التحديد للإخفاء</p>'
            +'  <ul id="colOrderList" style="list-style:none;padding:0;margin:0;"></ul>'
            +'  <div style="display:flex;gap:8px;margin-top:15px;justify-content:flex-end;">'
            +'    <button type="button" class="btn btn-sm btn-secondary" onclick="ColOrder.resetOrder()">إعادة ضبط</button>'
            +'    <button type="button" class="btn btn-sm btn-primary" onclick="ColOrder.saveOrder()">حفظ</button>'
            +'    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="ColOrder.closeModal()">إغلاق</button>'
            +'  </div>'
            +'</div>';
        document.body.appendChild(div);
        div.addEventListener('click',function(e){if(e.target===div)closeModal();});
        return div;
    }

    function renderModalList(){
        const list=document.getElementById('colOrderList');
        if(!list)return;
        list.innerHTML='';
        _order.forEach((c,idx)=>{
            const def=getDef(c.key);
            if(c.key==='rownum'||c.key==='delete'||def.fixed)return;
            const li=document.createElement('li');
            li.draggable=true;
            li.dataset.index=idx;
            li.style.cssText='display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #e9ecef;border-radius:6px;margin-bottom:6px;background:#fff;cursor:move;transition:all .2s;';
            li.innerHTML='<span style="color:#999;font-size:14px;cursor:move;"><i class="bi bi-grip-vertical"></i></span>'
                +'<input type="checkbox" '+(c.visible?'checked':'')+' style="cursor:pointer;" onchange="ColOrder.toggleVisible('+idx+',this.checked)">'
                +'<label style="flex:1;margin:0;font-size:13px;cursor:pointer;">'+(def.label||c.key)+'</label>';
            li.addEventListener('dragstart',dragStart);
            li.addEventListener('dragover',dragOver);
            li.addEventListener('drop',drop);
            li.addEventListener('dragend',dragEnd);
            list.appendChild(li);
        });
    }

    function toggleVisible(idx,visible){_order[idx].visible=visible;}

    function saveOrder(){
        localStorage.setItem(_storageKey,JSON.stringify(_order));
        renderHeader();
        closeModal();
        // Refresh existing rows
        refreshExistingRows();
    }

    function resetOrder(){
        _order=_defs.map(c=>({key:c.key,visible:c.hidden!==true}));
        localStorage.setItem(_storageKey,JSON.stringify(_order));
        renderHeader();
        closeModal();
        refreshExistingRows();
    }

    function refreshExistingRows(){
        if(!_buildFn)return;
        const tbody=document.getElementById('itemsBody');
        if(!tbody)return;
        const rows=[];
        tbody.querySelectorAll('tr').forEach(tr=>{
            const id=tr.dataset.rid;
            if(!id)return;
            const d={};
            tr.querySelectorAll('input,select').forEach(inp=>{
                if(inp.name){
                    const m=inp.name.match(/\[([^\]]+)\]$/);
                    if(m)d[m[1]]=inp.value;
                }
                if(inp.id&&!inp.name){
                    const shortId=inp.id.replace(/^[a-z]+_/,'');
                    if(shortId===id)d[inp.id.split('_')[0]]=inp.value;
                }
            });
            // Extract special fields
            const pidInput=tr.querySelector('input[name*="[product_id]"]');
            d.product_id=pidInput?pidInput.value:'';
            const stockInput=tr.querySelector('input[id^="st_"]');
            if(stockInput)d.stock_qty=stockInput.value;
            const companyInput=tr.querySelector('input[id^="cy_"]');
            if(companyInput)d.company_name=companyInput.value;
            const locInput=tr.querySelector('input[id^="lc_"]');
            if(locInput)d.location=locInput.value;
            const sellInput=tr.querySelector('input[name*="[sell_price]"],input[id^="sp_"]');
            if(sellInput)d.sell_price=sellInput.value;
            rows.push({id:id,data:d});
        });
        tbody.innerHTML='';
        rows.forEach(r=>{
            const html=_buildFn(parseInt(r.id),r.data);
            const temp=document.createElement('tbody');
            temp.innerHTML=html;
            const newRow=temp.firstElementChild;
            if(newRow)tbody.appendChild(newRow);
        });
        // Re-attach F2 listeners and recalc
        if(typeof recalc==='function')recalc();
        rows.forEach(r=>{
            const rid=r.id;
            const bc=document.getElementById('bc_'+rid);
            const nm=document.getElementById('nm_'+rid);
            if(bc)bc.addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();if(typeof f2row==='function')f2row(rid);else if(typeof f2r==='function')f2r(rid);}});
            if(nm)nm.addEventListener('keydown',function(e){if(e.key==='F2'){e.preventDefault();if(typeof f2row==='function')f2row(rid);else if(typeof f2r==='function')f2r(rid);}});
        });
    }

    /* ===== Drag & Drop ===== */
    function dragStart(e){_dragSrc=this;this.style.opacity='0.5';e.dataTransfer.effectAllowed='move';}
    function dragOver(e){e.preventDefault();e.dataTransfer.dropEffect='move';return false;}
    function drop(e){e.stopPropagation();const srcIdx=parseInt(_dragSrc.dataset.index);const tgtIdx=parseInt(this.dataset.index);if(srcIdx!==tgtIdx){const item=_order.splice(srcIdx,1)[0];_order.splice(tgtIdx,0,item);renderModalList();}return false;}
    function dragEnd(){this.style.opacity='1';}

    // Public API
    return{
        init:init,
        openModal:openModal,
        closeModal:closeModal,
        toggleVisible:toggleVisible,
        saveOrder:saveOrder,
        resetOrder:resetOrder,
        getOrder:getOrder,
        isVisible:isVisible,
        renderHeader:renderHeader
    };
})();