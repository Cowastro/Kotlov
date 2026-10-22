<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmPersonalMailboxAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_personal_mailbox_is_visible_only_to_its_employees_and_admins(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $colleague = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $info = $this->mailbox('info@kotlov.by');
        $b2b = $this->mailbox('b2b@kotlov.by');
        $b2b->users()->attach($owner);
        $shared = $this->thread($info, 'retail@example.com');
        $private = $this->thread($b2b, 'wholesale@example.com');
        $privateMessage = $this->message($private);
        $this->message($shared);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($colleague);
        Livewire::test(ListCrmMailThreads::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$shared])
            ->assertCanNotSeeTableRecords([$private]);
        $this->assertSame(1, ListCrmMailThreads::messageBadgeCounts()['all']);
        $this->assertSame(['info@kotlov.by'], array_column((new ListCrmMailThreads)->mailboxSwitchOptions(), 'email'));
        $this->assertSame([$info->id], \App\Filament\Resources\CrmMailboxes\CrmMailboxResource::getEloquentQuery()->pluck('id')->all());
        $this->assertFalse($b2b->isVisibleTo($colleague));
        $this->get(route('admin.crm-mail-messages.attachments.download', ['message' => $privateMessage, 'attachment' => 0]))->assertForbidden();
        $this->get(ViewCrmMailThread::getUrl(['record' => $private]))->assertNotFound();

        $this->actingAs($owner);
        Livewire::test(ListCrmMailThreads::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$shared, $private]);
        $this->assertSame(2, ListCrmMailThreads::messageBadgeCounts()['all']);

        $this->actingAs($admin);
        Livewire::test(ListCrmMailThreads::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$shared, $private]);
        $this->assertTrue($b2b->isVisibleTo($admin));
        $this->assertSame(2, CrmMailMessage::query()->visibleTo($admin)->count());
        Livewire::test(\App\Filament\Resources\CrmMailboxes\Pages\ListCrmMailboxes::class)
            ->assertSuccessful()
            ->assertSee('Общий')
            ->assertSee($owner->name);
        Livewire::test(\App\Filament\Resources\CrmMailboxes\Pages\EditCrmMailbox::class, ['record' => $b2b->getRouteKey()])
            ->assertSuccessful()
            ->fillForm(['users' => [$owner->id, $colleague->id]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($b2b->fresh()->isVisibleTo($colleague));
    }

    private function mailbox(string $email): CrmMailbox
    {
        return CrmMailbox::query()->updateOrCreate(['email' => $email], [
            'name' => $email,
            'username' => $email,
            'is_enabled' => true,
        ]);
    }

    private function thread(CrmMailbox $mailbox, string $email): CrmMailThread
    {
        return CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', $email),
            'participant_name' => $email,
            'participant_email' => $email,
            'subject' => 'Письмо '.$email,
            'status' => 'in_progress',
            'messages_count' => 1,
            'unread_count' => 1,
            'last_message_at' => now(),
        ]);
    }

    private function message(CrmMailThread $thread): CrmMailMessage
    {
        return $thread->messages()->create([
            'mailbox_id' => $thread->mailbox_id,
            'direction' => 'incoming',
            'from_email' => $thread->participant_email,
            'subject' => $thread->subject,
            'is_read' => false,
            'received_at' => now(),
        ]);
    }
}
