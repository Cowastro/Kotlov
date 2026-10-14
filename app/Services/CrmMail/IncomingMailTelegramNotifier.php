<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailMessage;
use App\Services\TelegramApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class IncomingMailTelegramNotifier
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly CrmMailMessagePreview $messagePreview,
    ) {}

    public function send(CrmMailMessage $message): bool
    {
        $chatId = config('services.telegram.mail_chat_id');

        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram incoming mail notification skipped: credentials are not configured.', [
                'crm_mail_message_id' => $message->id,
            ]);

            return false;
        }

        $message->loadMissing(['thread', 'mailbox']);

        $escape = fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $sender = collect([$message->from_name, $message->from_email])
            ->filter(fn ($value): bool => filled($value))
            ->unique()
            ->join(' · ');
        $body = $this->messagePreview->plainText($message);
        $preview = Str::limit(Str::squish($body), 900);
        $adminUrl = url('/admin/crm-mail-threads/'.$message->thread_id);
        $receivedAt = ($message->received_at ?? $message->created_at ?? now())
            ->copy()
            ->timezone(config('app.display_timezone', 'Europe/Minsk'));
        $attachmentsLabel = $message->attachments_count > 0
            ? $message->attachments_count.' шт.'
            : 'нет';

        $lines = [
            '📧 <b>Новое входящее письмо</b>',
            '',
            '<b>От:</b> '.$escape($sender ?: 'Не определён'),
            '<b>Кому:</b> '.$escape($message->mailbox?->email ?: 'KOTLOV'),
            '<b>Тема:</b> '.$escape($message->subject ?: '(без темы)'),
            '<b>Вложения:</b> '.$escape($attachmentsLabel),
            '',
            '<b>Сообщение:</b>',
            $escape($preview !== '' ? $preview : 'Текст письма отсутствует'),
            '',
            '<a href="'.$escape($adminUrl).'">Открыть переписку в CRM</a>',
            '<b>Получено:</b> '.$receivedAt->format('d.m.Y H:i').' (Минск)',
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", $lines),
                [
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[
                            ['text' => '📨 Открыть письмо', 'url' => $adminUrl],
                        ]],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            );

            if ($result['ok'] ?? false) {
                Log::info('Telegram incoming mail notification sent.', [
                    'crm_mail_message_id' => $message->id,
                    'crm_mail_thread_id' => $message->thread_id,
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                ]);

                return true;
            }

            Log::error('Telegram incoming mail notification failed.', [
                'crm_mail_message_id' => $message->id,
                'crm_mail_thread_id' => $message->thread_id,
                'response' => $result,
            ]);
        } catch (Throwable $exception) {
            Log::error('Telegram incoming mail notification exception: '.$exception->getMessage(), [
                'crm_mail_message_id' => $message->id,
                'crm_mail_thread_id' => $message->thread_id,
            ]);
        }

        return false;
    }
}
