<div wire:init="refreshStatus" wire:poll.60s.visible="refreshStatus" class="text-sm" aria-live="polite">
    <div class="font-medium">Статус Европочты</div>
    @if ($latest)
        <div>{{ $latest['status'] }}</div>
    @endif
    @if ($unavailable)
        <div class="text-gray-500 dark:text-gray-400">Обновление временно недоступно. Повторим автоматически.</div>
    @elseif (! $latest)
        <div class="text-gray-500 dark:text-gray-400">{{ $loaded ? 'Ожидаем первый статус от Европочты' : 'Загружаем статус…' }}</div>
    @endif
    @if ($smsStatus)
        <div class="mt-2 text-gray-500 dark:text-gray-400">
            {{ match ($smsStatus) {
                'pending' => 'СМС получателю ожидает отправки',
                'accepted' => 'СМС с трек-номером принято SMS.BY',
                'unconfirmed' => 'Отправка СМС не подтверждена. Проверьте журнал SMS.BY.',
                'skipped' => 'СМС получателю отключено при оформлении',
                'unavailable' => 'СМС получателю недоступно. Проверьте настройки SMS.BY.',
                default => 'Статус СМС неизвестен',
            } }}
        </div>
    @endif
    @if ($canEditShipment && ! $unavailable && ($latest['code'] ?? null) === \App\Services\Shipping\Evropochta::STATUS_CREATED)
        <div class="mt-2">
            <x-filament::button type="button" color="danger" size="sm"
                wire:click="cancelShipment" wire:loading.attr="disabled" wire:target="cancelShipment"
                wire:confirm="Отменить заявку у Европочты? Заказ в KOTLOV останется в текущем статусе.">
                Отменить отправление Европочты
            </x-filament::button>
        </div>
    @endif
</div>
