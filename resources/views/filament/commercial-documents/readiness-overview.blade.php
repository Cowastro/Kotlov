@php
    $ready = $issues === [];
    $warnings = $warnings ?? [];
@endphp

<div class="space-y-3">
<div @class([
    'flex items-start gap-3 rounded-xl px-4 py-3 ring-1',
    'bg-success-50 text-success-800 ring-success-200 dark:bg-success-950/30 dark:text-success-300 dark:ring-success-800' => $ready,
    'bg-warning-50 text-warning-900 ring-warning-200 dark:bg-warning-950/30 dark:text-warning-200 dark:ring-warning-700' => ! $ready,
])>
    <x-filament::icon
        :icon="$ready ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'"
        class="mt-0.5 h-5 w-5 shrink-0"
    />

    <div class="min-w-0">
        <p class="font-semibold">
            {{ $ready ? 'Документ готов к отправке' : 'Перед отправкой нужно исправить' }}
        </p>

        @if (! $ready)
            <ul class="mt-1.5 list-disc space-y-1 ps-5 text-sm">
                @foreach ($issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        @else
            <p class="mt-1 text-sm opacity-80">Реквизиты клиента и получателя заполнены.</p>
        @endif
    </div>
</div>

@if($warnings !== [])
    <div class="flex items-start gap-3 rounded-xl bg-danger-50 px-4 py-3 text-danger-900 ring-1 ring-danger-200 dark:bg-danger-950/30 dark:text-danger-200 dark:ring-danger-800">
        <x-filament::icon icon="heroicon-o-exclamation-circle" class="mt-0.5 h-5 w-5 shrink-0" />
        <div class="min-w-0">
            <p class="font-semibold">КП разрешено без подтверждённой входной цены</p>
            <ul class="mt-1.5 list-disc space-y-1 ps-5 text-sm">
                @foreach($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
</div>
