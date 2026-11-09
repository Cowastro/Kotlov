<x-filament-panels::page>
    <x-filament::section heading="Подключение">
        <p>Проверка баланса и настроек не отправляет SMS.</p>
        <x-filament::button wire:click="checkConnection" wire:loading.attr="disabled" class="mt-3">
            Проверить подключение
        </x-filament::button>
        @error('connection') <p role="alert" class="mt-3 text-danger-600">{{ $message }}</p> @enderror
        @if($connection)
            <dl class="mt-4 space-y-1">
                <div><dt class="inline font-semibold">Баланс</dt> <dd class="inline">{{ $connection['balance'] ?? 'Недоступен' }} {{ $connection['currency'] }}</dd></div>
                <div><dt class="inline font-semibold">Отправитель</dt> <dd class="inline">{{ $connection['sender'] ?? 'Не найден' }}</dd></div>
                <div><dt class="inline font-semibold">Код из шести цифр</dt> <dd class="inline">{{ $connection['six_digits'] ? 'Настроен' : 'Проверьте объект кода' }}</dd></div>
            </dl>
        @endif
    </x-filament::section>

    <x-filament::section heading="Тестовый код">
        <p>Одна SMS на указанный номер. Шаблон «Код KOTLOV: 123456».</p>
        <form wire:submit="sendTest" class="mt-4 space-y-3">
            <label class="block space-y-1">
                <span>Номер получателя</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="tel" wire:model="phone" maxlength="30" placeholder="+375 (XX) XXX-XX-XX" required />
                </x-filament::input.wrapper>
            </label>
            @error('phone') <p role="alert" class="text-danger-600">{{ $message }}</p> @enderror
            <x-filament::button type="submit" wire:loading.attr="disabled">Отправить тестовый код</x-filament::button>
        </form>

        @if($test)
            <div class="mt-5 space-y-3" aria-live="polite">
                <p>{{ $test['phone'] }} · {{ $test['status'] }} · Сегментов {{ $test['parts'] ?? '—' }}</p>
                @if($test['sms_id'])
                    <p>ID SMS {{ $test['sms_id'] }}</p>
                    <x-filament::button color="gray" wire:click="checkDelivery" wire:loading.attr="disabled">Проверить доставку</x-filament::button>
                @endif
                @error('delivery') <p role="alert" class="text-danger-600">{{ $message }}</p> @enderror
                @if($test['confirmed'])
                    <p class="font-semibold text-success-600">Код подтверждён.</p>
                @else
                    <form wire:submit="confirmCode" class="space-y-3">
                        <label class="block space-y-1">
                            <span>Код из полученной SMS</span>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="code" maxlength="6" required />
                            </x-filament::input.wrapper>
                        </label>
                        @error('code') <p role="alert" class="text-danger-600">{{ $message }}</p> @enderror
                        <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">Проверить код</x-filament::button>
                    </form>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
