<x-email.brand-shell
    subject="Подтвердите email — KOTLOV.BY"
    eyebrow="АКТИВАЦИЯ АККАУНТА"
    title="Подтвердите ваш email"
    logo-src="cid:kotlov-logo"
    :button-url="$verificationUrl"
    button-label="Активировать аккаунт"
    notice="Это автоматическое письмо службы безопасности KOTLOV.BY. Отвечать на него не нужно."
>
    <p style="margin:0">Здравствуйте{{ filled($name) ? ', '.$name : '' }}!</p>
    <p style="margin:12px 0 0">Для завершения регистрации подтвердите, что этот email принадлежит вам.</p>
    <p style="margin:12px 0 0">Ссылка действует <strong style="color:#101010">{{ $minutes }} минут</strong>. После подтверждения откроется ваш личный кабинет.</p>
    <p style="margin:12px 0 0">Если вы не регистрировались на KOTLOV.BY, просто проигнорируйте письмо — аккаунт не будет активирован.</p>
</x-email.brand-shell>
