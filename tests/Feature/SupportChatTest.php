<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Livewire\ContactRequestDialog;
use App\Models\ContactRequest;
use App\Models\SupportChatSetting;
use App\Models\SupportConversation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
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
        $this->assertDatabaseCount('contact_requests', 1);
        $this->assertDatabaseHas('contact_requests', [
            'external_source' => ContactRequest::SITE_CHAT_SOURCE,
            'external_id' => $conversation->public_id,
            'status' => 'new',
            'message' => 'Желательно настенный двухконтурный.',
        ]);

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

    public function test_site_chat_and_manager_reply_use_the_unified_inbox(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
            'name' => 'Мария',
        ]);
        $this->postJson(route('support-chat.start'), [
            'name' => 'Сергей',
            'phone' => '+375 29 555-66-77',
            'message' => 'Есть ли котёл в наличии?',
        ])->assertCreated();

        $conversation = SupportConversation::query()->sole();
        $lead = $conversation->inboxEntry()->sole();
        $this->assertTrue($lead->needs_attention);
        $cardUrl = ContactRequestResource::getUrl('view', ['record' => $lead], panel: 'admin');
        $this->assertSame($cardUrl, $manager->notifications()->sole()->data['actions'][0]['url']);

        $this->actingAs($manager);
        $this->get(SupportConversationResource::getUrl('view', ['record' => $conversation], panel: 'admin'))
            ->assertRedirect($cardUrl);
        $this->get($cardUrl)
            ->assertOk()
            ->assertSee('Есть ли котёл в наличии?')
            ->assertSee('Напишите ответ…');
        Livewire::test(ListContactRequests::class)
            ->assertCanSeeTableRecords([$lead])
            ->mountAction(TestAction::make('dialog')->table($lead))
            ->assertActionMounted(TestAction::make('dialog')->table($lead));

        $dialog = Livewire::test(ContactRequestDialog::class, ['contactRequestId' => $lead->id])
            ->assertSee('Есть ли котёл в наличии?')
            ->set('draft', 'Да, проверяем остаток и сейчас уточним.')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('draft', '');
        $this->getJson(route('support-chat.show'))
            ->assertOk()
            ->assertJsonPath('conversation.status', 'answered')
            ->assertJsonPath('messages.1.sender_name', 'Мария')
            ->assertJsonPath('messages.1.body', 'Да, проверяем остаток и сейчас уточним.');

        $this->assertNotNull($conversation->messages()->first()->read_at);
        $this->assertDatabaseHas('contact_requests', [
            'id' => $lead->id,
            'status' => 'waiting',
            'responsible_user_id' => $manager->id,
            'needs_attention' => false,
        ]);

        $dialog->call('closeSiteChat')->assertSee('Диалог завершён.');
        $this->postJson(route('support-chat.send'), ['message' => 'Спасибо!'])->assertConflict();
        $dialog->call('reopenSiteChat')->assertSee('Напишите ответ…');
        $this->postJson(route('support-chat.send'), ['message' => 'Спасибо!'])->assertCreated();
        $this->assertTrue($lead->fresh()->needs_attention);
        $this->assertDatabaseCount('contact_requests', 1);
    }

    public function test_manager_can_enable_and_disable_safe_ai_replies_from_unified_inbox(): void
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
            ->test(ListContactRequests::class)
            ->assertActionVisible('toggleAiReplies')
            ->callAction('toggleAiReplies')
            ->assertHasNoActionErrors();

        $this->assertTrue(SupportChatSetting::current()->fresh()->ai_enabled);

        Livewire::actingAs($manager)
            ->test(ListContactRequests::class)
            ->callAction('toggleAiReplies')
            ->assertHasNoActionErrors();

        $this->assertFalse(SupportChatSetting::current()->fresh()->ai_enabled);
    }
}
