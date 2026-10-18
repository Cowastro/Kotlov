<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#171717;font-family:Arial,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:28px 12px">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #e5e7eb">
                <tr>
                    <td style="padding:28px 32px 22px;border-bottom:1px solid #ededed">
                        <img src="cid:kotlov-logo" alt="KOTLOV.BY" width="156" style="display:block;max-width:156px;height:auto">
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px 32px 12px">
                        <div style="font-size:12px;line-height:18px;font-weight:700;letter-spacing:.09em;color:#e63c47">{{ $eyebrow }}</div>
                        <h1 style="margin:10px 0 14px;font-size:28px;line-height:36px;color:#111">{{ $title }}</h1>
                        <p style="margin:0;font-size:16px;line-height:26px;color:#5f6368">Здравствуйте, Денис!</p>
                        <p style="margin:12px 0 0;font-size:16px;line-height:26px;color:#5f6368">{{ $message }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f7f8;border-radius:12px;padding:8px 20px">
                            @foreach ($details as $label => $value)
                                <tr>
                                    <td style="padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:14px;color:#777">{{ $label }}</td>
                                    <td align="right" style="padding:12px 0;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#222">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 32px 34px">
                        <a href="{{ $siteUrl }}" style="display:inline-block;padding:14px 24px;background:#111;color:#fff;text-decoration:none;border-radius:999px;font-size:15px;font-weight:700">{{ $button }}</a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 32px;background:#111;color:#aeb0b4;font-size:12px;line-height:20px">
                        <strong style="color:#fff">KOTLOV.BY</strong><br>
                        {{ $notice }}<br>
                        Отправитель: {{ $sender }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
