@extends('layouts.amerce')

@section('content')
@php $clientTypeLabel = $user->client_type_label ?? 'Розничный клиент'; @endphp

{{-- Кнопка открытия сайдбара на мобиле --}}
<div class="btn-sidebar-mb d-lg-none left">
    <button data-bs-toggle="offcanvas" data-bs-target="#mbSidebar">
        <i class="icon icon-sidebar"></i>
    </button>
</div>

<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Личный кабинет</p>
                </div>
                <h3>Личный кабинет</h3>
                <p class="text-body-1 cl-text-2">
                    Управляйте профилем, отслеживайте заказы и обновляйте личные данные.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">

            @if (session('success'))
                <div class="alert alert-success mb-20">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger mb-20">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="row">

                {{-- ===== САЙДБАР ===== --}}
                <div class="col-lg-4 col-xl-3">
                    <div class="sidebar-account-wrap sidebar-content-wrap sticky-top d-lg-block d-none">

                        {{-- Инфо о пользователе --}}
                        <div class="d-flex align-items-center gap-16 mb-16 px-8 pt-8">
                            <div class="tf-account-avatar">
                                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <h6 class="mb-2 fw-semibold">{{ $user->name }}</h6>
                                <p class="text-caption-01 cl-text-2 mb-0">{{ $user->email }}</p>
                                @if ($user->phone)
                                    <p class="text-caption-01 cl-text-2 mb-0">{{ $user->phone }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="br-line mb-8"></div>

                        <div class="my-account-nav">
                            <a href="#" class="link-account {{ session('open_tab', 'orders') === 'orders' && !$errors->any() ? 'active' : '' }}"
                                data-tab="orders" onclick="showAccountTab('orders'); return false;">
                                <i class="icon icon-Package"></i>
                                <span class="text h6 fw-medium">Мои заказы</span>
                            </a>
                            <a href="#" class="link-account"
                                data-tab="profile" onclick="showAccountTab('profile'); return false;">
                                <i class="icon icon-User"></i>
                                <span class="text h6 fw-medium">Профиль</span>
                            </a>
                            <a href="#" class="link-account"
                                data-tab="security" onclick="showAccountTab('security'); return false;">
                                <i class="icon icon-ShieldCheck"></i>
                                <span class="text h6 fw-medium">Безопасность</span>
                            </a>
                            @if ($user->isRetailClient())
                                <a href="#" class="link-account"
                                    data-tab="b2b" onclick="showAccountTab('b2b'); return false;">
                                    <i class="icon icon-GearSix"></i>
                                    <span class="text h6 fw-medium">Спецусловия</span>
                                </a>
                            @endif
                            <a href="/wishlist" class="link-account">
                                <i class="icon icon-HeartStraight"></i>
                                <span class="text h6 fw-medium">Избранное</span>
                            </a>
                            <form action="/logout" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="link-account w-100 text-start border-0 bg-transparent">
                                    <i class="icon icon-SignOut"></i>
                                    <span class="text h6 fw-medium">Выйти</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ===== КОНТЕНТ ===== --}}
                <div class="col-lg-8 ms-auto">
                    <div class="my-account-content">

                        {{-- ЗАКАЗЫ --}}
                        <div id="account-tab-orders" class="account-tab">
                            <h4 class="account-title">Мои заказы</h4>

                            @if ($orders->count() > 0)
                                <div class="overflow-auto">
                                    <table class="table-my_recent">
                                        <thead>
                                            <tr>
                                                <th>Заказ</th>
                                                <th>Товары</th>
                                                <th>Сумма</th>
                                                <th>Статус</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($orders as $order)
                                                <tr class="tb-order-item">
                                                    <td class="tb-order_code fw-medium">#{{ $order->id }}</td>
                                                    <td>
                                                        @if ($order->items && $order->items->count())
                                                            @foreach ($order->items->take(2) as $item)
                                                                <div class="tb-order_product">
                                                                    <div class="infor-prd">
                                                                        <p class="prd_name fw-medium lh-24 mb-0">
                                                                            {{ Str::limit($item->product->name ?? 'Товар удалён', 40) }}
                                                                        </p>
                                                                        <p class="prd_type cl-text-2 text-caption-01 mb-0">
                                                                            {{ $item->quantity }} шт. × {{ number_format($item->price, 2, '.', ' ') }} BYN
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                            @if ($order->items->count() > 2)
                                                                <p class="text-caption-01 cl-text-3 mt-4 mb-0">
                                                                    + ещё {{ $order->items->count() - 2 }} поз.
                                                                </p>
                                                            @endif
                                                        @else
                                                            <span class="cl-text-3">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="tb-order_price fw-medium">
                                                        {{ number_format($order->total_price ?? 0, 2, '.', ' ') }} BYN
                                                    </td>
                                                    <td>
                                                        @php
                                                            $statusMap = [
                                                                'pending'    => ['label' => 'Ожидает',    'class' => 'stt-pending'],
                                                                'processing' => ['label' => 'В обработке','class' => 'stt-processing'],
                                                                'paid'       => ['label' => 'Оплачен',    'class' => 'stt-completed'],
                                                                'shipped'    => ['label' => 'Отправлен',  'class' => 'stt-processing'],
                                                                'delivered'  => ['label' => 'Доставлен',  'class' => 'stt-completed'],
                                                                'completed'  => ['label' => 'Выполнен',   'class' => 'stt-completed'],
                                                                'cancelled'  => ['label' => 'Отменён',    'class' => 'stt-cancelled'],
                                                            ];
                                                            $st = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => ''];
                                                        @endphp
                                                        <div class="tb-order_status text-label {{ $st['class'] }}">
                                                            {{ $st['label'] }}
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if ($orders->hasPages())
                                    <div class="mt-24">{{ $orders->links() }}</div>
                                @endif

                            @else
                                <div class="text-center py-60">
                                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                                    <h5 class="mb-8">Заказов пока нет</h5>
                                    <p class="text-body-1 cl-text-2 mb-24">
                                        Самое время выбрать котёл, камин или оборудование для отопления.
                                    </p>
                                    <a href="/catalog" class="tf-btn animate-btn">Перейти в каталог</a>
                                </div>
                            @endif
                        </div>

                        {{-- ПРОФИЛЬ --}}
                        <div id="account-tab-profile" class="account-tab">
                            <h4 class="account-title">Профиль</h4>
                            <div class="account-my_address setting">
                                <form action="/account/profile" method="POST" class="form-setting">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-content">
                                        <div class="tf-grid-layout sm-col-2">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">
                                                    Имя <span class="text-primary">*</span>
                                                </label>
                                                <input type="text" name="name"
                                                    value="{{ old('name', $user->name) }}" required>
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Email</label>
                                                <input type="email" value="{{ $user->email }}" disabled>
                                            </fieldset>
                                        </div>
                                        <div class="tf-grid-layout sm-col-2">
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Телефон</label>
                                                <input type="tel" name="phone"
                                                    value="{{ old('phone', $user->phone) }}"
                                                    placeholder="+375 (29) 000-00-00">
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Тип аккаунта</label>
                                                <input type="text" value="{{ $clientTypeLabel }}" disabled>
                                            </fieldset>
                                        </div>
                                    </div>
                                    <div class="btn-submit">
                                        <button type="submit" class="tf-btn animate-btn">
                                            Сохранить изменения
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- БЕЗОПАСНОСТЬ --}}
                        <div id="account-tab-security" class="account-tab">
                            <h4 class="account-title">Безопасность</h4>
                            <div class="account-my_address setting">
                                <p class="mb-12 h6 fw-medium">Изменение пароля</p>
                                <form action="/account/password" method="POST" class="form-setting">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-content">
                                        <fieldset class="tf-field password-wrapper">
                                            <label class="tf-lable fw-medium">
                                                Текущий пароль <span class="text-primary">*</span>
                                            </label>
                                            <div class="password-wrapper w-100">
                                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                                <input class="password-field" type="password"
                                                    name="current_password" required>
                                            </div>
                                        </fieldset>
                                        <fieldset class="tf-field password-wrapper">
                                            <label class="tf-lable fw-medium">
                                                Новый пароль <span class="text-primary">*</span>
                                            </label>
                                            <div class="password-wrapper w-100">
                                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                                <input class="password-field" type="password"
                                                    name="password" required>
                                            </div>
                                        </fieldset>
                                        <fieldset class="tf-field password-wrapper">
                                            <label class="tf-lable fw-medium">
                                                Повторите пароль <span class="text-primary">*</span>
                                            </label>
                                            <div class="password-wrapper w-100">
                                                <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                                <input class="password-field" type="password"
                                                    name="password_confirmation" required>
                                            </div>
                                        </fieldset>
                                    </div>
                                    <div class="btn-submit">
                                        <button type="submit" class="tf-btn animate-btn">
                                            Изменить пароль
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- СПЕЦУСЛОВИЯ B2B --}}
                        @if ($user->isRetailClient())
                            <div id="account-tab-b2b" class="account-tab">
                                <h4 class="account-title">Специальные условия</h4>
                                <div class="account-my_address setting">
                                    <p class="cl-text-2 mb-20">
                                        Закупаете оптом или занимаетесь монтажом? Оставьте заявку — откроем персональные цены.
                                    </p>

                                    <div class="b2b-choice mb-24">
                                        <label class="b2b-choice-card" id="card-wholesale">
                                            <input type="radio" name="b2b_pick" value="wholesale"
                                                class="b2b-pick-radio" style="display:none" checked>
                                            <span class="b2b-choice-icon">🏪</span>
                                            <div>
                                                <p class="fw-semibold mb-2">Оптовик</p>
                                                <p class="text-caption-01 cl-text-2 mb-0">
                                                    Закупаю оборудование для перепродажи или крупных объектов
                                                </p>
                                            </div>
                                        </label>
                                        <label class="b2b-choice-card" id="card-installer">
                                            <input type="radio" name="b2b_pick" value="installer"
                                                class="b2b-pick-radio" style="display:none">
                                            <span class="b2b-choice-icon">🔧</span>
                                            <div>
                                                <p class="fw-semibold mb-2">Монтажник</p>
                                                <p class="text-caption-01 cl-text-2 mb-0">
                                                    Устанавливаю оборудование у клиентов
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    <form action="/account/b2b-request" method="POST" class="form-setting">
                                        @csrf
                                        <input type="hidden" name="client_type" id="b2b-client-type"
                                            value="{{ old('client_type', 'wholesale') }}">
                                        <div class="form-content">
                                            <div class="tf-grid-layout sm-col-2">
                                                <fieldset class="tf-field">
                                                    <label class="tf-lable fw-medium">
                                                        Компания / ФИО ИП <span class="text-primary">*</span>
                                                    </label>
                                                    <input type="text" name="company_name"
                                                        value="{{ old('company_name', $user->company_name) }}"
                                                        placeholder="ООО Теплосервис" required>
                                                </fieldset>
                                                <fieldset class="tf-field">
                                                    <label class="tf-lable fw-medium">УНП / ИНН</label>
                                                    <input type="text" name="company_inn"
                                                        value="{{ old('company_inn', $user->company_inn) }}"
                                                        placeholder="100000000">
                                                </fieldset>
                                            </div>
                                            <fieldset class="tf-field">
                                                <label class="tf-lable fw-medium">Комментарий</label>
                                                <textarea name="b2b_comment" rows="3"
                                                    placeholder="Объёмы закупок, регион работы...">{{ old('b2b_comment') }}</textarea>
                                            </fieldset>
                                        </div>
                                        <div class="request-note mt-16">
                                            <p class="text-caption-01 cl-text-2 mb-0">
                                                После проверки администратор откроет специальные цены. Обычно 1 рабочий день.
                                            </p>
                                        </div>
                                        <div class="btn-submit">
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
        </div>
    </section>

