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

    // Lowest acceptable margin, % of retail: kept in this browser, rows below it are highlighted.
    var thresholdInput = document.querySelector('[data-workspace-margin-threshold]');
    var thresholdKey = 'kotlov.b2b.marginThreshold';
    function threshold() {
        var value = thresholdInput ? parseFloat(thresholdInput.value) : NaN;
        return Number.isFinite(value) && value > 0 ? value : null;
    }
    function itemMarginPercent(item) {
        var cost = item.workspace_purchase && item.workspace_purchase.purchase_price;
        return cost !== null && cost !== undefined && item.price > 0 ? (item.price - cost) / item.price * 100 : null;
    }
    function paintMargins() {
        var limit = threshold();
        document.querySelectorAll('.b2b-workspace-margin[data-margin-percent]').forEach(function (box) {
            var percent = box.dataset.marginPercent === '' ? null : Number(box.dataset.marginPercent);
            var low = limit !== null && percent !== null && percent < limit;
            box.classList.toggle('is-low', low);
            box.closest('.b2b-product-row').classList.toggle('is-low-margin', low);
        });
    }
    if (thresholdInput) {
        try { thresholdInput.value = localStorage.getItem(thresholdKey) || ''; } catch (e) {}
        thresholdInput.addEventListener('input', function () {
            try { localStorage.setItem(thresholdKey, thresholdInput.value); } catch (e) {}
            render();
        });
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
            var limit = threshold();
            var low = limit === null ? 0 : state.items.filter(function (item) { var percent = itemMarginPercent(item); return percent !== null && percent < limit; }).length;
            if (cost && low) node.textContent += ' · Маржа ниже ' + limit.toLocaleString('ru-RU') + '%: ' + low + ' поз.';
            node.classList.toggle('is-negative', !!cost && (cost.margin < 0 || low > 0));
            node.hidden = !state.items.length;
        });
        document.querySelectorAll('[data-workspace-suppliers]').forEach(function (node) {
            var suppliers = (state.procurement && state.procurement.suppliers) || [];
            node.textContent = suppliers.map(function (supplier) {
                return supplier.name + ': ' + supplier.positions + ' поз.' + (supplier.purchase !== null ? ', закупка ' + money(supplier.purchase) : '') + (supplier.unconfirmed && supplier.purchase !== null ? ' + ' + supplier.unconfirmed + ' без цены' : '');
            }).join(' · ');
            node.hidden = !suppliers.length;
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
        // Diameter table: a cell shows the quantity of its item in the selection.
        document.querySelectorAll('.b2b-grid-cell').forEach(function (cell) {
            var item = state.items.find(function (line) { return line.id === Number(cell.dataset.workspaceProduct); });
            cell.classList.toggle('is-selected', !!item);
            var input = cell.querySelector('input');
            if (document.activeElement !== input && !cell.dataset.dirty) input.value = item ? item.quantity : '';
        });
        paintMargins();
        if (dialog.open && !dialog.querySelector('[data-workspace-quantity]:focus')) drawLines();
        document.dispatchEvent(new CustomEvent('kotlov:workspace-updated'));
    }

    function saveGridCell(input) {
        var cell = input.closest('.b2b-grid-cell');
        var quantity = input.value === '' ? 0 : Number(input.value);
        var known = state.items.some(function (item) { return item.id === Number(cell.dataset.workspaceProduct); });
        if (quantity === 0 && !known) { delete cell.dataset.dirty; return Promise.resolve(); }
        return save(cell, quantity);
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
            var stockIndicator = JSON.parse(option.dataset.stockIndicator);
            var stockMeter = row.querySelector('[data-workspace-stock-meter]');
            stockMeter.className = 'b2b-stock-meter is-' + stockIndicator.state;
            stockMeter.dataset.level = stockIndicator.level; stockMeter.title = stockIndicator.title;
            row.querySelector('[data-workspace-stock]').textContent = stockIndicator.label;
            row.querySelector('[data-workspace-stock]').classList.toggle('is-confirmed', option.dataset.confirmed === '1');
            row.querySelector('[data-workspace-cost]').textContent = option.dataset.cost !== '' ? money(option.dataset.cost).replace(' BYN', '') : 'Уточняется';
            row.querySelector('[data-workspace-margin]').textContent = option.dataset.margin !== '' ? money(option.dataset.margin).replace(' BYN', '') : 'Уточняется';
            row.querySelector('[data-workspace-margin-percent]').textContent = option.dataset.marginPercent !== '' ? Number(option.dataset.marginPercent).toLocaleString('ru-RU', {maximumFractionDigits:1}) + '%' : '';
            row.querySelector('[data-workspace-margin]').classList.toggle('is-negative', Number(option.dataset.margin) < 0);
            row.querySelector('[data-workspace-margin]').classList.toggle('is-unknown', option.dataset.margin === '');
            row.querySelector('.b2b-workspace-margin').dataset.marginPercent = option.dataset.marginPercent;
            paintMargins();
            var box = row.querySelector('[data-workspace-product]');
            box.dataset.orderRules = option.dataset.orderRules || '{}';
            if (!box.dataset.dirty && !state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) box.querySelector('input').value = orderRules.initialQuantity(JSON.parse(box.dataset.orderRules));
            paintRules(box);
            if (state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) save(box, Number(box.querySelector('input').value)).catch(function () {});
            return;
        }
        if (event.target.matches('.b2b-grid-cell [data-workspace-quantity]')) { saveGridCell(event.target).catch(function () {}); return; }
        if (!event.target.matches('.b2b-workspace-quantity [data-workspace-quantity]')) return;
        var box = event.target.closest('[data-workspace-product]');
        if (box.dataset.dirty && state.items.some(function (item) { return item.id === Number(box.dataset.workspaceProduct); })) save(box, Number(event.target.value)).catch(function () {});
    });
    dialog.addEventListener('change', function (event) {
        if (event.target.matches('[data-workspace-quantity]')) save(event.target.closest('[data-workspace-product]'), Number(event.target.value)).catch(function () {});
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.matches('.b2b-grid-cell [data-workspace-quantity]')) {
            event.preventDefault();
            var cells = Array.from(document.querySelectorAll('.b2b-grid-cell [data-workspace-quantity]'));
            var next = cells[cells.indexOf(event.target) + 1];
            // Leaving the field fires «change», which saves the cell.
            if (next) { next.focus(); next.select(); } else { event.target.blur(); }
            return;
        }
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
    window.KotlovStaffWorkspace = {
        line: function (productId) { return state.items.find(function (item) { return item.id === productId; }); },
        add: function (productId, quantity, supplierKey) {
            if (!Number.isInteger(quantity) || quantity < 1 || quantity > 999) return Promise.reject(new Error('Укажите целое количество от 1 до 999.'));
            return enqueue(function () {
                return ensureSelection().then(function () {
                    var url = root.dataset.updateUrl.replace('__selection__', state.id).replace('__product__', productId);
                    return request(url, 'PUT', {quantity:quantity, revision:state.revision, supplier_key:supplierKey, operation:'add'});
                }).then(function (data) {
                    state = data; render(); status(pending > 1 ? 'Сохраняем…' : 'Сохранено ' + state.saved_at);
                    return state.items.find(function (item) { return item.id === productId; });
                });
            });
        },
    };
    render();
})();
