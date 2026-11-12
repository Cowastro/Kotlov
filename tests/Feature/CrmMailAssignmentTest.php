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
            'is_crm_user' => true,
        ]);
        $assignee = User::factory()->create([
            'name' => 'Анатолий',
            'role' => 'manager',
            'is_active' => true,
            'is_crm_user' => true,
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

        $thread->messages()->create([
            'mailbox_id' => $mailbox->id, 'direction' => 'incoming',
            'from_email' => 'client@example.com', 'subject' => $thread->subject,
            'text_body' => 'Прошу подготовить предложение.', 'received_at' => now(), 'is_read' => false,
        ]);

        Livewire::actingAs($dispatcher)
            ->test(ListCrmMailThreads::class)
            ->assertTableActionExists('assign_responsible', record: $thread)
            ->callTableColumnAction('assignedUser.name', $thread)
            ->assertActionMounted(\Filament\Actions\Testing\TestAction::make('assign_responsible')->table($thread))
            ->setActionData([
                'assigned_user_id' => $assignee->id,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertTableColumnStateSet('assignedUser.name', 'Анатолий', $thread)
            ->assertNoRedirect();

        $this->assertSame($assignee->id, $thread->fresh()->assigned_user_id);
        $this->assertSame('in_progress', $thread->fresh()->status);
        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame('Вам назначена переписка', $assignee->notifications()->first()->data['title']);
        $this->assertSame(1, $thread->fresh()->unread_count);
        $this->assertFalse($thread->messages()->sole()->is_read);

        Livewire::actingAs($dispatcher)->test(ListCrmMailThreads::class)
            ->set('activeTab', 'needs_response')
            ->assertCanSeeTableRecords([$thread])
            ->callTableColumnAction('assignedUser.name', $thread)
            ->assertActionMounted(\Filament\Actions\Testing\TestAction::make('assign_responsible')->table($thread))
            ->setActionData(['assigned_user_id' => $dispatcher->id])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertTableColumnStateSet('assignedUser.name', 'Диспетчер', $thread)
            ->assertCanSeeTableRecords([$thread])
            ->assertNoRedirect();
        $this->assertSame($dispatcher->id, $thread->fresh()->assigned_user_id);
        $this->assertSame(1, $dispatcher->notifications()->count());
    }

    public function test_list_assignment_rejects_staff_without_access_to_the_mailbox(): void
    {
        $dispatcher = User::factory()->create(['role' => 'manager', 'is_active' => true, 'is_crm_user' => true]);
        $outsider = User::factory()->create(['role' => 'manager', 'is_active' => true, 'is_crm_user' => true]);
        $mailbox = CrmMailbox::create(['name' => 'Private', 'email' => 'private@example.com', 'username' => 'private@example.com']);
        $mailbox->users()->attach($dispatcher);
        $thread = $mailbox->threads()->create([
            'thread_key' => 'restricted-assignment', 'subject' => 'Заявка',
            'participant_email' => 'client@example.com', 'status' => 'new',
        ]);
        $thread->messages()->create(['mailbox_id' => $mailbox->id, 'direction' => 'incoming', 'from_email' => 'client@example.com', 'received_at' => now()]);

        Livewire::actingAs($dispatcher)->test(ListCrmMailThreads::class)
            ->callTableColumnAction('assignedUser.name', $thread)
            ->setActionData(['assigned_user_id' => $outsider->id])
            ->callMountedAction()
            ->assertHasActionErrors(['assigned_user_id']);

        $this->assertNull($thread->fresh()->assigned_user_id);
        $this->assertSame(0, $outsider->notifications()->count());
    }

    public function test_crm_staff_scope_excludes_customers_inactive_users_and_non_crm_managers(): void
    {
        $crmManager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
            'is_crm_user' => true,
        ]);
        User::factory()->create([
            'role' => 'client',
            'is_active' => true,
            'is_crm_user' => true,
        ]);
        User::factory()->create([
            'role' => 'manager',
            'is_active' => false,
            'is_crm_user' => true,
        ]);
        User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
            'is_crm_user' => false,
        ]);

        $this->assertEquals([$crmManager->id], User::query()->crmStaff()->pluck('id')->all());
    }
}
