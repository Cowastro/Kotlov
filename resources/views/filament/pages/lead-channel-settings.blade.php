<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Подключите аккаунты магазина. Входящие сообщения появятся в разделе «Обращения», где менеджер сможет ответить клиенту.
    </p>

    @foreach (\App\Models\LeadChannel::LABELS as $provider => $label)
        @php($channel = \App\Models\LeadChannel::settings($provider))
        <x-filament::section>
            <x-slot name="heading">{{ $label }}</x-slot>
            <div class="grid gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::badge :color="$channel->acceptsMessages() ? 'success' : 'gray'">
                        {{ $channel->acceptsMessages() ? 'Приём включён' : 'Отключён' }}
                    </x-filament::badge>
                    @if ($channel->public_url && $channel->acceptsMessages())
                        <x-filament::badge color="info">Ссылка на сайте</x-filament::badge>
                    @endif
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $channel->usesMeta() ? 'Укажите этот адрес в настройках вебхука Meta и подпишитесь на события messages.' : 'После сохранения настроек зарегистрируйте вебхук бота.' }}
                </p>
                @if ($channel->acceptsMessages())
                    @php($lastIncoming = $channel->lastIncomingAt())
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $lastIncoming ? 'Последнее входящее · '.$lastIncoming->timezone('Europe/Minsk')->format('d.m.Y H:i') : 'Входящих сообщений пока нет' }}
                    </p>
                @endif
                <code class="break-all text-sm" dir="ltr">{{ $channel->webhookUrl() }}</code>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button wire:click="mountAction('configureChannel', { provider: '{{ $provider }}' })" icon="heroicon-o-cog-6-tooth">
                        {{ $channel->acceptsMessages() ? 'Настроить' : 'Подключить' }}
                    </x-filament::button>
                    @if (! $channel->usesMeta() && $channel->acceptsMessages() && $channel->access_token)
                        <x-filament::button color="gray" wire:click="mountAction('registerWebhook', { provider: '{{ $provider }}' })">
                            Зарегистрировать вебхук
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
