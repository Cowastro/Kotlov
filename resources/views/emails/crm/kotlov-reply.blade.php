<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KOTLOV.BY</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#202124;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(17,24,39,.08);">
                <tr>
                    <td style="padding:22px 30px;background:#111216;border-bottom:4px solid #f28c00;">
                        <div style="font-size:26px;line-height:1;font-weight:800;letter-spacing:1.5px;color:#ffffff;">KOTLOV<span style="color:#f28c00;">.BY</span></div>
                        <div style="margin-top:8px;font-size:12px;line-height:1.5;letter-spacing:.8px;color:#c8cbd1;text-transform:uppercase;">Отопление · водоснабжение · профессиональный подбор</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 30px 22px;font-size:16px;line-height:1.65;color:#202124;">
                        {!! nl2br(e(trim($body))) !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 30px 30px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #e6e7ea;">
                            <tr>
                                <td style="padding-top:22px;font-size:14px;line-height:1.6;color:#5f6368;">
                                    С уважением,<br>
                                    <strong style="color:#202124;">{{ $managerName }}</strong><br>
                                    @if(filled($managerJobTitle))
                                        {{ $managerJobTitle }}<br>
                                    @endif
                                    @if(filled($managerPhone))
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $managerPhone) }}" style="color:#202124;text-decoration:none;">{{ $managerPhone }}</a><br>
                                    @endif
                                    Команда KOTLOV.BY
                                </td>
                                <td align="right" style="padding-top:22px;font-size:14px;line-height:1.7;color:#5f6368;">
                                    @foreach(($company['phones'] ?? []) as $phone)
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}" style="color:#202124;text-decoration:none;">{{ $phone }}</a><br>
                                    @endforeach
                                    <a href="mailto:{{ $company['email'] ?? 'info@kotlov.by' }}" style="color:#f28c00;text-decoration:none;">{{ $company['email'] ?? 'info@kotlov.by' }}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:18px 30px;background:#f7f7f8;font-size:12px;line-height:1.6;color:#7a7f87;">
                        <a href="https://kotlov.by" style="color:#f28c00;font-weight:700;text-decoration:none;">kotlov.by</a><br>
                        {{ $company['short_name'] ?? 'KOTLOV.BY' }} · {{ $company['address'] ?? '' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
