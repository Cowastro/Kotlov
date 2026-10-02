<?php

namespace Tests\Unit;

use App\Models\InstallerApplication;
use App\Services\InstallerApplicationTelegramNotifier;
use App\Services\TelegramApi;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class InstallerApplicationTelegramNotifierTest extends TestCase
{
    public function test_it_sends_all_application_details_to_configured_channel(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.installer_applications_chat_id' => '-100123456789',
        ]);

        $application = new InstallerApplication([
            'contact_name' => 'Иван Петров',
            'phone' => '+375 29 123-45-67',
            'email' => 'ivan@example.com',
            'city' => 'Борисов',
            'company_name' => 'ИП Тепло',
            'experience_years' => 15,
            'specializations' => ['kotly', 'dymohody'],
            'message' => 'Монтаж под ключ',
            'source' => 'outreach-messenger',
        ]);
        $application->id = 321;
        $application->created_at = Carbon::parse('2026-10-02 18:00:00');

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, string $message, array $extra): bool {
                return $chatId === '-100123456789'
                    && $extra === ['parse_mode' => 'HTML']
                    && str_contains($message, 'Новая заявка монтажника')
                    && str_contains($message, 'Рассылка Telegram / Viber')
                    && str_contains($message, 'Иван Петров')
                    && str_contains($message, '+375 29 123-45-67')
                    && str_contains($message, 'ivan@example.com')
                    && str_contains($message, 'Борисов')
                    && str_contains($message, 'ИП Тепло')
                    && str_contains($message, '15 лет')
                    && str_contains($message, 'Монтаж котлов, Дымоходы')
                    && str_contains($message, 'Монтаж под ключ')
                    && str_contains($message, 'Дата:</b> 02.10.2026 21:00 (Минск)')
                    && str_contains($message, '/admin/installer-applications/321');
            })
            ->andReturn(['ok' => false, 'description' => 'test response']);

        $this->assertFalse((new InstallerApplicationTelegramNotifier($telegram))->send($application));
    }

    public function test_it_skips_sending_when_credentials_are_missing(): void
    {
        config([
            'services.telegram.bot_token' => null,
            'services.telegram.installer_applications_chat_id' => null,
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldNotReceive('sendMessage');

        $this->assertFalse((new InstallerApplicationTelegramNotifier($telegram))->send(
            new InstallerApplication(['contact_name' => 'Тест', 'phone' => '+375']),
        ));
    }
}
