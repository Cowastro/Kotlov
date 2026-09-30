<div class="space-y-4">
    <div class="text-sm text-gray-600 dark:text-gray-300">
        Начало: {{ $run->started_at?->timezone('Europe/Minsk')->format('d.m.Y H:i:s') }}.
        Всего изменений: {{ $total }}.
        @if ($total > 300)
            Показаны последние 300 записей.
        @endif
    </div>

    @if ($changes->isEmpty())
        <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">
            Цены и остатки не изменились.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-900 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2">Поставщик / товар</th>
                        <th class="px-3 py-2">Что изменилось</th>
                        <th class="px-3 py-2">Закупочная цена</th>
                        <th class="px-3 py-2">Розничная цена</th>
                        <th class="px-3 py-2">Наличие</th>
                        <th class="px-3 py-2">Количество</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($changes as $change)
                        <tr class="align-top">
                            <td class="px-3 py-3">
                                <div class="font-medium">{{ $change->supplier_name ?: '—' }}</div>
                                <div>{{ $change->product_name ?: 'Несвязанный товар' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $change->product_sku ?: 'без SKU' }} · {{ $change->supplier_article ?: 'без артикула' }}
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($change->change_flags as $flag)
                                        <span class="rounded bg-amber-100 px-2 py-1 text-xs text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                            {{ match ($flag) {
                                                'supplier_price' => 'закупочная цена',
                                                'retail_price' => 'розничная цена',
                                                'in_stock' => 'наличие',
                                                'stock_quantity' => 'количество',
                                                'stock_status' => 'статус склада',
                                                'availability_status' => 'статус на сайте',
                                                'product_link' => 'привязка товара',
                                                'link_created' => 'создана связка',
                                                'link_removed' => 'удалена связка',
                                                default => $flag,
                                            } }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                {{ $change->supplier_price_before ?? '—' }} → {{ $change->supplier_price_after ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                {{ $change->retail_price_before ?? '—' }} → {{ $change->retail_price_after ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                {{ $change->in_stock_before === null ? '—' : ($change->in_stock_before ? 'есть' : 'нет') }}
                                →
                                {{ $change->in_stock_after === null ? '—' : ($change->in_stock_after ? 'есть' : 'нет') }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                {{ $change->stock_quantity_before ?? '—' }} → {{ $change->stock_quantity_after ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
