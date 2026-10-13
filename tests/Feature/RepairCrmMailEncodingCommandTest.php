<?php

namespace Tests\Feature;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairCrmMailEncodingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_previously_stored_encoded_subjects(): void
    {
        $mailbox = CrmMailbox::query()->firstOrFail();
        $subject = 'Запрос по котлу';
        $encoded = '=?utf-8?B?'.base64_encode($subject).'?=';
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'client@example.com|encoded'),
            'participant_email' => 'client@example.com',
            'subject' => $encoded,
        ]);
        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'from_email' => 'client@example.com',
            'subject' => $encoded,
        ]);

        $this->artisan('crm:mail-repair-encoding')->assertSuccessful();

        $this->assertSame($subject, $thread->fresh()->subject);
        $this->assertSame($subject, $message->fresh()->subject);
    }

    public function test_dry_run_does_not_change_stored_subject(): void
    {
        $mailbox = CrmMailbox::query()->firstOrFail();
        $encoded = '=?utf-8?B?'.base64_encode('Тема').'?=';
        $thread = CrmMailThread::create([
            'mailbox_id' => $mailbox->id,
            'thread_key' => hash('sha256', 'client@example.com|dry-run'),
            'participant_email' => 'client@example.com',
            'subject' => $encoded,
        ]);

        $this->artisan('crm:mail-repair-encoding --dry-run')->assertSuccessful();

        $this->assertSame($encoded, $thread->fresh()->subject);
    }
}
