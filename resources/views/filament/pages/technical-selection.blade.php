<x-filament-panels::page>
    <style>
        .ts-wrap{display:grid;gap:20px;max-width:1250px}.ts-card{padding:22px;border:1px solid #d1d5db;border-radius:12px;background:#fff}.dark .ts-card{background:#18181b;border-color:#3f3f46}.ts-card h2{font-size:18px;font-weight:700;margin-bottom:12px}.ts-muted{color:#64748b;font-size:13px}.dark .ts-muted{color:#a1a1aa}.ts-prompt{display:block;width:100%;min-height:145px;padding:14px;margin:10px 0;border:1px solid #9ca3af;border-radius:8px;background:transparent;color:inherit;font-size:15px}.ts-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:16px}.ts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.ts-list{padding-left:20px;list-style:disc;margin:8px 0}.ts-line{white-space:pre-line;overflow-wrap:anywhere}.ts-table{width:100%;border-collapse:collapse;font-size:13px}.ts-table td,.ts-table th{text-align:left;padding:9px;border-bottom:1px solid #71717a44;vertical-align:top}.ts-scroll{overflow:auto}.ts-warning{color:#b45309}.dark .ts-warning{color:#fbbf24}@media(max-width:800px){.ts-grid{grid-template-columns:1fr}.ts-card{padding:16px}}
    </style>
    <div class="ts-wrap">
        <form wire:submit="search" class="ts-card">
            <h2>Опишите задачу — помощник найдёт варианты</h2>
            <p class="ts-muted">Вставьте текст заявки или напишите своими словами. Сначала ищем в нашем каталоге и импортированных предложениях поставщиков. Товары и цены не изменяются.</p>
            <label for="selection-prompt" class="sr-only">Задача и характеристики</label>
            <textarea id="selection-prompt" wire:model="prompt" class="ts-prompt" maxlength="6000" placeholder="Например: насосная станция для дома. Забор воды 8,5 м, расход 3,1 м³/ч при напоре 45 м, бак 24 л, питание 220 В. Нужен 1 комплект."></textarea>
            @error('prompt') <p role="alert" class="ts-warning">{{ $message }}</p> @enderror
            <div class="ts-actions">
                <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="search,searchOutside">Подобрать по задаче</x-filament::button>
                <span wire:loading wire:target="search" class="ts-muted" role="status">Разбираем требования и сравниваем товары…</span>
            </div>
        </form>
        @if ($result)
            <section class="ts-card">
                <h2>{{ $result['task'] }}</h2>
                <p class="ts-muted">{{ $result['searched_at'] }} · Кандидатов проверено: {{ $result['candidate_count'] }} · Поиск: {{ implode(' / ', $result['queries']) }}</p>
                <p class="ts-line" style="margin-top:12px">{{ $result['summary'] }}</p>
                <div class="ts-grid" style="margin-top:18px">
                    <div><strong>Требования из заявки</strong><ul class="ts-list">@foreach ($result['requirements'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
                    <div><strong>Уточнить у клиента</strong><ul class="ts-list">@forelse ($result['questions'] as $line)<li>{{ $line }}</li>@empty<li>Дополнительные вопросы не выделены. Проверьте полноту заявки.</li>@endforelse</ul></div>
                </div>
                <p class="ts-muted">Это предварительный AI-подбор, не заключение инженера. Выборка ограничена: до 12 товаров и 5 непривязанных предложений на поисковую фразу. Отсутствие совпадений не означает отсутствия товара у всех поставщиков.</p>
            </section>
            @foreach ($result['matches'] as $match)
                <section class="ts-card" wire:key="match-{{ $match['key'] }}">
                    <h2>@if ($match['product_id'])<label><input type="checkbox" wire:model="selected" value="{{ $match['product_id'] }}"> {{ $match['name'] }}</label>@else{{ $match['name'] }}@endif</h2>
                    <p class="ts-muted">{{ $match['fit'] }} · {{ $match['sku'] }} · {{ $match['availability'] }}</p>
                    <p style="margin:10px 0">Продажа: {{ $match['price'] > 0 ? number_format($match['price'], 2, ',', ' ').' '.$match['currency'] : 'Цена не указана' }}
                        @if ($match['product_id']) · <a class="ts-warning" href="{{ url('/admin/products/'.$match['product_id']) }}" target="_blank" rel="noopener">Открыть карточку ↗</a>@endif
                    </p>
                    <div class="ts-grid">
                        <div><strong>По данным карточки</strong><ul class="ts-list">@forelse ($match['confirmed'] as $line)<li>{{ $line }}</li>@empty<li>Совпадения характеристик не подтверждены.</li>@endforelse</ul></div>
                        <div><strong>Отличия и проверка</strong><ul class="ts-list">@foreach (array_merge($match['differences'], $match['check']) as $line)<li>{{ $line }}</li>@endforeach</ul></div>
                    </div>
                    <div class="ts-scroll"><table class="ts-table"><thead><tr><th>Поставщик</th><th>Входная, BYN</th><th>Остаток по данным импорта</th><th>Обновление</th></tr></thead><tbody>
                        @forelse ($match['supplier_offers'] as $offer)<tr><td>{{ $offer['supplier'] }}</td><td>{{ $offer['purchase_byn'] > 0 ? number_format($offer['purchase_byn'], 2, ',', ' ') : 'Уточнить' }}</td><td>{{ $offer['stock'] ?? 'Не указан' }} · {{ $offer['fresh_positive_stock'] ? 'Свежий положительный остаток' : 'Требует подтверждения' }}@if ($offer['stock_text'])<br>{{ $offer['stock_text'] }}@endif</td><td>{{ $offer['synced_at'] ?: 'Нет даты' }}@if ($offer['delivery_days'] !== null)<br>Срок: {{ $offer['delivery_days'] }} дн.@endif</td></tr>@empty<tr><td colspan="4">Нет предложений в справочнике поставщиков. Закупку и наличие нужно подтвердить.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endforeach
            <div class="ts-actions">
                @if (collect($result['matches'])->contains(fn ($match) => $match['product_id']))
                    <x-filament::button wire:click="createOffer" color="success" wire:loading.attr="disabled">Создать КП из выбранных</x-filament::button>
                    <span class="ts-muted">Количество и условия проверьте в КП. Ничего клиенту не отправляется.</span>
                @endif
            </div>
            <section class="ts-card">
                <h2>Если нужно закупить под клиента</h2>
                <p class="ts-muted">Заготовка запроса поставщику — можно скопировать. Закупочную цену, срок, документы и маржу подтвердите до отправки КП.</p>
                <p class="ts-line" style="margin-top:12px;user-select:all">{{ $result['procurement_request'] }}</p>
                <div class="ts-actions"><x-filament::button wire:click="searchOutside" color="gray" icon="heroicon-o-globe-alt" wire:loading.attr="disabled" wire:target="search,searchOutside">Поиск вне каталога</x-filament::button><span wire:loading wire:target="searchOutside" role="status">Исследование рынка…</span></div>
                <p class="ts-muted" style="margin-top:10px">Отдельный API-запрос. Результат зависит от настроенного провайдера: если веб-поиск недоступен, система не подтвердит продавцов и цены.</p>
                @if ($externalResult)
                    <p class="ts-line" style="margin-top:16px">{{ ($externalResult['status'] ?? '') === 'error' ? ($externalResult['message'] ?? 'Внешний поиск недоступен.') : 'Найдены источники для проверки закупки. Цены ниже — предложения продавцов, не согласованные партнёрские входные цены. Техническое соответствие не подтверждено.' }}</p>
                    <p class="ts-warning" style="margin-top:12px">Не заказывайте товар только по совпадению цифр в карточке. Для насосов максимальные расход, напор и глубина всасывания не подтверждают одновременную работу в требуемой точке. Нужны документация, кривая Q/H и проверка условий установки.</p>
                    @foreach ($externalResult['offers'] ?? [] as $offer)
                        <p style="margin-top:12px">{{ $offer['title'] ?? '' }} · {{ $offer['seller'] ?? '' }} · {{ isset($offer['price_byn']) && $offer['price_byn'] > 0 ? number_format($offer['price_byn'], 2, ',', ' ').' BYN' : 'Цену уточнить' }}<br>{{ $offer['evidence'] ?? '' }}</p>
                        @if (in_array(parse_url($offer['url'] ?? '', PHP_URL_SCHEME), ['http', 'https'], true))
                            <a class="ts-warning" href="{{ $offer['url'] }}" target="_blank" rel="noopener noreferrer">Проверить предложение ↗</a>
                        @endif
                    @endforeach
                    @foreach ($externalResult['sources'] ?? [] as $source)
                        @if (in_array(parse_url($source['url'] ?? '', PHP_URL_SCHEME), ['http', 'https'], true))
                            <p style="margin-top:10px"><a class="ts-warning" href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] ?? $source['url'] }} ↗</a></p>
                        @endif
                    @endforeach
                    @if (empty($externalResult['offers']) && empty($externalResult['sources']))
                        <p class="ts-muted">Подтверждённых предложений и источников не найдено. Запросите подбор у поставщика.</p>
                    @endif
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
