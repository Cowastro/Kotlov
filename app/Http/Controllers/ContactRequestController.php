<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Services\TelegramApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['required', 'string', 'max:50'],
            'email'   => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $contactRequest = ContactRequest::create([
            'name'    => $validated['name'],
            'phone'   => $validated['phone'],
            'email'   => $validated['email'] ?? null,
            'message' => $validated['message'] ?? '',
            'status'  => 'new',
        ]);

        $this->sendTelegramNotification($contactRequest);

        return back()->with('success', 'Спасибо! Ваша заявка отправлена. Мы скоро свяжемся с вами.');
    }

    private function sendTelegramNotification(ContactRequest $contactRequest): void
    {
        $chatId = config('services.telegram.orders_chat_id');

        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram consultation notification skipped: credentials are not configured.', [
                'contact_request_id' => $contactRequest->id,
            ]);

            return;
        }

        $escape = fn (?string $value): string => str_replace(
            ['_', '*', '`', '['],
            ['\_', '\*', '\`', '\['],
            (string) $value
        );

        $lines = [
            '🔥 *Новая заявка на консультацию*',
            '',
            '*Имя:* ' . $escape($contactRequest->name),
            '*Телефон:* ' . $escape($contactRequest->phone),
            $contactRequest->email ? '*Email:* ' . $escape($contactRequest->email) : null,
            '*Сообщение:* ' . $escape($contactRequest->message ?: 'Не указано'),
            '',
            '*Дата:* ' . $contactRequest->created_at->format('d.m.Y H:i'),
        ];

        try {
            $result = (new TelegramApi())->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line) => $line !== null))
            );

            if (! ($result['ok'] ?? false)) {
                Log::error('Telegram consultation notification failed.', [
                    'contact_request_id' => $contactRequest->id,
                    'response' => $result,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram consultation notification exception: ' . $e->getMessage(), [
                'contact_request_id' => $contactRequest->id,
            ]);
        }
    }
}
