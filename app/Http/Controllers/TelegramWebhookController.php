<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {

        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.orders_chat_id');

        $update = $request->all();

        // Обрабатываем только callback_query
        if (!isset($update['callback_query'])) {
            return response()->json(['ok' => true]);
        }

        $callback   = $update['callback_query'];
        $callbackId = $callback['id'];
        $data       = $callback['data'] ?? '';
        $from       = $callback['from'];
        $messageId  = $callback['message']['message_id'] ?? null;
        $msgChatId  = $callback['message']['chat']['id'] ?? null;

        // Формируем имя пользователя
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        $username = isset($from['username']) ? '@' . $from['username'] : $name;

        if (!str_starts_with($data, 'take_order:')) {
            return response()->json(['ok' => true]);
        }

        $orderId = (int) str_replace('take_order:', '', $data);
        $order   = Order::find($orderId);

        if (!$order) {
            Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackId,
                'text'              => '❌ Заказ не найден.',
                'show_alert'        => true,
            ]);
            return response()->json(['ok' => true]);
        }

        // Уже кто-то взял
        // Уже назначен — из CRM или через Telegram
        if ($order->manager_id || $order->assigned_to) {
            $who = $order->manager?->name ?? $order->assigned_to ?? 'менеджер';
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                'callback_query_id' => $callbackId,
                'text'              => "⚠️ Заказ уже взят: {$who}",
                'show_alert'        => true,
            ]);
            return response()->json(['ok' => true]);
        }

        // Ищем CRM-пользователя по Telegram username (без @)
        $telegramLogin = $from['username'] ?? null;
        $crmUser = $telegramLogin
            ? User::where('telegram_username', $telegramLogin)->first()
            : null;

        // Назначаем ответственного, меняем статус на "В обработке" если был "Новый"
        $order->updateQuietly([
            'assigned_to' => $username,
            'manager_id'  => $crmUser?->id,  // null если маппинг не найден — не ломаем процесс
            'status'      => $order->status === 'new' ? 'processing' : $order->status,
        ]);

        // Отвечаем на callback
        Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text'              => "✅ Вы взяли заказ {$order->number}",
        ]);

        // Редактируем сообщение — убираем кнопку, добавляем исполнителя
        if ($messageId && $msgChatId) {
            $currentText = $callback['message']['text'] ?? '';
            $newText     = $currentText . "\n\n✅ *Взял:* {$username}";

            Http::post("https://api.telegram.org/bot{$token}/editMessageText", [
                'chat_id'      => $msgChatId,
                'message_id'   => $messageId,
                'text'         => $newText,
                'parse_mode'   => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ]);
        }

        Log::info("Заказ {$order->number} взят: {$username}");

        return response()->json(['ok' => true]);
    }
}
