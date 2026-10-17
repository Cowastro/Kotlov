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
    var state = { intent: '', category: null, catalogCategoryId: null, catalogCategoryPath: '', known: {}, questions: [], runId: null, runToken: null, currentStep: 'intent', previousStep: 'questions', leadCaptured: false, productOpened: false };
    var catalogCategories = [];
    var categoriesLoading = false;
    var roleLabels = { optimal: 'Оптимальный', economy: 'Экономичный', reserve: 'Альтернатива' };
    var launcher = one('[data-ai-selector-launcher]');
    var teaser = one('[data-ai-selector-teaser]');
    var assistanceMenu = one('[data-assistance-menu]');
    var supportChatElement = document.getElementById('supportChat');
    var supportChatModal = supportChatElement ? window.bootstrap.Modal.getOrCreateInstance(supportChatElement) : null;
    var supportChatConversation = null;
    var supportChatLastMessageId = 0;
    var teaserAutoHideTimer = null;

    function all(selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); }
    function one(selector, context) { return (context || document).querySelector(selector); }
    function setHidden(element, hidden) { if (element) element.hidden = hidden; }

    function readStorage(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    }

    function writeStorage(key, value) {
        try { window.localStorage.setItem(key, value); } catch (error) {}
    }

    function minskMinutesNow() {
        try {
            var parts = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Europe/Minsk',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23'
            }).formatToParts(new Date());
            var hour = Number(parts.find(function (part) { return part.type === 'hour'; }).value);
            var minute = Number(parts.find(function (part) { return part.type === 'minute'; }).value);
            return hour * 60 + minute;
        } catch (error) {
            var now = new Date();
            return now.getHours() * 60 + now.getMinutes();
        }
    }

    function updateConsultantAvailability() {
        var minutes = minskMinutesNow();
        var online = minutes >= 9 * 60 && minutes < 20 * 60;
        all('[data-consultant-status]').forEach(function (status) {
            status.classList.toggle('is-online', online);
            status.classList.toggle('is-offline', !online);
            var text = one('[data-consultant-status-text]', status);
            if (text) text.textContent = online ? 'Консультант онлайн до 20:00' : 'Сейчас не в сети — ответим утром';
        });
        all('[data-quick-callback-label]').forEach(function (label) {
            label.textContent = online ? 'Перезвоним за 5 минут' : 'Заказать звонок на утро';
        });
        all('[data-quick-callback-submit]').forEach(function (button) {
            button.textContent = online ? 'Жду звонка' : 'Заказать звонок';
        });
        all('[data-quick-callback-note]').forEach(function (note) {
            note.textContent = online
                ? 'Обычно отвечаем в течение 5 минут в рабочее время.'
                : 'Заявка сразу попадёт в очередь — консультант перезвонит утром.';
        });
    }

    function supportChatError(message, thread) {
        if (!supportChatElement) return;
        var target = one(thread ? '[data-support-chat-thread-error]' : '[data-support-chat-error]', supportChatElement);
        if (!target) return;
        target.textContent = message || '';
        target.hidden = !message;
    }

    function supportChatRequest(url, options) {
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }, options || {})).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    var firstError = data.errors && Object.values(data.errors)[0];
                    throw new Error(Array.isArray(firstError) ? firstError[0] : (data.message || 'Не удалось связаться с чатом. Попробуйте ещё раз.'));
                }
                return data;
            });
        });
    }

    function supportChatTime(value) {
        if (!value) return '';
        try {
            return new Intl.DateTimeFormat('ru-RU', {
                timeZone: 'Europe/Minsk',
                hour: '2-digit',
                minute: '2-digit'
            }).format(new Date(value));
        } catch (error) {
            return '';
        }
    }

    function renderSupportChat(data) {
        if (!supportChatElement) return;
        var intro = one('[data-support-chat-intro]', supportChatElement);
        var conversationHost = one('[data-support-chat-conversation]', supportChatElement);
        supportChatConversation = data.conversation || null;
        if (!supportChatConversation) {
            setHidden(intro, false);
            setHidden(conversationHost, true);
            supportChatLastMessageId = 0;
            return;
        }

        setHidden(intro, true);
        setHidden(conversationHost, false);
        var contact = one('[data-support-chat-contact]', supportChatElement);
        if (contact) contact.textContent = supportChatConversation.name + ' · ' + supportChatConversation.phone;

        var messagesHost = one('[data-support-chat-messages]', supportChatElement);
        var messages = Array.isArray(data.messages) ? data.messages : [];
        var newestId = messages.length ? Number(messages[messages.length - 1].id) : 0;
        var shouldScroll = newestId > supportChatLastMessageId;
        messagesHost.replaceChildren();
        messages.forEach(function (message) {
            var row = document.createElement('div');
            var isTeam = message.sender === 'manager' || message.sender === 'assistant';
            row.className = 'support-chat__message support-chat__message--' + (isTeam ? 'manager' : 'visitor');
            var bubble = document.createElement('div');
            bubble.className = 'support-chat__bubble';
            var name = document.createElement('div');
            name.className = 'support-chat__bubble-name';
            name.textContent = message.sender_name;
            var body = document.createElement('div');
            body.className = 'support-chat__bubble-body';
            body.textContent = message.body;
            var time = document.createElement('div');
            time.className = 'support-chat__bubble-time';
            time.textContent = supportChatTime(message.created_at);
            bubble.appendChild(name);
            bubble.appendChild(body);
            bubble.appendChild(time);
            row.appendChild(bubble);
            messagesHost.appendChild(row);
        });
        supportChatLastMessageId = newestId;
        if (shouldScroll) messagesHost.scrollTop = messagesHost.scrollHeight;

        var closed = supportChatConversation.status === 'closed';
        setHidden(one('[data-support-chat-closed]', supportChatElement), !closed);
        setHidden(one('[data-support-chat-message-form]', supportChatElement), closed);
    }

    function loadSupportChat() {
        if (!supportChatElement) return Promise.resolve();
        return supportChatRequest(supportChatElement.dataset.supportChatShowUrl)
            .then(renderSupportChat)
            .catch(function (error) { supportChatError(error.message, true); });
    }

    function closeAssistanceMenu() {
        if (assistanceMenu) assistanceMenu.hidden = true;
        if (launcher) launcher.setAttribute('aria-expanded', 'false');
    }

    function toggleAssistanceMenu() {
        if (!assistanceMenu) return;
        var willOpen = assistanceMenu.hidden;
        assistanceMenu.hidden = !willOpen;
        if (launcher) launcher.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (teaser) teaser.hidden = true;
        if (launcher) launcher.classList.remove('is-explaining');
        writeStorage('kotlov_ai_selector_intro_seen', String(Date.now()));
    }

    function showLauncher(showExplanation) {
        if (!launcher) return;
        launcher.hidden = false;
        window.requestAnimationFrame(function () { launcher.classList.add('is-visible'); });
        if (!showExplanation || !teaser || modalElement.classList.contains('show')) return;
        launcher.classList.add('is-explaining');
        teaser.hidden = false;
        writeStorage('kotlov_ai_selector_intro_seen', String(Date.now()));
        window.clearTimeout(teaserAutoHideTimer);
        teaserAutoHideTimer = window.setTimeout(function () {
            teaser.hidden = true;
            launcher.classList.remove('is-explaining');
        }, 10000);
    }

    function showStep(name) {
        state.currentStep = name;
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

    function trackEvent(eventName, extra) {
        if (!state.runId || !root.dataset.eventUrl) return Promise.resolve();
        return fetch(root.dataset.eventUrl, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.content : ''
            },
            body: JSON.stringify(Object.assign({ run_id: state.runId, run_token: state.runToken, event: eventName, step: state.currentStep }, extra || {}))
        }).catch(function () {});
    }

    function resetLeadForm() {
        var form = one('[data-ai-selector-step="lead"]', modalElement);
        if (!form) return;
        form.reset();
        showError('', form);
        setHidden(one('[data-ai-lead-success]', form), true);
        all('.ai-selector__field, [data-ai-lead-submit], .ai-selector__privacy', form).forEach(function (item) { item.hidden = false; });
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
        state.runId = data.run_id || state.runId;
        state.runToken = data.run_token || state.runToken;
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
        state.runId = data.run_id || state.runId;
        state.runToken = data.run_token || state.runToken;
        one('[data-ai-selector-result-summary]', modalElement).textContent = data.summary || '';
        var host = one('[data-ai-selector-results]', modalElement);
        host.replaceChildren();
        if (!Array.isArray(data.products) || !data.products.length) {
            host.appendChild(make('div', 'ai-selector__empty', 'Готовых вариантов не найдено. Передайте задачу специалисту — он проверит каталог и поставщиков вручную.'));
            var help = one('[data-ai-selector-step="results"] .ai-selector__human-help', modalElement);
            if (help) help.classList.add('is-urgent');
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
            link.addEventListener('click', function () {
                state.productOpened = true;
                trackEvent('product_opened', { product_id: product.id });
            });
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
        post(root.dataset.startUrl, { intent: intent, category: state.category, catalog_category_id: state.catalogCategoryId, page_url: window.location.href }).then(renderQuestions).catch(function (error) {
            showError(error.message, step);
        }).finally(function () { loading(false); });
    }

    all('[data-ai-selector-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeAssistanceMenu();
            if (teaser) teaser.hidden = true;
            if (launcher) launcher.classList.remove('is-explaining');
            writeStorage('kotlov_ai_selector_intro_seen', String(Date.now()));
            modal.show();
            window.setTimeout(function () { intentInput.focus(); }, 300);
        });
    });

    all('[data-assistance-toggle]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleAssistanceMenu();
        });
    });

    all('[data-consultant-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeAssistanceMenu();
        });
    });

    all('[data-support-chat-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeAssistanceMenu();
            if (supportChatModal) supportChatModal.show();
        });
    });

    if (supportChatElement) {
        var supportStartForm = one('[data-support-chat-start-form]', supportChatElement);
        var supportMessageForm = one('[data-support-chat-message-form]', supportChatElement);
        supportChatElement.addEventListener('shown.bs.modal', loadSupportChat);
        supportStartForm.addEventListener('submit', function (event) {
            event.preventDefault();
            supportChatError('', false);
            var submit = one('button[type="submit"]', supportStartForm);
            submit.disabled = true;
            supportChatRequest(supportChatElement.dataset.supportChatStartUrl, {
                method: 'POST',
                body: new FormData(supportStartForm)
            }).then(renderSupportChat).catch(function (error) {
                supportChatError(error.message, false);
            }).finally(function () {
                submit.disabled = false;
            });
        });
        supportMessageForm.addEventListener('submit', function (event) {
            event.preventDefault();
            supportChatError('', true);
            var input = one('textarea[name="message"]', supportMessageForm);
            var submit = one('button[type="submit"]', supportMessageForm);
            var message = input.value.trim();
            if (!message) return;
            submit.disabled = true;
            supportChatRequest(supportChatElement.dataset.supportChatSendUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.content : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ message: message })
            }).then(function (data) {
                input.value = '';
                renderSupportChat(data);
            }).catch(function (error) {
                supportChatError(error.message, true);
            }).finally(function () {
                submit.disabled = false;
                input.focus();
            });
        });
        one('textarea[name="message"]', supportMessageForm).addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                supportMessageForm.requestSubmit();
            }
        });
        one('[data-support-chat-restart]', supportChatElement).addEventListener('click', function () {
            var name = one('input[name="name"]', supportStartForm);
            var phone = one('input[name="phone"]', supportStartForm);
            name.value = supportChatConversation ? supportChatConversation.name : '';
            phone.value = supportChatConversation ? supportChatConversation.phone : '';
            one('textarea[name="message"]', supportStartForm).value = '';
            supportChatConversation = null;
            setHidden(one('[data-support-chat-conversation]', supportChatElement), true);
            setHidden(one('[data-support-chat-intro]', supportChatElement), false);
        });
        window.setInterval(function () {
            if (supportChatConversation && supportChatElement.classList.contains('show')) loadSupportChat();
        }, 5000);
    }

    all('[data-consultant-form]').forEach(function (consultantForm) {
        consultantForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var submit = one('button[type="submit"]', consultantForm);
            var success = one('[data-consultant-form-success]', consultantForm);
            var error = one('[data-consultant-form-error]', consultantForm);
            var formData = new FormData(consultantForm);
            if (submit) submit.disabled = true;
            if (success) success.hidden = true;
            if (error) error.hidden = true;
            fetch(consultantForm.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok) {
                        var firstError = data.errors && Object.values(data.errors)[0];
                        throw new Error(Array.isArray(firstError) ? firstError[0] : 'Не удалось отправить заявку. Попробуйте ещё раз.');
                    }
                    return data;
                });
            }).then(function (data) {
                if (success) {
                    success.textContent = (data.message || 'Заявка отправлена консультанту.') + (data.request_id ? ' Номер заявки: ' + data.request_id + '.' : '');
                    success.hidden = false;
                }
                consultantForm.reset();
                var startedAt = one('input[name="form_started_at"]', consultantForm);
                if (startedAt) startedAt.value = String(Math.floor(Date.now() / 1000));
            }).catch(function (exception) {
                if (error) {
                    error.textContent = exception.message;
                    error.hidden = false;
                }
            }).finally(function () {
                if (submit) submit.disabled = false;
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (!assistanceMenu || assistanceMenu.hidden || root.contains(event.target)) return;
        closeAssistanceMenu();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAssistanceMenu();
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
        post(root.dataset.recommendUrl, { intent: state.intent, category: state.category, catalog_category_id: state.catalogCategoryId, run_id: state.runId, run_token: state.runToken, page_url: window.location.href, answers: answers }).then(renderResults).catch(function (error) {
            showError(error.message, form);
        }).finally(function () { loading(false); });
    });

    one('[data-ai-selector-back]', modalElement).addEventListener('click', function () { showStep('intent'); });
    one('[data-ai-selector-restart]', modalElement).addEventListener('click', function () {
        if (state.runId && !state.leadCaptured) trackEvent('abandoned');
        state = { intent: '', category: null, catalogCategoryId: null, catalogCategoryPath: '', known: {}, questions: [], runId: null, runToken: null, currentStep: 'intent', previousStep: 'questions', leadCaptured: false, productOpened: false };
        intentInput.value = '';
        resetLeadForm();
        clearCatalogCategory();
        all('[data-ai-category]', modalElement).forEach(function (item) { item.classList.remove('is-active'); });
        showStep('intent');
    });

    all('[data-ai-lead-open]', modalElement).forEach(function (button) {
        button.addEventListener('click', function () {
            resetLeadForm();
            state.previousStep = button.dataset.aiLeadSource === 'results' ? 'results' : 'questions';
            var noResults = state.previousStep === 'results' && !one('[data-ai-selector-results] .ai-selector-product', modalElement);
            one('[data-ai-lead-title]', modalElement).textContent = noResults ? 'Не нашли подходящий вариант?' : 'Поможем разобраться';
            showStep('lead');
            trackEvent('lead_prompted');
            window.setTimeout(function () { one('#aiLeadName', modalElement).focus(); }, 100);
        });
    });
    one('[data-ai-lead-back]', modalElement).addEventListener('click', function () { showStep(state.previousStep || 'questions'); });
    one('[data-ai-selector-step="lead"]', modalElement).addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        showError('', form);
        var payload = Object.fromEntries(new FormData(form).entries());
        payload.run_id = state.runId;
        payload.run_token = state.runToken;
        loading(true);
        post(root.dataset.contactUrl, payload).then(function (data) {
            state.leadCaptured = true;
            var success = one('[data-ai-lead-success]', form);
            success.textContent = data.message || 'Спасибо! Менеджер скоро перезвонит.';
            success.hidden = false;
            all('.ai-selector__field, [data-ai-lead-submit], .ai-selector__privacy', form).forEach(function (item) { item.hidden = true; });
        }).catch(function (error) {
            showError(error.message, form);
        }).finally(function () { loading(false); });
    });

    var blockedPath = /^\/(cart|checkout|account|wishlist)(\/|$)/.test(window.location.pathname);
    var dismissedAt = Number(readStorage('kotlov_ai_selector_dismissed_at') || 0);
    var introSeen = Boolean(readStorage('kotlov_ai_selector_intro_seen'));
    var sevenDays = 7 * 24 * 60 * 60 * 1000;
    if (launcher && !blockedPath) {
        window.setTimeout(function () {
            showLauncher(!introSeen && Date.now() - dismissedAt > sevenDays);
        }, introSeen ? 10000 : 5000);
    }
    updateConsultantAvailability();
    window.setInterval(updateConsultantAvailability, 60000);
    var dismiss = one('[data-ai-selector-dismiss]');
    if (dismiss) dismiss.addEventListener('click', function () {
        teaser.hidden = true;
        if (launcher) launcher.classList.remove('is-explaining');
        window.clearTimeout(teaserAutoHideTimer);
        writeStorage('kotlov_ai_selector_dismissed_at', String(Date.now()));
        writeStorage('kotlov_ai_selector_intro_seen', String(Date.now()));
    });

    modalElement.addEventListener('show.bs.modal', function () {
        document.body.classList.add('ai-selector-is-open');
        if (teaser) teaser.hidden = true;
        loadCategories();
    });
    modalElement.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('ai-selector-is-open');
        if (state.runId && !state.leadCaptured && !state.productOpened && ['questions', 'results', 'lead'].indexOf(state.currentStep) !== -1) {
            trackEvent('abandoned');
        }
    });
})();
