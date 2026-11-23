<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailThreadResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmMailLongSubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_unicode_subjects_are_preserved_and_distinct_requests_are_not_merged(): void
    {
        foreach (['crm_mail_threads', 'crm_mail_messages'] as $table) {
            $this->assertContains(Schema::getColumnType($table, 'subject'), ['text', 'longtext']);
        }

        $mailbox = CrmMailbox::create([
            'name' => 'Inbox', 'email' => 'inbox@example.com', 'username' => 'inbox@example.com',
        ]);
        $prefix = str_repeat('Подбор оборудования 🔧 ', 30);
        foreach (['первый запрос', 'второй запрос'] as $suffix) {
            $subject = $prefix.$suffix;
            $thread = CrmMailThread::create([
                'mailbox_id' => $mailbox->id,
                'thread_key' => hash('sha256', $subject),
                'participant_email' => 'sender@example.com',
                'subject' => $subject,
            ]);
            $message = CrmMailMessage::create([
                'mailbox_id' => $mailbox->id, 'thread_id' => $thread->id,
                'direction' => 'incoming', 'from_email' => 'sender@example.com',
                'message_id' => hash('sha256', $subject).'@example.com', 'subject' => $subject,
            ]);

            $this->assertSame($subject, $thread->fresh()->subject);
            $this->assertSame($subject, $message->fresh()->subject);
            $resolved = app(CrmMailThreadResolver::class)->resolve(
                $mailbox, 'sender@example.com', null, null, 'Re: '.$subject,
            );
            $this->assertSame($thread->id, $resolved?->id);
        }
        $this->assertSame(2, CrmMailThread::count());
        $this->assertSame(2, CrmMailMessage::count());
    }
}
