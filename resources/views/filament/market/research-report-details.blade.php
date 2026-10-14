<div class="space-y-6 text-sm">
    <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div><dt class="text-gray-500">Пакет</dt><dd class="font-medium">{{ $report->batch_code }}</dd></div>
        <div><dt class="text-gray-500">Исследовано</dt><dd class="font-medium">{{ $report->researched_at?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? '—' }}</dd></div>
        <div><dt class="text-gray-500">Решение</dt><dd class="font-medium">{{ $report->decisionLabel() }}</dd></div>
    </dl>

    <div><h3 class="font-semibold">Причина проверки</h3><p class="mt-1 whitespace-pre-line">{{ $report->review_reason }}</p></div>
    @if($report->summary)<div><h3 class="font-semibold">Краткий итог</h3><p class="mt-1 whitespace-pre-line">{{ $report->summary }}</p></div>@endif
    @if($report->verdict)<div><h3 class="font-semibold">Вывод</h3><p class="mt-1 whitespace-pre-line">{{ $report->verdict }}</p></div>@endif
    @if($report->decision_reason)<div><h3 class="font-semibold">Почему принято решение</h3><p class="mt-1 whitespace-pre-line">{{ $report->decision_reason }}</p></div>@endif

    @if(!empty($report->search_queries))
        <div><h3 class="font-semibold">Поисковые запросы</h3><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($report->search_queries as $query)<li>{{ $query }}</li>@endforeach</ul></div>
    @endif

    <div>
        <h3 class="font-semibold">Подтверждённые предложения ({{ count($report->observations_snapshot ?? []) }})</h3>
        <div class="mt-2 overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b"><th class="p-2">Источник</th><th class="p-2">Предложение</th><th class="p-2">Цена</th><th class="p-2">Статус</th></tr></thead><tbody>
        @forelse($report->observations_snapshot ?? [] as $offer)
            <tr class="border-b align-top"><td class="p-2">{{ $offer['source_name'] ?? $offer['source'] ?? '—' }}</td><td class="p-2"><a class="text-primary-600 underline" href="{{ $offer['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer">{{ $offer['external_name'] ?? $offer['model'] ?? $offer['url'] ?? 'Открыть' }}</a></td><td class="p-2 whitespace-nowrap">{{ number_format((float)($offer['observed_price'] ?? 0), 2, ',', ' ') }} {{ $offer['currency'] ?? 'BYN' }}</td><td class="p-2">{{ $offer['availability_status'] ?? 'unknown' }}</td></tr>
        @empty<tr><td class="p-2 text-gray-500" colspan="4">Подтверждённых предложений нет.</td></tr>@endforelse
        </tbody></table></div>
    </div>

    @if(!empty($report->rejected_candidates) || !empty($report->excluded_offers))
        <div><h3 class="font-semibold">Отклонённые варианты</h3><pre class="mt-2 max-h-80 overflow-auto rounded bg-gray-100 p-3 text-xs dark:bg-gray-900">{{ json_encode(array_merge($report->rejected_candidates ?? [], $report->excluded_offers ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
    @endif

    @if(!empty($report->research_payload))
        <details><summary class="cursor-pointer font-semibold">Полный сохранённый протокол исследования</summary><pre class="mt-2 max-h-96 overflow-auto rounded bg-gray-100 p-3 text-xs dark:bg-gray-900">{{ json_encode($report->research_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
    @endif
</div>
