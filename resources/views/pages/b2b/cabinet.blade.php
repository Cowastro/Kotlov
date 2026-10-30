@extends('layouts.amerce')

@section('title', 'Кабинет партнёра — KOTLOV B2B')
@section('robots', 'noindex, nofollow')
@section('body-class', 'b2b-body')

@section('content')
@php
    $cart = collect(session('cart', []));
    $cartCount = $cart->sum('quantity');
    $sectionUrl = fn (string $name) => route('b2b.cabinet', ['section' => $name]);
    $statusLabels = \App\Models\Order::STATUSES;
    $documentStatuses = \App\Models\CommercialDocument::STATUSES;
    $contractStatuses = \App\Models\CommercialContract::STATUSES;
    $can = fn (string $permission) => $permissions->contains($permission);
@endphp

<div class="b2b-portal b2b-cabinet">
    <header class="b2b-cabinet-topbar">
        <div class="b2b-cabinet-topbar__inner">
            <a href="{{ $can('catalog') ? route('b2b.catalog') : $sectionUrl('overview') }}" class="b2b-cabinet-brand">
                <img src="{{ asset('img/logo.svg') }}" width="126" height="26" alt="KOTLOV"><span>B2B</span>
            </a>
            <nav aria-label="B2B-навигация">
                @if($can('catalog'))<a href="{{ route('b2b.catalog') }}"><i class="icon icon-SquaresFour"></i> Каталог</a>@endif
                @if($can('order'))<a href="{{ route('cart') }}"><i class="icon icon-Handbag"></i> Корзина <b>{{ $cartCount }}</b></a>@endif
                <form action="{{ route('logout') }}" method="POST">@csrf<button type="submit">Выйти</button></form>
            </nav>
        </div>
    </header>

    <main class="b2b-cabinet-shell">
        @if (session('success'))<div class="b2b-cabinet-alert is-success">{{ session('success') }}</div>@endif
        @if ($errors->any())
            <div class="b2b-cabinet-alert is-error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <section class="b2b-cabinet-welcome">
            <div><span>Кабинет партнёра · {{ \App\Models\B2bClientMember::ROLES[$role] ?? $role }}</span><h1>{{ $client->company_name }}</h1><p>Заказы, документы и реквизиты компании в одном месте.</p></div>
            @if($can('catalog'))<a href="{{ route('b2b.catalog') }}">Открыть B2B-каталог <span>→</span></a>@endif
        </section>

        <div class="b2b-cabinet-layout">
            <aside class="b2b-cabinet-nav">
                <div class="b2b-cabinet-nav__company"><small>{{ $client->partner_type === 'installer' ? 'Монтажная организация' : 'Оптовый покупатель' }}</small><strong>{{ $client->company_name }}</strong>@if($client->unp)<span>УНП {{ $client->unp }}</span>@endif</div>
                @foreach ([
                    'overview' => ['icon-House', 'Главная', null],
                    'profile' => ['icon-User', 'Компания и реквизиты', 'profile'],
                    'team' => ['icon-Users', 'Сотрудники и роли', 'team'],
                    'specifications' => ['icon-FileText', 'Спецификации', 'specifications'],
                    'carts' => ['icon-Handbag', 'Сохранённые корзины', 'order'],
                    'orders' => ['icon-Package', 'История заказов', 'documents'],
                    'documents' => ['icon-FileText', 'КП и счета', 'documents'],
                    'contracts' => ['icon-Note', 'Договоры', 'documents'],
                    'reconciliation' => ['icon-ChartBar', 'Сверка взаиморасчётов', 'finance'],
                    'security' => ['icon-ShieldCheck', 'Безопасность', null],
                ] as $key => [$icon, $label, $permission])
                    @continue($permission && !$can($permission))
                    <a href="{{ $sectionUrl($key) }}" class="{{ $section === $key ? 'active' : '' }}"><i class="icon {{ $icon }}"></i><span>{{ $label }}</span>@if($key === 'carts' && $savedCarts->count())<b>{{ $savedCarts->count() }}</b>@elseif($key === 'specifications' && $specifications->total())<b>{{ $specifications->total() }}</b>@endif</a>
                @endforeach
                <a href="{{ route('account') }}" class="b2b-cabinet-nav__retail"><i class="icon icon-ArrowLeft"></i><span>Обычный личный кабинет</span></a>
            </aside>

            <section class="b2b-cabinet-content">
                @if ($section === 'overview')
                    <div class="b2b-cabinet-heading"><div><span>Обзор</span><h2>Рабочий стол</h2></div><p>Актуальная информация по вашей компании</p></div>
                    <article class="b2b-profile-progress {{ $profileCompletion === 100 ? 'is-complete' : '' }}">
                        <div class="b2b-profile-progress__copy">
                            <span>{{ $profileCompletion === 100 ? 'Готово к документам' : 'Заполните реквизиты компании' }}</span>
                            <strong>{{ $profileCompletion }}%</strong>
                            <p>{{ $profileCompletion === 100 ? 'Данных достаточно для автоматического формирования счетов и договоров.' : 'После заполнения менеджер сможет быстрее готовить счета и договоры без уточняющих звонков.' }}</p>
                            @if($profileMissing)<small>Не хватает: {{ implode(', ', array_slice($profileMissing, 0, 4)) }}{{ count($profileMissing) > 4 ? ' и ещё '.(count($profileMissing) - 4) : '' }}.</small>@endif
                        </div>
                        <div class="b2b-profile-progress__side"><div class="b2b-profile-progress__meter"><i style="width:{{ $profileCompletion }}%"></i></div>@if($can('profile'))<a href="{{ $sectionUrl('profile') }}">{{ $profileCompletion === 100 ? 'Проверить данные' : 'Дозаполнить реквизиты' }} →</a>@endif</div>
                    </article>
                    <article class="b2b-commercial-profile">
                        <div class="b2b-commercial-profile__intro">
                            <span>Ваши условия</span>
                            <h3>{{ $client->priceLevel?->name ?: 'Уровень цены назначается' }}</h3>
                            <p><strong>{{ $client->serviceModeLabel() }}.</strong> Специальная цена действует при самостоятельном выборе и оформлении. В неё входят комплектация, документы и организация доставки.</p>
                            @if($client->specification_service_enabled && $can('specifications'))<a href="{{ $sectionUrl('specifications') }}" class="b2b-commercial-profile__service"><i class="icon icon-FileText"></i><div><strong>Есть готовая спецификация?</strong><small>Загрузите PDF, Excel или фотографию. Статус обработки останется в кабинете.</small></div><b>Загрузить →</b></a>@endif
                        </div>
                        <dl class="b2b-commercial-profile__terms">
                            <div><dt>Доступные прайсы</dt><dd>{{ $allowedSources->isNotEmpty() ? $allowedSources->join(', ') : 'Назначаются менеджером' }}</dd></div>
                            <div><dt>Доступные склады</dt><dd>{{ $allowedWarehouses->isNotEmpty() ? $allowedWarehouses->map(fn($warehouse) => $warehouse->name ?: $warehouse->external_id)->join(', ') : 'В рамках доступного прайса' }}</dd></div>
                            <div><dt>Партнёрские склады</dt><dd>{{ $allowedPartnerSuppliers->isNotEmpty() ? $allowedPartnerSuppliers->map(fn($supplier) => $supplier->b2b_client_warehouse_label ?: $supplier->name)->join(', ') : 'Не подключены' }}</dd></div>
                            <div><dt>Оплата</dt><dd>{{ $client->payment_terms ?: 'По согласованию с менеджером' }}</dd></div>
                            <div><dt>Доставка</dt><dd>{{ $client->delivery_terms ?: 'Рассчитывается при оформлении' }}</dd></div>
                            <div><dt>Минимальный заказ</dt><dd>{{ $client->minimum_order_amount !== null ? number_format((float) $client->minimum_order_amount, 2, ',', ' ').' BYN' : 'Без установленного минимума' }}</dd></div>
                            <div><dt>Отсрочка</dt><dd>{{ $client->payment_deferment_days !== null ? $client->payment_deferment_days.' дней' : 'Не предоставлена' }}</dd></div>
                            @if($creditSummary['enabled'])
                                <div><dt>Кредитный лимит</dt><dd>Доступно {{ number_format((float)$creditSummary['available'], 2, ',', ' ') }} из {{ number_format((float)$creditSummary['limit'], 2, ',', ' ') }} BYN</dd></div>
                            @elseif($creditSummary['deferment_days'] > 0)
                                <div class="is-warning"><dt>Кредитный лимит</dt><dd>{{ $creditSummary['issue'] ?: 'Отсрочка временно недоступна' }}</dd></div>
                            @elseif($client->credit_limit !== null)
                                <div><dt>Кредитный лимит</dt><dd>{{ number_format((float) $client->credit_limit, 2, ',', ' ') }} BYN · без отсрочки</dd></div>
                            @endif
                            @if($client->commercial_terms_valid_until)<div class="{{ $client->commercial_terms_valid_until->isBefore(today()) ? 'is-expired' : '' }}"><dt>Условия действуют до</dt><dd>{{ $client->commercial_terms_valid_until->format('d.m.Y') }}{{ $client->commercial_terms_valid_until->isBefore(today()) ? ' · требуется продление' : '' }}</dd></div>@endif
                        </dl>
                    </article>
                    <div class="b2b-cabinet-stats">
                        @if($can('documents'))<a href="{{ $sectionUrl('orders') }}"><i class="icon icon-Package"></i><span>Заказы</span><strong>{{ $orders->total() }}</strong><small>на {{ number_format($companyOrderTotal, 2, ',', ' ') }} BYN</small></a>@endif
                        @if($can('documents'))
                        <a href="{{ $sectionUrl('documents') }}"><i class="icon icon-FileText"></i><span>КП и счета</span><strong>{{ $documents->total() }}</strong><small>доступно в кабинете</small></a>
                        @endif
                        @if($can('specifications'))
                        <a href="{{ $sectionUrl('specifications') }}"><i class="icon icon-FileText"></i><span>Спецификации</span><strong>{{ $specifications->total() }}</strong><small>загружено вами</small></a>
                        @endif
                        @if($can('documents'))
                        <a href="{{ $sectionUrl('contracts') }}"><i class="icon icon-Note"></i><span>Договоры</span><strong>{{ $contracts->count() }}</strong><small>{{ $contracts->where('status', 'signed')->count() }} подписано</small></a>
                        @endif
                        @if($can('order'))
                        <a href="{{ $sectionUrl('carts') }}"><i class="icon icon-Handbag"></i><span>Корзины</span><strong>{{ $savedCarts->count() }}</strong><small>{{ $cartCount }} товаров сейчас</small></a>
                        @endif
                    </div>
                    <div class="b2b-cabinet-grid">
                        <article class="b2b-cabinet-card"><div class="b2b-cabinet-card__head"><h3>Последние заказы</h3><a href="{{ $sectionUrl('orders') }}">Все заказы</a></div>@forelse($orders->take(4) as $order)<a class="b2b-cabinet-row" href="{{ $order->customerDetailsAreAvailable() ? route('account.orders.show', $order) : $sectionUrl('orders') }}"><span><strong>{{ $order->number }}</strong><small>{{ $order->created_at?->format('d.m.Y') }}</small></span><span>{{ $statusLabels[$order->status] ?? $order->status }}</span><b>{{ number_format((float) $order->total, 2, ',', ' ') }} BYN</b></a>@empty<p class="b2b-cabinet-empty">Заказов пока нет.@if($can('catalog')) Начните с B2B-каталога.@endif</p>@endforelse</article>
                        <article class="b2b-cabinet-card"><div class="b2b-cabinet-card__head"><h3>Данные компании</h3>@if($can('profile'))<a href="{{ $sectionUrl('profile') }}">Изменить</a>@endif</div><dl class="b2b-cabinet-details"><div><dt>Компания</dt><dd>{{ $client->company_name }}</dd></div><div><dt>УНП</dt><dd>{{ $client->unp ?: 'Не указан' }}</dd></div><div><dt>Контакт</dt><dd>{{ $client->contact_name ?: $user->name }}</dd></div><div><dt>Условия оплаты</dt><dd>{{ $client->payment_terms ?: 'По согласованию с менеджером' }}</dd></div></dl></article>
                    </div>
                @elseif ($section === 'profile')
                    <div class="b2b-cabinet-heading"><div><span>Профиль</span><h2>Компания и реквизиты</h2></div><p>Эти данные используются в КП, счетах и договорах</p></div>
                    <article class="b2b-profile-progress {{ $profileCompletion === 100 ? 'is-complete' : '' }}">
                        <div class="b2b-profile-progress__copy"><span>Готовность реквизитов</span><strong>{{ $profileCompletion }}%</strong><p>{{ $profileCompletion === 100 ? 'Все данные для счетов и договоров заполнены.' : 'Можно сохранять данные поэтапно. Поля со звёздочкой нужны для базовой карточки клиента.' }}</p></div>
                        <div class="b2b-profile-progress__side"><div class="b2b-profile-progress__meter"><i style="width:{{ $profileCompletion }}%"></i></div>@if($profileMissing)<small>Осталось заполнить полей: {{ count($profileMissing) }}</small>@else<small>Реквизиты заполнены полностью</small>@endif</div>
                    </article>
                    <form method="POST" action="{{ route('b2b.cabinet.profile') }}" class="b2b-cabinet-form" data-b2b-profile data-company-lookup-url="{{ route('b2b.cabinet.company-lookup') }}">@csrf @method('PUT')
                        <section class="b2b-form-section">
                            <div class="b2b-form-section__head"><div><h3>Данные организации</h3><p>Введите УНП — название и юридический адрес заполнятся из реестра МНС.</p></div></div>
                            <div class="b2b-cabinet-form__grid">
                                <label><span>УНП *</span><div class="b2b-unp-row"><input name="unp" value="{{ old('unp', $client->unp) }}" inputmode="numeric" maxlength="9" required data-company-unp><button type="button" data-company-lookup>Заполнить по УНП</button></div><small data-company-lookup-status>Проверим 9 цифр в реестре и подставим доступные данные.</small></label>
                                <label><span>Краткое название *</span><input name="company_name" value="{{ old('company_name', $client->company_name) }}" required data-company-short-name></label>
                                <label class="wide"><span>Полное юридическое название</span><input name="legal_name" value="{{ old('legal_name', $counterparty?->legal_name) }}" data-company-legal-name></label>
                                <label class="wide"><span>Юридический адрес</span><input name="legal_address" value="{{ old('legal_address', $counterparty?->legal_address) }}" data-company-legal-address></label>
                                <label class="wide"><span>Почтовый адрес</span><input name="postal_address" value="{{ old('postal_address', $counterparty?->postal_address) }}"><small><button type="button" class="b2b-inline-action" data-copy-legal-address>Совпадает с юридическим адресом</button></small></label>
                            </div>
                        </section>
                        <section class="b2b-form-section">
                            <div class="b2b-form-section__head"><div><h3>Контактное лицо</h3><p>Кому менеджер направляет счета, договоры и информацию по заказам.</p></div></div>
                            <div class="b2b-cabinet-form__grid">
                                <label><span>Имя и фамилия *</span><input name="contact_name" value="{{ old('contact_name', $client->contact_name ?: $user->name) }}" autocomplete="name" required></label>
                                <label><span>Телефон *</span><input type="tel" name="contact_phone" value="{{ old('contact_phone', $client->contact_phone ?: $user->phone) }}" autocomplete="tel" required></label>
                                <label class="wide"><span>Email *</span><input type="email" name="contact_email" value="{{ old('contact_email', $client->contact_email ?: $user->email) }}" autocomplete="email" required></label>
                            </div>
                        </section>
                        <section class="b2b-form-section">
                            <div class="b2b-form-section__head"><div><h3>Банковские реквизиты</h3><p>Нужны для автоматического заполнения договоров и бухгалтерских документов.</p></div><span>Заполняются вручную</span></div>
                            <div class="b2b-cabinet-form__grid">
                                <label class="wide"><span>Наименование банка</span><input name="bank_name" value="{{ old('bank_name', $counterparty?->bank_name) }}" placeholder="Например, ОАО «Приорбанк»"></label>
                                <label><span>BIC банка</span><input name="bank_bic" value="{{ old('bank_bic', $counterparty?->bank_bic) }}" maxlength="11" placeholder="PJCBBY2X" data-uppercase></label>
                                <label><span>IBAN / расчётный счёт</span><input name="bank_iban" value="{{ old('bank_iban', $counterparty?->bank_iban) }}" maxlength="28" placeholder="BY00AAAA00000000000000000000" data-uppercase></label>
                            </div>
                        </section>
                        <section class="b2b-form-section">
                            <div class="b2b-form-section__head"><div><h3>Подписант</h3><p>Представитель организации, который подписывает договор.</p></div></div>
                            <div class="b2b-cabinet-form__grid">
                                <label><span>ФИО подписанта</span><input name="authorized_representative" value="{{ old('authorized_representative', $counterparty?->authorized_representative) }}" placeholder="Иванов Иван Иванович"></label>
                                <label><span>Должность</span><input name="representative_position" value="{{ old('representative_position', $counterparty?->representative_position) }}" placeholder="Директор"></label>
                                <label class="wide"><span>Действует на основании</span><input name="authority_basis" value="{{ old('authority_basis', $counterparty?->authority_basis) }}" placeholder="Устава или доверенности №…"></label>
                            </div>
                        </section>
                        <div class="b2b-form-submit"><div><strong>Сохраните изменения</strong><span>Обновлённые данные сразу будут доступны вашему менеджеру.</span></div><button class="b2b-cabinet-primary" type="submit">Сохранить реквизиты</button></div>
                    </form>
                @elseif ($section === 'team')
                    <div class="b2b-cabinet-heading"><div><span>Компания</span><h2>Сотрудники и роли</h2></div><p>Доступы разделены по рабочим задачам</p></div>
                    <article class="b2b-cabinet-card b2b-team-intro"><strong>Один кабинет — одна компания</strong><p>Закупщик работает с каталогом и заказами, бухгалтер — с документами и сверкой, наблюдатель получает доступ только для чтения. Кредитный лимит и история заказов общие для всей компании.</p></article>
                    <div class="b2b-team-list">
                        @foreach($members as $member)
                            <article class="b2b-cabinet-card b2b-team-member">
                                <div><strong>{{ $member->user?->name ?: $member->user?->email }}</strong><span>{{ $member->user?->email }}</span></div>
                                @if($member->role === 'owner')
                                    <b>{{ $member->roleLabel() }}</b>
                                @elseif($member->is_active)
                                    <div class="b2b-team-member__actions">
                                        <form method="POST" action="{{ route('b2b.cabinet.team.update', $member) }}">@csrf @method('PATCH')<select name="role" aria-label="Роль {{ $member->user?->name }}"><option value="buyer" @selected($member->role === 'buyer')>Закупщик</option><option value="accountant" @selected($member->role === 'accountant')>Бухгалтер</option><option value="viewer" @selected($member->role === 'viewer')>Наблюдатель</option></select><button type="submit">Сохранить роль</button></form>
                                        <form method="POST" action="{{ route('b2b.cabinet.team.destroy', $member) }}">@csrf @method('DELETE')<button type="submit" class="is-danger" onclick="return confirm('Отключить доступ и завершить активные сессии сотрудника?')">Отключить</button></form>
                                    </div>
                                @else
                                    <b>Доступ отключён</b>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('b2b.cabinet.team.store') }}" class="b2b-cabinet-form b2b-team-add">@csrf
                        <h3>Пригласить сотрудника</h3><p>Мы отправим фирменное письмо с одноразовой ссылкой. Новый сотрудник зарегистрируется прямо по ней, существующий — войдёт в свой аккаунт.</p>
                        <div class="b2b-cabinet-form__grid"><label><span>Email *</span><input type="email" name="email" value="{{ old('email') }}" required></label><label><span>Роль *</span><select name="role" required><option value="buyer">Закупщик</option><option value="accountant">Бухгалтер</option><option value="viewer">Наблюдатель</option></select></label></div>
                        <button class="b2b-cabinet-primary" type="submit">Отправить приглашение</button>
                    </form>
                    @if($pendingInvitations->isNotEmpty())
                        <section class="b2b-team-subsection"><div class="b2b-cabinet-heading"><div><span>Ожидают ответа</span><h3>Активные приглашения</h3></div><p>Ссылка действует 7 дней</p></div><div class="b2b-team-list">@foreach($pendingInvitations as $invitation)<article class="b2b-cabinet-card b2b-team-member"><div><strong>{{ $invitation->email }}</strong><span>До {{ $invitation->expires_at->format('d.m.Y H:i') }}</span></div><b>{{ $invitation->roleLabel() }}</b><form method="POST" action="{{ route('b2b.cabinet.invitations.destroy', $invitation) }}">@csrf @method('DELETE')<button type="submit" class="is-danger">Отозвать</button></form></article>@endforeach</div></section>
                    @endif
                    @php($auditLabels = ['invitation_created' => 'Создано приглашение', 'invitation_accepted' => 'Приглашение принято', 'invitation_revoked' => 'Приглашение отозвано', 'role_changed' => 'Изменена роль', 'access_revoked' => 'Доступ отключён'])
                    <section class="b2b-team-subsection"><div class="b2b-cabinet-heading"><div><span>Безопасность</span><h3>Журнал доступа</h3></div><p>Последние 30 действий</p></div><div class="b2b-team-audit">@forelse($auditLogs as $log)<article><time>{{ $log->created_at?->format('d.m.Y H:i') }}</time><div><strong>{{ $auditLabels[$log->event] ?? $log->event }}</strong><span>{{ $log->target?->email ?: ($log->metadata['email'] ?? '') }}</span></div><small>{{ $log->actor?->name ?: 'Система' }}</small></article>@empty<p>Действий пока нет.</p>@endforelse</div></section>
                @elseif ($section === 'specifications')
                    <div class="b2b-cabinet-heading"><div><span>Комплектация</span><h2>Спецификации</h2></div><p>Передайте готовый перечень без обязательной индивидуальной консультации</p></div>
                    @if($client->specification_service_enabled)
                        <form method="POST" action="{{ route('b2b.cabinet.specifications.store') }}" enctype="multipart/form-data" class="b2b-specification-upload">@csrf
                            <div class="b2b-specification-upload__copy"><span>Новая заявка</span><h3>Загрузить спецификацию</h3><p>Мы сопоставим позиции с доступным ассортиментом, проверим остатки и подготовим предложение. Инженерный расчёт объекта в эту услугу не входит.</p></div>
                            <div class="b2b-specification-upload__fields">
                                <label><span>Название объекта или закупки *</span><input name="title" value="{{ old('title') }}" maxlength="255" placeholder="Например, котельная на ул. Центральной" required></label>
                                <label><span>Комментарий</span><textarea name="comment" maxlength="3000" rows="3" placeholder="Срок, желаемая доставка или допустимые аналоги">{{ old('comment') }}</textarea></label>
                                <label class="b2b-specification-file"><span>Файлы спецификации *</span><input type="file" name="files[]" accept=".pdf,.xlsx,.xls,.csv,.ods,.png,.jpg,.jpeg,.webp" multiple required><small>PDF, Excel, CSV, JPG, PNG или WEBP. До 5 файлов, каждый не более 15 МБ.</small></label>
                                <button class="b2b-cabinet-primary" type="submit">Отправить на комплектацию</button>
                            </div>
                        </form>
                    @else
                        <div class="b2b-cabinet-zero"><i class="icon icon-FileText"></i><h3>Комплектация по спецификации не подключена</h3><p>Уточните доступность услуги у вашего менеджера.</p></div>
                    @endif
                    <div class="b2b-specification-list">
                        @forelse($specifications as $specification)
                            <article>
                                <header><div><span>{{ $specification->submitted_at?->format('d.m.Y H:i') }}</span><h3>{{ $specification->title }}</h3><small>{{ $specification->reference }}</small></div><b class="is-{{ $specification->status }}">{{ $specification->statusLabel() }}</b></header>
                                @if($specification->comment)<p>{{ $specification->comment }}</p>@endif
                                @if($specification->client_message)<div class="b2b-specification-message"><strong>Комментарий менеджера</strong><span>{{ $specification->client_message }}</span></div>@endif
                                @php($matchedItems = $specification->items->where('status', 'matched'))
                                @if($specification->parsed_at || $specification->parsing_status === 'pending')
                                    <div class="b2b-specification-parsing is-{{ $specification->parsing_status }}">
                                        <div><strong>{{ $specification->parsingStatusLabel() }}</strong><span>{{ $specification->parse_message }}</span></div>
                                        @if($matchedItems->isNotEmpty())<form method="POST" action="{{ route('b2b.cabinet.specifications.cart', $specification) }}">@csrf<button type="submit">Добавить найденное в корзину</button></form>@endif
                                    </div>
                                @endif
                                @if($specification->items->isNotEmpty())
                                    <div class="b2b-specification-items">
                                        @foreach($specification->items->take(8) as $item)
                                            <div><span>{{ $item->source_sku ?: 'Без артикула' }}</span><strong>{{ $item->product?->name ?: ($item->source_name ?: 'Позиция без названия') }}</strong><small>{{ rtrim(rtrim(number_format((float)$item->quantity, 3, ',', ' '), '0'), ',') }} {{ $item->unit ?: 'шт.' }} · {{ $item->status === 'matched' ? 'найдено в каталоге' : 'проверит менеджер' }}</small></div>
                                        @endforeach
                                        @if($specification->items->count() > 8)<p>Ещё {{ $specification->items->count() - 8 }} позиций</p>@endif
                                    </div>
                                @endif
                                @if($specification->versions->isNotEmpty())
                                    <details class="b2b-specification-versions">
                                        <summary>История редакций <b>{{ $specification->versions->count() }}</b></summary>
                                        <div>
                                            @foreach($specification->versions as $version)
                                                @php($diff = $version->diff_summary ?? [])
                                                <section class="{{ $specification->current_version_id === $version->id ? 'is-current' : '' }}">
                                                    <div><strong>Редакция №{{ $version->number }}</strong><span>{{ $version->sourceLabel() }} · {{ $version->created_at?->format('d.m.Y H:i') }}</span>@if($version->note)<small>{{ $version->note }}</small>@endif</div>
                                                    <p><b>{{ $version->item_count }}</b> позиций <span>+{{ $diff['added'] ?? 0 }}</span> <span>±{{ $diff['changed'] ?? 0 }}</span> <span>−{{ $diff['removed'] ?? 0 }}</span></p>
                                                </section>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                                <div class="b2b-specification-files">@foreach($specification->attachments as $attachment)<a href="{{ route('b2b.cabinet.specifications.attachment', $attachment) }}"><i class="icon icon-FileText"></i>{{ $attachment->original_name }} <small>{{ number_format($attachment->size / 1024, 0, ',', ' ') }} КБ@if(in_array(mb_strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION)), ['pdf', 'png', 'jpg', 'jpeg', 'webp'], true)) · {{ $attachment->ocrStatusLabel() }}@endif</small></a>@endforeach</div>
                                @if($client->specification_service_enabled && !in_array($specification->status, ['closed', 'cancelled'], true))
                                    <details class="b2b-specification-revision">
                                        <summary>Загрузить уточнённую редакцию</summary>
                                        <form method="POST" action="{{ route('b2b.cabinet.specifications.revisions.store', $specification) }}" enctype="multipart/form-data">@csrf
                                            <label><span>Что изменилось</span><textarea name="revision_note" maxlength="1000" rows="2" placeholder="Например, изменили диаметры и количество радиаторов"></textarea></label>
                                            <label class="b2b-specification-file"><span>Новые файлы *</span><input type="file" name="files[]" accept=".pdf,.xlsx,.xls,.csv,.ods,.png,.jpg,.jpeg,.webp" multiple required><small>Загрузите полную актуальную редакцию. Предыдущая останется в истории.</small></label>
                                            <button class="b2b-cabinet-primary" type="submit">Сохранить новую редакцию</button>
                                        </form>
                                    </details>
                                @endif
                                @if($specification->commercialDocument)<a class="b2b-specification-result" href="{{ $sectionUrl('documents') }}">Открыть подготовленное {{ $specification->commercialDocument->type === 'invoice' ? 'счёт' : 'КП' }} →</a>@endif
                            </article>
                        @empty
                            <div class="b2b-cabinet-zero"><i class="icon icon-FileText"></i><h3>Спецификаций пока нет</h3><p>Загрузите готовый перечень оборудования — заявка и её статус сохранятся здесь.</p></div>
                        @endforelse
                    </div>
                    {{ $specifications->links() }}
                @elseif ($section === 'carts')
                    <div class="b2b-cabinet-heading"><div><span>Покупки</span><h2>Сохранённые корзины</h2></div><p>Возвращайтесь к подборке с обновлением цен и остатков</p></div>
                    @if($cart->where('pricing_type', 'b2b')->isNotEmpty())<form method="POST" action="{{ route('b2b.cabinet.carts.store') }}" class="b2b-save-current">@csrf<div><strong>Текущая корзина</strong><span>{{ $cartCount }} товаров · {{ number_format($cart->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 0)), 2, ',', ' ') }} BYN</span></div><input name="name" value="Заказ от {{ now()->format('d.m.Y') }}" maxlength="120" required><button type="submit">Сохранить</button></form>@endif
                    <div class="b2b-cabinet-list">@forelse($savedCarts as $savedCart)<article><div><span>{{ $savedCart->updated_at?->format('d.m.Y H:i') }}</span><h3>{{ $savedCart->name }}</h3><p>{{ $savedCart->items_count }} товаров · {{ number_format((float)$savedCart->total, 2, ',', ' ') }} BYN на момент сохранения</p></div><div class="b2b-cabinet-actions"><form method="POST" action="{{ route('b2b.cabinet.carts.restore', $savedCart) }}">@csrf<button class="primary">Восстановить</button></form><form method="POST" action="{{ route('b2b.cabinet.carts.destroy', $savedCart) }}" onsubmit="return confirm('Удалить сохранённую корзину?')">@csrf @method('DELETE')<button>Удалить</button></form></div></article>@empty<div class="b2b-cabinet-zero"><i class="icon icon-Handbag"></i><h3>Нет сохранённых корзин</h3><p>Соберите товары в B2B-каталоге и сохраните подборку здесь.</p><a href="{{ route('b2b.catalog') }}">Перейти в каталог</a></div>@endforelse</div>
                @elseif ($section === 'orders')
                    <div class="b2b-cabinet-heading"><div><span>Покупки</span><h2>История заказов</h2></div><p>Статусы, суммы и состав заказов</p></div>
                    <div class="b2b-cabinet-list">@forelse($orders as $order)<article><div><span>{{ $order->created_at?->format('d.m.Y H:i') }}</span><h3>{{ $order->number }}</h3><p>{{ $order->items->count() }} позиций · {{ $statusLabels[$order->status] ?? $order->status }} · {{ $order->paymentStatusLabel() }}</p></div><div class="b2b-cabinet-order-total"><strong>{{ number_format((float)$order->total, 2, ',', ' ') }} BYN</strong>@if($order->customerDetailsAreAvailable())<a href="{{ route('account.orders.show', $order) }}">Открыть заказ →</a>@else<small>Менеджер проверяет наличие</small>@endif</div></article>@empty<div class="b2b-cabinet-zero"><i class="icon icon-Package"></i><h3>Заказов пока нет</h3><a href="{{ route('b2b.catalog') }}">Перейти в каталог</a></div>@endforelse</div>{{ $orders->links() }}
                @elseif ($section === 'documents')
                    <div class="b2b-cabinet-heading"><div><span>Документы</span><h2>Коммерческие предложения и счета</h2></div><p>Документы, которые менеджер направил вашей компании</p></div>
                    <div class="b2b-cabinet-list">@forelse($documents as $document)<article><div><span>{{ $document->issued_at?->format('d.m.Y') }} · {{ $document->type === 'invoice' ? 'Счёт' : 'Коммерческое предложение' }}</span><h3>{{ $document->reference() }}</h3><p>{{ $documentStatuses[$document->status] ?? $document->status }}@if($document->valid_until) · действительно до {{ $document->valid_until->format('d.m.Y') }}@endif</p></div><div class="b2b-cabinet-order-total"><strong>{{ number_format((float)$document->total, 2, ',', ' ') }} {{ $document->currency }}</strong>@if($document->contracts->count())<a href="{{ $sectionUrl('contracts') }}">Связанные договоры →</a>@endif</div></article>@empty<div class="b2b-cabinet-zero"><i class="icon icon-FileText"></i><h3>Документов пока нет</h3><p>Отправленные менеджером КП и счета появятся здесь автоматически.</p></div>@endforelse</div>{{ $documents->links() }}
                @elseif ($section === 'contracts')
                    <div class="b2b-cabinet-heading"><div><span>Документы</span><h2>Мои договоры</h2></div><p>Договоры, сформированные по вашим КП и счетам</p></div>
                    <div class="b2b-cabinet-list">@forelse($contracts as $contract)<article><div><span>{{ $contract->contract_date?->format('d.m.Y') }}</span><h3>Договор {{ $contract->number }}</h3><p>{{ $contractStatuses[$contract->status] ?? $contract->status }} · основание {{ $contract->commercialDocument?->reference() }}</p></div><div class="b2b-cabinet-actions">@if($contract->generated_pdf_path)<a class="primary" target="_blank" href="{{ route('b2b.cabinet.contracts.file', [$contract, 'pdf']) }}">PDF</a>@endif @if($contract->generated_docx_path)<a href="{{ route('b2b.cabinet.contracts.file', [$contract, 'docx']) }}">DOCX</a>@endif</div></article>@empty<div class="b2b-cabinet-zero"><i class="icon icon-Note"></i><h3>Договоров пока нет</h3><p>Подготовленные менеджером договоры появятся здесь.</p></div>@endforelse</div>
                @elseif ($section === 'reconciliation')
                    <div class="b2b-cabinet-heading"><div><span>Финансы</span><h2>Сверка взаиморасчётов</h2></div><p>Предварительная сверка по заказам в KOTLOV</p></div>
                    <form class="b2b-reconciliation-filter" method="GET"><input type="hidden" name="section" value="reconciliation"><label><span>Начало периода</span><input type="date" name="date_from" value="{{ $dateFrom->toDateString() }}"></label><label><span>Конец периода</span><input type="date" name="date_to" value="{{ $dateTo->toDateString() }}"></label><button type="submit">Сформировать</button><button type="button" onclick="window.print()">Печать / PDF</button></form>
                    <article class="b2b-reconciliation-sheet"><header><img src="{{ asset('img/logo.svg') }}" alt="KOTLOV"><div><strong>Сверка взаиморасчётов</strong><span>за {{ $dateFrom->format('d.m.Y') }} — {{ $dateTo->format('d.m.Y') }}</span></div></header><p>Организация: <strong>{{ $counterparty?->legal_name ?: $client->company_name }}</strong>@if($client->unp), УНП {{ $client->unp }}@endif</p><table><thead><tr><th>Дата</th><th>Документ</th><th>Начислено, BYN</th><th>Оплачено, BYN</th></tr></thead><tbody><tr class="muted"><td colspan="2">Сальдо на начало периода</td><td>{{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', ' ') : '—' }}</td><td>{{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', ' ') : '—' }}</td></tr>@foreach($reconciliationOrders as $order)<tr><td>{{ $order->created_at?->format('d.m.Y') }}</td><td>{{ $order->number }} · {{ $statusLabels[$order->status] ?? $order->status }}</td><td>{{ $order->status !== 'cancelled' ? number_format((float)$order->total, 2, ',', ' ') : '—' }}</td><td>{{ $order->payment_status === 'paid' ? number_format((float)$order->total, 2, ',', ' ') : '—' }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="2">Обороты за период</th><th>{{ number_format($periodCharges, 2, ',', ' ') }}</th><th>{{ number_format($periodPayments, 2, ',', ' ') }}</th></tr><tr><th colspan="2">Сальдо на конец периода</th><th colspan="2">{{ number_format($closingBalance, 2, ',', ' ') }} BYN</th></tr></tfoot></table><small>Сверка сформирована по заказам сайта. Для официального акта с данными бухгалтерского учёта обратитесь к менеджеру.</small></article>
                @elseif ($section === 'security')
                    <div class="b2b-cabinet-heading"><div><span>Аккаунт</span><h2>Безопасность</h2></div><p>Изменение пароля для входа в кабинет</p></div>
                    <form method="POST" action="{{ route('account.password') }}" class="b2b-cabinet-form b2b-security-form">@csrf @method('PUT')<label><span>Текущий пароль *</span><input type="password" name="current_password" required autocomplete="current-password"></label><label><span>Новый пароль *</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label><label><span>Повторите новый пароль *</span><input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label><button class="b2b-cabinet-primary" type="submit">Изменить пароль</button></form>
                @endif
            </section>
        </div>
    </main>
</div>
@endsection

@push('styles')
<style>
.b2b-commercial-profile__terms .is-expired{background:#fff0f1}.b2b-commercial-profile__terms .is-expired dt,.b2b-commercial-profile__terms .is-expired dd{color:#a92b38}
.b2b-commercial-profile__terms .is-warning{background:#fff7df}.b2b-commercial-profile__terms .is-warning dt,.b2b-commercial-profile__terms .is-warning dd{color:#8a6200}
body.b2b-body{background:#f4f7fb}.b2b-cabinet *{box-sizing:border-box}.b2b-cabinet-topbar{background:#132238;color:#fff;position:sticky;top:0;z-index:50;box-shadow:0 8px 24px #1322381f}.b2b-cabinet-topbar__inner{max-width:1480px;margin:auto;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;gap:24px}.b2b-cabinet-brand{display:flex;align-items:center;gap:10px}.b2b-cabinet-brand img{filter:brightness(0) invert(1)}.b2b-cabinet-brand span{background:#fa626a;color:#fff;border-radius:6px;padding:4px 7px;font-weight:800;font-size:12px}.b2b-cabinet-topbar nav,.b2b-cabinet-topbar nav a,.b2b-cabinet-topbar nav form{display:flex;align-items:center}.b2b-cabinet-topbar nav{gap:8px}.b2b-cabinet-topbar nav a,.b2b-cabinet-topbar nav button{border:0;background:transparent;color:#fff;padding:10px 13px;border-radius:8px;font-weight:600}.b2b-cabinet-topbar nav a:hover,.b2b-cabinet-topbar nav button:hover{background:#ffffff14}.b2b-cabinet-topbar nav b{background:#fa626a;border-radius:99px;padding:1px 6px;font-size:11px}.b2b-cabinet-shell{max-width:1480px;margin:auto;padding:28px}.b2b-cabinet-welcome{background:linear-gradient(120deg,#17283f,#294762);color:#fff;border-radius:18px;padding:28px 32px;display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;overflow:hidden;position:relative}.b2b-cabinet-welcome:after{content:"";position:absolute;width:360px;height:360px;border:65px solid #ffffff0d;border-radius:50%;right:15%;top:-180px}.b2b-cabinet-welcome span,.b2b-cabinet-heading span{color:#18c795;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:800}.b2b-cabinet-welcome h1{font-size:28px;margin:3px 0 2px;color:#fff}.b2b-cabinet-welcome p{margin:0;color:#cbd7e5}.b2b-cabinet-welcome>a{z-index:1;background:#fff;color:#132238;padding:13px 18px;border-radius:10px;font-weight:700}.b2b-cabinet-layout{display:grid;grid-template-columns:270px minmax(0,1fr);gap:22px}.b2b-cabinet-nav,.b2b-cabinet-content{background:#fff;border:1px solid #e1e8f0;border-radius:16px}.b2b-cabinet-nav{padding:14px;height:max-content;position:sticky;top:82px}.b2b-cabinet-nav__company{padding:10px 12px 18px;border-bottom:1px solid #e8edf3;margin-bottom:8px}.b2b-cabinet-nav__company>*{display:block}.b2b-cabinet-nav__company small{color:#78889b}.b2b-cabinet-nav__company strong{color:#142338;margin:3px 0}.b2b-cabinet-nav__company span{font-size:12px;color:#53657a}.b2b-cabinet-nav>a{display:flex;align-items:center;gap:10px;color:#53657a;padding:11px 12px;border-radius:9px;margin:2px 0;font-size:14px}.b2b-cabinet-nav>a i{font-size:18px}.b2b-cabinet-nav>a b{margin-left:auto;background:#e8edf3;padding:1px 7px;border-radius:99px;font-size:11px}.b2b-cabinet-nav>a:hover,.b2b-cabinet-nav>a.active{background:#e9f8f3;color:#0b8966;font-weight:700}.b2b-cabinet-nav>a.b2b-cabinet-nav__retail{margin-top:10px;border-top:1px solid #e8edf3;border-radius:0;padding-top:16px}.b2b-cabinet-content{padding:28px;min-height:640px}.b2b-cabinet-heading{display:flex;align-items:end;justify-content:space-between;border-bottom:1px solid #e8edf3;padding-bottom:19px;margin-bottom:22px;gap:20px}.b2b-cabinet-heading h2{font-size:25px;margin:3px 0 0;color:#142338}.b2b-cabinet-heading p{margin:0;color:#78889b}.b2b-commercial-profile{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:24px;margin-bottom:18px;padding:22px;border:1px solid #bce8da;border-radius:14px;background:linear-gradient(135deg,#f3fbf8,#fff)}.b2b-commercial-profile__intro>span{color:#0b9d76;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.b2b-commercial-profile__intro h3{margin:5px 0 7px;color:#142338;font-size:21px}.b2b-commercial-profile__intro p{margin:0;color:#53657a;line-height:1.55}.b2b-commercial-profile__service{display:flex;align-items:center;gap:10px;margin-top:15px;padding:12px;border-radius:10px;background:#fff;color:#142338}.b2b-commercial-profile__service i{color:#0b9d76;font-size:23px}.b2b-commercial-profile__service strong,.b2b-commercial-profile__service small{display:block}.b2b-commercial-profile__service small{margin-top:3px;color:#78889b}.b2b-commercial-profile__service>b{margin-left:auto;color:#0b8966;white-space:nowrap}.b2b-commercial-profile__terms{display:grid;grid-template-columns:1fr 1fr;gap:0 18px;margin:0}.b2b-commercial-profile__terms div{padding:9px 0;border-bottom:1px solid #e1ece8}.b2b-commercial-profile__terms dt{color:#78889b;font-size:11px}.b2b-commercial-profile__terms dd{margin:3px 0 0;color:#142338;font-size:13px;font-weight:650}.b2b-cabinet-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px}.b2b-cabinet-stats a{border:1px solid #e1e8f0;border-radius:13px;padding:18px;color:#53657a;display:grid;grid-template-columns:auto 1fr;gap:2px 10px}.b2b-cabinet-stats i{grid-row:1/4;background:#e9f8f3;color:#0c9972;padding:9px;border-radius:9px;font-size:20px}.b2b-cabinet-stats strong{font-size:24px;color:#142338}.b2b-cabinet-stats small{color:#8a98a8}.b2b-cabinet-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:18px}.b2b-cabinet-card{border:1px solid #e1e8f0;border-radius:13px;padding:20px}.b2b-cabinet-card__head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.b2b-cabinet-card__head h3{margin:0;font-size:17px}.b2b-cabinet-card__head a{color:#0b9d76;font-weight:700}.b2b-cabinet-row{display:grid;grid-template-columns:1fr auto auto;gap:16px;align-items:center;border-top:1px solid #edf1f5;padding:12px 0;color:#53657a}.b2b-cabinet-row span:first-child>*{display:block}.b2b-cabinet-row small{color:#8a98a8}.b2b-cabinet-row b{color:#142338}.b2b-cabinet-details div{display:grid;grid-template-columns:105px 1fr;padding:10px 0;border-top:1px solid #edf1f5}.b2b-cabinet-details dt{color:#8a98a8}.b2b-cabinet-details dd{margin:0;color:#142338}.b2b-cabinet-form h3{font-size:17px;margin:25px 0 12px}.b2b-cabinet-form h3:first-child{margin-top:0}.b2b-cabinet-form__grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px}.b2b-cabinet-form label,.b2b-reconciliation-filter label{display:block}.b2b-cabinet-form label span,.b2b-reconciliation-filter label span{display:block;font-size:12px;color:#53657a;margin-bottom:6px;font-weight:600}.b2b-cabinet-form label.wide{grid-column:1/-1}.b2b-cabinet-form input,.b2b-save-current input,.b2b-reconciliation-filter input{width:100%;border:1px solid #ccd6e1;border-radius:9px;padding:12px 13px;outline:none}.b2b-cabinet-form input:focus,.b2b-save-current input:focus{border-color:#16b88b;box-shadow:0 0 0 3px #16b88b1a}.b2b-cabinet-primary,.b2b-save-current button,.b2b-reconciliation-filter button{border:0;background:#132238;color:#fff;border-radius:9px;padding:13px 19px;font-weight:700;margin-top:22px}.b2b-save-current{display:grid;grid-template-columns:1fr minmax(220px,360px) auto;align-items:center;gap:14px;border:1px solid #bce8da;background:#f1fbf8;padding:16px;border-radius:12px;margin-bottom:18px}.b2b-save-current div>*{display:block}.b2b-save-current span{color:#6a7c8f;font-size:13px}.b2b-save-current button{margin:0;background:#0b9d76}.b2b-cabinet-list article{display:flex;align-items:center;justify-content:space-between;gap:22px;border:1px solid #e1e8f0;border-radius:12px;padding:18px;margin-bottom:12px}.b2b-cabinet-list article span{font-size:12px;color:#8a98a8}.b2b-cabinet-list article h3{font-size:17px;margin:3px 0;color:#142338}.b2b-cabinet-list article p{margin:0;color:#617286}.b2b-cabinet-actions{display:flex;gap:7px}.b2b-cabinet-actions a,.b2b-cabinet-actions button{display:block;border:1px solid #ccd6e1;background:#fff;color:#53657a;border-radius:8px;padding:9px 12px;font-weight:700}.b2b-cabinet-actions .primary{background:#132238;color:#fff;border-color:#132238}.b2b-cabinet-order-total{text-align:right}.b2b-cabinet-order-total>*{display:block}.b2b-cabinet-order-total strong{color:#142338;white-space:nowrap}.b2b-cabinet-order-total a{color:#0b9d76;margin-top:5px}.b2b-cabinet-order-total small{color:#8a98a8}.b2b-cabinet-zero{text-align:center;padding:70px 20px;border:1px dashed #ccd6e1;border-radius:13px;color:#718196}.b2b-cabinet-zero i{font-size:42px;color:#aab7c5}.b2b-cabinet-zero h3{color:#142338;margin:12px 0 6px}.b2b-cabinet-zero a{display:inline-block;margin-top:12px;color:#0b9d76;font-weight:700}.b2b-cabinet-alert{border-radius:10px;padding:13px 16px;margin-bottom:16px}.b2b-cabinet-alert.is-success{background:#e8f8f2;color:#087453}.b2b-cabinet-alert.is-error{background:#fff0f1;color:#b62635}.b2b-reconciliation-filter{display:flex;align-items:end;gap:12px;margin-bottom:20px}.b2b-reconciliation-filter label{width:190px}.b2b-reconciliation-filter button{margin:0}.b2b-reconciliation-filter button:last-child{background:#fff;color:#132238;border:1px solid #ccd6e1}.b2b-reconciliation-sheet{border:1px solid #ccd6e1;padding:30px;border-radius:12px}.b2b-reconciliation-sheet header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #142338;padding-bottom:15px;margin-bottom:20px}.b2b-reconciliation-sheet header div{text-align:right}.b2b-reconciliation-sheet header span,.b2b-reconciliation-sheet header strong{display:block}.b2b-reconciliation-sheet header strong{font-size:20px;color:#142338}.b2b-reconciliation-sheet table{width:100%;border-collapse:collapse;margin:20px 0}.b2b-reconciliation-sheet th,.b2b-reconciliation-sheet td{padding:10px;border:1px solid #dfe5ec;text-align:right}.b2b-reconciliation-sheet th:nth-child(-n+2),.b2b-reconciliation-sheet td:nth-child(-n+2){text-align:left}.b2b-reconciliation-sheet .muted{background:#f5f7fa}.b2b-reconciliation-sheet>small{color:#78889b}.b2b-security-form{max-width:560px}.b2b-security-form>label{margin-bottom:14px}
@media(max-width:1050px){.b2b-cabinet-layout{grid-template-columns:1fr}.b2b-cabinet-nav{position:static;display:flex;overflow:auto;gap:4px}.b2b-cabinet-nav__company{display:none}.b2b-cabinet-nav>a{white-space:nowrap}.b2b-cabinet-nav>a.b2b-cabinet-nav__retail{margin:0;border:0;padding-top:11px}.b2b-cabinet-stats{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.b2b-cabinet-topbar__inner,.b2b-cabinet-shell{padding:14px}.b2b-cabinet-topbar nav a{font-size:0}.b2b-cabinet-topbar nav a i,.b2b-cabinet-topbar nav a b{font-size:16px}.b2b-cabinet-welcome{padding:22px;display:block}.b2b-cabinet-welcome>a{display:inline-block;margin-top:15px}.b2b-cabinet-content{padding:18px}.b2b-cabinet-heading{display:block}.b2b-cabinet-heading p{margin-top:5px}.b2b-commercial-profile,.b2b-commercial-profile__terms,.b2b-cabinet-stats,.b2b-cabinet-grid,.b2b-cabinet-form__grid{grid-template-columns:1fr}.b2b-cabinet-form label.wide{grid-column:auto}.b2b-save-current{grid-template-columns:1fr}.b2b-cabinet-list article{align-items:flex-start;display:block}.b2b-cabinet-actions,.b2b-cabinet-order-total{margin-top:12px;text-align:left}.b2b-reconciliation-filter{align-items:stretch;flex-direction:column}.b2b-reconciliation-filter label{width:100%}.b2b-reconciliation-sheet{padding:15px;overflow:auto}.b2b-reconciliation-sheet table{min-width:620px}}
@media print{.b2b-cabinet-topbar,.b2b-cabinet-welcome,.b2b-cabinet-nav,.b2b-cabinet-heading,.b2b-reconciliation-filter,.footer{display:none!important}.b2b-cabinet-shell,.b2b-cabinet-content{padding:0;border:0}.b2b-cabinet-layout{display:block}.b2b-reconciliation-sheet{border:0}.b2b-cabinet-content>*:not(.b2b-reconciliation-sheet){display:none}}
</style>
@endpush

@push('styles')
<style>
.b2b-cabinet-form select{width:100%;height:46px;padding:0 13px;border:1px solid #ccd6e1;border-radius:9px;background:#fff;color:#142338}.b2b-team-intro{margin-bottom:14px;background:#f1fbf8;border-color:#bce8da}.b2b-team-intro p{margin:6px 0 0;color:#617286;line-height:1.5}.b2b-team-list{display:grid;gap:10px}.b2b-team-member{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:18px}.b2b-team-member div>*{display:block}.b2b-team-member span{margin-top:3px;color:#78889b;font-size:12px}.b2b-team-member>b{border-radius:99px;background:#edf4f8;color:#31506e;padding:6px 10px;font-size:12px}.b2b-team-member button{border:1px solid #cad5e1;border-radius:8px;background:#fff;color:#31506e;padding:8px 11px;font-weight:700}.b2b-team-member button.is-danger{border-color:#e2b9bd;color:#a92b38}.b2b-team-member__actions,.b2b-team-member__actions form{display:flex;align-items:center;gap:8px}.b2b-team-member__actions select{height:36px;padding:0 30px 0 10px;border:1px solid #ccd6e1;border-radius:8px;background:#fff}.b2b-team-add{margin-top:20px;padding:20px;border:1px solid #e1e8f0;border-radius:13px}.b2b-team-add>p{margin:-5px 0 16px;color:#78889b}.b2b-team-add .b2b-cabinet-primary{margin-top:16px}.b2b-team-subsection{margin-top:28px}.b2b-team-subsection .b2b-cabinet-heading{margin-bottom:12px}.b2b-team-audit{overflow:hidden;border:1px solid #e1e8f0;border-radius:13px;background:#fff}.b2b-team-audit article{display:grid;grid-template-columns:120px minmax(0,1fr) auto;align-items:center;gap:18px;padding:13px 16px;border-bottom:1px solid #edf1f5}.b2b-team-audit article:last-child{border-bottom:0}.b2b-team-audit time,.b2b-team-audit small,.b2b-team-audit span{color:#78889b;font-size:12px}.b2b-team-audit strong,.b2b-team-audit span{display:block}.b2b-team-audit p{margin:0;padding:20px;color:#78889b}@media(max-width:700px){.b2b-team-member{grid-template-columns:1fr}.b2b-team-member form{justify-self:start}.b2b-team-member__actions,.b2b-team-member__actions form{align-items:stretch;flex-direction:column}.b2b-team-audit article{grid-template-columns:1fr;gap:4px}}
.b2b-profile-progress{display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,340px);align-items:center;gap:26px;margin-bottom:20px;padding:20px;border:1px solid #f1d89b;border-radius:13px;background:#fffaf0}.b2b-profile-progress.is-complete{border-color:#bce8da;background:#f1fbf8}.b2b-profile-progress__copy>span{display:block;color:#9a6b00;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.b2b-profile-progress.is-complete .b2b-profile-progress__copy>span{color:#087453}.b2b-profile-progress__copy strong{display:block;margin-top:3px;color:#142338;font-size:28px;line-height:1}.b2b-profile-progress__copy p{margin:7px 0 0;color:#53657a}.b2b-profile-progress__copy small{display:block;margin-top:5px;color:#78889b}.b2b-profile-progress__side{display:grid;gap:9px}.b2b-profile-progress__side>a{justify-self:end;color:#0b8966;font-weight:800}.b2b-profile-progress__side>small{text-align:right;color:#78889b}.b2b-profile-progress__meter{height:9px;border-radius:99px;background:#e2e8ef;overflow:hidden}.b2b-profile-progress__meter i{display:block;height:100%;border-radius:inherit;background:#e8a619}.b2b-profile-progress.is-complete .b2b-profile-progress__meter i{background:#16b88b}
.b2b-form-section{border:1px solid #e1e8f0;border-radius:13px;padding:20px;margin-bottom:16px}.b2b-form-section__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:15px;margin-bottom:17px;border-bottom:1px solid #edf1f5}.b2b-cabinet-form .b2b-form-section h3{margin:0;color:#142338}.b2b-form-section__head p{margin:4px 0 0;color:#78889b;font-size:13px}.b2b-form-section__head>span{flex:0 0 auto;border-radius:99px;background:#f1f4f8;color:#65768a;padding:5px 9px;font-size:11px;font-weight:700}.b2b-cabinet-form__grid{gap:16px 18px}.b2b-cabinet-form input{height:46px;padding:0 13px;background:#fff;color:#142338}.b2b-cabinet-form label>span{font-weight:700}.b2b-cabinet-form label>small{display:block;min-height:18px;margin-top:6px;color:#78889b;font-size:12px;line-height:1.45}.b2b-cabinet-form label>small.is-success{color:#087453}.b2b-cabinet-form label>small.is-error{color:#b62635}.b2b-unp-row{display:grid;grid-template-columns:minmax(0,1fr) 160px;gap:8px}.b2b-unp-row button{display:flex;align-items:center;justify-content:center;border:0;border-radius:9px;background:#132238;color:#fff;font-size:12px;font-weight:700;line-height:1.2}.b2b-unp-row button:disabled{opacity:.6;cursor:wait}.b2b-inline-action{border:0;background:transparent;color:#0b8966;padding:0;font:inherit;font-weight:700;text-decoration:underline;text-underline-offset:2px}.b2b-form-submit{display:flex;align-items:center;justify-content:space-between;gap:20px;border-radius:13px;background:#eef8f5;padding:17px 20px}.b2b-form-submit div>*{display:block}.b2b-form-submit strong{color:#142338}.b2b-form-submit span{margin-top:3px;color:#617286;font-size:13px}.b2b-form-submit .b2b-cabinet-primary{margin:0;white-space:nowrap}
.b2b-specification-upload{display:grid;grid-template-columns:minmax(260px,.75fr) minmax(0,1.25fr);gap:26px;padding:22px;margin-bottom:22px;border:1px solid #bce8da;border-radius:14px;background:linear-gradient(135deg,#f3fbf8,#fff)}.b2b-specification-upload__copy>span{color:#0b9d76;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.b2b-specification-upload__copy h3{margin:5px 0 8px;color:#142338;font-size:22px}.b2b-specification-upload__copy p{margin:0;color:#617286;line-height:1.55}.b2b-specification-upload__fields{display:grid;gap:13px}.b2b-specification-upload__fields label>span{display:block;margin-bottom:6px;color:#53657a;font-size:12px;font-weight:700}.b2b-specification-upload__fields input:not([type=file]),.b2b-specification-upload__fields textarea{width:100%;border:1px solid #ccd6e1;border-radius:9px;background:#fff;color:#142338;padding:12px 13px;outline:none}.b2b-specification-upload__fields textarea{resize:vertical}.b2b-specification-upload__fields input:focus,.b2b-specification-upload__fields textarea:focus{border-color:#16b88b;box-shadow:0 0 0 3px #16b88b1a}.b2b-specification-file{padding:13px;border:1px dashed #9fb3c6;border-radius:10px;background:#fff}.b2b-specification-file input{display:block;width:100%}.b2b-specification-file small{display:block;margin-top:6px;color:#78889b}.b2b-specification-upload .b2b-cabinet-primary{justify-self:start;margin:0}.b2b-specification-list article{padding:20px;margin-bottom:13px;border:1px solid #e1e8f0;border-radius:13px}.b2b-specification-list article>header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.b2b-specification-list article>header span,.b2b-specification-list article>header small{display:block;color:#8a98a8;font-size:12px}.b2b-specification-list article h3{margin:3px 0;color:#142338;font-size:18px}.b2b-specification-list article>header>b{border-radius:99px;padding:6px 10px;background:#edf1f5;color:#53657a;font-size:12px;white-space:nowrap}.b2b-specification-list article>header>b.is-submitted{background:#fff4d7;color:#8a6200}.b2b-specification-list article>header>b.is-reviewing{background:#eaf2ff;color:#2861a5}.b2b-specification-list article>header>b.is-clarification{background:#fff0f1;color:#a92b38}.b2b-specification-list article>header>b.is-quoted,.b2b-specification-list article>header>b.is-closed{background:#e8f8f2;color:#087453}.b2b-specification-list article>p{margin:12px 0;color:#617286}.b2b-specification-message{display:grid;gap:3px;margin-top:12px;padding:12px;border-radius:9px;background:#f1f4f8}.b2b-specification-message span{color:#53657a}.b2b-specification-files{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.b2b-specification-files a{display:flex;align-items:center;gap:6px;padding:7px 9px;border:1px solid #dce4ec;border-radius:8px;color:#31506e;font-size:12px}.b2b-specification-files a small{color:#8a98a8}.b2b-specification-result{display:inline-block;margin-top:14px;color:#0b8966;font-weight:800}.b2b-specification-parsing{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:14px;padding:12px 14px;border-radius:10px;background:#f3f6f9}.b2b-specification-parsing>div{display:grid;gap:3px}.b2b-specification-parsing strong{color:#24384d}.b2b-specification-parsing span{color:#66788b;font-size:12px}.b2b-specification-parsing.is-pending{background:#fff7df}.b2b-specification-parsing.is-parsed{background:#eaf8f3}.b2b-specification-parsing.is-failed{background:#fff0f1}.b2b-specification-parsing button{border:0;border-radius:8px;background:#0b9d76;color:#fff;padding:10px 13px;font-weight:800;cursor:pointer;white-space:nowrap}.b2b-specification-items{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;margin-top:12px;overflow:hidden;border:1px solid #e3eaf1;border-radius:10px;background:#e3eaf1}.b2b-specification-items>div{display:grid;gap:2px;padding:10px 12px;background:#fff}.b2b-specification-items span,.b2b-specification-items small{color:#77889a;font-size:11px}.b2b-specification-items strong{overflow:hidden;color:#24384d;font-size:13px;text-overflow:ellipsis;white-space:nowrap}.b2b-specification-items>p{grid-column:1/-1;margin:0!important;padding:8px 12px;background:#f7f9fb;color:#66788b!important;font-size:12px}
.b2b-specification-versions,.b2b-specification-revision{margin-top:14px;border:1px solid #dfe7ef;border-radius:10px;background:#fafbfd}.b2b-specification-versions>summary,.b2b-specification-revision>summary{display:flex;align-items:center;justify-content:space-between;padding:11px 13px;color:#31506e;font-size:13px;font-weight:800;cursor:pointer}.b2b-specification-versions>summary b{border-radius:99px;background:#e8eef4;padding:2px 7px}.b2b-specification-versions>div{border-top:1px solid #e5ebf1}.b2b-specification-versions section{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:10px 13px;border-top:1px solid #edf1f5}.b2b-specification-versions section:first-child{border-top:0}.b2b-specification-versions section.is-current{background:#eef9f5}.b2b-specification-versions section>div{display:grid;gap:2px}.b2b-specification-versions section span,.b2b-specification-versions section small{color:#748699;font-size:11px}.b2b-specification-versions section p{display:flex;gap:7px;margin:0;color:#687b8e;font-size:11px;white-space:nowrap}.b2b-specification-versions section p>b{color:#263b50}.b2b-specification-versions section p>span:first-of-type{color:#087453}.b2b-specification-versions section p>span:last-of-type{color:#a33b47}.b2b-specification-revision>form{display:grid;gap:11px;padding:13px;border-top:1px solid #e5ebf1}.b2b-specification-revision label>span{display:block;margin-bottom:5px;color:#53657a;font-size:12px;font-weight:700}.b2b-specification-revision textarea{width:100%;border:1px solid #ccd6e1;border-radius:9px;padding:10px 12px;resize:vertical}.b2b-specification-revision .b2b-cabinet-primary{justify-self:start;margin:0}
@media(max-width:700px){.b2b-profile-progress,.b2b-specification-upload,.b2b-specification-items{grid-template-columns:1fr;gap:16px}.b2b-profile-progress__side>a,.b2b-profile-progress__side>small{justify-self:start;text-align:left}.b2b-form-section,.b2b-specification-upload{padding:16px}.b2b-form-section__head,.b2b-form-submit{align-items:stretch;display:block}.b2b-form-section__head>span{display:inline-block;margin-top:9px}.b2b-form-submit .b2b-cabinet-primary,.b2b-specification-upload .b2b-cabinet-primary{width:100%;margin-top:14px}.b2b-unp-row{grid-template-columns:1fr}.b2b-unp-row button{height:42px}.b2b-specification-list article>header{display:block}.b2b-specification-list article>header>b{display:inline-block;margin-top:10px}.b2b-specification-files a{width:100%}.b2b-specification-parsing{align-items:stretch;flex-direction:column}.b2b-specification-parsing button{width:100%}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-b2b-profile]');
    if (!form) return;

    const unp = form.querySelector('[data-company-unp]');
    const lookupButton = form.querySelector('[data-company-lookup]');
    const lookupStatus = form.querySelector('[data-company-lookup-status]');
    const shortName = form.querySelector('[data-company-short-name]');
    const legalName = form.querySelector('[data-company-legal-name]');
    const legalAddress = form.querySelector('[data-company-legal-address]');
    const postalAddress = form.querySelector('input[name="postal_address"]');
    let lookupTimer = null;
    let lastLookup = '';

    function showLookupStatus(message, type) {
        lookupStatus.textContent = message;
        lookupStatus.classList.toggle('is-success', type === 'success');
        lookupStatus.classList.toggle('is-error', type === 'error');
    }

    async function lookupCompany() {
        const value = unp.value.replace(/\D/g, '').slice(0, 9);
        unp.value = value;
        if (value.length !== 9) {
            showLookupStatus('Введите 9 цифр УНП.', 'error');
            return;
        }
        if (lastLookup === value) return;

        lookupButton.disabled = true;
        lookupButton.textContent = 'Проверяем…';
        showLookupStatus('Ищем организацию в реестре МНС…', '');
        try {
            const response = await fetch(form.dataset.companyLookupUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify({unp: value})
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Организация не найдена.');

            const data = payload.data || {};
            shortName.value = data.short_name || data.legal_name || shortName.value;
            legalName.value = data.legal_name || legalName.value;
            legalAddress.value = data.address || legalAddress.value;
            lastLookup = value;
            showLookupStatus('Организация найдена. Название и адрес заполнены.', 'success');
        } catch (error) {
            showLookupStatus((error.message || 'Не удалось проверить УНП.') + ' Заполните данные вручную.', 'error');
        } finally {
            lookupButton.disabled = false;
            lookupButton.textContent = 'Заполнить по УНП';
        }
    }

    unp.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 9);
        lastLookup = '';
        clearTimeout(lookupTimer);
        if (this.value.length === 9) lookupTimer = setTimeout(lookupCompany, 450);
        else showLookupStatus('Проверим 9 цифр в реестре и подставим доступные данные.', '');
    });
    lookupButton.addEventListener('click', lookupCompany);
    form.querySelector('[data-copy-legal-address]').addEventListener('click', function () {
        postalAddress.value = legalAddress.value;
        postalAddress.focus();
    });
    form.querySelectorAll('[data-uppercase]').forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/\s/g, '');
        });
    });
});
</script>
@endpush
