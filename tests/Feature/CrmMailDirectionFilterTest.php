<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMailDirectionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_threads_are_filtered_by_the_direction_of_the_latest_message(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'direction@kotlov.by',
            'username' => 'direction@kotlov.by',
            'is_enabled' => true,
        ]);
        $incomingThread = $this->thread($mailbox, 'incoming@example.com');
        $outgoingThread = $this->thread($mailbox, 'outgoing@example.com');

        $this->message($incomingThread, 'outgoing', 1);
        $this->message($incomingThread, 'incoming', 2);
        $this->message($outgoingThread, 'incoming', 3);
        $this->message($outgoingThread, 'outgoing', 4);

        $this->assertEquals(
            [$incomingThread->id],
            CrmMailThread::query()->latestMessageDirection('incoming')->pluck('id')->all(),
        );
        $this->assertEquals(
            [$outgoingThread->id],
            CrmMailThread::query()->latestMessageDirection('outgoing')->pluck('id')->all(),
        );

        $incomingThread = CrmMailThreadResource::getEloquentQuery()->findOrFail($incomingThread->id);
        $outgoingThread = CrmMailThreadResource::getEloquentQuery()->findOrFail($outgoingThread->id);
        $this->assertSame('Входящее · прочитано', CrmMailThreadResource::mailReadLabel($incomingThread));
        $this->assertSame('Исходящее · прочитано', CrmMailThreadResource::mailReadLabel($outgoingThread));
    }

    public function test_mail_badges_count_messages_and_navigation_badge_counts_only_unread_mail(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'KOTLOV',
            'email' => 'counters@kotlov.by',
            'username' => 'counters@kotlov.by',
            'is_enabled' => true,
        ]);

        $activeThread = $this->thread($mailbox, 'active@example.com');
        $attentionWithoutUnread = $this->thread($mailbox, 'attention@example.com');
        $attentionWithoutUnread->update(['status' => 'new', 'unread_count' => 7]);
        $spamThread = $this->thread($mailbox, 'spam@example.com');
        $spamThread->update(['status' => 'spam']);

        $this->message($activeThread, 'incoming', 1, false);
        $this->message($activeThread, 'incoming', 2, false);
        $this->message($activeThread, 'outgoing', 3);
        $this->message($attentionWithoutUnread, 'outgoing', 4);
        $this->message($spamThread, 'incoming', 5, false);

        $this->assertSame('2', CrmMailThreadResource::getNavigationBadge());
        $this->assertSame([
            'inbox' => 3,
            'unread' => 2,
            'incoming' => 2,
            'drafts' => 0,
            'sent' => 2,
            'spam' => 1,
            'trash' => 0,
            'all' => 5,
        ], ListCrmMailThreads::messageBadgeCounts());
    }

    private function thread(CrmMailbox $mailbox, string $email): CrmMailThread
    {
        return CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', $email),
            'participant_name' => $email,
            'participant_email' => $email,
            'subject' => 'Тест направления',
            'status' => 'in_progress',
            'messages_count' => 2,
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    private function message(CrmMailThread $thread, string $direction, int $minute, bool $isRead = true): void
    {
        CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $thread->mailbox_id,
            'direction' => $direction,
            'from_email' => $direction === 'incoming' ? $thread->participant_email : 'direction@kotlov.by',
            'to' => [],
            'subject' => 'Тест направления',
            'text_body' => 'Текст',
            'is_read' => $isRead,
            'received_at' => now()->addMinutes($minute),
        ]);
    }
}