</main>

{{-- Мобильный сайдбар --}}
<div class="offcanvas offcanvas-start" id="mbSidebar">
    <div class="canvas-wrapper">
        <div class="canvas-header">
            <div class="d-flex align-items-center gap-16 mb-16">
                <div class="tf-account-avatar">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <div>
                    <h6 class="mb-2 fw-semibold">{{ $user->name }}</h6>
                    <p class="text-caption-01 cl-text-2 mb-0">{{ $user->email }}</p>
                </div>
            </div>
        </div>
        <div class="canvas-body">
            <div class="my-account-nav">
                <a href="#" class="link-account" data-tab="orders"
                    onclick="showAccountTab('orders'); bootstrap.Offcanvas.getInstance(document.getElementById('mbSidebar')).hide(); return false;">
                    <i class="icon icon-Package"></i>
                    <span class="text h6 fw-medium">Мои заказы</span>
                </a>
                <a href="#" class="link-account" data-tab="profile"
                    onclick="showAccountTab('profile'); bootstrap.Offcanvas.getInstance(document.getElementById('mbSidebar')).hide(); return false;">
                    <i class="icon icon-User"></i>
                    <span class="text h6 fw-medium">Профиль</span>
                </a>
                <a href="#" class="link-account" data-tab="security"
                    onclick="showAccountTab('security'); bootstrap.Offcanvas.getInstance(document.getElementById('mbSidebar')).hide(); return false;">
                    <i class="icon icon-ShieldCheck"></i>
                    <span class="text h6 fw-medium">Безопасность</span>
                </a>
                @if ($user->isRetailClient())
                    <a href="#" class="link-account" data-tab="b2b"
                        onclick="showAccountTab('b2b'); bootstrap.Offcanvas.getInstance(document.getElementById('mbSidebar')).hide(); return false;">
                        <i class="icon icon-GearSix"></i>
                        <span class="text h6 fw-medium">Спецусловия</span>
                    </a>
                @endif
                <a href="/wishlist" class="link-account">
                    <i class="icon icon-HeartStraight"></i>
                    <span class="text h6 fw-medium">Избранное</span>
                </a>
                <form action="/logout" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="link-account w-100 text-start border-0 bg-transparent">
                        <i class="icon icon-SignOut"></i>
                        <span class="text h6 fw-medium">Выйти</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.tf-account-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: #111; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: 700; flex-shrink: 0;
}
.account-tab { display: none; }
.account-tab.active { display: block; }

