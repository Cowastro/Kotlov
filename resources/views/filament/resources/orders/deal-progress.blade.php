@php
    $order = $getRecord();
    $stages = \App\Models\Order::DEAL_STAGES;
    $history = $order->dealStageHistory();
    $compactLabels = ['received' => 'Получен', 'contacted' => 'Контакт', 'agreed' => 'Условия',
        'processing' => 'В работе', 'handover' => 'Передача', 'completed' => 'Завершён'];
    $currentIndex = array_search($order->effectiveDealStage(), array_keys($stages), true);
    $active = $order->dealIsActive();
    $overdue = $active && $order->deal_control_at?->isPast();
    $today = $active && $order->deal_control_at?->timezone('Europe/Minsk')->isToday();
    $context = ($isTable ?? false) ? ['table' => true, 'recordKey' => (string) $order->getKey()] : [];
@endphp
<div class="order-deal {{ ($isTable ?? false) ? 'order-deal--compact' : 'order-deal--full' }}">
    <div class="order-deal__heading">
        <strong title="Исполнение заказа · {{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}">{{ $order->status === 'cancelled' ? 'Закрыт с причиной' : $stages[$order->effectiveDealStage()] }}</strong>
        <span>{{ $currentIndex + 1 }} / {{ count($stages) }}</span>
    </div>
    <div class="order-deal__track" aria-label="Этапы сделки">
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
                <span class="order-deal__step-label">{{ ($isTable ?? false) ? $compactLabels[$key] : $label }}</span>
            </button>
        @endforeach
    </div>
    <div class="order-deal__control {{ $overdue ? 'is-overdue' : ($today ? 'is-today' : '') }}">
        <span>{{ $order->dealControlLabel() }}</span>
        @if ($active)
            <button type="button" title="Изменить следующий шаг и срок контроля"
                wire:click.stop="mountAction('manageDeal', {}, @js($context))">Изменить</button>
        @endif
    </div>
    @if ($active && filled($order->deal_next_action))
        <div class="order-deal__next" title="{{ $order->deal_next_action }}">{{ $order->deal_next_action }}</div>
    @endif
    <div class="order-deal__facts">
        <span title="Ответственный менеджер">{{ $order->manager?->name ?: ($order->assigned_to ?: 'Без ответственного') }}</span>
        <span class="order-deal__fulfillment-status">{{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}</span>
        <span>Наличие {{ $order->items->whereNotNull('fulfillment_confirmed_at')->count() }}/{{ $order->items->count() }}</span>
        <span @class(['order-payment-confirmed' => $order->payment_status === 'paid'])>{{ $order->payment_status === 'paid' ? '✓ Оплачен' : $order->paymentStatusLabel() }}</span>
        @if ($order->managementSummary()['problem_count'] > 0)
            <span class="order-control-indicator">Проблем: {{ $order->managementSummary()['problem_count'] }}</span>
        @endif
    </div>
    @if (! ($isTable ?? false) && $order->deal_stage_changed_at)
        <div class="order-deal__changed">Последняя отметка менеджера {{ $order->deal_stage_changed_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}. Все изменения — в истории этапов и действий.</div>
    @endif
</div>
