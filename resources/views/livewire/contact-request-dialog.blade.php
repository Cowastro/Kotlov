<div class="lead-dialog grid gap-3" @if ($historyPage === 1) wire:poll.12s.visible="$refresh" @endif>
    <style>
        .lead-dialog-history { display: flex; flex-direction: column; gap: .75rem; overflow-y: auto; max-height: 48vh; min-height: 8rem; padding: .5rem; }
        .lead-dialog-message { width: fit-content; max-width: 86%; align-self: flex-start; overflow-wrap: anywhere; border: 1px solid #e4e4e7; border-radius: .9rem .9rem .9rem .2rem; background: #f4f4f5; padding: .65rem .85rem; }
        .lead-dialog-message-out { align-self: flex-end; background: #fef3c7; border-color: #fde68a; border-radius: .9rem .9rem .2rem .9rem; }
        .dark .lead-dialog-message { background: #27272a; border-color: #3f3f46; }
        .dark .lead-dialog-message-out { background: #3c2d13; border-color: #675028; }
        .lead-dialog-draft { width: 100%; min-height: 4rem; resize: vertical; border: 1px solid #a1a1aa; border-radius: .65rem; padding: .6rem .75rem; background: transparent; }
        .lead-dialog-draft:focus { outline: 2px solid #d97706; outline-offset: 2px; }
    </style>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::badge color="info">{{ $lead->channelLabel() }}</x-filament::badge>
            @if ($lead->needs_attention)
                <x-filament::badge color="danger">Требует реакции</x-filament::badge>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canEdit && $lead->needs_attention)
                <x-filament::button type="button" size="sm" color="gray" wire:click="markSeen">Просмотрено</x-filament::button>
            @endif
            @if ($history->hasMorePages())
                <x-filament::button type="button" size="sm" color="gray" wire:click="olderMessages">Раньше</x-filament::button>
            @endif
            @if ($historyPage > 1)
                <x-filament::button type="button" size="sm" color="gray" wire:click="newerMessages">Позже</x-filament::button>
            @endif
        </div>
    </div>

    <div class="lead-dialog-history" role="log" aria-label="История переписки" aria-live="polite">
        @forelse ($history->getCollection()->reverse() as $message)
            <article wire:key="message-{{ $message->id }}" @class(['lead-dialog-message', 'lead-dialog-message-out' => $message->direction === 'outgoing'])>
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <strong>{{ match ($message->direction) {
                        'outgoing' => $message->user?->name ?? 'Менеджер',
                        'legacy' => 'Предыдущая переписка',
                        default => $lead->name,
                    } }}</strong>
                    @if ($message->direction !== 'legacy')
                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone('Europe/Minsk')->format('d.m H:i') }}</time>
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
                <p class="whitespace-pre-wrap text-sm">{{ $message->body }}</p>
            </article>
        @empty
            <p class="lead-dialog-message whitespace-pre-wrap text-sm">{{ $lead->message ?: 'Сообщений пока нет.' }}</p>
        @endforelse

        @if ($historyPage === 1)
            <div wire:key="tail-{{ $history->getCollection()->first()?->id ?? 0 }}" x-data
                x-init="$nextTick(() => { $el.parentElement.scrollTop = $el.parentElement.scrollHeight })"></div>
        @endif
    </div>

    @if ($canReply)
        <div class="grid gap-2">
            <label for="draft-{{ $this->getId() }}" class="text-sm font-medium">Ответ в {{ $lead->channelLabel() }}</label>
            <textarea id="draft-{{ $this->getId() }}" class="lead-dialog-draft text-sm" rows="2" maxlength="1000"
                placeholder="Напишите ответ…" wire:model="draft" wire:keydown.ctrl.enter.prevent="sendReply"
                wire:keydown.meta.enter.prevent="sendReply"></textarea>
            @error('draft')
                <p class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $message }}</p>
            @enderror
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs text-gray-600 dark:text-gray-400">Ctrl + Enter для отправки</span>
                <x-filament::button type="button" icon="heroicon-o-paper-airplane" wire:click="sendReply" wire:target="sendReply">Отправить</x-filament::button>
            </div>
        </div>
    @elseif ($canEdit)
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $lead->archived_at ? 'Обращение в архиве. Восстановите его, чтобы ответить.' : 'Ответ через этот канал сейчас недоступен.' }}
        </p>
    @endif
</div>
