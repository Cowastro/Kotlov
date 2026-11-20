@php
    $order = $getRecord();
    $stages = \App\Models\Order::DEAL_STAGES;
    $history = $order->dealStageHistory();
    $compactLabels = ['received' => 'Получен', 'contacted' => 'Контакт', 'agreed' => 'Условия',
        'processing' => 'В работе', 'handover' => 'Передача', 'completed' => 'Завершён'];
    $mobileLabels = array_replace($compactLabels, ['handover' => 'Выдача', 'completed' => 'Готово']);
    $currentIndex = array_search($order->effectiveDealStage(), array_keys($stages), true);
    $active = $order->dealIsActive();
    $overdue = $active && $order->deal_control_at?->isPast();
    $today = $active && $order->deal_control_at?->timezone('Europe/Minsk')->isToday();
    $isTable ??= false;
    $controlLabel = $order->dealControlLabel();
    if (! $isTable && $active && ! $order->deal_control_at) {
        $controlLabel = 'Срок контроля не назначен';
    }
    $context = $isTable ? ['table' => true, 'recordKey' => (string) $order->getKey()] : [];
    $shipmentAction = null;
    if (! $isTable && $order->needsEvropochtaShipment() && ! $order->needsFulfillmentConfirmation()
        && \App\Filament\Resources\Orders\OrderResource::canEdit($order)) {
        $shipments = app(\App\Services\Shipping\EvropochtaOrderShipment::class);
        if ($shipments->unavailableReason($order) === null) {
            $shipmentAction = ['createEvropochtaShipment', 'Оформить Европочту и открыть ярлык'];
        } elseif ($shipments->preparationUnavailableReason($order) === null) {
            $shipmentAction = ['prepareEvropochtaShipment', 'Подготовить отправление Европочтой'];
        }
    }
@endphp
<section class="order-deal {{ $isTable ? 'order-deal--compact' : 'order-deal--full' }}"
    wire:key="order-deal-layout-v3-{{ $order->getKey() }}-{{ $isTable ? 'table' : 'card' }}-{{ $order->effectiveDealStage() }}"
    style="display:block;flex:none;{{ $isTable ? 'width:384px;min-width:384px;max-width:384px;' : 'width:100%;min-width:0;' }}">
    <div class="order-deal__heading">
        <strong title="Исполнение заказа · {{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}">{{ $order->status === 'cancelled' ? 'Закрыт с причиной' : $stages[$order->effectiveDealStage()] }}</strong>
        <span>{{ $currentIndex + 1 }} / {{ count($stages) }}</span>
    </div>
    <div class="order-deal__track" aria-label="Этапы сделки"
        style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));width:100%;gap:4px;">
        @foreach ($stages as $key => $label)
            @php
                $event = $history[$key] ?? null;
                $hint = $label;
                if ($event) {
                    $author = $event->user?->name ?: ($event->user_id ? 'Пользователь удалён' : 'Система');
                    $hint .= "\n".$author."\n".$event->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i').' (Минск)';
                } elseif ($key === 'received' && $order->created_at) {
                    $hint .= "\n".$order->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i').' (Минск)'
                        ."\nАвтор создания не зафиксирован";
                } else {
                    $hint .= "\nПереход отдельно не отмечен в истории";
                }
            @endphp
            <button type="button"
                class="order-deal__step {{ $loop->index < $currentIndex ? 'is-done' : '' }} {{ $loop->index === $currentIndex ? 'is-current' : '' }}"
                @disabled(! $active)
                @if ($active) wire:click.stop="mountAction('manageDeal', @js(['stage' => $key]), @js($context))" @endif
                title="{{ $hint }}"
                aria-label="{{ $label }}"
                aria-description="{{ $hint }}"
                @if ($loop->index === $currentIndex) aria-current="step" @endif>
                <span class="order-deal__bar"></span>
                @if ($isTable)
                    <span class="order-deal__step-label">{{ $compactLabels[$key] }}</span>
                @else
                    <span class="order-deal__step-label order-deal__step-label--long">{{ $label }}</span>
                    <span class="order-deal__step-label order-deal__step-label--short">{{ $mobileLabels[$key] }}</span>
                @endif
            </button>
        @endforeach
    </div>
    <div class="order-deal__control {{ $overdue ? 'is-overdue' : ($today ? 'is-today' : '') }}">
        <span>{{ $controlLabel }}</span>
        @if ($active)
            <button type="button" title="Изменить следующий шаг и срок контроля"
                wire:click.stop="mountAction('manageDeal', {}, @js($context))">Изменить</button>
        @endif
    </div>
    @if (! $isTable)
        <div class="order-deal__next">
            <strong>Следующий шаг</strong>
            <p>{{ $order->workflowNextStep() }}</p>
            @if ($shipmentAction)
                <x-filament::link tag="button" icon="heroicon-o-cube"
                    wire:click="mountAction('{{ $shipmentAction[0] }}')">
                    {{ $shipmentAction[1] }}
                </x-filament::link>
            @endif
            @if ($active && filled($order->deal_next_action))
                <p class="text-gray-500 dark:text-gray-400">{{ $order->deal_next_action }}</p>
            @endif
        </div>
    @elseif ($active && filled($order->deal_next_action))
        <div class="order-deal__next" title="{{ $order->deal_next_action }}">{{ $order->deal_next_action }}</div>
    @endif
    <div class="order-deal__facts">
        @if ($isTable)
            <span title="Ответственный менеджер">{{ $order->manager?->name ?: ($order->assigned_to ?: 'Без ответственного') }}</span>
            <span class="order-deal__fulfillment-status">{{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}</span>
        @endif
        <span>Наличие {{ $order->items->whereNotNull('fulfillment_confirmed_at')->count() }}/{{ $order->items->count() }}</span>
        @if ($isTable)
            <span @class(['order-payment-confirmed' => $order->payment_status === 'paid'])>{{ $order->payment_status === 'paid' ? '✓ Оплачен' : $order->paymentStatusLabel() }}</span>
        @endif
        @if ($order->managementSummary()['problem_count'] > 0)
            <span class="order-control-indicator">Проблем: {{ $order->managementSummary()['problem_count'] }}</span>
        @endif
    </div>
    @if (! $isTable && $order->deal_stage_changed_at)
        <div class="order-deal__changed">Последняя отметка менеджера {{ $order->deal_stage_changed_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}. Все изменения — в истории этапов и действий.</div>
    @endif
    @include('filament.resources.orders.review-status', ['order' => $order])
</section>
