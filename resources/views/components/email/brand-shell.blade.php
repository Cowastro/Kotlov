@props([
    'subject',
    'eyebrow',
    'title',
    'logoSrc' => null,
    'buttonUrl' => null,
    'buttonLabel' => null,
    'notice' => 'Это автоматическое письмо KOTLOV.BY.',
    'sender' => null,
])
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-wrap { padding: 0 !important; }
            .email-card { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
            .email-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .email-title { font-size: 26px !important; line-height: 32px !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;background:#f4f4f4;color:#101010;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-wrap" style="width:100%;background:#f4f4f4;padding:28px 12px">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-card" style="width:100%;max-width:640px;background:#ffffff;border:1px solid #dedede;border-radius:18px;overflow:hidden">
                <tr>
                    <td class="email-pad" style="padding:26px 32px 22px;border-top:5px solid #DC4646;border-bottom:1px solid #ececec">
                        <img src="{{ $logoSrc ?: url('/img/logo-black.png') }}" alt="KOTLOV.BY" width="166" style="display:block;width:166px;max-width:100%;height:auto;border:0">
                    </td>
                </tr>
                <tr>
                    <td class="email-pad" style="padding:32px 32px 12px">
                        <div style="font-size:12px;line-height:18px;font-weight:700;letter-spacing:.09em;color:#DC4646">{{ $eyebrow }}</div>
                        <h1 class="email-title" style="margin:10px 0 0;font-size:30px;line-height:38px;color:#101010">{{ $title }}</h1>
                    </td>
                </tr>
                <tr>
                    <td class="email-pad" style="padding:8px 32px 22px;font-size:16px;line-height:26px;color:#5f6368">
                        {{ $slot }}
                    </td>
                </tr>
                @if ($buttonUrl && $buttonLabel)
                    <tr>
                        <td class="email-pad" style="padding:0 32px 34px">
                            <a href="{{ $buttonUrl }}" class="email-button" style="display:inline-block;padding:14px 24px;background:#DC4646;color:#ffffff;text-decoration:none;border-radius:999px;font-size:15px;line-height:20px;font-weight:700">{{ $buttonLabel }}</a>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="email-pad" style="padding:22px 32px;background:#101010;color:#aeb0b4;font-size:12px;line-height:20px">
                        <strong style="color:#ffffff">KOTLOV.BY</strong><br>
                        {{ $notice }}
                        @if ($sender)
                            <br>Отправитель: {{ $sender }}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
