@php
    $formatMoney = static fn ($value): string => $value === null
        ? '—'
        : number_format((float) $value, 2, ',', ' ') . ' BYN';
@endphp

<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $overview['label'] }}</span>
            <span class="text-sm text-gray-600 dark:text-gray-300">{{ $overview['description'] }}</span>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Рыночные предложения не являются закупочными ценами. Цена товара автоматически не изменяется.
        </p>
    </div>

    @foreach ($rows as $row)
        @php
            $indicator = $row['indicator'];
            $product = $row['product'];
            $item = $row['item'] ?? null;
            $checkedAt = $indicator['last_checked_at'] ?? $indicator['latest_observed_at'] ?? null;
        @endphp

        <section class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="font-semibold text-gray-950 dark:text-white">
                        {{ $item?->product_name ?? $product?->name ?? 'Позиция без карточки kotlov.by' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $item?->product_sku ?? $product?->sku ?? 'Артикул не указан' }}
                    </div>
                </div>
                <div class="shrink-0 text-left sm:text-right">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $indicator['indicator_label'] }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $indicator['indicator_description'] }}</div>
                </div>
            </div>

            <div class="grid gap-3 bg-gray-50 p-4 text-sm dark:bg-white/5 sm:grid-cols-2 xl:grid-cols-5">
                <div><div class="text-xs text-gray-500">Наша цена</div><div class="font-medium">{{ $formatMoney($indicator['our_price']) }}</div></div>
                <div><div class="text-xs text-gray-500">Медиана</div><div class="font-medium">{{ $formatMoney($indicator['median']) }}</div></div>
                <div><div class="text-xs text-gray-500">Коридор</div><div class="font-medium">{{ $indicator['minimum'] === null ? '—' : $formatMoney($indicator['minimum']) . ' – ' . $formatMoney($indicator['maximum']) }}</div></div>
                <div><div class="text-xs text-gray-500">Источники</div><div class="font-medium">{{ $indicator['sources_count'] }} из {{ \App\Services\Market\MarketPriceSummary::MINIMUM_SOURCES }} минимум</div></div>
                <div><div class="text-xs text-gray-500">Проверено</div><div class="font-medium">{{ $checkedAt?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? 'Нет данных' }}</div></div>
            </div>

            @if ($indicator['status'] !== 'ready')
                <div class="border-t border-gray-200 px-4 py-3 text-sm text-amber-700 dark:border-white/10 dark:text-amber-300">
                    {{ $indicator['reason'] }}
                </div>
            @endif

            @if ($indicator['evidence']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-gray-100 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Источник</th>
                                <th class="px-4 py-2">Предложение</th>
                                <th class="px-4 py-2">Цена</th>
                                <th class="px-4 py-2">Наличие</th>
                                <th class="px-4 py-2">Сопоставление</th>
                                <th class="px-4 py-2">Проверено</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($indicator['evidence'] as $evidence)
                                @php($observation = $evidence['observation'])
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $observation->source?->name ?? 'Источник недоступен' }}</td>
                                    <td class="max-w-xs px-4 py-3">
                                        <a class="text-primary-600 hover:underline dark:text-primary-400" href="{{ $observation->url }}" target="_blank" rel="noopener noreferrer">
                                            {{ $observation->external_name ?: 'Открыть предложение' }}
                                        </a>
                                        <div class="mt-1 text-xs text-gray-500">{{ $observation->external_sku ?: $observation->model }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $formatMoney($observation->price_byn) }}</td>
                                    <td class="px-4 py-3">{{ $observation->availabilityLabel() }}</td>
                                    <td class="px-4 py-3">
                                        <div>{{ $evidence['label'] }}</div>
                                        <div class="mt-1 text-xs text-gray-500">Уверенность {{ number_format((float) $observation->match_confidence * 100, 0) }}%</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ $observation->observed_at?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-4 py-5 text-sm text-gray-500 dark:text-gray-400">Предложений рынка для этой карточки пока нет.</div>
            @endif
        </section>
    @endforeach
</div>
