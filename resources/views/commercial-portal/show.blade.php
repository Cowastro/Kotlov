@extends('commercial-portal.layout', ['title' => 'Предложение '.$document->number])

@php
    $statusLabels = \App\Models\CommercialDocument::STATUSES;
    $preview = $preview ?? false;
    $actionable = !$preview && $document->status === 'sent' && (!$document->valid_until || !$document->valid_until->isPast());
@endphp

@section('content')
@if($preview)<div class="notice">Режим предпросмотра администратора. Клиентская ссылка не активирована, статистика не изменяется.</div>@endif
@if(session('portal_success'))<div class="notice">{{ session('portal_success') }}</div>@endif
@if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif

<section class="card hero">
    <div class="eyebrow">Коммерческое предложение</div>
    <h1>№ {{ $document->number }}</h1>
    <div class="meta">
        <span class="status">{{ $statusLabels[$document->status] ?? $document->status }}</span>
        <span>от {{ $document->issued_at->format('d.m.Y') }}</span>
        @if($document->valid_until)<span>действительно до {{ $document->valid_until->format('d.m.Y') }}</span>@endif
        <span>{{ $preview ? 'Предпросмотр администратора' : $session->device_label }}</span>
    </div>
</section>

<div class="grid">
    <div>
        <section class="card section">
            <h2>Состав предложения</h2>
            @foreach($document->items as $item)
                @php
                    $snapshot = $item->portal_snapshot ?? [];
                    $catalogUrl = $snapshot['catalog_url'] ?? null;
                    $image = $snapshot['images'][0] ?? null;
                    $attributes = collect($snapshot['attributes'] ?? [])->take(10);
                    if ($attributes->isEmpty()) {
                        $attributes = collect($snapshot['specs'] ?? [])->map(function ($value, $key) {
                            if (is_array($value)) return ['name' => $value['name'] ?? $value['key'] ?? $key, 'value' => $value['value'] ?? ''];
                            return ['name' => $key, 'value' => $value];
                        })->filter(fn($row) => filled($row['value'] ?? null))->take(10);
                    }
                @endphp
                <article class="item">
                    @if($image)<img class="item-image" src="{{ $image }}" alt="">@else<div class="item-image"></div>@endif
                    <div>
                        <h3>
                            @if($catalogUrl)
                                <a class="item-title-link" href="{{ $catalogUrl }}" target="_blank" rel="noopener noreferrer">{{ $item->name }}</a>
                            @else
                                {{ $item->name }}
                            @endif
                        </h3>
                        <div class="sku">{{ $item->sku ? 'Артикул '.$item->sku.' · ' : '' }}{{ rtrim(rtrim(number_format((float)$item->quantity, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}</div>
                        @if($snapshot['short_description'] ?? null)<p>{{ strip_tags($snapshot['short_description']) }}</p>@endif
                        @if($attributes->isNotEmpty())
                            <div class="specs">
                                @foreach($attributes as $attribute)<div><span>{{ $attribute['name'] ?? '' }}</span><span>{{ $attribute['value'] ?? '' }}</span></div>@endforeach
                            </div>
                        @endif
                        @if($snapshot['warranty'] ?? null)<p class="small">Гарантия: {{ $snapshot['warranty'] }}</p>@endif
                        @if($catalogUrl)<a class="catalog-link" href="{{ $catalogUrl }}" target="_blank" rel="noopener noreferrer">Посмотреть товар на сайте <span aria-hidden="true">↗</span></a>@endif
                    </div>
                    <div class="price"><strong>{{ number_format((float)$item->line_total, 2, ',', ' ') }} {{ $document->currency }}</strong><span class="small">{{ number_format((float)$item->unit_price, 2, ',', ' ') }} за {{ $item->unit }}</span></div>
                </article>
            @endforeach
        </section>

        <section class="card section">
            <h2>Условия</h2>
            <div class="terms">
                <div><strong>Оплата</strong>{{ $document->payment_terms ?: 'Условия согласовываются с менеджером.' }}</div>
                <div><strong>Поставка</strong>@include('commercial-documents.delivery-terms')</div>
            </div>
            @if($document->notes)<p>{{ $document->notes }}</p>@endif
        </section>
    </div>

    <aside>
        <div class="card summary">
            <div class="sum-row"><span>Товары</span><strong>{{ number_format((float)$document->subtotal, 2, ',', ' ') }}</strong></div>
            @if((float)$document->discount_total > 0)<div class="sum-row"><span>Скидка</span><strong>− {{ number_format((float)$document->discount_total, 2, ',', ' ') }}</strong></div>@endif
            <div class="sum-row total"><span>Итого</span><span>{{ number_format((float)$document->total, 2, ',', ' ') }} {{ $document->currency }}</span></div>
            <a class="button secondary" href="{{ $preview ? route('admin.commercial-documents.pdf', $document) : route('commercial-portal.pdf', $link->public_id) }}" target="_blank">Открыть PDF</a>

            @if($preview)
                <div class="notice" style="margin-top:14px">Кнопки принятия, изменения и отказа доступны только клиенту.</div>
            @elseif($document->status === 'accepted' && $document->order)
                <div class="notice" style="margin-top:14px">Заказ {{ $document->order->number }} создан и передан менеджеру.</div>
            @elseif($actionable)
                <form method="post" action="{{ route('commercial-portal.accept', $link->public_id) }}">
                    @csrf
                    @if(blank($document->client_phone))<input type="text" name="client_phone" placeholder="Телефон для заказа" maxlength="64" required>@endif
                    <label class="confirm"><input type="checkbox" name="confirmation" value="1" required><span>Подтверждаю заказ на указанных условиях и сумму.</span></label>
                    <button class="button" type="submit">Принять предложение</button>
                </form>

                <details class="action-box"><summary>Запросить изменения</summary>
                    <form method="post" action="{{ route('commercial-portal.revision', $link->public_id) }}">@csrf
                        <textarea name="note" rows="4" maxlength="2000" placeholder="Что нужно изменить?" required></textarea>
                        <button class="button secondary" type="submit">Отправить менеджеру</button>
                    </form>
                </details>
                <details class="action-box"><summary>Отказаться от предложения</summary>
                    <form method="post" action="{{ route('commercial-portal.reject', $link->public_id) }}">@csrf
                        <select name="reason" required>
                            <option value="">Выберите причину</option><option value="price">Не устроила цена</option><option value="delivery_time">Не устроил срок поставки</option><option value="competitor">Выбран другой поставщик</option><option value="budget">Закупка отложена</option><option value="requirements_changed">Изменилась потребность</option><option value="other">Другая причина</option>
                        </select>
                        <textarea name="note" rows="3" maxlength="2000" placeholder="Комментарий (необязательно)"></textarea>
                        <button class="button danger" type="submit">Подтвердить отказ</button>
                    </form>
                </details>
            @elseif($document->status === 'needs_revision')
                <div class="notice">Менеджер получил запрос и подготовит новую редакцию.</div>
            @elseif($document->valid_until?->isPast())
                <div class="notice error">Срок предложения истёк. Попросите менеджера обновить его.</div>
            @endif

            <p class="small" style="margin-top:18px"><strong>{{ $document->manager?->name ?: 'Менеджер KOTLOV.BY' }}</strong><br>{{ $document->manager?->phone }}<br>{{ config('legal.commercial_documents.email') }}</p>
        </div>
    </aside>
</div>
@endsection
