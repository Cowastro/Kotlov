<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Новая заявка на B2B-доступ</title>
</head>
<body style="font-family:Arial,sans-serif;color:#171717;line-height:1.5">
    <h1 style="font-size:22px">Новая заявка на B2B-доступ</h1>
    <p><strong>Компания:</strong> {{ $applicant->company_name ?: '—' }}</p>
    <p><strong>Контакт:</strong> {{ $applicant->name ?: '—' }}</p>
    <p><strong>Email:</strong> {{ $applicant->email }}</p>
    <p><strong>Телефон:</strong> {{ $applicant->phone ?: '—' }}</p>
    <p><strong>УНП:</strong> {{ $applicant->company_inn ?: '—' }}</p>
    <p><strong>Комментарий:</strong><br>{{ $applicant->b2b_comment ?: '—' }}</p>
    <p style="margin-top:24px">
        <a href="{{ $adminUrl }}" style="display:inline-block;padding:12px 20px;background:#111;color:#fff;text-decoration:none;border-radius:8px">
            Проверить и одобрить партнёра
        </a>
    </p>
</body>
</html>
