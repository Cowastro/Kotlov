<div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <span class="px-2 text-xs font-medium text-gray-500 dark:text-gray-400">Почтовый ящик</span>

    <x-filament::button
        size="sm"
        :color="$this->mailbox === 'all' ? 'primary' : 'gray'"
        :outlined="$this->mailbox !== 'all'"
        wire:click="selectMailbox('all')"
    >
        Все ящики
    </x-filament::button>

    @foreach ($mailboxes as $mailbox)
        <x-filament::button
            size="sm"
            :color="$this->mailbox === $mailbox['id'] ? 'primary' : 'gray'"
            :outlined="$this->mailbox !== $mailbox['id']"
            wire:click="selectMailbox('{{ $mailbox['id'] }}')"
        >
            <span class="font-semibold">{{ $mailbox['name'] }}</span>
            <span class="ml-1 opacity-70">{{ $mailbox['email'] }}</span>
            @if ($mailbox['unread'] > 0)
                <span class="ml-1 rounded-full bg-warning-500/15 px-1.5 text-xs text-warning-600 dark:text-warning-400">
                    {{ $mailbox['unread'] }}
                </span>
            @endif
        </x-filament::button>
    @endforeach
</div>
