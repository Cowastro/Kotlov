<?php

namespace Tests\Feature;

use App\Jobs\SendMissedCallToTelegram;
use App\Models\TelephonyCall;
use App\Models\User;
use App\Services\TelegramApi;
use App\Services\Telephony\MissedCallTelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class MissedCallTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-telephony-secret-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telephony.webhook_secret', $this->secret);
        config()->set('services.telegram.bot_token', 'test-token');
        config()->set('services.telegram.telephony_chat_id', '-100123456789');
    }

    public function test_finish_event_queues_notification_for_unanswered_inbound_call(): void
    {
        Queue::fake();

        $this->postJson(route('telephony.a1.webhook', $this->secret), $this->startPayload())->assertOk();
        Queue::assertNothingPushed();

        $this->postJson(route('telephony.a1.webhook', $this->secret), [
            ...$this->startPayload(),
            'notificationType' => 'FINISH',
            'duration' => 26,
            'crmCallFinishedStatus' => 'CANCELLED',
            'isCallFinished' => true,
        ])->assertOk();

        $call = TelephonyCall::query()->firstOrFail();
        Queue::assertPushed(
            SendMissedCallToTelegram::class,
            fn (SendMissedCallToTelegram $job): bool => $job->telephonyCallId === $call->id,
        );
    }

    public function test_notifier_sends_actionable_message_and_marks_call_only_once(): void
    {
        $manager = User::factory()->create(['name' => 'Анна Менеджер']);
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'missed-call-telegram-test',
            'direction' => 'inbound',
            'customer_phone' => '375255436224',
            'assigned_user_id' => $manager->id,
            'status' => 'cancelled',
            'handling_status' => 'callback_required',
            'started_at' => '2026-10-17 08:01:00',
            'finished_at' => '2026-10-17 08:01:26',
            'duration' => 26,
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, string $message, array $extra): bool {
                return $chatId === '-100123456789'
                    && str_contains($message, 'Пропущенный входящий звонок')
                    && str_contains($message, '+375255436224')
                    && str_contains($message, '17.10.2026 11:01:00')
                    && str_contains($message, '00:26')
                    && str_contains($message, 'Пропущен')
                    && ! str_contains($message, 'Отменён клиентом')
                    && str_contains($message, 'Анна Менеджер')
                    && str_contains($message, 'Нужно перезвонить')
                    && ($extra['parse_mode'] ?? null) === 'HTML'
                    && str_contains((string) ($extra['reply_markup'] ?? ''), 'Открыть звонок в CRM');
            })
            ->andReturn(['ok' => true, 'result' => ['message_id' => 321]]);

        $notifier = new MissedCallTelegramNotifier($telegram);

        $this->assertTrue($notifier->send($call));
        $this->assertTrue($notifier->send($call->fresh()));
        $this->assertSame(321, $call->fresh()->telegram_message_id);
        $this->assertNotNull($call->fresh()->telegram_notified_at);
        $this->assertNull($call->fresh()->telegram_notification_claimed_at);
    }

    public function test_two_a1_legs_for_one_call_send_only_one_final_missed_notification(): void
    {
        $first = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'duplicate-leg-cancelled',
            'direction' => 'inbound',
            'customer_phone' => '375255436224',
            'status' => 'missed',
            'handling_status' => 'callback_required',
            'started_at' => '2026-10-17 10:04:17',
            'finished_at' => '2026-10-17 10:04:17',
            'duration' => 0,
        ]);
        $final = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'duplicate-leg-not-answered',
            'direction' => 'inbound',
            'customer_phone' => '375255436224',
            'status' => 'missed',
            'handling_status' => 'callback_required',
            'started_at' => '2026-10-17 10:04:26',
            'finished_at' => '2026-10-17 10:04:26',
            'duration' => 0,
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()
            ->withArgs(fn ($chatId, string $message): bool => $chatId === '-100123456789'
                && str_contains($message, 'Пропущен')
                && ! str_contains($message, 'Отменён клиентом'))
            ->andReturn(['ok' => true, 'result' => ['message_id' => 987]]);
        $notifier = new MissedCallTelegramNotifier($telegram);

        $this->assertTrue($notifier->send($first));
        $this->assertNull($first->fresh()->telegram_message_id);
        $this->assertTrue($notifier->send($final));
        $this->assertSame(987, $final->fresh()->telegram_message_id);
    }

    public function test_failed_send_releases_claim_so_queue_retry_can_send_again(): void
    {
        $call = TelephonyCall::query()->create([
            'provider' => 'a1',
            'external_uuid' => 'missed-call-retry-test',
            'direction' => 'inbound',
            'customer_phone' => '375295523310',
            'status' => 'missed',
            'handling_status' => 'callback_required',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'duration' => 30,
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->twice()
            ->andReturn(
                ['ok' => false, 'description' => 'temporary failure'],
                ['ok' => true, 'result' => ['message_id' => 654]],
            );
        $notifier = new MissedCallTelegramNotifier($telegram);

        $this->assertFalse($notifier->send($call));
        $this->assertNull($call->fresh()->telegram_notification_claimed_at);
        $this->assertTrue($notifier->send($call->fresh()));
        $this->assertSame(654, $call->fresh()->telegram_message_id);
    }

    /** @return array<string, mixed> */
    private function startPayload(): array
    {
        return [
            'notificationType' => 'START',
            'companyId' => 732,
            'crmCallType' => 'INBOUND',
            'numberA' => '375255436224',
            'numberB' => '375291177593',
            'lineNumber' => '375291179623',
            'dialType' => 'SINGLE',
            'date' => '2026-10-17T11:01:00',
            'uuid' => 'missed-call-webhook-test',
        ];
    }
}
