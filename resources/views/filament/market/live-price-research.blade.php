@php
    $ready = ($research['status'] ?? null) === 'ready';
    $market = (array) ($research['market'] ?? []);
    $offers = (array) ($research['offers'] ?? []);
    $sources = (array) ($research['sources'] ?? []);
    $rejected = (array) ($research['rejected_candidates'] ?? []);
    $localMarket = (array) ($pricing['market'] ?? []);
    $minimumSafe = isset($pricing['minimum_safe_price']) ? (float) $pricing['minimum_safe_price'] : null;
    $median = isset($market['median']) ? (float) $market['median'] : null;
    $suggested = $median !== null ? max($median, (float) ($minimumSafe ?? 0)) : $minimumSafe;
    $maximum = isset($market['maximum']) ? (float) $market['maximum'] : null;
    $conflict = $suggested !== null && $maximum !== null && $suggested > $maximum;
    $confidenceLabels = ['high' => 'высокая', 'medium' => 'средняя', 'low' => 'низкая'];
    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 2, ',', ' ').' BYN';
@endphp

<div class="space-y-6">
    @if (! $ready)
        <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-700 dark:bg-danger-950/40 dark:text-danger-300">
            {{ $research['message'] ?? 'Не удалось выполнить исследование рынка.' }}
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Проверяемая позиция</div>
                    <div class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $research['product_name'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">Артикул: {{ $research['sku'] ?: 'не указан' }} · Цена КП: {{ $money($research['quote_price']) }}</div>
                </div>
                <div class="rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-950/50 dark:text-primary-300">
                    Достоверность: {{ $confidenceLabels[$research['confidence'] ?? 'low'] ?? 'низкая' }}
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs text-gray-500">Подтверждённых продавцов</div>
                <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ (int) ($market['count'] ?? 0) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs text-gray-500">Диапазон рынка</div>
                <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $money($market['minimum'] ?? null) }} — {{ $money($market['maximum'] ?? null) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="text-xs text-gray-500">Медиана / отклонение КП</div>
                <div class="mt-1 font-semibold text-gray-950 dark:text-white">
                    {{ $money($market['median'] ?? null) }}
                    @if (($market['quote_delta_percent'] ?? null) !== null)
                        <span class="text-sm font-normal text-gray-500">({{ (float) $market['quote_delta_percent'] > 0 ? '+' : '' }}{{ number_format((float) $market['quote_delta_percent'], 1, ',', ' ') }}%)</span>
                    @endif
                </div>
            </div>
            <div class="rounded-xl border {{ $conflict ? 'border-danger-300 dark:border-danger-700' : 'border-success-300 dark:border-success-700' }} p-4">
                <div class="text-xs text-gray-500">Безопасный ориентир</div>
                <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $money($suggested) }}</div>
                <div class="mt-1 text-xs text-gray-500">Не применяется автоматически</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <h3 class="font-semibold text-gray-950 dark:text-white">Вывод исследования</h3>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $research['summary'] }}</p>
                <p class="mt-3 whitespace-pre-line text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $research['verdict'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <h3 class="font-semibold text-gray-950 dark:text-white">Закупка и маржа</h3>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-gray-500">Поставщик</dt><dd class="text-right text-gray-950 dark:text-white">{{ $pricing['supplier_name'] ?? 'нет данных' }}</dd>
                    <dt class="text-gray-500">Закупочная цена</dt><dd class="text-right text-gray-950 dark:text-white">{{ $money($pricing['purchase_price'] ?? null) }}</dd>
                    <dt class="text-gray-500">Маржа КП</dt><dd class="text-right text-gray-950 dark:text-white">{{ $money($pricing['margin_byn'] ?? null) }}@if (($pricing['margin_percent'] ?? null) !== null) / {{ number_format((float) $pricing['margin_percent'], 1, ',', ' ') }}%@endif</dd>
                    <dt class="text-gray-500">Минимум для маржи</dt><dd class="text-right text-gray-950 dark:text-white">{{ $money($minimumSafe) }}</dd>
                </dl>
                @if ($conflict)
                    <div class="mt-3 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-950/40 dark:text-danger-300">
                        Минимальная цена для допустимой маржи выше найденного рынка. Нужна новая закупочная цена или отдельное решение менеджера.
                    </div>
                @elseif (($market['count'] ?? 0) < 3)
                    <div class="mt-3 rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-950/40 dark:text-warning-300">
                        Найдено меньше трёх сопоставимых продавцов. Вывод полезен как ориентир, но цену лучше подтвердить вручную.
                    </div>
                @endif
            </div>
        </div>

        <div>
            <h3 class="mb-3 font-semibold text-gray-950 dark:text-white">Подтверждённые предложения</h3>
            @if ($offers === [])
                <div class="rounded-xl border border-gray-200 p-4 text-sm text-gray-500 dark:border-white/10">Точных предложений с проверяемой ценой не найдено.</div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5">
                            <tr><th class="px-4 py-3">Продавец</th><th class="px-4 py-3">Цена</th><th class="px-4 py-3">Наличие</th><th class="px-4 py-3">Совпадение</th><th class="px-4 py-3">Подтверждение</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($offers as $offer)
                                <tr class="align-top">
                                    <td class="px-4 py-3">
                                        <a href="{{ $offer['url'] }}" target="_blank" rel="noopener noreferrer" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $offer['seller'] ?: $offer['domain'] }}</a>
                                        <div class="mt-1 max-w-xs text-xs text-gray-500">{{ $offer['title'] }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-950 dark:text-white">
                                        {{ $offer['price'] !== null ? number_format((float) $offer['price'], 2, ',', ' ').' '.($offer['currency'] ?: '') : '—' }}
                                        @if ($offer['price_byn'] !== null && ($offer['currency'] ?? null) !== 'BYN')<div class="text-xs font-normal text-gray-500">≈ {{ $money($offer['price_byn']) }}</div>@endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $offer['availability'] ?: 'не подтверждено' }}@if ($offer['vat'])<div class="mt-1 text-xs text-gray-500">{{ $offer['vat'] }}</div>@endif</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs {{ ($offer['match'] ?? null) === 'exact' ? 'bg-success-100 text-success-700 dark:bg-success-950 dark:text-success-300' : 'bg-warning-100 text-warning-700 dark:bg-warning-950 dark:text-warning-300' }}">{{ ($offer['match'] ?? null) === 'exact' ? 'точное' : 'вероятное' }}</span></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $offer['evidence'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($rejected !== [])
            <details class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <summary class="cursor-pointer font-semibold text-gray-950 dark:text-white">Отбракованные похожие товары ({{ count($rejected) }})</summary>
                <div class="mt-3 space-y-3">
                    @foreach ($rejected as $candidate)
                        <div class="text-sm">
                            @if (! empty($candidate['url']))<a href="{{ $candidate['url'] }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline dark:text-primary-400">{{ $candidate['title'] ?? $candidate['url'] }}</a>@else<span class="font-medium">{{ $candidate['title'] ?? 'Похожая позиция' }}</span>@endif
                            <div class="text-gray-500">{{ $candidate['reason'] ?? 'Не соответствует проверяемой модели.' }}</div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <details class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <summary class="cursor-pointer font-semibold text-gray-950 dark:text-white">Источники и поисковые запросы ({{ count($sources) }})</summary>
            <div class="mt-3 grid gap-5 lg:grid-cols-2">
                <div class="space-y-2 text-sm">
                    @forelse ($sources as $source)
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="block break-all text-primary-600 hover:underline dark:text-primary-400">{{ $source['title'] ?: $source['url'] }}</a>
                    @empty
                        <span class="text-gray-500">Список источников не получен.</span>
                    @endforelse
                </div>
                <ol class="list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ((array) ($research['search_queries'] ?? []) as $query)<li>{{ $query }}</li>@endforeach
                </ol>
            </div>
        </details>

        <div class="text-xs text-gray-500">
            Исследовано {{ $research['searched_at']?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i') }} · модель {{ $research['model'] ?? '—' }}. Результат является рекомендацией; цена каталога не изменена.
        </div>
    @endif
</div>
