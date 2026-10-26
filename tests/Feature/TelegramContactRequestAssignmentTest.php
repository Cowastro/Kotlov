<?php

namespace Tests\Feature;

use App\Models\ContactRequest;
use App\Models\User;
use App\Services\TelegramApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelegramContactRequestAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_take_contact_request_from_telegram_once(): void
    {
        config([
            'services.telegram.contact_requests_chat_id' => '-100987654321',
            'services.telegram.orders_chat_id' => '-100987654321',
        ]);

        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
            'telegram_username' => 'sales_manager',
        ]);
        $contactRequest = ContactRequest::query()->create([
            'name' => 'Клиент',
            'phone' => '+375290000000',
            'message' => 'Уточнить наличие',
            'source' => 'product_availability_request',
            'status' => 'new',
        ]);

        $telegram = Mockery::mock(TelegramApi::class);
        $telegram->shouldReceive('answerCallbackQuery')
            ->once()
            ->with('callback-1', '✅ Заявка назначена на '.$manager->name)
            ->andReturn(['ok' => true]);
        $telegram->shouldReceive('editMessageReplyMarkup')
            ->once()
            ->with('-100987654321', 777)
            ->andReturn(['ok' => true]);
        $telegram->shouldReceive('sendReply')
            ->once()
            ->with('-100987654321', 777, '✅ Взял: @sales_manager')
            ->andReturn(['ok' => true]);
        $this->app->instance(TelegramApi::class, $telegram);

        $this->postJson('/telegram/webhook', [
            'callback_query' => [
                'id' => 'callback-1',
                'data' => 'take_contact_request:'.$contactRequest->id,
                'from' => [
                    'first_name' => 'Менеджер',
                    'username' => 'sales_manager',
                ],
                'message' => [
                    'message_id' => 777,
                    'chat' => ['id' => '-100987654321'],
                ],
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $contactRequest->refresh();
        $this->assertSame($manager->id, $contactRequest->responsible_user_id);
        $this->assertSame('in_progress', $contactRequest->status);
    }
}
