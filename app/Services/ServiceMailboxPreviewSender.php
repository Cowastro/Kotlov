<?php

namespace App\Services;

use App\Models\CrmMailbox;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class ServiceMailboxPreviewSender
{
    private const PREVIEWS = [
        'order@kotlov.by' => [
            'subject' => 'Тест: подтверждение заказа — KOTLOV.BY',
            'eyebrow' => 'ЗАКАЗ ПРИНЯТ',
            'title' => 'Спасибо за заказ',
            'message' => 'Это тест системного письма, которое будет использоваться для уведомлений о заказах.',
            'details' => [
                'Номер заказа' => 'KOTLOV-TEST',
                'Статус' => 'Принят в обработку',
                'Следующий шаг' => 'Менеджер проверит наличие и свяжется с вами',
            ],
            'button' => 'Перейти на KOTLOV.BY',
            'notice' => 'Письмо отправлено автоматически системой заказов KOTLOV.BY.',
        ],
        'no-reply@kotlov.by' => [
            'subject' => 'Тест: доступ к аккаунту — KOTLOV.BY',
            'eyebrow' => 'БЕЗОПАСНЫЙ ДОСТУП',
            'title' => 'Доступ к аккаунту KOTLOV.BY',
            'message' => 'Это тест автоматического письма для создания или восстановления пароля.',
            'details' => [
                'Назначение' => 'Создание и восстановление пароля',
                'Срок ссылки' => '60 минут',
                'Безопасность' => 'Если вы не запрашивали доступ, письмо можно проигнорировать',
            ],
            'button' => 'Открыть KOTLOV.BY',
            'notice' => 'Это автоматическое письмо. Отвечать на него не нужно.',
        ],
    ];

    /** @return list<string> */
    public function sendAll(string $recipient): array
    {
        $recipient = mb_strtolower(trim($recipient));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Укажите корректный email получателя.');
        }

        $sent = [];
        foreach (array_keys(self::PREVIEWS) as $sender) {
            $mailbox = CrmMailbox::query()->whereRaw('LOWER(email) = ?', [$sender])->first();
            if (! $mailbox) {
                throw new RuntimeException("Служебный ящик {$sender} не настроен.");
            }

            $this->sendFrom($mailbox, $recipient);
            $sent[] = $sender;
        }

        return $sent;
    }

    public function sendFrom(CrmMailbox $mailbox, string $recipient): void
    {
        $recipient = mb_strtolower(trim($recipient));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Укажите корректный email получателя.');
        }

        $sender = mb_strtolower(trim((string) $mailbox->email));
        $preview = self::PREVIEWS[$sender] ?? null;
        if (! $mailbox->isServiceMailbox() || ! $preview) {
            throw new RuntimeException("Ящик {$sender} не является служебным.");
        }
        if (blank($mailbox->password)) {
            throw new RuntimeException("Для {$sender} не сохранён пароль SMTP.");
        }

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to($recipient)
            ->subject($preview['subject'])
            ->text($this->plainText($preview))
            ->html(view('emails.service-mailbox-preview', [
                ...$preview,
                'sender' => $sender,
                'siteUrl' => config('app.url'),
            ])->render());

        $logo = public_path('img/logo-black.png');
        if (is_file($logo)) {
            $email->embedFromPath($logo, 'kotlov-logo', 'image/png');
        }

        $this->mailer($mailbox)->send($email);
    }

    /** @param array<string, mixed> $preview */
    private function plainText(array $preview): string
    {
        $details = collect($preview['details'])
            ->map(fn (string $value, string $label): string => "{$label}: {$value}")
            ->implode("\n");

        return implode("\n\n", [
            $preview['title'],
            $preview['message'],
            $details,
            config('app.url'),
            $preview['notice'],
        ]);
    }

    private function mailer(CrmMailbox $mailbox): Mailer
    {
        $transport = new EsmtpTransport(
            $mailbox->smtp_host,
            $mailbox->smtp_port,
            in_array($mailbox->smtp_encryption, ['ssl', 'tls'], true),
        );
        $transport->setUsername($mailbox->username);
        $transport->setPassword($mailbox->password);

        return new Mailer($transport);
    }
}
