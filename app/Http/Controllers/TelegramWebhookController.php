<?php

namespace App\Http\Controllers;

use App\Models\ChimneyCalculationRequest;
use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\ChimneyCalculationRequestService;
use App\Services\TelegramApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly ChimneyCalculationRequestService $chimneyCalculations,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();
        // Обрабатываем только callback_query
        if (! isset($update['callback_query'])) {
            return response()->json(['ok' => true]);
        }

        $callback = $update['callback_query'];
        $callbackId = $callback['id'] ?? '';
        $data = $callback['data'] ?? '';
        $from = $callback['from'] ?? [];
        $messageId = $callback['message']['message_id'] ?? null;
        $msgChatId = $callback['message']['chat']['id'] ?? null;

        // Формируем имя пользователя
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));
        $username = isset($from['username']) ? '@'.$from['username'] : $name;

        if (str_starts_with($data, 'confirm_chimney:')) {
            return $this->confirmChimneyCalculation($data, $callbackId, $from, $messageId, $msgChatId);
        }

        if (str_starts_with($data, 'take_contact_request:')) {
            return $this->takeContactRequest($data, $callbackId, $from, $messageId, $msgChatId);
        }

        if (! str_starts_with($data, 'take_order:')) {
            return response()->json(['ok' => true]);
        }

        $orderId = (int) str_replace('take_order:', '', $data);
        $order = Order::with('manager')->find($orderId);

        if (! $order) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Заказ не найден.', true);

            return response()->json(['ok' => true]);
        }

        // Уже назначен — из CRM или через Telegram
        if ($order->manager_id || $order->assigned_to) {
            $who = $order->manager?->name ?? $order->assigned_to ?? 'менеджер';
            $this->telegram->answerCallbackQuery($callbackId, "⚠️ Заказ уже взят: {$who}", true);

            return response()->json(['ok' => true]);
        }

        // Ищем CRM-пользователя по Telegram username (без @)
        $telegramLogin = $from['username'] ?? null;
        $crmUser = $telegramLogin
            ? User::where('telegram_username', $telegramLogin)->first()
            : null;

        // Назначаем ответственного. Взять заказ — значит начать проверку,
        // а не подтвердить наличие и не передать его в исполнение.
        $attributes = [
            'assigned_to' => $username,
            'manager_id' => $crmUser?->id,
        ];
        if ($order->status === 'new') {
            $order->transitionTo('checking', 'Заказ принят через Telegram. Начата проверка наличия.', $attributes);
        } else {
            $order->forceFill($attributes)->save();
        }

        // Отвечаем на callback
        $this->telegram->answerCallbackQuery($callbackId, "✅ Вы взяли заказ {$order->number}");

        // Убираем кнопку + reply-сообщение кто взял
        if ($messageId && $msgChatId) {
            $this->telegram->editMessageReplyMarkup($msgChatId, $messageId);
            $this->telegram->sendReply($msgChatId, $messageId, "✅ Взял: {$username}");
        }

        Log::info("Заказ {$order->number} взят: {$username}");

        return response()->json(['ok' => true]);
    }

    private function takeContactRequest(
        string $data,
        string $callbackId,
        array $from,
        ?int $messageId,
        string|int|null $chatId,
    ): JsonResponse {
        $allowedChats = array_values(array_filter([
            (string) config('services.telegram.contact_requests_chat_id'),
            (string) config('services.telegram.orders_chat_id'),
        ]));
        if ($allowedChats !== [] && ! in_array((string) $chatId, $allowedChats, true)) {
            $this->telegram->answerCallbackQuery($callbackId, '⛔ Этот чат не может назначать заявки.', true);

            return response()->json(['ok' => true]);
        }

        $contactRequest = ContactRequest::with('responsibleUser')
            ->find((int) str_replace('take_contact_request:', '', $data));
        if (! $contactRequest) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Заявка не найдена.', true);

            return response()->json(['ok' => true]);
        }

        if ($contactRequest->responsible_user_id) {
            $who = $contactRequest->responsibleUser?->name ?: 'другой менеджер';
            $this->telegram->answerCallbackQuery($callbackId, "⚠️ Заявка уже назначена: {$who}", true);

            return response()->json(['ok' => true]);
        }

        if (! in_array($contactRequest->status, ['new', 'read'], true)) {
            $this->telegram->answerCallbackQuery($callbackId, '⚠️ Заявка уже находится в обработке.', true);

            return response()->json(['ok' => true]);
        }

        $telegramLogin = ltrim((string) ($from['username'] ?? ''), '@');
        $crmUser = User::query()
            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
            ->where('is_active', true)
            ->whereNotNull('telegram_username')
            ->get()
            ->first(fn (User $user): bool => mb_strtolower(ltrim((string) $user->telegram_username, '@')) === mb_strtolower($telegramLogin));

        if (! $crmUser) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Ваш Telegram не привязан к активному пользователю CRM.', true);

            return response()->json(['ok' => true]);
        }

        $claimed = ContactRequest::query()
            ->whereKey($contactRequest->getKey())
            ->whereNull('responsible_user_id')
            ->whereIn('status', ['new', 'read'])
            ->update([
                'responsible_user_id' => $crmUser->id,
                'status' => 'in_progress',
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            $current = $contactRequest->fresh('responsibleUser');
            $who = $current?->responsibleUser?->name ?: 'другой менеджер';
            $this->telegram->answerCallbackQuery($callbackId, "⚠️ Заявку уже взял: {$who}", true);

            return response()->json(['ok' => true]);
        }

        $telegramName = isset($from['username'])
            ? '@'.$from['username']
            : trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));
        $this->telegram->answerCallbackQuery($callbackId, "✅ Заявка назначена на {$crmUser->name}");

        if ($messageId && $chatId) {
            $this->telegram->editMessageReplyMarkup($chatId, $messageId);
            $this->telegram->sendReply($chatId, $messageId, '✅ Взял: '.($telegramName ?: $crmUser->name));
        }

        Log::info("Заявка {$contactRequest->id} взята из Telegram", [
            'responsible_user_id' => $crmUser->id,
        ]);

        return response()->json(['ok' => true]);
    }

    private function confirmChimneyCalculation(
        string $data,
        string $callbackId,
        array $from,
        ?int $messageId,
        string|int|null $chatId,
    ): JsonResponse {
        $allowedChats = array_values(array_filter([
            (string) config('services.telegram.contact_requests_chat_id'),
            (string) config('services.telegram.orders_chat_id'),
        ]));
        if ($allowedChats !== [] && ! in_array((string) $chatId, $allowedChats, true)) {
            $this->telegram->answerCallbackQuery($callbackId, '⛔ Этот чат не может подтверждать расчёты.', true);

            return response()->json(['ok' => true]);
        }

        $calculation = ChimneyCalculationRequest::find((int) str_replace('confirm_chimney:', '', $data));
        if (! $calculation) {
            $this->telegram->answerCallbackQuery($callbackId, '❌ Расчёт не найден.', true);

            return response()->json(['ok' => true]);
        }

        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));
        $username = isset($from['username']) ? '@'.$from['username'] : ($name ?: 'Менеджер Telegram');
        $crmUser = isset($from['username'])
            ? User::where('telegram_username', $from['username'])->first()
            : null;

        try {
            $result = $this->chimneyCalculations->confirm($calculation, $username, $crmUser);
        } catch (ValidationException $exception) {
            $this->telegram->answerCallbackQuery($callbackId, $exception->getMessage(), true);

            return response()->json(['ok' => true]);
        }

        $order = $result['order'];
        $text = $result['created']
            ? "✅ Заказ {$order->number} создан"
            : "ℹ️ Заказ {$order->number} уже был создан";
        $this->telegram->answerCallbackQuery($callbackId, $text, ! $result['created']);

        if ($messageId && $chatId) {
            $this->telegram->editMessageReplyMarkup($chatId, $messageId);
            $this->telegram->sendReply($chatId, $messageId, $text." · подтвердил {$username}");
        }

        Log::info("Расчёт {$calculation->number} подтверждён, заказ {$order->number}", [
            'confirmed_by' => $username,
            'created' => $result['created'],
        ]);

        return response()->json(['ok' => true]);
    }
}
