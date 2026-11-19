<div wire:init="refreshStatus" wire:poll.60s.visible="refreshStatus" class="text-sm" aria-live="polite">
    <div class="font-medium">Статус Европочты</div>
    @if ($latest)
        <div>{{ $latest['status'] }}</div>
        @if ($latest['time'] !== '')
            <div class="text-gray-500 dark:text-gray-400">{{ $latest['time'] }}</div>
        @endif
    @endif
    @if ($unavailable)
        <div class="text-gray-500 dark:text-gray-400">Обновление временно недоступно. Повторим автоматически.</div>
    @elseif (! $latest)
        <div class="text-gray-500 dark:text-gray-400">{{ $loaded ? 'Ожидаем первый статус от Европочты' : 'Загружаем статус…' }}</div>
    @endif
</div>
