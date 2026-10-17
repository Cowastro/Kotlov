<?php

namespace Tests\Unit;

use App\Models\ContactRequest;
use App\Services\ContactRequestNotifier;
use App\Services\TelegramApi;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ContactRequestNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_callback_request_is_sent_to_telegram_with_full_selection_context(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.contact_requests_chat_id' => '-100987654321',
        ]);
        $request = new ContactRequest([
            'name' => 'Иван',
            'phone' => '+375 29 123-45-67',
            'source' => 'ai_selection',
            'product_name' => 'Электрические котлы',
            'product_url' => 'https://kotlov.by/catalog/kotly',
            'message' => "Запрос: электрокотёл 30 кВт\nОтветы: fuel: Электричество; power: 30",
        ]);
        $request->id = 55;
        $request->created_at = Carbon::parse('2026-10-17 12:30:00');

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()->withArgs(
            fn ($chatId, string $message): bool => $chatId === '-100987654321'
                && str_contains($message, 'Нужна помощь с AI-подбором')
                && str_contains($message, 'Иван')
                && str_contains($message, '+375 29 123-45-67')
                && str_contains($message, 'Электрические котлы')
                && str_contains($message, 'электрокотёл 30 кВт')
                && str_contains($message, 'power: 30')
        )->andReturn(['ok' => true, 'result' => ['message_id' => 777]]);

        (new ContactRequestNotifier($telegram))->notify($request);
    }

    public function test_contact_requests_channel_falls_back_to_orders_channel(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.orders_chat_id' => '-100111111111',
            'services.telegram.contact_requests_chat_id' => '-100111111111',
        ]);
        $request = new ContactRequest([
            'name' => 'Анна',
            'phone' => '+375 33 000-00-00',
            'source' => 'consultation_form',
            'message' => 'Нужна консультация',
        ]);
        $request->id = 56;
        $request->created_at = now();

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('sendMessage')->once()
            ->withArgs(fn ($chatId, string $message): bool => $chatId === '-100111111111' && str_contains($message, 'Анна'))
            ->andReturn(['ok' => true]);

        (new ContactRequestNotifier($telegram))->notify($request);
    }
}
