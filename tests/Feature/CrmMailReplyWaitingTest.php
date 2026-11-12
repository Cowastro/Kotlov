<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailReplyWaitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_wait_starts_with_first_unanswered_message_and_ignores_reminders_and_drafts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00'));
        $thread = $this->thread();
        $this->message($thread, 'incoming', now()->subDays(4));
        $this->message($thread, 'outgoing', now()->subDays(3));
        $first = now()->subDays(2)->subHours(3);
        $this->message($thread, 'incoming', $first);
        $this->message($thread, 'incoming', now()->subHour());
        $this->message($thread, 'draft', now());
        $thread->update(['next_follow_up_at' => now(), 'unread_count' => 0, 'status' => 'in_progress']);

        $waiting = CrmMailThread::withReplyWaitingSince()->findOrFail($thread->id);
        $this->assertTrue($waiting->reply_waiting_since->equalTo($first));
        $this->assertSame('Ждёт 2 д 3 ч', $waiting->replyWaitingLabel());

        $this->message($thread, 'outgoing', now());
        $this->assertNull(CrmMailThread::withReplyWaitingSince()->findOrFail($thread->id)->reply_waiting_since);
        $this->travel(10)->minutes();
        $this->message($thread, 'incoming', now());
        $this->travel(35)->minutes();
        $this->assertSame('Ждёт 35 мин', CrmMailThread::withReplyWaitingSince()->findOrFail($thread->id)->replyWaitingLabel());
    }

    public function test_historical_import_and_same_second_ties_follow_message_chronology(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00'));
        $thread = $this->thread();
        $this->message($thread, 'outgoing', now()->subHours(3));
        $this->message($thread, 'incoming', now()->subHours(5));
        $this->assertNull(CrmMailThread::withReplyWaitingSince()->findOrFail($thread->id)->replyWaitingLabel());
        $this->message($thread, 'incoming', now()->subHours(3));
        $this->assertSame('Ждёт 3 ч', CrmMailThread::withReplyWaitingSince()->findOrFail($thread->id)->replyWaitingLabel());
        $this->message($thread, 'outgoing', now()->subHours(3));
        $this->assertNull(CrmMailThread::withReplyWaitingSince()->findOrFail($thread->id)->replyWaitingLabel());
    }

    public function test_reaction_queue_shows_wait_under_responsible_without_extra_controls(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00'));
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Наталья Тестовая']);
        $thread = $this->thread();
        $thread->update(['assigned_user_id' => $manager->id]);
        $this->message($thread, 'incoming', now()->subHours(26));
        $unassigned = $this->thread();
        $this->message($unassigned, 'incoming', now()->subMinutes(15));

        $page = Livewire::actingAs($manager)->test(ListCrmMailThreads::class)
            ->set('activeTab', 'needs_response')
            ->assertCanSeeTableRecords([$thread, $unassigned])
            ->assertSeeText('Наталья Тестовая')
            ->assertSeeText('Не назначен')
            ->assertSeeText('Ждёт 1 д 2 ч')
            ->assertSeeText('Ждёт 15 мин');
        $ownerColumn = $page->instance()->getTable()->getColumn('assignedUser.name');
        $this->assertSame('danger', $ownerColumn->record($unassigned)->getColor('Не назначен'));
        $this->assertSame(\Filament\Support\Enums\FontWeight::Bold, $ownerColumn->getWeight());
        $this->assertSame('heroicon-o-exclamation-circle', $ownerColumn->getIcon('Не назначен'));
        $this->assertSame('gray', $ownerColumn->record($thread)->getColor($manager->name));
        $page->set('activeTab', 'all')->assertDontSeeText('Ждёт 1 д 2 ч');
    }

    public function test_longest_unanswered_wait_stays_first_after_a_repeat_message(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00'));
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $oldest = $this->thread();
        $oldest->update(['assigned_user_id' => $manager->id]);
        $this->message($oldest, 'incoming', now()->subDays(3));
        $this->message($oldest, 'incoming', now());
        $middle = $this->thread();
        $this->message($middle, 'incoming', now()->subDay());
        $freshUrgent = $this->thread();
        $freshUrgent->update(['priority' => 'urgent', 'next_follow_up_at' => now()->subHour()]);
        $this->message($freshUrgent, 'incoming', now()->subMinutes(10));

        Livewire::actingAs($manager)->test(ListCrmMailThreads::class)
            ->set('activeTab', 'needs_response')
            ->assertCanSeeTableRecords([$oldest, $middle, $freshUrgent], inOrder: true);
    }

    private function thread(): CrmMailThread
    {
        $mailbox = CrmMailbox::create(['name' => 'Waiting', 'email' => fake()->unique()->safeEmail(), 'username' => fake()->unique()->safeEmail()]);

        return $mailbox->threads()->create([
            'thread_key' => fake()->uuid(), 'participant_email' => 'buyer@example.com',
            'subject' => 'Запрос оборудования', 'status' => 'waiting_manager', 'last_message_at' => now(),
        ]);
    }

    private function message(CrmMailThread $thread, string $direction, Carbon $at): void
    {
        $thread->messages()->create([
            'mailbox_id' => $thread->mailbox_id, 'direction' => $direction,
            'from_email' => $direction === 'incoming' ? 'buyer@example.com' : 'sales@example.com',
            'received_at' => $at, 'is_read' => true,
        ]);
    }
}