.b2b-choice { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.b2b-choice-card {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 16px; border: 2px solid #e8e8e8; border-radius: 10px;
    cursor: pointer; transition: .2s;
}
.b2b-choice-card:hover { border-color: #999; }
.b2b-choice-card.selected { border-color: #111; background: #f9f9f9; }
.b2b-choice-icon { font-size: 26px; flex-shrink: 0; }
.request-note {
    background: #fafafa; border: 1px solid #eee;
    border-radius: 10px; padding: 14px 16px;
}
.tb-order_status.stt-pending    { background: #fef9c3; color: #854d0e; }
.tb-order_status.stt-processing { background: #dbeafe; color: #1e40af; }
.tb-order_status.stt-completed  { background: #dcfce7; color: #166534; }
.tb-order_status.stt-cancelled  { background: #fee2e2; color: #991b1b; }
.account-my_address textarea {
    width: 100%; border: 1px solid #e0e0e0; border-radius: 8px;
    padding: 10px 14px; font-size: 14px; resize: vertical; min-height: 100px;
}
@media (max-width: 575px) {
    .b2b-choice { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // B2B: переключение карточек
    const radios = document.querySelectorAll('.b2b-pick-radio');
    const hidden = document.getElementById('b2b-client-type');
    function syncCards() {
        radios.forEach(function (r) {
            const card = r.closest('.b2b-choice-card');
            if (card) card.classList.toggle('selected', r.checked);
            if (r.checked && hidden) hidden.value = r.value;
        });
    }
    radios.forEach(r => r.addEventListener('change', syncCards));
    document.querySelectorAll('.b2b-choice-card').forEach(function (card) {
        card.addEventListener('click', function () {
            const radio = this.querySelector('.b2b-pick-radio');
            if (radio) { radio.checked = true; syncCards(); }
        });
    });
    syncCards();

    // Открываем нужный таб
    @if ($errors->has('current_password'))
        showAccountTab('security');
    @elseif ($errors->has('company_name') || $errors->has('company_inn') || $errors->has('b2b_comment'))
        showAccountTab('b2b');
    @elseif ($errors->any())
        showAccountTab('profile');
    @elseif (session('open_tab'))
        showAccountTab('{{ session("open_tab") }}');
    @else
        showAccountTab(localStorage.getItem('account_tab') || 'orders');
    @endif
});

function showAccountTab(tabName) {
    document.querySelectorAll('.account-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.link-account[data-tab]').forEach(a => a.classList.remove('active'));

    var tab = document.getElementById('account-tab-' + tabName);
    if (tab) tab.classList.add('active');

    document.querySelectorAll('.link-account[data-tab="' + tabName + '"]').forEach(a => a.classList.add('active'));
    localStorage.setItem('account_tab', tabName);
}
</script>
@endpush

@endsection
