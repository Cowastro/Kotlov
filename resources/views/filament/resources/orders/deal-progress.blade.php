@php
    $order = $getRecord();
    $stages = \App\Models\Order::DEAL_STAGES;
    $history = $order->dealStageHistory();
    $compactLabels = ['received' => 'Принят', 'contacted' => 'Проверка', 'agreed' => 'Обработка',
        'processing' => 'Сборка', 'handover' => 'Доставка', 'completed' => 'Получен'];
    $mobileLabels = $compactLabels;
    $currentIndex = array_search($order->effectiveDealStage(), array_keys($stages), true);
    $active = $order->dealIsActive();
    $completed = $order->effectiveDealStage() === 'completed' && $order->status !== 'cancelled';
    $overdue = $active && $order->deal_control_at?->isPast();
    $today = $active && $order->deal_control_at?->timezone('Europe/Minsk')->isToday();
    $isTable ??= false;
    $canSelectStage = $isTable && $active && ! \App\Services\Orders\OrderWorkflow::managedBy1c($order);
    $controlLabel = $order->dealControlLabel();
    if (! $isTable && $active && ! $order->deal_control_at) {
        $controlLabel = 'Срок контроля не назначен';
    }
    $context = $isTable ? ['table' => true, 'recordKey' => (string) $order->getKey()] : [];
@endphp
<section class="order-deal {{ $isTable ? 'order-deal--compact' : 'order-deal--full' }}"
    wire:key="order-deal-layout-v4-{{ $order->getKey() }}-{{ $isTable ? 'table' : 'card' }}-{{ $order->effectiveDealStage() }}"
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
                class="order-deal__step {{ $loop->index < $currentIndex || $completed ? 'is-done' : '' }} {{ $loop->index === $currentIndex && ! $completed ? 'is-current' : '' }}"
                @disabled(! $canSelectStage || $loop->index < $currentIndex || $loop->index > $currentIndex + 1)
                @if ($canSelectStage && $loop->index >= $currentIndex && $loop->index <= $currentIndex + 1) wire:click.stop="mountAction('manageDeal', @js(['stage' => $key]), @js($context))" @endif
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
            @if (isset($supplierItem))
                <p>Назначьте поставщика для позиции «{{ $supplierItem->product_name }}»{{ $supplierItem->product_sku ? ' · '.$supplierItem->product_sku : '' }}.</p>
                <x-filament::button tag="a" size="sm" :href="'#order-item-supplier-'.$supplierItem->id" icon="heroicon-o-arrow-down">
                    Перейти к назначению поставщика
                </x-filament::button>
            @elseif (($nextAction ?? null)?->getName() === 'recordSupplierEmailResponse' && isset($supplierReply))
                <p>Ждём ответ от {{ $supplierReply->supplier_name }} по заявке {{ $supplierReply->number }}.</p>
                @if ($supplierReply->items->isNotEmpty())
                    <p>{{ $supplierReply->items->map(fn ($item) => $item->product_name.($item->product_sku ? ' · '.$item->product_sku : ''))->join(', ') }}</p>
                @endif
                <x-filament::button type="button" size="sm" icon="heroicon-o-inbox-arrow-down"
                    wire:click="mountAction('recordSupplierEmailResponse', { supplierOrderRequestId: {{ $supplierReply->id }} })">
                    Зафиксировать ответ поставщика
                </x-filament::button>
            @else
                <p>{{ $order->workflowNextStep() }}</p>
            @endif
            @if (! isset($supplierItem) && isset($nextAction) && $nextAction->getName() !== 'recordSupplierEmailResponse')
                <x-filament::button type="button" size="sm" :icon="$nextAction->getIcon()"
                    data-order-next-action="{{ $nextAction->getName() }}"
                    wire:click="mountAction('{{ $nextAction->getName() }}')">
                    {{ $nextAction->getLabel() }}
                </x-filament::button>
            @endif
            @if (isset($labelAction))
                <x-filament::link :href="$labelAction->getUrl()" target="_blank" rel="noopener" icon="heroicon-o-printer">
                    Печать этикетки Европочты
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
        <span title="Позиции с зафиксированным решением менеджера; остаток поставщика показывается отдельно">Проверено менеджером {{ $order->items->whereNotNull('fulfillment_confirmed_at')->count() }}/{{ $order->items->count() }}</span>
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
