<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmMailThreadUnreadPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_newest_messages_are_shown_first_in_a_thread(): void
    {
        [$mailbox, $thread] = $this->mailThread();

        $oldest = $this->message($mailbox, $thread, 'Старое письмо', now()->subHour());
        $newest = $this->message($mailbox, $thread, 'Новое письмо', now());

        $loadedThread = CrmMailThreadResource::getEloquentQuery()->findOrFail($thread->id);

        $this->assertSame([$newest->id, $oldest->id], $loadedThread->messages->pluck('id')->all());
    }

    public function test_unread_messages_remain_visually_marked_during_the_first_open(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        [$mailbox, $thread] = $this->mailThread();

        $read = $this->message($mailbox, $thread, 'Ранее прочитано', now()->subHour(), true);
        $unread = $this->message($mailbox, $thread, 'Новое непрочитанное', now(), false);
        $thread->forceFill(['messages_count' => 2, 'unread_count' => 1])->save();

        Livewire::actingAs($manager)
            ->test(ViewCrmMailThread::class, ['record' => $thread->getRouteKey()])
            ->assertSet('initiallyUnreadMessageIds', [$unread->id])
            ->assertSee('Не прочитано')
            ->assertSee('is-unread', false);

        $this->assertTrue($unread->fresh()->is_read);
        $this->assertTrue($read->fresh()->is_read);
        $this->assertSame(0, $thread->fresh()->unread_count);
    }

    /** @return array{CrmMailbox, CrmMailThread} */
    private function mailThread(): array
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'thread-order@kotlov.by',
            'username' => 'thread-order@kotlov.by',
            'is_enabled' => true,
        ]);

        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'thread-order@example.com'),
            'participant_name' => 'Клиент',
            'participant_email' => 'thread-order@example.com',
            'subject' => 'Переписка',
            'status' => 'new',
        ]);

        return [$mailbox, $thread];
    }

    private function message(
        CrmMailbox $mailbox,
        CrmMailThread $thread,
        string $subject,
        mixed $receivedAt,
        bool $isRead = false,
    ): CrmMailMessage {
        return CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'incoming',
            'from_name' => 'Клиент',
            'from_email' => 'thread-order@example.com',
            'to' => [['email' => $mailbox->email]],
            'subject' => $subject,
            'text_body' => $subject,
            'is_read' => $isRead,
            'received_at' => $receivedAt,
        ]);
    }
}
