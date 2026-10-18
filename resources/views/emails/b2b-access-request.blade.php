<x-email.brand-shell
    subject="Новая заявка на B2B-доступ — KOTLOV.BY"
    eyebrow="НОВАЯ B2B-ЗАЯВКА"
    title="Партнёр ожидает проверки"
    logo-src="cid:kotlov-logo"
    :button-url="$adminUrl"
    button-label="Проверить и одобрить партнёра"
    notice="Системное уведомление отдела B2B KOTLOV.BY."
>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#f7f7f8;border:1px solid #ececec;border-radius:12px">
        @foreach ([
            'Компания' => $applicant->company_name ?: '—',
            'Контакт' => $applicant->name ?: '—',
            'Email' => $applicant->email,
            'Телефон' => $applicant->phone ?: '—',
            'УНП' => $applicant->company_inn ?: '—',
        ] as $label => $value)
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #e5e5e5;font-size:14px;color:#777777">{{ $label }}</td>
                <td align="right" style="padding:12px 16px;border-bottom:1px solid #e5e5e5;font-size:14px;font-weight:700;color:#101010">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    <p style="margin:20px 0 6px;font-size:13px;font-weight:700;color:#101010">Комментарий</p>
    <p style="margin:0">{{ $applicant->b2b_comment ?: '—' }}</p>
</x-email.brand-shell>
