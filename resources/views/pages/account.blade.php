@extends('layouts.amerce')

@section('content')
@php
    $clientTypeLabel = $user->client_type_label ?? 'Розничный клиент';
@endphp

<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Личный кабинет</p>
                </div>

                <h3 class="letter-space-0">Личный кабинет</h3>

                <p class="text-body-1 cl-text-2">
                    Управление заказами, профилем и заявкой на специальные условия.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">

            @if (session('success'))
                <div class="alert alert-success mb-20">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mb-20">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="row gy-30">

                <div class="col-lg-4">
                    <div class="tf-grid-layout gap-10">

                        <div class="box-account-info">
                            <div class="d-flex align-items-center gap-16 mb-16">
                                <div class="account-avatar">
                                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                                </div>

                                <div>
                                    <h5 class="mb-4">{{ $user->name }}</h5>
                                    <p class="text-caption-01 cl-text-2 mb-0">{{ $user->email }}</p>

                                    @if ($user->phone)
                                        <p class="text-caption-01 cl-text-2 mb-0">{{ $user->phone }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="br-line mb-16"></div>

                            <p class="text-caption-01 cl-text-3 mb-4">Тип аккаунта</p>

                            <div class="d-flex align-items-center justify-content-between gap-10">
                                <p class="fw-semibold mb-0">{{ $clientTypeLabel }}</p>

                                @if ($user->isB2B())
                                    <span class="badge-account badge-success">Одобрен</span>
                                @elseif (! $user->isRetailClient())
                                    <span class="badge-account badge-warning">На проверке</span>
                                @else
                                    <span class="badge-account badge-gray">Розница</span>
                                @endif
                            </div>

                            @if ($user->company_name)
                                <p class="text-caption-01 cl-text-2 mt-8 mb-0">
                                    {{ $user->company_name }}
                                    @if ($user->company_inn)
                                        · {{ $user->company_inn }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="account-menu">
                            <a href="#" class="account-menu-item active" data-tab="orders" onclick="showAccountTab('orders'); return false;">
                                <i class="icon icon-Package"></i>
                                <span>Мои заказы</span>
                            </a>

                            <a href="#" class="account-menu-item" data-tab="profile" onclick="showAccountTab('profile'); return false;">
                                <i class="icon icon-User"></i>
                                <span>Профиль</span>
                            </a>

                            <a href="#" class="account-menu-item" data-tab="security" onclick="showAccountTab('security'); return false;">
                                <i class="icon icon-Lock"></i>
                                <span>Безопасность</span>
                            </a>

                            @if ($user->isRetailClient())
                                <a href="#" class="account-menu-item" data-tab="b2b" onclick="showAccountTab('b2b'); return false;">
                                    <i class="icon icon-Buildings"></i>
                                    <span>Спецусловия</span>
                                </a>
                            @endif

                            <a href="/wishlist" class="account-menu-item">
                                <i class="icon icon-HeartStraight"></i>
                                <span>Избранное</span>
                            </a>

                            <form action="/logout" method="POST">
                                @csrf
                                <button type="submit" class="account-menu-item account-menu-button">
                                    <i class="icon icon-SignOut"></i>
                                    <span>Выйти</span>
                                </button>
                            </form>
                        </div>

                    </div>
                </div>

                <div class="col-lg-8">

                    <div id="account-tab-orders" class="account-tab active">
                        <div class="account-content">
                            <div class="d-flex align-items-start justify-content-between gap-16 mb-20">
                                <div>
                                    <h4 class="mb-8">Мои заказы</h4>
                                    <p class="text-body-1 cl-text-2 mb-0">
                                        История ваших заказов на KOTLOV.BY.
                                    </p>
                                </div>
                            </div>

                            @if ($orders->count() > 0)
                                <div class="tf-table-res-df">
                                    <div class="overflow-auto">
                                        <table class="table-account">
                                            <thead>
                                                <tr>
                                                    <th>Заказ</th>
                                                    <th>Дата</th>
                                                    <th>Статус</th>
                                                    <th>Сумма</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($orders as $order)
                                                    <tr>
                                                        <td>
                                                            <span class="fw-semibold">#{{ $order->id }}</span>
                                                        </td>
                                                        <td>
                                                            {{ $order->created_at?->format('d.m.Y') }}
                                                        </td>
                                                        <td>
                                                            <span class="badge-account badge-gray">
                                                                @switch($order->status)
                                                                    @case('pending')
                                                                        Ожидает
                                                                        @break
                                                                    @case('processing')
                                                                        В обработке
                                                                        @break
                                                                    @case('paid')
                                                                        Оплачен
                                                                        @break
                                                                    @case('shipped')
                                                                        Отправлен
                                                                        @break
                                                                    @case('delivered')
                                                                        Доставлен
                                                                        @break
                                                                    @case('completed')
                                                                        Выполнен
                                                                        @break
                                                                    @case('cancelled')
                                                                        Отменён
                                                                        @break
                                                                    @default
                                                                        {{ $order->status }}
                                                                @endswitch
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fw-semibold">
                                                                {{ number_format($order->total_price ?? 0, 2, '.', ' ') }} BYN
                                                            </span>
                                                        </td>
                                                    </tr>

                                                    @if ($order->items && $order->items->count())
                                                        <tr>
                                                            <td colspan="4">
                                                                <div class="order-products">
                                                                    @foreach ($order->items->take(3) as $item)
                                                                        <div class="order-product-row">
                                                                            <span class="cl-text-2">
                                                                                {{ $item->product->name ?? 'Товар удалён' }}
                                                                            </span>
                                                                            <span class="text-caption-01 cl-text-3">
                                                                                {{ $item->quantity }} шт. × {{ number_format($item->price, 2, '.', ' ') }} BYN
                                                                            </span>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                @if ($orders->hasPages())
                                    <div class="mt-24">
                                        {{ $orders->links() }}
                                    </div>
                                @endif
                            @else
                                <div class="account-empty text-center">
                                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                                    <h5 class="mb-8">Заказов пока нет</h5>
                                    <p class="text-body-1 cl-text-2 mb-20">
                                        Самое время выбрать котёл, камин, дымоход или оборудование для отопления.
                                    </p>
                                    <a href="/catalog" class="tf-btn animate-btn">
                                        Перейти в каталог
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div id="account-tab-profile" class="account-tab">
                        <div class="account-content">
                            <h4 class="mb-8">Профиль</h4>
                            <p class="text-body-1 cl-text-2 mb-24">
                                Основные данные вашего аккаунта.
                            </p>

                            <form action="/account/profile" method="POST" class="form-log">
                                @csrf
                                @method('PUT')

                                <div class="form-content">
                                    <div class="row gy-16">
                                        <div class="col-md-6">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">
                                                    Имя <span class="text-primary">*</span>
                                                </label>
                                                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                                            </fieldset>
                                        </div>

                                        <div class="col-md-6">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Email</label>
                                                <input type="email" value="{{ $user->email }}" disabled>
                                            </fieldset>
                                        </div>

                                        <div class="col-md-6">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Телефон</label>
                                                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+375 (29) 000-00-00">
                                            </fieldset>
                                        </div>

                                        <div class="col-md-6">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Тип аккаунта</label>
                                                <input type="text" value="{{ $clientTypeLabel }}" disabled>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>

                                <div class="group-action mt-20">
                                    <button type="submit" class="tf-btn animate-btn">
                                        Сохранить изменения
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="account-tab-security" class="account-tab">
                        <div class="account-content">
                            <h4 class="mb-8">Безопасность</h4>
                            <p class="text-body-1 cl-text-2 mb-24">
                                Изменение пароля от личного кабинета.
                            </p>

                            <form action="/account/password" method="POST" class="form-log">
                                @csrf
                                @method('PUT')

                                <div class="form-content">
                                    <div class="row gy-16">
                                        <div class="col-12">
                                            <fieldset class="tf-field password-wrapper">
                                                <label class="tf-lable fw-medium">
                                                    Текущий пароль <span class="text-primary">*</span>
                                                </label>
                                                <div class="password-wrapper w-100">
                                                    <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                                    <input class="password-field" type="password" name="current_password" required>
                                                </div>
                                            </fieldset>
                                        </div>

                                        <div class="col-md-6">
                                            <fieldset class="tf-field password-wrapper">
                                                <label class="tf-lable fw-medium">
                                                    Новый пароль <span class="text-primary">*</span>
                                                </label>
                                                <div class="password-wrapper w-100">
                                                    <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                                    <input class="password-field" type="password" name="password" required>
                                                </div>
                                            </fieldset>
                                        </div>

                                        <div class="col-md-6">
                                            <fieldset class="tf-field password-wrapper">
                                                <label class="tf-lable fw-medium">
                                                    Повторите пароль <span class="text-primary">*</span>
                                                </label>
                                                <div class="password-wrapper w-100">
                                                    <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                                    <input class="password-field" type="password" name="password_confirmation" required>
                                                </div>
                                            </fieldset>
                                        </div>
                                    </div>
                                </div>

                                <div class="group-action mt-20">
                                    <button type="submit" class="tf-btn animate-btn">
                                        Изменить пароль
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if ($user->isRetailClient())
                        <div id="account-tab-b2b" class="account-tab">
                            <div class="account-content">
                                <h4 class="mb-8">Специальные условия</h4>
                                <p class="text-body-1 cl-text-2 mb-20">
                                    Вы закупаете оборудование оптом или занимаетесь монтажом? Оставьте заявку — откроем персональные цены.
                                </p>

                                {{-- Выбор типа --}}
                                <div class="b2b-choice mb-24">
                                    <label class="b2b-choice-card" id="card-wholesale">
                                        <input type="radio" name="b2b_pick" value="wholesale" class="b2b-pick-radio" style="display:none" checked>
                                        <span class="b2b-choice-icon">🏪</span>
                                        <div>
                                            <p class="fw-semibold mb-2">Оптовик</p>
                                            <p class="text-caption-01 cl-text-2 mb-0">Закупаю оборудование для перепродажи или крупных объектов</p>
                                        </div>
                                    </label>
                                    <label class="b2b-choice-card" id="card-installer">
                                        <input type="radio" name="b2b_pick" value="installer" class="b2b-pick-radio" style="display:none">
                                        <span class="b2b-choice-icon">🔧</span>
                                        <div>
                                            <p class="fw-semibold mb-2">Монтажник</p>
                                            <p class="text-caption-01 cl-text-2 mb-0">Устанавливаю оборудование у клиентов, нужны цены для расчётов</p>
                                        </div>
                                    </label>
                                </div>

                                <form action="/account/b2b-request" method="POST" class="form-log">
                                    @csrf
                                    <input type="hidden" name="client_type" id="b2b-client-type" value="{{ old('client_type', 'wholesale') }}">

                                    <div class="form-content">
                                        <div class="row gy-16">
                                            <div class="col-md-6">
                                                <fieldset class="tf-field">
                                                    <label class="tf-lable fw-medium">
                                                        Компания / ФИО ИП <span class="text-primary">*</span>
                                                    </label>
                                                    <input type="text" name="company_name"
                                                        value="{{ old('company_name', $user->company_name) }}"
                                                        placeholder="ООО Теплосервис или Иванов И.И."
                                                        required>
                                                </fieldset>
                                            </div>
                                            <div class="col-md-6">
                                                <fieldset class="tf-field">
                                                    <label class="tf-lable fw-medium">УНП / ИНН</label>
                                                    <input type="text" name="company_inn"
                                                        value="{{ old('company_inn', $user->company_inn) }}"
                                                        placeholder="100000000">
                                                </fieldset>
                                            </div>
                                            <div class="col-12">
                                                <fieldset class="tf-field">
                                                    <label class="tf-lable fw-medium">Комментарий</label>
                                                    <textarea name="b2b_comment" rows="3"
                                                        placeholder="Объёмы закупок, регион работы, количество объектов в год...">{{ old('b2b_comment') }}</textarea>
                                                </fieldset>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="request-note mt-20">
                                        <p class="text-caption-01 cl-text-2 mb-0">
                                            После проверки администратор откроет специальные цены на все товары. Обычно это 1 рабочий день.
                                        </p>
                                    </div>

                                    <div class="group-action mt-20">
                                        <button type="submit" class="tf-btn animate-btn">
                                            Отправить заявку
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </section>

</main>

@push('styles')
<style>
.box-account-info,
.account-menu,
.account-content {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
}

.account-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #111;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
    flex-shrink: 0;
}

.account-menu {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.account-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    border-radius: 8px;
    color: #333;
    text-decoration: none;
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    font-weight: 500;
    transition: .2s;
}

.account-menu-item:hover,
.account-menu-item.active {
    background: #111;
    color: #fff;
}

.account-menu-button {
    cursor: pointer;
}

.account-menu-button:hover {
    background: #dc2626;
    color: #fff;
}

.account-tab {
    display: none;
}

.account-tab.active {
    display: block;
}

.badge-account {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-warning {
    background: #fef9c3;
    color: #854d0e;
}

.badge-gray {
    background: #f3f4f6;
    color: #374151;
}

.table-account {
    width: 100%;
    border-collapse: collapse;
}

.table-account th,
.table-account td {
    padding: 14px 12px;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
}

.table-account th {
    font-weight: 600;
    color: #111;
    background: #fafafa;
}

.order-products {
    background: #fafafa;
    border-radius: 8px;
    padding: 10px 12px;
}

.order-product-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 4px 0;
}

.account-empty {
    border: 1px dashed #ddd;
    border-radius: 12px;
    padding: 50px 20px;
    background: #fafafa;
}

.account-content .form-log {
    max-width: none;
}

.account-content select,
.account-content textarea {
    width: 100%;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    background: #fff;
}

.account-content textarea {
    resize: vertical;
    min-height: 130px;
}

.request-note {
    background: #fafafa;
    border: 1px solid #eeeeee;
    border-radius: 10px;
    padding: 14px 16px;
}

/* B2B выбор типа */
.b2b-choice {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.b2b-choice-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    cursor: pointer;
    transition: .2s;
}
.b2b-choice-card:hover { border-color: #999; }
.b2b-choice-card.selected {
    border-color: #111;
    background: #f9f9f9;
}
.b2b-choice-icon { font-size: 28px; flex-shrink: 0; }

/* Цветные статусы */
.badge-info    { background: #dbeafe; color: #1e40af; }
.badge-primary { background: #e0e7ff; color: #3730a3; }
.badge-danger  { background: #fee2e2; color: #991b1b; }

@media (max-width: 767px) {
    .box-account-info,
    .account-menu,
    .account-content {
        padding: 16px;
    }

    .order-product-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
@endpush

@push('scripts')
<script>
// B2B: переключение карточек
document.addEventListener('DOMContentLoaded', function () {
    const radios  = document.querySelectorAll('.b2b-pick-radio');
    const cards   = document.querySelectorAll('.b2b-choice-card');
    const hidden  = document.getElementById('b2b-client-type');

    function syncCards() {
        radios.forEach(function (r) {
            const card = r.closest('.b2b-choice-card');
            if (r.checked) {
                card.classList.add('selected');
                if (hidden) hidden.value = r.value;
            } else {
                card.classList.remove('selected');
            }
        });
    }

    radios.forEach(r => r.addEventListener('change', syncCards));
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            const radio = this.querySelector('.b2b-pick-radio');
            if (radio) { radio.checked = true; syncCards(); }
        });
    });

    syncCards(); // инициализация
});

function showAccountTab(tabName) {
    document.querySelectorAll('.account-tab').forEach(function (tab) {
        tab.classList.remove('active');
    });

    document.querySelectorAll('.account-menu-item[data-tab]').forEach(function (item) {
        item.classList.remove('active');
    });

    const tab = document.getElementById('account-tab-' + tabName);

    if (tab) {
        tab.classList.add('active');
    }

    document.querySelectorAll('.account-menu-item[data-tab="' + tabName + '"]').forEach(function (item) {
        item.classList.add('active');
    });

    localStorage.setItem('account_tab', tabName);
}

document.addEventListener('DOMContentLoaded', function () {
    const savedTab = localStorage.getItem('account_tab');

    @if ($errors->has('current_password'))
        showAccountTab('security');
    @elseif ($errors->has('company_name') || $errors->has('company_inn') || $errors->has('b2b_comment'))
        showAccountTab('b2b');
    @elseif ($errors->any())
        showAccountTab('profile');
    @elseif (session('open_tab'))
        showAccountTab('{{ session("open_tab") }}');
    @else
        showAccountTab(savedTab || 'orders');
    @endif
});
</script>
@endpush

@endsection