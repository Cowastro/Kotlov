<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportConversations\Pages\ListSupportConversations;
use App\Filament\Resources\SupportConversations\Pages\ViewSupportConversation;
use App\Models\SupportChatSetting;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_start_continue_and_restore_session_chat(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->getJson(route('support-chat.show'))
            ->assertOk()
            ->assertJsonPath('conversation', null);

        $start = $this->postJson(route('support-chat.start'), [
            'name' => 'Иван',
            'phone' => '+375 29 123-45-67',
            'message' => 'Нужен газовый котёл для дома 150 м²',
            'page_url' => 'https://kotlov.by/gazovye-kotly',
        ]);

        $start->assertCreated()
            ->assertJsonPath('conversation.name', 'Иван')
            ->assertJsonPath('conversation.status', 'new')
            ->assertJsonPath('messages.0.sender', 'visitor')
            ->assertJsonPath('messages.0.body', 'Нужен газовый котёл для дома 150 м²');

        $conversation = SupportConversation::query()->sole();
        $this->assertSame('new', $conversation->status);
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $manager->id]);

        $this->postJson(route('support-chat.send'), [
            'message' => 'Желательно настенный двухконтурный.',
        ])->assertCreated()->assertJsonCount(2, 'messages');
        $this->assertDatabaseCount('notifications', 2);

        $this->getJson(route('support-chat.show'))
            ->assertOk()
            ->assertJsonPath('conversation.id', $conversation->public_id)
            ->assertJsonCount(2, 'messages');
    }

    public function test_chat_is_bound_to_browser_session_and_closed_chat_rejects_messages(): void
    {
        $this->postJson(route('support-chat.start'), [
            'name' => 'Анна',
            'phone' => '+375 44 765-43-21',
            'message' => 'Подскажите по радиаторам',
        ])->assertCreated();

        $conversation = SupportConversation::query()->sole();
        $conversation->update(['status' => 'closed']);

        $this->postJson(route('support-chat.send'), ['message' => 'Есть кто-нибудь?'])
            ->assertConflict();

        $this->flushSession();
        $this->getJson(route('support-chat.show'))
            ->assertOk()
            ->assertJsonPath('conversation', null);
    }

    public function test_manager_reply_is_returned_to_the_visitor_and_marked_read(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
            'name' => 'Ольга',
        ]);
        $this->postJson(route('support-chat.start'), [
            'name' => 'Пётр',
            'phone' => '+375 33 111-22-33',
            'message' => 'Нужна консультация',
        ])->assertCreated();

        $conversation = SupportConversation::query()->sole();
        $message = $conversation->messages()->create([
            'sender_type' => 'manager',
            'sender_id' => $manager->id,
            'body' => 'Здравствуйте! Уже помогаю.',
        ]);
        $conversation->update([
            'status' => 'answered',
            'last_message_at' => now(),
            'last_manager_message_at' => now(),
        ]);

        $this->getJson(route('support-chat.show'))
            ->assertOk()
            ->assertJsonPath('conversation.status', 'answered')
            ->assertJsonPath('messages.1.sender_name', 'Ольга')
            ->assertJsonPath('messages.1.body', 'Здравствуйте! Уже помогаю.');

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_ai_assistant_reply_is_returned_as_a_team_message_and_marked_read(): void
    {
        $this->postJson(route('support-chat.start'), [
            'name' => 'Ирина',
            'phone' => '+375 29 444-55-66',
            'message' => 'Нужен котёл',
        ])->assertCreated();

        $conversation = SupportConversation::query()->sole();
        $assistantMessage = $conversation->messages()->create([
            'sender_type' => 'assistant',
            'body' => 'Я AI-помощник KOTLOV. Какая площадь дома?',
        ]);

        $this->getJson(route('support-chat.show'))
            ->assertOk()
            ->assertJsonPath('messages.1.sender', 'assistant')
            ->assertJsonPath('messages.1.sender_name', 'AI-помощник KOTLOV');

        $this->assertNotNull($assistantMessage->fresh()->read_at);
    }

    public function test_manager_can_read_and_reply_from_admin_chat(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
            'name' => 'Мария',
        ]);
        $conversation = SupportConversation::query()->create([
            'public_id' => fake()->uuid(),
            'session_hash' => hash('sha256', 'visitor-session'),
            'name' => 'Сергей',
            'phone' => '+375 29 555-66-77',
            'status' => 'new',
            'last_message_at' => now(),
            'last_visitor_message_at' => now(),
        ]);
        $visitorMessage = $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => 'Есть ли котёл в наличии?',
        ]);

        Livewire::actingAs($manager)
            ->test(ViewSupportConversation::class, ['record' => $conversation->getRouteKey()])
            ->assertSee('Есть ли котёл в наличии?')
            ->assertActionVisible('reply')
            ->callAction('reply', ['body' => 'Да, проверяем остаток и сейчас уточним.'])
            ->assertHasNoActionErrors();

        $this->assertNotNull($visitorMessage->fresh()->read_at);
        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversation->id,
            'sender_type' => 'manager',
            'sender_id' => $manager->id,
            'body' => 'Да, проверяем остаток и сейчас уточним.',
        ]);
        $this->assertDatabaseHas('support_conversations', [
            'id' => $conversation->id,
            'status' => 'answered',
            'assigned_user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_enable_and_disable_safe_ai_replies_from_chat_list(): void
    {
        config([
            'services.ai.api_key' => 'deepseek-test-key',
            'services.ai.api_url' => 'https://api.deepseek.test/chat/completions',
        ]);
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);
        SupportChatSetting::query()->create(['key' => 'legacy-row', 'ai_enabled' => false]);

        Livewire::actingAs($manager)
            ->test(ListSupportConversations::class)
            ->assertActionVisible('toggleAiReplies')
            ->callAction('toggleAiReplies')
            ->assertHasNoActionErrors();

        $this->assertTrue(SupportChatSetting::current()->fresh()->ai_enabled);

        Livewire::actingAs($manager)
            ->test(ListSupportConversations::class)
            ->callAction('toggleAiReplies')
            ->assertHasNoActionErrors();

        $this->assertFalse(SupportChatSetting::current()->fresh()->ai_enabled);
    }
}
