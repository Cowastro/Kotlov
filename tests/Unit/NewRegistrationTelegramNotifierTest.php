<?php

namespace Tests\Unit;

use App\Models\B2bClient;
use App\Models\User;
use App\Services\NewRegistrationTelegramNotifier;
use App\Services\TelegramApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NewRegistrationTelegramNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_b2b_registration_is_sent_with_contact_details_and_admin_link(): void
    {
        config()->set([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.registrations_chat_id' => '-100123456789',
        ]);
        $user = User::factory()->create([
            'name' => 'Иван Петров',
            'email' => 'office@example.by',
            'phone' => '+375291112233',
            'email_verified_at' => now(),
            'client_type' => 'wholesale',
        ]);
        B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Теплосервис»',
            'unp' => '190123456',
            'contact_name' => 'Иван Петров',
            'contact_email' => 'office@example.by',
            'contact_phone' => '+375291112233',
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()->withArgs(
            function ($chatId, $message, $extra): bool {
                return $chatId === '-100123456789'
                    && str_contains($message, 'Новая регистрация на KOTLOV.BY')
                    && str_contains($message, 'Юридическое лицо / B2B')
                    && str_contains($message, 'ООО «Теплосервис»')
                    && str_contains($message, '190123456')
                    && str_contains($message, '+375291112233')
                    && str_contains($message, 'office@example.by')
                    && str_contains($message, '/admin/b2b-clients/')
                    && ($extra['parse_mode'] ?? null) === 'HTML';
            },
        )->andReturn(['ok' => true, 'result' => ['message_id' => 321]]);

        $this->assertTrue((new NewRegistrationTelegramNotifier($telegram))->send($user));
    }

    public function test_notification_is_skipped_without_telegram_credentials(): void
    {
        config()->set([
            'services.telegram.bot_token' => null,
            'services.telegram.registrations_chat_id' => null,
        ]);
        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldNotReceive('sendMessage');

        $this->assertFalse((new NewRegistrationTelegramNotifier($telegram))->send(User::factory()->create()));
    }

    public function test_verified_individual_registration_links_to_user_card(): void
    {
        config()->set([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.registrations_chat_id' => '-100123456789',
        ]);
        $user = User::factory()->create([
            'name' => 'Анна Иванова',
            'email' => 'anna@example.by',
            'phone' => '+375292223344',
            'email_verified_at' => now(),
            'client_type' => 'retail',
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()->withArgs(
            function ($chatId, $message, $extra): bool {
                return $chatId === '-100123456789'
                    && str_contains($message, 'Физическое лицо')
                    && str_contains($message, 'Анна Иванова')
                    && str_contains($message, '+375292223344')
                    && str_contains($message, '/admin/users/')
                    && ($extra['parse_mode'] ?? null) === 'HTML';
            },
        )->andReturn(['ok' => true, 'result' => ['message_id' => 322]]);

        $this->assertTrue((new NewRegistrationTelegramNotifier($telegram))->send($user));
    }

    public function test_invited_company_employee_is_identified_with_company_and_role(): void
    {
        config()->set([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.registrations_chat_id' => '-100123456789',
        ]);
        $owner = User::factory()->create(['client_type' => 'wholesale']);
        $client = B2bClient::query()->create([
            'user_id' => $owner->id,
            'status' => 'approved',
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «Командный кабинет»',
            'unp' => '190555444',
            'contact_name' => $owner->name,
            'contact_email' => $owner->email,
        ]);
        $employee = User::factory()->create([
            'name' => 'Мария Бухгалтер',
            'email' => 'accountant@example.by',
            'phone' => '+375293334455',
            'email_verified_at' => now(),
            'client_type' => 'retail',
        ]);
        $client->members()->create([
            'user_id' => $employee->id,
            'role' => 'accountant',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $sentMessage = '';
        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()->withArgs(
            function ($chatId, $message, $extra) use (&$sentMessage): bool {
                $sentMessage = $message;

                return $chatId === '-100123456789' && ($extra['parse_mode'] ?? null) === 'HTML';
            },
        )->andReturn(['ok' => true, 'result' => ['message_id' => 323]]);

        $this->assertTrue((new NewRegistrationTelegramNotifier($telegram))->send($employee));
        $this->assertStringContainsString('Сотрудник B2B-компании · Бухгалтер', $sentMessage);
        $this->assertStringContainsString('ООО «Командный кабинет»', $sentMessage);
        $this->assertStringContainsString('accountant@example.by', $sentMessage);
        $this->assertStringContainsString('/admin/b2b-clients/', $sentMessage);
    }
}
