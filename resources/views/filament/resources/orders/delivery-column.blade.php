@php
    $order = $getRecord();
    $method = $order->delivery_type;
    if ($method !== 'evropochta' && $order->vamExpressShipments->isNotEmpty()) {
        $method = 'vam_express';
    }
    $fullLabel = \App\Models\Order::DELIVERY_TYPES[$method] ?? ($method ?: 'Не указана');
    $label = match ($method) {
        'courier' => 'Наш транспорт · МКАД-2',
        'own_transport' => 'Наш транспорт · по графику',
        'transport' => 'ТК по Беларуси',
        'kit' => 'ТК КИТ',
        default => $fullLabel,
    };
@endphp

<div class="grid gap-1 px-3 py-2 text-xs" data-order-delivery>
    <div class="font-medium" title="{{ $fullLabel }}">{{ $label }}</div>
    @if ($method === 'evropochta')
        <x-filament::badge size="sm" :color="\App\Services\Shipping\EvropochtaShipmentSummary::color($order)">
            {{ \App\Services\Shipping\EvropochtaShipmentSummary::status($order) }}
        </x-filament::badge>
    @elseif ($method === 'vam_express')
        @forelse ($order->vamExpressShipments as $shipment)
            <x-filament::badge size="sm" :color="\App\Support\Shipping\VamExpressStatus::color($shipment->carrier_status)">
                @if ($order->vamExpressShipments->count() > 1 && filled($shipment->display_id))
                    {{ $shipment->display_id }} ·
                @endif
                {{ filled($shipment->carrier_status) ? $shipment->carrier_status : 'Статус ещё не проверен' }}
            </x-filament::badge>
        @empty
            <x-filament::badge size="sm" color="gray">Не оформлено</x-filament::badge>
        @endforelse
    @endif
</div>
