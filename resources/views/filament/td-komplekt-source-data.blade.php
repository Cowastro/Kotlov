<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Исходные значения поставщика. Остатки складов расшифровывают общее наличие и повторно к нему не прибавляются.
        Дата поставки требует подтверждения. Единицы гарантии, веса и размеров не дополняются, если поставщик их не указал.
        Акционные и рекомендованные цены показаны для проверки, скидка покупателю автоматически не назначается.
    </p>
    <p class="text-sm">Последний обмен: {{ $record->last_synced_at?->format('d.m.Y H:i') ?: 'Нет даты' }}. Полей: {{ count($fields) }}.</p>
    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach ($fields as $field)
            <div class="grid gap-2 py-3 sm:grid-cols-2">
                <dt class="text-sm font-medium">{{ $field['label'] }}<small class="block text-gray-500">{{ $field['field'] }}</small></dt>
                <dd class="whitespace-pre-wrap break-words text-sm">@if (mb_strlen($field['value']) > 400)<details><summary>{{ mb_substr($field['value'], 0, 160) }}…</summary>{{ $field['value'] }}</details>@else{{ $field['value'] !== '' ? $field['value'] : '—' }}@endif</dd>
            </div>
        @endforeach
    </dl>
</div>
