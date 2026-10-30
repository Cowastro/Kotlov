<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Приглашение в B2B-кабинет</title></head>
<body style="margin:0;background:#f3f6fa;font-family:Arial,sans-serif;color:#14243a">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;background:#f3f6fa"><tr><td align="center">
    <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 12px 35px rgba(20,36,58,.09)">
        <tr><td style="padding:28px 34px;background:#14243a"><img src="cid:kotlov-logo" alt="KOTLOV.BY" width="150" style="display:block;filter:brightness(0) invert(1)"></td></tr>
        <tr><td style="padding:36px 34px">
            <div style="font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#1aa67a">B2B-кабинет компании</div>
            <h1 style="margin:10px 0 16px;font-size:28px;line-height:1.2">Вас пригласили в {{ $company }}</h1>
            <p style="margin:0 0 18px;font-size:16px;line-height:1.6;color:#526174">{{ $inviter ? $inviter.' открыл(а)' : 'Владелец компании открыл' }} вам доступ к рабочему кабинету KOTLOV.BY.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#eef9f5;border:1px solid #d1eee4;border-radius:12px"><tr><td style="padding:18px 20px"><strong style="display:block;margin-bottom:5px">Ваша роль: {{ $role }}</strong><span style="font-size:14px;color:#526174">Заказы, документы и условия компании доступны строго в пределах назначенной роли.</span></td></tr></table>
            <a href="{{ $invitationUrl }}" style="display:block;padding:15px 22px;border-radius:10px;background:#14243a;color:#fff;text-align:center;text-decoration:none;font-size:16px;font-weight:700">Принять приглашение</a>
            <p style="margin:20px 0 0;font-size:13px;line-height:1.55;color:#7b8795">Ссылка одноразовая и действует до {{ $expiresAt->format('d.m.Y H:i') }}. Если вы не ожидали приглашения, просто проигнорируйте письмо.</p>
        </td></tr>
        <tr><td style="padding:20px 34px;background:#f8fafc;font-size:12px;line-height:1.5;color:#8a95a3">KOTLOV.BY — инженерный центр и цифровой склад отопительного оборудования.</td></tr>
    </table>
</td></tr></table>
</body>
</html>
