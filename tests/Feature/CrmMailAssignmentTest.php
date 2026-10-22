<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_a_mail_thread_and_assignee_receives_a_notification(): void
    {
        $dispatcher = User::factory()->create([
            'name' => 'Диспетчер',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $assignee = User::factory()->create([
            'name' => 'Анатолий',
            'role' => 'manager',
            'is_active' => true,
        ]);
        $mailbox = CrmMailbox::query()->create([
            'name' => 'Общая почта',
            'email' => 'assignment@kotlov.by',
            'username' => 'assignment@kotlov.by',
            'is_enabled' => true,
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'assignment-test'),
            'subject' => 'Запрос коммерческого предложения',
            'participant_name' => 'ООО Клиент',
            'participant_email' => 'client@example.com',
            'status' => 'new',
            'priority' => 'normal',
            'messages_count' => 1,
            'unread_count' => 1,
            'last_message_at' => now(),
        ]);

        Livewire::actingAs($dispatcher)
            ->test(ListCrmMailThreads::class)
            ->assertTableActionExists('assign_responsible', record: $thread)
            ->callTableAction('assign_responsible', $thread, [
                'assigned_user_id' => $assignee->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($assignee->id, $thread->fresh()->assigned_user_id);
        $this->assertSame('in_progress', $thread->fresh()->status);
        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame('Вам назначена переписка', $assignee->notifications()->first()->data['title']);
    }
}
