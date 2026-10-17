(function () {
    'use strict';

    var root = document.getElementById('ai-product-selector');
    var modalElement = document.getElementById('aiSelectorModal');
    if (!root || !modalElement || !window.bootstrap) return;

    var modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var intentInput = document.getElementById('aiSelectorIntent');
    var categorySearch = one('[data-ai-category-search]', modalElement);
    var categoryResults = one('[data-ai-category-results]', modalElement);
    var categorySelected = one('[data-ai-category-selected]', modalElement);
    var categoryClear = one('[data-ai-category-clear]', modalElement);
    var state = { intent: '', category: null, catalogCategoryId: null, catalogCategoryPath: '', known: {}, questions: [] };
    var catalogCategories = [];
    var categoriesLoading = false;
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

    function normalized(value) {
        return String(value || '').toLocaleLowerCase('ru').replace(/ё/g, 'е');
    }

    function loadCategories() {
        if (categoriesLoading || catalogCategories.length || !root.dataset.categoriesUrl) return;
        categoriesLoading = true;
        fetch(root.dataset.categoriesUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('category-load');
                return response.json();
            })
            .then(function (data) {
                catalogCategories = Array.isArray(data.categories) ? data.categories : [];
                renderCategoryResults(categorySearch ? categorySearch.value : '');
            })
            .catch(function () {
                if (categoryResults) {
                    categoryResults.replaceChildren(make('div', 'ai-selector__category-empty', 'Категории временно не загрузились. Опишите задачу ниже.'));
                    categoryResults.hidden = false;
                }
            })
            .finally(function () { categoriesLoading = false; });
    }

    function renderCategoryResults(query) {
        if (!categoryResults) return;
        var needle = normalized(query).trim();
        var matches = catalogCategories.filter(function (item) {
            return !needle || normalized(item.path).indexOf(needle) !== -1;
        });
        if (!needle) {
            matches = matches.filter(function (item) { return String(item.path).split(' › ').length <= 2; })
                .sort(function (a, b) { return Number(b.product_count) - Number(a.product_count); });
        } else {
            matches.sort(function (a, b) {
                var aName = normalized(a.name);
                var bName = normalized(b.name);
                var aScore = aName === needle ? 0 : (aName.indexOf(needle) === 0 ? 1 : 2);
                var bScore = bName === needle ? 0 : (bName.indexOf(needle) === 0 ? 1 : 2);
                return aScore - bScore || String(a.path).length - String(b.path).length;
            });
        }
        matches = matches.slice(0, 12);
        categoryResults.replaceChildren();
        if (!matches.length) {
            categoryResults.appendChild(make('div', 'ai-selector__category-empty', 'Совпадений нет — попробуйте другое название.'));
        } else {
            matches.forEach(function (item) {
                var button = make('button', 'ai-selector__category-option');
                button.type = 'button';
                var text = make('span');
                text.appendChild(make('strong', '', item.name));
                if (item.path !== item.name) text.appendChild(make('small', '', item.path));
                button.appendChild(text);
                button.appendChild(make('em', '', item.product_count + ' тов.'));
                button.addEventListener('click', function () { selectCatalogCategory(item); });
                categoryResults.appendChild(button);
            });
        }
        categoryResults.hidden = false;
    }

    function selectCatalogCategory(item) {
        state.catalogCategoryId = Number(item.id);
        state.catalogCategoryPath = item.path || item.name;
        state.category = null;
        categorySearch.value = item.path || item.name;
        categorySelected.textContent = 'Выбрано: ' + (item.path || item.name);
        categorySelected.hidden = false;
        categoryClear.hidden = false;
        categoryResults.hidden = true;
        all('[data-ai-category]', modalElement).forEach(function (button) { button.classList.remove('is-active'); });
        if (!intentInput.value.trim()) intentInput.value = 'Хочу подобрать товар в категории «' + item.name + '»';
    }

    function clearCatalogCategory() {
        state.catalogCategoryId = null;
        state.catalogCategoryPath = '';
        if (categorySearch) categorySearch.value = '';
        if (categorySelected) categorySelected.hidden = true;
        if (categoryClear) categoryClear.hidden = true;
        if (categoryResults) categoryResults.hidden = true;
    }

    function make(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    }

    function renderQuestions(data) {
        state.category = data.category;
        state.catalogCategoryId = data.catalog_category_id || state.catalogCategoryId;
        state.catalogCategoryPath = data.catalog_category_path || state.catalogCategoryPath;
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
        post(root.dataset.startUrl, { intent: intent, category: state.category, catalog_category_id: state.catalogCategoryId }).then(renderQuestions).catch(function (error) {
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
            clearCatalogCategory();
            all('[data-ai-category]', modalElement).forEach(function (item) { item.classList.toggle('is-active', item === button); });
            if (!intentInput.value.trim()) intentInput.value = 'Хочу подобрать: ' + button.textContent.toLowerCase();
            intentInput.focus();
        });
    });

    if (categorySearch) {
        categorySearch.addEventListener('focus', function () {
            loadCategories();
            if (catalogCategories.length) renderCategoryResults(categorySearch.value);
        });
        categorySearch.addEventListener('input', function () {
            state.catalogCategoryId = null;
            state.catalogCategoryPath = '';
            categorySelected.hidden = true;
            categoryClear.hidden = !categorySearch.value;
            loadCategories();
            renderCategoryResults(categorySearch.value);
        });
    }
    if (categoryClear) categoryClear.addEventListener('click', function () {
        clearCatalogCategory();
        categorySearch.focus();
        renderCategoryResults('');
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
        post(root.dataset.recommendUrl, { intent: state.intent, category: state.category, catalog_category_id: state.catalogCategoryId, answers: answers }).then(renderResults).catch(function (error) {
            showError(error.message, form);
        }).finally(function () { loading(false); });
    });

    one('[data-ai-selector-back]', modalElement).addEventListener('click', function () { showStep('intent'); });
    one('[data-ai-selector-restart]', modalElement).addEventListener('click', function () {
        state = { intent: '', category: null, catalogCategoryId: null, catalogCategoryPath: '', known: {}, questions: [] };
        intentInput.value = '';
        clearCatalogCategory();
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
        loadCategories();
    });
    modalElement.addEventListener('hidden.bs.modal', function () { document.body.classList.remove('ai-selector-is-open'); });
})();
