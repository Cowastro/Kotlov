<x-filament-panels::page>
@php
    $counterparty = $this->getRecord();
    $classification = \App\Models\CrmCounterparty::CLASSIFICATIONS[$counterparty->classification] ?? $counterparty->classification;
    $businessUnit = \App\Models\CrmCounterparty::BUSINESS_UNITS[$counterparty->business_unit] ?? $counterparty->business_unit;
    $status = \App\Models\CrmCounterparty::STATUSES[$counterparty->status] ?? $counterparty->status;
    $source = \App\Models\CrmCounterparty::SOURCES[$counterparty->source] ?? $counterparty->source;
    $statusColor = match ($counterparty->status) {
        'active' => 'success', 'lead' => 'warning', 'paused' => 'info', 'inactive' => 'danger', default => 'gray',
    };
    $classificationColor = match ($counterparty->classification) {
        'b2b_partner' => 'success', 'b2b_prospect' => 'info', 'retail' => 'warning',
        'supplier' => 'primary', 'unclassified' => 'danger', default => 'gray',
    };
    $phones = $counterparty->contactPoints->where('type', 'phone')->where('is_active', true);
    $emails = $counterparty->contactPoints->where('type', 'email')->where('is_active', true);
    $formatMoney = fn ($value, $currency = 'BYN') => number_format((float) $value, 2, ',', ' ').' '.$currency;
    $documentStatusColor = fn ($value) => match ($value) {
        'accepted', 'paid' => 'success', 'sent' => 'info', 'needs_revision' => 'warning',
        'rejected', 'expired', 'cancelled' => 'danger', default => 'gray',
    };
    $orderStatusColor = fn ($value) => match ($value) {
        'delivered' => 'success', 'confirmed', 'processing', 'shipped' => 'info',
        'cancelled' => 'danger', 'new' => 'warning', default => 'gray',
    };
    $timeline = $this->getClientTimeline();
    $timelineUrl = fn (array $event) => match ($event['url_type'] ?? null) {
        'mail' => \App\Filament\Resources\CrmMailThreads\CrmMailThreadResource::getUrl('view', ['record' => $event['url_id']]),
        'document' => \App\Filament\Resources\CommercialDocuments\CommercialDocumentResource::getUrl('view', ['record' => $event['url_id']]),
        'order' => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $event['url_id']]),
        'call' => \App\Filament\Resources\TelephonyCalls\TelephonyCallResource::getUrl('view', ['record' => $event['url_id']]),
        default => null,
    };
@endphp

