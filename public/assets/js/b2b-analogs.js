(function () {
    'use strict';
    // «Аналоги» of a row: the same item of another series, brand or supplier; add it, or put it in place of the row.
    var dialog = document.getElementById('workspace-analogs-dialog');
    if (!dialog) return;
    var list = dialog.querySelector('[data-analogs-list]');
    var statusNode = dialog.querySelector('[data-analogs-status]');
    var sourceNode = dialog.querySelector('[data-analogs-source]');
    var workspace = function () { return window.KotlovStaffWorkspace; };

    function money(value) {
        return value !== null && value !== undefined && Number(value) > 0 ? Number(value).toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' BYN' : '—';
    }

    // Out of stock or not confirmed: the button asks to be used.
    function markNeeded() {
        document.querySelectorAll('.b2b-analogs-open').forEach(function (button) {
            var meter = button.closest('.b2b-product-row') && button.closest('.b2b-product-row').querySelector('[data-workspace-stock-meter]');
            button.classList.toggle('is-needed', !!meter && /is-(empty|unknown)/.test(meter.className));
        });
    }

    function cell(text, className) {
        var node = document.createElement('span'); node.textContent = text; if (className) node.className = className; return node;
    }

    function draw(data, sourceId) {
        list.replaceChildren();
        var line = workspace().line(sourceId);
        sourceNode.textContent = 'Вместо: ' + data.source.name + (line ? ' · в подборе ' + line.quantity + ' шт.' : '');
        if (!data.items.length) { statusNode.textContent = 'Похожих товаров того же раздела и размера не нашлось.'; return; }
        statusNode.textContent = '';
        data.items.forEach(function (item) {
            var row = document.createElement('article'); row.className = 'b2b-analog';
            var name = document.createElement('a'); name.href = item.url; name.target = '_blank'; name.rel = 'noopener'; name.textContent = item.name;
            var meta = document.createElement('small'); meta.textContent = [item.sku, item.brand].filter(Boolean).join(' · ');
            var matches = document.createElement('div'); matches.className = 'b2b-analog__matches';
            item.matches.forEach(function (match) { matches.appendChild(cell('✓ ' + match)); });
            var copy = document.createElement('div'); copy.append(name, meta, matches);
            var summary = item.summary || {};
            var facts = document.createElement('div'); facts.className = 'b2b-analog__facts';
            facts.append(cell('Розница ' + money(item.price)));
            if (item.summary) {
                facts.append(cell((summary.supplier || 'Поставщик не привязан') + ' · ' + (summary.stock_label || ''), 'is-' + (summary.stock_state || 'unknown')));
                facts.append(cell('Входная ' + money(summary.purchase_price) + (summary.margin !== null && summary.margin !== undefined ? ' · маржа ' + money(summary.margin) + (summary.margin_percent !== null ? ' / ' + Number(summary.margin_percent).toLocaleString('ru-RU') + '%' : '') : '')));
            }
            var actions = document.createElement('div'); actions.className = 'b2b-analog__actions';
            var add = document.createElement('button'); add.type = 'button'; add.textContent = 'В подбор';
            add.addEventListener('click', function () { act(item, summary, sourceId, false, add); });
            actions.appendChild(add);
            if (line) {
                var replace = document.createElement('button'); replace.type = 'button'; replace.className = 'is-primary'; replace.textContent = 'Заменить в подборе';
                replace.addEventListener('click', function () { act(item, summary, sourceId, true, replace); });
                actions.appendChild(replace);
            }
            row.append(copy, facts, actions);
            list.appendChild(row);
        });
    }

    // «Заменить»: the analog gets the quantity of the row, then the row leaves the selection.
    function act(item, summary, sourceId, replace, button) {
        var line = workspace().line(sourceId);
        var quantity = replace && line ? line.quantity : 1;
        var existing = workspace().line(item.id);
        var supplierKey = existing && existing.workspace_purchase ? existing.workspace_purchase.supplier_key : (summary.supplier_key || null);
        button.disabled = true;
        workspace().add(item.id, quantity, supplierKey)
            .then(function () { return replace ? workspace().set(sourceId, 0) : null; })
            .then(function () {
                statusNode.textContent = replace ? 'Заменено: ' + item.name + ' · ' + quantity + ' шт.' : 'Добавлено в подбор: ' + item.name;
                if (replace) list.querySelectorAll('.b2b-analog__actions .is-primary').forEach(function (other) { other.remove(); });
            })
            .catch(function (error) { statusNode.textContent = error.message || 'Не удалось изменить подбор.'; })
            .finally(function () { button.disabled = false; });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.b2b-analogs-open');
        if (!button) return;
        var sourceId = Number(button.dataset.productId);
        list.replaceChildren(); sourceNode.textContent = ''; statusNode.textContent = 'Ищем аналоги…';
        dialog.showModal();
        fetch(button.dataset.analogsUrl, {headers: {'Accept': 'application/json'}})
            .then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.message || 'Не удалось подобрать аналоги.'); return body; }); })
            .then(function (body) { draw(body, sourceId); })
            .catch(function (error) { statusNode.textContent = error.message || 'Нет соединения. Повторите.'; });
    });
    document.addEventListener('kotlov:workspace-updated', markNeeded);
    markNeeded();
})();
