(function () {
    'use strict';

    var root = document.getElementById('ai-product-selector');
    var modalElement = document.getElementById('aiSelectorModal');
    if (!root || !modalElement || !window.bootstrap) return;

    var modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var intentInput = document.getElementById('aiSelectorIntent');
    var state = { intent: '', category: null, known: {}, questions: [] };
    var roleLabels = { optimal: 'Оптимальный', economy: 'Экономичный', reserve: 'Альтернатива' };

    function all(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }
    function one(selector, context) { return (context || document).querySelector(selector); }
    function setHidden(element, hidden) { if (element) element.hidden = hidden; }

    function showStep(name) {
        all('[data-ai-selector-step]', modalElement).forEach(function (step) {
            step.hidden = step.getAttribute('data-ai-selector-step') !== name;
        });
        setHidden(one('[data-ai-selector-loading]', modalElement), true);
        var modalBody = one('.modal-body', modalElement);
        if (modalBody) modalBody.scrollTop = 0;
    }

    function showError(message, context) {
        var error = one('[data-ai-selector-error]', context || modalElement);
        if (!error) return;
        error.textContent = message;
        error.hidden = !message;
    }

    function loading(active) {
        setHidden(one('[data-ai-selector-loading]', modalElement), !active);
        all('button, input, textarea, select', modalElement).forEach(function (field) {
            field.disabled = active;
        });
    }

    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.content : ''
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (!response.ok) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    var firstError = data.errors && Object.values(data.errors)[0];
                    throw new Error(Array.isArray(firstError) ? firstError[0] : 'Не удалось выполнить подбор. Попробуйте ещё раз.');
                });
            }
            return response.json();
        });
    }

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    }

    function renderQuestions(data) {
        state.category = data.category;
        state.known = data.known || {};
        state.questions = data.questions || [];
        one('[data-ai-selector-category]', modalElement).textContent = data.category_label || 'Уточним параметры';
        one('[data-ai-selector-summary]', modalElement).textContent = data.summary || 'Ответьте только на известные вам вопросы — неизвестные значения можно пропустить.';
        var host = one('[data-ai-selector-questions]', modalElement);
        host.replaceChildren();

        state.questions.forEach(function (question) {
            var group = make('div', 'ai-selector__field');
            var label = make('label', 'ai-selector__label', question.label);
            label.setAttribute('for', 'ai-question-' + question.key);
            group.appendChild(label);
            var field;
            if (question.type === 'choice') {
                field = make('select', 'ai-selector__input');
                var blank = make('option', '', 'Не выбрано');
                blank.value = '';
                field.appendChild(blank);
                (question.options || []).forEach(function (option) {
                    var item = make('option', '', option);
                    item.value = option;
                    field.appendChild(item);
                });
            } else {
                field = make(question.type === 'text' ? 'textarea' : 'input', 'ai-selector__input');
                if (question.type !== 'text') field.type = question.type || 'text';
                if (question.type === 'number') { field.min = '0'; field.step = 'any'; }
                field.placeholder = question.placeholder || '';
            }
            field.id = 'ai-question-' + question.key;
            field.name = question.key;
            field.maxLength = 200;
            group.appendChild(field);
            host.appendChild(group);
        });
        showStep('questions');
    }

    function addList(card, title, items, className) {
        if (!Array.isArray(items) || !items.length) return;
        var block = make('div', className);
        block.appendChild(make('strong', '', title));
        var list = make('ul');
        items.forEach(function (item) { list.appendChild(make('li', '', item)); });
        block.appendChild(list);
        card.appendChild(block);
    }

    function renderResults(data) {
        one('[data-ai-selector-result-summary]', modalElement).textContent = data.summary || '';
        var host = one('[data-ai-selector-results]', modalElement);
        host.replaceChildren();
        if (!Array.isArray(data.products) || !data.products.length) {
            host.appendChild(make('div', 'ai-selector__empty', 'Готовых вариантов не найдено. Передайте задачу специалисту — он проверит каталог и поставщиков вручную.'));
            showStep('results');
            return;
        }

        data.products.forEach(function (product) {
            var card = make('article', 'ai-selector-product');
            card.appendChild(make('span', 'ai-selector-product__role', roleLabels[product.role] || roleLabels.optimal));
            var top = make('div', 'ai-selector-product__top');
            var image = document.createElement('img');
            image.src = product.image;
            image.alt = product.name;
            image.loading = 'lazy';
            top.appendChild(image);
            var info = make('div');
            info.appendChild(make('h4', '', product.name));
            info.appendChild(make('div', 'ai-selector-product__stock', product.availability));
            info.appendChild(make('div', 'ai-selector-product__price', product.price_formatted));
            top.appendChild(info);
            card.appendChild(top);
            card.appendChild(make('p', 'ai-selector-product__reason', product.reason));
            addList(card, 'Подтверждено', product.confirmed, 'ai-selector-product__confirmed');
            addList(card, 'Нужно проверить', product.verify, 'ai-selector-product__verify');
            var link = make('a', 'tf-btn btn-outline-dark ai-selector-product__link', 'Открыть товар');
            link.href = product.url;
            card.appendChild(link);
            host.appendChild(card);
        });
        showStep('results');
    }

    function startSelection() {
        var intent = (intentInput.value || '').trim();
        var step = one('[data-ai-selector-step="intent"]', modalElement);
        showError('', step);
        if (intent.length < 5) {
            showError('Опишите задачу хотя бы несколькими словами.', step);
            intentInput.focus();
            return;
        }
        state.intent = intent;
        loading(true);
        post(root.dataset.startUrl, { intent: intent, category: state.category }).then(renderQuestions).catch(function (error) {
            showError(error.message, step);
        }).finally(function () { loading(false); });
    }

    all('[data-ai-selector-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var teaser = one('[data-ai-selector-teaser]');
            if (teaser) teaser.hidden = true;
            modal.show();
            window.setTimeout(function () { intentInput.focus(); }, 300);
        });
    });

    all('[data-ai-category]', modalElement).forEach(function (button) {
        button.addEventListener('click', function () {
            state.category = button.dataset.aiCategory;
            all('[data-ai-category]', modalElement).forEach(function (item) { item.classList.toggle('is-active', item === button); });
            if (!intentInput.value.trim()) intentInput.value = 'Хочу подобрать: ' + button.textContent.toLowerCase();
            intentInput.focus();
        });
    });

    one('[data-ai-selector-start]', modalElement).addEventListener('click', startSelection);
    intentInput.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') startSelection();
    });

    one('[data-ai-selector-step="questions"]', modalElement).addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        showError('', form);
        var answers = Object.assign({}, state.known);
        new FormData(form).forEach(function (value, key) { if (String(value).trim()) answers[key] = String(value).trim(); });
        loading(true);
        post(root.dataset.recommendUrl, { intent: state.intent, category: state.category, answers: answers }).then(renderResults).catch(function (error) {
            showError(error.message, form);
        }).finally(function () { loading(false); });
    });

    one('[data-ai-selector-back]', modalElement).addEventListener('click', function () { showStep('intent'); });
    one('[data-ai-selector-restart]', modalElement).addEventListener('click', function () {
        state = { intent: '', category: null, known: {}, questions: [] };
        intentInput.value = '';
        all('[data-ai-category]', modalElement).forEach(function (item) { item.classList.remove('is-active'); });
        showStep('intent');
    });

    var teaser = one('[data-ai-selector-teaser]');
    var blockedPath = /^\/(cart|checkout|account|wishlist)(\/|$)/.test(window.location.pathname);
    var dismissedAt = Number(localStorage.getItem('kotlov_ai_selector_dismissed_at') || 0);
    var sevenDays = 7 * 24 * 60 * 60 * 1000;
    if (teaser && !blockedPath && Date.now() - dismissedAt > sevenDays) {
        window.setTimeout(function () {
            if (!modalElement.classList.contains('show')) teaser.hidden = false;
        }, 40000);
    }
    var dismiss = one('[data-ai-selector-dismiss]');
    if (dismiss) dismiss.addEventListener('click', function () {
        teaser.hidden = true;
        localStorage.setItem('kotlov_ai_selector_dismissed_at', String(Date.now()));
    });

    modalElement.addEventListener('show.bs.modal', function () {
        document.body.classList.add('ai-selector-is-open');
        if (teaser) teaser.hidden = true;
    });
    modalElement.addEventListener('hidden.bs.modal', function () { document.body.classList.remove('ai-selector-is-open'); });
})();
