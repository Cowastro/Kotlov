<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
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

    private function message(CrmMailThread $thread, string $direction, int $minute): void
    {
        CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $thread->mailbox_id,
            'direction' => $direction,
            'from_email' => $direction === 'incoming' ? $thread->participant_email : 'direction@kotlov.by',
            'to' => [],
            'subject' => 'Тест направления',
            'text_body' => 'Текст',
            'is_read' => true,
            'received_at' => now()->addMinutes($minute),
        ]);
    }
}
