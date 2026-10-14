<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KOTLOV.BY</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { padding: 0 !important; }
            .email-card { border-radius: 0 !important; }
            .email-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .email-logo { width: 178px !important; height: auto !important; }
            .signature-column { display: block !important; width: 100% !important; text-align: left !important; }
            .signature-company { padding-top: 16px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#101010;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f4f4;">
    <tr>
        <td class="email-shell" align="center" style="padding:32px 12px;">
            <table class="email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;background:#ffffff;border:1px solid #e7e7e7;border-radius:18px;overflow:hidden;box-shadow:0 12px 36px rgba(16,16,16,.08);">
                <tr>
                    <td class="email-pad" style="padding:26px 32px 22px;background:#ffffff;border-bottom:4px solid #DC4646;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <img class="email-logo" src="cid:kotlov-logo" width="210" alt="KOTLOV.BY" style="display:block;width:210px;max-width:100%;height:auto;border:0;">
                                </td>
                                <td align="right" style="vertical-align:middle;font-size:13px;line-height:1.4;">
                                    <a href="https://kotlov.by" style="color:#DC4646;font-weight:700;text-decoration:none;">kotlov.by</a>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:16px;font-size:11px;line-height:1.5;letter-spacing:1px;color:#6b6b6b;text-transform:uppercase;">Отопление · водоснабжение · профессиональный подбор</div>
                    </td>
                </tr>
                <tr>
                    <td class="email-pad" style="padding:36px 32px 30px;font-size:16px;line-height:1.65;color:#202020;">
                        {!! nl2br(e(trim($body))) !!}
                    </td>
                </tr>
                <tr>
                    <td class="email-pad" style="padding:0 32px 32px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f7f7f7;border-left:4px solid #DC4646;border-radius:10px;">
                            <tr>
                                <td class="signature-column" width="56%" style="padding:20px 18px 20px 20px;font-size:14px;line-height:1.65;color:#686868;vertical-align:top;">
                                    С уважением,<br>
                                    <strong style="color:#101010;font-size:15px;">{{ $managerName }}</strong><br>
                                    @if(filled($managerJobTitle))
                                        {{ $managerJobTitle }}<br>
                                    @endif
                                    @if(filled($managerPhone))
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $managerPhone) }}" style="color:#101010;text-decoration:none;">{{ $managerPhone }}</a><br>
                                    @endif
                                    <span style="color:#DC4646;font-weight:700;">Команда KOTLOV.BY</span>
                                </td>
                                <td class="signature-column signature-company" width="44%" align="right" style="padding:20px 20px 20px 12px;font-size:14px;line-height:1.75;color:#686868;vertical-align:top;">
                                    @foreach(($company['phones'] ?? []) as $phone)
                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}" style="color:#101010;text-decoration:none;white-space:nowrap;">{{ $phone }}</a><br>
                                    @endforeach
                                    <a href="mailto:{{ $company['email'] ?? 'info@kotlov.by' }}" style="color:#DC4646;font-weight:700;text-decoration:none;">{{ $company['email'] ?? 'info@kotlov.by' }}</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="email-pad" align="center" style="padding:18px 32px;background:#101010;font-size:12px;line-height:1.6;color:#bdbdbd;">
                        <a href="https://kotlov.by" style="color:#ffffff;font-weight:700;text-decoration:none;">KOTLOV.BY</a><br>
                        {{ $company['short_name'] ?? 'KOTLOV.BY' }} · {{ $company['legal_address'] ?? ($company['address'] ?? '') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
