(function () {
    'use strict';
    var root = document.getElementById('staff-workspace');
    if (!root) return;
    var state = JSON.parse(root.dataset.state);
    var queue = Promise.resolve();
    var pending = 0;
    var switcher = root.querySelector('[data-workspace-switch]');
    var dialog = document.getElementById('workspace-selection-dialog');
    var newDialog = document.getElementById('workspace-new-dialog');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var search = dialog.querySelector('[data-workspace-line-search]');
    var orderRules = window.KotlovOrderRules;

    function paintRules(box) {
        var rules = JSON.parse(box.dataset.orderRules || '{}');
        var label = box.querySelector('[data-workspace-order-rules]');
        var warning = box.querySelector('[data-workspace-rule-warning]');
        label.textContent = orderRules.label(rules); label.hidden = !label.textContent;
        warning.textContent = orderRules.warning(rules, Number(box.querySelector('input').value)); warning.hidden = !warning.textContent;
    }

    function money(value) {
        return Number(value || 0).toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' BYN';
    }

    function status(text, error) {
        document.querySelector('[data-workspace-status]').textContent = text;
        document.querySelector('[data-workspace-status]').classList.toggle('is-error', !!error);
        dialog.querySelector('[data-workspace-dialog-status]').textContent = text;
    }

    function request(url, method, data) {
        return fetch(url, {method: method, headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify(data)})
            .then(function (response) {
                return response.json().catch(function () { throw new Error('Ответ сервера не получен. Проверьте соединение и повторите сохранение.'); })
                    .then(function (body) {
                        if (!response.ok) throw new Error(body.message || 'Не удалось сохранить подбор. Повторите действие.');
                        return body;
                    });
            });
    }

    function drawLines() {
        var list = dialog.querySelector('[data-workspace-lines]');
        list.replaceChildren();
        var needle = search.value.trim().toLocaleLowerCase('ru');
        var items = state.items.filter(function (item) { return (item.name + ' ' + (item.sku || '')).toLocaleLowerCase('ru').includes(needle); });
        if (!items.length) {
            var empty = document.createElement('p');
            empty.textContent = state.items.length ? 'В подборе нет совпадений.' : 'Подбор пока пуст. Добавьте товары из каталога.';
            list.appendChild(empty);
        }
        items.forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'b2b-workspace-line';
            row.dataset.workspaceProduct = item.id;
            var copy = document.createElement('div');
            var name = document.createElement('strong');
            name.textContent = item.name;
            var sku = document.createElement('small');
            sku.textContent = (item.sku || 'Без артикула') + ' · ' + (item.price > 0 ? money(item.price) + ' / шт.' : 'Цена уточняется');
            copy.append(name, sku);
            if (item.order_rules_label) {
                var rules = document.createElement('small'); rules.className = 'b2b-workspace-rule-label'; rules.textContent = item.order_rules_label; copy.appendChild(rules);
            }
            if (item.order_rules_warning) {
                var ruleWarning = document.createElement('small'); ruleWarning.className = 'b2b-workspace-rule-warning'; ruleWarning.textContent = item.order_rules_warning; copy.appendChild(ruleWarning);
            }
            if (item.workspace_purchase) {
                var purchase = document.createElement('small');
                purchase.textContent = item.workspace_purchase.supplier + ' · входная ' + (item.workspace_purchase.purchase_price !== null ? money(item.workspace_purchase.purchase_price) : 'уточняется') + ' · ' + item.workspace_purchase.stock;
                copy.appendChild(purchase);
            }
            var input = document.createElement('input');
            input.type = 'number'; input.min = '0'; input.max = '999'; input.step = '1'; input.inputMode = 'numeric';
            input.value = item.quantity; input.dataset.workspaceQuantity = '';
            input.setAttribute('aria-label', 'Количество: ' + item.name);
            var total = document.createElement('span');
            total.textContent = item.price > 0 ? money(item.price * item.quantity) : 'Цена уточняется';
            var remove = document.createElement('button');
            remove.type = 'button'; remove.textContent = '×'; remove.dataset.workspaceRemove = '';
            remove.setAttribute('aria-label', 'Убрать из подбора: ' + item.name);
            row.append(copy, input, total, remove); list.appendChild(row);
        });
    }

    function render() {
        if (state.id) {
            // Keep category links, pagination and search on the newly created selection.
            var currentUrl = new URL(location.href);
            currentUrl.searchParams.set('selection_id', state.id);
            history.replaceState(null, '', currentUrl.toString());
            document.querySelectorAll('a[href]').forEach(function (link) {
                var url = new URL(link.href, location.href);
                if (url.origin === location.origin && url.pathname === currentUrl.pathname && url.searchParams.get('workspace') === '1') {
                    url.searchParams.set('selection_id', state.id); link.href = url.toString();
                }
            });
            var suggest = document.querySelector('.b2b-search');
            var suggestUrl = new URL(suggest.dataset.suggestUrl, location.href);
            suggestUrl.searchParams.set('selection_id', state.id); suggest.dataset.suggestUrl = suggestUrl.toString();
        }
        document.querySelector('[data-workspace-count]').textContent = state.items.length + ' поз. · ' + state.items.reduce(function (sum, item) { return sum + item.quantity; }, 0) + ' шт.';
        var unpriced = state.items.some(function (item) { return !(item.price > 0); });
        document.querySelectorAll('[data-workspace-total]').forEach(function (node) { node.textContent = money(state.total) + (unpriced ? ' + уточнение' : ''); });
        document.querySelectorAll('[data-workspace-procurement]').forEach(function (node) {
            var cost = state.procurement;
            node.textContent = cost ? 'Закупка ' + money(cost.total) + ' · Маржа ' + money(cost.margin) + (cost.margin_percent !== null ? ' / ' + cost.margin_percent.toLocaleString('ru-RU') + '%' : '') + (cost.unconfirmed ? ' · Без входной цены: ' + cost.unconfirmed + ' поз.' : '') + (cost.margin_unconfirmed ? ' · Маржа рассчитана по подтверждённым ценам' : '') : '';
            node.classList.toggle('is-negative', !!cost && cost.margin < 0);
            node.hidden = !state.items.length;
        });
        document.querySelectorAll('[data-workspace-print], [data-workspace-edit]').forEach(function (link) {
            var href = link.hasAttribute('data-workspace-print') ? state.print_url : state.edit_url;
            link.hidden = !href; if (href) link.href = href;
        });
        document.querySelectorAll('[data-workspace-desk]').forEach(function (link) { link.hidden = !state.desk_url; if (state.desk_url) link.href = state.desk_url; });
        document.querySelector('[data-workspace-title]').textContent = state.name;
        document.querySelectorAll('[data-workspace-selection-id]').forEach(function (input) { input.value = state.id || ''; });
        var found = Array.from(switcher.options).some(function (option) { return Number(option.value) === state.id; });
        if (state.id && !found) { var option = new Option(state.name, state.id); switcher.add(option); }
        switcher.value = state.id || '';
        document.querySelectorAll('.b2b-workspace-quantity').forEach(function (box) {
            var item = state.items.find(function (line) { return line.id === Number(box.dataset.workspaceProduct); });
            var button = box.querySelector('[data-workspace-save]');
            button.textContent = item ? '✓ В подборе' : 'В подбор';
            button.classList.toggle('is-selected', !!item);
            box.closest('.b2b-product-row').classList.toggle('is-in-cart', !!item);
            box.querySelector('[data-workspace-line-total]').textContent = item ? (item.price > 0 ? money(item.price * item.quantity) : 'Цена уточняется') : '';
            // Leave typed quantities intact while another row is being saved.
            if (item && document.activeElement !== box.querySelector('input') && !box.dataset.dirty) box.querySelector('input').value = item.quantity;
            paintRules(box);
        });
        if (dialog.open && !dialog.querySelector('[data-workspace-quantity]:focus')) drawLines();
    }

    function enqueue(task) {
        pending += 1;
        status('Сохраняем…');
        var job = queue.then(task);
        queue = job.catch(function (error) { status(error.message || 'Нет соединения. Повторите сохранение.', true); })
            .finally(function () { pending -= 1; });
        return job;
    }

    function ensureSelection() {
        if (state.id) return Promise.resolve();
        return request(root.dataset.createUrl, 'POST', {name: 'Подбор от ' + new Date().toLocaleDateString('ru-RU')})
            .then(function (data) { state = data; render(); });
    }

    function save(box, quantity) {
        var productId = Number(box.dataset.workspaceProduct);
        var supplier = document.querySelector('[data-workspace-offer][data-product-id="' + productId + '"]');
        var supplierKey = supplier ? supplier.value : null;
        if (!Number.isInteger(quantity) || quantity < 0 || quantity > 999) {
            status('Укажите целое количество от 0 до 999.', true);
            return Promise.reject(new Error('Некорректное количество'));
        }
        var button = box.querySelector('[data-workspace-save], [data-workspace-remove]');
        if (button) button.disabled = true;
        box.dataset.dirty = '1';
        return enqueue(function () {
            return ensureSelection().then(function () {
                var url = root.dataset.updateUrl.replace('__selection__', state.id).replace('__product__', productId);
                return request(url, 'PUT', {quantity: quantity, revision: state.revision, supplier_key: supplierKey});
            }).then(function (data) {
                state = data; delete box.dataset.dirty; render();
                status(pending > 1 ? 'Сохраняем…' : 'Сохранено ' + state.saved_at);
            });
        }).finally(function () { if (button) button.disabled = false; });
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('button');
        if (!target) return;
        if (target.hasAttribute('data-workspace-open')) { drawLines(); dialog.showModal(); return; }
        if (target.hasAttribute('data-workspace-new')) { newDialog.showModal(); return; }
        if (target.hasAttribute('data-dialog-close')) { target.closest('dialog').close(); return; }
        var box = target.closest('[data-workspace-product]');
        if (!box) return;
        var input = box.querySelector('[data-workspace-quantity]');
        if (target.hasAttribute('data-step')) {
            input.value = Math.min(999, Math.max(0, (Number(input.value) || 0) + Number(target.dataset.step)));
            if (box.classList.contains('b2b-workspace-quantity')) paintRules(box);
            box.dataset.dirty = '1';
            if (state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) save(box, Number(input.value)).catch(function () {});
        } else if (target.hasAttribute('data-workspace-save') || target.hasAttribute('data-workspace-remove')) {
            save(box, target.hasAttribute('data-workspace-remove') ? 0 : Number(input.value)).catch(function () {});
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-workspace-quantity]')) {
            var box = event.target.closest('[data-workspace-product]'); box.dataset.dirty = '1';
            if (box.classList.contains('b2b-workspace-quantity')) paintRules(box);
        }
    });
    // New positions require confirmation; quantities already in the selection save on blur.
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-workspace-offer]')) {
            var select = event.target;
            var option = select.selectedOptions[0];
            var row = select.closest('.b2b-product-row');
            var supplierCaption = row.querySelector('[data-workspace-supplier-caption]');
            supplierCaption.textContent = option.dataset.supplierName;
            supplierCaption.title = option.dataset.supplierName;
            row.querySelector('[data-workspace-stock]').textContent = option.dataset.stockLabel;
            row.querySelector('[data-workspace-stock]').classList.toggle('is-confirmed', option.dataset.confirmed === '1');
            row.querySelector('[data-workspace-cost]').textContent = option.dataset.cost !== '' ? money(option.dataset.cost) : 'Уточняется';
            row.querySelector('[data-workspace-margin]').textContent = option.dataset.margin !== '' ? money(option.dataset.margin) : 'Уточняется';
            row.querySelector('[data-workspace-margin-percent]').textContent = option.dataset.marginPercent !== '' ? Number(option.dataset.marginPercent).toLocaleString('ru-RU', {maximumFractionDigits:1}) + '%' : '';
            row.querySelector('[data-workspace-margin]').classList.toggle('is-negative', Number(option.dataset.margin) < 0);
            row.querySelector('[data-workspace-margin]').classList.toggle('is-unknown', option.dataset.margin === '');
            var box = row.querySelector('[data-workspace-product]');
            box.dataset.orderRules = option.dataset.orderRules || '{}';
            if (!box.dataset.dirty && !state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) box.querySelector('input').value = orderRules.initialQuantity(JSON.parse(box.dataset.orderRules));
            paintRules(box);
            if (state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) save(box, Number(box.querySelector('input').value)).catch(function () {});
            return;
        }
        if (!event.target.matches('.b2b-workspace-quantity [data-workspace-quantity]')) return;
        var box = event.target.closest('[data-workspace-product]');
        if (box.dataset.dirty && state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) save(box, Number(event.target.value)).catch(function () {});
    });
    dialog.addEventListener('change', function (event) {
        if (event.target.matches('[data-workspace-quantity]')) save(event.target.closest('[data-workspace-product]'), Number(event.target.value)).catch(function () {});
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.matches('.b2b-workspace-quantity [data-workspace-quantity]')) return;
        event.preventDefault();
        var input = event.target;
        var inputs = Array.from(document.querySelectorAll('.b2b-workspace-quantity [data-workspace-quantity]'));
        save(input.closest('[data-workspace-product]'), Number(input.value)).then(function () {
            var next = inputs[inputs.indexOf(input) + 1]; if (next) { next.focus(); next.select(); }
        }).catch(function () {});
    });
    search.addEventListener('input', drawLines);
    switcher.addEventListener('change', function () {
        if (pending) { switcher.value = state.id || ''; status('Дождитесь сохранения текущего изменения.', true); return; }
        var url = new URL(location.href); url.searchParams.set('selection_id', switcher.value); location.href = url.toString();
    });
    document.querySelector('[data-workspace-create]').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.target;
        var error = form.querySelector('[data-workspace-create-error]');
        var submit = form.querySelector('[type="submit"]');
        submit.disabled = true; error.hidden = true;
        enqueue(function () { return request(root.dataset.createUrl, 'POST', {name: form.elements.name.value.trim()})
            .then(function (data) { state = data; render(); status('Сохранено ' + state.saved_at); newDialog.close(); form.reset(); });
        }).catch(function (failure) { error.textContent = failure.message; error.hidden = false; })
            .finally(function () { submit.disabled = false; });
    });
    window.addEventListener('beforeunload', function (event) {
        var dirty = document.querySelector('[data-workspace-product][data-dirty]');
        if (pending || dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    render();
})();