<style>
    .crm-profile { display: grid; gap: 16px; }
    .crm-card { overflow: hidden; border: 1px solid rgba(148,163,184,.18); border-radius: 14px; background: rgba(255,255,255,.025); }
    .crm-hero { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 22px; padding: 22px; background: linear-gradient(135deg, rgba(245,158,11,.11), rgba(255,255,255,.02) 48%); }
    .crm-hero-main { display: flex; gap: 16px; min-width: 0; }
    .crm-avatar { display: grid; place-items: center; width: 54px; height: 54px; flex: 0 0 54px; border-radius: 14px; background: rgba(245,158,11,.16); color: rgb(251,191,36); }
    .crm-name { margin: 0; color: rgb(248,250,252); font-size: 22px; font-weight: 750; line-height: 1.2; }
    .crm-legal { margin-top: 5px; color: rgb(148,163,184); font-size: 13px; }
    .crm-badges { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; }
    .crm-manager { min-width: 220px; padding-left: 22px; border-left: 1px solid rgba(148,163,184,.16); }
    .crm-eyebrow { color: rgb(148,163,184); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .crm-manager-name { margin-top: 6px; color: rgb(241,245,249); font-size: 14px; font-weight: 650; }
    .crm-manager-meta { margin-top: 6px; color: rgb(148,163,184); font-size: 12px; }
    .crm-kpis { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); border-top: 1px solid rgba(148,163,184,.14); }
    .crm-kpi { padding: 14px 18px; border-right: 1px solid rgba(148,163,184,.12); }
    .crm-kpi:last-child { border-right: 0; }
    .crm-kpi-value { color: rgb(248,250,252); font-size: 20px; font-weight: 750; }
    .crm-kpi-label { margin-top: 2px; color: rgb(148,163,184); font-size: 11px; }
    .crm-layout { display: grid; grid-template-columns: minmax(0,1.55fr) minmax(310px,.75fr); gap: 16px; align-items: start; }
    .crm-stack { display: grid; gap: 16px; }
    .crm-card-head { display: flex; align-items: center; gap: 9px; padding: 15px 18px; border-bottom: 1px solid rgba(148,163,184,.14); color: rgb(226,232,240); font-size: 14px; font-weight: 700; }
    .crm-card-head svg { width: 18px; height: 18px; color: rgb(251,191,36); }
    .crm-card-body { padding: 18px; }
    .crm-info-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 17px 24px; }
    .crm-info-wide { grid-column: 1 / -1; }
    .crm-label { margin-bottom: 5px; color: rgb(148,163,184); font-size: 11px; }
    .crm-value { color: rgb(226,232,240); font-size: 13px; line-height: 1.45; overflow-wrap: anywhere; }
    .crm-value strong { color: rgb(248,250,252); }
    .crm-contact-list { display: grid; gap: 9px; }
    .crm-contact-link { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 10px 12px; border: 1px solid rgba(148,163,184,.14); border-radius: 10px; background: rgba(15,23,42,.34); color: rgb(226,232,240); text-decoration: none; }
    .crm-contact-link:hover { border-color: rgba(245,158,11,.42); background: rgba(245,158,11,.07); }
    .crm-contact-link svg { width: 17px; height: 17px; flex: 0 0 17px; color: rgb(148,163,184); }
    .crm-contact-link span { overflow: hidden; text-overflow: ellipsis; }
    .crm-terms { display: grid; gap: 14px; }
    .crm-discount { color: rgb(251,191,36); font-size: 28px; font-weight: 800; }
    .crm-note { padding: 11px 12px; border-radius: 10px; background: rgba(245,158,11,.08); color: rgb(203,213,225); font-size: 12px; line-height: 1.5; }
    .crm-person { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(148,163,184,.11); }
    .crm-person:first-child { padding-top: 0; }
    .crm-person:last-child { padding-bottom: 0; border-bottom: 0; }
    .crm-person-name { color: rgb(241,245,249); font-size: 13px; font-weight: 700; }
    .crm-person-meta { margin-top: 3px; color: rgb(148,163,184); font-size: 11px; }
    .crm-person-contacts { display: grid; gap: 3px; text-align: right; font-size: 12px; }
    .crm-person-contacts a { color: rgb(203,213,225); text-decoration: none; }
    .crm-table-wrap { overflow-x: auto; }
    .crm-table { width: 100%; min-width: 620px; border-collapse: collapse; font-size: 12px; }
    .crm-table th { padding: 10px 14px; border-bottom: 1px solid rgba(148,163,184,.14); color: rgb(148,163,184); font-size: 10px; font-weight: 700; text-align: left; text-transform: uppercase; letter-spacing: .04em; }
    .crm-table td { padding: 12px 14px; border-bottom: 1px solid rgba(148,163,184,.09); color: rgb(203,213,225); }
    .crm-table tbody tr:last-child td { border-bottom: 0; }
    .crm-table-link { color: rgb(251,191,36); font-weight: 700; text-decoration: none; }
    .crm-table-number { text-align: right !important; white-space: nowrap; }
    .crm-timeline { display: grid; padding: 2px 18px 8px; }
    .crm-timeline-item { position: relative; display: grid; grid-template-columns: 34px minmax(0,1fr); gap: 12px; padding: 14px 0; }
    .crm-timeline-item:not(:last-child)::before { content: ''; position: absolute; top: 45px; bottom: -3px; left: 16px; width: 1px; background: rgba(148,163,184,.2); }
    .crm-timeline-icon { z-index: 1; display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(148,163,184,.2); border-radius: 50%; background: rgb(17,24,39); color: rgb(148,163,184); }
    .crm-timeline-icon svg { width: 17px; height: 17px; }
    .crm-timeline-icon[data-tone="warning"] { color: rgb(251,191,36); border-color: rgba(251,191,36,.35); }
    .crm-timeline-icon[data-tone="info"], .crm-timeline-icon[data-tone="primary"] { color: rgb(96,165,250); border-color: rgba(96,165,250,.35); }
    .crm-timeline-icon[data-tone="success"] { color: rgb(52,211,153); border-color: rgba(52,211,153,.35); }
    .crm-timeline-icon[data-tone="danger"] { color: rgb(248,113,113); border-color: rgba(248,113,113,.35); }
    .crm-timeline-main { min-width: 0; padding-top: 1px; }
    .crm-timeline-top { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
    .crm-timeline-title { color: rgb(241,245,249); font-size: 13px; font-weight: 700; line-height: 1.4; }
    .crm-timeline-title:hover { color: rgb(251,191,36); }
    .crm-timeline-date { flex: 0 0 auto; color: rgb(100,116,139); font-size: 10px; white-space: nowrap; }
    .crm-timeline-summary { margin-top: 5px; color: rgb(148,163,184); font-size: 12px; line-height: 1.5; }
    .crm-timeline-meta { margin-top: 6px; color: rgb(100,116,139); font-size: 10px; }
    .crm-empty { display: grid; justify-items: center; gap: 7px; padding: 28px 18px; color: rgb(148,163,184); text-align: center; }
    .crm-empty svg { width: 28px; height: 28px; opacity: .65; }
    .crm-empty strong { color: rgb(203,213,225); font-size: 13px; }
    .crm-empty span { max-width: 420px; font-size: 11px; line-height: 1.45; }
    @media (max-width: 1100px) { .crm-kpis { grid-template-columns: repeat(3,1fr); } .crm-kpi:nth-child(3) { border-right: 0; } .crm-kpi:nth-child(-n+3) { border-bottom: 1px solid rgba(148,163,184,.12); } }
    @media (max-width: 850px) { .crm-layout { grid-template-columns: 1fr; } .crm-hero { grid-template-columns: 1fr; } .crm-manager { padding: 14px 0 0; border-left: 0; border-top: 1px solid rgba(148,163,184,.16); } }
    @media (max-width: 560px) { .crm-hero { padding: 17px; } .crm-avatar { width: 44px; height: 44px; flex-basis: 44px; } .crm-name { font-size: 18px; } .crm-kpis { grid-template-columns: repeat(2,1fr); } .crm-kpi:nth-child(2n) { border-right: 0; } .crm-kpi:nth-child(4) { border-bottom: 1px solid rgba(148,163,184,.12); } .crm-info-grid { grid-template-columns: 1fr; } .crm-info-wide { grid-column: auto; } }
</style>

<div class="crm-profile">
    <section class="crm-card">
        <div class="crm-hero">
            <div class="crm-hero-main">
                <div class="crm-avatar">
                    <x-heroicon-o-building-office-2 class="h-7 w-7"/>
                </div>
                <div class="min-w-0">
                    <h2 class="crm-name">{{ $counterparty->public_name ?: $counterparty->name }}</h2>
                    <div class="crm-legal">{{ $counterparty->legal_name ?: 'Юридическое название не заполнено' }}</div>
                    <div class="crm-badges">
                        <x-filament::badge :color="$statusColor">{{ $status }}</x-filament::badge>
                        <x-filament::badge :color="$classificationColor">{{ $classification }}</x-filament::badge>
                        <x-filament::badge color="gray">{{ $businessUnit }}</x-filament::badge>
                    </div>
                </div>
            </div>
            <div class="crm-manager">
                <div class="crm-eyebrow">Ответственный менеджер</div>
                <div class="crm-manager-name">{{ $counterparty->responsibleUser?->name ?: 'Не назначен' }}</div>
                <div class="crm-manager-meta">Источник: {{ $source }}</div>
                <div class="crm-manager-meta">Обновлено {{ $counterparty->updated_at?->timezone('Europe/Minsk')->format('d.m.Y в H:i') }}</div>
            </div>
        </div>
        <div class="crm-kpis">
            @foreach ([
                ['value' => $counterparty->contacts_count, 'label' => 'контактных лиц'],
                ['value' => $counterparty->calls_count, 'label' => 'звонков'],
                ['value' => $counterparty->mail_threads_count, 'label' => 'переписок'],
                ['value' => $counterparty->commercial_documents_count, 'label' => 'КП и счетов'],
                ['value' => $counterparty->orders_count, 'label' => 'заказов'],
                ['value' => $counterparty->external_identities_count, 'label' => 'связей с 1С'],
            ] as $kpi)
                <div class="crm-kpi"><div class="crm-kpi-value">{{ $kpi['value'] }}</div><div class="crm-kpi-label">{{ $kpi['label'] }}</div></div>
            @endforeach
        </div>
    </section>

    <div class="crm-layout">
        <div class="crm-stack">
            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-identification/>Реквизиты и контакты</div>
                <div class="crm-card-body crm-info-grid">
                    <div><div class="crm-label">Название</div><div class="crm-value"><strong>{{ $counterparty->name }}</strong></div></div>
                    <div><div class="crm-label">УНП / ИНН</div><div class="crm-value">{{ $counterparty->unp ?: 'Не указан' }}</div></div>
                    @if($counterparty->website)
                        <div><div class="crm-label">Сайт</div><div class="crm-value"><a class="crm-table-link" href="{{ $counterparty->website }}" target="_blank" rel="noopener">{{ $counterparty->website }}</a></div></div>
                    @endif
                    <div><div class="crm-label">Менеджер в 1С</div><div class="crm-value">{{ $counterparty->one_c_manager_name ?: 'Не указан' }}</div></div>
                    <div class="crm-info-wide"><div class="crm-label">Юридический адрес</div><div class="crm-value">{{ $counterparty->legal_address ?: 'Не заполнен' }}</div></div>
                    @if($counterparty->actual_address && $counterparty->actual_address !== $counterparty->legal_address)
                        <div class="crm-info-wide"><div class="crm-label">Фактический адрес</div><div class="crm-value">{{ $counterparty->actual_address }}</div></div>
                    @endif
                    @if($counterparty->notes)
                        <div class="crm-info-wide"><div class="crm-label">Общая информация</div><div class="crm-value">{{ $counterparty->notes }}</div></div>
                    @endif
                </div>
            </section>

            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-users/>Контактные лица</div>
                <div class="crm-card-body">
                    @forelse($counterparty->contacts as $contact)
                        <div class="crm-person">
                            <div>
                                <div class="crm-person-name">{{ $contact->full_name }} @if($contact->is_primary)<x-filament::badge color="warning">Основной</x-filament::badge>@endif</div>
                                <div class="crm-person-meta">{{ $contact->position ?: 'Должность не указана' }}</div>
                            </div>
                            <div class="crm-person-contacts">
                                @foreach($contact->contactPoints->where('is_active', true) as $point)
                                    <a href="{{ $point->type === 'phone' ? 'tel:'.preg_replace('/[^+\d]/', '', $point->value) : 'mailto:'.$point->value }}">{{ $point->value }}</a>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="crm-empty"><x-heroicon-o-user-plus/><strong>Контактных лиц пока нет</strong><span>Добавьте имя, должность и прямые контакты при следующем общении.</span></div>
                    @endforelse
                </div>
            </section>

            <section class="crm-card" id="client-timeline">
                <div class="crm-card-head"><x-heroicon-o-clock/>Единая цепочка клиента</div>
                @if($timeline->isNotEmpty())
                    <div class="crm-timeline">
                        @foreach($timeline as $event)
                            @php($eventUrl = $timelineUrl($event))
                            <article class="crm-timeline-item">
                                <div class="crm-timeline-icon" data-tone="{{ $event['tone'] }}">
                                    <x-filament::icon :icon="$event['icon']" />
                                </div>
                                <div class="crm-timeline-main">
                                    <div class="crm-timeline-top">
                                        @if($eventUrl)
                                            <a class="crm-timeline-title" href="{{ $eventUrl }}">{{ $event['title'] }}</a>
                                        @else
                                            <div class="crm-timeline-title">{{ $event['title'] }}</div>
                                        @endif
                                        <time class="crm-timeline-date">{{ $event['date']->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</time>
                                    </div>
                                    @if($event['summary'])<div class="crm-timeline-summary">{{ $event['summary'] }}</div>@endif
                                    @if($event['meta'])<div class="crm-timeline-meta">{{ $event['meta'] }}</div>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="crm-empty"><x-heroicon-o-clock/><strong>История пока пуста</strong><span>Письма, звонки, КП, заказы и действия менеджеров будут собираться здесь одной цепочкой.</span></div>
                @endif
            </section>

            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-document-text/>Коммерческие предложения и счета</div>
                @if($counterparty->commercialDocuments->isNotEmpty())
                    <div class="crm-table-wrap"><table class="crm-table">
                        <thead><tr><th>Документ</th><th>Дата</th><th>Тип</th><th>Статус</th><th class="crm-table-number">Сумма</th></tr></thead>
                        <tbody>
                        @foreach($counterparty->commercialDocuments->take(10) as $document)
                            <tr>
                                <td><a class="crm-table-link" href="{{ \App\Filament\Resources\CommercialDocuments\CommercialDocumentResource::getUrl('view', ['record' => $document]) }}">{{ $document->number }}</a></td>
                                <td>{{ $document->issued_at?->format('d.m.Y') ?: '—' }}</td>
                                <td>{{ \App\Models\CommercialDocument::TYPES[$document->type] ?? $document->type }}</td>
                                <td><x-filament::badge :color="$documentStatusColor($document->status)">{{ \App\Models\CommercialDocument::STATUSES[$document->status] ?? $document->status }}</x-filament::badge></td>
                                <td class="crm-table-number">{{ $formatMoney($document->total, $document->currency ?: 'BYN') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @else
                    <div class="crm-empty"><x-heroicon-o-document-plus/><strong>Документов пока нет</strong><span>Создайте первое коммерческое предложение кнопкой вверху страницы.</span></div>
                @endif
            </section>

            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-shopping-cart/>Заказы клиента</div>
                @if($counterparty->orders->isNotEmpty())
                    <div class="crm-table-wrap"><table class="crm-table">
                        <thead><tr><th>Заказ</th><th>Дата</th><th>Статус</th><th>Оплата</th><th class="crm-table-number">Сумма</th></tr></thead>
                        <tbody>
                        @foreach($counterparty->orders->take(10) as $order)
                            <tr>
                                <td><a class="crm-table-link" href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order]) }}">{{ $order->number }}</a></td>
                                <td>{{ $order->created_at?->timezone('Europe/Minsk')->format('d.m.Y H:i') }}</td>
                                <td><x-filament::badge :color="$orderStatusColor($order->status)">{{ \App\Models\Order::STATUSES[$order->status] ?? $order->status }}</x-filament::badge></td>
                                <td>{{ $order->paymentStatusLabel() }}</td>
                                <td class="crm-table-number">{{ $formatMoney($order->total) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @else
                    <div class="crm-empty"><x-heroicon-o-shopping-cart/><strong>Заказов пока нет</strong><span>Заказы этого контрагента будут собраны здесь в одной истории.</span></div>
                @endif
            </section>
        </div>

        <aside class="crm-stack">
            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-phone/>Быстрая связь</div>
                <div class="crm-card-body crm-contact-list">
                    @foreach($phones as $phone)
                        <a class="crm-contact-link" href="tel:{{ preg_replace('/[^+\d]/', '', $phone->value) }}"><x-heroicon-o-phone/><span>{{ $phone->value }}{{ $phone->label ? ' · '.$phone->label : '' }}</span></a>
                    @endforeach
                    @foreach($emails as $email)
                        <a class="crm-contact-link" href="mailto:{{ $email->value }}"><x-heroicon-o-envelope/><span>{{ $email->value }}{{ $email->label ? ' · '.$email->label : '' }}</span></a>
                    @endforeach
                    @if($phones->isEmpty() && $emails->isEmpty())
                        <div class="crm-empty"><x-heroicon-o-phone-x-mark/><strong>Контакты не заполнены</strong><span>Добавьте общий телефон или email организации.</span></div>
                    @endif
                </div>
            </section>

            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-banknotes/>Коммерческие условия</div>
                <div class="crm-card-body crm-terms">
                    <div><div class="crm-label">Стандартная скидка</div><div class="crm-discount">{{ number_format((float) $counterparty->commercial_discount_percent, 2, ',', ' ') }}%</div></div>
                    <div><div class="crm-label">Валюта документов</div><div class="crm-value"><strong>{{ $counterparty->commercial_currency ?: 'BYN' }}</strong></div></div>
                    <div><div class="crm-label">Условия оплаты</div><div class="crm-value">{{ $counterparty->commercial_payment_terms ?: 'Используются общие условия' }}</div></div>
                    <div><div class="crm-label">Условия поставки</div><div class="crm-value">{{ $counterparty->commercial_delivery_terms ?: 'Используются общие условия' }}</div></div>
                    @if($counterparty->commercial_note)
                        <div class="crm-note"><strong>Менеджеру:</strong><br>{{ $counterparty->commercial_note }}</div>
                    @endif
                </div>
            </section>

            <section class="crm-card">
                <div class="crm-card-head"><x-heroicon-o-link/>Учётные данные</div>
                <div class="crm-card-body crm-terms">
                    <div><div class="crm-label">Бизнес-регион 1С</div><div class="crm-value">{{ $counterparty->business_region ?: 'Не указан' }}</div></div>
                    <div><div class="crm-label">Внешний идентификатор 1С</div><div class="crm-value">{{ $counterparty->external_1c_id ?: 'Не указан' }}</div></div>
                    <div><div class="crm-label">Дата регистрации</div><div class="crm-value">{{ $counterparty->registered_at?->format('d.m.Y') ?: 'Не указана' }}</div></div>
                </div>
            </section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
