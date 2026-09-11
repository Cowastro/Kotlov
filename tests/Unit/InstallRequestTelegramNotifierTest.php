<?php

namespace Tests\Unit;

use App\Models\InstallRequest;
use App\Services\InstallRequestTelegramNotifier;
use App\Services\TelegramApi;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallRequestTelegramNotifierTest extends TestCase
{
    public static function sourceProvider(): array
    {
        return [
            ['heat_pump_installation', 'Монтаж тепловых насосов'],
            ['fireplace_installation', 'Монтаж каминов и печей-каминов'],
            ['product_engineering_calculation', 'Инженерный расчёт из карточки товара'],
            ['pellet_burner_promo', 'Акция KOTLOV XO Ceramic PRO'],
            ['pellet_burner_evo_promo', 'Акция KOTLOV XO EVO'],
            ['pellet_burner_hotta_promo', 'Распродажа HOTTA Ceramik'],
        ];
    }

    #[DataProvider('sourceProvider')]
    public function test_it_sends_each_new_form_source_to_telegram(string $source, string $label): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.orders_chat_id' => '-100123456789',
        ]);

        $request = new InstallRequest([
            'customer_name' => 'Тест KOTLOV',
            'customer_phone' => '+375 29 000-00-00',
            'city' => 'Минск',
            'specialization' => 'engineering',
            'description' => 'Проверка уведомления',
            'source' => $source,
        ]);
        $request->id = 123;
        $request->created_at = Carbon::parse('2026-09-11 14:00:00');

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, string $message) use ($label): bool {
                return $chatId === '-100123456789'
                    && str_contains($message, $label)
                    && str_contains($message, 'Тест KOTLOV')
                    && str_contains($message, '/admin/install-requests/123');
            })
            ->andReturn(['ok' => true, 'result' => ['message_id' => 456]]);

        $sent = (new InstallRequestTelegramNotifier($telegram))->send($request);

        $this->assertTrue($sent);
    }
}
