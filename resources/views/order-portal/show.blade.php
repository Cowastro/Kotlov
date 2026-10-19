@extends('order-portal.layout', ['title' => 'Заказ '.$order->number])

@php
    $preview = $preview ?? false;
    $printUrl = $preview
        ? route('admin.orders.procurement', $order)
        : app(\App\Services\Orders\OrderPortal::class)->printUrl($link);
@endphp

@section('content')
@if($preview)<div class="notice">Режим предпросмотра администратора. QR-ссылка и статистика доступа не изменяются.</div>@endif
@if(session('portal_success'))<div class="notice">{{ session('portal_success') }}</div>@endif
@if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif

<section class="card hero">
    <div class="eyebrow">Онлайн-заказ</div>
    <h1>{{ $order->number }}</h1>
    <div class="meta">
        <span class="status">{{ $order->status === 'delivered' && $order->payment_status === 'paid' ? 'Завершён' : (\App\Models\Order::STATUSES[$order->status] ?? $order->status) }}</span>
        <span>Создан {{ $order->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</span>
        @if($order->corrected_at)<span>Скорректирован {{ $order->corrected_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</span>@endif
        <span>Ответственный: {{ $order->manager?->name ?? $order->assigned_to ?? 'не назначен' }}</span>
        <span>{{ $preview ? 'Предпросмотр администратора' : 'Просмотр по защищённой ссылке' }}</span>
    </div>
</section>

<div class="grid">
    <div>
        <section class="card section">
            <h2>Товары заказа</h2>
            @foreach($order->items as $item)
                @php
                    $supplier = $item->fulfillmentSupplier ?: $item->supplySupplier;
                    $supplierName = $item->fulfillment_supplier_name ?: $supplier?->name ?: $item->supply_supplier_name;
                    $purchasePrice = $item->fulfillment_purchase_price ?? $item->supply_purchase_price;
                    $request = $item->supplierOrderRequestItem?->request;
                    $fulfillmentStatus = $request
                        ? $request->statusLabel()
                        : ($item->fulfillment_confirmed_at
                            ? ($item->fulfillmentRouteLabel() ?: 'Исполнение подтверждено')
                            : 'Ожидает решения менеджера');
                    $image = $item->product?->imageUrl();
                @endphp
                <article class="item">
                    @if($image)<img class="item-image" src="{{ $image }}" alt="">@else<div class="item-image"></div>@endif
                    <div>
                        <h3>{{ $item->product_name }}</h3>
                        <div class="sku">{{ $item->product_sku ? 'Артикул '.$item->product_sku.' · ' : '' }}{{ $item->quantity }} шт.</div>
                    </div>
                    <div><div class="cell-title">Розничная цена</div><div class="money">{{ number_format((float) $item->price, 2, ',', ' ') }} BYN</div><div class="small">Сумма {{ number_format((float) $item->total, 2, ',', ' ') }} BYN</div></div>
                    <div><div class="cell-title">Поставщик и закупка</div><strong>{{ $supplierName ?: ($item->fulfillment_route === 'own_stock' ? 'Наш склад' : 'не определён') }}</strong><div class="small">{{ $purchasePrice !== null ? number_format((float) $purchasePrice, 2, ',', ' ').' BYN' : 'Входная цена не указана' }}</div><div class="small">{!! nl2br(e($item->currentSupplyContact() ?: 'Контакты не указаны')) !!}</div></div>
                    <div><div class="cell-title">Наличие и статус</div>
                        @if($item->supply_stock_quantity !== null)
                            <strong>{{ rtrim(rtrim(number_format((float) $item->supply_stock_quantity, 3, ',', ' '), '0'), ',') }} шт.</strong>
                        @elseif($item->supply_is_available === true)
                            <strong>В наличии</strong><div class="small">Количество не указано</div>
                        @elseif($item->supply_is_available === false)
                            <strong>Нет подтверждённого остатка</strong>
                        @else
                            <strong>Наличие неизвестно</strong>
                        @endif
                        <div class="small">{{ $fulfillmentStatus }}</div>
                        @if($request?->supplier_response_note)<div class="small">{{ $request->supplier_response_note }}</div>@endif
                    </div>
                </article>
            @endforeach
        </section>

        <section class="card section">
            <h2>Данные заказа</h2>
            <div class="meta">
                <span>Клиент: <strong>{{ $order->customer_name }}</strong></span>
                @if($order->customer_phone)<span>Телефон: <strong>{{ $order->customer_phone }}</strong></span>@endif
                @if($order->delivery_city || $order->delivery_address)<span>Доставка: <strong>{{ collect([$order->delivery_city, $order->delivery_address])->filter()->implode(', ') }}</strong></span>@endif
                <span>Оплата: <strong>{{ \App\Models\Order::PAYMENT_TYPES[$order->payment_type] ?? ($order->payment_type ?: 'не указана') }}</strong></span>
            </div>
            @if($order->comment)<p>{{ $order->comment }}</p>@endif
        </section>
    </div>

    <aside>
        <div class="card summary">
            <h2>Итого</h2>
            <div class="sum-row"><span>Товары</span><strong>{{ number_format((float) $order->subtotal, 2, ',', ' ') }} BYN</strong></div>
            @if((float) $order->discount > 0)<div class="sum-row"><span>Скидка</span><strong>− {{ number_format((float) $order->discount, 2, ',', ' ') }} BYN</strong></div>@endif
            @if((float) $order->delivery_price > 0)<div class="sum-row"><span>Доставка</span><strong>{{ number_format((float) $order->delivery_price, 2, ',', ' ') }} BYN</strong></div>@endif
            <div class="sum-row total"><span>К оплате</span><span>{{ number_format((float) $order->total, 2, ',', ' ') }} BYN</span></div>
            <div class="sum-row"><span>Оплата</span><strong>{{ $order->paymentStatusLabel() }}</strong></div>

            @unless($preview)
                <div class="workflow">
                    <h3>Управление заказом</h3>
                    <p class="small">Имя сотрудника сохранится в истории действия.</p>
                    @if(in_array($order->status, ['confirmed', 'processing', 'shipped'], true))
                        <form method="post" action="{{ route('order-portal.action', $link->token_encrypted) }}">
                            @csrf
                            <input type="text" name="actor" value="{{ old('actor', $actor) }}" placeholder="Имя сотрудника" maxlength="100" required>
                            @if(in_array($order->status, ['confirmed', 'processing'], true))
                                <button class="button" type="submit" name="workflow_action" value="accept_delivery">Принять в доставку</button>
                            @elseif($order->status === 'shipped')
                                <button class="button success" type="submit" name="workflow_action" value="delivered_paid">Доставлен и оплачен</button>
                            @endif
                        </form>
                    @else
                        <p class="small">Для текущего статуса доступных действий нет.</p>
                    @endif

                    @if($order->portalEvents->isNotEmpty())
                        <h3 style="margin-top:18px">История</h3>
                        <ul class="history">
                            @foreach($order->portalEvents as $event)
                                <li><strong>{{ $event->metadata['label'] ?? 'Изменение заказа' }}</strong><br><span class="small">{{ $event->metadata['actor'] ?? 'Не указан' }} · {{ $event->occurred_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endunless
            <a class="button" href="{{ $printUrl }}" target="_blank" rel="noopener noreferrer">Версия для печати</a>
            <p class="small">Данные на странице обновляются вместе с заказом: изменения состава, поставщиков, остатков и статусов видны без создания новой ссылки.</p>
            @unless($preview)<p class="small">Ссылка действует до {{ $link->expires_at->timezone('Europe/Minsk')->format('d.m.Y H:i') }} и закроется раньше при завершении или отмене заказа.</p>@endunless
            <p class="small"><strong>{{ $order->manager?->name ?: 'Менеджер KOTLOV.BY' }}</strong><br>{{ $order->manager?->phone }}<br>order@kotlov.by</p>
        </div>
    </aside>
</div>
@endsection
