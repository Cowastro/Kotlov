<x-filament-panels::page>
    {{ $this->form }}
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button wire:click="calculate" wire:loading.attr="disabled" icon="heroicon-o-calculator">Рассчитать доставку</x-filament::button>
        <x-filament::button wire:click="saveDraft" wire:loading.attr="disabled" color="gray" icon="heroicon-o-document-text">Сохранить как черновик</x-filament::button>
    </div>
    <div wire:loading wire:target="calculate">Получаем предварительную стоимость…</div>
    @if($quote)
        <x-filament::section heading="Предварительная стоимость">
            <div style="font-size:28px;font-weight:700;line-height:1.3">{{ number_format((float)$quote['amount'], 2, ',', ' ') }} BYN</div>
            <p>{{ $quote['profile'] }} · {{ $quote['city'] }}</p>
            @if($quote['days'] || $quote['terms'])<p>Дни доставки: {{ $quote['days'] }} · {{ $quote['terms'] }}</p>@endif
            <p class="text-sm text-gray-500">Расчёт ВАМ Экспресс по выбранным параметрам. Забор с адреса, подъём и особые условия уточняются отдельно. Заявка не создана.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
