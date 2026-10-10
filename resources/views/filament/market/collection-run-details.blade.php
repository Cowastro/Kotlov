<div class="space-y-5 text-sm">
    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-gray-500">Источник</dt><dd class="font-medium">{{ $run->source->name }}</dd></div>
        <div><dt class="text-gray-500">Результат</dt><dd class="font-medium">{{ $run->statusLabel() }}</dd></div>
        <div><dt class="text-gray-500">Запуск</dt><dd class="font-medium">{{ \App\Models\MarketPriceCollectionRun::TRIGGERS[$run->trigger] ?? $run->trigger }}</dd></div>
        <div><dt class="text-gray-500">Начало</dt><dd>{{ $run->started_at?->timezone('Europe/Minsk')->format('d.m.Y H:i:s') }}</dd></div>
        <div><dt class="text-gray-500">Завершение</dt><dd>{{ $run->finished_at?->timezone('Europe/Minsk')->format('d.m.Y H:i:s') ?? 'Выполняется' }}</dd></div>
        <div><dt class="text-gray-500">Инициатор</dt><dd>{{ $run->createdBy?->name ?? 'Система' }}</dd></div>
        <div class="sm:col-span-2 lg:col-span-3"><dt class="text-gray-500">UUID сессии</dt><dd class="break-all font-mono text-xs">{{ $run->session_uuid }}</dd></div>
    </dl>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([
            'Запросы' => $run->requested_count,
            'Записано' => $run->recorded_count,
            'Пропущено' => $run->skipped_count,
            'Предупреждения' => $run->warning_count,
            'Ошибки' => $run->error_count,
        ] as $label => $value)
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format($value, 0, ',', ' ') }}</div>
            </div>
        @endforeach
    </div>

    @if ($run->summary)
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5"><span class="font-medium">Итог:</span> {{ $run->summary }}</div>
    @endif

    <div>
        <h3 class="mb-2 font-semibold">События и ограничения</h3>
        @forelse ($run->events ?? [] as $event)
            <div class="mb-2 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold uppercase">{{ $event['level'] ?? 'info' }}</span>
                    <span class="font-mono">{{ $event['code'] ?? 'event' }}</span>
                    <span class="text-gray-500">{{ $event['at'] ?? '' }}</span>
                </div>
                <div class="mt-1">{{ $event['message'] ?? '' }}</div>
            </div>
        @empty
            <p class="text-gray-500">Ошибок и предупреждений нет.</p>
        @endforelse
    </div>
</div>
