<x-email.brand-shell
    subject="Вы подключены к KOTLOV — добро пожаловать в семью"
    eyebrow="ПАРТНЁРСКАЯ СЕТЬ KOTLOV"
    title="Добро пожаловать в семью KOTLOV"
    logo-src="cid:kotlov-logo"
    :button-url="$loginUrl"
    :button-label="$setPassword ? 'Задать пароль и войти' : 'Открыть B2B-кабинет'"
    :notice="$setPassword ? 'Ссылка действует 14 дней и срабатывает один раз.' : 'Войдите под своим e-mail и паролем.'"
>
    <p style="margin:0 0 14px">{{ $client->company_name }} подключена к партнёрской сети KOTLOV.</p>
    <p style="margin:0 0 14px">В B2B-кабинете — оптовый каталог с вашими ценами и остатками складов, ваши точки продаж и раздел «Заказы клиентов», куда приходят заказы с сайта.</p>
    @if($client->branches()->exists())
        <p style="margin:0 0 18px">Ваши точки продаж уже показываются в блоке «Где купить» на kotlov.by: {{ $client->branches()->with('city')->get()->map(fn ($b) => $b->city?->name)->filter()->unique()->implode(', ') }}.</p>
    @endif
</x-email.brand-shell>
