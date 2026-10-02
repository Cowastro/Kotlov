<?php

namespace App\Services;

use App\Models\InstallerApplication;
use Illuminate\Support\Facades\Log;

class InstallerApplicationTelegramNotifier
{
    public function __construct(private TelegramApi $telegram) {}

    public function send(InstallerApplication $application): bool
    {
        $chatId = config('services.telegram.installer_applications_chat_id');

        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram installer application notification skipped: credentials are not configured.', [
                'installer_application_id' => $application->id,
                'source' => $application->source,
            ]);

            return false;
        }

        $escape = fn ($value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $source = InstallerApplication::$sourceLabels[$application->source]
            ?? $application->source
            ?? 'Не указан';
        $specializations = collect($application->specializations ?? [])
            ->map(fn ($item) => InstallerApplication::$specializationLabels[$item] ?? $item)
            ->join(', ');
        $adminUrl = $application->id
            ? url('/admin/installer-applications/'.$application->id)
            : null;
        $submittedAt = ($application->created_at ?? now())
            ->copy()
            ->timezone(config('app.display_timezone', 'Europe/Minsk'));

        $lines = [
            '🧰 <b>Новая заявка монтажника</b>',
            '',
            '<b>Источник:</b> '.$escape($source),
            '<b>Имя:</b> '.$escape($application->contact_name),
            '<b>Телефон:</b> '.$escape($application->phone),
            $application->email ? '<b>Email:</b> '.$escape($application->email) : null,
            $application->city ? '<b>Город:</b> '.$escape($application->city) : null,
            $application->company_name ? '<b>Компания:</b> '.$escape($application->company_name) : null,
            $application->experience_years !== null ? '<b>Опыт:</b> '.$escape($application->experience_years).' лет' : null,
            $specializations !== '' ? '<b>Специализации:</b> '.$escape($specializations) : null,
            $application->message ? "<b>О себе:</b>\n".$escape($application->message) : null,
            '',
            $adminUrl ? '<a href="'.$escape($adminUrl).'">Открыть заявку в админке</a>' : null,
            '<b>Дата:</b> '.$submittedAt->format('d.m.Y H:i').' (Минск)',
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line) => $line !== null)),
                ['parse_mode' => 'HTML'],
            );

            if ($result['ok'] ?? false) {
                if ($application->exists) {
                    $application->updateQuietly([
                        'telegram_message_id' => $result['result']['message_id'] ?? null,
                        'telegram_notified_at' => now(),
                    ]);
                }

                Log::info('Telegram installer application notification sent.', [
                    'installer_application_id' => $application->id,
                    'source' => $application->source,
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                ]);

                return true;
            }

            Log::error('Telegram installer application notification failed.', [
                'installer_application_id' => $application->id,
                'source' => $application->source,
                'response' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram installer application notification exception: '.$e->getMessage(), [
                'installer_application_id' => $application->id,
                'source' => $application->source,
            ]);
        }

        return false;
    }
}
