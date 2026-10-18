<x-email.brand-shell
    :subject="$subject"
    :eyebrow="$eyebrow"
    :title="$title"
    logo-src="cid:kotlov-logo"
    :button-url="$siteUrl"
    :button-label="$button"
    :notice="$notice"
    :sender="$sender"
>
    <p style="margin:0">Здравствуйте, Денис!</p>
    <p style="margin:12px 0 20px">{{ $message }}</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f7f7f8;border:1px solid #ececec;border-radius:12px">
        @foreach ($details as $label => $value)
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #e5e5e5;font-size:14px;color:#777777">{{ $label }}</td>
                <td align="right" style="padding:12px 16px;border-bottom:1px solid #e5e5e5;font-size:14px;font-weight:700;color:#101010">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
</x-email.brand-shell>
