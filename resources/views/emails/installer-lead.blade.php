<x-email.brand-shell
    :subject="'Новая заявка на монтаж: '.$kind"
    eyebrow="ЗАЯВКА ДЛЯ МОНТАЖНИКОВ"
    :title="'Здравствуйте'.($profile->contact_name ? ', '.$profile->contact_name : '').'!'"
    logo-src="cid:kotlov-logo"
    :button-url="$leadUrl"
    button-label="Посмотреть и взять заявку"
    :notice="'Ссылка персональная и действует '.$linkDays.' дней. Вы получили письмо, потому что ваш профиль опубликован в каталоге монтажников KOTLOV.BY. Отказаться от заявок можно по той же ссылке.'"
>
    <p style="margin:0 0 18px">
        На KOTLOV.BY пришла заявка на монтаж в вашем регионе.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;margin:0 0 18px;border-collapse:collapse">
        <tr><td style="padding:6px 0;color:#6b6b6b;width:38%">Работы</td><td style="padding:6px 0"><strong>{{ $kind }}</strong></td></tr>
        @if($place)
            <tr><td style="padding:6px 0;color:#6b6b6b">Где</td><td style="padding:6px 0">{{ $place }}</td></tr>
        @endif
        @if($request->preferred_date)
            <tr><td style="padding:6px 0;color:#6b6b6b">Желаемая дата</td><td style="padding:6px 0">{{ $request->preferred_date->format('d.m.Y') }}</td></tr>
        @endif
    </table>
    <p style="margin:0">
        Контакты клиента получат первые {{ $maxTakers }} монтажника, которые возьмут заявку. Откройте ссылку, прочитайте описание и нажмите «Взять заявку».
    </p>
</x-email.brand-shell>
