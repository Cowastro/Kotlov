<x-email.brand-shell
    :subject="'Код доступа к предложению '.$reference"
    eyebrow="ЗАЩИЩЁННОЕ ПРЕДЛОЖЕНИЕ"
    title="Подтвердите вход"
    logo-src="cid:kotlov-logo"
    notice="Код создан автоматически для безопасного просмотра предложения KOTLOV.BY."
>
    <p style="margin:0 0 16px">Код подтверждения для предложения <strong style="color:#101010">{{ $reference }}</strong>:</p>
    <div style="padding:18px 20px;background:#f7f7f8;border:1px solid #dedede;border-left:5px solid #DC4646;border-radius:12px;text-align:center;font-size:30px;line-height:38px;font-weight:700;letter-spacing:.18em;color:#101010">{{ $code }}</div>
    <p style="margin:16px 0 0">Код действует 10 минут. Если вы не запрашивали доступ, проигнорируйте письмо.</p>
</x-email.brand-shell>
