<?php

namespace App\Http\Controllers;

use App\Models\LeadChannel;
use App\Services\LeadInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramLeadWebhookController extends Controller
{
    public function __invoke(Request $request, LeadInbox $inbox): JsonResponse
    {
        $channel = LeadChannel::settings('telegram');
        $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless($channel->acceptsMessages() && hash_equals($channel->webhook_secret, $receivedSecret), 404);

        $message = $request->input('message', $request->input('edited_message'));
        if (! is_array($message)
            || data_get($message, 'chat.type') !== 'private'
            || (bool) data_get($message, 'from.is_bot', false)) {
            return response()->json(['ok' => true, 'processed' => false]);
        }

        $chatId = trim((string) data_get($message, 'chat.id'));
        $messageId = trim((string) ($message['message_id'] ?? ''));
        abort_unless(ctype_digit($chatId) && ctype_digit($messageId), 422);

        $result = $inbox->receive(
            $channel,
            $chatId,
            $chatId.':'.$messageId,
            $this->messageText($message),
            $this->senderName($message),
            trim((string) data_get($message, 'contact.phone_number')) ?: null,
            $this->telegramReference($message, $chatId),
        );

        return response()->json([
            'ok' => true,
            'processed' => $result['changed'],
            'duplicate' => ! $result['changed'],
            'request_id' => $result['request']->id,
        ]);
    }

    /** @param array<string, mixed> $message */
    private function senderName(array $message): string
    {
        $name = trim(implode(' ', array_filter([
            data_get($message, 'from.first_name'),
            data_get($message, 'from.last_name'),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        return $name !== '' ? $name : 'Контакт Telegram';
    }

    /** @param array<string, mixed> $message */
    private function messageText(array $message): string
    {
        foreach (['text', 'caption'] as $field) {
            $text = trim((string) ($message[$field] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return match (true) {
            isset($message['contact']) => '[Клиент отправил контакт]',
            isset($message['photo']) => '[Фотография]',
            isset($message['document']) => '[Документ: '.((string) data_get($message, 'document.file_name') ?: 'без названия').']',
            isset($message['voice']) => '[Голосовое сообщение]',
            isset($message['video']) => '[Видео]',
            isset($message['location']) => '[Геолокация]',
            isset($message['sticker']) => '[Стикер]',
            default => '[Сообщение без текста]',
        };
    }

    /** @param array<string, mixed> $message */
    private function telegramReference(array $message, string $chatId): string
    {
        $username = trim((string) data_get($message, 'from.username'));
        $userId = trim((string) data_get($message, 'from.id'));

        return implode(' · ', array_filter([
            $username !== '' ? 'Telegram @'.$username : null,
            $userId !== '' ? 'user ID '.$userId : null,
            'chat ID '.$chatId,
        ]));
    }
}
