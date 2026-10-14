@php
    $previous = $comparison['previous'];
    $changes = $comparison['changes'];
    $labels = [
        'name' => 'название',
        'sku' => 'артикул',
        'unit' => 'единица',
        'quantity' => 'количество',
        'unit_price' => 'цена',
        'discount_percent' => 'скидка',
        'line_total' => 'сумма',
    ];
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ').' BYN';
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Предыдущая версия</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $previous->number }} · редакция №{{ $previous->revision_number }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Было / стало</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $money($previous->total) }} → {{ $money($document->total) }}</div>
        </div>
        <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">Изменение итога</div>
            <div @class([
                'mt-1 font-semibold',
                'text-success-600 dark:text-success-400' => $comparison['total_delta'] < 0,
                'text-danger-600 dark:text-danger-400' => $comparison['total_delta'] > 0,
                'text-gray-950 dark:text-white' => $comparison['total_delta'] == 0,
            ])>
                {{ $comparison['total_delta'] > 0 ? '+' : '' }}{{ $money($comparison['total_delta']) }}
            </div>
        </div>
    </div>

    @if (filled($previous->outcome_note))
        <div class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
            <span class="font-semibold">Причина корректировки:</span> {{ $previous->outcome_note }}
        </div>
    @endif

    @if ($changes === [])
        <div class="rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
            Состав, количество и цены не изменились.
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
            @foreach ($changes as $change)
                @php
                    $before = $change['before'];
                    $after = $change['after'];
                    $badge = match ($change['type']) {
                        'added' => ['Добавлено', 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300'],
                        'removed' => ['Удалено', 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300'],
                        default => ['Изменено', 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300'],
                    };
                @endphp
                <div class="border-b border-gray-200 p-4 last:border-b-0 dark:border-white/10">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $change['name'] }}</span>
                        @if (filled($change['sku']))
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $change['sku'] }}</span>
                        @endif
                    </div>

                    @if ($change['type'] === 'changed')
                        <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($change['changed_fields'] as $field)
                                @php
                                    $isMoney = in_array($field, ['unit_price', 'line_total'], true);
                                    $isNumber = in_array($field, ['quantity', 'discount_percent'], true);
                                    $format = function ($value) use ($field, $isMoney, $isNumber, $money, $number) {
                                        if ($isMoney) return $money($value);
                                        if ($isNumber) return $number($value).($field === 'discount_percent' ? '%' : '');
                                        return filled($value) ? $value : '—';
                                    };
                                @endphp
                                <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $labels[$field] }}</div>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">
                                        <span class="line-through opacity-60">{{ $format($before->{$field}) }}</span>
                                        <span class="mx-1">→</span>
                                        <span class="font-medium">{{ $format($after->{$field}) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @php $item = $after ?: $before; @endphp
                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ $number($item->quantity) }} {{ $item->unit }} × {{ $money($item->unit_price) }}
                            @if ((float) $item->discount_percent > 0)
                                · скидка {{ $number($item->discount_percent) }}%
                            @endif
                            · сумма {{ $money($item->line_total) }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
