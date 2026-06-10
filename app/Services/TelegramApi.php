<?php

namespace App\Services;

/**
 * Обёртка над Telegram Bot API через PHP curl.
 * Используем curl напрямую — Guzzle зависает на этом хостинге.
 */
class TelegramApi
{
    private string $token;
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->token   = config('services.telegram.bot_token', '');
        $this->timeout = $timeout;
    }

    public function sendMessage(string|int $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ], $extra));
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): array
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert,
        ]);
    }

    public function editMessageReplyMarkup(string|int $chatId, int $messageId, array $replyMarkup = []): array
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'reply_markup' => json_encode($replyMarkup ?: ['inline_keyboard' => []]),
        ]);
    }

    public function sendReply(string|int $chatId, int $replyToMessageId, string $text): array
    {
        return $this->call('sendMessage', [
            'chat_id'             => $chatId,
            'reply_to_message_id' => $replyToMessageId,
            'text'                => $text,
        ]);
    }

    private function call(string $method, array $params): array
    {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            \Illuminate\Support\Facades\Log::error("Telegram API curl error [{$method}]: {$error}");
            return ['ok' => false, 'error' => $error];
        }

        return json_decode($body, true) ?? ['ok' => false];
    }
}
