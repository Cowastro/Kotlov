<x-email.brand-shell
    subject="Для вас открыт B2B-кабинет KOTLOV — без новой регистрации"
    eyebrow="ПАРТНЁРАМ-МОНТАЖНИКАМ"
    :title="'Здравствуйте'.($profile->contact_name ? ', '.$profile->contact_name : '').'!'"
    logo-src="cid:kotlov-logo"
    :button-url="$activationUrl"
    button-label="Задать пароль и войти"
    notice="Ссылка персональная, действует 14 дней и срабатывает один раз."
>
    <p style="margin:0 0 18px">
        Ваш профиль монтажника{{ $profile->company_name ? ' «'.$profile->company_name.'»' : '' }} на KOTLOV.BY подключён к B2B-кабинету.
        Регистрироваться заново не нужно — это тот же аккаунт, а ваша страница в каталоге монтажников остаётся как есть.
    </p>
    <p style="margin:0 0 8px">В B2B-кабинете:</p>
    <ul style="margin:0 0 18px;padding-left:20px">
        <li>партнёрские цены на оборудование;</li>
        <li>заказ со склада с резервом и подтверждением наличия;</li>
        <li>счета, документы и история заказов компании.</li>
    </ul>
    <p style="margin:0">
        E-mail для входа: <strong>{{ $profile->user?->email }}</strong>. Если вы помните пароль, можно просто войти на сайте.
    </p>
</x-email.brand-shell>
