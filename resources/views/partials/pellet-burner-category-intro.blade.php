<section class="pellet-category-intro" aria-labelledby="pellet-selection-title">
    <div class="container">
        <div class="pellet-category-intro__panel">
            <div>
                <span class="pellet-category-intro__eyebrow">Инженерный подбор KOTLOV</span>
                <h2 id="pellet-selection-title">Подберём горелку под ваш котёл и тепловую нагрузку</h2>
                <p>Проверим мощность, размеры топки, дымоход, автоматику и компоновку котельной. Вы получите совместимый комплект без риска купить оборудование, которое не подходит к существующему котлу.</p>
                <div class="pellet-category-intro__points" aria-label="Что учитываем при подборе">
                    <span>Мощность котла</span>
                    <span>Размеры топки</span>
                    <span>Дымоход и тяга</span>
                    <span>Шнек и бункер</span>
                </div>
            </div>
            <div class="pellet-category-intro__actions">
                <button type="button" class="tf-btn btn-primary" data-bs-toggle="modal" data-bs-target="#engineeringCalculation">Получить расчёт</button>
                <a href="/akcii/kotlov-xo-ceramic-pro" class="tf-btn btn-white">Комплекты по акции</a>
            </div>
        </div>
        @if ($pelletPowerRanges->isNotEmpty())
            <nav class="pellet-power-filter" aria-label="Фильтр горелок по мощности">
                <span class="pellet-power-filter__label">Мощность:</span>
                <a href="?{{ http_build_query(request()->except(['power', 'page'])) }}"
                    class="pellet-power-filter__item {{ !request('power') ? 'is-active' : '' }}">
                    Все
                </a>
                @foreach ($pelletPowerRanges as $range)
                    <a href="?{{ http_build_query(array_merge(request()->except(['power', 'page']), ['power' => $range->key])) }}"
                        class="pellet-power-filter__item {{ request('power') === $range->key ? 'is-active' : '' }}">
                        {{ $range->label }}
                        <span class="pellet-power-filter__count">{{ $range->products_count }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
        <nav class="pellet-category-links" aria-label="Материалы о пеллетных горелках">
            <a href="/blog/pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro">Как работает горелка 100 кВт</a>
            <a href="/pelletnye-gorelki/pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt">KOTLOV XO Ceramic PRO 100 кВт</a>
            <a href="#pellet-burner-faq">Ответы по подбору и монтажу</a>
        </nav>
    </div>
</section>
