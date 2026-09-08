<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Models\User;
use App\Services\TelegramApi;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:50', new \App\Rules\PhoneNotSpam()],
            'email'        => ['nullable', 'email', 'max:255'],
            'message'      => ['nullable', 'string', 'max:2000'],
            'product_id'   => ['nullable', 'integer'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_url'  => ['nullable', 'string', 'max:1000'],
            'city'         => ['nullable', 'string', 'max:255'],
            'source'       => ['nullable', 'string', 'max:100'],
        ]);

        $contactRequest = ContactRequest::create([
            'name'         => $validated['name'],
            'phone'        => $validated['phone'],
            'email'        => $validated['email'] ?? null,
            'message'      => $validated['message'] ?? '',
            'product_id'   => $validated['product_id'] ?? null,
            'product_name' => $validated['product_name'] ?? null,
            'product_url'  => $validated['product_url'] ?? null,
            'city'         => $validated['city'] ?? null,
            'source'       => $validated['source'] ?? null,
            'status'       => 'new',
        ]);

        $this->sendAdminNotification($contactRequest);
        $this->sendTelegramNotification($contactRequest);

        return back()->with('success', 'Спасибо! Ваша заявка отправлена. Мы скоро свяжемся с вами.');
    }

    private function sendAdminNotification(ContactRequest $contactRequest): void
    {
        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        $title = 'Новая заявка на консультацию';
        $body = implode(' · ', array_filter([
            $contactRequest->name,
            $contactRequest->phone,
            $contactRequest->product_name,
            $contactRequest->city,
        ]));

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('warning')
            ->sendToDatabase($admins);
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

        $sourceLabels = [
            'product_page' => 'Карточка товара',
            'consultation_form' => 'Форма консультации',
        ];

        $source = $contactRequest->source
            ? ($sourceLabels[$contactRequest->source] ?? $contactRequest->source)
            : null;

        $lines = [
            '🔥 *Новая заявка на консультацию*',
            '',
            $source ? '*Источник:* ' . $escape($source) : null,
            $contactRequest->city ? '*Город:* ' . $escape($contactRequest->city) : null,
            $contactRequest->product_name ? '' : null,
            $contactRequest->product_name ? "*Товар:*\n" . $escape($contactRequest->product_name) : null,
            $contactRequest->product_url ? '' : null,
            $contactRequest->product_url ? "*Страница:*\n" . $escape($contactRequest->product_url) : null,
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
