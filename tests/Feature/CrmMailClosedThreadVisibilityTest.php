<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
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
            ->assertSet('activeTab', 'incoming')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$closed])
            ->set('activeTab', 'inbox')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$closed])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$open, $closed]);
    }

    public function test_reaction_tab_includes_read_new_leads_and_needed_replies_only(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create(['name' => 'Reaction', 'email' => 'reaction@example.com', 'username' => 'reaction@example.com']);
        $threads = collect(['new', 'waiting_manager', 'in_progress', 'waiting_customer', 'closed', 'spam', 'draft'])
            ->mapWithKeys(function (string $status) use ($mailbox): array {
                $thread = $this->thread($mailbox, $status.'@example.com', $status);
                $this->incomingMessage($thread, $mailbox, $status);

                return [$status => $thread];
            });
        $trashed = $this->thread($mailbox, 'trashed@example.com', 'new');
        $trashed->delete();

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ListCrmMailThreads::class)->set('activeTab', 'needs_response');
        $page->assertCanSeeTableRecords([$threads['new'], $threads['waiting_manager']])
            ->assertCanNotSeeTableRecords($threads->except(['new', 'waiting_manager'])->values())
            ->assertCanNotSeeTableRecords([$trashed]);
        $this->assertSame('2', $page->instance()->getTabs()['needs_response']->getBadge());
    }

    public function test_reaction_tab_lists_newest_mail_first_by_default(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create(['name' => 'Order', 'email' => 'order-sort@example.com', 'username' => 'order-sort@example.com']);
        $old = $this->thread($mailbox, 'old@example.com', 'new');
        $old->forceFill(['last_message_at' => now()->subDays(20)])->save();
        $fresh = $this->thread($mailbox, 'fresh@example.com', 'new');
        $fresh->forceFill(['last_message_at' => now()->subHour()])->save();
        $middle = $this->thread($mailbox, 'middle@example.com', 'waiting_manager');
        $middle->forceFill(['last_message_at' => now()->subDays(3)])->save();

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListCrmMailThreads::class)
            ->set('activeTab', 'needs_response')
            ->assertCanSeeTableRecords([$fresh, $middle, $old], inOrder: true)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$fresh, $middle, $old], inOrder: true);
    }

    public function test_incoming_mail_stays_newest_first_and_new_replies_return_to_the_top(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox = CrmMailbox::create(['name' => 'Incoming', 'email' => 'incoming-sort@example.com', 'username' => 'incoming-sort@example.com']);
        $old = $this->thread($mailbox, 'old-unread@example.com', 'new');
        $fresh = $this->thread($mailbox, 'fresh-read@example.com', 'in_progress');
        $sameTime = $this->thread($mailbox, 'same-time@example.com', 'in_progress');
        $receivedAt = now()->subHour();

        foreach ([$old, $fresh, $sameTime] as $thread) {
            $this->incomingMessage($thread, $mailbox, $thread->subject);
            $thread->messages()->update(['received_at' => $thread->is($old) ? now()->subDay() : $receivedAt]);
            $thread->refreshMessageSummary();
        }
        $old->update(['unread_count' => 1, 'priority' => 'urgent']);
        $old->messages()->update(['is_read' => false]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ListCrmMailThreads::class)
            ->assertSet('activeTab', 'incoming')
            ->assertCanSeeTableRecords([$sameTime, $fresh, $old], inOrder: true)
            ->assertSee('Не прочитано · 1');

        $old->messages()->create([
            'mailbox_id' => $mailbox->id,
            'message_id' => '<new-reply@example.com>',
            'direction' => 'incoming',
            'from_email' => $old->participant_email,
            'subject' => 'Новый ответ в старой переписке',
            'is_read' => false,
            'received_at' => now(),
        ]);
        $old->refreshMessageSummary();

        $page->call('$refresh')
            ->assertCanSeeTableRecords([$old, $sameTime, $fresh], inOrder: true)
            ->assertSee('Не прочитано · 2');
    }

    public function test_trashed_thread_can_be_opened_from_trash_without_appearing_in_active_tabs(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'Trash route',
            'email' => 'trash-route@example.com',
            'username' => 'trash-route@example.com',
        ]);
        $trashed = $this->thread($mailbox, 'trashed-route@example.com', 'waiting_manager');
        $trashed->delete();

        $resolved = CrmMailThreadResource::resolveRecordRouteBinding($trashed->id);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->trashed());
        $this->assertNull(CrmMailThread::query()->find($trashed->id));
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
