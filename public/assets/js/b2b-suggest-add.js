(function () {
    'use strict';
    function create(item) {
        var procurement = item.procurement, rules = procurement.order_rules || {}, orderRules = window.KotlovOrderRules;
        var busy = false, dirty = false, lastQuantity = -1;
        var root = document.createElement('div'); root.className = 'b2b-suggest-quick';
        root.setAttribute('role', 'group'); root.setAttribute('aria-label', 'Добавить в подбор: ' + item.name);
        var stepper = document.createElement('span'); stepper.className = 'b2b-suggest-stepper';
        var minus = document.createElement('button'); minus.type = 'button'; minus.textContent = '−'; minus.setAttribute('aria-label', 'Уменьшить количество: ' + item.name);
        var input = document.createElement('input'); input.type = 'number'; input.min = '1'; input.step = '1'; input.inputMode = 'numeric'; input.setAttribute('aria-label', 'Количество добавить: ' + item.name);
        var plus = document.createElement('button'); plus.type = 'button'; plus.textContent = '+'; plus.setAttribute('aria-label', 'Увеличить количество: ' + item.name);
        var add = document.createElement('button'); add.type = 'button'; add.className = 'b2b-suggest-add'; add.textContent = 'В подбор';
        var info = document.createElement('small'); info.className = 'b2b-suggest-quantity-note';
        var constraints = document.createElement('small'); constraints.className = 'b2b-suggest-rules'; constraints.textContent = orderRules.label({minimum:rules.minimum, multiple:rules.multiple, unit:rules.unit}); constraints.title = orderRules.label(rules); constraints.hidden = !constraints.textContent;
        var feedback = document.createElement('small'); feedback.className = 'b2b-suggest-feedback'; feedback.setAttribute('role', 'status'); feedback.setAttribute('aria-live', 'polite'); feedback.hidden = true;
        stepper.append(minus, input, plus); root.append(stepper, add, info, constraints, feedback);

        function currentLine() { return window.KotlovStaffWorkspace ? window.KotlovStaffWorkspace.line(item.id) : null; }
        function refresh() {
            var line = currentLine(), existing = line ? line.quantity : 0, amount;
            if (!dirty && !busy && existing !== lastQuantity) input.value = orderRules.additionQuantity(rules, existing, 1, 1) || '';
            lastQuantity = existing; amount = Number(input.value); input.max = Math.max(0, 999 - existing);
            var changedSupplier = line && (line.workspace_purchase ? line.workspace_purchase.supplier_key : null) !== procurement.supplier_key;
            var warning = !window.KotlovStaffWorkspace ? 'Рабочий подбор загружается…' : (changedSupplier ? 'Поставщик изменён. Обновите поиск.' : '');
            if (!warning) warning = !input.value || !Number.isInteger(amount) || amount < 1 || amount + existing > 999
                ? 'Укажите целое количество. Всего в позиции — не более 999.' : orderRules.warning(rules, existing + amount);
            info.textContent = warning || ((existing ? 'В подборе: ' + existing + ' · будет ' : 'Будет в подборе: ') + (existing + amount) + (rules.unit ? ' ' + rules.unit : ' шт.'));
            info.classList.toggle('is-warning', !!warning);
            add.disabled = busy || !!warning; input.disabled = busy;
            minus.disabled = busy || orderRules.additionQuantity(rules, existing, amount - 1, -1) === null;
            plus.disabled = busy || orderRules.additionQuantity(rules, existing, amount + 1, 1) === null;
            var saved = !feedback.hidden && !feedback.classList.contains('is-error');
            add.textContent = busy ? 'Сохраняем…' : ((saved ? '✓ ' : '') + (existing ? 'Добавить ещё' : 'В подбор'));
            add.classList.toggle('is-saved', saved);
        }
        [minus, plus].forEach(function (button, index) {
            button.addEventListener('click', function (event) {
                event.preventDefault(); event.stopPropagation();
                var line = currentLine(), direction = index ? 1 : -1;
                var amount = orderRules.additionQuantity(rules, line ? line.quantity : 0, Number(input.value) + direction, direction);
                if (amount !== null) input.value = amount;
                dirty = true; feedback.hidden = true; refresh();
            });
        });
        input.addEventListener('input', function () { dirty = true; feedback.hidden = true; refresh(); });
        input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); event.stopPropagation(); if (!add.disabled) add.click(); } });
        add.addEventListener('click', function (event) {
            event.preventDefault(); event.stopPropagation(); if (busy || add.disabled) return;
            var amount = Number(input.value); busy = true; feedback.hidden = true; refresh();
            window.KotlovStaffWorkspace.add(item.id, amount, procurement.supplier_key).then(function (line) {
                dirty = false; lastQuantity = -1;
                feedback.textContent = 'Сохранено · в подборе ' + line.quantity + (rules.unit ? ' ' + rules.unit : ' шт.');
                feedback.classList.remove('is-error'); feedback.hidden = false;
            }).catch(function (error) {
                feedback.textContent = error.message || 'Не удалось сохранить. Повторите добавление.';
                feedback.classList.add('is-error'); feedback.hidden = false;
            }).finally(function () { busy = false; refresh(); });
        });
        root.refreshSuggestion = refresh; refresh();
        return root;
    }
    document.addEventListener('kotlov:workspace-updated', function () {
        document.querySelectorAll('.b2b-suggest-quick').forEach(function (node) { node.refreshSuggestion(); });
    });
    window.KotlovSuggestionAdd = {create:create};
    document.dispatchEvent(new CustomEvent('kotlov:suggest-add-ready'));
})();
