<?php

namespace Tests\Unit;

use App\Models\SupportChatSetting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\SupportChatAutoResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportChatAutoResponderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ai.api_key' => 'deepseek-test-key',
            'services.ai.api_url' => 'https://api.deepseek.test/chat/completions',
            'services.ai.model' => 'deepseek-chat',
        ]);
        Http::preventStrayRequests();
    }

    public function test_enabled_ai_sends_restricted_context_and_saves_assistant_reply(): void
    {
        SupportChatSetting::query()->create(['id' => 1, 'ai_enabled' => true]);
        [$conversation, $visitorMessage] = $this->conversationWithVisitorMessage();
        Http::fake([
            'api.deepseek.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'Я AI-помощник KOTLOV. Подскажите площадь объекта?'],
                ]],
            ]),
        ]);

        (new SupportChatAutoResponder)->reply($conversation->id, $visitorMessage->id);

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversation->id,
            'sender_type' => 'assistant',
            'body' => 'Я AI-помощник KOTLOV. Подскажите площадь объекта?',
        ]);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.deepseek.test/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer deepseek-test-key')
            && data_get($request->data(), 'model') === 'deepseek-chat'
            && data_get($request->data(), 'temperature') === 0.1
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'не придумывай и не подтверждай цену')
            && str_contains((string) data_get($request->data(), 'messages.0.content'), 'уже безопасно сохранены')
            && ! str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), '+375 29 123-45-67'));
    }

    public function test_disabled_ai_does_not_call_deepseek(): void
    {
        SupportChatSetting::query()->create(['id' => 1, 'ai_enabled' => false]);
        [$conversation, $visitorMessage] = $this->conversationWithVisitorMessage();

        (new SupportChatAutoResponder)->reply($conversation->id, $visitorMessage->id);

        Http::assertNothingSent();
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_ai_stops_when_a_manager_has_joined_the_conversation(): void
    {
        SupportChatSetting::query()->create(['id' => 1, 'ai_enabled' => true]);
        [$conversation, $visitorMessage] = $this->conversationWithVisitorMessage();
        $conversation->update(['last_manager_message_at' => now(), 'status' => 'answered']);

        (new SupportChatAutoResponder)->reply($conversation->id, $visitorMessage->id);

        Http::assertNothingSent();
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_third_ai_reply_is_a_deterministic_handoff_without_an_api_call(): void
    {
        SupportChatSetting::query()->create(['id' => 1, 'ai_enabled' => true]);
        [$conversation] = $this->conversationWithVisitorMessage();
        $conversation->messages()->createMany([
            ['sender_type' => 'assistant', 'body' => 'Первое уточнение'],
            ['sender_type' => 'visitor', 'body' => 'Дом 150 м²'],
            ['sender_type' => 'assistant', 'body' => 'Второе уточнение'],
        ]);
        $latestVisitor = $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => 'Газ, настенный котёл',
        ]);

        (new SupportChatAutoResponder)->reply($conversation->id, $latestVisitor->id);

        Http::assertNothingSent();
        $handoff = SupportMessage::query()
            ->where('support_conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('assistant', $handoff->sender_type);
        $this->assertStringContainsString('имя и телефон уже сохранены', $handoff->body);
        $this->assertStringContainsString('передаю запрос менеджеру', $handoff->body);
        $this->assertNotNull($conversation->fresh()->ai_handoff_at);

        $nextVisitor = $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => 'Когда ответят?',
        ]);
        (new SupportChatAutoResponder)->reply($conversation->id, $nextVisitor->id);
        $this->assertSame(7, $conversation->messages()->count());
    }

    private function conversationWithVisitorMessage(): array
    {
        $conversation = SupportConversation::query()->create([
            'public_id' => fake()->uuid(),
            'session_hash' => hash('sha256', fake()->uuid()),
            'name' => 'Иван',
            'phone' => '+375 29 123-45-67',
            'status' => 'new',
            'last_message_at' => now(),
            'last_visitor_message_at' => now(),
        ]);
        $message = $conversation->messages()->create([
            'sender_type' => 'visitor',
            'body' => 'Нужен котёл',
        ]);

        return [$conversation, $message];
    }
}
