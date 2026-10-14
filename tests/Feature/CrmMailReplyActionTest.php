<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
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

    public function test_message_can_open_forward_form_with_subject_body_and_attachments_prefilled(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create([
            'name' => 'KOTLOV',
            'email' => 'forward-action@kotlov.by',
            'username' => 'forward-action@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'forward-action@example.com|request'),
            'participant_name' => 'Тестовый клиент',
            'participant_email' => 'forward-action@example.com',
            'subject' => 'Запрос прайса',
            'status' => 'new',
        ]);
        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_name' => 'Клиент',
            'from_email' => 'forward-action@example.com',
            'to' => [['name' => 'KOTLOV', 'email' => 'forward-action@kotlov.by']],
            'subject' => 'Запрос прайса',
            'text_body' => 'Добрый день. Пришлите прайс.',
            'attachments_count' => 1,
            'is_read' => false,
            'received_at' => now(),
            'metadata' => ['attachments' => [[
                'path' => 'crm-mail-attachments/incoming/price.xlsx',
                'name' => 'price.xlsx',
            ]]],
        ]);

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertActionExists('forward', fn ($action): bool => $action->getModalSubmitActionLabel() === 'Переслать')
            ->call('openForward', $message->id)
            ->assertActionMounted('forward')
            ->assertSchemaStateSet([
                'subject' => 'Fwd: Запрос прайса',
                'original_attachments' => ['crm-mail-attachments/incoming/price.xlsx'],
            ]);
    }
}
