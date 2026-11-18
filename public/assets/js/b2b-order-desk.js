(function () {
    'use strict';
    var root = document.querySelector('[data-order-desk]'); if (!root) return;
    var state = JSON.parse(root.dataset.state), busy = false, selectedOnly = false, action = 'copy', availabilityFilter = 'all', submitting = false;
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var rows = Array.from(root.querySelectorAll('[data-desk-row]'));
    var dialog = document.getElementById('desk-transfer-dialog');
    var targetSelect = dialog.querySelector('select');
    var lineDialog = document.getElementById('desk-line-dialog');
    var lineForm = lineDialog.querySelector('form');
    var pricingControls = lineDialog.querySelector('[data-desk-client-pricing]');
    function paintClientPrice() {
        if (!pricingControls) return;
        var item = state.items.find(function (entry) { return entry.id === Number(lineForm.elements.product_id.value); });
        if (!item) return;
        var mode = lineForm.elements.pricing_mode.value;
        lineDialog.querySelector('[data-desk-client-price-field]').hidden = mode !== 'price';
        lineDialog.querySelector('[data-desk-discount-field]').hidden = mode !== 'discount';
        lineForm.elements.client_price.disabled = busy || mode !== 'price'; lineForm.elements.client_price.required = mode === 'price';
        lineForm.elements.discount_percent.disabled = busy || mode !== 'discount'; lineForm.elements.discount_percent.required = mode === 'discount';
        var quote = window.KotlovClientPricing.quote(item, mode, lineForm.elements.client_price.value, lineForm.elements.discount_percent.value, Number(lineForm.elements.quantity.value));
        lineDialog.querySelector('[data-desk-base-price]').textContent = quote.base > 0 ? 'Исходная цена: ' + money(quote.base) : 'Исходная цена не указана';
        var purchase = item.workspace_purchase && item.workspace_purchase.purchase_price;
        lineDialog.querySelector('[data-desk-purchase-price]').textContent = purchase !== null && purchase !== undefined ? 'Входная цена: ' + money(purchase) : 'Входная цена уточняется';
        lineDialog.querySelector('[data-desk-preview-price]').textContent = quote.valid ? money(quote.price) : 'Уточняется';
        lineDialog.querySelector('[data-desk-preview-total]').textContent = quote.valid ? money(quote.total) : 'Уточняется';
        var margin = lineDialog.querySelector('[data-desk-preview-margin]');
        margin.textContent = !quote.valid ? 'Укажите корректную цену и количество' : (quote.margin !== null ? money(quote.margin) + ' / ' + quote.marginPercent.toLocaleString('ru-RU') + '%' : 'Входная цена не указана');
        margin.classList.toggle('is-negative', quote.belowCost);
        var warning = lineDialog.querySelector('[data-desk-preview-warning]');
        warning.hidden = !quote.belowCost; warning.textContent = quote.belowCost ? 'Цена для клиента ниже входной. Маржа отрицательная.' : '';
    }
    var errorBox = root.querySelector('[data-desk-error]');
    function money(value) { return Number(value || 0).toLocaleString('ru-RU', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' BYN'; }
    function chosen() { return state.items.filter(function (item) { return !state.excluded.includes(item.id); }); }
    function normalized(value) { return String(value || '').normalize('NFKC').toLocaleLowerCase('ru').replace(/ё/g, 'е').replace(/[^\p{L}\p{N}]/gu, ''); }
    function status(text) { root.querySelector('[data-desk-status]').textContent = text; }
    function syncExportScope() {
        var scope = root.querySelector('[data-desk-export-scope]').value;
        root.querySelectorAll('[data-desk-export]').forEach(function (link) { var url = new URL(link.href); url.searchParams.set('scope', scope); link.href = url.toString(); });
        paintExportSummary();
    }
    function paintExportSummary() {
        var scope = root.querySelector('[data-desk-export-scope]').value, positions = 0, quantity = 0, total = 0, unchecked = 0, unpriced = false;
        chosen().forEach(function (item) {
            if (scope !== 'all' && !item.allocation) { unchecked++; return; }
            var units = scope === 'all' ? item.quantity : item.allocation[scope];
            if (!(units > 0)) return;
            positions++; quantity += units; total += Number(item.price || 0) * units;
            if (!(item.price > 0)) unpriced = true;
        });
        root.querySelector('[data-desk-export-summary]').textContent = positions ? 'В выгрузке: ' + positions + ' поз. · ' + quantity + ' шт. · ' + money(total) + (unpriced ? ' + уточнение цены' : '') + (unchecked ? ' · Не проверено ' + unchecked + ' поз.' : '') : 'Нет подходящих отмеченных позиций. Проверьте наличие и выбор товаров.';
    }
    function visibleRows() {
        var tokens = root.querySelector('[data-desk-search]').value.trim().split(/\s+/).map(normalized).filter(Boolean);
        var visible = 0;
        rows.forEach(function (row) {
            var item = state.items.find(function (line) { return line.id === Number(row.dataset.deskRow); });
            var searchable = item ? normalized([item.name, item.sku, item.source_name, item.supplier_article, item.workspace_purchase && item.workspace_purchase.supplier, item.workspace_purchase && item.workspace_purchase.article].filter(Boolean).join(' ')) : '';
            var match = item && (!selectedOnly || !state.excluded.includes(item.id)) && tokens.every(function (token) { return searchable.includes(token); });
            if (match && availabilityFilter !== 'all') {
                match = availabilityFilter === 'unchecked' ? !item.allocation : Boolean(item.allocation && item.allocation[availabilityFilter] > 0);
            }
            row.hidden = !match; if (match) visible++;
        });
        root.querySelector('[data-desk-found]').textContent = visible + ' из ' + state.items.length;
        root.querySelector('[data-desk-empty]').hidden = visible > 0;
        var shown = rows.filter(function (row) { return !row.hidden; });
        var picked = shown.filter(function (row) { return !state.excluded.includes(Number(row.dataset.deskRow)); }).length;
        var all = root.querySelector('[data-desk-check-all]');
        all.checked = shown.length > 0 && picked === shown.length; all.indeterminate = picked > 0 && picked < shown.length;
        root.querySelector('[data-desk-reset]').hidden = tokens.length === 0 && availabilityFilter === 'all' && !selectedOnly;
    }
    function paint() {
        var items = chosen(), total = 0, cost = 0, margin = 0, marginRetail = 0, unknown = 0;
        items.forEach(function (item) {
            total += item.price * item.quantity;
            if (item.workspace_purchase && item.workspace_purchase.purchase_price !== null) {
                cost += item.workspace_purchase.purchase_price * item.quantity;
                if (item.price > 0) { margin += (item.price - item.workspace_purchase.purchase_price) * item.quantity; marginRetail += item.price * item.quantity; }
                else unknown++;
            } else unknown++;
        });
        root.querySelector('[data-desk-count]').textContent = items.length + ' из ' + state.items.length + ' позиций';
        root.querySelector('[data-desk-total]').textContent = money(total) + (items.some(function (item) { return !(item.price > 0); }) ? ' + уточнение' : '');
        root.querySelector('[data-desk-margin]').textContent = state.procurement ? 'Закупка ' + money(cost) + ' · Маржа ' + money(margin) + (marginRetail > 0 ? ' / ' + (margin / marginRetail * 100).toLocaleString('ru-RU', {maximumFractionDigits:1}) + '%' : '') + (unknown ? ' · По подтверждённым ценам; уточнить ' + unknown + ' поз.' : '') : '';
        root.querySelector('[data-desk-title]').textContent = state.name;
        var totals = {ready:0, waiting:0, checked:0};
        state.items.forEach(function (item) { if (item.allocation) { totals.checked++; totals.ready += item.allocation.ready; totals.waiting += item.allocation.waiting; } });
        root.querySelector('[data-desk-stock-summary]').textContent = totals.checked ? 'Проверено ' + totals.checked + ' из ' + state.items.length + ' поз. · Доступно ' + totals.ready + ' шт. · Ожидается ' + totals.waiting + ' шт. По сохранённой проверке.' : 'Наличие ещё не проверено. Нажмите «Проверить наличие», чтобы увидеть доступное и недостающее количество.';
        var tabLabels = {all:'Все', ready:'Доступное', waiting:'Ожидаемые', unchecked:'Не проверены'};
        root.querySelectorAll('[data-desk-availability]').forEach(function (button) {
            var scope = button.dataset.deskAvailability;
            var count = state.items.filter(function (item) { return scope === 'all' || (scope === 'unchecked' ? !item.allocation : item.allocation && item.allocation[scope] > 0); }).length;
            button.textContent = tabLabels[scope] + ' (' + count + ')';
        });
        root.querySelectorAll('[data-desk-revision]').forEach(function (input) { input.value = state.revision; });
        var all = root.querySelector('[data-desk-check-all]'); all.checked = state.items.length > 0 && items.length === state.items.length; all.indeterminate = items.length > 0 && items.length < state.items.length;
        root.querySelector('[data-desk-undo]').hidden = !state.undo_available;
        rows.forEach(function (row) {
            var item = state.items.find(function (line) { return line.id === Number(row.dataset.deskRow); }); if (!item) { row.hidden = true; return; }
            row.querySelector('[data-desk-check]').checked = !state.excluded.includes(item.id);
            row.classList.toggle('is-postponed', state.excluded.includes(item.id));
            row.querySelector('[data-desk-quantity]').value = item.quantity;
            row.querySelector('[data-desk-unit-price]').textContent = item.price > 0 ? money(item.price).replace(' BYN', '') : 'Уточняется';
            row.querySelector('[data-desk-price-label]').textContent = item.client_pricing ? 'Цена для клиента' + (item.client_pricing.mode === 'discount' && item.client_pricing.discount_percent > 0 ? ' · −' + Number(item.client_pricing.discount_percent).toLocaleString('ru-RU', {maximumFractionDigits:2}) + '%' : '') : (item.pricing_type === 'b2b' ? 'Ваша B2B-цена' : 'Цена сайта');
            row.querySelector('[data-desk-working-note]').hidden = !item.workspace_note;
            row.querySelector('[data-desk-working-note-text]').textContent = item.workspace_note || '';
            row.querySelector('[data-desk-line-total]').textContent = item.price > 0 ? money(item.price * item.quantity) : 'Уточняется';
            var warning = row.querySelector('[data-desk-stock-warning]');
            if (warning) warning.hidden = item.quantity <= item.workspace_purchase.stock_quantity;
            var allocation = row.querySelector('[data-desk-allocation]');
            if (allocation) allocation.textContent = item.allocation ? 'Доступно ' + item.allocation.ready + ' · Ожидается ' + item.allocation.waiting + (item.allocation.unknown ? ' · остаток уточняется' : '') + ' · не резерв' : 'Наличие ещё не проверено';
        });
        visibleRows();
        paintExportSummary();
        root.querySelectorAll('[data-desk-remove], [data-desk-transfer]').forEach(function (button) { button.disabled = busy || chosen().length === 0; });
    }
    function lock(value) {
        busy = value;
        root.querySelectorAll('button, [data-desk-check], [data-desk-check-all], [data-desk-quantity]').forEach(function (node) { node.disabled = value || (node.matches('[data-desk-remove], [data-desk-transfer]') && chosen().length === 0); });
        lineDialog.querySelectorAll('button,input,textarea,select').forEach(function (node) { node.disabled = value; });
        paintClientPrice();
        dialog.querySelector('[type="submit"]').disabled = value;
        root.querySelectorAll('[data-desk-export]').forEach(function (node) { node.setAttribute('aria-disabled', value ? 'true' : 'false'); });
    }
    function mutate(payload) {
        if (busy) return Promise.reject(new Error('Дождитесь сохранения.'));
        errorBox.hidden = true; status('Сохраняем…'); lock(true);
        return fetch(root.dataset.updateUrl, {method:'PATCH', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(Object.assign({revision:state.revision}, payload))})
            .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'Не удалось сохранить изменения.'); return data; }); })
            .then(function (data) {
                state = data.state; paint(); status('Сохранено ' + state.saved_at);
                // After a refresh, deleted rows have no DOM left to unhide on undo.
                if (state.items.some(function (item) { return !rows.some(function (row) { return Number(row.dataset.deskRow) === item.id; }); })) { submitting = true; location.reload(); }
                if (data.target) {
                    var option = Array.from(targetSelect.options).find(function (entry) { return Number(entry.value) === data.target.id; });
                    if (!option) { option = new Option(data.target.name, data.target.id); targetSelect.add(option); }
                    option.dataset.revision = data.target.revision;
                    var link = document.createElement('a'); link.href = data.target.url; link.textContent = ' Открыть «' + data.target.name + '» →';
                    root.querySelector('[data-desk-status]').appendChild(link);
                }
                return data;
            }).catch(function (error) {
                errorBox.textContent = error.message || 'Нет соединения. Повторите сохранение.'; errorBox.hidden = false; status('Изменение не сохранено');
                paint(); throw error;
            }).finally(function () { lock(false); });
    }
    root.querySelector('[data-desk-search]').addEventListener('input', visibleRows);
    root.querySelector('[data-desk-sort]').addEventListener('change', function (event) {
        var by = event.target.value;
        var ordered = state.items.slice().sort(function (a,b) {
            if (by === 'quantity') return b.quantity - a.quantity;
            if (by === 'total') return b.price*b.quantity - a.price*a.quantity;
            return String(a[by] || '').localeCompare(String(b[by] || ''), 'ru', {numeric:true});
        });
        var parent = root.querySelector('[data-desk-rows]'); ordered.forEach(function (item) { var row = rows.find(function (entry) { return Number(entry.dataset.deskRow) === item.id; }); if (row) parent.appendChild(row); });
    });
    root.addEventListener('change', function (event) {
        var input = event.target;
        if (input.matches('[data-desk-check], [data-desk-check-all]')) {
            var ids;
            if (input.hasAttribute('data-desk-check-all')) {
                var shownIds = rows.filter(function (row) { return !row.hidden; }).map(function (row) { return Number(row.dataset.deskRow); });
                ids = chosen().map(function (item) { return item.id; }).filter(function (id) { return !shownIds.includes(id); });
                if (input.checked) ids = ids.concat(shownIds);
            } else ids = rows.filter(function (row) { return state.items.some(function (item) { return item.id === Number(row.dataset.deskRow); }) && row.querySelector('[data-desk-check]').checked; }).map(function (row) { return Number(row.dataset.deskRow); });
            mutate({action:'select', ids:ids}).catch(function () {});
        }
        if (input.matches('[data-desk-quantity]')) saveQuantity(input);
    });
    function saveQuantity(input, advance) {
        var value = Number(input.value), id = Number(input.closest('[data-desk-row]').dataset.deskRow);
        if (!Number.isInteger(value) || value < 1 || value > 999) { input.value = state.items.find(function (item) { return item.id === id; }).quantity; errorBox.textContent = 'Количество: целое число от 1 до 999.'; errorBox.hidden = false; return; }
        if (busy) return;
        mutate({action:'quantity', product_id:id, quantity:value}).then(function () {
            if (advance) { var visible = Array.from(root.querySelectorAll('[data-desk-row]:not([hidden]) [data-desk-quantity]')); var next = visible[visible.indexOf(input)+1]; if (next) { next.focus(); next.select(); } }
        }).catch(function () {});
    }
    root.addEventListener('keydown', function (event) { if (event.key === 'Enter' && event.target.matches('[data-desk-quantity]')) { event.preventDefault(); saveQuantity(event.target, true); } });
    root.addEventListener('click', function (event) {
        var button = event.target.closest('button'); if (!button || busy) return;
        if (button.hasAttribute('data-desk-line-remove')) {
            mutate({action:'remove', ids:[Number(button.closest('[data-desk-row]').dataset.deskRow)]}).catch(function () {});
        }
        if (button.hasAttribute('data-desk-line-edit') || button.hasAttribute('data-desk-price-edit')) {
            var item = state.items.find(function (entry) { return entry.id === Number(button.closest('[data-desk-row]').dataset.deskRow); });
            lineForm.elements.product_id.value = item.id; lineForm.elements.quantity.value = item.quantity;
            lineForm.elements.workspace_note.value = item.workspace_note || '';
            if (pricingControls) {
                lineForm.elements.pricing_mode.value = button.hasAttribute('data-desk-price-edit') ? 'price' : 'keep';
                lineForm.elements.client_price.value = item.price > 0 ? Number(item.price).toFixed(2) : '';
                lineForm.elements.discount_percent.value = item.client_pricing && item.client_pricing.mode === 'discount' ? item.client_pricing.discount_percent : '0';
                paintClientPrice();
            }
            lineDialog.querySelector('[data-desk-edit-name]').textContent = item.name;
            lineDialog.querySelector('[data-desk-line-error]').hidden = true;
            var catalogLink = lineDialog.querySelector('[data-desk-edit-supplier]');
            if (catalogLink) { var catalogUrl = new URL(catalogLink.href); catalogUrl.searchParams.set('q', item.sku || item.name); catalogUrl.searchParams.delete('category'); catalogUrl.searchParams.delete('page'); catalogLink.href = catalogUrl.toString(); }
            lineDialog.showModal();
            var focusInput = pricingControls && button.hasAttribute('data-desk-price-edit') ? lineForm.elements.client_price : lineForm.elements.quantity;
            focusInput.focus(); focusInput.select();
        }
        if (button.hasAttribute('data-desk-reset')) {
            root.querySelector('[data-desk-search]').value = ''; selectedOnly = false; availabilityFilter = 'all';
            root.querySelector('[data-desk-filter-selected]').setAttribute('aria-pressed', 'false');
            root.querySelectorAll('[data-desk-availability]').forEach(function (entry) { entry.setAttribute('aria-pressed', String(entry.dataset.deskAvailability === 'all')); });
            root.querySelector('.desk-availability-tabs').scrollLeft = 0;
            visibleRows();
        }
        if (button.hasAttribute('data-desk-availability')) {
            availabilityFilter = button.dataset.deskAvailability;
            root.querySelectorAll('[data-desk-availability]').forEach(function (entry) { entry.setAttribute('aria-pressed', String(entry === button)); });
            visibleRows();
        }
        if (button.hasAttribute('data-desk-step')) { var input = button.closest('[data-desk-row]').querySelector('[data-desk-quantity]'); input.value = Math.min(999, Math.max(1, Number(input.value) + Number(button.dataset.deskStep))); saveQuantity(input); }
        if (button.hasAttribute('data-desk-filter-selected')) { selectedOnly = !selectedOnly; button.setAttribute('aria-pressed', String(selectedOnly)); visibleRows(); }
        if (button.hasAttribute('data-desk-select-found')) mutate({action:'select', ids:rows.filter(function (row) { return !row.hidden; }).map(function (row) { return Number(row.dataset.deskRow); })}).catch(function () {});
        if (button.hasAttribute('data-desk-remove')) mutate({action:'remove', ids:chosen().map(function (item) { return item.id; })}).catch(function () {});
        if (button.hasAttribute('data-desk-undo')) mutate({action:'undo'}).catch(function () {});
        if (button.hasAttribute('data-desk-transfer')) { action = button.dataset.deskTransfer; dialog.querySelector('h2').textContent = action === 'move' ? 'Перенести отмеченные' : 'Копировать отмеченные'; dialog.showModal(); }
    });
    root.querySelector('[data-desk-details]').addEventListener('submit', function (event) { event.preventDefault(); mutate({action:'details', name:event.target.elements.name.value, note:event.target.elements.note.value}).catch(function () {}); });
    function saveLine(nextUrl) {
        if (busy || !lineForm.reportValidity()) return;
        var error = lineDialog.querySelector('[data-desk-line-error]'); error.hidden = true;
        var payload = {action:'line', product_id:Number(lineForm.elements.product_id.value), quantity:Number(lineForm.elements.quantity.value), workspace_note:lineForm.elements.workspace_note.value};
        if (pricingControls && lineForm.elements.pricing_mode.value !== 'keep') {
            payload.pricing_mode = lineForm.elements.pricing_mode.value;
            if (payload.pricing_mode === 'price') payload.client_price = lineForm.elements.client_price.value;
            if (payload.pricing_mode === 'discount') payload.discount_percent = lineForm.elements.discount_percent.value;
        }
        mutate(payload)
            .then(function () { lineDialog.close(); if (nextUrl) location.assign(nextUrl); })
            .catch(function (failure) { error.textContent = failure.message; error.hidden = false; });
    }
    lineForm.addEventListener('submit', function (event) { event.preventDefault(); saveLine(); });
    lineForm.addEventListener('input', paintClientPrice);
    lineForm.addEventListener('change', paintClientPrice);
    lineDialog.querySelectorAll('[data-desk-line-close]').forEach(function (button) { button.addEventListener('click', function () { if (!busy) lineDialog.close(); }); });
    lineDialog.addEventListener('cancel', function (event) { if (busy) event.preventDefault(); });
    var supplierEdit = lineDialog.querySelector('[data-desk-edit-supplier]');
    if (supplierEdit) supplierEdit.addEventListener('click', function (event) { event.preventDefault(); saveLine(supplierEdit.href); });
    dialog.querySelector('[data-desk-close]').addEventListener('click', function () { dialog.close(); });
    targetSelect.addEventListener('change', function () { var fresh = targetSelect.value === ''; dialog.querySelector('[data-desk-new-target]').hidden = !fresh; dialog.querySelector('input[name="name"]').required = fresh; });
    dialog.querySelector('form').addEventListener('submit', function (event) {
        event.preventDefault(); var form = event.target, error = form.querySelector('[data-desk-transfer-error]'); error.hidden = true;
        mutate({action:action, ids:chosen().map(function (item) { return item.id; }), target_id:targetSelect.value || null, target_revision:targetSelect.value ? Number(targetSelect.selectedOptions[0].dataset.revision) : null, name:form.elements.name.value})
            .then(function () { dialog.close(); }).catch(function (failure) { error.textContent = failure.message; error.hidden = false; });
    });
    root.querySelectorAll('[data-desk-export]').forEach(function (link) { link.addEventListener('click', function (event) { if (busy) { event.preventDefault(); status('Дождитесь сохранения перед экспортом.'); } }); });
    root.querySelector('[data-desk-export-scope]').addEventListener('change', syncExportScope);
    root.querySelectorAll('.desk-fulfillment form, [data-desk-publish]').forEach(function (form) { form.addEventListener('submit', function (event) {
        if (busy) { event.preventDefault(); status('Дождитесь сохранения перед проверкой или оформлением.'); return; }
        root.querySelectorAll('[data-desk-revision]').forEach(function (input) { input.value = state.revision; });
        status('Проверяем актуальные остатки…'); submitting = true; lock(true);
    }); });
    window.addEventListener('beforeunload', function (event) { if (busy && !submitting) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pageshow', function () { if (submitting) { submitting = false; lock(false); } syncExportScope(); });
    paint(); syncExportScope();
})();
