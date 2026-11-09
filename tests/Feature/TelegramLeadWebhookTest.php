<?php

namespace Tests\Feature;

use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test_telegram_leads_secret_123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.leads_webhook_secret' => self::SECRET,
            'services.telegram.bot_token' => null,
        ]);
    }

    public function test_private_customer_message_creates_a_direct_telegram_lead(): void
    {
        $response = $this->postJson('/integrations/telegram/leads', [
            'update_id' => 9001,
            'message' => $this->message([
                'message_id' => 101,
                'text' => 'Искандер 14 есть в наличии?',
            ]),
        ], $this->headers());

        $response->assertOk()->assertJson([
            'ok' => true,
            'processed' => true,
            'duplicate' => false,
        ]);

        $lead = ContactRequest::query()->sole();
        $this->assertSame('Сергей Петров', $lead->name);
        $this->assertNull($lead->phone);
        $this->assertSame('telegram_channel', $lead->source);
        $this->assertSame('telegram_bot', $lead->external_source);
        $this->assertSame('chat:700', $lead->external_id);
        $this->assertSame('700:101', $lead->last_external_event_id);
        $this->assertStringContainsString('Искандер 14 есть в наличии?', $lead->message);
        $this->assertStringContainsString('Telegram @sergey', $lead->admin_notes);
    }

    public function test_duplicate_is_ignored_and_later_message_updates_the_same_lead(): void
    {
        $first = ['message' => $this->message(['message_id' => 101, 'text' => 'Здравствуйте'])];

        $this->postJson('/integrations/telegram/leads', $first, $this->headers())->assertOk();
        $this->postJson('/integrations/telegram/leads', $first, $this->headers())
            ->assertOk()
            ->assertJson(['processed' => false, 'duplicate' => true]);

        ContactRequest::query()->firstOrFail()->update(['status' => 'done']);

        $this->postJson('/integrations/telegram/leads', [
            'message' => $this->message(['message_id' => 102, 'text' => 'Есть в наличии?']),
        ], $this->headers())->assertOk()->assertJson(['processed' => true]);

        $this->assertDatabaseCount('contact_requests', 1);
        $lead = ContactRequest::query()->sole();
        $this->assertSame('new', $lead->status);
        $this->assertStringContainsString('Здравствуйте', $lead->message);
        $this->assertStringContainsString('Есть в наличии?', $lead->message);
    }

    public function test_shared_contact_adds_phone_to_existing_lead(): void
    {
        $this->postJson('/integrations/telegram/leads', [
            'message' => $this->message(['message_id' => 101, 'text' => 'Здравствуйте']),
        ], $this->headers())->assertOk();

        $this->postJson('/integrations/telegram/leads', [
            'message' => $this->message([
                'message_id' => 102,
                'contact' => ['phone_number' => '+375291234567', 'first_name' => 'Сергей', 'user_id' => 501],
            ]),
        ], $this->headers())->assertOk();

        $this->assertSame('+375291234567', ContactRequest::query()->sole()->phone);
    }

    public function test_invalid_secret_and_non_private_messages_are_rejected_or_ignored(): void
    {
        $this->postJson('/integrations/telegram/leads', [
            'message' => $this->message(['message_id' => 101, 'text' => 'Без секрета']),
        ])->assertNotFound();

        $group = $this->message(['message_id' => 102, 'text' => 'Сообщение группы']);
        $group['chat']['type'] = 'group';

        $this->postJson('/integrations/telegram/leads', ['message' => $group], $this->headers())
            ->assertOk()
            ->assertJson(['processed' => false]);

        $this->assertDatabaseCount('contact_requests', 0);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function message(array $overrides): array
    {
        return array_replace_recursive([
            'message_id' => 1,
            'date' => 1793538360,
            'from' => [
                'id' => 501,
                'is_bot' => false,
                'first_name' => 'Сергей',
                'last_name' => 'Петров',
                'username' => 'sergey',
            ],
            'chat' => ['id' => 700, 'type' => 'private'],
        ], $overrides);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET];
    }
}
