<x-email.brand-shell
    subject="Станьте частью семьи KOTLOV — анкета партнёра"
    eyebrow="ПАРТНЁРСКАЯ СЕТЬ KOTLOV"
    title="Станьте частью семьи KOTLOV"
    logo-src="cid:kotlov-logo"
    :button-url="$onboarding->url()"
    button-label="Заполнить анкету партнёра"
    notice="Ссылка личная — по ней анкета вашей компании. Заполнение займёт около 5 минут."
>
    <p style="margin:0 0 14px">Здравствуйте{{ $onboarding->company_name ? ', '.$onboarding->company_name : '' }}!</p>
    <p style="margin:0 0 14px">KOTLOV уходит в B2B и передаёт розницу партнёрам в городах. Клиенты, которые выбирают ваш город на kotlov.by, будут видеть ваш телефон и адрес, а заказы с сайта — передаваться вам.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:0 0 18px">
        @foreach([
            'Клиенты и заказы с сайта kotlov.by в вашем городе',
            'Цены ниже розницы и доступ к складам поставщиков',
            'Своя страница и точки продаж в блоке «Где купить»',
            'Калькуляторы подбора и поддержка инженеров KOTLOV',
        ] as $benefit)
            <tr><td style="padding:7px 0;border-bottom:1px solid #edf1f5">✓ {{ $benefit }}</td></tr>
        @endforeach
    </table>
    <p style="margin:0 0 18px">Заполните короткую анкету: реквизиты, тип компании и адреса ваших точек продаж. Менеджер проверит данные и откроет доступ в B2B-кабинет.</p>
</x-email.brand-shell>
