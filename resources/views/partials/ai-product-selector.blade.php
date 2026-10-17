<div class="ai-selector"
     id="ai-product-selector"
     data-start-url="{{ route('ai-selection.start') }}"
     data-categories-url="{{ route('ai-selection.categories') }}"
     data-recommend-url="{{ route('ai-selection.recommend') }}"
     data-event-url="{{ route('ai-selection.event') }}"
     data-contact-url="{{ route('ai-selection.contact') }}">
    <button class="ai-selector__launcher" type="button" data-ai-selector-launcher data-ai-selector-open
        aria-haspopup="dialog" aria-controls="aiSelectorModal" aria-label="Открыть AI-помощника по подбору оборудования" hidden>
        <span class="ai-selector__help-icon" aria-hidden="true">
            <i class="icon icon-Question"></i>
        </span>
        <span>AI-помощник</span>
    </button>

    <div class="ai-selector__teaser" data-ai-selector-teaser hidden role="status" aria-live="polite">
        <button type="button" class="ai-selector__teaser-close" data-ai-selector-dismiss aria-label="Закрыть">×</button>
        <strong>Это AI-помощник KOTLOV</strong>
        <span>Опишите задачу — он подберёт 3 товара из каталога. Бесплатно и без регистрации.</span>
        <button type="button" class="ai-selector__teaser-action" data-ai-selector-open>Попробовать подбор</button>
    </div>
</div>

<div class="modal fade ai-selector-modal" id="aiSelectorModal" tabindex="-1" aria-labelledby="aiSelectorTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="ai-selector-modal__eyebrow">AI-помощник KOTLOV</div>
                    <h2 class="modal-title" id="aiSelectorTitle">Подберём оборудование под вашу задачу</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div data-ai-selector-step="intent">
                    <p class="ai-selector-modal__lead">Выберите раздел большого каталога или опишите задачу своими словами. Помощник уточнит важные параметры и будет искать только в выбранной категории.</p>
                    <div class="ai-selector__catalog-picker">
                        <label class="ai-selector__label" for="aiSelectorCategorySearch">Категория каталога</label>
                        <div class="ai-selector__category-search-wrap">
                            <input id="aiSelectorCategorySearch" class="ai-selector__input" type="search" autocomplete="off" placeholder="Например: радиаторы, дымоходы, стабилизаторы" data-ai-category-search>
                            <button type="button" class="ai-selector__category-clear" data-ai-category-clear hidden aria-label="Сбросить категорию">×</button>
                        </div>
                        <div class="ai-selector__category-results" data-ai-category-results hidden></div>
                        <div class="ai-selector__category-selected" data-ai-category-selected hidden></div>
                        <small class="ai-selector__hint">Показываем только разделы, где есть активные товары.</small>
                    </div>
                    <div class="ai-selector__quick-title">Популярные задачи</div>
                    <div class="ai-selector__chips" aria-label="Быстрый выбор задачи">
                        <button type="button" data-ai-category="gas_boiler">Газовый котёл</button>
                        <button type="button" data-ai-category="boiler">Котёл</button>
                        <button type="button" data-ai-category="heat_pump">Тепловой насос</button>
                        <button type="button" data-ai-category="pump">Насос</button>
                        <button type="button" data-ai-category="gas_meter">Счётчик газа</button>
                    </div>
                    <label class="ai-selector__label" for="aiSelectorIntent">Что нужно подобрать?</label>
                    <textarea id="aiSelectorIntent" class="ai-selector__textarea" rows="4" maxlength="1000" placeholder="Например: нужен газовый котёл для дома 140 м², чтобы готовил горячую воду"></textarea>
                    <div class="ai-selector__error" data-ai-selector-error role="alert" hidden></div>
                    <button type="button" class="tf-btn btn-primary animate-btn ai-selector__primary" data-ai-selector-start>Продолжить</button>
                </div>

                <form data-ai-selector-step="questions" hidden>
                    <button type="button" class="ai-selector__back" data-ai-selector-back>← Изменить задачу</button>
                    <h3 data-ai-selector-category></h3>
                    <p class="ai-selector-modal__lead" data-ai-selector-summary></p>
                    <div data-ai-selector-questions></div>
                    <div class="ai-selector__lost-help">
                        <div><strong>Не знаете параметры?</strong><span>Оставьте номер — менеджер увидит весь запрос и поможет с выбором.</span></div>
                        <button type="button" class="tf-btn btn-outline-dark" data-ai-lead-open data-ai-lead-source="questions">Нужна помощь</button>
                    </div>
                    <div class="ai-selector__error" data-ai-selector-error role="alert" hidden></div>
                    <button type="submit" class="tf-btn btn-primary animate-btn ai-selector__primary">Показать варианты</button>
                </form>

                <div data-ai-selector-step="results" hidden>
                    <button type="button" class="ai-selector__back" data-ai-selector-restart>← Новый подбор</button>
                    <h3>Подходящие варианты</h3>
                    <p class="ai-selector-modal__lead" data-ai-selector-result-summary></p>
                    <div class="ai-selector__results" data-ai-selector-results></div>
                    <div class="ai-selector__human-help">
                        <strong>Нужна проверка специалиста?</strong>
                        <span>Оставьте номер — менеджер получит запрос, ответы и найденные варианты.</span>
                        <button type="button" class="tf-btn btn-outline-dark" data-ai-lead-open data-ai-lead-source="results">Перезвоните мне</button>
                    </div>
                </div>

                <form data-ai-selector-step="lead" hidden>
                    <button type="button" class="ai-selector__back" data-ai-lead-back>← Вернуться к подбору</button>
                    <div class="ai-selector__lead-icon" aria-hidden="true">☎</div>
                    <h3 data-ai-lead-title>Поможем разобраться</h3>
                    <p class="ai-selector-modal__lead">Менеджер увидит, что вы искали и какие параметры указали. Не придётся объяснять всё заново.</p>
                    <div class="ai-selector__field">
                        <label class="ai-selector__label" for="aiLeadName">Как к вам обращаться?</label>
                        <input id="aiLeadName" name="name" class="ai-selector__input" maxlength="255" autocomplete="name" required placeholder="Ваше имя">
                    </div>
                    <div class="ai-selector__field">
                        <label class="ai-selector__label" for="aiLeadPhone">Телефон для звонка</label>
                        <input id="aiLeadPhone" name="phone" class="ai-selector__input" maxlength="50" autocomplete="tel" inputmode="tel" required placeholder="+375 (__) ___-__-__">
                    </div>
                    <div class="ai-selector__error" data-ai-selector-error role="alert" hidden></div>
                    <div class="ai-selector__lead-success" data-ai-lead-success hidden></div>
                    <button type="submit" class="tf-btn btn-primary animate-btn ai-selector__primary" data-ai-lead-submit>Заказать обратный звонок</button>
                    <small class="ai-selector__privacy">Нажимая кнопку, вы соглашаетесь на обработку данных для обратной связи.</small>
                </form>

                <div class="ai-selector__loading" data-ai-selector-loading hidden aria-live="polite">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <span>Анализируем задачу и каталог…</span>
                </div>
            </div>
            <div class="modal-footer ai-selector-modal__notice">
                AI-подбор носит рекомендательный характер. Монтажные и инженерные параметры проверяет специалист.
            </div>
        </div>
    </div>
</div>
