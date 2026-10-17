<div class="ai-selector"
     id="ai-product-selector"
     data-start-url="{{ route('ai-selection.start') }}"
     data-recommend-url="{{ route('ai-selection.recommend') }}">
    <button class="ai-selector__launcher" type="button" data-ai-selector-open aria-haspopup="dialog" aria-controls="aiSelectorModal">
        <span class="ai-selector__spark" aria-hidden="true">✦</span>
        <span>Умный подбор</span>
    </button>

    <div class="ai-selector__teaser" data-ai-selector-teaser hidden role="status">
        <button type="button" class="ai-selector__teaser-close" data-ai-selector-dismiss aria-label="Закрыть">×</button>
        <strong>Не уверены, что выбрать?</strong>
        <span>Подберём 3 варианта за несколько вопросов.</span>
        <button type="button" class="ai-selector__teaser-action" data-ai-selector-open>Помочь с выбором</button>
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
                    <p class="ai-selector-modal__lead">Опишите задачу своими словами. Помощник уточнит важные параметры и покажет только товары из действующего каталога.</p>
                    <div class="ai-selector__chips" aria-label="Быстрый выбор категории">
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
                        <span>Передайте результаты менеджеру — он проверит совместимость и комплектацию.</span>
                        <a href="#ask" data-bs-toggle="modal" data-bs-dismiss="modal" class="tf-btn btn-outline-dark">Связаться со специалистом</a>
                    </div>
                </div>

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
