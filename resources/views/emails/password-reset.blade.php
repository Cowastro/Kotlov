<x-email.brand-shell
    subject="Доступ к аккаунту KOTLOV.BY"
    eyebrow="БЕЗОПАСНЫЙ ДОСТУП"
    title="Создайте новый пароль"
    logo-src="cid:kotlov-logo"
    :button-url="$resetUrl"
    button-label="Задать новый пароль"
    notice="Это автоматическое письмо. Отвечать на него не нужно."
>
    <p style="margin:0">Здравствуйте{{ filled($name) ? ', '.$name : '' }}!</p>
    <p style="margin:12px 0 0">Для вашего аккаунта запрошено создание или восстановление пароля.</p>
    <p style="margin:12px 0 0">Ссылка действует <strong style="color:#101010">{{ $minutes }} минут</strong>.</p>
    <p style="margin:12px 0 0">Если вы не запрашивали доступ, просто проигнорируйте это письмо.</p>
</x-email.brand-shell>
