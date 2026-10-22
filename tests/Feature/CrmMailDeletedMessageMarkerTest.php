<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmMailDeletedMessageMarkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_force_deleting_a_thread_remembers_its_imap_uids(): void
    {
        $mailbox = CrmMailbox::query()->create([
            'name' => 'Test',
            'email' => 'mail-delete-test@example.com',
            'username' => 'mail-delete-test@example.com',
        ]);
        $thread = CrmMailThread::query()->create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'deleted-message'),
            'participant_email' => 'spam@example.com',
            'status' => 'spam',
        ]);
        $message = CrmMailMessage::query()->create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'message_id' => 'deleted-message@example.com',
            'imap_uid' => 7788,
            'direction' => 'incoming',
            'from_email' => 'spam@example.com',
            'is_read' => true,
        ]);

        $thread->delete();
        $thread->forceDelete();

        $this->assertDatabaseMissing('crm_mail_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('crm_mail_messages', ['id' => $message->id]);
        $this->assertDatabaseHas('crm_mail_deleted_messages', [
            'mailbox_id' => $mailbox->id,
            'imap_uid' => 7788,
            'message_id' => 'deleted-message@example.com',
        ]);
        $this->assertDatabaseHas('crm_mail_blocked_senders', [
            'mailbox_id' => $mailbox->id,
            'email' => 'spam@example.com',
        ]);
        $this->assertTrue(CrmMailThread::senderIsBlocked($mailbox->id, 'SPAM@example.com'));
    }
}
