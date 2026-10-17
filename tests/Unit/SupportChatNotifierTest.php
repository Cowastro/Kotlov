<?php

namespace Tests\Unit;

use App\Models\SupportConversation;
use App\Services\SupportChatNotifier;
use App\Services\TelegramApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupportChatNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_online_chat_is_sent_to_telegram_with_contact_message_and_admin_link(): void
    {
        config([
            'app.url' => 'https://kotlov.by',
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.support_chat_id' => '-100222333444',
        ]);
        $conversation = SupportConversation::query()->create([
            'public_id' => fake()->uuid(),
            'session_hash' => hash('sha256', 'telegram-test-session'),
            'name' => 'Иван',
            'phone' => '+375 29 123-45-67',
            'status' => 'new',
            'page_url' => 'https://kotlov.by/gazovye-kotly',
            'last_message_at' => now(),
            'last_visitor_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => 'Нужен котёл для дома 150 м²',
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()->withArgs(
            fn ($chatId, string $message): bool => $chatId === '-100222333444'
                && str_contains($message, 'Новый онлайн-чат')
                && str_contains($message, 'Иван')
                && str_contains($message, '+375 29 123-45-67')
                && str_contains($message, 'Нужен котёл для дома 150 м²')
                && str_contains($message, '/admin/support-conversations/'.$conversation->id)
        )->andReturn(['ok' => true]);

        (new SupportChatNotifier($telegram))->notifyNewConversation($conversation);
    }

    public function test_follow_up_message_does_not_create_another_telegram_alert(): void
    {
        $conversation = SupportConversation::query()->create([
            'public_id' => fake()->uuid(),
            'session_hash' => hash('sha256', 'follow-up-session'),
            'name' => 'Анна',
            'phone' => '+375 44 111-22-33',
            'status' => 'new',
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldNotReceive('sendMessage');

        (new SupportChatNotifier($telegram))->notifyNewMessage($conversation);
    }
}
