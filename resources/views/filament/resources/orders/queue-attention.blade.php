@php
    $order = $getRecord();
    $nextStep = app(\App\Services\Orders\OrderNextStep::class);
    $shortcut = $nextStep->queueShortcut($order);
    $reasons = $nextStep->queueAttentionReasons($order);
@endphp

<div class="grid gap-2 px-3 py-2 text-xs" data-order-queue-attention>
    @foreach ($reasons as $reason)
        <div data-order-queue-wait>
            <div class="font-medium">{{ $reason['label'] }}</div>
            <div class="text-gray-600 dark:text-gray-300">{{ $reason['action'] }}</div>
            @if (! empty($reason['items']))
                <ul class="grid gap-1">
                    @foreach ($reason['items'] as $item)
                        <li><a href="{{ $item['url'] }}" class="underline" data-order-attention-item>{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
            @if (isset($reason['url']))
                <a href="{{ $reason['url'] }}" class="underline">{{ $reason['link'] }}</a>
            @endif
        </div>
    @endforeach
    @if ($shortcut)
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button tag="a" size="xs" :href="$shortcut['url']" data-order-queue-next-step>
                {{ $shortcut['label'] }}
            </x-filament::button>
            @if ($shortcut['count'] > 1)
                <span>Позиций: {{ $shortcut['count'] }}</span>
            @endif
        </div>
    @elseif (! collect($reasons)->contains(fn ($reason) => isset($reason['url']) || ! empty($reason['items'])))
        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}" class="underline">Открыть заказ</a>
    @endif
</div>
