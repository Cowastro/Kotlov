<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Services\CrmMail\ImapMailboxSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class ImapMailboxSynchronizerDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_message_id_is_recognized_when_imap_uid_changed(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'Inbox',
            'email' => 'inbox@example.com',
            'username' => 'inbox@example.com',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'duplicate-message'),
            'participant_email' => 'sender@example.com',
            'status' => 'new',
        ]);
        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'message_id' => 'stable-id@example.com',
            'imap_uid' => 100,
            'direction' => 'incoming',
            'from_email' => 'sender@example.com',
        ]);

        $synchronizer = (new ReflectionClass(ImapMailboxSynchronizer::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($synchronizer, 'existingMessageUnderLock');

        $existing = $method->invoke($synchronizer, $mailbox, 200, 'stable-id@example.com');

        $this->assertSame($message->id, $existing?->id);
        $this->assertSame(1, CrmMailMessage::query()->count());
    }

    public function test_same_uid_is_recognized_without_message_id(): void
    {
        $mailbox = CrmMailbox::create([
            'name' => 'Inbox',
            'email' => 'inbox@example.com',
            'username' => 'inbox@example.com',
        ]);
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'same-uid'),
            'participant_email' => 'sender@example.com',
            'status' => 'new',
        ]);
        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'imap_uid' => 300,
            'direction' => 'incoming',
            'from_email' => 'sender@example.com',
        ]);

        $synchronizer = (new ReflectionClass(ImapMailboxSynchronizer::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($synchronizer, 'existingMessageUnderLock');

        $existing = $method->invoke($synchronizer, $mailbox, 300, null);

        $this->assertSame($message->id, $existing?->id);
    }
}
