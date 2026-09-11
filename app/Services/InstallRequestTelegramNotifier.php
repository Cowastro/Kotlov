<?php

namespace App\Services;

use App\Models\InstallRequest;
use Illuminate\Support\Facades\Log;

class InstallRequestTelegramNotifier
{
    public function __construct(private TelegramApi $telegram) {}

    public function send(InstallRequest $installRequest): bool
    {
        $chatId = config('services.telegram.orders_chat_id');

        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram install request notification skipped: credentials are not configured.', [
                'install_request_id' => $installRequest->id,
                'source' => $installRequest->source,
            ]);

            return false;
        }

        $installRequest->loadMissing('product');

        $sourceLabels = [
            'heat_pump_installation' => 'Монтаж тепловых насосов',
            'fireplace_installation' => 'Монтаж каминов и печей-каминов',
            'product_engineering_calculation' => 'Инженерный расчёт из карточки товара',
            'pellet_burner_promo' => 'Акция KOTLOV XO Ceramic PRO',
            'pellet_burner_evo_promo' => 'Акция KOTLOV XO EVO',
            'pellet_burner_hotta_promo' => 'Распродажа HOTTA Ceramik',
            'installer_profile' => 'Карточка монтажника',
            'installers_page' => 'Общая форма монтажа',
        ];

        $specializationLabels = [
            'heating' => 'Монтаж котла',
            'heatpump' => 'Монтаж теплового насоса',
            'fireplace' => 'Монтаж камина',
            'chimney' => 'Монтаж дымохода',
            'sauna' => 'Монтаж банной печи',
            'service' => 'Сервис',
            'commissioning' => 'Пусконаладка',
            'engineering' => 'Инженерный подбор',
            'other' => 'Другое',
        ];

        $escape = fn (?string $value): string => str_replace(
            ['_', '*', '`', '['],
            ['\_', '\*', '\`', '\['],
            (string) $value,
        );

        $source = $sourceLabels[$installRequest->source]
            ?? $installRequest->source
            ?? 'Общая форма монтажа';
        $specialization = $specializationLabels[$installRequest->specialization]
            ?? $installRequest->specialization
            ?? 'Не указано';
        $product = $installRequest->product;
        $adminUrl = url('/admin/install-requests/'.$installRequest->id);

        $lines = [
            '🛠 *Новая заявка на монтаж / расчёт*',
            '',
            '*Источник:* '.$escape($source),
            '*Направление:* '.$escape($specialization),
            $product ? '*Товар:* '.$escape($product->name) : null,
            '',
            '*Имя:* '.$escape($installRequest->customer_name),
            '*Телефон:* '.$escape($installRequest->customer_phone),
            $installRequest->customer_email ? '*Email:* '.$escape($installRequest->customer_email) : null,
            $installRequest->city ? '*Город:* '.$escape($installRequest->city) : null,
            $installRequest->region ? '*Регион:* '.$escape($installRequest->region) : null,
            $installRequest->description ? "*Задача:*\n".$escape($installRequest->description) : '*Задача:* Не указана',
            '',
            '*Заявка в CRM:* '.$adminUrl,
            '*Дата:* '.($installRequest->created_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i')),
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line) => $line !== null)),
            );

            if ($result['ok'] ?? false) {
                Log::info('Telegram install request notification sent.', [
                    'install_request_id' => $installRequest->id,
                    'source' => $installRequest->source,
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                ]);

                return true;
            }

            Log::error('Telegram install request notification failed.', [
                'install_request_id' => $installRequest->id,
                'source' => $installRequest->source,
                'response' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram install request notification exception: '.$e->getMessage(), [
                'install_request_id' => $installRequest->id,
                'source' => $installRequest->source,
            ]);
        }

        return false;
    }
}
