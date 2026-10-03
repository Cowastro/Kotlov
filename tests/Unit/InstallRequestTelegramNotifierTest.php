<?php

namespace Tests\Unit;

use App\Models\InstallerProfile;
use App\Models\InstallRequest;
use App\Models\Product;
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
            ['heat_pump_catalog', 'Каталог тепловых насосов'],
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
                    && str_contains($message, '*Дата:* 11.09.2026 17:00 (Минск)')
                    && str_contains($message, '/admin/install-requests/123');
            })
            ->andReturn(['ok' => true, 'result' => ['message_id' => 456]]);

        $sent = (new InstallRequestTelegramNotifier($telegram))->send($request);

        $this->assertTrue($sent);
    }

    public function test_it_identifies_the_selected_installer_profile_in_telegram(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.orders_chat_id' => '-100123456789',
        ]);

        $installer = new InstallerProfile([
            'company_name' => 'ООО «Отопление плюс»',
            'contact_name' => 'Алексей Максимов',
        ]);
        $installer->id = 2;

        $request = new InstallRequest([
            'customer_name' => 'Тест KOTLOV',
            'customer_phone' => '+375 29 000-00-00',
            'specialization' => 'heatpump',
            'source' => 'installer_profile',
        ]);
        $request->id = 124;
        $request->created_at = Carbon::parse('2026-10-01 16:00:00');
        $request->setRelation('installerProfile', $installer);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, string $message): bool => $chatId === '-100123456789'
                && str_contains($message, '*Монтажник:* ООО «Отопление плюс» — Алексей Максимов')
                && str_contains($message, '*Источник:* Карточка монтажника')
            )
            ->andReturn(['ok' => true, 'result' => ['message_id' => 457]]);

        $this->assertTrue((new InstallRequestTelegramNotifier($telegram))->send($request));
    }

    public function test_heat_pump_notification_contains_structured_project_details(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.orders_chat_id' => '-100123456789',
        ]);

        $request = new InstallRequest([
            'customer_name' => 'Тест теплового насоса',
            'customer_phone' => '+375 29 000-00-00',
            'specialization' => 'heatpump',
            'source' => 'heat_pump_installation',
            'project_details' => [
                'property_area' => 180,
                'heating_system' => 'mixed',
                'flow_temperature' => '46_to_60',
                'power_supply' => '380',
                'needs_hot_water' => true,
            ],
        ]);
        $request->id = 125;
        $request->created_at = Carbon::parse('2026-10-03 10:00:00');

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, string $message): bool => $chatId === '-100123456789'
                && str_contains($message, '*Площадь:* 180 м²')
                && str_contains($message, '*Отопление:* Тёплый пол + радиаторы')
                && str_contains($message, '*Подача:* 46–60 °C')
                && str_contains($message, '*Электропитание:* 380 В')
                && str_contains($message, '*ГВС:* нужно')
            )
            ->andReturn(['ok' => true, 'result' => ['message_id' => 458]]);

        $this->assertTrue((new InstallRequestTelegramNotifier($telegram))->send($request));
    }

    public function test_catalog_heat_pump_notification_contains_selected_model(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.orders_chat_id' => '-100123456789',
        ]);

        $product = new Product(['name' => 'Тепловой насос KOTLOV GE 12 кВт R290']);
        $product->id = 77;

        $request = new InstallRequest([
            'customer_name' => 'Тест каталога',
            'customer_phone' => '+375 29 000-00-00',
            'specialization' => 'heatpump',
            'source' => 'heat_pump_catalog',
            'product_id' => 77,
        ]);
        $request->id = 126;
        $request->created_at = Carbon::parse('2026-10-03 10:00:00');
        $request->setRelation('product', $product);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($chatId, string $message): bool => $chatId === '-100123456789'
                && str_contains($message, '*Источник:* Каталог тепловых насосов')
                && str_contains($message, '*Товар:* Тепловой насос KOTLOV GE 12 кВт R290')
            )
            ->andReturn(['ok' => true, 'result' => ['message_id' => 459]]);

        $this->assertTrue((new InstallRequestTelegramNotifier($telegram))->send($request));
    }
}
