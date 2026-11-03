<x-email.brand-shell
    :subject="'Вы перешли на уровень '.$levelName"
    eyebrow="Партнёрская программа KOTLOV"
    :title="'Вы перешли на уровень '.$levelName"
    logo-src="cid:kotlov-logo"
    :button-url="route('b2b.cabinet')"
    button-label="Открыть кабинет"
>
    <p>Условия вашего уровня доступны в кабинете партнёра KOTLOV.</p>
    @if($benefits)
        <ul>
            @foreach($benefits as $label => $terms)
                <li><strong>{{ $label }}</strong> — {{ $terms }}</li>
            @endforeach
        </ul>
    @endif
</x-email.brand-shell>
