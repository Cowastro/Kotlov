(function () {
    'use strict';
    var root = document.querySelector('[data-cart-workspace]');
    if (!root) return;
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var summary = JSON.parse(root.dataset.initialSelection || '{}');
    var chain = Promise.resolve(), pending = 0, timers = new Map(), failures = new Set();
    var rows = Array.from(root.querySelectorAll('[data-cart-row]'));
    var errorBox = document.getElementById('cart-error');
    var state = document.getElementById('cart-save-state');
    function money(n) { return Number(n).toLocaleString('ru-RU', {minimumFractionDigits:2,maximumFractionDigits:2}); }
    function put(id, text) { var e=document.getElementById(id); if(e) e.textContent=text; }
    function busy() { return pending > 0 || timers.size > 0; }
    function syncButtons() {
        var allowed = !busy() && !failures.size && summary.positions > 0;
        var agree = document.getElementById('cart-agree'), checkout = document.getElementById('checkout-btn');
        if(checkout) { checkout.disabled = !allowed || !agree.checked; checkout.style.opacity=checkout.disabled?'.45':'1'; checkout.style.cursor=checkout.disabled?'not-allowed':'pointer'; }
        var mobile=document.getElementById('cart-mobile-checkout'); if(mobile) mobile.disabled=!allowed;
        if(state) state.textContent=busy()?'Сохраняем изменения…':failures.size?'Есть несохранённые изменения':'Изменения сохранены';
    }
    function showError(message) {
        if(!errorBox) return;
        errorBox.hidden=false; errorBox.textContent=message+' Перед оформлением повторите действие или обновите корзину.';
        var button=document.createElement('button');button.type='button';button.textContent='Обновить корзину';button.onclick=function(){location.reload();};errorBox.appendChild(button);
    }
    function drawRow(row) {
        var q=Number(row.querySelector('.cart-qty-input').value), price=Number(row.dataset.price);
        row.querySelector('[data-line-quantity]').textContent=Number.isInteger(q)&&q>0?q:'—';
        row.querySelector('[data-line-total]').textContent=Number.isInteger(q)&&q>0?money(price*q)+' BYN':'Укажите количество';
        row.querySelector('[data-unit-price]').textContent=money(price);
        var discount=row.querySelector('[data-b2b-discount]');if(discount){var retail=Number(discount.dataset.retail);discount.textContent=retail>price&&retail>0?'Выгода '+((retail-price)/retail*100).toLocaleString('ru-RU',{maximumFractionDigits:1})+'%':'';}
    }
    function drawSelection(data) {
        if(!data) return; summary=data;
        root.querySelectorAll('[data-selected-positions]').forEach(function(e){e.textContent=data.positions;});
        root.querySelectorAll('[data-selected-count]').forEach(function(e){e.textContent=data.count;});
        root.querySelectorAll('[data-selected-total]').forEach(function(e){e.textContent=money(data.total)+' BYN';});
        put('cart-subtotal',money(data.subtotal)+' BYN');put('cart-items-count',data.count+' шт.');put('cart-total',money(data.total)+' BYN');
        [['cart-coupon-summary',data.discount],['cart-online-discount-row',data.coupon_discount],['cart-bundle-discount-row',data.bundle_discount]].forEach(function(x){var el=document.getElementById(x[0]);if(el)el.style.setProperty('display',x[1]>0?'':'none','important');});
        put('cart-online-discount','−'+money(data.coupon_discount)+' BYN');put('cart-bundle-discount','−'+money(data.bundle_discount)+' BYN');
        [['cart-online-note',data.coupon_discount],['cart-bundle-note',data.bundle_discount]].forEach(function(x){var el=document.getElementById(x[0]);if(el)el.hidden=!(x[1]>0);});
        var delivery=document.getElementById('cart-delivery-estimate');if(delivery)delivery.textContent=data.subtotal>=Number(delivery.dataset.threshold)?'В пределах МКАД-2 — бесплатно; по городам графика — согласно тарифу':'от 10 BYN, точная цена при оформлении';
        put('cart-bundle-discount-label',(data.bundle_mode==='self_selection'?'Самостоятельный подбор':'Дымоход с оборудованием')+' −'+data.bundle_percent+'%:');
        var all=document.getElementById('cart-select-all');if(all){var n=rows.filter(function(r){return r.querySelector('.cart-line-check').checked;}).length;all.checked=n===rows.length;all.indeterminate=n>0&&n<rows.length;}
        syncButtons();
    }
    function enqueue(key,url,body,onSuccess) {
        pending++;syncButtons();
        chain=chain.then(function(){return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)})
            .then(function(r){return r.json().catch(function(){return {};}).then(function(d){if(!r.ok)throw new Error(d.message||'Не удалось сохранить изменение.');return d;});})
            .then(function(d){failures.delete(key);if(onSuccess)onSuccess(d);if(d.selection)drawSelection(d.selection);if(d.count!==undefined&&window.updateCartCount)window.updateCartCount(d.count);if(!failures.size&&errorBox)errorBox.hidden=true;})
            .catch(function(e){failures.add(key);showError(e.message||'Нет соединения с сервером.');})
            .finally(function(){pending--;syncButtons();});});
        return chain;
    }
    function schedule(input,fn) { if(timers.has(input))clearTimeout(timers.get(input).timer);timers.set(input,{fn:fn,timer:setTimeout(function(){timers.delete(input);fn();syncButtons();},300)});syncButtons(); }
    function flush() { timers.forEach(function(t){clearTimeout(t.timer);t.fn();});timers.clear();syncButtons();return chain; }
    function saveQuantity(input) {
        var row=input.closest('[data-cart-row]'), q=Number(input.value), key='qty-'+row.dataset.productId;
        if(!Number.isInteger(q)||q<1||q>999){failures.add(key);showError('Количество должно быть целым числом от 1 до 999.');syncButtons();return;}
        enqueue(key,'/cart/update',{product_id:row.dataset.productId,quantity:q},function(d){
            if(d.item_quantity===0){failures.add(key);showError('Позиция больше недоступна. Обновите корзину.');return;}
            input.dataset.saved=d.item_quantity;row.dataset.price=d.item_price;
            if(Number(input.value)===q)input.value=d.item_quantity;drawRow(row);
        });
    }
    rows.forEach(function(row){
        drawRow(row);
        var input=row.querySelector('.cart-qty-input');
        row.querySelectorAll('[data-qty-step]').forEach(function(btn){btn.addEventListener('click',function(){input.value=Math.min(999,Math.max(1,(Number(input.value)||1)+Number(btn.dataset.qtyStep)));drawRow(row);schedule(input,function(){saveQuantity(input);});});});
        input.addEventListener('input',function(){drawRow(row);schedule(input,function(){saveQuantity(input);});});
        var check=row.querySelector('.cart-line-check');check.addEventListener('change',function(){row.classList.toggle('is-postponed',!check.checked);enqueue('select-'+row.dataset.productId,'/cart/selection',{ids:[Number(row.dataset.productId)],selected:check.checked});});
        var note=row.querySelector('.cart-line-note');note.addEventListener('input',function(){schedule(note,function(){enqueue('note-'+row.dataset.productId,'/cart/line-note',{product_id:row.dataset.productId,note:note.value});});});
        var editNote=row.querySelector('[data-edit-line-note]');if(editNote)editNote.addEventListener('click',function(){row.querySelector('.cart-line__note').hidden=false;row.querySelector('details').open=false;note.focus();});
        var move=row.querySelector('[data-move-line]');if(move)move.addEventListener('click',function(){document.getElementById('cart-move-id').value=row.dataset.productId;document.getElementById('cart-move-name').textContent=row.querySelector('h2').textContent;row.querySelector('details').open=false;document.getElementById('cart-move-dialog').showModal();});
    });
    var all=document.getElementById('cart-select-all');if(all)all.addEventListener('change',function(){var selected=all.checked;rows.forEach(function(row){row.querySelector('.cart-line-check').checked=selected;row.classList.toggle('is-postponed',!selected);});enqueue('select-all','/cart/selection',{ids:rows.map(function(r){return Number(r.dataset.productId);}),selected:selected});});
    var search=document.getElementById('cart-find');if(search)search.addEventListener('input',function(){var q=this.value.toLocaleLowerCase('ru').trim(), visible=0;rows.forEach(function(row){row.hidden=!row.querySelector('.cart-line__info').textContent.toLocaleLowerCase('ru').includes(q);if(!row.hidden)visible++;});root.querySelector('.cart-search-empty').hidden=visible>0;});
    var agree=document.getElementById('cart-agree');if(agree)agree.addEventListener('change',syncButtons);
    var checkout=document.getElementById('checkout-btn');if(checkout)checkout.addEventListener('click',function(){flush().then(function(){if(!failures.size&&summary.positions>0&&agree.checked)location.href='/checkout';});});
    var mobile=document.getElementById('cart-mobile-checkout');if(mobile)mobile.addEventListener('click',function(){if(agree.checked)checkout.click();else{document.getElementById('cart-order-summary').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});agree.focus({preventScroll:true});}});
    root.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(e){e.preventDefault();if(form.dataset.waiting)return;form.dataset.waiting='1';flush().then(function(){if(failures.size){delete form.dataset.waiting;errorBox.scrollIntoView({block:'center'});return;}if(form.reportValidity()){form.dataset.ready='1';HTMLFormElement.prototype.submit.call(form);}else{delete form.dataset.waiting;}});});});
    root.querySelectorAll('a[target="_blank"]').forEach(function(a){a.addEventListener('click',function(e){if(busy()||failures.size){e.preventDefault();showError('Сначала дождитесь сохранения изменений.');}});});
    // Header/account actions live outside the cart form. Wait for its debounced
    // writes before navigation or logout invalidates the current session.
    function afterSaved(action) {
        flush().then(function () {
            if (failures.size) {
                if (errorBox) errorBox.scrollIntoView({block:'center'});
                return;
            }
            action();
        });
    }
    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || (!busy() && !failures.size)) return;
        var link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.getAttribute('href').startsWith('#')) return;
        var url = new URL(link.href, location.href);
        if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
        event.preventDefault();
        afterSaved(function () { location.assign(url.href); });
    });
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (root.contains(form) || (!busy() && !failures.size)) return;
        var url = new URL(form.action, location.href);
        if (url.origin !== location.origin || url.pathname !== '/logout') return;
        event.preventDefault();
        if (form.dataset.cartWaiting) return;
        form.dataset.cartWaiting = '1';
        afterSaved(function () { HTMLFormElement.prototype.submit.call(form); });
        chain.then(function () { delete form.dataset.cartWaiting; });
    });
    var dialog=document.getElementById('cart-move-dialog');if(dialog){dialog.querySelector('[data-close-cart-dialog]').onclick=function(){dialog.close();};document.getElementById('cart-move-target').onchange=function(){var fresh=!this.value;document.getElementById('cart-move-new').hidden=!fresh;document.getElementById('cart-move-title').required=fresh;};}
    var note=document.getElementById('cart-b2b-note');if(note)note.addEventListener('input',function(){schedule(note,function(){enqueue('cart-note','/cart/note',{note:note.value});});});
    root.querySelectorAll('[data-share-cart]').forEach(function(button){button.addEventListener('click',async function(){
        if(busy()||failures.size){showError('Сначала дождитесь сохранения изменений.');return;}
        var text='Подбор оборудования KOTLOV.BY\n'+rows.map(function(row){return row.querySelector('h2').textContent.trim()+' · '+row.querySelector('.cart-line__sku').textContent.trim()+' · '+row.querySelector('.cart-qty-input').value+' шт. · '+row.querySelector('[data-line-total]').textContent+(row.querySelector('.cart-line-note').value?'\n'+row.querySelector('.cart-line-note').value:'');}).join('\n\n')+'\n\nЦены на момент подбора. Наличие и условия подтвердит менеджер.';
        try{if(navigator.share)await navigator.share({title:'Подбор KOTLOV',text:text});else{await navigator.clipboard.writeText(text);state.textContent='Подбор скопирован — вставьте его в сообщение';}}catch(e){if(e.name!=='AbortError')showError('Не удалось поделиться. Можно воспользоваться печатью / PDF.');}
    });});
    document.addEventListener('click',function(e){root.querySelectorAll('.cart-line__menu[open]').forEach(function(menu){if(!menu.contains(e.target))menu.open=false;});});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')root.querySelectorAll('.cart-line__menu[open]').forEach(function(menu){menu.open=false;});});
    window.addEventListener('beforeunload',function(e){if(busy()||failures.size){e.preventDefault();e.returnValue='';}});
    // A quantity edited in the preview must also update the open full cart.
    document.addEventListener('cart:refreshed',function(e){
        if(busy()||failures.size)return;
        var data=e.detail, items=new Map(data.items.map(function(item){return [String(item.id),item];}));
        if(rows.some(function(row){return !items.has(row.dataset.productId);})){
            document.getElementById('shoppingCart').addEventListener('hidden.bs.offcanvas',function(){location.reload();},{once:true});
            return;
        }
        rows.forEach(function(row){var item=items.get(row.dataset.productId),input=row.querySelector('.cart-qty-input');input.value=item.quantity;input.dataset.saved=item.quantity;row.dataset.price=item.price;drawRow(row);});
        drawSelection(data.selection);
    });
    drawSelection(summary);
})();
