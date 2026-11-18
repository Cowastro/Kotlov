(function () {
    'use strict';
    // «Вставить список»: lines from Excel or a letter → recognised rows → the manager confirms → the selection.
    var opener = document.querySelector('[data-workspace-paste-open]');
    var dialog = document.getElementById('workspace-paste-dialog');
    if (!opener || !dialog) return;
    var form = dialog.querySelector('[data-workspace-paste-form]');
    var list = dialog.querySelector('[data-workspace-paste-rows]');
    var footer = dialog.querySelector('[data-workspace-paste-footer]');
    var summary = dialog.querySelector('[data-workspace-paste-summary]');
    var addButton = dialog.querySelector('[data-workspace-paste-add]');
    var statusNode = dialog.querySelector('[data-workspace-paste-status]');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var rows = [];
    var labels = {found: 'Найдено', choose: 'Выберите вариант', none: 'Не найдено'};

    function money(value) {
        return value > 0 ? Number(value).toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' BYN' : 'цена уточняется';
    }

    function catalogUrl(term) {
        var url = new URL(location.href);
        url.searchParams.set('q', term); url.searchParams.delete('page'); url.searchParams.delete('category');
        return url.toString();
    }

    function paintSummary() {
        var chosen = rows.filter(function (row) { return row.select && row.select.value && !row.done; });
        var none = rows.filter(function (row) { return row.data.status === 'none'; }).length;
        summary.textContent = 'К добавлению: ' + chosen.length + ' из ' + rows.length + (none ? ' · Не найдено: ' + none : '');
        addButton.disabled = chosen.length === 0;
        footer.hidden = rows.length === 0;
    }

    function draw(data) {
        list.replaceChildren(); rows = [];
        data.forEach(function (row) {
            var box = document.createElement('div');
            box.className = 'b2b-paste-row is-' + row.status;
            var line = document.createElement('small'); line.textContent = row.line;
            var badge = document.createElement('b'); badge.textContent = labels[row.status] + (row.by === 'article' ? ' · по артикулу' : '');
            var head = document.createElement('div'); head.className = 'b2b-paste-row__head'; head.append(badge, line);
            box.appendChild(head);
            var entry = {data: row, box: box, select: null, quantity: null, done: false};
            var controls = document.createElement('div'); controls.className = 'b2b-paste-row__controls';
            if (row.candidates.length) {
                var select = document.createElement('select');
                select.setAttribute('aria-label', 'Товар для строки: ' + row.line);
                row.candidates.forEach(function (candidate) {
                    select.add(new Option((candidate.sku ? candidate.sku + ' · ' : '') + candidate.name + ' · ' + money(candidate.price), candidate.id));
                });
                select.add(new Option('— не добавлять', ''));
                select.addEventListener('change', paintSummary);
                entry.select = select; controls.appendChild(select);
            } else {
                var find = document.createElement('a');
                find.href = catalogUrl(row.term); find.target = '_blank'; find.rel = 'noopener';
                find.textContent = 'Найти в каталоге вручную →';
                controls.appendChild(find);
            }
            var quantity = document.createElement('input');
            quantity.type = 'number'; quantity.min = '1'; quantity.max = '999'; quantity.step = '1'; quantity.inputMode = 'numeric';
            quantity.value = row.quantity; quantity.setAttribute('aria-label', 'Количество: ' + row.line);
            if (!row.quantity_given) { quantity.classList.add('is-guess'); quantity.title = 'Количество в строке не указано — стоит 1'; }
            entry.quantity = quantity; controls.appendChild(quantity);
            var note = document.createElement('small'); note.className = 'b2b-paste-row__note'; entry.note = note;
            box.append(controls, note);
            list.appendChild(box); rows.push(entry);
        });
        paintSummary();
    }

    opener.addEventListener('click', function () { dialog.showModal(); form.elements.text.focus(); });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var submit = form.querySelector('[type="submit"]');
        submit.disabled = true; statusNode.textContent = 'Распознаём…';
        fetch(opener.dataset.pasteUrl, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({text: form.elements.text.value})})
            .then(function (response) { return response.json().then(function (body) { if (!response.ok) throw new Error(body.message || 'Не удалось распознать список.'); return body; }); })
            .then(function (body) {
                draw(body.rows);
                var lines = form.elements.text.value.split(/\r?\n/).filter(function (line) { return line.trim() !== ''; }).length;
                statusNode.textContent = body.rows.length ? 'Строк: ' + body.rows.length + (lines > body.limit ? ' · Обработаны первые ' + body.limit + ' строк' : '') : 'В тексте нет строк с товарами.';
            })
            .catch(function (error) { statusNode.textContent = error.message || 'Нет соединения. Повторите.'; })
            .finally(function () { submit.disabled = false; });
    });

    // One line after another: the selection keeps its revision order; a refused line does not stop the rest.
    addButton.addEventListener('click', function () {
        var workspace = window.KotlovStaffWorkspace;
        var queue = rows.filter(function (row) { return row.select && row.select.value && !row.done; });
        var added = 0, failed = 0;
        addButton.disabled = true;
        queue.reduce(function (chain, row) {
            return chain.then(function () {
                var id = Number(row.select.value), quantity = Number(row.quantity.value);
                var line = workspace.line(id);
                var supplierKey = line && line.workspace_purchase ? line.workspace_purchase.supplier_key : null;
                return workspace.add(id, quantity, supplierKey).then(function () {
                    row.done = true; added++;
                    row.box.classList.add('is-added'); row.note.textContent = '✓ Добавлено';
                    row.select.disabled = true; row.quantity.disabled = true;
                }).catch(function (error) {
                    failed++; row.box.classList.add('is-failed'); row.note.textContent = error.message || 'Не добавлено';
                });
            });
        }, Promise.resolve()).then(function () {
            statusNode.textContent = 'Добавлено в подбор: ' + added + (failed ? ' · Не добавлено: ' + failed + ' — причина у строки' : '');
            paintSummary();
        });
    });
})();
