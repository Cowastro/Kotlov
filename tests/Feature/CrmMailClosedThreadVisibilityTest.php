<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailClosedThreadVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_threads_are_hidden_from_work_mail_but_remain_available_in_all_mail(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'closed-filter@kotlov.by',
            'username' => 'closed-filter@kotlov.by',
        ]);
        $open = $this->thread($mailbox, 'open@example.com', 'waiting_manager');
        $closed = $this->thread($mailbox, 'closed@example.com', 'closed');

        $this->incomingMessage($open, $mailbox, 'Открытая переписка');
        $this->incomingMessage($closed, $mailbox, 'Закрытая переписка');

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListCrmMailThreads::class)
            ->set('activeTab', 'inbox')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$closed])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$open, $closed]);
    }

    private function thread(CrmMailbox $mailbox, string $email, string $status): CrmMailThread
    {
        return CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', $email),
            'participant_email' => $email,
            'subject' => $email,
            'status' => $status,
            'messages_count' => 1,
            'last_message_at' => now(),
        ]);
    }

    private function incomingMessage(CrmMailThread $thread, CrmMailbox $mailbox, string $subject): void
    {
        $thread->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => '<'.$thread->id.'@example.com>',
            'direction' => 'incoming',
            'from_email' => $thread->participant_email,
            'subject' => $subject,
            'is_read' => true,
            'received_at' => now(),
        ]);
    }
}
