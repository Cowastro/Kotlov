<?php

namespace App\Services;

use App\Filament\Resources\B2bClients\B2bClientResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NewRegistrationTelegramNotifier
{
    public function __construct(private readonly TelegramApi $telegram) {}

    public function send(User $user): bool
    {
        $chatId = config('services.telegram.registrations_chat_id');
        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram registration notification skipped: credentials are not configured.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        $user->loadMissing('b2bClient');
        $client = $user->b2bClient;
        $isLegal = $client !== null;
        $escape = fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $adminUrl = $client
            ? B2bClientResource::getUrl('edit', ['record' => $client])
            : UserResource::getUrl('edit', ['record' => $user]);
        $registeredAt = ($user->created_at ?? now())
            ->copy()
            ->timezone(config('app.display_timezone', 'Europe/Minsk'));

        $lines = [
            '👤 <b>Новая регистрация на KOTLOV.BY</b>',
            '',
            '<b>Тип:</b> '.($isLegal ? 'Юридическое лицо / B2B' : 'Физическое лицо'),
            $client?->company_name ? '<b>Компания:</b> '.$escape($client->company_name) : null,
            $client?->unp ? '<b>УНП:</b> '.$escape($client->unp) : null,
            '<b>Контакт:</b> '.$escape($client?->contact_name ?: $user->name),
            '<b>Телефон:</b> '.$escape($client?->contact_phone ?: $user->phone ?: 'Не указан'),
            '<b>Email:</b> '.$escape($client?->contact_email ?: $user->email),
            '<b>Проверка:</b> email подтверждён ✅',
            '',
            '<a href="'.$escape($adminUrl).'">Открыть карточку в админке</a>',
            '<b>Дата:</b> '.$registeredAt->format('d.m.Y H:i').' (Минск)',
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line) => $line !== null)),
                ['parse_mode' => 'HTML'],
            );

            if ($result['ok'] ?? false) {
                Log::info('Telegram registration notification sent.', [
                    'user_id' => $user->id,
                    'b2b_client_id' => $client?->id,
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                ]);

                return true;
            }

            Log::error('Telegram registration notification failed.', [
                'user_id' => $user->id,
                'response' => $result,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Telegram registration notification exception: '.$exception->getMessage(), [
                'user_id' => $user->id,
            ]);
        }

        return false;
    }
}
