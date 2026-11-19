(function () {
    'use strict';
    // The client of the selection: what and at what price they bought before, in the rows and in «История».
    var pick = document.querySelector('[data-client-pick]');
    var dialog = document.getElementById('workspace-history-dialog');
    if (!pick || !dialog) return;
    var search = pick.querySelector('[data-client-search]');
    var results = pick.querySelector('[data-client-results]');
    var chosen = pick.querySelector('[data-client-chosen]');
    var history = null, loadedFor = null, timer = null;
    var workspace = function () { return window.KotlovStaffWorkspace; };

    function money(value) {
        return Number(value || 0).toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function quantity(value) { return Number(value).toLocaleString('ru-RU', {maximumFractionDigits: 3}); }

    function paintClient() {
        var state = workspace() && workspace().state();
        var client = state && state.client;
        chosen.hidden = !client; search.hidden = !!client;
        if (client) chosen.querySelector('[data-client-label]').textContent = client.label + (client.unp ? ' · УНП ' + client.unp : '');
        var key = client ? client.key + '@' + state.id : null;
        if (key !== loadedFor) { loadedFor = key; history = null; paintRows(); if (client) load(); }
    }

    function load() {
        var state = workspace().state();
        return fetch(state.history_url, {headers: {'Accept': 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) { history = data; paintRows(); return data; })
            .catch(function () { history = null; });
    }

    // «Прошлый раз: 92,00 · 12.10.2026 · № 123» under the price of the rows the client bought.
    function paintRows() {
        document.querySelectorAll('[data-row-product]').forEach(function (row) {
            var hint = row.querySelector('.b2b-history-hint');
            var bought = history && history.products && history.products[row.dataset.rowProduct];
            if (!bought) { if (hint) hint.remove(); return; }
            if (!hint) {
                hint = document.createElement('small'); hint.className = 'b2b-history-hint';
                (row.querySelector('.b2b-product-copy') || row).appendChild(hint);
            }
            hint.textContent = 'Прошлый раз: ' + money(bought.price) + ' BYN × ' + quantity(bought.quantity) + ' · ' + bought.date + (bought.number ? ' · № ' + bought.number : '') + (bought.times > 1 ? ' · брал ' + bought.times + ' раза' : '');
        });
    }

    function drawHistory() {
        var list = dialog.querySelector('[data-history-orders]');
        var statusNode = dialog.querySelector('[data-history-status]');
        list.replaceChildren();
        if (!history || !history.orders.length) { statusNode.textContent = 'У клиента нет заказов в базе сайта.'; return; }
        statusNode.textContent = 'Заказов: ' + history.orders.length;
        history.orders.forEach(function (order) {
            var box = document.createElement('section'); box.className = 'b2b-history-order';
            var head = document.createElement('header');
            var title = document.createElement('strong');
            title.textContent = (order.source === 'b2b' ? 'B2B‑заявка ' : 'Заказ ') + (order.number ? '№ ' + order.number : '') + ' · ' + order.date + ' · ' + money(order.total) + ' BYN';
            var repeat = document.createElement('button'); repeat.type = 'button'; repeat.textContent = 'Повторить заказ';
            var known = order.items.filter(function (item) { return item.product_id; });
            repeat.disabled = !known.length;
            repeat.addEventListener('click', function () { add(known, repeat, statusNode); });
            head.append(title, repeat); box.appendChild(head);
            order.items.forEach(function (item) {
                var line = document.createElement('div'); line.className = 'b2b-history-line';
                var name = document.createElement('span'); name.textContent = (item.sku ? item.sku + ' · ' : '') + item.name;
                var facts = document.createElement('span'); facts.textContent = quantity(item.quantity) + ' × ' + money(item.price);
                line.append(name, facts);
                if (item.product_id) {
                    var one = document.createElement('button'); one.type = 'button'; one.textContent = 'В подбор';
                    one.addEventListener('click', function () { add([item], one, statusNode); });
                    line.appendChild(one);
                } else {
                    line.appendChild(document.createElement('span')).textContent = 'нет в каталоге';
                }
                box.appendChild(line);
            });
            list.appendChild(box);
        });
    }

    // One by one, with the quantity of the old order; a refused item does not stop the rest.
    function add(items, button, statusNode) {
        var added = 0, failed = [];
        button.disabled = true;
        items.reduce(function (chain, item) {
            return chain.then(function () {
                var line = workspace().line(item.product_id);
                var units = Math.max(1, Math.min(999, Math.round(item.quantity)));
                return workspace().add(item.product_id, units, line && line.workspace_purchase ? line.workspace_purchase.supplier_key : null)
                    .then(function () { added++; })
                    .catch(function (error) { failed.push(item.name + ': ' + (error.message || 'не добавлено')); });
            });
        }, Promise.resolve()).then(function () {
            statusNode.textContent = 'Добавлено в подбор: ' + added + (failed.length ? ' · Не добавлено: ' + failed.join('; ') : '');
            button.disabled = false;
        });
    }

    search.addEventListener('input', function () {
        clearTimeout(timer);
        var query = search.value.trim();
        if (query.length < 2) { results.hidden = true; return; }
        timer = setTimeout(function () {
            var url = new URL(pick.dataset.clientsUrl, location.href); url.searchParams.set('q', query);
            fetch(url, {headers: {'Accept': 'application/json'}}).then(function (response) { return response.json(); }).then(function (data) {
                results.replaceChildren();
                (data.items.length ? data.items : [{key: '', label: 'Никого не нашли', unp: ''}]).forEach(function (client) {
                    var option = document.createElement('li'); option.setAttribute('role', 'option');
                    option.textContent = client.label + (client.unp ? ' · УНП ' + client.unp : '');
                    if (client.key) {
                        option.tabIndex = 0;
                        var choose = function () {
                            results.hidden = true; search.value = '';
                            workspace().setClient(client.key).catch(function () {});
                        };
                        option.addEventListener('click', choose);
                        option.addEventListener('keydown', function (event) { if (event.key === 'Enter') choose(); });
                    }
                    results.appendChild(option);
                });
                results.hidden = false;
            });
        }, 250);
    });
    pick.querySelector('[data-client-clear]').addEventListener('click', function () { workspace().setClient(null).catch(function () {}); });
    pick.querySelector('[data-client-history]').addEventListener('click', function () {
        dialog.showModal();
        dialog.querySelector('[data-history-status]').textContent = 'Загружаем…';
        (history ? Promise.resolve(history) : load()).then(drawHistory);
    });
    document.addEventListener('click', function (event) { if (!pick.contains(event.target)) results.hidden = true; });
    document.addEventListener('kotlov:workspace-updated', paintClient);
    paintClient();
})();
