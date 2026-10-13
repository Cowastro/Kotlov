<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailReplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_opens_as_a_single_compose_form_without_a_second_confirmation(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'reply-action@kotlov.by',
            'username' => 'reply-action@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'reply-action@example.com|request'),
            'participant_name' => 'Тестовый клиент',
            'participant_email' => 'reply-action@example.com',
            'subject' => 'Запрос',
            'status' => 'new',
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertActionExists('reply', fn ($action): bool => ! $action->isConfirmationRequired()
                && $action->getModalSubmitActionLabel() === 'Отправить письмо');
    }
}
