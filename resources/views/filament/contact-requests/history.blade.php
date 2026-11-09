@php
    $lead = $getRecord();
    $history = $lead->leadMessages()->with('user')->latest('id')
        ->simplePaginate(30, page: $this->historyPage);
@endphp

<div class="grid gap-3" @if ($this->historyPage === 1) wire:poll.15s.visible="$refresh" @endif>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold">Переписка</h3>
        <div class="flex gap-2">
            @if ($history->hasMorePages())
                <x-filament::button size="sm" color="gray" wire:click="olderMessages">Раньше</x-filament::button>
            @endif
            @if ($this->historyPage > 1)
                <x-filament::button size="sm" color="gray" wire:click="newerMessages">Позже</x-filament::button>
            @endif
        </div>
    </div>

    @forelse ($history->getCollection()->reverse() as $message)
        <article wire:key="lead-message-{{ $message->id }}"
            class="grid gap-1 rounded-xl p-3 {{ $message->direction === 'outgoing' ? 'bg-primary-50 dark:bg-gray-800' : 'bg-gray-100 dark:bg-gray-900' }}">
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                <strong>{{ match ($message->direction) {
                    'outgoing' => $message->user?->name ?? 'Менеджер',
                    'legacy' => 'История до подключения переписки',
                    default => 'Клиент',
                } }}</strong>
                @if ($message->direction !== 'legacy')
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</time>
                @endif
                @if ($message->direction === 'outgoing')
                    <x-filament::badge :color="match ($message->state) { 'sent' => 'success', 'failed' => 'danger', default => 'warning' }">
                        {{ match ($message->state) {
                            'sent' => 'Принято каналом',
                            'failed' => 'Отклонено каналом',
                            'pending' => $message->created_at->gt(now()->subSeconds(30)) ? 'Отправляется' : 'Отправка не подтверждена',
                            default => 'Отправка не подтверждена',
                        } }}
                    </x-filament::badge>
                @endif
            </div>
            <p class="whitespace-pre-wrap break-words text-sm">{{ $message->body }}</p>
        </article>
    @empty
        <p class="whitespace-pre-wrap break-words text-sm">{{ $lead->message }}</p>
    @endforelse
</div>
