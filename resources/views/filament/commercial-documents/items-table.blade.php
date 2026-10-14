<div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
    <table class="w-full min-w-[760px] divide-y divide-gray-200 text-sm dark:divide-white/10">
        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3">Товар</th>
                <th class="px-4 py-3">Артикул</th>
                <th class="px-4 py-3 text-right">Количество</th>
                <th class="px-4 py-3 text-right">Цена</th>
                <th class="px-4 py-3 text-right">Сумма</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-transparent">
            @foreach ($items as $item)
                @php
                    $quantity = number_format((float) $item->quantity, 3, ',', ' ');
                    $quantity = rtrim(rtrim($quantity, '0'), ',');
                @endphp
                <tr>
                    <td class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ $item->name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $item->sku ?: '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $quantity }} {{ $item->unit }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} BYN</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ number_format((float) $item->line_total, 2, ',', ' ') }} BYN</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
