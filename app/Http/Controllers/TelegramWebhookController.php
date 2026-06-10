<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\TelegramApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $update = $request->all();
        $chatId = config('services.telegram.orders_chat_id');

        // Обрабатываем только callback_query
        if (!isset($update['callback_query'])) {
            return response()->json(['ok' => true]);
        }

        $tg         = new TelegramApi();
        $callback   = $update['callback_query'];
        $callbackId = $callback['id'];
        $data       = $callback['data'] ?? '';
        $from       = $callback['from'];
        $messageId  = $callback['message']['message_id'] ?? null;
        $msgChatId  = $callback['message']['chat']['id'] ?? null;

        // Формируем имя пользователя
        $name     = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        $username = isset($from['username']) ? '@' . $from['username'] : $name;

        if (!str_starts_with($data, 'take_order:')) {
            return response()->json(['ok' => true]);
        }

        $orderId = (int) str_replace('take_order:', '', $data);
        $order   = Order::with('manager')->find($orderId);

        if (!$order) {
            $tg->answerCallbackQuery($callbackId, '❌ Заказ не найден.', true);
            return response()->json(['ok' => true]);
        }

        // Уже назначен — из CRM или через Telegram
        if ($order->manager_id || $order->assigned_to) {
            $who = $order->manager?->name ?? $order->assigned_to ?? 'менеджер';
            $tg->answerCallbackQuery($callbackId, "⚠️ Заказ уже взят: {$who}", true);
            return response()->json(['ok' => true]);
        }

        // Ищем CRM-пользователя по Telegram username (без @)
        $telegramLogin = $from['username'] ?? null;
        $crmUser = $telegramLogin
            ? User::where('telegram_username', $telegramLogin)->first()
            : null;

        // Назначаем ответственного, меняем статус new → processing
        $order->updateQuietly([
            'assigned_to' => $username,
            'manager_id'  => $crmUser?->id,
            'status'      => $order->status === 'new' ? 'processing' : $order->status,
        ]);

        // Отвечаем на callback
        $tg->answerCallbackQuery($callbackId, "✅ Вы взяли заказ {$order->number}");

        // Убираем кнопку + reply-сообщение кто взял
        if ($messageId && $msgChatId) {
            $tg->editMessageReplyMarkup($msgChatId, $messageId);
            $tg->sendReply($msgChatId, $messageId, "✅ Взял: {$username}");
        }

        Log::info("Заказ {$order->number} взят: {$username}");

        return response()->json(['ok' => true]);
    }
}
